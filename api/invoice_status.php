<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Only administrators may change invoice status
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : null;
$newStatus = $data['status'] ?? null;
if (!$id || !in_array($newStatus, ['paid', 'unpaid'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing or invalid id/status']);
    exit;
}

$stmt = $pdo->prepare('SELECT status, total FROM invoices WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}
$prevStatus = $row['status'];
$total = (float)$row['total'];
if ($prevStatus === $newStatus) {
    echo json_encode(['success' => true]);
    exit;
}

$update = $pdo->prepare('UPDATE invoices SET status = ? WHERE id = ?');
$update->execute([$newStatus, $id]);

// Log status change
$balanceBefore = $prevStatus === 'paid' ? 0.0 : $total;
$balanceAfter = $newStatus === 'paid' ? 0.0 : $total;
$log = $pdo->prepare('INSERT INTO change_log (invoice_id,event_type,prev_value,new_value,reason,balance_before,balance_after,user_id,ip_address) VALUES (?,?,?,?,?,?,?,?,?)');
$log->execute([$id, 'status_change', $prevStatus, $newStatus, 'manual', $balanceBefore, $balanceAfter, $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

echo json_encode(['success' => true]);
exit;
?>