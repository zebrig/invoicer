<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid prefix ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare('SELECT * FROM customer_prefixes WHERE id = ?');
                $stmt->execute([(int)$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } elseif (isset($_GET['customer_id'])) {
            if (!is_numeric($_GET['customer_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid customer ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare('SELECT * FROM customer_prefixes WHERE customer_id = ?');
                $stmt->execute([(int)$_GET['customer_id']]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } else {
            try {
                $stmt = $pdo->query('SELECT * FROM customer_prefixes');
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $stmt = $pdo->prepare('INSERT INTO customer_prefixes (customer_id, entity, property, prefix) VALUES (?,?,?,?)');
            $stmt->execute([
                $data['customer_id'], $data['entity'], $data['property'], $data['prefix']
            ]);
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            header('Location: /api/prefixes.php?id=' . $newId);
            echo json_encode(['id' => $newId]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id']) || !is_numeric($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid prefix ID.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('UPDATE customer_prefixes SET entity = ?, property = ?, prefix = ? WHERE id = ?');
            $stmt->execute([
                $data['entity'], $data['property'], $data['prefix'], (int)$data['id']
            ]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Prefix not found.']);
            } else {
                http_response_code(204);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid prefix ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare('DELETE FROM customer_prefixes WHERE id = ?');
                $stmt->execute([(int)$_GET['id']]);
                if ($stmt->rowCount() === 0) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Prefix not found.']);
                } else {
                    http_response_code(204);
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } elseif (isset($_GET['customer_id'])) {
            if (!is_numeric($_GET['customer_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid customer ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare('DELETE FROM customer_prefixes WHERE customer_id = ?');
                $stmt->execute([(int)$_GET['customer_id']]);
                http_response_code(200);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'No identifier provided']);
        }
        break;
}