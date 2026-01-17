<?php
require 'auth_check.php';
$uid = $_SESSION['uid'];
$msg = "";
$msg_type = "success"; // Default color

// 1. Catch messages from order.php (e.g., "请先添加一个地址")
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = "error"; 
}

// 2. Handle New Address Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_addr'])) {
    $name = trim($_POST['name']); // Added: Recipient Name
    $postcode = trim($_POST['postcode']);
    $addr = trim($_POST['addr']);
    $phone = trim($_POST['phone']);
    $intl = isset($_POST['intl']) ? 1 : 0;
    $is_default = isset($_POST['is_default']) ? 1 : 0;

    // If setting a new default, unset the old one first
    if ($is_default) {
        $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE uid = ?")->execute([$uid]);
    }

    // Updated: Included 'name' column in the INSERT statement
    $stmt = $pdo->prepare("INSERT INTO addresses (uid, name, postcode, addr, phone, is_intl, is_default, is_deleted) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->execute([$uid, $name, $postcode, $addr, $phone, $intl, $is_default]);
    $msg = "新地址已添加！";
    $msg_type = "success";
}

// 3. Handle Setting Default
if (isset($_GET['set_default'])) {
    $aid = $_GET['set_default'];
    $pdo->prepare("UPDATE addresses SET is_default = 0 WHERE uid = ?")->execute([$uid]);
    $pdo->prepare("UPDATE addresses SET is_default = 1 WHERE uid = ? AND aid = ?")->execute([$uid, $aid]);
    header("Location: address.php"); exit;
}

// 4. Handle SOFT DELETE (Mark as is_deleted = 1)
if (isset($_GET['delete'])) {
    $aid = $_GET['delete'];
    $stmt = $pdo->prepare("UPDATE addresses SET is_deleted = 1 WHERE uid = ? AND aid = ?");
    $stmt->execute([$uid, $aid]);
    header("Location: address.php"); exit;
}

// 5. Fetch all active addresses
$stmt = $pdo->prepare("SELECT * FROM addresses WHERE uid = ? AND is_deleted = 0 ORDER BY is_default DESC, aid DESC");
$stmt->execute([$uid]);
$my_addresses = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>地址管理 - 旧一代无料发放登记系统</title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; }
        .box { background: white; border: 2px solid #333; padding: 20px; max-width: 600px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 0.9em; }
        th, td { border: 1px solid #ccc; padding: 10px; text-align: left; }
        .msg-banner { padding: 10px; margin-bottom: 15px; border: 1px solid #333; font-weight: bold; }
        .error { background: #fff2f0; color: #ff4d4f; border-color: #ffccc7; }
        .success { background: #f6ffed; color: #52c41a; border-color: #b7eb8f; }
        .default-badge { background: #333; color: white; padding: 2px 5px; font-size: 0.8em; margin-right: 5px; }
        input[type="text"], textarea { width: 100%; margin: 8px 0; padding: 8px; box-sizing: border-box; border: 1px solid #333; }
    </style>
</head>
<body>

<div class="box">
    <div style="margin-bottom: 10px;"><a href="home.php"><- 返回主页</a></div>
    <h3>我的地址库</h3>

    <?php if($msg): ?>
        <div class="msg-banner <?php echo $msg_type; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>
    
    <table>
        <thead><tr><th>地址详情</th><th>管理操作</th></tr></thead>
        <tbody>
        <?php foreach($my_addresses as $a): ?>
        <tr>
            <td>
                <?php if($a['is_default']) echo "<span class='default-badge'>默认</span>"; ?>
                <b><?php echo htmlspecialchars($a['name']); ?></b> (<?php echo htmlspecialchars($a['phone']); ?>)<br>
                <small>[<?php echo htmlspecialchars($a['postcode']); ?>]</small> <?php echo htmlspecialchars($a['addr']); ?>
                <?php if($a['is_intl']) echo " <small>(国际)</small>"; ?>
            </td>
            <td>
                <?php if(!$a['is_default']): ?>
                    <a href="?set_default=<?php echo $a['aid']; ?>">设为默认</a><br>
                <?php endif; ?>
                <a href="?delete=<?php echo $a['aid']; ?>" style="color:red;" onclick="return confirm('确定删除该地址吗？这不会影响已生成的预约。')">删除</a>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if(!$my_addresses) echo "<tr><td colspan='2'>暂无保存的地址。</td></tr>"; ?>
        </tbody>
    </table>

    <hr style="margin: 20px 0; border: 1px dashed #ccc;">
    
    <h4>添加新地址</h4>
    <form method="POST">
        收件人姓名 (n1):
        <input type="text" name="name" required placeholder="请不要填写的太离谱">
        
        邮政编码 (z1):
        <input type="text" name="postcode" required>
        
        详细地址 (a1):
        <textarea name="addr" rows="3" required placeholder="省/市/区/街道门牌号"></textarea>
        
        联系电话 (p1):
        <input type="text" name="phone" required>
        
        <p>
            <label><input type="checkbox" name="intl"> 国际地址 (International)</label> | 
            <label><input type="checkbox" name="is_default" checked> 设为默认</label>
        </p>
        
        <button type="submit" name="save_addr" style="width:100%; padding: 12px; background: #333; color: white; border: none; cursor: pointer;">保存地址</button>
    </form>
</div>

</body>
</html>
