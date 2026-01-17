<?php
require 'db.php'; // Connect to the database

$error_msg = "";
$success_msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid = trim($_POST['uid']);
    $pwd = $_POST['pwd'];
    $socialId = trim($_POST['qq']);

    // --- 1. Validate UID (ERR L01: only letters and numbers) ---
    if (!preg_match('/^[a-zA-Z0-9]+$/', $uid)) {
        $error_msg = "ERR L01: 用户名只能由字母和数字组成";
    } 
    // --- 2. Validate Password (ERR L02: length >= 8) ---
    elseif (strlen($pwd) < 8) {
        $error_msg = "ERR L02: 密码长度必须至少为8位";
    }
    // --- 3. Validate social ID (ERR L04: numbers only when configured) ---
    elseif ($gsSocialIdNumericOnly && !ctype_digit($socialId)) {
        $error_msg = "ERR L04: {$gsSocialPlatform}号仅可由数字构成";
    }
    else {
        // --- 4. Check for duplicates (ERR L03 & L05) ---
        $stmt = $pdo->prepare("SELECT uid, qq FROM users WHERE uid = ? OR qq = ?");
        $stmt->execute([$uid, $socialId]);
        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['uid'] == $uid) $error_msg = "ERR L03: 用户名已存在";
            else $error_msg = "ERR L05: 本{$gsSocialPlatform}号已被用于注册";
        } else {
            // --- 5. Success: Hash password and Save ---
            $pwdhash = password_hash($pwd, PASSWORD_BCRYPT); // Using Bcrypt as planned
            
            try {
                $stmt = $pdo->prepare("INSERT INTO users (uid, pwdhash, qq, user_group, verified) VALUES (?, ?, ?, 'new', 0)");
                $stmt->execute([$uid, $pwdhash, $socialId]);
                $success_msg = "注册成功！您的账户目前尚未激活，请联系{$gsOwnerName}进行激活。";
            } catch (Exception $e) {
                $error_msg = "ERR L06: 数据库错误";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>注册 - <?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: "SimSun", "宋体", serif; background: #f0f0f0; padding: 20px; }
        .reg-box { background: white; border: 2px solid #333; padding: 20px; width: 350px; margin: 0 auto; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        input { width: 100%; margin: 10px 0; padding: 5px; box-sizing: border-box; }
        .note { font-size: 0.8em; color: #666; }
    </style>
</head>
<body>

<div class="reg-box">
    <h3><?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($gsSiteVersion, ENT_QUOTES, 'UTF-8'); ?></h3>
    <p><b>初次使用请注册</b></p>
    
    <?php if($error_msg) echo "<p class='error'>$error_msg</p>"; ?>
    <?php if($success_msg) echo "<p class='success'>$success_msg</p>"; ?>

    <form method="POST">
        用户名 (a-z, 0-9):
        <input type="text" name="uid" required>
        
        设置密码 (至少8位):
        <input type="password" name="pwd" required>
        
        <?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?>号:
        <input type="text" name="qq" required>
        
        <p class="note">您的密码将以 Bcrypt 哈希的形式存储，并不会存有密码本身。</p>
        
        <button type="submit" style="width:100%; padding: 10px; cursor: pointer;">注 册</button>
    </form>
    
    <hr>
    <a href="index.html">回到首页</a> | <a href="login.php">登 录</a>
</div>

</body>
</html>
