<?php
define('GIVEAWAY_SYSTEM', true);
require __DIR__ . '/config.php';

date_default_timezone_set('Asia/Shanghai');

$host = $gsDbHost;
$db   = $gsDbName;
$user = $gsDbUser;
$pass = $gsDbPass;
$charset = $gsDbCharset;

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // Critical for SQL Injection protection
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     $pdo->exec("SET time_zone = '+08:00'");
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
