<?php
/**
 * 批量导入捐款 CSV
 * POST (FormData) file=CSV文件
 * 权限：仅管理员可导入
 */
require_once __DIR__ . '/../config.php';
requireAdmin();   // ← 仅管理员可导入
$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file']['tmp_name'])) {
    jsonResponse(['success' => false, 'msg' => '请上传CSV文件']);
}

$file = $_FILES['file']['tmp_name'];
$handle = fopen($file, 'r');
if (!$handle) {
    jsonResponse(['success' => false, 'msg' => '文件读取失败']);
}

// 跳过 BOM + 表头
fgets($handle); // 表头行
$imported = 0;
$failed   = 0;

$pdo->beginTransaction();
try {
    while (($row = fgetcsv($handle)) !== false) {
        // 期望列：捐款人, 金额, 捐款日期, 来源, 支付方式, 备注
        if (count($row) < 2) { $failed++; continue; }
        $donor_name    = trim($row[0] ?? '');
        $amount         = floatval($row[1] ?? 0);
        $donate_date    = trim($row[2] ?? date('Y-m-d'));
        $source         = trim($row[3] ?? '兄弟募集');
        $payment_method = trim($row[4] ?? '微信');
        $note           = trim($row[5] ?? '');

        if (!$donor_name || $amount <= 0) { $failed++; continue; }

        // 默认归入当前管理员（可扩展为指定 brother_id）
        $brother_id = CURRENT_USER_ID();

        $stmt = $pdo->prepare(
            'INSERT INTO donations
             (brother_id, donor_name, amount, donate_date, source, payment_method, note, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$brother_id, $donor_name, $amount, $donate_date, $source, $payment_method, $note, $brother_id]);

        // 自动入账到管理员的钱包
        $adminName = CURRENT_BROTHER_NAME();
        $w = $pdo->prepare('SELECT person_key FROM wallets WHERE person_name = ?');
        $w->execute([$adminName]);
        $wallet = $w->fetch();
        if ($wallet) {
            $pdo->prepare('UPDATE wallets SET balance = balance + ? WHERE person_key = ?')
                 ->execute([$amount, $wallet['person_key']]);
            $pdo->prepare(
                'INSERT INTO wallet_transactions
                 (person_key, person_name, trans_type, amount, description, created_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$wallet['person_key'], $adminName, 'receive', $amount, "批量导入：$donor_name", $brother_id]);
        }

        $imported++;
    }
    fclose($handle);
    $pdo->commit();
    logOperation('批量导入', "成功导入 $imported 条，失败 $failed 条");
    jsonResponse(['success' => true, 'msg' => "导入完成！成功 $imported 条，失败 $failed 条"]);
} catch (Exception $e) {
    $pdo->rollBack();
    jsonResponse(['success' => false, 'msg' => '导入失败：' . $e->getMessage()]);
}
