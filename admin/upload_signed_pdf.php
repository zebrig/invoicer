<?php
require_once __DIR__.'/../auth.php';
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__.'/../db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit;
}
$invoiceId = isset($_POST['invoice_id']) ? (int)$_POST['invoice_id'] : 0;
if (!$invoiceId || empty($_FILES['signed_pdf'])) {
    header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=error');
    exit;
}
// Fetch invoice info
$stmt = $pdo->prepare('SELECT date, company_id FROM invoices WHERE id = ?');
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$invoice) {
    header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=error');
    exit;
}
// Fetch company name
$stmt2 = $pdo->prepare('SELECT name FROM companies WHERE id = ?');
$stmt2->execute([$invoice['company_id']]);
$companyName = $stmt2->fetchColumn() ?: '';
// Prepare directory
$uploadDir = PRIVATE_DIR_PATH.'/invoices_signed/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
// Remove existing signed PDFs for this invoice
foreach (glob($uploadDir . "*_${invoiceId}.pdf") as $oldFile) {
    @unlink($oldFile);
}
// Validate upload
$file = $_FILES['signed_pdf'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=error');
    exit;
}
// Validate MIME type
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
if ($mime !== 'application/pdf') {
    header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=error');
    exit;
}
// Build filename
$invoiceDate = date('Y-m-d', strtotime($invoice['date']));
$companySlug = preg_replace('/[^A-Za-z0-9]+/', '-', trim(substr($companyName, 0, 30)));
$uploadTimestamp = date('Y-m-d_H:i:s');
$filename = "{$invoiceDate}_{$companySlug}_{$uploadTimestamp}_{$invoiceId}.pdf";
// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
    header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=error');
    exit;
}
// Success
header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=success');
exit;
?>