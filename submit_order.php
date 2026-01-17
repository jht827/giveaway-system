<?php
/**
 * submit_order.php
 * Finalized Engine for "Old-School Free Item Dist Reg Sys"
 */

require 'auth_check.php'; 

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: home.php");
    exit;
}

csrf_require();

$uid    = $_SESSION['uid'];
$eid    = $_POST['eid'] ?? '';
$aid    = $_POST['aid'] ?? ''; 
$choice = $_POST['choice'] ?? 1;

// NEW LOGIC: Use 'X' for pending payment if registered mail is requested
$xa_request = isset($_POST['xa']) ? 'X' : '0'; 

try {
    $pdo->beginTransaction();

    // 1. SECURITY: Verify Address Ownership
    $stmt_addr = $pdo->prepare("SELECT aid FROM addresses WHERE aid = ? AND uid = ?");
    $stmt_addr->execute([$aid, $uid]);
    if (!$stmt_addr->fetch()) {
        throw new Exception("Address Error: 无权使用该地址或地址不存在");
    }

    // 2. STOCK CHECK: Lock the Event row (Prevent Race Conditions)
    $stmt_event = $pdo->prepare("SELECT total, used FROM events WHERE eid = ? FOR UPDATE");
    $stmt_event->execute([$eid]);
    $event = $stmt_event->fetch();

    if (!$event) {
        throw new Exception("ERR: 活动不存在");
    }

    $current_used = (int)$event['used'];

    // 2.1 ALIGN SEQUENCE: Ensure used count matches existing orders
    $stmt_max_seq = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(oid, '-', -1) AS UNSIGNED)) AS max_seq
        FROM orders
        WHERE eid = ?
        FOR UPDATE
    ");
    $stmt_max_seq->execute([$eid]);
    $max_seq_row = $stmt_max_seq->fetch();
    $max_seq = $max_seq_row && $max_seq_row['max_seq'] !== null ? (int)$max_seq_row['max_seq'] : 0;

    $effective_used = max($current_used, $max_seq);
    $remaining = $event['total'] - $effective_used;
    if ($remaining <= 0) {
        throw new Exception("O04"); // Out of stock
    }

    // 3. DUPLICATE CHECK
    $stmt_dup = $pdo->prepare("SELECT oid FROM orders WHERE uid = ? AND eid = ?");
    $stmt_dup->execute([$uid, $eid]);
    if ($stmt_dup->fetch()) {
        throw new Exception("O01"); 
    }

    // 4. GENERATE OID
    $next_seq_num = $effective_used + 1;
    $next_seq = str_pad($next_seq_num, 4, '0', STR_PAD_LEFT);
    $oid = "J-" . $eid . "-" . $next_seq;

    // 5. INSERT ORDER: Updated with 'X' status and NOW() timestamp
    // Note: ensure your DB table 'orders' has a 'time' column (DATETIME or TIMESTAMP)
    $stmt_insert = $pdo->prepare("
        INSERT INTO orders (oid, uid, eid, aid, choice, xa, state, time) 
        VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
    ");
    $stmt_insert->execute([$oid, $uid, $eid, $aid, $choice, $xa_request]);

    // 6. UPDATE EVENT: Increment count
    $stmt_update = $pdo->prepare("UPDATE events SET used = ? WHERE eid = ?");
    $stmt_update->execute([$next_seq_num, $eid]);

    $pdo->commit();
    
    header("Location: success.php?id=" . $oid);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $err = $e->getMessage();
    if (in_array($err, ['O01', 'O03', 'O04'])) {
        header("Location: home.php?err=" . $err);
    } else {
        die("提交失败: " . htmlspecialchars($err));
    }
    exit;
}
