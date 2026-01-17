<?php
session_start();
require 'db.php';

// 1. Check if the session exists at all
if (!isset($_SESSION['uid'])) {
    header("Location: login.php");
    exit;
}

// 2. Query the DB for the CURRENT status of this user
$stmt = $pdo->prepare("SELECT verified, disabled FROM users WHERE uid = ?");
$stmt->execute([$_SESSION['uid']]);
$user_status = $stmt->fetch();

// 3. If the user was deleted, unverified, or disabled since login, kick them out
if (!$user_status || $user_status['verified'] == 0 || $user_status['disabled'] == 1) {
    session_destroy(); // Wipe their session
    header("Location: login.php?err=account_issue");
    exit;
}
?>