<?php
session_start();

date_default_timezone_set('Asia/Shanghai');

$lockFile = __DIR__ . '/setup.lock';
$errors = [];
$success = false;
$setupBlocked = false;
$setupRequiresConfirmation = false;

$language = $_GET['lang'] ?? '';
$language = strtolower($language);
if ($language !== 'zh' && $language !== 'en') {
    $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    $language = strpos($accept, 'zh') === 0 ? 'zh' : 'en';
}

$translations = [
    'en' => [
        'page_title' => 'Setup Wizard - Giveaway System',
        'heading' => 'Giveaway System Setup',
        'setup_blocked' => 'Setup blocked:',
        'setup_completed' => 'Setup completed!',
        'setup_completed_detail' => 'Your configuration file has been written, the database schema was created, and the owner account is ready.',
        'setup_completed_security' => 'Security: delete setup.php or keep setup.lock in place to prevent re-running the installer.',
        'setup_locked' => 'Setup locked.',
        'setup_locked_detail' => 'Remove setup.lock only if you need to re-run the installer on a fresh database.',
        'setup_warning' => 'This installer will create the database schema and an owner account. Do not run it on a production system with existing data.',
        'db_section' => 'Database',
        'db_host' => 'Database host',
        'db_name' => 'Database name',
        'db_user' => 'Database user',
        'db_pass' => 'Database password',
        'db_charset' => 'Database charset',
        'site_section' => 'Site Settings',
        'site_name' => 'Site name',
        'owner_name' => 'Owner name',
        'site_version' => 'Site version label',
        'admin_version' => 'Admin version label',
        'social_platform' => 'Social platform name',
        'social_numeric_only' => 'Social ID numeric only',
        'yes' => 'Yes',
        'no' => 'No',
        'owner_section' => 'Owner Account',
        'admin_username' => 'Admin username',
        'admin_password' => 'Admin password',
        'admin_password_confirm' => 'Confirm password',
        'admin_social' => 'Admin social media ID',
        'tracking_section' => 'Tracking API (Optional)',
        'tracking_provider' => 'Provider',
        'tracking_none' => 'None (disabled)',
        'tracking_17track_key' => '17Track API key (if using 17track)',
        'run_setup' => 'Run setup',
        'existing_tables' => 'Existing tables were detected in the target database. Setup is disabled to protect existing data.',
        'existing_config_confirm' => 'A configuration file already exists. To proceed you must confirm that this is a fresh install.',
        'confirm_label' => 'I understand this will overwrite any existing data and should only be used on a fresh install.',
        'confirm_required' => 'Please confirm this is a fresh install before continuing.',
        'csrf_mismatch' => 'Security token mismatch. Please refresh and try again.',
        'db_required' => 'Database host, name, and user are required.',
        'db_name_invalid' => 'Database name may only contain letters, numbers, underscores, and dashes.',
        'site_required' => 'Site name and owner name are required.',
        'social_required' => 'Social platform name is required.',
        'admin_required' => 'Admin username and password are required.',
        'password_mismatch' => 'Admin passwords do not match.',
        'password_length' => 'Admin password must be at least 8 characters.',
        'admin_social_required' => 'Admin social media ID is required.',
        'admin_social_numeric' => 'Admin social media ID must be numeric.',
        'tracking_invalid' => 'Selected tracking provider is invalid.',
        'schema_read' => 'Could not read schema file.',
        'config_not_writable' => 'config.php is not writable. Please adjust file permissions and try again.',
        'config_write_fail' => 'Unable to write config.php.',
        'lock_write_fail' => 'Unable to create setup.lock. Please ensure the directory is writable.',
        'setup_failed_prefix' => 'Setup failed: ',
        'setup_already_completed' => 'Setup has already been completed. Remove setup.lock if you need to re-run the installer.',
    ],
    'zh' => [
        'page_title' => '安装向导 - Giveaway System',
        'heading' => 'Giveaway System 安装向导',
        'setup_blocked' => '安装已被阻止：',
        'setup_completed' => '安装完成！',
        'setup_completed_detail' => '配置文件已写入，数据库结构已创建，管理员账户已准备就绪。',
        'setup_completed_security' => '安全提示：请删除 setup.php 或保留 setup.lock 以防止再次运行安装程序。',
        'setup_locked' => '安装已锁定。',
        'setup_locked_detail' => '仅在全新数据库上需要重新安装时才移除 setup.lock。',
        'setup_warning' => '安装程序将创建数据库结构与管理员账户。不要在已有生产数据的系统上运行。',
        'db_section' => '数据库',
        'db_host' => '数据库主机',
        'db_name' => '数据库名称',
        'db_user' => '数据库用户名',
        'db_pass' => '数据库密码',
        'db_charset' => '数据库字符集',
        'site_section' => '站点设置',
        'site_name' => '站点名称',
        'owner_name' => '站点所有者名称',
        'site_version' => '站点版本标签',
        'admin_version' => '后台版本标签',
        'social_platform' => '社交平台名称',
        'social_numeric_only' => '社交 ID 仅限数字',
        'yes' => '是',
        'no' => '否',
        'owner_section' => '管理员账户',
        'admin_username' => '管理员用户名',
        'admin_password' => '管理员密码',
        'admin_password_confirm' => '确认密码',
        'admin_social' => '管理员社交账号 ID',
        'tracking_section' => '物流跟踪 API（可选）',
        'tracking_provider' => '服务商',
        'tracking_none' => '无（禁用）',
        'tracking_17track_key' => '17Track API Key（使用 17track 时）',
        'run_setup' => '开始安装',
        'existing_tables' => '检测到目标数据库已有表。为保护现有数据，已禁止安装。',
        'existing_config_confirm' => '检测到已存在的配置文件。继续前请确认这是全新安装。',
        'confirm_label' => '我已了解这将覆盖现有数据，仅能在全新安装时使用。',
        'confirm_required' => '请先确认这是全新安装再继续。',
        'csrf_mismatch' => '安全令牌不匹配，请刷新后重试。',
        'db_required' => '数据库主机、名称和用户名为必填项。',
        'db_name_invalid' => '数据库名称只能包含字母、数字、下划线和短横线。',
        'site_required' => '站点名称和所有者名称为必填项。',
        'social_required' => '社交平台名称为必填项。',
        'admin_required' => '管理员用户名和密码为必填项。',
        'password_mismatch' => '管理员密码不一致。',
        'password_length' => '管理员密码长度至少为 8 个字符。',
        'admin_social_required' => '管理员社交账号 ID 为必填项。',
        'admin_social_numeric' => '管理员社交账号 ID 只能为数字。',
        'tracking_invalid' => '所选物流服务商无效。',
        'schema_read' => '无法读取数据库结构文件。',
        'config_not_writable' => 'config.php 不可写，请调整文件权限后重试。',
        'config_write_fail' => '无法写入 config.php。',
        'lock_write_fail' => '无法创建 setup.lock，请确保目录可写。',
        'setup_failed_prefix' => '安装失败：',
        'setup_already_completed' => '安装已完成。如需重新安装，请删除 setup.lock。',
    ],
];

function t($key) {
    global $translations, $language;
    return $translations[$language][$key] ?? $translations['en'][$key] ?? $key;
}

$availableProviders = ['none'];
$providerDir = __DIR__ . '/track_api';
if (is_dir($providerDir)) {
    $entries = scandir($providerDir);
    if ($entries !== false) {
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (substr($entry, -4) !== '.php') {
                continue;
            }
            $availableProviders[] = basename($entry, '.php');
        }
    }
}
$availableProviders = array_values(array_unique($availableProviders));

function h($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function setup_csrf_token() {
    if (empty($_SESSION['setup_csrf'])) {
        $_SESSION['setup_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['setup_csrf'];
}

function setup_require_csrf(&$errors) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['setup_csrf'] ?? '', $token)) {
        $errors[] = t('csrf_mismatch');
    }
}

function setup_write_config($configPath, $values) {
    $config = "<?php\n";
    $config .= "# Configuration file for the giveaway system.\n";
    $config .= "# Generated by setup.php.\n";
    $config .= "\n";
    $config .= "# Protect against web entry\n";
    $config .= "if (!defined('GIVEAWAY_SYSTEM')) {\n";
    $config .= "    exit;\n";
    $config .= "}\n\n";
    $config .= "## Site branding\n";
    $config .= '$gsSiteName = ' . var_export($values['site_name'], true) . ";\n";
    $config .= '$gsOwnerName = ' . var_export($values['owner_name'], true) . ";\n";
    $config .= '$gsSiteVersion = ' . var_export($values['site_version'], true) . ";\n";
    $config .= '$gsAdminVersion = ' . var_export($values['admin_version'], true) . ";\n\n";
    $config .= "## Social media settings\n";
    $config .= '$gsSocialPlatform = ' . var_export($values['social_platform'], true) . ";\n";
    $config .= '$gsSocialIdNumericOnly = ' . ($values['social_numeric_only'] ? 'true' : 'false') . ";\n\n";
    $config .= "## Tracking API\n";
    $config .= "# Select the tracking provider file name (without .php) in /track_api\n";
    $config .= '$gsTrackingProvider = ' . var_export($values['tracking_provider'], true) . ";\n";
    $config .= '$gs17TrackApiKey = ' . var_export($values['tracking_17track_key'], true) . ";\n\n";
    $config .= "## Database configuration\n";
    $config .= '$gsDbHost = ' . var_export($values['db_host'], true) . ";\n";
    $config .= '$gsDbName = ' . var_export($values['db_name'], true) . ";\n";
    $config .= '$gsDbUser = ' . var_export($values['db_user'], true) . ";\n";
    $config .= '$gsDbPass = ' . var_export($values['db_pass'], true) . ";\n";
    $config .= '$gsDbCharset = ' . var_export($values['db_charset'], true) . ";\n";

    return file_put_contents($configPath, $config, LOCK_EX);
}

function setup_extract_config_value($contents, $variable) {
    $pattern = '/\\$' . preg_quote($variable, '/') . '\\s*=\\s*([\'"])(.*?)\\1\\s*;/m';
    if (preg_match($pattern, $contents, $matches)) {
        return $matches[2];
    }
    return null;
}

function setup_read_config_db_settings($configPath) {
    if (!is_file($configPath) || !is_readable($configPath)) {
        return null;
    }
    $contents = file_get_contents($configPath);
    if ($contents === false) {
        return null;
    }
    $host = setup_extract_config_value($contents, 'gsDbHost');
    $name = setup_extract_config_value($contents, 'gsDbName');
    $user = setup_extract_config_value($contents, 'gsDbUser');
    $pass = setup_extract_config_value($contents, 'gsDbPass');
    $charset = setup_extract_config_value($contents, 'gsDbCharset');

    if ($host === null || $name === null || $user === null) {
        return null;
    }

    return [
        'host' => $host,
        'name' => $name,
        'user' => $user,
        'pass' => $pass ?? '',
        'charset' => $charset ?: 'utf8mb4',
    ];
}

function setup_database_has_tables($config) {
    $dbCharset = $config['charset'] ?: 'utf8mb4';
    $createCharset = preg_replace('/[^a-z0-9_]/i', '', $dbCharset);
    $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['name'] . ';charset=' . $createCharset;
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $stmt = $pdo->prepare('SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = ?');
    $stmt->execute([$config['name']]);
    $result = $stmt->fetch();
    return !empty($result) && (int)$result['total'] > 0;
}

if (is_file($lockFile)) {
    $errors[] = t('setup_already_completed');
    $setupBlocked = true;
}

if (!$setupBlocked) {
    $existingConfig = setup_read_config_db_settings(__DIR__ . '/config.php');
    if ($existingConfig !== null) {
        $setupRequiresConfirmation = true;
    }
}

$defaults = [
    'db_host' => '127.0.0.1',
    'db_name' => 'giveaway_sys',
    'db_user' => '',
    'db_pass' => '',
    'db_charset' => 'utf8mb4',
    'site_name' => 'Giveaway System',
    'owner_name' => 'Site Owner',
    'site_version' => 'V1.0',
    'admin_version' => 'v1.0',
    'social_platform' => 'QQ',
    'social_numeric_only' => true,
    'admin_username' => '',
    'admin_password' => '',
    'admin_password_confirm' => '',
    'admin_social' => '',
    'tracking_provider' => 'none',
    'tracking_17track_key' => '',
];

$values = $defaults;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    setup_require_csrf($errors);

    foreach ($defaults as $key => $default) {
        if (isset($_POST[$key])) {
            if (is_bool($default)) {
                $values[$key] = $_POST[$key] === '1';
            } else {
                $values[$key] = trim((string)$_POST[$key]);
            }
        }
    }

    if ($values['db_host'] === '' || $values['db_name'] === '' || $values['db_user'] === '') {
        $errors[] = t('db_required');
    }
    if (!preg_match('/^[A-Za-z0-9_\-]+$/', $values['db_name'])) {
        $errors[] = t('db_name_invalid');
    }
    if ($values['site_name'] === '' || $values['owner_name'] === '') {
        $errors[] = t('site_required');
    }
    if ($values['social_platform'] === '') {
        $errors[] = t('social_required');
    }
    if ($values['admin_username'] === '' || $values['admin_password'] === '' || $values['admin_password_confirm'] === '') {
        $errors[] = t('admin_required');
    }
    if ($values['admin_password'] !== $values['admin_password_confirm']) {
        $errors[] = t('password_mismatch');
    }
    if (strlen($values['admin_password']) < 8) {
        $errors[] = t('password_length');
    }
    if ($values['admin_social'] === '') {
        $errors[] = t('admin_social_required');
    }
    if ($values['social_numeric_only'] && !preg_match('/^[0-9]+$/', $values['admin_social'])) {
        $errors[] = t('admin_social_numeric');
    }

    if (!in_array($values['tracking_provider'], $availableProviders, true)) {
        $errors[] = t('tracking_invalid');
    }

    if ($setupRequiresConfirmation && empty($_POST['confirm_fresh'])) {
        $errors[] = t('confirm_required');
    }

    if (empty($errors)) {
        $targetConfig = [
            'host' => $values['db_host'],
            'name' => $values['db_name'],
            'user' => $values['db_user'],
            'pass' => $values['db_pass'],
            'charset' => $values['db_charset'],
        ];

        try {
            if (setup_database_has_tables($targetConfig)) {
                $errors[] = t('existing_tables');
            }
        } catch (Throwable $e) {
            $setupRequiresConfirmation = true;
            if (empty($_POST['confirm_fresh'])) {
                $errors[] = t('confirm_required');
            }
        }
    }

    if (empty($errors)) {
        $dbCharset = $values['db_charset'] ?: 'utf8mb4';
        $createCharset = preg_replace('/[^a-z0-9_]/i', '', $dbCharset);
        $dsnBase = 'mysql:host=' . $values['db_host'] . ';charset=' . $createCharset;

        try {
            $pdo = new PDO($dsnBase, $values['db_user'], $values['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);

            $collation = $createCharset === 'utf8mb4' ? 'utf8mb4_unicode_ci' : $createCharset . '_general_ci';
            $pdo->exec(
                sprintf(
                    "CREATE DATABASE IF NOT EXISTS `%s` DEFAULT CHARACTER SET %s COLLATE %s",
                    str_replace('`', '``', $values['db_name']),
                    $createCharset,
                    $collation
                )
            );

            $dsnDb = 'mysql:host=' . $values['db_host'] . ';dbname=' . $values['db_name'] . ';charset=' . $createCharset;
            $pdo = new PDO($dsnDb, $values['db_user'], $values['db_pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $schemaSql = file_get_contents(__DIR__ . '/docs/init-db.sql');
            if ($schemaSql === false) {
                throw new RuntimeException(t('schema_read'));
            }
            $schemaSql = preg_replace('/--.*$/m', '', $schemaSql);
            $schemaSql = preg_replace('/CREATE DATABASE.*?;\s*/is', '', $schemaSql, 1);
            $schemaSql = preg_replace('/USE\s+`?[^`\s;]+`?\s*;\s*/i', '', $schemaSql, 1);
            $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }

            $adminHash = password_hash($values['admin_password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (uid, pwdhash, qq, user_group, verified, disabled) VALUES (?, ?, ?, 'owner', 1, 0)"
            );
            $stmt->execute([
                $values['admin_username'],
                $adminHash,
                $values['admin_social'],
            ]);

            if (!is_writable(__DIR__ . '/config.php')) {
                throw new RuntimeException(t('config_not_writable'));
            }

            $configValues = [
                'site_name' => $values['site_name'],
                'owner_name' => $values['owner_name'],
                'site_version' => $values['site_version'],
                'admin_version' => $values['admin_version'],
                'social_platform' => $values['social_platform'],
                'social_numeric_only' => $values['social_numeric_only'],
                'tracking_provider' => $values['tracking_provider'],
                'tracking_17track_key' => $values['tracking_provider'] === '17track' ? $values['tracking_17track_key'] : '',
                'db_host' => $values['db_host'],
                'db_name' => $values['db_name'],
                'db_user' => $values['db_user'],
                'db_pass' => $values['db_pass'],
                'db_charset' => $values['db_charset'],
            ];

            if (!setup_write_config(__DIR__ . '/config.php', $configValues)) {
                throw new RuntimeException(t('config_write_fail'));
            }

            $lockWritten = file_put_contents($lockFile, 'Setup completed at ' . date('c'));
            if ($lockWritten === false) {
                throw new RuntimeException(t('lock_write_fail'));
            }
            $success = true;
        } catch (Throwable $e) {
            $errors[] = t('setup_failed_prefix') . $e->getMessage();
        }
    }
}

$csrfToken = setup_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo h(t('page_title')); ?></title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 30px; }
        .container { max-width: 720px; margin: 0 auto; background: #fff; padding: 24px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { margin-top: 0; }
        .section { margin-top: 24px; }
        label { display: block; margin: 10px 0 6px; font-weight: 600; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 8px 10px; border: 1px solid #ccc; border-radius: 4px; }
        .row { display: flex; gap: 16px; }
        .row > div { flex: 1; }
        .note { background: #f0f4ff; padding: 12px; border-radius: 6px; font-size: 0.9em; }
        .error { background: #ffe5e5; color: #b40000; padding: 12px; border-radius: 6px; }
        .success { background: #e7f8ed; color: #1a6b2b; padding: 12px; border-radius: 6px; }
        .actions { margin-top: 20px; }
        .actions button { padding: 10px 18px; border: none; background: #2d6cdf; color: #fff; border-radius: 4px; cursor: pointer; }
        .actions button:disabled { background: #aab7d6; }
        .language-switch { text-align: right; font-size: 0.9em; margin-bottom: 12px; }
        .language-switch a { color: #2d6cdf; text-decoration: none; margin-left: 8px; }
        .language-switch strong { margin-left: 8px; }
    </style>
</head>
<body>
<div class="container">
    <div class="language-switch">
        <span>Language:</span>
        <?php if ($language === 'en'): ?>
            <strong>English</strong>
        <?php else: ?>
            <a href="?lang=en">English</a>
        <?php endif; ?>
        <?php if ($language === 'zh'): ?>
            <strong>中文</strong>
        <?php else: ?>
            <a href="?lang=zh">中文</a>
        <?php endif; ?>
    </div>
    <h1><?php echo h(t('heading')); ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong><?php echo h(t('setup_blocked')); ?></strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo h($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success">
            <strong><?php echo h(t('setup_completed')); ?></strong>
            <p><?php echo h(t('setup_completed_detail')); ?></p>
            <p><strong><?php echo h(t('setup_completed_security')); ?></strong></p>
        </div>
    <?php elseif ($setupBlocked): ?>
        <div class="note">
            <strong><?php echo h(t('setup_locked')); ?></strong>
            <p><?php echo h(t('setup_locked_detail')); ?></p>
        </div>
    <?php else: ?>
        <p class="note">
            <?php echo h(t('setup_warning')); ?>
        </p>

        <form method="post" action="?lang=<?php echo h($language); ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">

            <div class="section">
                <h2><?php echo h(t('db_section')); ?></h2>
                <div class="row">
                    <div>
                        <label for="db_host"><?php echo h(t('db_host')); ?></label>
                        <input id="db_host" type="text" name="db_host" value="<?php echo h($values['db_host']); ?>" required>
                    </div>
                    <div>
                        <label for="db_name"><?php echo h(t('db_name')); ?></label>
                        <input id="db_name" type="text" name="db_name" value="<?php echo h($values['db_name']); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label for="db_user"><?php echo h(t('db_user')); ?></label>
                        <input id="db_user" type="text" name="db_user" value="<?php echo h($values['db_user']); ?>" required>
                    </div>
                    <div>
                        <label for="db_pass"><?php echo h(t('db_pass')); ?></label>
                        <input id="db_pass" type="password" name="db_pass" value="<?php echo h($values['db_pass']); ?>">
                    </div>
                </div>
                <label for="db_charset"><?php echo h(t('db_charset')); ?></label>
                <input id="db_charset" type="text" name="db_charset" value="<?php echo h($values['db_charset']); ?>">
            </div>

            <div class="section">
                <h2><?php echo h(t('site_section')); ?></h2>
                <label for="site_name"><?php echo h(t('site_name')); ?></label>
                <input id="site_name" type="text" name="site_name" value="<?php echo h($values['site_name']); ?>" required>

                <label for="owner_name"><?php echo h(t('owner_name')); ?></label>
                <input id="owner_name" type="text" name="owner_name" value="<?php echo h($values['owner_name']); ?>" required>

                <div class="row">
                    <div>
                        <label for="site_version"><?php echo h(t('site_version')); ?></label>
                        <input id="site_version" type="text" name="site_version" value="<?php echo h($values['site_version']); ?>">
                    </div>
                    <div>
                        <label for="admin_version"><?php echo h(t('admin_version')); ?></label>
                        <input id="admin_version" type="text" name="admin_version" value="<?php echo h($values['admin_version']); ?>">
                    </div>
                </div>

                <div class="row">
                    <div>
                        <label for="social_platform"><?php echo h(t('social_platform')); ?></label>
                        <input id="social_platform" type="text" name="social_platform" value="<?php echo h($values['social_platform']); ?>" required>
                    </div>
                    <div>
                        <label for="social_numeric_only"><?php echo h(t('social_numeric_only')); ?></label>
                        <select id="social_numeric_only" name="social_numeric_only">
                            <option value="1" <?php echo $values['social_numeric_only'] ? 'selected' : ''; ?>><?php echo h(t('yes')); ?></option>
                            <option value="0" <?php echo !$values['social_numeric_only'] ? 'selected' : ''; ?>><?php echo h(t('no')); ?></option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="section">
                <h2><?php echo h(t('owner_section')); ?></h2>
                <label for="admin_username"><?php echo h(t('admin_username')); ?></label>
                <input id="admin_username" type="text" name="admin_username" value="<?php echo h($values['admin_username']); ?>" required>

                <div class="row">
                    <div>
                        <label for="admin_password"><?php echo h(t('admin_password')); ?></label>
                        <input id="admin_password" type="password" name="admin_password" required>
                    </div>
                    <div>
                        <label for="admin_password_confirm"><?php echo h(t('admin_password_confirm')); ?></label>
                        <input id="admin_password_confirm" type="password" name="admin_password_confirm" required>
                    </div>
                </div>

                <label for="admin_social"><?php echo h(t('admin_social')); ?></label>
                <input id="admin_social" type="text" name="admin_social" value="<?php echo h($values['admin_social']); ?>" required>
            </div>

            <div class="section">
                <h2><?php echo h(t('tracking_section')); ?></h2>
                <label for="tracking_provider"><?php echo h(t('tracking_provider')); ?></label>
                <select id="tracking_provider" name="tracking_provider">
                    <?php foreach ($availableProviders as $provider): ?>
                        <option value="<?php echo h($provider); ?>" <?php echo $values['tracking_provider'] === $provider ? 'selected' : ''; ?>>
                            <?php echo $provider === 'none' ? h(t('tracking_none')) : h($provider); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="tracking_17track_key"><?php echo h(t('tracking_17track_key')); ?></label>
                <input id="tracking_17track_key" type="text" name="tracking_17track_key" value="<?php echo h($values['tracking_17track_key']); ?>">
            </div>

            <?php if ($setupRequiresConfirmation): ?>
                <div class="section">
                    <p class="note"><?php echo h(t('existing_config_confirm')); ?></p>
                    <label>
                        <input type="checkbox" name="confirm_fresh" value="1" <?php echo !empty($_POST['confirm_fresh']) ? 'checked' : ''; ?>>
                        <?php echo h(t('confirm_label')); ?>
                    </label>
                </div>
            <?php endif; ?>

            <div class="actions">
                <button type="submit"><?php echo h(t('run_setup')); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
