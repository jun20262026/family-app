<?php
/**
 * 管理员数据操作 API
 * POST actions: clear_data（清空数据）, backup（备份）, restore（还原）, reset_wallets（钱包清零）
 */
require_once __DIR__ . '/../config.php';
requireLogin();
requireAdmin();

$pdo = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $_POST['action'] ?? $input['action'] ?? '';
$password = $_POST['password'] ?? $input['password'] ?? '';

// 验证管理员密码
function verifyAdminPassword($pdo, $password) {
    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([CURRENT_USER_ID()]);
    $user = $stmt->fetch();
    if (!$user) return false;
    return password_verify($password, $user['password']);
}

// 数据清零（需要密码）
if ($action === 'reset_wallets') {
    if (empty($password)) jsonResponse(['success' => false, 'msg' => '请输入管理员密码']);
    if (!verifyAdminPassword($pdo, $password)) jsonResponse(['success' => false, 'msg' => '管理员密码错误']);

    $pdo->beginTransaction();
    try {
        // 清零所有钱包余额
        $pdo->exec('UPDATE wallets SET balance = 0.00');
        // 清空交易记录
        $pdo->exec('TRUNCATE TABLE wallet_transactions');
        // 记录日志
        logAction(CURRENT_USER_ID(), 'reset_wallets', '所有钱包余额已清零');
        $pdo->commit();
        jsonResponse(['success' => true, 'msg' => '所有钱包余额已清零，交易记录已清空']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

// 清空所有数据（需要密码）
if ($action === 'clear_data') {
    if (empty($password)) jsonResponse(['success' => false, 'msg' => '请输入管理员密码']);
    if (!verifyAdminPassword($pdo, $password)) jsonResponse(['success' => false, 'msg' => '管理员密码错误']);

    $pdo->beginTransaction();
    try {
        $pdo->exec('TRUNCATE TABLE donations');
        $pdo->exec('TRUNCATE TABLE wallet_transactions');
        $pdo->exec('UPDATE wallets SET balance = 0.00');
        $pdo->exec('TRUNCATE TABLE audit_log');
        logAction(CURRENT_USER_ID(), 'clear_data', '所有业务数据已清空');
        $pdo->commit();
        jsonResponse(['success' => true, 'msg' => '所有数据已清空（用户账号保留）']);
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(['success' => false, 'msg' => '操作失败：' . $e->getMessage()]);
    }
}

// 备份数据库
if ($action === 'backup') {
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

    $filename = 'backup_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    // 获取数据库配置
    $dbHost = $GLOBALS['DB_HOST'] ?? 'localhost';
    $dbName = $GLOBALS['DB_NAME'] ?? 'fundraising';
    $dbUser = $GLOBALS['DB_USER'] ?? 'root';
    $dbPass = $GLOBALS['DB_PASS'] ?? '';

    // 使用 mysqldump 备份
    $command = sprintf('mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($filepath)
    );

    // Windows 下处理密码参数
    if (DIRECTORY_SEPARATOR === '\\') {
        $command = sprintf('mysqldump --host=%s --user=%s --password=%s %s > "%s" 2>nul',
            $dbHost,
            $dbUser,
            $dbPass,
            $dbName,
            str_replace('/', '\\', $filepath)
        );
    }

    exec($command, $output, $ret);
    // Windows 下 mysqldump 返回值可能非0但文件正常创建，以文件存在为准
    if (!file_exists($filepath) || filesize($filepath) < 10) {
        // 备用方案：用 PHP 导出
        $sql = "-- Fundraising System Backup\n";
        $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

        $tables = ['users', 'donations', 'wallets', 'wallet_transactions', 'operation_log', 'login_log', 'audit_log'];
        foreach ($tables as $table) {
            $sql .= "\n-- Table: $table --\n";
            // 表结构
            $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            if ($create) {
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create['Create Table'] . ";\n\n";
            }
            // 表数据
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
            if (count($rows) > 0) {
                $sql .= "INSERT INTO `$table` VALUES\n";
                $lines = [];
                foreach ($rows as $row) {
                    $vals = array_map(function($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote($v);
                    }, array_values($row));
                    $lines[] = '(' . implode(', ', $vals) . ')';
                }
                $sql .= implode(",\n", $lines) . ";\n\n";
            }
        }
        file_put_contents($filepath, $sql);
    }

    logAction(CURRENT_USER_ID(), 'backup', '数据库备份：' . $filename);
    jsonResponse(['success' => true, 'msg' => '备份成功', 'filename' => $filename, 'download_url' => '/backups/' . $filename]);
}

// 获取备份列表
if ($action === 'list_backups') {
    $backupDir = __DIR__ . '/../backups';
    $files = [];
    if (is_dir($backupDir)) {
        foreach (scandir($backupDir) as $f) {
            if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
                $path = $backupDir . '/' . $f;
                $files[] = [
                    'name' => $f,
                    'size' => round(filesize($path) / 1024, 1) . ' KB',
                    'time' => date('Y-m-d H:i:s', filemtime($path)),
                    'url'  => '/backups/' . $f
                ];
            }
        }
    }
    // 按时间倒序
    usort($files, fn($a, $b) => $b['time'] <=> $a['time']);
    jsonResponse(['success' => true, 'data' => $files]);
}

// 还原数据库（需要密码）
if ($action === 'restore') {
    if (empty($password)) jsonResponse(['success' => false, 'msg' => '请输入管理员密码']);
    if (!verifyAdminPassword($pdo, $password)) jsonResponse(['success' => false, 'msg' => '管理员密码错误']);

    $filename = $_POST['filename'] ?? $input['filename'] ?? '';
    if (empty($filename)) jsonResponse(['success' => false, 'msg' => '请选择备份文件']);

    $backupDir = __DIR__ . '/../backups';
    $filepath = $backupDir . '/' . basename($filename); // basename 防路径穿越
    if (!file_exists($filepath)) jsonResponse(['success' => false, 'msg' => '备份文件不存在']);

    // 执行还原
    $dbHost = $GLOBALS['DB_HOST'] ?? 'localhost';
    $dbName = $GLOBALS['DB_NAME'] ?? 'fundraising';
    $dbUser = $GLOBALS['DB_USER'] ?? 'root';
    $dbPass = $GLOBALS['DB_PASS'] ?? '';

    $command = sprintf('mysql --host=%s --user=%s --password=%s %s < %s',
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        escapeshellarg($dbPass),
        escapeshellarg($dbName),
        escapeshellarg($filepath)
    );

    if (DIRECTORY_SEPARATOR === '\\') {
        $command = sprintf('mysql --host=%s --user=%s --password=%s %s < "%s" 2>nul',
            $dbHost, $dbUser, $dbPass, $dbName,
            str_replace('/', '\\', $filepath)
        );
    }

    exec($command, $output, $ret);
    if ($ret !== 0) {
        // 备用方案：用 PHP 执行 SQL
        $sql = file_get_contents($filepath);
        $pdo->beginTransaction();
        try {
            // 禁用外键检查
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            // 按语句执行（简单拆分）
            $sql = preg_replace('/^--.*$/m', '', $sql);
            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (strlen($stmt) > 5) $pdo->exec($stmt);
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['success' => false, 'msg' => '还原失败：' . $e->getMessage()]);
        }
    }

    logAction(CURRENT_USER_ID(), 'restore', '数据库还原：' . $filename);
    jsonResponse(['success' => true, 'msg' => '数据库还原成功，页面将刷新']);
}

jsonResponse(['success' => false, 'msg' => '未知操作']);
