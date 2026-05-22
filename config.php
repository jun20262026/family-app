<?php
/**
 * 统一配置 + 工具函数
 */

/* === Session 启动 === */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* === 数据库 === */
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'fundraising');
define('DB_USER', 'fundraising');
define('DB_PASS', '123456');

/* === 上传目录 === */
define('UPLOAD_DIR', __DIR__ . '/static/uploads/');
define('UPLOAD_URL', '/static/uploads/');

/* === 全局PDO实例 === */
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ));
    }
    $GLOBALS['pdo'] = $pdo;
    return $pdo;
}

/* === 通用JSON输出 === */
function jsonResponse($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* === 鉴权 === */
function isLoggedIn() {
    return !empty($_SESSION['user']['id']);
}
function requireLogin() {
    if (!isset($_SESSION['user'])) {
        jsonResponse(array('success' => false, 'msg' => '请先登录', 'code' => 401));
    }
}
function requireAdmin() {
    requireLogin();
    if (CURRENT_ROLE() !== 'admin') {
        jsonResponse(array('success' => false, 'msg' => '需要管理员权限', 'code' => 403));
    }
}
function CURRENT_USER_ID()   { return $_SESSION['user']['id']; }
function CURRENT_ROLE()      { return $_SESSION['user']['role']; }
function CURRENT_BROTHER_NAME() { return $_SESSION['user']['brother_name']; }

/* === 获取捐款来源选项（动态） === */
function GET_DONATION_SOURCES() {
    $brotherName = CURRENT_BROTHER_NAME();
    $sources = array();
    $sources[] = '本人好友';
    $sources[] = '父亲好友';
    $brothers = array('大哥', '二哥', '三弟');
    foreach ($brothers as $brother) {
        if ($brother !== $brotherName) {
            $sources[] = $brother . '好友';
        }
    }
    $sources[] = '社会捐款';
    $sources[] = '企业捐款';
    return $sources;
}

/* === 获取所有兄弟列表（用于转账等） === */
function GET_ALL_BROTHERS() {
    $pdo = getDB();
    $stmt = $pdo->query('SELECT person_key, person_name FROM wallets ORDER BY id, person_name');
    return $stmt->fetchAll();
}

/* === 获取除当前用户外的兄弟列表（用于转账） === */
function GET_OTHER_BROTHERS() {
    $currentName = CURRENT_BROTHER_NAME();
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT person_key, person_name FROM wallets WHERE person_name != ? ORDER BY id, person_name');
    $stmt->execute(array($currentName));
    return $stmt->fetchAll();
}

/* === 创建审核记录 === */
function createAuditLog($userId, $actionType, $targetType, $targetId, $oldData = null, $newData = null) {
    $pdo = getDB();
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action_type, target_type, target_id, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute(array(
        $userId,
        $actionType,
        $targetType,
        $targetId,
        $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null
    ));
    return $pdo->lastInsertId();
}

/* === 确保上传目录存在 === */
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

/* === 记录操作日志 === */
function logAction($userId, $action, $detail = null) {
    $pdo = getDB();
    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $stmt = $pdo->prepare('INSERT INTO operation_log (user_id, action, detail, ip) VALUES (?, ?, ?, ?)');
    $stmt->execute(array($userId, $action, $detail, $ip));
}
