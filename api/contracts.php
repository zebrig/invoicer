<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

# Handle file download
if (isset($_GET['download_file_id'])) {
    $fileId = (int)$_GET['download_file_id'];
    $stmt = $pdo->prepare('SELECT filename, data FROM contract_files WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$file) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
    if (preg_match('#^data:([^;]+);base64,(.+)$#', $file['data'], $m)) {
        $mime = $m[1];
        $body = base64_decode($m[2]);
    } else {
        $mime = 'application/octet-stream';
        $body = $file['data'];
    }
    header("Content-Type: {$mime}");
    if ($mime === 'application/pdf') {
        header('Content-Disposition: inline; filename="' . basename($file['filename']) . '"');
        header('Content-Length: ' . strlen($body));
    } else {
        header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
    }
    echo $body;
    exit;
}

header('Content-Type: application/json');

# Only administrators may modify contracts; non-admins can only view
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
            $stmt->execute([$id]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($contract) {
                $af = $pdo->prepare('SELECT id, filename FROM contract_files WHERE contract_id = ?');
                $af->execute([$id]);
                $contract['attachments'] = $af->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($contract ?: null);
        } else {
            $params = [];
            $wheres = [];
            if (!empty($_GET['customer_id']) && is_numeric($_GET['customer_id'])) {
                $wheres[] = 'contracts.customer_id = ?';
                $params[] = (int)$_GET['customer_id'];
            }
            if (!empty($_GET['company_id']) && is_numeric($_GET['company_id'])) {
                $wheres[] = 'contracts.company_id = ?';
                $params[] = (int)$_GET['company_id'];
            }
            if (!empty($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date'])) {
                $wheres[] = 'contracts.date >= ?';
                $params[] = $_GET['start_date'];
            }
            if (!empty($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date'])) {
                $wheres[] = 'contracts.date <= ?';
                $params[] = $_GET['end_date'];
            }
            if (!empty($_GET['search'])) {
                $wheres[] = '(contracts.name LIKE ? OR contracts.description LIKE ? OR contracts.date LIKE ? OR IFNULL(contracts.end_date,\'\') LIKE ? OR customers.name LIKE ? OR companies.name LIKE ?)';
                $s = '%' . $_GET['search'] . '%';
                $params = array_merge($params, [$s, $s, $s, $s, $s, $s]);
            }
            $sql = 'SELECT contracts.*, customers.name AS customer_name, companies.name AS company_name'
                 . ' FROM contracts'
                 . ' LEFT JOIN customers ON contracts.customer_id = customers.id'
                 . ' LEFT JOIN companies ON contracts.company_id = companies.id';
            if ($wheres) {
                $sql .= ' WHERE ' . implode(' AND ', $wheres);
            }
            $sql .= ' ORDER BY date DESC';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $cfStmt = $pdo->prepare('SELECT id, filename FROM contract_files WHERE contract_id = ?');
            foreach ($contracts as &$c) {
                $cfStmt->execute([$c['id']]);
                $c['attachments'] = $cfStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            unset($c);
            echo json_encode($contracts);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || empty($data['name']) || empty($data['date']) || empty($data['customer_id']) || empty($data['company_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO contracts (customer_id,company_id,date,end_date,name,description) VALUES (?,?,?,?,?,?)');
            $stmt->execute([
                (int)$data['customer_id'], (int)$data['company_id'], $data['date'],
                $data['end_date'] ?? null, $data['name'], $data['description'] ?? null
            ]);
            $newId = $pdo->lastInsertId();
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $af = $pdo->prepare('INSERT INTO contract_files (contract_id,filename,data) VALUES (?,?,?)');
                foreach ($data['attachments'] as $file) {
                    if (empty($file['filename']) || empty($file['data'])) {
                        continue;
                    }
                    $af->execute([$newId, $file['filename'], $file['data']]);
                }
            }
            http_response_code(201);
            header('Location: /api/contracts.php?id=' . $newId);
            echo json_encode(['id' => $newId]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['id']) || !is_numeric($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request payload or missing contract ID.']);
            exit;
        }
        $id = (int)$data['id'];
        $existingStmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ?');
        $existingStmt->execute([$id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Contract not found']);
            exit;
        }
        $fields = ['customer_id','company_id','date','end_date','name','description'];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                $data[$f] = $existing[$f];
            }
        }
        try {
            $stmt = $pdo->prepare('UPDATE contracts SET customer_id=?,company_id=?,date=?,end_date=?,name=?,description=? WHERE id=?');
            $stmt->execute([
                (int)$data['customer_id'], (int)$data['company_id'], $data['date'],
                $data['end_date'] ?? null, $data['name'], $data['description'] ?? null,
                $id
            ]);
            // handle attachments: keep existing, add new, remove missing
            $af = $pdo->prepare('SELECT id FROM contract_files WHERE contract_id = ?');
            $af->execute([$id]);
            $existingFiles = $af->fetchAll(PDO::FETCH_COLUMN);
            $keepIds = [];
            if (!empty($data['attachments']) && is_array($data['attachments'])) {
                $ins = $pdo->prepare('INSERT INTO contract_files (contract_id,filename,data) VALUES (?,?,?)');
                foreach ($data['attachments'] as $file) {
                    if (!empty($file['id']) && in_array($file['id'], $existingFiles, true)) {
                        $keepIds[] = $file['id'];
                    } elseif (!empty($file['data']) && !empty($file['filename'])) {
                        $ins->execute([$id, $file['filename'], $file['data']]);
                    }
                }
            }
            $toDelete = array_diff($existingFiles, $keepIds);
            if ($toDelete) {
                $del = $pdo->prepare('DELETE FROM contract_files WHERE id = ?');
                foreach ($toDelete as $fid) {
                    $del->execute([$fid]);
                }
            }
            http_response_code(204);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;

    case 'DELETE':
        $id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contract ID.']);
            exit;
        }
        try {
            $delF = $pdo->prepare('DELETE FROM contract_files WHERE contract_id = ?');
            $delF->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM contracts WHERE id = ?');
            $stmt->execute([$id]);
            http_response_code($stmt->rowCount() ? 204 : 404);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
}