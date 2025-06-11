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
        $id = $_GET['id'] ?? null;
        if ($id !== null) {
            if (!is_numeric($id)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid customer ID.']);
                break;
            }
            try {
                if (is_admin()) {
                    $stmt = $pdo->prepare(
                        'SELECT customers.*, (SELECT COUNT(*) FROM invoices WHERE customer_id = customers.id) AS invoice_count FROM customers WHERE id = ?'
                    );
                    $stmt->execute([(int)$id]);
                    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, name, company FROM customers WHERE id = ? AND id IN (SELECT customer_id FROM user_customers WHERE user_id = ?)'
            );
            $stmt->execute([(int)$id, $_SESSION['user_id']]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if ($customer) {
                    $pstmt = $pdo->prepare('SELECT entity, property, prefix FROM customer_prefixes WHERE customer_id = ?');
                    $pstmt->execute([(int)$id]);
                    $customer['prefixes'] = $pstmt->fetchAll(PDO::FETCH_ASSOC);
                }
                echo json_encode($customer ?: null);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
            break;
        }

        try {
        if (is_admin()) {
            $stmt = $pdo->query(
                'SELECT customers.*, (SELECT COUNT(*) FROM invoices WHERE customer_id = customers.id) AS invoice_count FROM customers'
            );
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, name, company FROM customers WHERE id IN (SELECT customer_id FROM user_customers WHERE user_id = ?)' 
            );
            $stmt->execute([$_SESSION['user_id']]);
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
            echo json_encode($customers);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        try {
        $stmt = $pdo->prepare('INSERT INTO customers (name,email,company,regon_krs_number,regon_number,agreement,id_number,vat_number,website,address,city,postal_code,country,phone,currency,logo,default_invoice_emails,default_invoice_template,payment_unique_string)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['name'], $data['email'], $data['company'], $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null,
            $data['agreement'] ?? null, $data['id_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
            $data['address'], $data['city'], $data['postal_code'],
            $data['country'], $data['phone'], $data['currency'], $data['logo'] ?? null,
            $data['default_invoice_emails'] ?? null, $data['default_invoice_template'] ?? null,
            $data['payment_unique_string'] ?? null,
        ]);
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            header('Location: /api/customers.php?id=' . $newId);
            echo json_encode(['id' => $newId]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Customer id is required']);
            exit;
        }
        $id = (int) $data['id'];
        $existingStmt = $pdo->prepare('SELECT * FROM customers WHERE id = ?');
        $existingStmt->execute([$id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['error' => 'Customer not found']);
            exit;
        }
        $fields = [
            'name','email','company','regon_krs_number','regon_number','agreement',
            'id_number','vat_number','website','address','city','postal_code',
            'country','phone','currency','logo','default_invoice_emails',
            'default_invoice_template','payment_unique_string'
        ];
        foreach ($fields as $f) {
            if (!array_key_exists($f, $data)) {
                $data[$f] = $existing[$f];
            }
        }
        try {
            $stmt = $pdo->prepare('UPDATE customers SET name=?,email=?,company=?,regon_krs_number=?,regon_number=?,agreement=?,id_number=?,vat_number=?,website=?,address=?,city=?,postal_code=?,country=?,phone=?,currency=?,logo=?,default_invoice_emails=?,default_invoice_template=?,payment_unique_string=? WHERE id=?');
            $stmt->execute([
                $data['name'], $data['email'], $data['company'], $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null,
                $data['agreement'] ?? null, $data['id_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
                $data['address'], $data['city'], $data['postal_code'],
                $data['country'], $data['phone'], $data['currency'], $data['logo'] ?? null,
                $data['default_invoice_emails'] ?? null, $data['default_invoice_template'] ?? null,
                $data['payment_unique_string'] ?? null,
                $id,
            ]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Customer not found.']);
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
            echo json_encode(['error' => 'Invalid customer ID.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM customers WHERE id = ?');
            $stmt->execute([(int)$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Customer not found.']);
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