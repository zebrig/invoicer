<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';
$invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$files = [];
if ($invoiceId) {
    $dir = PRIVATE_DIR_PATH.'/invoices_history/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (is_dir($dir)) {
        $pattern = $dir . "*_{$invoiceId}.html";
        $paths = glob($pattern);
        usort($paths, function($a, $b) { return filemtime($b) - filemtime($a); });
        foreach ($paths as $path) {
            $files[] = basename($path);
        }
    }
}
echo json_encode($files);