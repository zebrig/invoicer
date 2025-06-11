<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$fileId = isset($_GET['file_id']) ? (int)$_GET['file_id'] : 0;
if (!$fileId) {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing file_id');
}
$stmt = $pdo->prepare('SELECT filename, data FROM contract_files WHERE id = ?');
$stmt->execute([$fileId]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$file) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found');
}
if (preg_match('/^data:([^;]+);base64,(.+)$/', $file['data'], $m)) {
    $mime = $m[1];
    $body = base64_decode($m[2]);
} else {
    $mime = 'application/octet-stream';
    $body = $file['data'];
}
if (strcasecmp($mime, 'application/pdf') === 0) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file['filename']) . '"');
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}
header("Content-Type: {$mime}");
header('Content-Disposition: attachment; filename="' . basename($file['filename']) . '"');
echo $body;
exit;