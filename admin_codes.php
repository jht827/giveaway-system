<?php
require 'db.php';
session_start();
require 'csrf.php';

if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied.");
}

$msg = '';
$err = '';

function is_valid_windows95_prefix(string $prefix): bool
{
    if (!preg_match('/^\d{3}$/', $prefix)) {
        return false;
    }

    $blocked = ['333', '444', '555', '666', '777', '888', '999'];
    if (in_array($prefix, $blocked, true)) {
        return false;
    }

    return ((int)$prefix % 7) === 0;
}

function resolve_prefix(?string $requested): string
{
    $requested = trim((string)$requested);
    if ($requested !== '') {
        if (!is_valid_windows95_prefix($requested)) {
            throw new InvalidArgumentException('前三位必须是 3 位数字、可被 7 整除，且不能是 333/444/555/666/777/888/999。');
        }
        return $requested;
    }

    do {
        $candidate = str_pad((string)random_int(0, 999), 3, '0', STR_PAD_LEFT);
    } while (!is_valid_windows95_prefix($candidate));

    return $candidate;
}

function generate_suffix_mod7(): string
{
    do {
        $suffix = str_pad((string)random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    } while ($suffix[0] === '0' || ((int)$suffix % 7) !== 0);

    return $suffix;
}

function generate_redeem_code(?string $prefix): string
{
    $resolved_prefix = resolve_prefix($prefix);
    return $resolved_prefix . '-' . generate_suffix_mod7();
}

function is_duplicate_key_exception(Throwable $e): bool
{
    if (!($e instanceof PDOException)) {
        return false;
    }

    $code = $e->errorInfo[1] ?? null;
    $sqlState = $e->errorInfo[0] ?? null;

    return $code === 1062 || $sqlState === '23000';
}

function insert_generated_code(PDO $pdo, string $code_type, ?string $bound_uid, string $target_group, string $created_by, ?string $prefix): void
{
    $insert = $pdo->prepare(
        'INSERT INTO redeem_codes (code, code_type, bound_uid, target_group, created_by) VALUES (?, ?, ?, ?, ?)'
    );

    for ($attempt = 0; $attempt < 50; $attempt++) {
        $candidate = generate_redeem_code($prefix);

        try {
            $insert->execute([$candidate, $code_type, $bound_uid, $target_group, $created_by]);
            return;
        } catch (Throwable $e) {
            if (is_duplicate_key_exception($e)) {
                continue;
            }
            throw $e;
        }
    }

    throw new RuntimeException('兑换码生成冲突过多，请重试。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_require();
    $action = $_POST['action'];

    try {
        if ($action === 'generate_public_batch') {
            $count = (int)($_POST['batch_count'] ?? 0);
            $prefix = $_POST['prefix'] ?? '';

            if ($count < 1 || $count > 1000) {
                $err = '批量数量必须在 1 到 1000 之间。';
            } else {
                $pdo->beginTransaction();
                for ($i = 0; $i < $count; $i++) {
                    insert_generated_code($pdo, 'public', null, 'auto', $_SESSION['uid'], $prefix);
                }
                $pdo->commit();
                $msg = "已生成 {$count} 个通用兑换码。";
            }
        } elseif ($action === 'generate_all_bound') {
            $prefix = $_POST['prefix'] ?? '';
            $users = $pdo->query('SELECT uid FROM users ORDER BY uid ASC')->fetchAll(PDO::FETCH_COLUMN);

            if (!$users) {
                $err = '没有可用用户。';
            } else {
                $pdo->beginTransaction();
                $created = 0;
                foreach ($users as $user_uid) {
                    insert_generated_code($pdo, 'bound', $user_uid, 'auto', $_SESSION['uid'], $prefix);
                    $created++;
                }
                $pdo->commit();
                $msg = "已为全部账户生成 {$created} 个绑定兑换码。";
            }
        } elseif ($action === 'generate_user_bound') {
            $bound_uid = trim($_POST['bound_uid'] ?? '');
            $count = (int)($_POST['user_code_count'] ?? 1);
            $prefix = $_POST['prefix'] ?? '';

            if ($bound_uid === '') {
                $err = '请填写绑定用户 UID。';
            } elseif ($count < 1 || $count > 100) {
                $err = '指定用户生成数量必须在 1 到 100 之间。';
            } else {
                $check = $pdo->prepare('SELECT uid FROM users WHERE uid = ?');
                $check->execute([$bound_uid]);

                if (!$check->fetchColumn()) {
                    $err = '用户不存在。';
                } else {
                    $pdo->beginTransaction();
                    for ($i = 0; $i < $count; $i++) {
                        insert_generated_code($pdo, 'bound', $bound_uid, 'auto', $_SESSION['uid'], $prefix);
                    }
                    $pdo->commit();
                    $msg = "已为用户 {$bound_uid} 生成 {$count} 个绑定兑换码。";
                }
            }
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = $e->getMessage();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $err = '兑换码生成失败，请稍后重试。';
    }
}

$codes_stmt = $pdo->query('SELECT code, code_type, bound_uid, target_group, is_used, redeemed_by, redeemed_at, created_by, created_at FROM redeem_codes ORDER BY id DESC LIMIT 200');
$codes = $codes_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>管理后台 - 兑换码中心</title>
    <style>
        body { font-family: "SimSun", serif; background: #333; color: #eee; padding: 20px; }
        .admin-box { background: #444; border: 2px solid #000; padding: 20px; max-width: 1200px; margin: 0 auto; box-shadow: 10px 10px 0px #222; }
        .row { display: flex; gap: 15px; flex-wrap: wrap; }
        .panel { border: 1px solid #222; background: #555; padding: 12px; flex: 1; min-width: 280px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #555; font-size: 13px; }
        th, td { border: 1px solid #222; padding: 8px; text-align: left; }
        th { background: #222; color: #0f0; }
        input, button { width: 100%; padding: 8px; margin-top: 6px; box-sizing: border-box; }
        .btn { border: 1px solid #000; cursor: pointer; }
        .btn-green { background: #28a745; color: #fff; }
        .msg { color: #0f0; }
        .err { color: #ff6b6b; }
        .actions { margin-top: 10px; }
    </style>
</head>
<body>
<div class="admin-box">
    <h2>[ 管理后台 ] 兑换码生成与导出</h2>
    <p>当前管理员: <?php echo htmlspecialchars($_SESSION['uid'], ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($msg): ?>
        <p class="msg"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>
    <?php if ($err): ?>
        <p class="err"><?php echo htmlspecialchars($err, ENT_QUOTES, 'UTF-8'); ?></p>
    <?php endif; ?>

    <div class="row">
        <div class="panel">
            <h3>批量生成通用码</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="generate_public_batch">
                数量 (1-1000)
                <input type="number" name="batch_count" min="1" max="1000" value="10" required>
                前三位(可选，需满足 mod7，如 035)
                <input type="text" name="prefix" maxlength="3" pattern="\d{3}" placeholder="留空则随机">
                <button class="btn btn-green" type="submit">生成通用码</button>
            </form>
        </div>

        <div class="panel">
            <h3>为全部账户生成绑定码</h3>
            <form method="POST" onsubmit="return confirm('将为所有用户生成绑定码，确定继续？');">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="generate_all_bound">
                <p>每个账户生成 1 个绑定码。</p>
                前三位(可选，需满足 mod7，如 035)
                <input type="text" name="prefix" maxlength="3" pattern="\d{3}" placeholder="留空则随机">
                <button class="btn btn-green" type="submit">生成全部绑定码</button>
            </form>
        </div>

        <div class="panel">
            <h3>为指定用户生成绑定码</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="generate_user_bound">
                用户 UID
                <input type="text" name="bound_uid" required>
                数量 (1-100)
                <input type="number" name="user_code_count" min="1" max="100" value="1" required>
                前三位(可选，需满足 mod7，如 035)
                <input type="text" name="prefix" maxlength="3" pattern="\d{3}" placeholder="留空则随机">
                <button class="btn btn-green" type="submit">生成指定绑定码</button>
            </form>
        </div>
    </div>

    <div class="actions">
        <a href="admin_codes_export.php" style="color:#0f0;">下载兑换码 CSV 导出</a> |
        <a href="admin.php" style="color:#0f0;"><- 返回后台</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Bound UID</th>
                <th>Target Group</th>
                <th>Used</th>
                <th>Redeemed By</th>
                <th>Redeemed At</th>
                <th>Created By</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($codes as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['code_type'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['bound_uid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['target_group'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo ((int)$row['is_used'] === 1) ? 'Y' : 'N'; ?></td>
                    <td><?php echo htmlspecialchars((string)($row['redeemed_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['redeemed_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string)($row['created_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
