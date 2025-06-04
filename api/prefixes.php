<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM customer_prefixes WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } elseif (isset($_GET['customer_id'])) {
            $stmt = $pdo->prepare('SELECT * FROM customer_prefixes WHERE customer_id = ?');
            $stmt->execute([$_GET['customer_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->query('SELECT * FROM customer_prefixes');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('INSERT INTO customer_prefixes (customer_id, entity, property, prefix) VALUES (?,?,?,?)');
        $stmt->execute([
            $data['customer_id'], $data['entity'], $data['property'], $data['prefix']
        ]);
        echo json_encode(['id' => $pdo->lastInsertId()]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('UPDATE customer_prefixes SET entity = ?, property = ?, prefix = ? WHERE id = ?');
        $stmt->execute([
            $data['entity'], $data['property'], $data['prefix'], $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare('DELETE FROM customer_prefixes WHERE id = ?');
            $stmt->execute([$_GET['id']]);
        } elseif (isset($_GET['customer_id'])) {
            $stmt = $pdo->prepare('DELETE FROM customer_prefixes WHERE customer_id = ?');
            $stmt->execute([$_GET['customer_id']]);
        }
        echo json_encode(['success' => true]);
        break;
}