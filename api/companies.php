<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}
require_once __DIR__ . '/../db.php';
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            if (!is_numeric($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid company ID.']);
                break;
            }
            try {
                $stmt = $pdo->prepare(
                    'SELECT *,
                            (SELECT COUNT(*) FROM invoices WHERE company_id = companies.id) AS invoice_count
                       FROM companies
                      WHERE id = ?'
                );
                $stmt->execute([$_GET['id']]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        } else {
            try {
                $stmt = $pdo->query(
                    'SELECT *,
                            (SELECT COUNT(*) FROM invoices WHERE company_id = companies.id) AS invoice_count
                       FROM companies'
                );
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            } catch (PDOException $e) {
                error_log($e->getMessage());
                http_response_code(500);
                echo json_encode(['error' => 'Internal Server Error']);
            }
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON payload.']);
            break;
        }
        if (empty($data['name'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Company name is required.']);
            break;
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'A valid email is required.']);
            break;
        }
        if (empty($data['currency'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Currency is required.']);
            break;
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO companies (name,company,id_number,regon_krs_number,regon_number,vat_number,website,email,phone,address,city,postal_code,country,bank_name,bank_account,bank_code,payment_unique_string,currency,logo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $data['name'], $data['company'] ?? null, $data['id_number'] ?? null, $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
                $data['email'], $data['phone'], $data['address'], $data['city'], $data['postal_code'], $data['country'],
                $data['bank_name'] ?? null, $data['bank_account'] ?? null, $data['bank_code'] ?? null,
                $data['payment_unique_string'] ?? null, $data['currency'], $data['logo'] ?? null
            ]);
            $newId = $pdo->lastInsertId();
            http_response_code(201);
            header('Location: /api/companies.php?id=' . $newId);
            echo json_encode(['id' => $newId]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || !isset($data['id']) || !is_numeric($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid request payload or missing company ID.']);
            break;
        }
        if (empty($data['name'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Company name is required.']);
            break;
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);
            echo json_encode(['error' => 'A valid email is required.']);
            break;
        }
        if (empty($data['currency'])) {
            http_response_code(422);
            echo json_encode(['error' => 'Currency is required.']);
            break;
        }
        try {
            $stmt = $pdo->prepare(
                'UPDATE companies
                    SET name=?,company=?,id_number=?,regon_krs_number=?,regon_number=?,vat_number=?,website=?,email=?,phone=?,address=?,city=?,postal_code=?,country=?,bank_name=?,bank_account=?,bank_code=?,payment_unique_string=?,currency=?,logo=?
                  WHERE id=?'
            );
            $stmt->execute([
                $data['name'], $data['company'] ?? null, $data['id_number'] ?? null, $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
                $data['email'], $data['phone'], $data['address'], $data['city'], $data['postal_code'], $data['country'],
                $data['bank_name'] ?? null, $data['bank_account'] ?? null, $data['bank_code'] ?? null,
                $data['payment_unique_string'] ?? null, $data['currency'], $data['logo'] ?? null,
                $data['id']
            ]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Company not found.']);
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
            echo json_encode(['error' => 'Invalid company ID.']);
            break;
        }
        try {
            $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Company not found.']);
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