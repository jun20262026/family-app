<?php
/**
 * 检查登录状态 API
 * GET: 返回当前登录状态
 */
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['user_id'])) {
    jsonResponse([
        'success' => true,
        'username' => $_SESSION['username'],
        'role' => $_SESSION['user_role']
    ]);
} else {
    jsonResponse(['success' => false]);
}
?>
