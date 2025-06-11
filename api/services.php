<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
require_once __DIR__ . '/../db.php';
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid service ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
                $stmt->execute([$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } else {
            try {
                $stmt = $pdo->query('SELECT * FROM services');
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
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload.']);
            break;
        }
        if (empty($data['description'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Service description is required.']);
            break;
        }
        if (!isset($data['unit_price']) || !is_numeric($data['unit_price'])) {
            http_response_code(422);
            echo json_encode(['error' => 'A valid unit price is required.']);
            break;
        }
        if (empty($data['currency'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Currency is required.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO services (description,unit_price,currency) VALUES (?,?,?)');
            $stmt->execute([
                $data['description'], $data['unit_price'], $data['currency']
            ]);
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            header('Location: /api/services.php?id=' . $newId);
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
            echo json_encode(['error' => 'Invalid request payload or missing service ID.']);
            break;
        }
        if (empty($data['description'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Service description is required.']);
            break;
        }
        if (!isset($data['unit_price']) || !is_numeric($data['unit_price'])) {
            http_response_code(422);
            echo json_encode(['error' => 'A valid unit price is required.']);
            break;
        }
        if (empty($data['currency'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Currency is required.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('UPDATE services SET description=?,unit_price=?,currency=? WHERE id=?');
            $stmt->execute([
                $data['description'], $data['unit_price'], $data['currency'], $data['id']
            ]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Service not found.']);
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
        $id = $_GET['id'] ?? null;
        if (!isset($id) || !is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid service ID.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM services WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Service not found.']);
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