<?php
/**
 * 用户管理 API（仅管理员可操作）
 * GET: 列表
 * POST: 新增/修改
 * DELETE: 删除
 */
require_once __DIR__ . '/../config.php';
requireAdmin();   // ← 仅管理员可访问
$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];

// ===== 列表 =====
if ($method === 'GET') {
    $stmt = $pdo->query('SELECT id, username, role, brother_name, display_order, created_at FROM users ORDER BY display_order, id');
    jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
}

// ===== 新增 / 修改 =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $id           = intval($input['id']           ?? 0);
    $username     = trim($input['username']        ?? '');
    $password     = $input['password']            ?? '';
    $role         = $input['role']                ?? 'brother';
    $brother_name = trim($input['brother_name']   ?? '');
    $display_order = intval($input['display_order'] ?? 0);

    if (!$username || !$brother_name) {
        jsonResponse(['success' => false, 'msg' => '用户名和名称不能为空']);
    }

    if ($id > 0) {
        // 修改
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'UPDATE users SET username=?, password=?, role=?, brother_name=?, display_order=? WHERE id=?'
            );
            $stmt->execute([$username, $hash, $role, $brother_name, $display_order, $id]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET username=?, role=?, brother_name=?, display_order=? WHERE id=?'
            );
            $stmt->execute([$username, $role, $brother_name, $display_order, $id]);
        }
        logOperation('修改用户', "修改用户：$brother_name");
        jsonResponse(['success' => true, 'msg' => '修改成功']);
    } else {
        // 新增
        if (!$password) {
            jsonResponse(['success' => false, 'msg' => '新增用户必须设置密码']);
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password, role, brother_name, display_order) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $role, $brother_name, $display_order]);
        logOperation('新增用户', "新增用户：$brother_name");
        jsonResponse(['success' => true, 'msg' => '新增成功']);
    }
}

// ===== 删除 =====
if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'msg' => '缺少ID']);
    }
    if ($id == CURRENT_USER_ID()) {
        jsonResponse(['success' => false, 'msg' => '不能删除当前登录用户']);
    }
    // 查找要删除的用户名（用于日志）
    $stmt = $pdo->prepare('SELECT brother_name FROM users WHERE id=?');
    $stmt->execute([$id]);
    $u = $stmt->fetch();
    $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
    logOperation('删除用户', '删除用户：' . ($u['brother_name'] ?? $id));
    jsonResponse(['success' => true, 'msg' => '删除成功']);
}

jsonResponse(['success' => false, 'msg' => '不支持的请求']);
