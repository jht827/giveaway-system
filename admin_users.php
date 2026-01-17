<?php
require 'db.php';
session_start();

// SIMPLE ADMIN SECURITY: Only 'owner' group can access
if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied: Only the owner can manage users.");
}

$msg = "";

// 1. Handle Status Toggles
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $target_uid = $_GET['uid'];
    $action = $_GET['action'];

    if ($action == 'verify') {
        $stmt = $pdo->prepare("UPDATE users SET verified = 1 WHERE uid = ?");
        $stmt->execute([$target_uid]);
        $msg = "用户 $target_uid 已激活！";
    } elseif ($action == 'unverify') {
        $stmt = $pdo->prepare("UPDATE users SET verified = 0 WHERE uid = ?");
        $stmt->execute([$target_uid]);
    } elseif ($action == 'disable') {
        $stmt = $pdo->prepare("UPDATE users SET disabled = 1 WHERE uid = ?");
        $stmt->execute([$target_uid]);
    } elseif ($action == 'enable') {
        $stmt = $pdo->prepare("UPDATE users SET disabled = 0 WHERE uid = ?");
        $stmt->execute([$target_uid]);
    } elseif ($action == 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        $pdo->beginTransaction();
        try {
            $stmt_orders = $pdo->prepare("DELETE FROM orders WHERE uid = ?");
            $stmt_orders->execute([$target_uid]);

            $stmt_user = $pdo->prepare("DELETE FROM users WHERE uid = ?");
            $stmt_user->execute([$target_uid]);

            $pdo->commit();
            $msg = "用户 $target_uid 及其所有订单已删除。";
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

// 2. Fetch all users
$stmt = $pdo->query("SELECT uid, qq, user_group, verified, disabled FROM users ORDER BY verified ASC, uid ASC");
$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理后台 - 用户管理</title>
    <style>
        body { font-family: "SimSun", serif; background: #333; color: #eee; padding: 20px; }
        .admin-box { background: #444; border: 2px solid #000; padding: 20px; max-width: 900px; margin: 0 auto; box-shadow: 10px 10px 0px #222; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #555; }
        th, td { border: 1px solid #222; padding: 10px; text-align: left; }
        th { background: #222; color: #0f0; }
        .btn { padding: 4px 8px; text-decoration: none; font-size: 0.85em; border: 1px solid #000; }
        .btn-green { background: #28a745; color: white; }
        .btn-red { background: #dc3545; color: white; }
        .btn-gray { background: #6c757d; color: white; }
        .status-badge { font-weight: bold; padding: 2px 5px; }
    </style>
    <script>
        function confirmDeleteUser(uid) {
            if (!confirm('即将删除该用户及其所有订单，是否继续？')) {
                return false;
            }
            if (!confirm('此操作不可撤销，确认删除用户 ' + uid + '？')) {
                return false;
            }
            window.location.href = '?action=delete&confirm=1&uid=' + encodeURIComponent(uid);
            return false;
        }
    </script>
</head>
<body>

<div class="admin-box">
    <h2>[ 管理后台 ] 用户激活与权限控制</h2>
    <p>欢迎，<?php echo $_SESSION['uid']; ?>。在这里你可以批准新用户的注册申请。</p>
    
    <?php if($msg) echo "<p style='color:#0f0;'>$msg</p>"; ?>

    <table>
        <thead>
            <tr>
                <th>UID (用户名)</th>
                <th><?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?>号</th>
                <th>用户组</th>
                <th>激活状态</th>
                <th>封禁状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?php echo htmlspecialchars($u['uid']); ?></td>
                <td><?php echo htmlspecialchars($u['qq']); ?></td>
                <td><?php echo strtoupper($u['user_group']); ?></td>
                <td>
                    <?php if($u['verified']): ?>
                        <span class="status-badge" style="color:#0f0;">√ 已激活</span>
                    <?php else: ?>
                        <span class="status-badge" style="color:#ffc107;">? 待审核</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($u['disabled']): ?>
                        <span class="status-badge" style="color:#f00;">× 已封禁</span>
                    <?php else: ?>
                        <span style="color:#aaa;">正常</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if(!$u['verified']): ?>
                        <a href="?action=verify&uid=<?php echo $u['uid']; ?>" class="btn btn-green">批准</a>
                    <?php else: ?>
                        <a href="?action=unverify&uid=<?php echo $u['uid']; ?>" class="btn btn-gray">撤销</a>
                    <?php endif; ?>

                    <?php if(!$u['disabled']): ?>
                        <a href="?action=disable&uid=<?php echo $u['uid']; ?>" class="btn btn-red">封禁</a>
                    <?php else: ?>
                        <a href="?action=enable&uid=<?php echo $u['uid']; ?>" class="btn btn-green">解封</a>
                    <?php endif; ?>

                    <a href="#" class="btn btn-red" onclick="return confirmDeleteUser('<?php echo htmlspecialchars($u['uid'], ENT_QUOTES, 'UTF-8'); ?>');">删除</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top:20px;">
        <a href="admin.php" style="color:#0f0;"><- 返回后台</a>
    </div>
</div>

</body>
</html>
