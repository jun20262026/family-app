<?php
/**
 * 统计汇总 API
 * GET: 返回完整统计数据
 * 权限：兄弟看自己的，管理员看全部
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$role   = CURRENT_ROLE();
$meId   = CURRENT_USER_ID();
$meName = CURRENT_BROTHER_NAME();

// ===== 时间范围 =====
$startDate = $_GET['start'] ?? date('Y-m-01');   // 本月1日
$endDate   = $_GET['end']   ?? date('Y-m-d');       // 今天

// ===== 基础统计 =====
if ($role === 'admin') {
    // 管理员：全部
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt, SUM(amount) AS total
         FROM donations
         WHERE donate_date BETWEEN ? AND ?'
    );
    $stmt->execute([$startDate, $endDate]);
} else {
    // 兄弟：自己的
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt, SUM(amount) AS total
         FROM donations
         WHERE brother_id = ? AND donate_date BETWEEN ? AND ?'
    );
    $stmt->execute([$meId, $startDate, $endDate]);
}
$basic = $stmt->fetch();

// ===== 按来源汇总 =====
if ($role === 'admin') {
    $stmt = $pdo->prepare(
        'SELECT source, SUM(amount) AS total, COUNT(*) AS cnt
         FROM donations
         WHERE donate_date BETWEEN ? AND ?
         GROUP BY source'
    );
    $stmt->execute([$startDate, $endDate]);
} else {
    $stmt = $pdo->prepare(
        'SELECT source, SUM(amount) AS total, COUNT(*) AS cnt
         FROM donations
         WHERE brother_id = ? AND donate_date BETWEEN ? AND ?
         GROUP BY source'
    );
    $stmt->execute([$meId, $startDate, $endDate]);
}
$bySource = $stmt->fetchAll();

// ===== 按支付方式汇总 =====
if ($role === 'admin') {
    $stmt = $pdo->prepare(
        'SELECT payment_method, SUM(amount) AS total, COUNT(*) AS cnt
         FROM donations
         WHERE donate_date BETWEEN ? AND ?
         GROUP BY payment_method'
    );
    $stmt->execute([$startDate, $endDate]);
} else {
    $stmt->prepare(
        'SELECT payment_method, SUM(amount) AS total, COUNT(*) AS cnt
         FROM donations
         WHERE brother_id = ? AND donate_date BETWEEN ? AND ?
         GROUP BY payment_method'
    );
    $stmt->execute([$meId, $startDate, $endDate]);
}
$byPay = $stmt->fetchAll();

// ===== 钱包总览 =====
if ($role === 'admin') {
    $wallets = $pdo->query('SELECT * FROM wallets ORDER BY person_key')->fetchAll();
} else {
    $stmt = $pdo->prepare('SELECT * FROM wallets WHERE person_name = ?');
    $stmt->execute([$meName]);
    $wallets = $stmt->fetchAll();
}

// ===== 输出 =====
jsonResponse([
    'success' => true,
    'data' => [
        'period' => ['start' => $startDate, 'end' => $endDate],
        'total_amount' => $basic['total'] ?: 0,
        'total_count'  => $basic['cnt']   ?: 0,
        'by_source'    => $bySource,
        'by_pay'       => $byPay,
        'wallets'       => $wallets,
    ]
]);
