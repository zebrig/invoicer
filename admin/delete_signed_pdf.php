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
if ($invoiceId) {
    $signedDir = PRIVATE_DIR_PATH.'/invoices_signed/';
    foreach (glob($signedDir . "*_${invoiceId}.pdf") as $file) {
        @unlink($file);
    }
}
header('Location: invoice_form.php?invoice_id=' . $invoiceId . '&upload_status=deleted');
exit;
?>