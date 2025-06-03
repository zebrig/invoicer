<?php
// Logout script for Invoicer
session_start();
require_once __DIR__ . '/db.php';

// Delete this auth token and clear cookie
if (isset($_COOKIE['auth_token'])) {
    $stmt = $pdo->prepare('DELETE FROM auth_sessions WHERE token = ?');
    $stmt->execute([$_COOKIE['auth_token']]);
    setcookie('auth_token', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE['auth_token']);
}

$_SESSION = [];
session_destroy();
header('Location: /login.php');
exit;
