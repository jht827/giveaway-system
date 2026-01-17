<?php
require 'auth_check.php';

$uid = $_SESSION['uid'];
$user_group = $_SESSION['group'];

// Fetch all events
// Only show events where is_hidden is 0
$stmt = $pdo->query("SELECT * FROM events WHERE is_hidden = 0 ORDER BY eid ASC");
$events = $stmt->fetchAll();

// Error Mapping
$error_map = [
    'O01' => '您已经预约过此项目了。',
    'O03' => '您的等级不符合该项目的预约要求。',
    'O04' => '份额已领完，下次早点来吧。'
];
$display_err = isset($_GET['err']) ? ($error_map[$_GET['err']] ?? '未知错误') : '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>用户主页 - 旧一代无料发放登记系统</title>
    <style>
        body { font-family: "SimSun", "宋体", serif; background: #f0f0f0; padding: 20px; }
        .container { background: white; border: 2px solid #333; padding: 20px; max-width: 1000px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background: #eee; }
        .error-banner { background: #fff2f0; border: 1px solid #ffccc7; color: #ff4d4f; padding: 10px; margin-bottom: 15px; font-weight: bold; }
        .nav { margin-bottom: 15px; border-bottom: 1px dashed #ccc; padding-bottom: 10px; }
        .status-ready { color: green; }
        .status-done { color: blue; font-weight: bold; }
        .status-none { color: red; }
    </style>
</head>
<body>

<div class="container">
    <div class="nav">
        用户: <b><?php echo htmlspecialchars($uid); ?></b> [<?php echo strtoupper($user_group); ?>] | 
        <a href="address.php">管理地址</a> | 
        <a href="my_orders.php">我的预约</a> | 
        <a href="logout.php">登出</a>
    </div>

    <?php if ($display_err): ?>
        <div class="error-banner"><?php echo $display_err; ?></div>
    <?php endif; ?>

    <h3>现在正在预约的分发活动</h3>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>内容</th>
                <th>剩余</th>
                <th>截止</th>
                <th>状态</th>
                <th>预计发出</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($events as $e): ?>
                <?php 
                    $stock = $e['total'] - $e['used'];
                    $allowed = explode(',', $e['allow_group']);
                    $is_eligible = in_array($user_group, $allowed);

                    // Check if this specific user already ordered this event
                    $check = $pdo->prepare("SELECT oid FROM orders WHERE uid = ? AND eid = ?");
                    $check->execute([$uid, $e['eid']]);
                    $already_ordered = $check->fetch();
                ?>
                <tr>
                    <td><?php echo $e['eid']; ?></td>
                    <td><?php echo htmlspecialchars($e['name']); ?></td>
                    <td><?php echo $stock; ?></td>
                    <td><?php echo $e['due_date']; ?></td>
                    <td>
                        <?php 
                        if ($already_ordered) {
                            echo "<span class='status-done'>已预约</span>";
                        } elseif ($stock <= 0) {
                            echo "<span class='status-none'>已领完</span>";
                        } elseif (!$is_eligible) {
                            echo "<span style='color:gray'>等级不足</span>";
                        } else {
                            echo "<span class='status-ready'>可预约</span>";
                        }
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($e['send_date']); ?></td>
                    <td>
                        <?php if (!$already_ordered && $is_eligible && $stock > 0): ?>
                            <a href="/events/<?php echo $e['eid']; ?>.html">详情</a>
                        <?php else: ?>
                            --
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>
