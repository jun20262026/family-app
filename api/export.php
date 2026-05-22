<?php
/**
 * 导出 CSV
 * GET ?type=donations|wallets
 * 权限：兄弟导出自己的，管理员导出全部
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

// 仅管理员可导出
if (CURRENT_ROLE() !== 'admin') {
    header('Content-Type: text/plain; charset=utf-8');
    exit('无权限：只有管理员可以导出数据');
}

$type = $_GET['type'] ?? 'donations';
$role = CURRENT_ROLE();
$meId   = CURRENT_USER_ID();

if ($type === 'donations') {
    // 捐款记录
    if ($role === 'admin') {
        $stmt = $pdo->query(
            'SELECT d.id, d.donor_name, d.amount, d.donate_date, d.source, d.payment_method, d.note, d.created_at, u.brother_name
             FROM donations d
             LEFT JOIN users u ON d.brother_id = u.id
             ORDER BY d.donate_date DESC, d.id DESC'
        );
    } else {
        $stmt = $pdo->prepare(
            'SELECT d.id, d.donor_name, d.amount, d.donate_date, d.source, d.payment_method, d.note, d.created_at
             FROM donations d
             WHERE d.brother_id = ?
             ORDER BY d.donate_date DESC, d.id DESC'
        );
        $stmt->execute([$meId]);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donations_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM for Excel
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['编号', '捐款人', '金额', '捐款日期', '来源', '支付方式', '备注', '登记时间', '登记人']);

    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'], $row['donor_name'], $row['amount'],
            $row['donate_date'], $row['source'], $row['payment_method'],
            $row['note'], $row['created_at'], $row['brother_name'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if ($type === 'wallets') {
    // 钱包交易记录
    if ($role === 'admin') {
        $stmt = $pdo->query(
            'SELECT * FROM wallet_transactions ORDER BY created_at DESC'
        );
    } else {
        $meName = CURRENT_BROTHER_NAME();
        $stmt = $pdo->prepare('SELECT * FROM wallet_transactions WHERE person_name = ? ORDER BY created_at DESC');
        $stmt->execute([$meName]);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wallet_tx_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['编号', '人员', '类型', '金额', '关联人', '说明', '凭证', '时间']);

    $typeMap = ['receive' => '入账', 'expense' => '支出', 'transfer' => '转出'];
    while ($row = $stmt->fetch()) {
        fputcsv($out, [
            $row['id'], $row['person_name'], $typeMap[$row['trans_type']] ?? $row['trans_type'],
            $row['amount'], $row['related_name'], $row['description'],
            $row['image_path'], $row['created_at'],
        ]);
    }
    fclose($out);
    exit;
}

echo '未知导出类型';
