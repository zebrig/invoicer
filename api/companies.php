<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare(
                'SELECT *,
                        (SELECT COUNT(*) FROM invoices WHERE company_id = companies.id) AS invoice_count
                   FROM companies
                  WHERE id = ?'
            );
            $stmt->execute([$_GET['id']]);
            echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
        } else {
            $stmt = $pdo->query(
                'SELECT *,
                        (SELECT COUNT(*) FROM invoices WHERE company_id = companies.id) AS invoice_count
                   FROM companies'
            );
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'INSERT INTO companies (name,company,id_number,regon_krs_number,regon_number,vat_number,website,email,phone,address,city,postal_code,country,bank_name,bank_account,bank_code,currency,logo)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['name'], $data['company'] ?? null, $data['id_number'] ?? null, $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
            $data['email'], $data['phone'], $data['address'], $data['city'], $data['postal_code'], $data['country'],
            $data['bank_name'] ?? null, $data['bank_account'] ?? null, $data['bank_code'] ?? null,
            $data['currency'], $data['logo'] ?? null
        ]);
        echo json_encode(['id' => $pdo->lastInsertId()]);
        break;
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $pdo->prepare(
            'UPDATE companies
                SET name=?,company=?,id_number=?,regon_krs_number=?,regon_number=?,vat_number=?,website=?,email=?,phone=?,address=?,city=?,postal_code=?,country=?,bank_name=?,bank_account=?,bank_code=?,currency=?,logo=?
              WHERE id=?'
        );
        $stmt->execute([
            $data['name'], $data['company'] ?? null, $data['id_number'] ?? null, $data['regon_krs_number'] ?? null, $data['regon_number'] ?? null, $data['vat_number'] ?? null, $data['website'] ?? null,
            $data['email'], $data['phone'], $data['address'], $data['city'], $data['postal_code'], $data['country'],
            $data['bank_name'] ?? null, $data['bank_account'] ?? null, $data['bank_code'] ?? null,
            $data['currency'], $data['logo'] ?? null,
            $data['id']
        ]);
        echo json_encode(['success' => true]);
        break;
    case 'DELETE':
        $id = $_GET['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare('DELETE FROM companies WHERE id = ?');
            $stmt->execute([$id]);
        }
        echo json_encode(['success' => true]);
        break;
}