<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $data = json_decode(file_get_contents('php://input'), true);
    $id = isset($data['id']) ? (int)$data['id'] : null;
    $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? (int)$data['customer_id'] : null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment id']);
        exit;
    }
    // Log manual customer assignment for the payment
    $prevStmt = $pdo->prepare('SELECT customer_id FROM pko_payments WHERE id = ?');
    $prevStmt->execute([$id]);
    $prevCust = $prevStmt->fetchColumn();
    $stmt = $pdo->prepare('UPDATE pko_payments SET customer_id = ? WHERE id = ?');
    $stmt->execute([$customerId, $id]);
    if ($prevCust != $customerId) {
        $log = $pdo->prepare('INSERT INTO change_log (payment_id,event_type,prev_value,new_value,reason,user_id,ip_address) VALUES (?,?,?,?,?,?,?)');
        $log->execute([$id, 'payment_assignment', $prevCust, $customerId, 'manual', $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

	$allowedSortBy = ['received_at', 'transaction_date', 'account_number', 'amount', 'sender'];
	$allowedSortDir = ['asc', 'desc'];
	$sortBy = $_GET['sort_by'] ?? 'transaction_date';
	if (!in_array($sortBy, $allowedSortBy, true)) {
	    $sortBy = 'transaction_date';
	}
	$sortDir = strtolower($_GET['sort_dir'] ?? 'desc');
	if (!in_array($sortDir, $allowedSortDir, true)) {
	    $sortDir = 'desc';
	}
	// Restrict to assigned customers for non-admin users
	$userId = $_SESSION['user_id'];
	if (!is_admin()) {
	    $stmtUc = $pdo->prepare('SELECT customer_id FROM user_customers WHERE user_id = ?');
	    $stmtUc->execute([$userId]);
	    $assigned = $stmtUc->fetchAll(PDO::FETCH_COLUMN);
	}
	// Build base query
	$base = 'SELECT id, customer_id, received_at, transaction_date, account_number, amount, currency, sender, title FROM pko_payments';
	$params = [];
	if (!is_admin()) {
	    if (empty($assigned)) {
	        echo json_encode([]);
	        exit;
	    }
	    $placeholders = implode(',', array_fill(0, count($assigned), '?'));
	    $base .= " WHERE customer_id IN ({$placeholders})";
	    $params = $assigned;
	}
	$sql = $base . sprintf(' ORDER BY %s %s', $sortBy, strtoupper($sortDir));
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
$jsonOptions = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonOptions |= JSON_INVALID_UTF8_SUBSTITUTE;
}
$json = json_encode($payments, $jsonOptions);
if ($json === false) {
    $payments = array_map(function ($row) {
        return array_map(function ($value) {
            return is_string($value)
                ? iconv('UTF-8', 'UTF-8//IGNORE', $value)
                : $value;
        }, $row);
    }, $payments);
    $json = json_encode($payments, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        echo json_encode(['error' => json_last_error_msg()]);
        exit;
    }
}
echo $json;
