<?php
require_once __DIR__ . '/auth.php';
// Get filename from query and sanitize
$file = isset($_GET['file']) ? basename($_GET['file']) : '';
// Validate filename pattern: YYYY-mm-dd_customerName_companySlug_timestamp_id.pdf
if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}_[^_]+_[A-Za-z0-9\-]+_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}:[0-9]{2}:[0-9]{2}_[0-9]+\.pdf$/', $file)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file name');
}
$path = PRIVATE_DIR_PATH."/invoices_signed/" . $file;
if (!file_exists($path)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}
// Serve PDF
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>