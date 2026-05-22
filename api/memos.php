<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$action = $_GET['action'] ?? '';
$pdo = getDB();
$currentUserId = CURRENT_USER_ID();
$currentRole = CURRENT_ROLE();

if ($action === 'list') {
    $stmt = $pdo->query('SELECT m.*, u.username as record_by_username FROM memos m LEFT JOIN users u ON m.record_by = u.id ORDER BY m.record_time DESC');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} elseif ($action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (empty(trim($data['content'] ?? ''))) jsonResponse(['success'=>false,'error'=>'请输入记录事项']);
    if (empty(trim($data['record_time'] ?? ''))) jsonResponse(['success'=>false,'error'=>'请选择记录时间']);
    
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$currentUserId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare('INSERT INTO memos (content, record_time, record_by, record_by_name) VALUES (?, ?, ?, ?)');
    $stmt->execute([trim($data['content']), $data['record_time'], $currentUserId, $user['username'] ?? null]);
    
    logAction($currentUserId, '新增备忘录', '记录事项：' . mb_substr($data['content'],0,50));
    jsonResponse(['success'=>true,'msg'=>'添加成功']);
} elseif ($action === 'update') {
    // 修改备忘录（创建人可修改）
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id']) || !isset($data['content']) || trim($data['content']) === '') 
        jsonResponse(['success'=>false,'error'=>'参数错误']);
    if (!isset($data['record_time']) || trim($data['record_time']) === '')
        jsonResponse(['success'=>false,'error'=>'请选择记录时间']);
    
    // 检查权限（只有创建人可以修改）
    $stmt = $pdo->prepare('SELECT record_by FROM memos WHERE id = ?');
    $stmt->execute([$data['id']]);
    $memo = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$memo || ($currentRole !== 'admin' && $memo['record_by'] != $currentUserId)) {
        jsonResponse(['success'=>false,'error'=>'无权限修改']);
    }
    
    $stmt = $pdo->prepare('UPDATE memos SET content = ?, record_time = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([trim($data['content']), $data['record_time'], $data['id']]);
    logAction($currentUserId, '修改备忘录', 'ID：' . $data['id']);
    jsonResponse(['success'=>true,'msg'=>'修改成功']);
} elseif ($action === 'delete') {
    // 删除备忘录（登录用户可删除自己的，管理员可删除所有）
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    
    if (!$id) jsonResponse(['success'=>false,'error'=>'参数错误']);
    
    // 非管理员检查是否是创建人
    if ($currentRole !== 'admin') {
        $stmt = $pdo->prepare('SELECT record_by FROM memos WHERE id = ?');
        $stmt->execute([$id]);
        $memo = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$memo || $memo['record_by'] != $currentUserId) {
            jsonResponse(['success'=>false,'error'=>'无权限删除']);
        }
    }
    
    $stmt = $pdo->prepare('DELETE FROM memos WHERE id = ?');
    $stmt->execute([$id]);
    logAction($currentUserId, '删除备忘录', 'ID：' . $id);
    jsonResponse(['success'=>true,'msg'=>'删除成功']);
} else {
    jsonResponse(['success'=>false,'error'=>'未知操作']);
}

