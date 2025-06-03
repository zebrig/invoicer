<?php
// Login page for Invoicer
session_start();
require_once __DIR__ . '/db.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT id, password, disabled FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && !$user['disabled'] && password_verify($password, $user['password'])) {
        // create and persist auth token with metadata (expires in 3 months)
        $token = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+3 months'));
        $ip = $_SERVER['REMOTE_ADDR'];
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $pdo->prepare(
            'INSERT INTO auth_sessions (token, user_id, created_ip, created_user_agent, last_used_ip, last_used_user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$token, $user['id'], $ip, $ua, $ip, $ua, $expires]);
        setcookie('auth_token', $token, [
            'expires' => strtotime($expires),
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_SESSION['user_id'] = $user['id'];
        header('Location: /index.php');
        exit;
    }
    $error = 'Invalid credentials or account disabled';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Invoicer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="height: 100vh;">
    <div class="container" style="max-width: 400px;">
        <h2 class="mb-4 text-center">Login</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
