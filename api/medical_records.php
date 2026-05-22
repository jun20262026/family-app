<?php
/**
 * 医院就诊记录 API
 * 功能：增删改查医院就诊记录（支持多文件）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
$pdo = getDB();

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

$currentUserName = '';
if (function_exists('CURRENT_BROTHER_NAME')) {
    $currentUserName = CURRENT_BROTHER_NAME();
}

// 处理多文件上传，返回路径数组和文件名数组
function handleMultiFileUpload($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || empty($_FILES[$fileInputName]['name'])) {
        return array('paths' => array(), 'names' => array());
    }

    $uploadDir = __DIR__ . '/../uploads/medical/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $paths = array();
    $names = array();

    // 兼容单文件和多文件
    $files = $_FILES[$fileInputName];
    if (!is_array($files['name'])) {
        $files = array(
            'name'     => array($files['name']),
            'type'     => array($files['type']),
            'tmp_name' => array($files['tmp_name']),
            'error'    => array($files['error']),
            'size'     => array($files['size']),
        );
    }

    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $ext = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
        $ext = $ext ? $ext : 'bin';
        $newFileName = uniqid('medical_') . '.' . $ext;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($files['tmp_name'][$i], $targetPath)) {
            $paths[] = 'uploads/medical/' . $newFileName;
            $names[]  = $files['name'][$i];
        }
    }

    return array('paths' => $paths, 'names' => $names);
}

// 合并已有文件和新上传的文件
function mergeFiles($existingPathsJson, $existingNamesJson, $newPaths, $newNames) {
    $paths = $existingPathsJson ? json_decode($existingPathsJson, true) : array();
    $names = $existingNamesJson ? json_decode($existingNamesJson, true) : array();
    if (!is_array($paths)) $paths = array();
    if (!is_array($names)) $names = array();

    return array(
        'paths' => array_merge($paths, $newPaths),
        'names' => array_merge($names, $newNames),
    );
}

// 删除指定索引的文件
function removeFileAt($existingPathsJson, $existingNamesJson, $index) {
    $paths = $existingPathsJson ? json_decode($existingPathsJson, true) : array();
    $names = $existingNamesJson ? json_decode($existingNamesJson, true) : array();
    if (!is_array($paths)) $paths = array();
    if (!is_array($names)) $names = array();

    // 删除服务器文件
    if (isset($paths[$index]) && $paths[$index]) {
        $fullPath = __DIR__ . '/../' . $paths[$index];
        if (file_exists($fullPath)) @unlink($fullPath);
    }

    array_splice($paths, $index, 1);
    array_splice($names, $index, 1);

    return array('paths' => $paths, 'names' => $names);
}

switch ($action) {
    case 'list':
        $page = intval(isset($_GET['page']) ? $_GET['page'] : 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = array();
        $params = array();

        if (!empty($_GET['patient_name'])) {
            $where[] = 'patient_name LIKE ?';
            $params[] = '%' . $_GET['patient_name'] . '%';
        }

        if (!empty($_GET['hospital'])) {
            $where[] = 'hospital LIKE ?';
            $params[] = '%' . $_GET['hospital'] . '%';
        }

        if (!empty($_GET['start_date'])) {
            $where[] = 'visit_date >= ?';
            $params[] = $_GET['start_date'];
        }

        if (!empty($_GET['end_date'])) {
            $where[] = 'visit_date <= ?';
            $params[] = $_GET['end_date'];
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM medical_records $whereSQL");
        $stmt->execute($params);
        $total = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT * FROM medical_records $whereSQL ORDER BY visit_date DESC, id DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset));
        $stmt->execute($params);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 将 file_path/file_name 解析为数组返回给前端
        foreach ($records as &$r) {
            $r['file_paths'] = $r['file_path'] ? json_decode($r['file_path'], true) : array();
            $r['file_names'] = $r['file_name'] ? json_decode($r['file_name'], true) : array();
            if (!is_array($r['file_paths'])) $r['file_paths'] = array();
            if (!is_array($r['file_names'])) $r['file_names'] = array();
        }

        echo json_encode(array(
            'success' => true,
            'data' => $records,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $limit)
        ));
        break;

    case 'add':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'msg' => 'Invalid request method'));
            exit;
        }

        $patientName = trim(isset($_POST['patient_name']) ? $_POST['patient_name'] : '');
        $hospital   = trim(isset($_POST['hospital']) ? $_POST['hospital'] : '');
        $visitDate  = isset($_POST['visit_date']) ? $_POST['visit_date'] : date('Y-m-d');
        $diagnosis  = trim(isset($_POST['diagnosis']) ? $_POST['diagnosis'] : '');
        $cost       = floatval(isset($_POST['cost']) ? $_POST['cost'] : 0);

        if (!$patientName || !$hospital || !$visitDate) {
            echo json_encode(array('success' => false, 'msg' => '请填写完整信息'));
            exit;
        }

        $fileInfo = handleMultiFileUpload('files');
        $filePathsJson = $fileInfo['paths'] ? json_encode($fileInfo['paths'], JSON_UNESCAPED_SLASHES) : null;
        $fileNamesJson = $fileInfo['names'] ? json_encode($fileInfo['names'], JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare('INSERT INTO medical_records (patient_name, hospital, visit_date, diagnosis, cost, file_path, file_name, record_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute(array($patientName, $hospital, $visitDate, $diagnosis, $cost, $filePathsJson, $fileNamesJson, $currentUserName));

        echo json_encode(array('success' => true, 'msg' => '添加成功'));
        break;

    case 'update':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'msg' => 'Invalid request method'));
            exit;
        }

        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) {
            echo json_encode(array('success' => false, 'msg' => 'ID 错误'));
            exit;
        }

        $patientName = trim(isset($_POST['patient_name']) ? $_POST['patient_name'] : '');
        $hospital   = trim(isset($_POST['hospital']) ? $_POST['hospital'] : '');
        $visitDate  = isset($_POST['visit_date']) ? $_POST['visit_date'] : date('Y-m-d');
        $diagnosis  = trim(isset($_POST['diagnosis']) ? $_POST['diagnosis'] : '');
        $cost       = floatval(isset($_POST['cost']) ? $_POST['cost'] : 0);

        if (!$patientName || !$hospital || !$visitDate) {
            echo json_encode(array('success' => false, 'msg' => '请填写完整信息'));
            exit;
        }

        // 获取已有文件
        $stmt = $pdo->prepare('SELECT file_path, file_name FROM medical_records WHERE id = ?');
        $stmt->execute(array($id));
        $existing = $stmt->fetch();

        // 处理新上传的文件
        $fileInfo = handleMultiFileUpload('files');
        $merged = mergeFiles($existing['file_path'], $existing['file_name'], $fileInfo['paths'], $fileInfo['names']);

        $filePathsJson = $merged['paths'] ? json_encode(array_values($merged['paths']), JSON_UNESCAPED_SLASHES) : null;
        $fileNamesJson = $merged['names'] ? json_encode(array_values($merged['names']), JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare('UPDATE medical_records SET patient_name=?, hospital=?, visit_date=?, diagnosis=?, cost=?, file_path=?, file_name=? WHERE id=?');
        $stmt->execute(array($patientName, $hospital, $visitDate, $diagnosis, $cost, $filePathsJson, $fileNamesJson, $id));

        echo json_encode(array('success' => true, 'msg' => '更新成功'));
        break;

    case 'delete_file':
        // 删除某条记录中的某个文件
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'msg' => 'Invalid request method'));
            exit;
        }

        $id    = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        $index = intval(isset($_POST['index']) ? $_POST['index'] : -1);
        if (!$id || $index < 0) {
            echo json_encode(array('success' => false, 'msg' => '参数错误'));
            exit;
        }

        $stmt = $pdo->prepare('SELECT file_path, file_name FROM medical_records WHERE id = ?');
        $stmt->execute(array($id));
        $record = $stmt->fetch();
        if (!$record) {
            echo json_encode(array('success' => false, 'msg' => '记录不存在'));
            exit;
        }

        $result = removeFileAt($record['file_path'], $record['file_name'], $index);
        $pathsJson = $result['paths'] ? json_encode(array_values($result['paths']), JSON_UNESCAPED_SLASHES) : null;
        $namesJson = $result['names'] ? json_encode(array_values($result['names']), JSON_UNESCAPED_SLASHES) : null;

        $stmt = $pdo->prepare('UPDATE medical_records SET file_path=?, file_name=? WHERE id=?');
        $stmt->execute(array($pathsJson, $namesJson, $id));

        echo json_encode(array('success' => true, 'msg' => '文件删除成功'));
        break;

    case 'delete':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(array('success' => false, 'msg' => 'Invalid request method'));
            exit;
        }

        $id = intval(isset($_POST['id']) ? $_POST['id'] : 0);
        if (!$id) {
            echo json_encode(array('success' => false, 'msg' => 'ID 错误'));
            exit;
        }

        $stmt = $pdo->prepare('SELECT file_path FROM medical_records WHERE id = ?');
        $stmt->execute(array($id));
        $record = $stmt->fetch();

        // 删除所有关联文件
        if ($record && $record['file_path']) {
            $paths = json_decode($record['file_path'], true);
            if (is_array($paths)) {
                foreach ($paths as $p) {
                    $fullPath = __DIR__ . '/../' . $p;
                    if (file_exists($fullPath)) @unlink($fullPath);
                }
            }
        }

        $stmt = $pdo->prepare('DELETE FROM medical_records WHERE id = ?');
        $stmt->execute(array($id));

        echo json_encode(array('success' => true, 'msg' => '删除成功'));
        break;

    case 'download':
        $id    = intval(isset($_GET['id']) ? $_GET['id'] : 0);
        $index = intval(isset($_GET['index']) ? $_GET['index'] : 0);
        if (!$id) {
            die('ID 错误');
        }

        $stmt = $pdo->prepare('SELECT file_path, file_name FROM medical_records WHERE id = ?');
        $stmt->execute(array($id));
        $record = $stmt->fetch();

        if (!$record || !$record['file_path']) {
            die('文件不存在');
        }

        $paths = json_decode($record['file_path'], true);
        $names = json_decode($record['file_name'], true);
        if (!is_array($paths) || !isset($paths[$index])) {
            die('文件不存在');
        }

        $fullPath = __DIR__ . '/../' . $paths[$index];
        $fileName = isset($names[$index]) ? $names[$index] : basename($paths[$index]);
        if (!file_exists($fullPath)) {
            die('文件不存在');
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . filesize($fullPath));
        readfile($fullPath);
        exit;
        break;

    default:
        echo json_encode(array('success' => false, 'msg' => '未知操作'));
}
