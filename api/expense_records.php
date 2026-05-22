<?php
require_once __DIR__.'/../config.php';

header('Content-Type: application/json; charset=utf-8');

// 检查登录
if (!isLoggedIn()) {
    echo json_encode(array('success' => false, 'msg' => '未登录'));
    exit;
}

$currentUserId = CURRENT_USER_ID();
$isAdmin = (CURRENT_ROLE() === 'admin');

// 分页
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// 搜索条件
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

try {
    $pdo = getDB();
    
    // 构建查询
    $where = '1=1';
    $params = array();
    
    // 如果不是管理员，只能看自己的记录
    if (!$isAdmin) {
        $where .= ' AND user_id = ?';
        $params[] = $currentUserId;
    }
    
    if ($search) {
        $where .= ' AND (person LIKE ? OR purpose LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($category) {
        $where .= ' AND category = ?';
        $params[] = $category;
    }
    
    if ($startDate) {
        $where .= ' AND DATE(created_at) >= ?';
        $params[] = $startDate;
    }
    
    if ($endDate) {
        $where .= ' AND DATE(created_at) <= ?';
        $params[] = $endDate;
    }
    
    // 获取总数
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expense_records WHERE $where");
    $stmt->execute($params);
    $total = $stmt->fetch()['cnt'];
    $totalPages = ceil($total / $pageSize);
    
    // 获取记录 - MySQL 使用 LIMIT offset, pageSize 语法
    $stmt = $pdo->prepare("SELECT * FROM expense_records WHERE $where ORDER BY created_at DESC LIMIT ?, ?");
    $stmt->execute(array_merge($params, array($offset, $pageSize)));
    $records = $stmt->fetchAll();
    
    echo json_encode(array(
        'success' => true,
        'records' => $records,
        'total' => $total,
        'total_pages' => $totalPages,
        'page' => $page
    ));
    
} catch (Exception $e) {
    echo json_encode(array('success' => false, 'msg' => '数据库错误'));
}
