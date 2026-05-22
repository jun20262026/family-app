<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$action = $_GET['action'] ?? '';
$pdo = getDB();

// 获取当前用户ID和角色
$currentUserId = CURRENT_USER_ID();
$currentRole = CURRENT_ROLE();

// 处理单文件上传（兼容旧逻辑）
function handleFileUpload($fileKey) {
    $result = handleMultiUpload($fileKey);
    return empty($result) ? null : [json_encode($result), implode(', ', array_column($result, 'name'))];
}

// 处理多文件上传
function handleMultiUpload($fileKey) {
    $files = [];
    if (!isset($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]['name'])) {
        // 单文件模式（旧兼容）
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $info = doUploadOne($fileKey, 0);
            if ($info) $files[] = $info;
        }
        return $files;
    }

    $count = count($_FILES[$fileKey]['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES[$fileKey]['error'][$i] === UPLOAD_ERR_OK) {
            $info = doUploadOne($fileKey, $i);
            if ($info) $files[] = $info;
        }
    }
    return $files;
}

// 执行单个文件上传
function doUploadOne($key, $idx) {
    $uploadDir = __DIR__ . '/../uploads/documents/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $originalName = basename($_FILES[$key]['name'][$idx]);
    $size = $_FILES[$key]['size'][$idx];
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($size > $maxSize) return null;

    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','bmp','webp','pdf','doc','docx','xls','xlsx','txt','zip','rar'];
    if (!in_array($ext, $allowed)) return null;

    $newName = uniqid('doc_') . '.' . $ext;
    $targetPath = $uploadDir . $newName;

    if (move_uploaded_file($_FILES[$key]['tmp_name'][$idx], $targetPath)) {
        return [
            'path' => '/uploads/documents/' . $newName,
            'name' => $originalName,
            'size' => $size,
            'type' => in_array($ext,['jpg','jpeg','png','gif','bmp','webp']) ? 'image' : 'file'
        ];
    }
    return null;
}

if ($action === 'list') {
    // 获取证件资料列表
    $stmt = $pdo->query('SELECT d.*, u.username as keeper_username FROM documents d LEFT JOIN users u ON d.keeper_id = u.id ORDER BY d.created_at DESC');
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'data' => $list]);
    
} elseif ($action === 'create') {
    // 新增证件资料（需要登录）
    if (!isset($_POST['title']) || trim($_POST['title']) === '') {
        jsonResponse(['success' => false, 'error' => '请输入证件名称']);
    }
    
    // 处理多文件上传
    $fileList = handleMultiUpload('files');
    $filePathJson = empty($fileList) ? null : json_encode($fileList, JSON_UNESCAPED_UNICODE);
    
    // 处理保管人
    $keeperId = isset($_POST['keeper_id']) ? intval($_POST['keeper_id']) : null;
    $keeperName = null;
    if ($keeperId) {
        $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt->execute([$keeperId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $keeperName = $user ? $user['username'] : null;
    }
    
    $stmt = $pdo->prepare('INSERT INTO documents (title, file_path, file_name, keeper_id, keeper_name, created_by) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([trim($_POST['title']), $filePathJson, $filePathJson ? count($fileList).'个文件' : null, $keeperId, $keeperName, $currentUserId]);
    
    logAction($currentUserId, '新增证件资料', '证件名称：' . $_POST['title'] . ($filePathJson ? '，共'.count($fileList).'个附件' : ''));
    jsonResponse(['success' => true, 'msg' => '添加成功']);
    
} elseif ($action === 'update') {
    // 修改证件资料（需要登录）
    if (!isset($_POST['id']) || !isset($_POST['title']) || trim($_POST['title']) === '') {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }
    
    // 检查权限
    $stmt = $pdo->prepare('SELECT created_by, file_path FROM documents WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    $doc = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$doc || ($currentRole !== 'admin' && $doc['created_by'] != $currentUserId)) {
        jsonResponse(['success' => false, 'error' => '无权限修改']);
    }
    
    // 处理多文件上传（追加模式：新文件合并到已有文件列表）
    $newFiles = handleMultiUpload('files');
    if (!empty($newFiles) && !empty($doc['file_path'])) {
        // 合并新旧文件
        try { $existingFiles = json_decode($doc['file_path'], true) ?: []; } catch (\Exception $e) { $existingFiles = []; }
        $fileList = array_merge($existingFiles, $newFiles);
    } else {
        $fileList = empty($newFiles) ? (empty($doc['file_path']) ? [] : json_decode($doc['file_path'], true)) : $newFiles;
    }
    
    $filePathJson = empty($fileList) ? null : json_encode($fileList, JSON_UNESCAPED_UNICODE);
    
    // 处理保管人
    $keeperId = isset($_POST['keeper_id']) ? intval($_POST['keeper_id']) : null;
    $keeperName = null;
    if ($keeperId) {
        $stmt2 = $pdo->prepare('SELECT username FROM users WHERE id = ?');
        $stmt2->execute([$keeperId]);
        $user = $stmt2->fetch(PDO::FETCH_ASSOC);
        $keeperName = $user ? $user['username'] : null;
    }
    
    $stmt3 = $pdo->prepare('UPDATE documents SET title = ?, file_path = ?, file_name = ?, keeper_id = ?, keeper_name = ?, updated_at = NOW() WHERE id = ?');
    $stmt3->execute([
        trim($_POST['title']),
        $filePathJson,
        $filePathJson ? count($fileList).'个文件' : null,
        $keeperId,
        $keeperName,
        $_POST['id']
    ]);
    
    logAction($currentUserId, '修改证件资料', '证件ID：' . $_POST['id']);
    jsonResponse(['success' => true, 'msg' => '修改成功', 'files' => $fileList]);
    
} elseif ($action === 'delete') {
    // 删除证件资料（仅管理员）
    if ($currentRole !== 'admin') {
        jsonResponse(['success' => false, 'error' => '无权限删除']);
    }
    
    if (!isset($_POST['id'])) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }
    
    $stmt = $pdo->prepare('DELETE FROM documents WHERE id = ?');
    $stmt->execute([$_POST['id']]);
    
    logAction($currentUserId, '删除证件资料', '证件ID：' . $_POST['id']);
    jsonResponse(['success' => true, 'msg' => '删除成功']);
    
} else {
    jsonResponse(['success' => false, 'error' => '未知操作']);
}
