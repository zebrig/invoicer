<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
switch ($method) {
    case 'GET':
        try {
            // Admins can view all sessions; non-admins see only their own
            if (is_admin()) {
                $stmt = $pdo->prepare(
                    'SELECT auth_sessions.id, auth_sessions.token, auth_sessions.user_id,
                            users.username AS username,
                            auth_sessions.created_at, auth_sessions.created_ip, auth_sessions.created_user_agent,
                            auth_sessions.last_used_at, auth_sessions.last_used_ip, auth_sessions.last_used_user_agent,
                            auth_sessions.expires_at
                     FROM auth_sessions
                     JOIN users ON auth_sessions.user_id = users.id'
                );
                $stmt->execute();
            } else {
                $stmt = $pdo->prepare(
                    'SELECT auth_sessions.id, auth_sessions.token, auth_sessions.user_id,
                            users.username AS username,
                            auth_sessions.created_at, auth_sessions.created_ip, auth_sessions.created_user_agent,
                            auth_sessions.last_used_at, auth_sessions.last_used_ip, auth_sessions.last_used_user_agent,
                            auth_sessions.expires_at
                     FROM auth_sessions
                     JOIN users ON auth_sessions.user_id = users.id
                     WHERE auth_sessions.user_id = ?'
                );
                $stmt->execute([$_SESSION['user_id']]);
            }
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($sessions as &$s) {
                $s['masked_token'] = substr($s['token'], 0, 8) . '…' . substr($s['token'], -8);
                unset($s['token']);
            }
            echo json_encode($sessions);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        if (!is_numeric($id)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid session ID.']);
            break;
        }
        try {
            if (is_admin()) {
                // Admins can revoke any session
                $stmt = $pdo->prepare('DELETE FROM auth_sessions WHERE id = ?');
                $stmt->execute([(int)$id]);
            } else {
                $stmt = $pdo->prepare('DELETE FROM auth_sessions WHERE id = ? AND user_id = ?');
                $stmt->execute([(int)$id, $_SESSION['user_id']]);
            }
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Session not found.']);
            } else {
                http_response_code(204);
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error']);
        }
        break;
}