<?php
/**
 * 钱包 API
 * GET: 查询余额 + 交易记录 + 所有钱包列表
 * POST: 消费/转账（充值功能已移除）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// ===== 查询所有钱包余额 =====
if ($method === 'GET' && $action === 'balances') {
    $stmt = $pdo->query('SELECT * FROM wallets ORDER BY id');
    $rows = $stmt->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ===== 查询单个钱包余额 =====
if ($method === 'GET' && $action === 'balance') {
    $person_key = $_GET['person_key'] ?? '';
    if (!$person_key) {
        jsonResponse(['success' => false, 'msg' => '缺少参数']);
    }
    $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE person_key=?');
    $stmt->execute([$person_key]);
    $row = $stmt->fetch();
    jsonResponse(['success' => true, 'balance' => floatval($row ? $row['balance'] : 0)]);
}

// ===== 查询交易记录 =====
if ($method === 'GET' && $action === 'transactions') {
    $person_key = $_GET['person_key'] ?? '';
    if (!$person_key) {
        jsonResponse(['success' => false, 'msg' => '缺少参数']);
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM wallet_transactions WHERE person_key=? ORDER BY created_at DESC LIMIT 200'
    );
    $stmt->execute([$person_key]);
    $rows = $stmt->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ===== 消费支出 =====
if ($method === 'POST' && $action === 'expense') {
    $input = json_decode(file_get_contents('php://input'), true);
    $amount      = floatval($input['amount']      ?? 0);
    $description = trim($input['description'] ?? '');
    $person_key  = $input['person_key']  ?? '';
    $image_path  = $input['image_path']  ?? '';

    if ($amount <= 0) {
        jsonResponse(['success' => false, 'msg' => '金额必须大于0']);
    }

    $pdo->beginTransaction();
    try {
        // 检查余额是否充足
        $stmt = $pdo->prepare('SELECT balance, person_name FROM wallets WHERE person_key=?');
        $stmt->execute([$person_key]);
        $wallet = $stmt->fetch();

        if (!$wallet || $wallet['balance'] < $amount) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'msg' => '余额不足']);
        }

        // 扣款
        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
             ->execute([$amount, $person_key]);

        // 获取交易后余额
        $stmt = $pdo->prepare('SELECT balance, person_name FROM wallets WHERE person_key=?');
        $stmt->execute([$person_key]);
        $wallet = $stmt->fetch();
        $newBalance = $wallet['balance'];

        // 记录交易
        $stmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
             (person_key, person_name, trans_type, amount, balance_after, description, image_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $person_key,
            $wallet['person_name'],
            'expense',
            $amount,
            $newBalance,
            $description,
            $image_path,
            CURRENT_USER_ID()
        ]);

        $pdo->commit();
        jsonResponse(['success' => true, 'msg' => '支出登记成功', 'balance' => $newBalance]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

// ===== 转账给兄弟 =====
if ($method === 'POST' && $action === 'transfer') {
    $input = json_decode(file_get_contents('php://input'), true);
    $amount       = floatval($input['amount']      ?? 0);
    $from_key     = $input['from_key']    ?? '';
    $to_key       = $input['to_key']      ?? '';
    $description  = trim($input['description'] ?? '');
    $image_path   = $input['image_path']  ?? '';

    if ($amount <= 0) {
        jsonResponse(['success' => false, 'msg' => '金额必须大于0']);
    }
    if ($from_key === $to_key) {
        jsonResponse(['success' => false, 'msg' => '不能转给自己']);
    }

    $pdo->beginTransaction();
    try {
        // 检查转出方余额
        $stmt = $pdo->prepare('SELECT balance, person_name FROM wallets WHERE person_key=?');
        $stmt->execute([$from_key]);
        $fromWallet = $stmt->fetch();

        if (!$fromWallet || $fromWallet['balance'] < $amount) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'msg' => '余额不足']);
        }

        // 获取转入方信息
        $stmt = $pdo->prepare('SELECT person_name FROM wallets WHERE person_key=?');
        $stmt->execute([$to_key]);
        $toWallet = $stmt->fetch();

        if (!$toWallet) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'msg' => '收款方不存在']);
        }

        // 转出方扣款
        $pdo->prepare('UPDATE wallets SET balance = balance - ? WHERE person_key = ?')
             ->execute([$amount, $from_key]);

        // 获取转出方交易后余额
        $stmt = $pdo->prepare('SELECT balance, person_name FROM wallets WHERE person_key=?');
        $stmt->execute([$from_key]);
        $fromWallet = $stmt->fetch();
        $fromBalance = $fromWallet['balance'];

        // 转入方入账
        $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
             ->execute([$amount, $to_key]);

        // 获取转入方交易后余额
        $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE person_key=?');
        $stmt->execute([$to_key]);
        $toBalance = $stmt->fetch()['balance'];

        // 记录转出交易
        $stmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
             (person_key, person_name, trans_type, amount, balance_after, related_key, related_name, description, image_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $from_key,
            $fromWallet['person_name'],
            'transfer',
            $amount,
            $fromBalance,
            $to_key,
            $toWallet['person_name'],
            $description,
            $image_path,
            CURRENT_USER_ID()
        ]);

        // 记录转入交易
        $stmt = $pdo->prepare(
            'INSERT INTO wallet_transactions
             (person_key, person_name, trans_type, amount, balance_after, related_key, related_name, description, image_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $to_key,
            $toWallet['person_name'],
            'receive',
            $amount,
            $toBalance,
            $from_key,
            $fromWallet['person_name'],
            '来自' . $fromWallet['person_name'] . '的转账',
            $image_path,
            CURRENT_USER_ID()
        ]);

        $pdo->commit();
        jsonResponse(['success' => true, 'msg' => '转账成功', 'balance' => $fromBalance]);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

// ===== 交易记录撤回（需审核）=====
if ($method === 'POST' && $action === 'withdraw') {
    $input = json_decode(file_get_contents('php://input'), true);
    $tx_id = intval($input['tx_id'] ?? 0);

    if (!$tx_id) {
        jsonResponse(['success' => false, 'msg' => '缺少交易ID']);
    }

    // 获取原交易记录
    $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE id = ?');
    $stmt->execute([$tx_id]);
    $oldData = $stmt->fetch();

    if (!$oldData) {
        jsonResponse(['success' => false, 'msg' => '交易记录不存在']);
    }

    // 创建审核记录
    $auditId = createAuditLog(CURRENT_USER_ID(), 'transaction_withdraw', 'wallet_transaction', $tx_id, $oldData, null);

    jsonResponse(['success' => true, 'msg' => '撤回申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

// ===== 交易记录修改金额（需审核）=====
if ($method === 'POST' && $action === 'modify_amount') {
    $input = json_decode(file_get_contents('php://input'), true);
    $tx_id     = intval($input['tx_id']     ?? 0);
    $new_amount = floatval($input['new_amount'] ?? 0);

    if (!$tx_id || $new_amount <= 0) {
        jsonResponse(['success' => false, 'msg' => '参数错误']);
    }

    // 获取原交易记录
    $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE id = ?');
    $stmt->execute([$tx_id]);
    $oldData = $stmt->fetch();

    if (!$oldData) {
        jsonResponse(['success' => false, 'msg' => '交易记录不存在']);
    }

    // 创建审核记录
    $newData = ['amount' => $new_amount];
    $auditId = createAuditLog(CURRENT_USER_ID(), 'transaction_modify', 'wallet_transaction', $tx_id, $oldData, $newData);

    jsonResponse(['success' => true, 'msg' => '修改申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

jsonResponse(['success' => false, 'msg' => '未知操作']);
?>
