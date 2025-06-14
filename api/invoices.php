<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$method = $_SERVER['REQUEST_METHOD'];
// Restrict write operations to administrators only
if (!is_admin() && $method !== 'GET') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
$historyDir = PRIVATE_DIR_PATH.'/invoices_history/';
if (!is_dir($historyDir)) {
    mkdir($historyDir, 0755, true);
}
$signedDir = PRIVATE_DIR_PATH.'/invoices_signed/';
if (!is_dir($signedDir)) {
    mkdir($signedDir, 0755, true);
}
switch ($method) {
    case 'GET':
        if (isset($_GET['customer_id'])) {
            $customerId = (int)$_GET['customer_id'];
            if (!is_admin()) {
                // Ensure customer is assigned to user
                $chk = $pdo->prepare('SELECT 1 FROM user_customers WHERE user_id = ? AND customer_id = ?');
                $chk->execute([$_SESSION['user_id'], $customerId]);
                if (!$chk->fetch()) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Forbidden']);
                    exit;
                }
            }
            $stmt = $pdo->prepare(
                'SELECT id,invoice_number,date,month_service,status,currency,subtotal,tax,total,company_id,contract_id,(SELECT name FROM companies WHERE id=invoices.company_id) AS company_name'
                . ' FROM invoices WHERE customer_id = ? ORDER BY date DESC'
            );
            $stmt->execute([$customerId]);
            $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($invoices as &$inv) {
                if (is_admin()) {
                    $inv['history'] = [];
                    if (is_dir($historyDir)) {
                        $pattern = $historyDir . "*_{$inv['invoice_number']}_*.html";
                        $files = glob($pattern);
                        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $inv['history'] = array_map('basename', array_slice($files, 0, 3));
                    }
                }
                $inv['signed_file'] = '';
                if (is_dir($signedDir)) {
                    $pattern = $signedDir . "*_{$inv['id']}.pdf";
                    $files = glob($pattern);
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    if ($files) {
                        $inv['signed_file'] = basename($files[0]);
                    }
                }
            }
            echo json_encode($invoices);
        } elseif (isset($_GET['id'])) {
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
            $stmt->execute([$id]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($inv) {
                if (!is_admin() && $inv['customer_id']) {
                    // Ensure user is assigned to this invoice's customer
                    $chk = $pdo->prepare('SELECT 1 FROM user_customers WHERE user_id = ? AND customer_id = ?');
                    $chk->execute([$_SESSION['user_id'], $inv['customer_id']]);
                    if (!$chk->fetch()) {
                        http_response_code(403);
                        echo json_encode(['error' => 'Forbidden']);
                        exit;
                    }
                }
                $inv['items'] = json_decode($inv['items'], true);
                $inv['company_details'] = json_decode($inv['company_details'], true);
                $inv['signed_file'] = '';
                if (is_dir($signedDir)) {
                    $pattern = $signedDir . "*_{$inv['id']}.pdf";
                    $files = glob($pattern);
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    if ($files) {
                        $inv['signed_file'] = basename($files[0]);
                    }
                }
            }
            echo json_encode($inv);
        } else {
            if (is_admin()) {
                $stmt = $pdo->query(
                'SELECT id,invoice_number,date,month_service,status,currency,subtotal,tax,total,customer_id,company_id,contract_id,(SELECT name FROM companies WHERE id=invoices.company_id) AS company_name'
                . ' FROM invoices ORDER BY date DESC'
                );
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // List only invoices for assigned customers
                $stmtUc = $pdo->prepare('SELECT customer_id FROM user_customers WHERE user_id = ?');
                $stmtUc->execute([$_SESSION['user_id']]);
                $assigned = $stmtUc->fetchAll(PDO::FETCH_COLUMN);
                if (empty($assigned)) {
                    echo json_encode([]);
                    exit;
                }
                $placeholders = implode(',', array_fill(0, count($assigned), '?'));
            $stmt = $pdo->prepare(
                "SELECT id,invoice_number,date,month_service,status,currency,subtotal,tax,total,customer_id,company_id,contract_id,"
                ."(SELECT name FROM companies WHERE id=invoices.company_id) AS company_name,"
                ."(SELECT name FROM customers WHERE id=invoices.customer_id) AS customer_name"
                ." FROM invoices WHERE customer_id IN ({$placeholders}) ORDER BY date DESC"
            );
                $stmt->execute($assigned);
                $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            foreach ($invoices as &$inv) {
                if (is_admin()) {
                    $inv['history'] = [];
                    if (is_dir($historyDir)) {
                        $pattern = $historyDir . "*_{$inv['invoice_number']}_*.html";
                        $files = glob($pattern);
                        usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                        $inv['history'] = array_map('basename', array_slice($files, 0, 3));
                    }
                }
                $inv['signed_file'] = '';
                if (is_dir($signedDir)) {
                    $pattern = $signedDir . "*_{$inv['id']}.pdf";
                    $files = glob($pattern);
                    usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                    if ($files) {
                        $inv['signed_file'] = basename($files[0]);
                    }
                }
            }
            echo json_encode($invoices);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        // Ensure subtotal, tax and total are stored with 2 decimal places
        $data['subtotal'] = number_format((float)($data['subtotal'] ?? 0), 2, '.', '');
        $data['tax']      = number_format((float)($data['tax'] ?? 0), 2, '.', '');
        $data['total']    = number_format((float)($data['total'] ?? 0), 2, '.', '');
        $stmt = $pdo->prepare(
            'INSERT INTO invoices (
                customer_id,company_id,contract_id,invoice_number,date,month_service,status,template,
                currency,vat_rate,items,company_details,subtotal,tax,total
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $dbSaved = false;
        try {
        $stmt->execute([
            $data['customer_id'], $data['company_id'], $data['contract_id'] ?? null, $data['invoice_number'], $data['date'], $data['month_service'], $data['status'], $data['template'],
            $data['currency'], $data['vat_rate'], json_encode($data['items']),
            json_encode($data['company_details']), $data['subtotal'], $data['tax'], $data['total'],
        ]);
            $newId = $pdo->lastInsertId();
            $dbSaved = true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
            break;
        }
        $fileSaved = false;
        $fileError = null;
        if ($dbSaved && !empty($data['preview_html'])) {
            if (!is_dir($historyDir)) {
                mkdir($historyDir, 0755, true);
            }
            $invoiceDate = date('Y-m-d', strtotime($data['date']));
            $customerName = $data['customer_name'] ?? '';
            if (empty($customerName) && !empty($data['customer_id'])) {
                $stmt2 = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
                $stmt2->execute([$data['customer_id']]);
                $customerName = $stmt2->fetchColumn() ?: '';
            }
            $customerSlug = preg_replace('/[^A-Za-z0-9]+/', '-', trim(substr($customerName, 0, 30)));
            $invoiceNo = $data['invoice_number'];
            $saveTimestamp = date('Y-m-d_H-i-s');
            $filename = "{$invoiceDate}_{$customerSlug}_{$invoiceNo}_{$saveTimestamp}_{$id}.html";
            try {
                // Ensure the <title> tag matches the history filename (without .html)
                $basename    = pathinfo($filename, PATHINFO_FILENAME);
                $htmlContent = $data['preview_html'];
                if (preg_match('/<title>.*<\/title>/i', $htmlContent)) {
                    $htmlContent = preg_replace(
                        '/<title>.*<\/title>/i',
                        "<title>{$basename}</title>",
                        $htmlContent,
                        1
                    );
                } else {
                    $htmlContent = preg_replace(
                        '/<head(\s*[^>]*)>/i',
                        "<head$1><title>{$basename}</title>",
                        $htmlContent,
                        1
                    );
                }
                $bytes = @file_put_contents($historyDir . $filename, $htmlContent);
                if ($bytes === false) {
                    throw new Exception("Unable to write history file: {$historyDir}{$filename}");
                }
                $fileSaved = true;
            } catch (Exception $e) {
                $fileError = $e->getMessage();
            }
        }
        http_response_code(201);
        header('Location: /api/invoices.php?id=' . $newId);
        echo json_encode([
            'id' => $newId,
            'db_saved'   => $dbSaved,
            'file_saved' => $fileSaved,
            'file_error' => $fileError,
        ]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        // Ensure subtotal, tax and total are stored with 2 decimal places
        $data['subtotal'] = number_format((float)($data['subtotal'] ?? 0), 2, '.', '');
        $data['tax']      = number_format((float)($data['tax'] ?? 0), 2, '.', '');
        $data['total']    = number_format((float)($data['total'] ?? 0), 2, '.', '');
        $stmt = $pdo->prepare(
            'UPDATE invoices SET
                customer_id=?,company_id=?,contract_id=?,invoice_number=?,date=?,month_service=?,status=?,template=?,
                currency=?,vat_rate=?,items=?,company_details=?,subtotal=?,tax=?,total=?
             WHERE id=?'
        );
        if (empty($data['id']) || !is_numeric($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid invoice ID.']);
            break;
        }
        $dbSaved = false;
        try {
            $stmt->execute([
                $data['customer_id'], $data['company_id'], $data['contract_id'] ?? null, $data['invoice_number'], $data['date'], $data['month_service'], $data['status'], $data['template'],
                $data['currency'], $data['vat_rate'], json_encode($data['items']),
                json_encode($data['company_details']), $data['subtotal'], $data['tax'], $data['total'],
                (int)$data['id'],
            ]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Invoice not found.']);
                break;
            }
            $dbSaved = true;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
            break;
        }
        $fileSaved = false;
        $fileError = null;
        if (!empty($data['preview_html'])) {
            if (!is_dir($historyDir)) {
                mkdir($historyDir, 0755, true);
            }
            $invoiceDate = date('Y-m-d', strtotime($data['date']));
            $customerName = $data['customer_name'] ?? '';
            if (empty($customerName) && !empty($data['customer_id'])) {
                $stmt2 = $pdo->prepare('SELECT name FROM customers WHERE id = ?');
                $stmt2->execute([$data['customer_id']]);
                $customerName = $stmt2->fetchColumn() ?: '';
            }
            $customerSlug = preg_replace('/[^A-Za-z0-9]+/', '-', trim(substr($customerName, 0, 30)));
            $invoiceNo = $data['invoice_number'];
            $saveTimestamp = date('Y-m-d_H-i-s');
            $filename = "{$invoiceDate}_{$customerSlug}_{$invoiceNo}_{$saveTimestamp}_{$data['id']}.html";
            try {
                // Ensure the <title> tag matches the history filename (without .html)
                $basename    = pathinfo($filename, PATHINFO_FILENAME);
                $htmlContent = $data['preview_html'];
                if (preg_match('/<title>.*<\/title>/i', $htmlContent)) {
                    $htmlContent = preg_replace(
                        '/<title>.*<\/title>/i',
                        "<title>{$basename}</title>",
                        $htmlContent,
                        1
                    );
                } else {
                    $htmlContent = preg_replace(
                        '/<head(\s*[^>]*)>/i',
                        "<head$1><title>{$basename}</title>",
                        $htmlContent,
                        1
                    );
                }
                $bytes = @file_put_contents($historyDir . $filename, $htmlContent);
                if ($bytes === false) {
                    throw new Exception("Unable to write history file: {$historyDir}{$filename}");
                }
                $fileSaved = true;
            } catch (Exception $e) {
                $fileError = $e->getMessage();
            }
        }
        echo json_encode([
            'db_saved'   => $dbSaved,
            'file_saved' => $fileSaved,
            'file_error' => $fileError,
        ]);
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if (!is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid invoice ID.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM invoices WHERE id = ?');
            $stmt->execute([(int)$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Invoice not found.']);
            } else {
                http_response_code(204);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
}
