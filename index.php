<?php
require_once __DIR__ . '/auth.php';
if (is_admin()) {
    header('Location: /admin/customers.php');
} else {
    header('Location: /client/invoices.php');
}
exit;
?>
