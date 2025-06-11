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
	if (!isset($_SESSION['user_id'])) {
		return false;
	}
	static $flagCache = [];
	$userId = $_SESSION['user_id'];
	if (!isset($flagCache[$userId])) {
		try {
			$stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = ?');
			$stmt->execute([$userId]);
			$flagCache[$userId] = (bool)$stmt->fetchColumn();
		} catch (PDOException $e) {
			error_log("is_admin check failed for user {$userId}: " . $e->getMessage());
			$flagCache[$userId] = false;
		}
	}
	return $flagCache[$userId];
}

/**
 * Get the current logged-in user's username.
 *
 * @return string
 */
function current_username(): string {
    global $pdo;
    if (!isset($_SESSION['user_id'])) {
        return '';
    }
    static $nameCache = [];
    $userId = $_SESSION['user_id'];
    if (!isset($nameCache[$userId])) {
        try {
            $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $nameCache[$userId] = $stmt->fetchColumn() ?: '';
        } catch (PDOException $e) {
            error_log("current_username failed for user {$userId}: " . $e->getMessage());
            $nameCache[$userId] = '';
        }
    }
    return $nameCache[$userId];
}

// Destroy any legacy PHP session when no auth_token cookie is present
$token = $_COOKIE['auth_token'] ?? '';
if ($token === '') {
    session_unset();
    session_destroy();
    session_start();
}

// Cleanup expired auth tokens
try {
    $stmt = $pdo->prepare('DELETE FROM auth_sessions WHERE expires_at < ?');
    $stmt->execute([date('Y-m-d H:i:s')]);
} catch (PDOException $e) {
    error_log("auth.php: Failed to cleanup expired auth tokens: " . $e->getMessage());
}

if ($token) {
    $hashedToken = hash('sha256', $token);
    try {
        $stmt = $pdo->prepare('SELECT user_id FROM auth_sessions WHERE token = ?');
        $stmt->execute([$hashedToken]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $now = date('Y-m-d H:i:s');
            $ip  = $_SERVER['REMOTE_ADDR'];
            $ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

            try {
                $upd = $pdo->prepare(
                    'UPDATE auth_sessions SET last_used_at = ?, last_used_ip = ?, last_used_user_agent = ? WHERE token = ?'
                );
                $upd->execute([$now, $ip, $ua, $hashedToken]);
            } catch (PDOException $e) {
                error_log("auth.php: Failed to update auth_sessions last_used: " . $e->getMessage());
            }

            $_SESSION['user_id'] = $row['user_id'];
        } else {
            $cookieOptions = [
                'expires'  => time() - 3600,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
                $cookieOptions['secure'] = true;
            }
            setcookie('auth_token', '', $cookieOptions);
            unset($_COOKIE['auth_token']);
        }
    } catch (PDOException $e) {
        error_log("auth.php: Error validating auth token: " . $e->getMessage());
        $cookieOptions = [
            'expires'  => time() - 3600,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $cookieOptions['secure'] = true;
        }
        setcookie('auth_token', '', $cookieOptions);
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
