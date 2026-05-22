<?php
require_once __DIR__ . '/../config.php';
requireLogin();

$action = '';
if (isset($_GET['action'])) $action = $_GET['action'];
if ($action === '' && isset($_POST['action'])) $action = $_POST['action'];

$pdo = getDB();
$myId = CURRENT_USER_ID();
$myRole = CURRENT_ROLE();

// 获取用户列表（用于AA用户勾选和执行人下拉）
if ($action === 'get_users') {
    $stmt = $pdo->query('SELECT id, IFNULL(brother_name,username) AS display_name FROM users ORDER BY id');
    jsonResponse(array('success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)));
    exit;
}

try {
    if ($action === 'list') {
        $userMap = array();
        $s = $pdo->query('SELECT id, IFNULL(brother_name,username) d FROM users');
        foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $userMap[$r['id']] = $r['d'];
        }

        $sql = 'SELECT e.*,u.username creator_name FROM family_expenses e LEFT JOIN users u ON e.created_by=u.id ORDER BY e.expense_date DESC,e.created_at DESC';
        $list = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $sumDaily = 0;
        $sumYesterday = 0;
        $sumMonth = 0;
        $sumLastMonth = 0;
        $sumPending = 0;
        $sumTotal = 0;
        $td = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $tm = date('Y-m');
        $lastMonth = date('Y-m', strtotime('first day of last month'));

        foreach ($list as &$it) {
            $pu = $it['paid_users'] ? json_decode($it['paid_users'], true) : array();
            $it['paid_users'] = $pu;
            $it['paid_names'] = array();
            foreach ($pu as $x) {
                $it['paid_names'][] = isset($userMap[$x]) ? $userMap[$x] : '用户' . $x;
            }

            $rs = $pdo->prepare('SELECT id,file_path,file_name FROM expense_receipts WHERE expense_id=?');
            $rs->execute(array($it['id']));
            $it['receipts'] = $rs->fetchAll(PDO::FETCH_ASSOC);

            $curAmount = floatval($it['amount']);
            if ($it['expense_date'] === $td) $sumDaily += $curAmount;
            if ($it['expense_date'] === $yesterday) $sumYesterday += $curAmount;
            if (substr($it['expense_date'], 0, 7) === $tm) $sumMonth += $curAmount;
            if (substr($it['expense_date'], 0, 7) === $lastMonth) $sumLastMonth += $curAmount;
            $sumTotal += $curAmount;

            // 解析 aa_users（参与AA的用户ID列表）
            $it['aa_users'] = $it['aa_users'] ? json_decode($it['aa_users'], true) : array();

            $aaAmount   = floatval($it['aa_amount']);
            $paidAmount = count($pu) * $aaAmount; // 已到款
            $unpaidAmt  = max(0, $curAmount - $paidAmount); // 未到款

            // 未结清汇总 = 所有支出的未到款之和
            $sumPending += $unpaidAmt;
        }

        jsonResponse(array(
            'success' => true,
            'data' => $list,
            'stats' => array(
                'daily'      => $sumDaily,
                'yesterday'  => $sumYesterday,
                'month'      => $sumMonth,
                'lastMonth'  => $sumLastMonth,
                'pending'    => $sumPending,
                'total'      => $sumTotal,
            )
        ));
        exit;
    }

    elseif ($action === 'create') {
        $purpose = trim($_POST['purpose'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $aaUsers = isset($_POST['aa_users']) ? json_decode($_POST['aa_users'], true) : array();

        if (empty($purpose) || empty($amount)) {
            jsonResponse(array('success' => false, 'error' => '用途和金额不能为空'));
        }
        if (empty($aaUsers) || !is_array($aaUsers)) {
            jsonResponse(array('success' => false, 'error' => '请选择AA用户'));
        }

        $aaCount = count($aaUsers);
        $aa = round(floatval($amount) / $aaCount, 2);
        $executorId = isset($_POST['executor_id']) ? intval($_POST['executor_id']) : 0;
        // 根据 executor_id 查用户名
        $executorName = null;
        if ($executorId > 0) {
            $es = $pdo->prepare('SELECT IFNULL(brother_name,username) d FROM users WHERE id=?');
            $es->execute(array($executorId));
            $er = $es->fetch(PDO::FETCH_ASSOC);
            if ($er) $executorName = $er['d'];
        }

        $stmt = $pdo->prepare('INSERT INTO family_expenses (purpose,amount,aa_amount,aa_users,executor,expense_date,remark,created_by) VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute(array(
            $purpose,
            $amount,
            $aa,
            json_encode($aaUsers),
            $executorName,
            isset($_POST['expense_date']) ? $_POST['expense_date'] : null,
            isset($_POST['remark']) ? $_POST['remark'] : null,
            $myId
        ));

        $eid = $pdo->lastInsertId();

        if (!empty($_FILES['receipts']['name'][0])) {
            foreach ($_FILES['receipts']['name'] as $i => $nm) {
                if ($_FILES['receipts']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = pathinfo($nm, PATHINFO_EXTENSION);
                $sname = 'expense_' . $eid . '_' . time() . '_' . $i . '.' . $ext;
                move_uploaded_file($_FILES['receipts']['tmp_name'][$i], UPLOAD_DIR . $sname);
                $r = $pdo->prepare('INSERT INTO expense_receipts (expense_id,file_path,file_name) VALUES (?,?,?)');
                $r->execute(array($eid, UPLOAD_URL . $sname, $nm));
            }
        }

        logAction($myId, '新增家庭支出', '用途：' . $purpose);
        jsonResponse(array('success' => true, 'msg' => '支出已记录'));
        exit;
    }

    elseif ($action === 'update') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(array('success' => false, 'error' => '参数错误'));

        $stmt = $pdo->prepare('SELECT created_by FROM family_expenses WHERE id=?');
        $stmt->execute(array($id));
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exp) jsonResponse(array('success' => false, 'error' => '记录不存在'));
        if ($myRole !== 'admin' && intval($exp['created_by']) !== $myId) {
            jsonResponse(array('success' => false, 'error' => '无权限修改'));
        }

        $purpose = trim($_POST['purpose'] ?? '');
        $amount = $_POST['amount'] ?? '';
        $aaUsers = isset($_POST['aa_users']) ? json_decode($_POST['aa_users'], true) : array();
        if (empty($purpose) || empty($amount)) {
            jsonResponse(array('success' => false, 'error' => '参数错误'));
        }
        if (empty($aaUsers) || !is_array($aaUsers)) {
            jsonResponse(array('success' => false, 'error' => '请选择AA用户'));
        }

        $aaCount = count($aaUsers);
        $aa = round(floatval($amount) / $aaCount, 2);
        $executorId = isset($_POST['executor_id']) ? intval($_POST['executor_id']) : 0;
        $executorName = null;
        if ($executorId > 0) {
            $es = $pdo->prepare('SELECT IFNULL(brother_name,username) d FROM users WHERE id=?');
            $es->execute(array($executorId));
            $er = $es->fetch(PDO::FETCH_ASSOC);
            if ($er) $executorName = $er['d'];
        }

        $stmt2 = $pdo->prepare('UPDATE family_expenses SET purpose=?,amount=?,aa_amount=?,aa_users=?,executor=?,expense_date=?,remark=?,updated_at=NOW() WHERE id=?');
        $stmt2->execute(array(
            $purpose,
            $amount,
            $aa,
            json_encode($aaUsers),
            $executorName,
            isset($_POST['expense_date']) ? $_POST['expense_date'] : null,
            isset($_POST['remark']) ? $_POST['remark'] : null,
            $id
        ));

        if (!empty($_FILES['receipts']['name'][0])) {
            foreach ($_FILES['receipts']['name'] as $i => $nm) {
                if ($_FILES['receipts']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $ext = pathinfo($nm, PATHINFO_EXTENSION);
                $sname = 'expense_' . $id . '_' . time() . '_' . $i . '.' . $ext;
                move_uploaded_file($_FILES['receipts']['tmp_name'][$i], UPLOAD_DIR . $sname);
                $r = $pdo->prepare('INSERT INTO expense_receipts (expense_id,file_path,file_name) VALUES (?,?,?)');
                $r->execute(array($id, UPLOAD_URL . $sname, $nm));
            }
        }

        logAction($myId, '修改家庭支出', 'ID：' . $id);
        jsonResponse(array('success' => true, 'msg' => '修改成功'));
        exit;
    }

    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(array('success' => false, 'error' => '参数错误'));

        $stmt = $pdo->prepare('SELECT created_by FROM family_expenses WHERE id=?');
        $stmt->execute(array($id));
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exp) jsonResponse(array('success' => false, 'error' => '记录不存在'));
        if ($myRole !== 'admin' && intval($exp['created_by']) !== $myId) {
            jsonResponse(array('success' => false, 'error' => '无权限删除'));
        }

        $rStmt = $pdo->prepare('SELECT file_path FROM expense_receipts WHERE expense_id=?');
        $rStmt->execute(array($id));
        foreach ($rStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $lp = str_replace(UPLOAD_URL, UPLOAD_DIR, $r['file_path']);
            if (file_exists($lp)) @unlink($lp);
        }
        $pdo->prepare('DELETE FROM expense_receipts WHERE expense_id=?')->execute(array($id));
        $pdo->prepare('DELETE FROM family_expenses WHERE id=?')->execute(array($id));

        logAction($myId, '删除家庭支出', 'ID：' . $id);
        jsonResponse(array('success' => true, 'msg' => '删除成功'));
        exit;
    }

    elseif ($action === 'toggle_paid') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) jsonResponse(array('success' => false, 'error' => '参数错误'));

        $stmt = $pdo->prepare('SELECT paid_users, aa_users FROM family_expenses WHERE id=?');
        $stmt->execute(array($id));
        $exp = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$exp) jsonResponse(array('success' => false, 'error' => '记录不存在'));

        // 检查当前用户是否在 AA 用户列表中
        $aaUsers = $exp['aa_users'] ? json_decode($exp['aa_users'], true) : array();
        if (!in_array($myId, $aaUsers)) {
            jsonResponse(array('success' => false, 'error' => '您不在本次AA列表中，无权操作'));
        }

        $plist = $exp['paid_users'] ? json_decode($exp['paid_users'], true) : array();
        $idx = array_search($myId, $plist);
        if ($idx !== false) {
            array_splice($plist, $idx, 1);
        } else {
            $plist[] = $myId;
        }

        $stmt2 = $pdo->prepare('UPDATE family_expenses SET paid_users=? WHERE id=?');
        $stmt2->execute(array(json_encode(array_values($plist)), $id));

        jsonResponse(array('success' => true, 'msg' => '操作成功'));
        exit;
    }

    jsonResponse(array('success' => false, 'error' => '未知操作'));

} catch (PDOException $e) {
    error_log('[expenses] ' . $e->getMessage());
    jsonResponse(array('success' => false, 'error' => '数据库错误'));
} catch (Exception $e) {
    error_log('[expenses] ' . $e->getMessage());
    jsonResponse(array('success' => false, 'error' => '操作出错'));
}
