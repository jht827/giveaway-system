<?php
/**
 * admin_events.php
 * Create, modify, and TOGGLE VISIBILITY of giveaway activities.
 */

require 'db.php';
session_start();
require 'csrf.php';

// Admin Check
if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied.");
}

$msg = "";

function normalize_datetime_input($value) {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (strlen($value) === 16) {
        $value .= ':00';
    }
    return $value;
}

// 1. Handle POST Actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    csrf_require();

    if (isset($_POST['toggle_visibility'], $_POST['eid'])) {
        $eid = $_POST['eid'];
        // Logic: 1 means hidden, 0 means visible
        $new_status = ($_POST['toggle_visibility'] === 'hide') ? 1 : 0;
        
        // FIX: Changed 'is_deleted' to 'is_hidden' to match your database
        $stmt = $pdo->prepare("UPDATE events SET is_hidden = ? WHERE eid = ?");
        $stmt->execute([$new_status, $eid]);
        
        header("Location: admin_events.php");
        exit;
    }

    // 2. Handle New Event Creation
    if (isset($_POST['add_event'])) {
        $eid = trim($_POST['eid']);
        $name = trim($_POST['name']);
        $total = (int)$_POST['total'];
        $due_date = normalize_datetime_input($_POST['due_date'] ?? '');
        $start_at = normalize_datetime_input($_POST['start_at'] ?? '');
        $send_date = $_POST['send_date'];
        $send_way = $_POST['send_way']; 
        $allow_group = trim($_POST['allow_group']);
        $choice_amount = (int)$_POST['choice_amount'];
        $xa_allow = isset($_POST['xa_allow']) ? 1 : 0;
        $autogroup = 0;
        $is_hidden = isset($_POST['is_hidden_init']) ? 1 : 0;

        // FIX: Ensured the INSERT query uses 'is_hidden'
        $stmt = $pdo->prepare("INSERT INTO events (eid, name, total, used, due_date, start_at, send_date, send_way, allow_group, choice_amount, xa_allow, autogroup, is_hidden) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$eid, $name, $total, $due_date, $start_at, $send_date, $send_way, $allow_group, $choice_amount, $xa_allow, $autogroup, $is_hidden])) {
            $msg = "活动 $eid 创建成功！";
        }
    }
}

// 3. Fetch All Events
$stmt = $pdo->query("SELECT * FROM events ORDER BY eid DESC");
$events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理后台 - 活动管理</title>
    <style>
        body { font-family: "SimSun", serif; background: #333; color: #eee; padding: 20px; }
        .admin-box { background: #444; border: 2px solid #000; padding: 20px; max-width: 1150px; margin: 0 auto; box-shadow: 8px 8px 0px #222; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #555; font-size: 0.85em; }
        th, td { border: 1px solid #222; padding: 8px; text-align: left; }
        th { background: #222; color: #0f0; }
        .form-box { background: #222; padding: 15px; border: 1px solid #0f0; margin-bottom: 20px; }
        input, select { background: #333; color: #fff; border: 1px solid #555; padding: 5px; margin: 5px 0; }
        .help { color: #bbb; font-size: 0.85em; }
        .btn { padding: 4px 10px; cursor: pointer; border: 1px solid #000; text-decoration: none; display: inline-block; font-size: 0.9em; }
        .btn-add { background: #28a745; color: #fff; padding: 8px 20px; border: none; }
        .btn-hide { background: #6c757d; color: #fff; }
        .btn-show { background: #007bff; color: #fff; }
    </style>
</head>
<body>

<div class="admin-box">
    <h2>[ 活动发布与控制中心 ]</h2>
    
    <?php if($msg) echo "<p style='color:#0f0;'>$msg</p>"; ?>

    <div class="form-box">
        <h4>发布新无料/分发活动</h4>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <table style="background: transparent; border:none;">
                <tr>
                    <td>EID (4位数字): <br><input type="text" name="eid" placeholder="0001" required pattern="\d{4}"></td>
                    <td>活动名称: <br><input type="text" name="name" required style="width:200px;"></td>
                    <td>总量: <br><input type="number" name="total" value="50" required></td>
                </tr>
                <tr>
                    <td>截止时间: <br><input type="datetime-local" name="due_date" required><div class="help">按北京时间(UTC+8)填写</div></td>
                    <td>开放时间: <br><input type="datetime-local" name="start_at"><div class="help">不填=立即开放 / UTC+8</div></td>
                    <td>预计发出: <br><input type="text" name="send_date" placeholder="2026年2月"></td>
                </tr>
                <tr>
                    <td>发货方式: <br>
                        <select name="send_way">
                            <option value="post">中国邮政 (Post)</option>
                            <option value="express">快递 (Express)</option>
                        </select>
                    </td>
                    <td>允许组: <br><input type="text" name="allow_group" value="new,auto"></td>
                    <td>选项数: <br><input type="number" name="choice_amount" value="1"></td>
                    <td>
                        <label><input type="checkbox" name="xa_allow" checked> 允许挂号</label><br>
                        <label style="color:#ffc107;"><input type="checkbox" name="is_hidden_init"> 初始隐藏</label>
                    </td>
                </tr>
            </table>
            <button type="submit" name="add_event" class="btn btn-add">发布活动</button>
        </form>
    </div>

    <h3>现有活动列表</h3>
    <table>
        <thead>
            <tr>
                <th>EID</th>
                <th>名称</th>
                <th>剩余/总量</th>
                <th>开放时间</th>
                <th>截止时间</th>
                <th>状态</th>
                <th>可见性</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $e): ?>
            <tr>
                <td><b><?php echo $e['eid']; ?></b></td>
                <td><?php echo htmlspecialchars($e['name']); ?></td>
                <td><?php echo ($e['total'] - $e['used']); ?> / <?php echo $e['total']; ?></td>
                <td><?php echo $e['start_at'] ? htmlspecialchars($e['start_at']) : '--'; ?></td>
                <td><?php echo $e['due_date'] ? htmlspecialchars($e['due_date']) : '--'; ?></td>
                <td>
                    <?php 
                        $now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
                        $due_at = $e['due_date'] ? new DateTime($e['due_date'], new DateTimeZone('Asia/Shanghai')) : null;
                        $start_at = $e['start_at'] ? new DateTime($e['start_at'], new DateTimeZone('Asia/Shanghai')) : null;
                        if ($due_at && $now > $due_at) {
                            echo "<span style='color:#ff4d4f;'>已截止</span>";
                        } elseif ($start_at && $now < $start_at) {
                            echo "<span style='color:#ffc107;'>未开始</span>";
                        } else {
                            echo "<span style='color:#0f0;'>进行中</span>";
                        }
                    ?>
                </td>
                <td>
                    <?php if($e['is_hidden']): ?>
                        <span style="color:#aaa;">[ 已隐藏 ]</span>
                    <?php else: ?>
                        <span style="color:#007bff;">[ 公开 ]</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($e['is_hidden']): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="eid" value="<?php echo htmlspecialchars($e['eid']); ?>">
                            <button type="submit" name="toggle_visibility" value="show" class="btn btn-show">设为公开</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                            <input type="hidden" name="eid" value="<?php echo htmlspecialchars($e['eid']); ?>">
                            <button type="submit" name="toggle_visibility" value="hide" class="btn btn-hide">设为隐藏</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top:20px;">
        <a href="admin_users.php" style="color:#0f0;">用户管理</a> | 
        <a href="admin.php" style="color:#0f0;">返回后台</a>
    </div>
</div>

</body>
</html>
