<?php
/**
 * 操作日志 API
 * 权限：
 *   - 管理员：查看所有用户的日志（含登录/操作）
 *   - 兄弟：仅查看自己的操作日志（不含管理员日志）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

// 仅允许 GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['success' => false, 'msg' => '仅支持GET']);
}

$role = CURRENT_ROLE();
$meId = CURRENT_USER_ID();

if ($role === 'admin') {
    // 管理员：看全部（含所有兄弟和管理员自己的日志）
    $stmt = $pdo->query(
        'SELECT l.*, u.username, u.brother_name
         FROM operation_log l
         LEFT JOIN users u ON l.user_id = u.id
         ORDER BY l.created_at DESC
         LIMIT 500'
    );
} else {
    // 兄弟：只看自己的操作日志（不含管理员的日志）
    $stmt = $pdo->prepare(
        'SELECT l.*, u.username, u.brother_name
         FROM operation_log l
         LEFT JOIN users u ON l.user_id = u.id
         WHERE l.user_id = ?
         ORDER BY l.created_at DESC
         LIMIT 500'
    );
    $stmt->execute([$meId]);
}

$rows = $stmt->fetchAll();

// 检查 ip_location 字段是否存在，并尝试批量转换 IP 为地区
$hasIpLocation = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM operation_log LIKE 'ip_location'");
    $hasIpLocation = ($check && $check->rowCount() > 0);
} catch (Exception $e) { $hasIpLocation = false; }

if ($hasIpLocation) {
    // 收集需要更新地区的IP
    $ipMap = [];
    foreach ($rows as $row) {
        $ipLoc = $row['ip_location'] ?? '';
        $ip    = $row['ip'] ?? '';
        if (empty($ipLoc) && !empty($ip) && $ip !== '未知' && $ip !== '-') {
            $ipMap[$ip] = null;
        }
    }
    if (count($ipMap) > 0) {
        $ipList = array_keys($ipMap);
        // 调用 ip-api.com 批量查询（免费API，无需key）
        $ch = curl_init('http://ip-api.com/batch');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($ipList));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if (!$curlErr && $response) {
            $locationData = json_decode($response, true);
            if (is_array($locationData)) {
                foreach ($locationData as $loc) {
                    if (($loc['status'] ?? '') === 'success') {
                        $parts = array_filter([$loc['country'] ?? '', $loc['regionName'] ?? '', $loc['city'] ?? '']);
                        $ipMap[$loc['query']] = implode(' ', $parts);
                    }
                }
                // 异步更新数据库（不阻塞响应）
                $updateStmt = $pdo->prepare('UPDATE operation_log SET ip_location = ? WHERE ip = ? AND (ip_location IS NULL OR ip_location = "")');
                foreach ($ipMap as $ip => $loc) {
                    if ($loc !== null && $loc !== '') {
                        try { $updateStmt->execute([$loc, $ip]); } catch (Exception $e) { /* 忽略 */ }
                    }
                }
            }
        }
        // 更新 $rows 中的 ip_location
        foreach ($rows as &$row) {
            $ip = $row['ip'] ?? '';
            if ($ip && isset($ipMap[$ip]) && $ipMap[$ip] !== null && $ipMap[$ip] !== '') {
                $row['ip_location'] = $ipMap[$ip];
            }
        }
    }
}

jsonResponse(['success' => true, 'data' => $rows]);
?>
