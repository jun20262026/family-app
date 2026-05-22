<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$action = $_GET['action'] ?? '';
$pdo = getDB();
$currentUserId = CURRENT_USER_ID();
$currentRole = CURRENT_ROLE();

// 处理头像上传
function handleAvatarUpload($fileKey) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $uploadDir = __DIR__ . '/../uploads/avatars/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
    $newName = uniqid('avatar_') . '.' . $ext;
    $targetPath = $uploadDir . $newName;
    
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $targetPath)) {
        return '/uploads/avatars/' . $newName;
    }
    
    return null;
}

if ($action === 'list') {
    $stmt = $pdo->query('SELECT * FROM contacts ORDER BY name ASC');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} elseif ($action === 'create') {
    // 注意：文件上传必须使用 FormData，不能用 JSON
    if (empty(trim($_POST['name'] ?? ''))) {
        jsonResponse(['success' => false, 'error' => '请输入联系人姓名']);
    }
    
    $avatarPath = handleAvatarUpload('avatar');
    
    $stmt = $pdo->prepare('INSERT INTO contacts (avatar_path, name, position, phone, created_by) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$avatarPath, trim($_POST['name']), $_POST['position'] ?? null, $_POST['phone'] ?? null, $currentUserId]);
    
    logAction($currentUserId, '新增联系人', '联系人：' . $_POST['name']);
    jsonResponse(['success' => true, 'msg' => '添加成功']);
    
} elseif ($action === 'update') {
    // 注意：文件上传必须使用 FormData，不能用 JSON
    if (empty($_POST['id'])) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }
    
    $stmt = $pdo->prepare('SELECT created_by FROM contacts WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    $contact = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$contact || ($currentRole !== 'admin' && $contact['created_by'] != $currentUserId)) {
        jsonResponse(['success' => false, 'error' => '无权限修改']);
    }
    
    $avatarPath = handleAvatarUpload('avatar');
    
    $sql = 'UPDATE contacts SET name = ?, position = ?, phone = ?';
    $params = [trim($_POST['name']), $_POST['position'] ?? null, $_POST['phone'] ?? null];
    
    if ($avatarPath) {
        $sql .= ', avatar_path = ?';
        $params[] = $avatarPath;
    }
    
    $sql .= ' WHERE id = ?';
    $params[] = $_POST['id'];
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    logAction($currentUserId, '修改联系人', '联系人ID：' . $_POST['id']);
    jsonResponse(['success' => true, 'msg' => '修改成功']);
    
} elseif ($action === 'delete') {
    // 删除操作不需要文件上传，但统一用 $_POST 更简单
    if ($currentRole !== 'admin') {
        jsonResponse(['success' => false, 'error' => '无权限删除']);
    }
    $stmt = $pdo->prepare('DELETE FROM contacts WHERE id = ?');
    $stmt->execute([$_POST['id'] ?? 0]);
    logAction($currentUserId, '删除联系人', 'ID：' . ($_POST['id'] ?? 0));
    jsonResponse(['success' => true, 'msg' => '删除成功']);
    
} else {
    jsonResponse(['success' => false, 'error' => '未知操作']);
}
