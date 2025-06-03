<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : null;
    $rawStart = $_GET['start_date'] ?? '';
    $rawEnd = $_GET['end_date'] ?? '';

    if (!$customerId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing customer parameter']);
        exit;
    }
    // Ensure non-admin users only access assigned customers
    if (!is_admin()) {
        $chk = $pdo->prepare('SELECT 1 FROM user_customers WHERE user_id = ? AND customer_id = ?');
        $chk->execute([$_SESSION['user_id'], $customerId]);
        if (!$chk->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
    }
    // Validate date formats if provided
    if ($rawStart !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawStart)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid start_date format']);
        exit;
    }
    if ($rawEnd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid end_date format']);
        exit;
    }

    $start = $rawStart;
    $end = $rawEnd;
    // Default start_date to Jan 1st of year of first event if not specified
    if ($start === '') {
        $stmt = $pdo->prepare(
            'SELECT MIN(dt) AS dt FROM ('
          . ' SELECT MIN(transaction_date) AS dt FROM pko_payments WHERE customer_id = ?'
          . ' UNION ALL'
          . ' SELECT MIN(date) AS dt FROM invoices WHERE customer_id = ?'
          . ') AS t'
        );
        $stmt->execute([$customerId, $customerId]);
        $minDt = $stmt->fetchColumn();
        if ($minDt) {
            $start = date('Y-01-01', strtotime($minDt));
        } else {
            $start = date('Y-01-01');
        }
    }
    // Default end_date to today if not specified
    if ($end === '') {
        $end = date('Y-m-d');
    }

  $stmt = $pdo->prepare(
    'SELECT id, transaction_date, account_number, amount, currency, sender, title'
  . ' FROM pko_payments'
  . ' WHERE customer_id = ? AND transaction_date BETWEEN ? AND ?'
  . ' ORDER BY transaction_date ASC'
  );
  $stmt->execute([$customerId, $start, $end]);
  $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt2 = $pdo->prepare(
    'SELECT date AS invoice_date, invoice_number, total AS amount, currency'
  . ' FROM invoices'
  . ' WHERE customer_id = ? AND date BETWEEN ? AND ?'
  . ' ORDER BY date ASC'
  );
  $stmt2->execute([$customerId, $start, $end]);
  $invoices = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'start_date' => $start,
        'end_date' => $end,
        'payments' => $payments,
        'invoices' => $invoices
    ], JSON_UNESCAPED_UNICODE);

  exit;