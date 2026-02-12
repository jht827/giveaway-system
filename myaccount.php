<?php
require 'auth_check.php';

$uid = $_SESSION['uid'];
$messages = [];
$errors = [];
$max_redeem_fail = 5;

/**
 * Normalize user input to canonical redeem format.
 * - Remove spaces
 * - Convert 10 digits to NNN-NNNNNNN
 */
function normalize_redeem_code(string $raw): string
{
    $clean = preg_replace('/\s+/', '', strtoupper(trim($raw)));

    if (preg_match('/^\d{10}$/', $clean)) {
        return substr($clean, 0, 3) . '-' . substr($clean, 3);
    }

    return $clean;
}

/**
 * Validate redeem code structure and checksum constraints.
 */
function is_valid_windows95_style_code(string $code): bool
{
    if (!preg_match('/^(\d{3})-(\d{7})$/', $code, $matches)) {
        return false;
    }

    $prefix = (int)$matches[1];
    $suffix = (int)$matches[2];
    $blocked_prefixes = [333, 444, 555, 666, 777, 888, 999];

    if ($prefix % 7 !== 0 || in_array($prefix, $blocked_prefixes, true)) {
        return false;
    }

    if ((int)$matches[2][0] === 0) {
        return false;
    }

    return $suffix % 7 === 0;
}

/**
 * Read only the minimal account status needed for redeem flow.
 */
function get_user_redeem_status(PDO $pdo, string $uid): ?array
{
    $stmt = $pdo->prepare('SELECT user_group, redeem_fail_count, redeem_locked_until FROM users WHERE uid = ?');
    $stmt->execute([$uid]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Clear stale lock when lock duration has elapsed.
 */
function clear_expired_redeem_lock(PDO $pdo, string $uid, array &$user_status, DateTime $now): void
{
    if (empty($user_status['redeem_locked_until'])) {
        return;
    }

    $locked_until = new DateTime($user_status['redeem_locked_until'], new DateTimeZone('Asia/Shanghai'));
    if ($locked_until <= $now) {
        $stmt = $pdo->prepare('UPDATE users SET redeem_fail_count = 0, redeem_locked_until = NULL WHERE uid = ?');
        $stmt->execute([$uid]);
        $user_status['redeem_fail_count'] = 0;
        $user_status['redeem_locked_until'] = null;
    }
}

/**
 * Return lock message when redeem is currently disabled.
 */
function get_redeem_lock_error(array $user_status, DateTime $now): ?string
{
    if (empty($user_status['redeem_locked_until'])) {
        return null;
    }

    $locked_until = new DateTime($user_status['redeem_locked_until'], new DateTimeZone('Asia/Shanghai'));
    if ($locked_until > $now) {
        return '兑换功能已被临时锁定，请在 ' . $locked_until->format('Y-m-d H:i:s') . ' 后重试。';
    }

    return null;
}

/**
 * Check whether code record can be redeemed by this user.
 */
function validate_code_row_for_user(?array $code_row, string $uid): array
{
    if (!$code_row) {
        return [false, '兑换码不存在。'];
    }

    if ((int)$code_row['is_used'] === 1) {
        return [false, '兑换码已被使用。'];
    }

    if ($code_row['code_type'] === 'bound' && $code_row['bound_uid'] !== $uid) {
        return [false, '该兑换码仅限指定用户使用。'];
    }

    return [true, ''];
}

/**
 * Persist successful redemption and session group refresh.
 */
function complete_redeem_success(PDO $pdo, array $code_row, string $uid): string
{
    $target_group = $code_row['target_group'] ?: 'auto';

    $set_group_stmt = $pdo->prepare(
        'UPDATE users SET user_group = ?, redeem_fail_count = 0, redeem_locked_until = NULL WHERE uid = ?'
    );
    $set_group_stmt->execute([$target_group, $uid]);

    $mark_code_stmt = $pdo->prepare('UPDATE redeem_codes SET is_used = 1, redeemed_by = ?, redeemed_at = NOW() WHERE id = ?');
    $mark_code_stmt->execute([$uid, $code_row['id']]);

    $_SESSION['group'] = $target_group;
    return '兑换成功，您的用户组已更新为 ' . strtoupper($target_group) . '。';
}

/**
 * Persist failed attempts and lock user for 2 hours when threshold is reached.
 */
function handle_redeem_failure(PDO $pdo, string $uid, int $current_fail_count, int $max_redeem_fail, string $fail_reason): string
{
    $next_fail = $current_fail_count + 1;

    if ($next_fail >= $max_redeem_fail) {
        $lock_stmt = $pdo->prepare(
            'UPDATE users SET redeem_fail_count = 0, redeem_locked_until = DATE_ADD(NOW(), INTERVAL 2 HOUR) WHERE uid = ?'
        );
        $lock_stmt->execute([$uid]);
        return $fail_reason . ' 失败次数过多，兑换功能已锁定2小时。';
    }

    $inc_stmt = $pdo->prepare('UPDATE users SET redeem_fail_count = redeem_fail_count + 1 WHERE uid = ?');
    $inc_stmt->execute([$uid]);
    $remaining = $max_redeem_fail - $next_fail;

    return $fail_reason . ' 再失败 ' . $remaining . ' 次将锁定2小时。';
}

/**
 * Handle password update action.
 */
function process_change_password(PDO $pdo, string $uid, array &$messages, array &$errors): void
{
    $current_pwd = $_POST['current_pwd'] ?? '';
    $new_pwd = $_POST['new_pwd'] ?? '';
    $confirm_new_pwd = $_POST['confirm_new_pwd'] ?? '';

    $stmt = $pdo->prepare('SELECT pwdhash FROM users WHERE uid = ?');
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($current_pwd, $user['pwdhash'])) {
        $errors[] = '当前密码不正确。';
        return;
    }

    if (strlen($new_pwd) < 8) {
        $errors[] = '新密码长度必须至少为8位。';
        return;
    }

    if ($new_pwd !== $confirm_new_pwd) {
        $errors[] = '两次输入的新密码不一致。';
        return;
    }

    $new_hash = password_hash($new_pwd, PASSWORD_BCRYPT);
    $update = $pdo->prepare('UPDATE users SET pwdhash = ? WHERE uid = ?');
    $update->execute([$new_hash, $uid]);
    $messages[] = '密码修改成功。';
}

/**
 * Handle redeem action with lock checks and transaction.
 */
function process_redeem_code(PDO $pdo, string $uid, int $max_redeem_fail, array &$messages, array &$errors): void
{
    $code = normalize_redeem_code($_POST['redeem_code'] ?? '');

    // Fast input-level validations before hitting DB transaction.
    if ($code === '') {
        $errors[] = '兑换码不能为空。';
        return;
    }

    if ($code === '111-1111111') {
        $messages[] = '你以为是windows95安装呢';
        return;
    }

    if (!is_valid_windows95_style_code($code)) {
        $errors[] = '兑换码格式错误，请按页面给定格式输入。';
        return;
    }

    $user_status = get_user_redeem_status($pdo, $uid);
    if (!$user_status) {
        $errors[] = '账户状态异常，请重新登录。';
        return;
    }

    $now = new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    clear_expired_redeem_lock($pdo, $uid, $user_status, $now);

    $lock_error = get_redeem_lock_error($user_status, $now);
    if ($lock_error !== null) {
        $errors[] = $lock_error;
        return;
    }

    // Transaction: lock code row, verify state, then apply success/failure updates.
    $pdo->beginTransaction();

    try {
        $code_stmt = $pdo->prepare('SELECT * FROM redeem_codes WHERE code = ? FOR UPDATE');
        $code_stmt->execute([$code]);
        $code_row = $code_stmt->fetch(PDO::FETCH_ASSOC);

        [$can_redeem, $fail_reason] = validate_code_row_for_user($code_row, $uid);

        if ($can_redeem) {
            $messages[] = complete_redeem_success($pdo, $code_row, $uid);
            $pdo->commit();
            return;
        }

        $errors[] = handle_redeem_failure(
            $pdo,
            $uid,
            (int)$user_status['redeem_fail_count'],
            $max_redeem_fail,
            $fail_reason
        );

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = '兑换处理失败，请稍后重试。';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require();

    $action = $_POST['action'];
    if ($action === 'change_password') {
        process_change_password($pdo, $uid, $messages, $errors);
    } elseif ($action === 'redeem_code') {
        process_redeem_code($pdo, $uid, $max_redeem_fail, $messages, $errors);
    }
}

$profile_stmt = $pdo->prepare('SELECT uid, qq, user_group, redeem_fail_count, redeem_locked_until FROM users WHERE uid = ?');
$profile_stmt->execute([$uid]);
$profile = $profile_stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>账户中心 - <?php echo htmlspecialchars($gsSiteName, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; }
        .container { background: white; border: 2px solid #333; padding: 20px; max-width: 900px; margin: 0 auto; }
        .row { display: flex; gap: 20px; flex-wrap: wrap; }
        .panel { border: 1px solid #333; padding: 15px; flex: 1; min-width: 280px; }
        .panel h3 { margin-top: 0; }
        .msg { color: green; font-weight: bold; }
        .err { color: red; font-weight: bold; }
        input, select, button { width: 100%; box-sizing: border-box; margin: 8px 0; padding: 8px; }
        .nav { margin-bottom: 15px; border-bottom: 1px dashed #ccc; padding-bottom: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="nav">
        <a href="home.php"><- 返回主页</a> | <a href="my_orders.php">我的预约</a> | 当前用户: <?php echo htmlspecialchars($uid, ENT_QUOTES, 'UTF-8'); ?>
    </div>

    <h2>账户中心</h2>

    <?php foreach ($messages as $m): ?>
        <p class="msg"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endforeach; ?>

    <?php foreach ($errors as $e): ?>
        <p class="err"><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endforeach; ?>

    <div class="panel" style="margin-bottom:20px;">
        <h3>账户信息</h3>
        <p>用户名: <b><?php echo htmlspecialchars($profile['uid'] ?? $uid, ENT_QUOTES, 'UTF-8'); ?></b></p>
        <p><?php echo htmlspecialchars($gsSocialPlatform, ENT_QUOTES, 'UTF-8'); ?>号: <b><?php echo htmlspecialchars($profile['qq'] ?? '', ENT_QUOTES, 'UTF-8'); ?></b></p>
        <p>用户组: <b><?php echo strtoupper(htmlspecialchars($profile['user_group'] ?? $_SESSION['group'], ENT_QUOTES, 'UTF-8')); ?></b></p>
        <?php if (!empty($profile['redeem_locked_until'])): ?>
            <p>兑换锁定至: <b><?php echo htmlspecialchars($profile['redeem_locked_until'], ENT_QUOTES, 'UTF-8'); ?></b></p>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="panel">
            <h3>修改密码</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="change_password">

                当前密码:
                <input type="password" name="current_pwd" required>

                新密码 (至少8位):
                <input type="password" name="new_pwd" required minlength="8">

                确认新密码:
                <input type="password" name="confirm_new_pwd" required minlength="8">

                <button type="submit">提交修改</button>
            </form>
        </div>

        <div class="panel">
            <h3>兑换自动组代码</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="redeem_code">

                兑换码:
                <input type="text" id="redeem_code" name="redeem_code" required maxlength="11" pattern="\d{3}-\d{7}" inputmode="numeric" placeholder="例如 035-1234567" oninput="formatRedeemCode(this)">

                <button type="submit">立即兑换</button>
            </form>
            <p style="font-size: 12px; color:#666;">兑换码格式：3位数字 + '-' + 7位数字（示例：035-1234567）。连续失败过多将锁定2小时。</p>
        </div>
    </div>
</div>

<script>
function formatRedeemCode(input) {
    let digits = input.value.replace(/\D/g, '').slice(0, 10);

    if (digits.length > 3) {
        input.value = digits.slice(0, 3) + '-' + digits.slice(3);
        return;
    }

    input.value = digits;
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>
