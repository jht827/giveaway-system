<?php
require 'auth_check.php';
$eid = $_GET['id'] ?? '';
$uid = $_SESSION['uid'];

$stmt = $pdo->prepare("SELECT * FROM events WHERE eid = ?");
$stmt->execute([$eid]);
$event = $stmt->fetch();

// Get user's addresses
$addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE uid = ? ORDER BY is_default DESC");
$addr_stmt->execute([$uid]);
$addresses = $addr_stmt->fetchAll();

if (!$event) die("活动不存在");
if (!$addresses) {
    header("Location: address.php?msg=请先添加一个地址"); exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>预约确认 旧一代无料分发登记系统</title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; }
        .box { background: white; border: 2px solid #333; padding: 20px; width: 450px; margin: 0 auto; }
        select, button { width: 100%; padding: 10px; margin-top: 10px; }
    </style>
</head>
<body>
<div class="box">
    <h2>预约: <?php echo htmlspecialchars($event['name']); ?></h2>
    <form action="submit_order.php" method="POST">
        <input type="hidden" name="eid" value="<?php echo $event['eid']; ?>">
        
        <p>选择收货地址:</p>
        <select name="aid" required>
            <?php foreach($addresses as $a): ?>
                <option value="<?php echo $a['aid']; ?>">
                    <?php echo ($a['is_default'] ? "[默认] " : "") . $a['postcode'] . " - " . mb_substr($a['addr'], 0, 15) . "..."; ?>
                </option>
            <?php endforeach; ?>
        </select>

        <p>选择类型:</p>
        <select name="choice">
            <?php for($i=1; $i<=$event['choice_amount']; $i++) echo "<option value='$i'>选项 $i</option>"; ?>
        </select>

        <?php if($event['xa_allow']): ?>
            <p><label><input type="checkbox" name="xa" value="R"> 加挂号 (+3元)</label></p>
        <?php endif; ?>

        <button type="submit">确认预约</button>
    </form>
</div>
</body>
</html>