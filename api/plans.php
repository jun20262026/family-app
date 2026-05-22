<?php
/**
 * 家庭计划 API
 * 所有用户可新增/同意，创建者或管理员可编辑/删除
 * 同意人：所有用户可点"同意"，再次点击取消同意
 * 列名与 create_plans_table.sql 保持一致：
 *   planned_date / planned_amount / is_done / approvers / executor
 */
require_once __DIR__ . '/../config.php';
requireLogin();

$action       = $_GET['action'] ?? ($_POST['action'] ?? '');
$pdo          = getDB();
$currentUserId = CURRENT_USER_ID();
$currentRole   = CURRENT_ROLE();

try {

// ===== list: 计划列表 =====
if ($action === 'list') {
    // 用户映射表（id → display_name）
    $uStmt = $pdo->query('SELECT id, IFNULL(brother_name, username) AS display_name FROM users');
    $userMap = [];
    foreach ($uStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
        $userMap[$u['id']] = $u['display_name'];
    }

    $stmt = $pdo->query(
        'SELECT p.*, u.username AS creator_name'
        . ' FROM family_plans p'
        . ' LEFT JOIN users u ON p.created_by = u.id'
        . ' ORDER BY p.is_done ASC, p.planned_date DESC, p.created_at DESC'
    );
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 解析同意人列表，转为姓名数组
    foreach ($list as &$item) {
        $ids = $item['approvers'] ? json_decode($item['approvers'], true) : [];
        $item['agree_users'] = $ids;   // JS 用这个字段名
        $item['agree_names'] = [];
        foreach ($ids as $uid) {
            $item['agree_names'][] = $userMap[$uid] ?? '用户' . $uid;
        }
    }

    jsonResponse(['success' => true, 'data' => $list]);
    exit;

// ===== create: 新增计划 =====
} elseif ($action === 'create') {
    $title      = trim($_POST['title'] ?? '');
    $planDate   = $_POST['plan_date'] ?? '';
    $planAmount = $_POST['plan_amount'] ?? '';
    $executor   = trim($_POST['executor'] ?? '');

    if (empty($title)) {
        jsonResponse(['success' => false, 'error' => '请输入计划事项']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO family_plans (title, planned_date, planned_amount, executor, created_by)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $planDate   ? $planDate   : null,
        $planAmount ? $planAmount : null,
        $executor   ? $executor   : null,
        $currentUserId
    ]);

    logAction($currentUserId, '新增家庭计划', '计划：' . $title);
    jsonResponse(['success' => true, 'msg' => '计划已添加']);

// ===== update: 修改计划 =====
} elseif ($action === 'update') {
    $id         = $_POST['id'] ?? '';
    $title      = trim($_POST['title'] ?? '');
    $planDate   = $_POST['plan_date'] ?? '';
    $planAmount = $_POST['plan_amount'] ?? '';
    $executor   = trim($_POST['executor'] ?? '');

    if (empty($id) || empty($title)) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }

    // 验证权限：创建者或管理员
    $stmt = $pdo->prepare('SELECT created_by FROM family_plans WHERE id = ?');
    $stmt->execute([$id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        jsonResponse(['success' => false, 'error' => '计划不存在']);
    }
    if ($currentRole !== 'admin' && $plan['created_by'] != $currentUserId) {
        jsonResponse(['success' => false, 'error' => '无权限修改此计划']);
    }

    $stmt2 = $pdo->prepare(
        'UPDATE family_plans SET title = ?, planned_date = ?, planned_amount = ?, executor = ?, updated_at = NOW() WHERE id = ?'
    );
    $stmt2->execute([
        $title,
        $planDate   ? $planDate   : null,
        $planAmount ? $planAmount : null,
        $executor   ? $executor   : null,
        $id
    ]);

    logAction($currentUserId, '修改家庭计划', '计划ID：' . $id);
    jsonResponse(['success' => true, 'msg' => '计划已修改']);

// ===== delete: 删除计划 =====
} elseif ($action === 'delete') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }

    $stmt = $pdo->prepare('SELECT created_by FROM family_plans WHERE id = ?');
    $stmt->execute([$id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        jsonResponse(['success' => false, 'error' => '计划不存在']);
    }
    if ($currentRole !== 'admin' && $plan['created_by'] != $currentUserId) {
        jsonResponse(['success' => false, 'error' => '无权限删除此计划']);
    }

    $stmt2 = $pdo->prepare('DELETE FROM family_plans WHERE id = ?');
    $stmt2->execute([$id]);

    logAction($currentUserId, '删除家庭计划', '计划ID：' . $id);
    jsonResponse(['success' => true, 'msg' => '计划已删除']);

// ===== toggle_done: 标记完成/重新打开 =====
} elseif ($action === 'toggle_done') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }

    $stmt = $pdo->prepare('UPDATE family_plans SET is_done = NOT is_done WHERE id = ?');
    $stmt->execute([$id]);

    logAction($currentUserId, '切换计划完成状态', '计划ID：' . $id);
    jsonResponse(['success' => true, 'msg' => '操作成功']);

// ===== toggle_agree: 同意/取消同意 =====
} elseif ($action === 'toggle_agree') {
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        jsonResponse(['success' => false, 'error' => '参数错误']);
    }

    // 获取当前同意人列表
    $stmt = $pdo->prepare('SELECT approvers FROM family_plans WHERE id = ?');
    $stmt->execute([$id]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$plan) {
        jsonResponse(['success' => false, 'error' => '计划不存在']);
    }

    $agreeList = $plan['approvers'] ? json_decode($plan['approvers'], true) : [];
    $idx = array_search($currentUserId, $agreeList);
    if ($idx !== false) {
        array_splice($agreeList, $idx, 1);   // 已同意 → 取消同意
    } else {
        $agreeList[] = $currentUserId;         // 未同意 → 添加同意
    }
    $agreeJson = json_encode(array_values($agreeList));

    $stmt2 = $pdo->prepare('UPDATE family_plans SET approvers = ? WHERE id = ?');
    $stmt2->execute([$agreeJson, $id]);

    jsonResponse(['success' => true, 'msg' => '操作成功']);
}

} catch (PDOException $e) {
    error_log('[plans] PDO Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => '数据库错误：' . $e->getMessage()]);
} catch (Exception $e) {
    error_log('[plans] Error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'error' => '操作出错：' . $e->getMessage()]);
}
