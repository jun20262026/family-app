<?php
/**
 * 审核 API
 * GET: 列表（管理员看所有待审核，用户看自己的）
 * POST: 审核通过/拒绝（仅管理员）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];

// ===== 审核列表 =====
if ($method === 'GET') {
    $statusFilter = $_GET['status'] ?? 'pending';
    
    $sql = 'SELECT al.*, u.username, u.brother_name AS requester_name
            FROM audit_log al
            JOIN users u ON al.user_id = u.id';
    $params = [];

    if (CURRENT_ROLE() !== 'admin') {
        // 非管理员只能看自己的申请
        $sql .= ' WHERE al.user_id = ?';
        $params = [CURRENT_USER_ID()];
    } else {
        // 管理员：根据过滤条件
        if ($statusFilter !== 'all') {
            $sql .= ' WHERE al.status = ?';
            $params = [$statusFilter];
        }
    }

    $sql .= ' ORDER BY al.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 解析JSON数据
    foreach ($rows as &$row) {
        $row['old_data'] = $row['old_data'] ? json_decode($row['old_data'], true) : null;
        $row['new_data'] = $row['new_data'] ? json_decode($row['new_data'], true) : null;
    }

    jsonResponse(['success' => true, 'data' => $rows]);
}

// ===== 创建审核记录（用户申请）=====
if ($method === 'POST' && !isset($_GET['action'])) {
    requireLogin();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $actionType = $input['action_type'] ?? '';
    $targetType = $input['target_type'] ?? '';
    $targetId   = intval($input['target_id'] ?? 0);
    $newData    = $input['new_data'] ?? null;
    
    if (!$actionType || !$targetType || !$targetId) {
        jsonResponse(['success' => false, 'msg' => '参数错误']);
    }
    
    // 获取原数据
    $oldData = null;
    if ($targetType === 'donation') {
        $stmt = $pdo->prepare('SELECT * FROM donations WHERE id = ?');
        $stmt->execute([$targetId]);
        $oldData = $stmt->fetch();
    } elseif ($targetType === 'transaction') {
        $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE id = ?');
        $stmt->execute([$targetId]);
        $oldData = $stmt->fetch();
    }
    
    // 创建审核记录
    $stmt = $pdo->prepare('INSERT INTO audit_log (user_id, action_type, target_type, target_id, old_data, new_data) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        CURRENT_USER_ID(),
        $actionType,
        $targetType,
        $targetId,
        $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
        $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null
    ]);
    
    jsonResponse(['success' => true, 'msg' => '申请已提交，请等待管理员审核']);
}

// ===== 审核操作（仅管理员）=====
if ($method === 'POST' && isset($_GET['action'])) {
    requireAdmin();
    
    $action   = $_GET['action'];  // approve 或 reject
    $audit_id = intval($_GET['id'] ?? 0);
    
    if (!$audit_id || !in_array($action, ['approve', 'reject'])) {
        jsonResponse(['success' => false, 'msg' => '参数错误']);
    }
    
    // 获取审核记录
    $stmt = $pdo->prepare('SELECT * FROM audit_log WHERE id = ? AND status = "pending"');
    $stmt->execute([$audit_id]);
    $audit = $stmt->fetch();
    
    if (!$audit) {
        jsonResponse(['success' => false, 'msg' => '审核记录不存在或已处理']);
    }
    
    $pdo->beginTransaction();
    try {
        if ($action === 'reject') {
            // 拒绝：只更新审核状态
            $stmt = $pdo->prepare('UPDATE audit_log SET status = "rejected", admin_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([CURRENT_USER_ID(), $audit_id]);
            
            $pdo->commit();
            logAction(CURRENT_USER_ID(), '拒绝审核', '审核ID：' . $audit_id);
            jsonResponse(['success' => true, 'msg' => '已拒绝']);
        }
        
        // 通过：根据类型执行相应操作
        $targetType = $audit['target_type'];
        $targetId   = $audit['target_id'];
        $actionType = $audit['action_type'];
        $oldData    = json_decode($audit['old_data'], true);
        $newData    = json_decode($audit['new_data'], true);
        
        if ($targetType === 'donation') {
            if ($actionType === 'edit') {
                // 修改捐款记录
                $stmt = $pdo->prepare('UPDATE donations SET donor_name = ?, amount = ? WHERE id = ?');
                $stmt->execute([
                    $newData['donor_name'] ?? $oldData['donor_name'],
                    $newData['amount'] ?? $oldData['amount'],
                    $targetId
                ]);
            } elseif ($actionType === 'delete') {
                // 删除捐款记录（同时回滚钱包余额）
                $stmt = $pdo->prepare('SELECT brother_id, amount FROM donations WHERE id = ?');
                $stmt->execute([$targetId]);
                $donation = $stmt->fetch();
                
                if ($donation) {
                    // 回滚钱包余额（减去捐款金额）
                    $stmt = $pdo->prepare('SELECT person_key FROM wallets WHERE person_name = (SELECT brother_name FROM users WHERE id = ?)');
                    $stmt->execute([$donation['brother_id']]);
                    $wallet = $stmt->fetch();
                    
                    if ($wallet) {
                        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
                             ->execute([$donation['amount'], $wallet['person_key']]);
                    }
                    
                    // 删除捐款记录
                    $pdo->prepare('DELETE FROM donations WHERE id = ?')->execute([$targetId]);
                }
            }
        } elseif ($targetType === 'transaction') {
            if ($actionType === 'revert') {
                // 撤回交易：回滚余额 + 删除交易记录
                $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE id = ?');
                $stmt->execute([$targetId]);
                $tx = $stmt->fetch();
                
                if ($tx) {
                    if ($tx['trans_type'] === 'receive') {
                        // 入账撤回：扣减余额
                        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
                             ->execute([$tx['amount'], $tx['person_key']]);
                    } elseif ($tx['trans_type'] === 'expense') {
                        // 支出撤回：恢复余额
                        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                             ->execute([$tx['amount'], $tx['person_key']]);
                    } elseif ($tx['trans_type'] === 'transfer') {
                        // 转账撤回：恢复原状（转出方加回，转入方扣减）
                        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                             ->execute([$tx['amount'], $tx['person_key']]);
                        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
                             ->execute([$tx['amount'], $tx['related_key']]);
                    }
                    
                    // 删除交易记录
                    $pdo->prepare('DELETE FROM wallet_transactions WHERE id = ?')->execute([$targetId]);
                }
            } elseif ($actionType === 'edit_amount') {
                // 修改交易金额：更新交易记录 + 同步调整钱包余额
                $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE id = ?');
                $stmt->execute([$targetId]);
                $tx = $stmt->fetch();
                
                if ($tx) {
                    $oldAmount = $tx['amount'];
                    $newAmount = floatval($newData['amount']);
                    $diff = $newAmount - $oldAmount;
                    
                    // 更新交易金额
                    $pdo->prepare('UPDATE wallet_transactions SET amount = ? WHERE id = ?')
                         ->execute([$newAmount, $targetId]);
                    
                    // 根据交易类型调整余额
                    if ($tx['trans_type'] === 'receive') {
                        // 入账：余额增加 diff
                        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                             ->execute([$diff, $tx['person_key']]);
                    } elseif ($tx['trans_type'] === 'expense') {
                        // 支出：余额减少 diff
                        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
                             ->execute([$diff, $tx['person_key']]);
                    } elseif ($tx['trans_type'] === 'transfer') {
                        // 转账：转出方余额减少 diff，转入方余额增加 diff
                        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
                             ->execute([$diff, $tx['person_key']]);
                        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                             ->execute([$diff, $tx['related_key']]);
                    }
                }
            }
        }
        
        // 更新审核状态
        $stmt = $pdo->prepare('UPDATE audit_log SET status = "approved", admin_id = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([CURRENT_USER_ID(), $audit_id]);
        
        $pdo->commit();
        logAction(CURRENT_USER_ID(), '通过审核', '审核ID：' . $audit_id . '，操作类型：' . $actionType);
        jsonResponse(['success' => true, 'msg' => '审核通过，操作已生效']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

jsonResponse(['success' => false, 'msg' => '不支持的请求']);
?>