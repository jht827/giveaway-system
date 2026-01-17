<?php
require 'auth_check.php';

$uid = $_SESSION['uid'];

// Fetch all orders for this user, joining with event names
$stmt = $pdo->prepare("
    SELECT o.*, e.name as event_name 
    FROM orders o 
    JOIN events e ON o.eid = e.eid 
    WHERE o.uid = ? 
    ORDER BY o.oid DESC
");
$stmt->execute([$uid]);
$my_orders = $stmt->fetchAll();

// Mapping for state
$state_map = [
    0 => '<span style="color:gray;">未发出</span>',
    1 => '<span style="color:blue;">已发出</span>',
    2 => '<span style="color:green;">已送达</span>'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>我的预约记录 - 旧一代无料发放登记系统</title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; }
        .container { background: white; border: 2px solid #333; padding: 20px; max-width: 800px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav">
        <a href="home.php"><- 返回主页</a> | 登录身份: <?php echo htmlspecialchars($uid); ?>
    </div>

    <h3>我的预约历史</h3>

    <?php if (!$my_orders): ?>
        <p>您还没有任何预约记录。</p>
    <?php else: ?>
        <table>
            <tr>
                <th>预约号 (OID)</th>
                <th>活动内容</th>
                <th>类型</th>
                <th>当前状态</th>
                <th>操作</th>
            </tr>
            <?php foreach ($my_orders as $o): ?>
            <tr>
                <td><?php echo $o['oid']; ?></td>
                <td><?php echo htmlspecialchars($o['event_name']); ?></td>
                <td>选项 <?php echo $o['choice']; ?></td>
                <td><?php echo $state_map[$o['state']]; ?></td>
                <td><a href="orderstatus.php?id=<?php echo $o['oid']; ?>">查看详情/物流</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
