<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
session_start();

$script = basename($_SERVER['PHP_SELF']);
if ($script === 'login.php' || $script === 'logout.php') {
    return;
}

/**
 * Check if current user is an administrator.
 *
 * @return bool
 */
function is_admin(): bool {
	global $pdo;
	static $flag;
	if ($flag === null) {
		$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
		$stmt->execute([$_SESSION['user_id']]);
		$flag = (bool)$stmt->fetchColumn();
	}
	return $flag;
}

/**
 * Get the current logged-in user's username.
 *
 * @return string
 */
function current_username(): string {
    global $pdo;
    static $name;
    if ($name === null) {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $name = $stmt->fetchColumn() ?: '';
    }
    return $name;
}

// Destroy any legacy PHP session when no auth_token cookie is present
$token = $_COOKIE['auth_token'] ?? '';
if ($token === '') {
    session_unset();
    session_destroy();
    session_start();
}

// Cleanup expired auth tokens
$pdo->prepare('DELETE FROM auth_sessions WHERE expires_at < ?')->execute([date('Y-m-d H:i:s')]);

if ($token) {
    $stmt = $pdo->prepare('SELECT user_id FROM auth_sessions WHERE token = ?');
    $stmt->execute([$token]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $now = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $upd = $pdo->prepare(
            'UPDATE auth_sessions SET last_used_at = ?, last_used_ip = ?, last_used_user_agent = ? WHERE token = ?'
        );
        $upd->execute([$now, $ip, $ua, $token]);
        $_SESSION['user_id'] = $row['user_id'];
    } else {
        setcookie('auth_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        unset($_COOKIE['auth_token']);
    }
}

if (empty($_SESSION['user_id'])) {
    if (strpos($_SERVER['PHP_SELF'], '/api/') !== false) {
        header('HTTP/1.1 401 Unauthorized');
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    header('Location: /login.php');
    exit;
}
