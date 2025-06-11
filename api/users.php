<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

// Only administrators may manage users
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        try {
            // Fetch users and their assigned customers
            $stmt = $pdo->query('SELECT id, username, disabled, is_admin FROM users');
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($users as &$u) {
                $stmt2 = $pdo->prepare('SELECT customer_id FROM user_customers WHERE user_id = ?');
                $stmt2->execute([$u['id']]);
                $u['customer_ids'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
            }
            echo json_encode($users);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (username, password, disabled, is_admin) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $data['username'],
                $hash,
                $data['disabled'] ? 1 : 0,
                $data['is_admin'] ? 1 : 0
            ]);
            $id = $pdo->lastInsertId();
            // Assign customers if provided
            if (!empty($data['customer_ids']) && is_array($data['customer_ids'])) {
                $ins = $pdo->prepare('INSERT OR IGNORE INTO user_customers (user_id, customer_id) VALUES (?, ?)');
                foreach ($data['customer_ids'] as $cid) {
                    $ins->execute([$id, $cid]);
                }
            }
            http_response_code(201);
            header('Location: /api/users.php?id=' . $id);
            echo json_encode(['id' => $id]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['id']) || !is_numeric($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID.']);
            break;
        }
        try {
            if (!empty($data['password'])) {
                $hash = password_hash($data['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET username = ?, password = ?, disabled = ?, is_admin = ? WHERE id = ?');
                $stmt->execute([
                    $data['username'],
                    $hash,
                    $data['disabled'] ? 1 : 0,
                    $data['is_admin'] ? 1 : 0,
                    (int)$data['id']
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE users SET username = ?, disabled = ?, is_admin = ? WHERE id = ?');
                $stmt->execute([
                    $data['username'],
                    $data['disabled'] ? 1 : 0,
                    $data['is_admin'] ? 1 : 0,
                    (int)$data['id']
                ]);
            }
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found.']);
                break;
            }
            // Update customer assignments
            if (isset($data['customer_ids']) && is_array($data['customer_ids'])) {
                $del = $pdo->prepare('DELETE FROM user_customers WHERE user_id = ?');
                $del->execute([(int)$data['id']]);
                $ins = $pdo->prepare('INSERT OR IGNORE INTO user_customers (user_id, customer_id) VALUES (?, ?)');
                foreach ($data['customer_ids'] as $cid) {
                    $ins->execute([(int)$data['id'], $cid]);
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
        $id = $_GET['id'] ?? null;
        if (!is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid user ID.']);
            break;
        }
        if ((int)$id === ($_SESSION['user_id'] ?? 0)) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot delete current user']);
            break;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([(int)$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'User not found.']);
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