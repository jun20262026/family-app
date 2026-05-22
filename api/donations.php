<?php
/**
 * 捐款记录 API
 * GET: 列表（mine=1 仅自己；rank=1 排行；stats=1 统计）
 * POST: 新增（自动入钱包）
 * PUT: 修改（需审核）
 * DELETE: 删除（需审核）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$mine = intval($_GET['mine'] ?? 0);
$rank = intval($_GET['rank'] ?? 0);

// ===== 捐款人排行 =====
if ($method === 'GET' && $rank === 1) {
    $sql = 'SELECT donor_name, SUM(amount) AS total_amount, COUNT(*) AS donate_count FROM donations GROUP BY donor_name ORDER BY total_amount DESC, donate_count DESC';
    $rows = $pdo->query($sql)->fetchAll();
    jsonResponse(['success' => true, 'data' => $rows]);
}

// ===== 统计接口（首页用）=====
if ($method === 'GET' && isset($_GET['stats'])) {
    // 总捐款金额 + 笔数（所有用户的捐款）
    $totalStmt = $pdo->query('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM donations');
    $totalRow = $totalStmt->fetch();

    // 个人收款金额 + 笔数（当前用户收到的捐款）
    $personalStmt = $pdo->prepare('SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM donations WHERE brother_id = ?');
    $personalStmt->execute([CURRENT_USER_ID()]);
    $personalRow = $personalStmt->fetch();

    jsonResponse(['success' => true, 'data' => [
        'total_amount' => $totalRow['total'],
        'total_count' => $totalRow['cnt'],
        'personal_amount' => $personalRow['total'],
        'personal_count' => $personalRow['cnt']
    ]]);
}

// ===== 获取单条捐款记录 =====
if ($method === 'GET' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $pdo->prepare('SELECT d.*, 
                   u.brother_name,
                   u2.brother_name AS created_by_name
            FROM donations d
            JOIN users u ON d.brother_id = u.id
            LEFT JOIN users u2 ON d.created_by = u2.id
            WHERE d.id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    
    if ($row) {
        jsonResponse(['success' => true, 'data' => $row]);
    } else {
        jsonResponse(['success' => false, 'msg' => '记录不存在']);
    }
}

// ===== 捐款列表（支持搜索筛选）=====
if ($method === 'GET') {
    $keyword   = trim($_GET['keyword']   ?? '');
    $source    = trim($_GET['source']    ?? '');
    $dateFrom  = trim($_GET['date_from'] ?? '');
    $dateTo    = trim($_GET['date_to']   ?? '');

    $where  = [];
    $params = [];

    if ($keyword !== '') {
        $where[]  = '(d.donor_name LIKE ? OR d.note LIKE ?)';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
    }
    if ($source !== '') {
        $where[]  = 'd.source = ?';
        $params[] = $source;
    }
    if ($dateFrom !== '') {
        $where[]  = 'd.donate_date >= ?';
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $where[]  = 'd.donate_date <= ?';
        $params[] = $dateTo;
    }

    $whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

    $sql = 'SELECT d.*, 
                   u.brother_name,
                   u2.brother_name AS created_by_name
            FROM donations d
            JOIN users u ON d.brother_id = u.id
            LEFT JOIN users u2 ON d.created_by = u2.id'
           . $whereSql .
           ' ORDER BY d.donate_date DESC, d.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // 同时返回该结果的汇总数据
    $totalAmount = array_sum(array_column($rows, 'amount'));
    $totalCount  = count($rows);

    jsonResponse(['success' => true, 'data' => $rows, 'total_amount' => $totalAmount, 'total_count' => $totalCount]);
}

// ===== 新增捐款 =====
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $donor_name    = trim($input['donor_name']    ?? '');
    $amount         = floatval($input['amount']     ?? 0);
    $donate_date    = $input['donate_date']       ?? date('Y-m-d');
    $source         = $input['source']            ?? '本人好友';
    $payment_method = $input['payment_method']    ?? '微信';
    $note           = $input['note']              ?? '';

    if (!$donor_name || $amount <= 0) {
        jsonResponse(['success' => false, 'msg' => '捐款人姓名和金额不能为空']);
    }

    $pdo->beginTransaction();
    try {
        $brother_id = CURRENT_USER_ID();

        // 插入捐款记录
        $stmt = $pdo->prepare(
            'INSERT INTO donations
             (brother_id, donor_name, amount, donate_date, source, payment_method, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$brother_id, $donor_name, $amount, $donate_date, $source, $payment_method, $note, $brother_id]);

        // 自动充值到当前用户的钱包
        $brotherName = CURRENT_BROTHER_NAME();
        $stmt = $pdo->prepare('SELECT person_key FROM wallets WHERE person_name = ?');
        $stmt->execute([$brotherName]);
        $wallet = $stmt->fetch();

        if ($wallet) {
            $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                 ->execute([$amount, $wallet['person_key']]);

            // 获取交易后余额
            $stmt = $pdo->prepare('SELECT balance FROM wallets WHERE person_key = ?');
            $stmt->execute([$wallet['person_key']]);
            $newBalance = $stmt->fetch()['balance'];

            // 获取刚插入的捐款记录ID
            $donationId = $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO wallet_transactions
                 (person_key, person_name, trans_type, amount, balance_after, description, donation_id, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$wallet['person_key'], $brotherName, 'receive', $amount, $newBalance, "捐款入账：" . $donor_name, $donationId, $brother_id]);
        }

        $pdo->commit();
        logAction($brother_id, '登记捐款', '捐款人：' . $donor_name . '，金额：¥' . $amount);
        jsonResponse(['success' => true, 'msg' => '登记成功，已入账到我的钱包']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

// ===== 修改捐款（需审核）=====
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id'] ?? 0);

    if (!$id) {
        jsonResponse(['success' => false, 'msg' => '缺少ID']);
    }

    // 获取原记录
    $stmt = $pdo->prepare('SELECT * FROM donations WHERE id = ?');
    $stmt->execute([$id]);
    $oldData = $stmt->fetch();

    if (!$oldData) {
        jsonResponse(['success' => false, 'msg' => '记录不存在']);
    }

    // 创建审核记录
    $newData = [
        'donor_name'    => $input['donor_name']    ?? $oldData['donor_name'],
        'amount'         => floatval($input['amount']     ?? $oldData['amount']),
        'donate_date'    => $input['donate_date']    ?? $oldData['donate_date'],
        'source'         => $input['source']         ?? $oldData['source'],
        'payment_method' => $input['payment_method'] ?? $oldData['payment_method'],
        'note'           => $input['note']           ?? $oldData['note']
    ];

    $auditId = createAuditLog(CURRENT_USER_ID(), 'edit', 'donation', $id, $oldData, $newData);
    logAction(CURRENT_USER_ID(), '申请修改捐款', '捐款ID：' . $id . '，新金额：¥' . $newData['amount'] . '，新捐款人：' . $newData['donor_name']);
    jsonResponse(['success' => true, 'msg' => '修改申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

// ===== 删除捐款（需审核）=====
if ($method === 'DELETE') {
    $id = intval($_GET['id'] ?? 0);
    if (!$id) {
        jsonResponse(['success' => false, 'msg' => '缺少ID']);
    }

    // 获取原记录
    $stmt = $pdo->prepare('SELECT * FROM donations WHERE id = ?');
    $stmt->execute([$id]);
    $oldData = $stmt->fetch();

    if (!$oldData) {
        jsonResponse(['success' => false, 'msg' => '记录不存在']);
    }

    // 创建审核记录
    $auditId = createAuditLog(CURRENT_USER_ID(), 'delete', 'donation', $id, $oldData, null);
    logAction(CURRENT_USER_ID(), '申请删除捐款', '捐款ID：' . $id . '，捐款人：' . $oldData['donor_name'] . '，金额：¥' . $oldData['amount']);
    jsonResponse(['success' => true, 'msg' => '删除申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

jsonResponse(['success' => false, 'msg' => '不支持的请求']);
?>