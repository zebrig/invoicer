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
        // Fetch users and their assigned customers
        $stmt = $pdo->query('SELECT id, username, disabled, is_admin FROM users');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as &$u) {
            $stmt2 = $pdo->prepare('SELECT customer_id FROM user_customers WHERE user_id = ?');
            $stmt2->execute([$u['id']]);
            $u['customer_ids'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        }
        echo json_encode($users);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
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
        echo json_encode(['id' => $id]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!empty($data['password'])) {
            $hash = password_hash($data['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET username = ?, password = ?, disabled = ?, is_admin = ? WHERE id = ?');
            $stmt->execute([
                $data['username'],
                $hash,
                $data['disabled'] ? 1 : 0,
                $data['is_admin'] ? 1 : 0,
                $data['id']
            ]);
        } else {
            $stmt = $pdo->prepare('UPDATE users SET username = ?, disabled = ?, is_admin = ? WHERE id = ?');
            $stmt->execute([
                $data['username'],
                $data['disabled'] ? 1 : 0,
                $data['is_admin'] ? 1 : 0,
                $data['id']
            ]);
        }
        // Update customer assignments
        if (isset($data['customer_ids']) && is_array($data['customer_ids'])) {
            $del = $pdo->prepare('DELETE FROM user_customers WHERE user_id = ?');
            $del->execute([$data['id']]);
            $ins = $pdo->prepare('INSERT OR IGNORE INTO user_customers (user_id, customer_id) VALUES (?, ?)');
            foreach ($data['customer_ids'] as $cid) {
                $ins->execute([$data['id'], $cid]);
            }
        }
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id === ($_SESSION['user_id'] ?? 0)) {
            echo json_encode(['error' => 'Cannot delete current user']);
            break;
        }
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        break;
}