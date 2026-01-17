<?php
require 'db.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied.");
}

// Quick Stats
$user_count = $pdo->query("SELECT COUNT(*) FROM users WHERE verified = 0")->fetchColumn();
$order_count = $pdo->query("SELECT COUNT(*) FROM orders WHERE state = 0")->fetchColumn();
$event_count = $pdo->query("SELECT COUNT(*) FROM events WHERE is_hidden = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理总线 - Jerry Dist Sys</title>
    <style>
        body { font-family: "SimSun", serif; background: #222; color: #0f0; padding: 40px; }
        .hub { border: 2px solid #0f0; padding: 30px; max-width: 600px; margin: 0 auto; box-shadow: 10px 10px 0px #000; }
        .menu-item { display: block; padding: 15px; border: 1px solid #444; margin: 10px 0; text-decoration: none; color: #0f0; }
        .menu-item:hover { background: #333; border-color: #0f0; }
        .stat { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="hub">
        <h2>[ 杰瑞小吃管理总线 v1.0 ]</h2>
        <hr border="1">
        <a href="admin_users.php" class="menu-item">
            用户审核管理 (Users) <span class="stat">[ <?php echo $user_count; ?> 待审核 ]</span>
        </a>
        <a href="admin_events.php" class="menu-item">
            活动发布中心 (Events) <span class="stat">[ <?php echo $event_count; ?> 进行中 ]</span>
        </a>
        <a href="admin_orders.php" class="menu-item">
            订单处理终端 (Orders) <span class="stat">[ <?php echo $order_count; ?> 待发货 ]</span>
        </a>
        <br>
        <a href="home.php" style="color:#aaa;"><- 返回前台首页</a>
    </div>
</body>
</html>