<?php
/**
 * Message notification API
 * Get unread messages (new donations/expenses/transfers)
 * Mark messages as read
 */

require_once __DIR__ . '/../config.php';
$pdo = getDB();

// 调试日志函数
function logDebug($msg) {
    $logFile = __DIR__ . '/../logs/notifications.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
}

// Check login
if (empty($_SESSION['user']['id'])) {
    logDebug('Unauthorized access');
    echo json_encode(array('success' => false, 'msg' => 'Please login first'));
    exit;
}

$currentUserId = $_SESSION['user']['id'];
$currentRole = $_SESSION['user']['role'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

logDebug("action=$action, userId=$currentUserId");

if ($action === 'get_unread') {
    // Get unread messages
    logDebug("get_unread: checking last_notification_check for user $currentUserId");
    $stmt = $pdo->prepare('SELECT last_notification_check FROM users WHERE id = ?');
    $stmt->execute(array($currentUserId));
    $user = $stmt->fetch();
    
    if (!$user) {
        logDebug("get_unread: user $currentUserId not found");
        echo json_encode(array('success' => false, 'msg' => 'User not found'));
        exit;
    }
    
    $lastCheck = $user['last_notification_check'];
    logDebug("get_unread: lastCheck=$lastCheck");

    $messages = array();

    if (!$lastCheck) {
        // Never checked before, initialize to current time, don't show history
        $stmt = $pdo->prepare('UPDATE users SET last_notification_check = NOW() WHERE id = ?');
        $stmt->execute(array($currentUserId));
        echo json_encode(array('success' => true, 'data' => array()));
        exit;
    }

    // 2. Query new donation records after lastCheck
    $stmt = $pdo->prepare('
        SELECT *
        FROM donations
        WHERE created_at > ?
        ORDER BY created_at DESC
        LIMIT 50
    ');
    $stmt->execute(array($lastCheck));
    $newDonations = $stmt->fetchAll();

    foreach ($newDonations as $d) {
        $donorName = $d['donor_name'] ? $d['donor_name'] : '未知';
        $messages[] = array(
            'type' => 'donation',
            'icon' => '💰',
            'title' => '新捐款记录',
            'content' => '捐款人：' . $donorName . "\n" . '金额：¥' . number_format($d['amount'], 2),
            'time' => $d['created_at'],
            'link' => '#records'
        );
    }

    // 3. Query new expense records after lastCheck - 使用 trans_type 字段
    $stmt = $pdo->prepare('
        SELECT *
        FROM wallet_transactions
        WHERE trans_type = \'expense\' AND created_at > ?
        ORDER BY created_at DESC
        LIMIT 50
    ');
    $stmt->execute(array($lastCheck));
    $newExpenses = $stmt->fetchAll();

    foreach ($newExpenses as $e) {
        $desc = $e['description'] ? $e['description'] : '无描述';
        $messages[] = array(
            'type' => 'expense',
            'icon' => '💸',
            'title' => '新支出记录',
            'content' => '金额：¥' . number_format(abs($e['amount']), 2) . "\n" . '用途：' . $desc,
            'time' => $e['created_at'],
            'link' => '#expense-records'
        );
    }

    // 4. Query new transfer records after lastCheck - 使用 trans_type 字段
    $stmt = $pdo->prepare('
        SELECT *
        FROM wallet_transactions
        WHERE trans_type = \'transfer\' AND created_at > ?
        ORDER BY created_at DESC
        LIMIT 50
    ');
    $stmt->execute(array($lastCheck));
    $newTransfers = $stmt->fetchAll();

    foreach ($newTransfers as $t) {
        $related = $t['related_name'] ? $t['related_name'] : '未知';
        $messages[] = array(
            'type' => 'transfer',
            'icon' => '🔄',
            'title' => '新转账记录',
            'content' => '转账给：' . $related . "\n" . '金额：¥' . number_format(abs($t['amount']), 2),
            'time' => $t['created_at'],
            'link' => '#wallet'
        );
    }

    // Sort by time (newest first)
    usort($messages, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });

    echo json_encode(array('success' => true, 'data' => $messages));
    exit;

} elseif ($action === 'mark_read') {
    // Mark as read
    logDebug("mark_read: updating last_notification_check for user $currentUserId");
    $stmt = $pdo->prepare('UPDATE users SET last_notification_check = NOW() WHERE id = ?');
    $stmt->execute(array($currentUserId));
    $affectedRows = $stmt->rowCount();
    logDebug("mark_read: updated $affectedRows rows");
    echo json_encode(array('success' => true, 'msg' => 'Marked as read', 'affected_rows' => $affectedRows));
    exit;

} else {
    echo json_encode(array('success' => false, 'msg' => 'Unknown action'));
    exit;
}
