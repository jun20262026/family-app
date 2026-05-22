<?php
/**
 * 首页统计 API
 * GET: 返回昨日/今日/上月/本月 捐款&支出统计
 * 权限：所有登录用户都能看到全局统计数据
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

// 今日
$today = date('Y-m-d');
// 昨日
$yesterday = date('Y-m-d', strtotime('-1 day'));
// 本月第一天
$thisMonthStart = date('Y-m-01');
// 上月第一天
$lastMonthStart = date('Y-m-01', strtotime('-1 month'));
// 上月最后一天
$lastMonthEnd = date('Y-m-d', strtotime('last day of last month'));

function getDonationStats($pdo, $start, $end) {
    $stmt = $pdo->prepare('SELECT COUNT(*) as cnt, SUM(amount) as total FROM donations WHERE donate_date BETWEEN ? AND ?');
    $stmt->execute([$start, $end]);
    return $stmt->fetch();
}

function getExpenseStats($pdo, $start, $end) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, SUM(amount) as total FROM wallet_transactions WHERE trans_type = 'expense' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$start, $end]);
    return $stmt->fetch();
}

$todayDonation   = getDonationStats($pdo, $today, $today);
$yesterdayDonation = getDonationStats($pdo, $yesterday, $yesterday);
$thisMonthDonation = getDonationStats($pdo, $thisMonthStart, $today);
$lastMonthDonation = getDonationStats($pdo, $lastMonthStart, $lastMonthEnd);

$todayExpense   = getExpenseStats($pdo, $today, $today);
$yesterdayExpense = getExpenseStats($pdo, $yesterday, $yesterday);
$thisMonthExpense = getExpenseStats($pdo, $thisMonthStart, $today);
$lastMonthExpense = getExpenseStats($pdo, $lastMonthStart, $lastMonthEnd);

jsonResponse([
    'success' => true,
    'data' => [
        'today_donation'     => ['count' => (int)($todayDonation['cnt'] ?: 0), 'total' => (float)($todayDonation['total'] ?: 0)],
        'yesterday_donation' => ['count' => (int)($yesterdayDonation['cnt'] ?: 0), 'total' => (float)($yesterdayDonation['total'] ?: 0)],
        'this_month_donation'=> ['count' => (int)($thisMonthDonation['cnt'] ?: 0), 'total' => (float)($thisMonthDonation['total'] ?: 0)],
        'last_month_donation'=> ['count' => (int)($lastMonthDonation['cnt'] ?: 0), 'total' => (float)($lastMonthDonation['total'] ?: 0)],
        'today_expense'     => ['count' => (int)($todayExpense['cnt'] ?: 0), 'total' => (float)($todayExpense['total'] ?: 0)],
        'yesterday_expense' => ['count' => (int)($yesterdayExpense['cnt'] ?: 0), 'total' => (float)($yesterdayExpense['total'] ?: 0)],
        'this_month_expense'=> ['count' => (int)($thisMonthExpense['cnt'] ?: 0), 'total' => (float)($thisMonthExpense['total'] ?: 0)],
        'last_month_expense'=> ['count' => (int)($lastMonthExpense['cnt'] ?: 0), 'total' => (float)($lastMonthExpense['total'] ?: 0)],
    ]
]);
