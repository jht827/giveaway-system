<?php
require 'auth_check.php';
$eid = $_GET['id'] ?? '';
$uid = $_SESSION['uid'];

$stmt = $pdo->prepare("SELECT * FROM events WHERE eid = ?");
$stmt->execute([$eid]);
$event = $stmt->fetch();

if (!$event) {
    die("活动不存在");
}

$start_at_dt = $event['start_at'] ? new DateTime($event['start_at'], new DateTimeZone('Asia/Shanghai')) : null;
$now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
if ($start_at_dt && $now < $start_at_dt) {
    $start_at_display = $start_at_dt->format('Y-m-d H:i');
    $start_at_iso = $start_at_dt->format('Y-m-d\\TH:i:sP');
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <title>未到时间 - <?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?></title>
        <style>
            body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; }
            .box { background: white; border: 2px solid #333; padding: 20px; width: 450px; margin: 0 auto; }
            .hint { color: #ff4d4f; font-weight: bold; }
        </style>
    </head>
    <body>
    <div class="box">
        <h2 class="hint">还没到时间</h2>
        <p>活动：<?php echo htmlspecialchars($event['name']); ?></p>
        <p>开放时间：<span class="start-time" data-start-at-iso="<?php echo htmlspecialchars($start_at_iso); ?>"><?php echo htmlspecialchars($start_at_display); ?></span></p>
        <p><a href="home.php">返回主页</a></p>
    </div>
    <script>
        const node = document.querySelector('.start-time');
        if (node && node.dataset.startAtIso) {
            const utc8Offset = -480;
            const localOffset = new Date().getTimezoneOffset();
            if (localOffset !== utc8Offset) {
                const localDate = new Date(node.dataset.startAtIso);
                if (!Number.isNaN(localDate.getTime())) {
                    const pad = (value) => String(value).padStart(2, '0');
                    node.textContent = `${localDate.getFullYear()}-${pad(localDate.getMonth() + 1)}-${pad(localDate.getDate())} ${pad(localDate.getHours())}:${pad(localDate.getMinutes())}`;
                }
            }
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}

// Get user's addresses
$addr_stmt = $pdo->prepare("SELECT * FROM addresses WHERE uid = ? ORDER BY is_default DESC");
$addr_stmt->execute([$uid]);
$addresses = $addr_stmt->fetchAll();

if (!$addresses) {
    header("Location: address.php?msg=请先添加一个地址"); exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>预约确认 - <?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?></title>
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
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
        <input type="hidden" name="eid" value="<?php echo $event['eid']; ?>">
        
        <p>选择收货地址:</p>
        <select name="aid" required>
            <?php foreach($addresses as $a): ?>
                <option value="<?php echo $a['aid']; ?>">
                    <?php echo htmlspecialchars(($a['is_default'] ? "[默认] " : "") . $a['postcode'] . " - " . mb_substr($a['addr'], 0, 15) . "..."); ?>
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
