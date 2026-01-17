<?php
require 'db.php';
session_start();
require 'csrf.php';

if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied.");
}

$msg = "";
$import_summary = "";
$import_errors = [];

function parse_import_state($value) {
    $normalized = strtolower(trim((string)$value));
    if ($normalized === '0' || $normalized === 'unsent') {
        return 0;
    }
    if ($normalized === '1' || $normalized === 'sent') {
        return 1;
    }
    if ($normalized === '2' || $normalized === 'arrived') {
        return 2;
    }
    return null;
}

// 1. Handle POST Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_require();

    // Manual Payment Confirmation (X -> R)
    if (isset($_POST['confirm_pay']) && isset($_POST['oid'])) {
        $oid = $_POST['oid'];
        $stmt = $pdo->prepare("UPDATE orders SET xa = 'R' WHERE oid = ? AND xa = 'X'");
        $stmt->execute([$oid]);
        $msg = "订单 $oid 已确认支付，转为挂号。";
    }

    // Update Order Logistics (Standard Update)
    if (isset($_POST['update_logistics'])) {
        $oid = $_POST['oid'];
        $l_no = trim($_POST['logistics_no']);
        $state = (int)$_POST['state'];

        $stmt = $pdo->prepare("UPDATE orders SET logistics_no = ?, state = ? WHERE oid = ?");
        $stmt->execute([$l_no, $state, $oid]);
        $msg = "订单 $oid 状态已更新。";
    }

    // Batch Import (CSV)
    if (isset($_POST['import_csv'])) {
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            $import_errors[] = "上传失败，请检查文件。";
        } else {
            $handle = fopen($_FILES['import_file']['tmp_name'], 'r');
            if (!$handle) {
                $import_errors[] = "无法读取上传文件。";
            } else {
                $header = fgetcsv($handle);
                $expected = ['o1', 'l1', 's1'];
                $normalized_header = [];
                foreach ($header ?: [] as $index => $item) {
                    $normalized = strtolower(trim((string)$item));
                    if ($index === 0) {
                        $normalized = preg_replace('/^\xEF\xBB\xBF/', '', $normalized);
                    }
                    $normalized_header[] = $normalized;
                }

                if ($normalized_header !== $expected) {
                    $import_errors[] = "CSV 表头必须为 o1,l1,s1。";
                } else {
                    $updated = 0;
                    $conflicts = 0;
                    $line = 1;
                    while (($row = fgetcsv($handle)) !== false) {
                        $line++;
                        $oid = trim($row[0] ?? '');
                        $l_no = trim($row[1] ?? '');
                        $state_raw = $row[2] ?? '';
                        $state = parse_import_state($state_raw);

                        if ($oid === '' || $l_no === '' || $state === null) {
                            $conflicts++;
                            $import_errors[] = "第 {$line} 行数据不完整或状态无效。";
                            continue;
                        }

                        $stmt = $pdo->prepare("SELECT logistics_no, state FROM orders WHERE oid = ?");
                        $stmt->execute([$oid]);
                        $current = $stmt->fetch();
                        if (!$current) {
                            $conflicts++;
                            $import_errors[] = "第 {$line} 行订单号 {$oid} 不存在。";
                            continue;
                        }

                        $existing_no = trim((string)$current['logistics_no']);
                        if ($existing_no !== '' && $existing_no !== $l_no) {
                            $conflicts++;
                            $import_errors[] = "第 {$line} 行订单 {$oid} 物流单号与当前记录冲突。";
                            continue;
                        }

                        $dup_stmt = $pdo->prepare("SELECT oid FROM orders WHERE logistics_no = ? AND oid <> ?");
                        $dup_stmt->execute([$l_no, $oid]);
                        if ($dup_stmt->fetch()) {
                            $conflicts++;
                            $import_errors[] = "第 {$line} 行物流单号 {$l_no} 已被其他订单使用。";
                            continue;
                        }

                        if ((int)$current['state'] > $state) {
                            $conflicts++;
                            $import_errors[] = "第 {$line} 行订单 {$oid} 状态回退冲突。";
                            continue;
                        }

                        $stmt = $pdo->prepare("UPDATE orders SET logistics_no = ?, state = ? WHERE oid = ?");
                        $stmt->execute([$l_no, $state, $oid]);
                        $updated++;
                    }
                    $import_summary = "批量导入完成：更新 {$updated} 条，冲突 {$conflicts} 条。";
                }
                fclose($handle);
            }
        }
    }
}

// 3. Fetch Orders
$eid_filter = $_GET['eid'] ?? '';
$sql = "SELECT o.*, u.qq, e.name as event_name, a.postcode, a.addr, a.phone, a.name as recipient_name
        FROM orders o 
        JOIN users u ON o.uid = u.uid 
        JOIN events e ON o.eid = e.eid 
        JOIN addresses a ON o.aid = a.aid";
if ($eid_filter) $sql .= " WHERE o.eid = " . $pdo->quote($eid_filter);
$sql .= " ORDER BY o.state ASC, o.oid DESC";
$orders = $pdo->query($sql)->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>订单处理 - <?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: "SimSun", serif; background: #333; color: #eee; font-size: 13px; }
        .container { background: #444; border: 2px solid #000; padding: 20px; max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #222; padding: 10px; text-align: left; }
        .address-box { background: #fff; color: #333; padding: 8px; font-size: 12px; }
        .pay-btn { background: #ffc107; color: #000; padding: 3px 6px; text-decoration: none; border-radius: 3px; font-weight: bold; }
        .btn-export { background: #28a745; color: white; padding: 8px 15px; text-decoration: none; display: inline-block; }
    </style>
</head>
<body>
    <div class="container">
        <h2>订单处理终端 (Manual Payment Logic)</h2>
        
        <form method="GET" style="margin-bottom:20px;">
            筛选EID: <input type="text" name="eid" value="<?php echo htmlspecialchars($eid_filter); ?>">
            <button type="submit">筛选</button>
            <?php if($eid_filter): ?>
                <a href="admin_export.php?eid=<?php echo urlencode($eid_filter); ?>" class="btn-export">导出表格 (CSV)</a>
            <?php endif; ?>
        </form>

        <?php if($msg) echo "<p style='color:#0f0;'>$msg</p>"; ?>
        <?php if($import_summary) echo "<p style='color:#0f0;'>" . htmlspecialchars($import_summary, ENT_QUOTES, 'UTF-8') . "</p>"; ?>
        <?php if($import_errors): ?>
            <div style="color:#f66; margin-bottom:15px;">
                <?php foreach ($import_errors as $error): ?>
                    <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="margin-bottom:20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <label>批量导入物流 CSV（表头：o1,l1,s1）:</label>
            <input type="file" name="import_file" accept=".csv" required>
            <button type="submit" name="import_csv">上传并更新</button>
            <div style="font-size:12px; color:#bbb; margin-top:6px;">
                s1 支持 0/1/2 或 unsent/sent/arrived（不允许状态回退）。
            </div>
        </form>

        <table>
            <thead>
                <tr>
                    <th>订单/<?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?></th>
                    <th>选项/费用</th>
                    <th>收货信息</th>
                    <th>物流更新</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $o): ?>
                <tr>
                    <td>
                        <b><?php echo htmlspecialchars($o['oid']); ?></b><br>
                        UID: <?php echo htmlspecialchars($o['uid']); ?><br>
                        <?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($o['qq']); ?>
                    </td>
                    <td>
                        选项: <?php echo $o['choice']; ?><br>
                        <?php 
                        if ($o['xa'] == 'X') {
                            echo '<span style="color:#ffc107;">[待付挂号费]</span><br>';
                            echo '<form method="POST" style="display:inline;">'
                                . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">'
                                . '<input type="hidden" name="oid" value="' . htmlspecialchars($o['oid']) . '">'
                                . '<input type="hidden" name="confirm_pay" value="1">'
                                . '<button type="submit" class="pay-btn" onclick="return confirm(\'确定已收到该用户的3元支付吗？\')">确认收款</button>'
                                . '</form>';
                        } elseif ($o['xa'] == 'R') {
                            echo '<span style="color:#0f0;">[已付挂号]</span>';
                        } else {
                            echo '平邮';
                        }
                        ?>
                    </td>
                    <td>
                        <div class="address-box">
                            <b><?php echo htmlspecialchars($o['recipient_name']); ?></b> (<?php echo htmlspecialchars($o['phone']); ?>)<br>
                            [<?php echo htmlspecialchars($o['postcode']); ?>] <?php echo htmlspecialchars($o['addr']); ?>
                        </div>
                    </td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="oid" value="<?php echo htmlspecialchars($o['oid']); ?>">
                            单号: <input type="text" name="logistics_no" value="<?php echo htmlspecialchars($o['logistics_no']); ?>" style="width:120px;"><br>
                            状态: <select name="state" style="margin-top:5px;">
                                <option value="0" <?php if($o['state']==0) echo 'selected'; ?>>待发货</option>
                                <option value="1" <?php if($o['state']==1) echo 'selected'; ?>>已发出</option>
                                <option value="2" <?php if($o['state']==2) echo 'selected'; ?>>已送达</option>
                            </select>
                            <button type="submit" name="update_logistics" style="margin-top:5px;">更新</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:20px;">
            <a href="admin.php" style="color:#0f0;"><- 返回后台</a>
        </div>
    </div>
</body>
</html>
