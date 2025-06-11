<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Only administrators may view change log
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$start = $_GET['start_date'] ?? null;
$end = $_GET['end_date'] ?? null;
$sql =
    'SELECT cl.*, inv.invoice_number, cust.name AS customer_name, '
  . 'pay.id AS payment_id, pay.transaction_date AS payment_date, pay.amount AS payment_amount, '
  . 'pay.currency AS payment_currency, pay.sender AS payment_sender, pay.title AS payment_title, '
  . 'usr.username AS user_name, '
  . 'COALESCE(prev_cust.name, cl.prev_value) AS prev_value, '
  . 'COALESCE(new_cust.name, cl.new_value) AS new_value'
  . ' FROM change_log cl'
  . ' LEFT JOIN invoices inv ON cl.invoice_id = inv.id'
  . ' LEFT JOIN customers cust ON inv.customer_id = cust.id'
  . ' LEFT JOIN pko_payments pay ON cl.payment_id = pay.id'
  . ' LEFT JOIN users usr ON cl.user_id = usr.id'
  . ' LEFT JOIN customers prev_cust ON cl.event_type = \'payment_assignment\' AND prev_cust.id = cl.prev_value'
  . ' LEFT JOIN customers new_cust ON cl.event_type = \'payment_assignment\' AND new_cust.id = cl.new_value';
$params = [];
$where = [];
if ($start) {
    $where[] = 'date(cl.change_date) >= date(?)';
    $params[] = $start;
}
if ($end) {
    $where[] = 'date(cl.change_date) <= date(?)';
    $params[] = $end;
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY cl.change_date DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows);