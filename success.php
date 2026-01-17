<?php
require 'auth_check.php';
$oid = $_GET['id'] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>预约成功</title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; text-align: center; }
        .success-box { background: white; border: 2px solid #333; padding: 30px; width: 500px; margin: 0 auto; }
        .oid-display { font-size: 1.5em; color: blue; margin: 20px 0; }
    </style>
</head>
<body>

<div class="success-box">
    <h1 style="color: green;">已成功预约！</h1>
    <p>您的预约号是：</p>
    <div class="oid-display"><?php echo htmlspecialchars($oid); ?></div>
    
    <p style="text-align: left; background: #fffbe6; padding: 10px; border: 1px solid #ffe58f;">
        <b>注意：</b><br>
        如果您选择了加挂号发送，请通过 <?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?> 向<?php echo htmlspecialchars($gsOwnerName, ENT_QUOTES, 'UTF-8'); ?>支付 3 元。<br>
        付款时请备注您的预约号，并在付款后通知<?php echo htmlspecialchars($gsOwnerName, ENT_QUOTES, 'UTF-8'); ?>更新状态。
    </p>

    <div style="margin-top: 30px;">
        <a href="my_orders.php">我的预约</a> | 
        <a href="home.php">回到主页</a>
    </div>
</div>

</body>
</html>
