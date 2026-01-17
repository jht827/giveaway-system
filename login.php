<?php
require 'db.php';
session_start(); // Starts a session to keep the user logged in

$error_msg = "";

// If already logged in, skip to home
if (isset($_SESSION['uid'])) {
    header("Location: home.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $uid = trim($_POST['uid']);
    $pwd = $_POST['pwd'];

    // 1. Check if user exists (ERR L07)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE uid = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user) {
        $error_msg = "ERR L07: 用户不存在";
    } 
    // 2. Verify password (ERR L08)
    elseif (!password_verify($pwd, $user['pwdhash'])) {
        $error_msg = "ERR L08: 密码错误";
    }
    // 3. Check if activated (ERR L09)
    elseif ($user['verified'] == 0) {
        $error_msg = "ERR L09: 用户未激活 (请联系{$gsOwnerName})";
    }
    // 4. Check if disabled (ERR L10)
    elseif ($user['disabled'] == 1) {
        $error_msg = "ERR L10: 用户已被禁用";
    }
    else {
        // SUCCESS: Set session variables
        $_SESSION['uid'] = $user['uid'];
        $_SESSION['group'] = $user['user_group'];
        
        header("Location: home.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>登录 - 旧一代无料发放登记系统</title>
    <style>
        body { font-family: "SimSun", "宋体", serif; background: #f0f0f0; padding: 20px; }
        .login-box { background: white; border: 2px solid #333; padding: 20px; width: 350px; margin: 0 auto; }
        .error { color: red; font-weight: bold; }
        input { width: 100%; margin: 10px 0; padding: 5px; box-sizing: border-box; }
    </style>
</head>
<body>

<div class="login-box">
    <h3>登录系统</h3>
    
    <?php if($error_msg) echo "<p class='error'>$error_msg</p>"; ?>

    <form method="POST">
        用户名:
        <input type="text" name="uid" required>
        
        密码:
        <input type="password" name="pwd" required>
        
        <button type="submit" style="width:100%; padding: 10px; cursor: pointer;">登 录</button>
    </form>
    
    <hr>
    <p>未注册？ <a href="register.php">去注册</a></p>
</div>

</body>
</html>
