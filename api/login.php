<?php
/**
 * 登录 API
 * POST: {username, password}  → 登录
 * GET:  → 检查登录状态
 */
require_once __DIR__ . '/../config.php';

$pdo = getDB();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        jsonResponse(['success' => false, 'msg' => '请输入用户名和密码']);
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonResponse(['success' => false, 'msg' => '用户不存在']);
    }

    // 验证密码（兼容明文和 bcrypt）
    $valid = false;
    if (strpos($user['password'], '$2y$') === 0 || strpos($user['password'], '$2a$') === 0) {
        // 已加密
        $valid = password_verify($password, $user['password']);
    } else {
        // 明文密码
        $valid = ($user['password'] === $password);
        if ($valid) {
            // 首次登录，自动加密
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([$hash, $user['id']]);
        }
    }

    if ($valid) {
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'brother_name' => $user['brother_name'],
        ];

        // 记录登录日志
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $pdo->prepare('INSERT INTO login_log (user_id, ip, user_agent) VALUES (?, ?, ?)')
            ->execute([$user['id'], $ip, $agent]);

        // 记录操作日志
        logAction($user['id'], '登录', 'IP: ' . $ip);

        jsonResponse([
            'success' => true,
            'username' => $user['username'],
            'role' => $user['role'],
            'brother_name' => $user['brother_name'],
        ]);
    } else {
        jsonResponse(['success' => false, 'msg' => '密码错误']);
    }
}

if ($method === 'GET') {
    if (isset($_SESSION['user']['id'])) {
        jsonResponse([
            'success' => true,
            'username' => $_SESSION['user']['username'],
            'role' => $_SESSION['user']['role'],
            'brother_name' => $_SESSION['user']['brother_name'],
        ]);
    } else {
        jsonResponse(['success' => false]);
    }
}

jsonResponse(['success' => false, 'msg' => '不支持的请求']);
?>
