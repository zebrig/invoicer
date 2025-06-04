<?php
require_once __DIR__ . '/../auth.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
    $invoiceId = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
    $file = isset($_GET['file']) ? basename($_GET['file']) : '';
    if ($invoiceId && $file) {
        $dir = PRIVATE_DIR_PATH . '/invoices_history/';
        $path = $dir . $file;
        if (is_file($path)) {
            @unlink($path);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Bad request']);
    }
    exit;
}

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