<?php
require_once __DIR__.'/../config.php';

header('Content-Type: application/json; charset=utf-8');

// 检查登录
if (!isLoggedIn()) {
    echo json_encode(array('success' => false, 'msg' => '未登录'));
    exit;
}

try {
    $pdo = getDB();
    
    // 获取总计
    $stmt = $pdo->query('SELECT COUNT(*) as cnt, COALESCE(SUM(amount),0) as total FROM expense_records');
    $row = $stmt->fetch();
    
    echo json_encode(array(
        'success' => true,
        'total_amount' => number_format($row['total'], 2),
        'total_count' => intval($row['cnt'])
    ));
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'msg' => '数据库错误'));
}

