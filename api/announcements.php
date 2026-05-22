<?php
/**
 * Announcements API
 * Admin: create/list/delete announcements
 * All users: get active announcements
 */

require_once __DIR__ . '/../config.php';
$pdo = getDB();

// Check login
if (empty($_SESSION['user']['id'])) {
    echo json_encode(array('success' => false, 'msg' => 'Please login first'));
    exit;
}

$currentRole = $_SESSION['user']['role'];
$currentUserId = CURRENT_USER_ID();
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if ($action === 'list') {
    // Get announcement list (admin: all; user: only active)
    if ($currentRole === 'admin') {
        $stmt = $pdo->query('SELECT * FROM announcements ORDER BY created_at DESC');
    } else {
        $stmt = $pdo->query('SELECT * FROM announcements WHERE is_active = 1 ORDER BY created_at DESC');
    }
    $list = $stmt->fetchAll();
    echo json_encode(array('success' => true, 'data' => $list));
    exit;

} elseif ($action === 'create') {
    // Only admin can create
    if ($currentRole !== 'admin') {
        echo json_encode(array('success' => false, 'msg' => 'Permission denied'));
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $title = trim($input['title'] ? $input['title'] : '');
    $content = trim($input['content'] ? $input['content'] : '');
    if (!$title || !$content) {
        echo json_encode(array('success' => false, 'msg' => 'Title and content required'));
        exit;
    }
    $stmt = $pdo->prepare('INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)');
    $stmt->execute(array($title, $content, $currentUserId));
    echo json_encode(array('success' => true, 'msg' => 'Announcement created'));
    exit;

} elseif ($action === 'delete') {
    // Only admin can delete
    if ($currentRole !== 'admin') {
        echo json_encode(array('success' => false, 'msg' => 'Permission denied'));
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id']);
    if (!$id) {
        echo json_encode(array('success' => false, 'msg' => 'Invalid ID'));
        exit;
    }
    $stmt = $pdo->prepare('DELETE FROM announcements WHERE id = ?');
    $stmt->execute(array($id));
    echo json_encode(array('success' => true, 'msg' => 'Announcement deleted'));
    exit;

} elseif ($action === 'toggle') {
    // Only admin can toggle active status
    if ($currentRole !== 'admin') {
        echo json_encode(array('success' => false, 'msg' => 'Permission denied'));
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = intval($input['id']);
    $isActive = intval($input['is_active']);
    if (!$id) {
        echo json_encode(array('success' => false, 'msg' => 'Invalid ID'));
        exit;
    }
    $stmt = $pdo->prepare('UPDATE announcements SET is_active = ? WHERE id = ?');
    $stmt->execute(array($isActive, $id));
    echo json_encode(array('success' => true, 'msg' => 'Status updated'));
    exit;

} else {
    echo json_encode(array('success' => false, 'msg' => 'Unknown action'));
    exit;
}
