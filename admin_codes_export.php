<?php
require 'db.php';
session_start();

if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die('Access Denied.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=redeem_codes_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

fputcsv($output, ['code', 'code_type', 'bound_uid', 'target_group', 'is_used', 'redeemed_by', 'redeemed_at', 'created_by', 'created_at']);

$stmt = $pdo->query('SELECT code, code_type, bound_uid, target_group, is_used, redeemed_by, redeemed_at, created_by, created_at FROM redeem_codes ORDER BY id DESC');

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['code'],
        $row['code_type'],
        $row['bound_uid'],
        $row['target_group'],
        $row['is_used'],
        $row['redeemed_by'],
        $row['redeemed_at'],
        $row['created_by'],
        $row['created_at']
    ]);
}

fclose($output);
exit;
