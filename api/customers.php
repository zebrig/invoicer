<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

// Only administrators may modify customers; non-admins can only view assigned customers
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
        if ($id) {
            if (is_admin()) {
                $stmt = $pdo->prepare(
                    'SELECT customers.*, (SELECT COUNT(*) FROM invoices WHERE customer_id = customers.id) AS invoice_count FROM customers WHERE id = ?'
                );
                $stmt->execute([$id]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id, name, company FROM customers WHERE id = ? AND id IN (SELECT customer_id FROM user_customers WHERE user_id = ?)'
                );
                $stmt->execute([$id, $_SESSION['user_id']]);
                $customer = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            if ($customer) {
                $pstmt = $pdo->prepare('SELECT entity, property, prefix FROM customer_prefixes WHERE customer_id = ?');
                $pstmt->execute([$id]);
                $customer['prefixes'] = $pstmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode($customer ?: null);
            break;
        }

        if (is_admin()) {
            // Administrators see full customer details and invoice counts
            $stmt = $pdo->query(
                'SELECT customers.*, (SELECT COUNT(*) FROM invoices WHERE customer_id = customers.id) AS invoice_count FROM customers'
            );
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Non-admin users see only assigned customers with minimal fields
            $stmt = $pdo->prepare(
                'SELECT id, name, company FROM customers WHERE id IN (SELECT customer_id FROM user_customers WHERE user_id = ?)'
            );
            $stmt->execute([$_SESSION['user_id']]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($customers);
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('INSERT INTO customers (name,email,company,agreement,id_number,vat_number,website,address,city,postal_code,country,phone,currency,logo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['name'], $data['email'], $data['company'], $data['agreement'] ?? null,
            $data['id_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
            $data['address'], $data['city'], $data['postal_code'],
            $data['country'], $data['phone'], $data['currency'], $data['logo'] ?? null,
        ]);
        echo json_encode(['id' => $pdo->lastInsertId()]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare('UPDATE customers SET name=?,email=?,company=?,agreement=?,id_number=?,vat_number=?,website=?,address=?,city=?,postal_code=?,country=?,phone=?,currency=?,logo=? WHERE id=?');
        $stmt->execute([
            $data['name'], $data['email'], $data['company'], $data['agreement'] ?? null,
            $data['id_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
            $data['address'], $data['city'], $data['postal_code'],
            $data['country'], $data['phone'], $data['currency'], $data['logo'] ?? null, $data['id'],
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;
}