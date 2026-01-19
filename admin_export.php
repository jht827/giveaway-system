<?php
/**
 * admin_export.php
 * Exports orders to a spreadsheet format (z1, a1, n1, p1, m1, o1)
 */

require 'db.php';
session_start();
require 'csrf.php';

// Security Check
if (!isset($_SESSION['uid']) || $_SESSION['group'] !== 'owner') {
    die("Access Denied.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Error: POST required.");
}

csrf_require();

$eid = $_POST['eid'] ?? '';
if (empty($eid)) {
    die("Error: Please specify an EID.");
}

// 1. Set Headers for CSV Download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Orders_Event_'.$eid.'_'.date('Ymd').'.csv');

// 2. Open output stream
$output = fopen('php://output', 'w');

// 3. Add UTF-8 BOM for Excel (Fixes Chinese character garbling)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// 4. Set Header Row
fputcsv($output, ['z1', 'a1', 'n1', 'p1', 'm1', 'o1']);

// 5. Fetch Data
$stmt = $pdo->prepare("
    SELECT a.postcode, a.addr, a.name, a.phone, o.choice, o.xa, o.oid
    FROM orders o
    JOIN addresses a ON o.aid = a.aid
    WHERE o.eid = ?
    ORDER BY o.oid ASC
");
$stmt->execute([$eid]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Logic for m1: Combine Registered status (R) and Choice
    $m1 = ($row['xa'] == 'R') ? 'R' . $row['choice'] : $row['choice'];

    fputcsv($output, [
        $row['postcode'],  // z1 (zip)
        $row['addr'],      // a1 (addr)
        $row['name'],      // n1 (name)
        $row['phone'],     // p1 (phone)
        $m1,               // m1 (note: R + choice)
        $row['oid']        // o1 (order number)
    ]);
}

fclose($output);
exit;
