<?php
/**
 * 钱包 API
 * GET: 查询余额 + 交易记录 + 所有钱包列表
 * POST: 消费/转账（充值功能已移除）
 */
// 错误处理：捕获所有 PHP 错误并返回 JSON
set_error_handler(function($errno, $errstr) {
    jsonResponse(['success' => false, 'msg' => 'PHP错误[' . $errno . ']: ' . $errstr]);
});
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ===== 查询所有钱包余额 =====
if ($method === 'GET' && $action === 'balances') {
    try {
        $stmt = $pdo->query('SELECT * FROM wallets');
        $rows = $stmt->fetchAll();
        jsonResponse(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        // 如果表结构有问题，返回空数据而不是报错
        error_log('wallets query error: ' . $e->getMessage());
        jsonResponse(['success' => true, 'data' => []]);
    }
}

// ===== 查询兄弟列表（用于转账下拉） =====
if ($method === 'GET' && $action === 'brothers_list') {
    $rows = GET_OTHER_BROTHERS();
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
    // 兼容 FormData（前端）和 JSON
    if (!empty($_POST)) {
        // 前端发送 FormData
        $amount      = floatval($_POST['amount']      ?? 0);
        $description = trim($_POST['description'] ?? '');
        $category    = trim($_POST['category']   ?? '');
        $person_key  = $_POST['person_key']  ?? '';
        $image_path  = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $fname = date('YmdHis') . '_' . mt_rand(1000,9999) . '.' . $ext;
            @move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $fname);
            $image_path = UPLOAD_URL . $fname;
        }
    } else {
        // 前端发送 JSON
        $input = json_decode(file_get_contents('php://input'), true);
        $amount      = floatval($input['amount']      ?? 0);
        $description = trim($input['description'] ?? '');
        $category    = trim($input['category']   ?? '');
        $person_key  = $input['person_key']  ?? '';
        $image_path  = $input['image_path']  ?? '';
    }

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
             (person_key, person_name, trans_type, amount, balance_after, description, category, image_path, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $person_key,
            $wallet['person_name'],
            'expense',
            $amount,
            $newBalance,
            $description,
            $category,
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
    // 兼容 FormData（前端）和 JSON
    if (isset($_POST['from_key'])) {
        $amount       = floatval($_POST['amount']      ?? 0);
        $from_key     = $_POST['from_key']    ?? '';
        $to_key       = $_POST['to_key']      ?? '';
        $description  = trim($_POST['description'] ?? '');
        $image_path   = '';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            $fname = date('YmdHis') . '_' . mt_rand(1000,9999) . '.' . $ext;
            @move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_DIR . $fname);
            $image_path = UPLOAD_URL . $fname;
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $amount       = floatval($input['amount']      ?? 0);
        $from_key     = $input['from_key']    ?? '';
        $to_key       = $input['to_key']      ?? '';
        $description  = trim($input['description'] ?? '');
        $image_path   = $input['image_path']  ?? '';
    }

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
if ($method === 'POST' && $action === 'revert') {
    // 支持前端传 id 参数（URL query string）
    if (isset($_GET['id'])) {
        $tx_id = intval($_GET['id']);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $tx_id = intval($input['tx_id'] ?? 0);
    }

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
    $auditId = createAuditLog(CURRENT_USER_ID(), 'revert', 'transaction', $tx_id, $oldData, null);

    jsonResponse(['success' => true, 'msg' => '撤回申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

// ===== 交易记录修改金额（需审核）=====
if ($method === 'POST' && $action === 'edit_amount') {
    // 支持前端传 id + amount 参数
    if (isset($_GET['id'])) {
        $tx_id = intval($_GET['id']);
        $input = json_decode(file_get_contents('php://input'), true);
        $new_amount = floatval($input['amount'] ?? 0);
    } else {
        $input = json_decode(file_get_contents('php://input'), true);
        $tx_id     = intval($input['tx_id']     ?? 0);
        $new_amount = floatval($input['new_amount'] ?? 0);
    }

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
    $auditId = createAuditLog(CURRENT_USER_ID(), 'edit_amount', 'transaction', $tx_id, $oldData, $newData);

    jsonResponse(['success' => true, 'msg' => '修改申请已提交，请等待管理员审核', 'audit_id' => $auditId]);
}

// helper：同时统计 wallet_transactions + expense_records（避免在 if 块内定义函数导致重声明错误）
function _wallet_getTotalExpense($pdo, $month = null, $date = null) {
    $total = 0;

    // wallet_transactions
    try {
        if ($month) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE trans_type = 'expense' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
        } elseif ($date) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE trans_type = 'expense' AND DATE(created_at) = ?");
            $stmt->execute([$date]);
        } else {
            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE trans_type = 'expense'");
        }
        $total += floatval($stmt->fetchColumn());
    } catch (Exception $e) {}

    // expense_records
    try {
        if ($month) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
            $stmt->execute([$month]);
        } elseif ($date) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expense_records WHERE DATE(created_at) = ?");
            $stmt->execute([$date]);
        } else {
            $stmt = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM expense_records");
        }
        $total += floatval($stmt->fetchColumn());
    } catch (Exception $e) {}

    return $total;
}

// ===== 支出统计（本月、上月） =====
if ($method === 'GET' && $action === 'expense_stats') {
    $currentMonth = date('Y-m');
    $lastMonth    = date('Y-m', strtotime('-1 month'));
    $today        = date('Y-m-d');

    $currentMonthTotal = _wallet_getTotalExpense($pdo, $currentMonth, null);
    $lastMonthTotal    = _wallet_getTotalExpense($pdo, $lastMonth, null);
    $todayTotal        = _wallet_getTotalExpense($pdo, null, $today);

    jsonResponse([
        'success'         => true,
        'current_month'   => $currentMonth,
        'last_month'      => $lastMonth,
        'current_total'   => $currentMonthTotal,
        'last_total'      => $lastMonthTotal,
        'today_total'     => $todayTotal,
    ]);
}

// ===== 查询所有支出记录（款项用途页面） =====
if ($method === 'GET' && $action === 'expense_records') {
    $search     = trim($_GET['search']      ?? '');
    $category   = trim($_GET['category']    ?? '');
    $start_date = trim($_GET['start_date']  ?? '');
    $end_date   = trim($_GET['end_date']    ?? '');
    $page       = max(1, intval($_GET['page'] ?? 1));
    $pageSize   = 20;
    $offset     = ($page - 1) * $pageSize;

    // ---------- 查询 wallet_transactions 表 ----------
    $wtWhere = "WHERE trans_type = 'expense'";
    $wtParams = [];
    if ($search !== '') {
        $wtWhere .= " AND (person_name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $wtParams[] = "%{$search}%";
        $wtParams[] = "%{$search}%";
        $wtParams[] = "%{$search}%";
    }
    if ($category !== '') {
        $wtWhere .= " AND category = ?";
        $wtParams[] = $category;
    }
    if ($start_date !== '') {
        $wtWhere .= " AND DATE(created_at) >= ?";
        $wtParams[] = $start_date;
    }
    if ($end_date !== '') {
        $wtWhere .= " AND DATE(created_at) <= ?";
        $wtParams[] = $end_date;
    }

    $wtData = [];
    $wtTotal = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM wallet_transactions {$wtWhere}");
        $stmt->execute($wtParams);
        $wtTotal = intval($stmt->fetchColumn());

        $stmt = $pdo->prepare("SELECT * FROM wallet_transactions {$wtWhere} ORDER BY created_at DESC");
        $stmt->execute($wtParams);
        $wtData = $stmt->fetchAll();
    } catch (Exception $e) {}

    // ---------- 查询 expense_records 表 ----------
    $erWhere = "WHERE 1=1";
    $erParams = [];
    if ($search !== '') {
        $erWhere .= " AND (person LIKE ? OR purpose LIKE ? OR category LIKE ?)";
        $erParams[] = "%{$search}%";
        $erParams[] = "%{$search}%";
        $erParams[] = "%{$search}%";
    }
    if ($category !== '') {
        $erWhere .= " AND category = ?";
        $erParams[] = $category;
    }
    if ($start_date !== '') {
        $erWhere .= " AND DATE(created_at) >= ?";
        $erParams[] = $start_date;
    }
    if ($end_date !== '') {
        $erWhere .= " AND DATE(created_at) <= ?";
        $erParams[] = $end_date;
    }

    $erData = [];
    $erTotal = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM expense_records {$erWhere}");
        $stmt->execute($erParams);
        $erTotal = intval($stmt->fetchColumn());

        $stmt = $pdo->prepare("SELECT id, person as person_name, category, amount, purpose as description, receipt_path as image_path, created_at, 'expense_records' as source FROM expense_records {$erWhere} ORDER BY created_at DESC");
        $stmt->execute($erParams);
        $erData = $stmt->fetchAll();
    } catch (Exception $e) {}

    // ---------- 合并、排序、分页 ----------
    $allData = array_merge($wtData, $erData);
    usort($allData, function($a, $b) {
        $tA = strtotime($a['created_at'] ?: '1970-01-01');
        $tB = strtotime($b['created_at'] ?: '1970-01-01');
        return $tB - $tA;
    });

    $total = $wtTotal + $erTotal;
    $filteredTotal = 0;
    foreach ($allData as $r) {
        $filteredTotal += floatval($r['amount'] ?? 0);
    }
    $rows = array_slice($allData, $offset, $pageSize);

    // ---------- 分类列表（合并两个表） ----------
    $allCategories = [];
    try {
        $catStmt = $pdo->query("SELECT DISTINCT category FROM wallet_transactions WHERE trans_type='expense' AND category IS NOT NULL AND category != '' ORDER BY category");
        $allCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}
    try {
        $catStmt = $pdo->query("SELECT DISTINCT category FROM expense_records WHERE category IS NOT NULL AND category != '' ORDER BY category");
        $erCats = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        $allCategories = array_unique(array_merge($allCategories, $erCats));
        sort($allCategories);
    } catch (Exception $e) {}

    jsonResponse([
        'success'        => true,
        'data'           => $rows,
        'categories'     => array_values($allCategories),
        'total'          => $total,
        'filtered_total' => $filteredTotal,
        'page'           => $page,
        'page_size'      => $pageSize,
        'total_pages'    => ceil($total / $pageSize),
    ]);
}

jsonResponse(['success' => false, 'msg' => '未知操作']);
?>
