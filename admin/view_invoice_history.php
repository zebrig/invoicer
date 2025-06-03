<?php
require_once __DIR__.'/../auth.php';
if (!is_admin()) {
    http_response_code(403);
    exit('Forbidden');
}
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$dir = PRIVATE_DIR_PATH.'/invoices_history/';
$filepath = $dir . $filename;
if (!$filename || pathinfo($filename, PATHINFO_EXTENSION) !== 'html' || !is_file($filepath)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}
header('Content-Type: text/html; charset=utf-8');
readfile($filepath);