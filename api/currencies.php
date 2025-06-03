<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Only administrators may modify currencies; non-admins can only view
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $stmt = $pdo->query("SELECT code FROM currencies ORDER BY code");
        $currencies = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        echo json_encode($currencies);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['code']) || !preg_match('/^[A-Z]{3}$/', $data['code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid code']);
            break;
        }
        $stmt = $pdo->prepare("INSERT INTO currencies (code) VALUES (?)");
        $stmt->execute([$data['code']]);
        echo json_encode(['code' => $data['code']]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['old_code']) || empty($data['new_code']) || !preg_match('/^[A-Z]{3}$/', $data['new_code'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid payload']);
            break;
        }
        $stmt = $pdo->prepare("UPDATE currencies SET code = ? WHERE code = ?");
        $stmt->execute([$data['new_code'], $data['old_code']]);
        echo json_encode(['code' => $data['new_code']]);
        break;
    case 'DELETE':
        $code = $_GET['code'] ?? null;
        if ($code) {
            $stmt = $pdo->prepare("DELETE FROM currencies WHERE code = ?");
            $stmt->execute([$code]);
        }
        echo json_encode(['success' => true]);
        break;
}