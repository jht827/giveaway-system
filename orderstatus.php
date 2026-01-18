<?php
/**
 * orderstatus.php
 * View specific order details, shipping snapshot, and live tracking.
 */

require 'auth_check.php';
require 'track_api.php'; // The logic helper

$oid = $_GET['id'] ?? '';
$uid = $_SESSION['uid'];

// 1. Fetch Order details joined with Event and the specific Address used
// Added 'xa' to the select statement to handle registration status
$stmt = $pdo->prepare("
    SELECT o.*, e.name as event_name, e.send_date, e.send_way, 
           a.postcode, a.addr, a.phone, a.is_intl
    FROM orders o 
    JOIN events e ON o.eid = e.eid 
    JOIN addresses a ON o.aid = a.aid
    WHERE o.oid = ? AND o.uid = ? AND o.user_hidden = 0
");
$stmt->execute([$oid, $uid]);
$order = $stmt->fetch();

if (!$order) {
    die("ERR: 找不到该预约记录或该记录已被隐藏。");
}

// 2. Handle POST Actions (Cancel/Hide)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    csrf_require();
    $action = $_POST['action'];
    try {
        $pdo->beginTransaction();
        if ($action == 'cancel' && $order['state'] == 0) {
            // Revert stock and delete order
            $pdo->prepare("UPDATE events SET used = used - 1 WHERE eid = ?")->execute([$order['eid']]);
            $pdo->prepare("DELETE FROM orders WHERE oid = ?")->execute([$oid]);
            $pdo->commit();
            header("Location: my_orders.php?msg=cancelled"); exit;
        } elseif ($action == 'hide' && $order['state'] == 2) {
            // Just hide from user view
            $pdo->prepare("UPDATE orders SET user_hidden = 1 WHERE oid = ?")->execute([$oid]);
            $pdo->commit();
            header("Location: my_orders.php?msg=hidden"); exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("操作失败: " . $e->getMessage());
    }
}

// 3. Fetch Tracking Data
$track_res = get_tracking_events($order['logistics_no'], $order['send_way']);
$tracking_data = $track_res['data'];
$is_untrackable = ($track_res['status'] == 'untrackable');

$state_text = [0 => "等待发放 (Pending)", 1 => "已发出 (Sent)", 2 => "确认送达 (Arrived)"];
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>预约单详情 - <?php echo htmlspecialchars($oid); ?></title>
    <style>
        body { font-family: "SimSun", serif; background: #f0f0f0; padding: 20px; font-size: 14px; line-height: 1.6; }
        .card { background: white; border: 2px solid #333; padding: 25px; max-width: 650px; margin: 0 auto; box-shadow: 6px 6px 0px #444; }
        .section { border: 1px solid #333; padding: 15px; margin-bottom: 15px; background: #fff; }
        h4 { margin: -15px -15px 10px -15px; background: #333; color: white; padding: 8px 15px; font-weight: normal; font-size: 13px; }
        
        /* Logistics UI */
        .logistics-banner { background: #000; color: #0f0; padding: 10px; font-family: monospace; font-size: 1.2em; text-align: center; border: 1px solid #333; }
        .timeline { max-height: 400px; overflow-y: auto; padding-top: 15px; border-top: 1px dashed #ccc; margin-top: 10px; }
        .track-item { border-left: 2px solid #333; padding: 0 0 15px 15px; position: relative; margin-left: 10px; }
        .track-item::before { content: "◆"; position: absolute; left: -7px; top: 0; background: white; font-size: 12px; color: #333; }
        .time { font-size: 11px; color: #666; display: block; font-family: monospace; }
        
        .notice { background: #fffbe6; border: 1px solid #ffe58f; padding: 12px; color: #856404; font-size: 0.9em; margin-top: 10px; }
        .btn { border: 1px solid #333; padding: 6px 12px; text-decoration: none; color: black; background: #eee; cursor: pointer; display: inline-block; }
        .btn-red { background: #900; color: white; border: none; }
        
        .payment-box { background: #fff0f0; border: 1px dashed #ff4d4f; padding: 10px; margin-top: 5px; }
    </style>
</head>
<body>

<div class="card">
    <div style="margin-bottom: 20px;">
        <a href="my_orders.php" class="btn"><- 返回记录列表</a>
    </div>
    
    <h2 style="margin-top:0;">预约单: <?php echo htmlspecialchars($oid); ?></h2>

    <div class="section">
        <h4>项目及配送信息</h4>
        <p><b>活动内容:</b> <?php echo htmlspecialchars($order['event_name']); ?></p>
        <p><b>预约类型:</b> 选项 <?php echo htmlspecialchars($order['choice']); ?></p>
        
        <p><b>邮寄方式:</b> 
            <?php 
            if ($order['xa'] == 'X') {
                echo '<span style="color:red; font-weight:bold;">[申请挂号: 待支付 +3元]</span>';
                echo '<div class="payment-box"><small>请联系管理员或按照活动说明完成支付，确认后将转为挂号发货。若未支付，将按普通平邮处理。</small></div>';
            } elseif ($order['xa'] == 'R') {
                echo '<span style="color:blue; font-weight:bold;">[挂号: 已支付]</span>';
            } else {
                echo '普通平邮';
            }
            ?>
        </p>

        <p><b>当前状态:</b> <b><?php echo $state_text[$order['state']]; ?></b></p>
        <p><b>收货地址:</b><br>
            [<?php echo htmlspecialchars($order['postcode']); ?>] <?php echo htmlspecialchars($order['phone']); ?><br>
            <?php echo htmlspecialchars($order['addr']); ?> <?php echo ($order['is_intl'] ? "<b>[国际地址]</b>" : ""); ?>
        </p>
    </div>

    <div class="section">
        <h4>物流追踪 (Logistics)</h4>
        
        <?php if ($order['state'] >= 1 && !empty($order['logistics_no'])): ?>
            <div class="logistics-banner">
                NO: <?php echo htmlspecialchars($order['logistics_no']); ?>
            </div>

            <?php if ($is_untrackable): ?>
                <div class="notice">
                    <b>平信提示:</b><br>
                    此邮件为平信。该单号无法在公网API实时追踪。请保存此号码，以便在必要时使用特殊渠道查询（如到邮局柜台询问）。
                </div>
            <?php elseif (!empty($tracking_data)): ?>
                <div class="timeline">
                    <?php foreach ($tracking_data as $step): ?>
                        <div class="track-item">
                            <span class="time"><?php echo $step['time']; ?></span>
                            <div><?php echo htmlspecialchars($step['desc']); ?></div>
                            <?php if(!empty($step['loc'])): ?>
                                <small style="color: #888;">位置: <?php echo htmlspecialchars($step['loc']); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #666; font-style: italic; margin-top: 15px;">
                    暂时无法获取实时动态。您可以手动前往 <a href="https://www.17track.net/zh-cn/track?nums=<?php echo urlencode($order['logistics_no']); ?>" target="_blank" style="color:blue;">17track官网</a> 尝试查询。
                </p>
            <?php endif; ?>

        <?php else: ?>
            <p style="color: #999; text-align: center; padding: 20px;">包裹尚未发出。预计发出时间: <?php echo htmlspecialchars($order['send_date']); ?></p>
        <?php endif; ?>
    </div>

    <div class="section" style="border-style: dashed; background: #fafafa;">
        <h4>订单管理 (Management)</h4>
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
            <?php if ($order['state'] == 0): ?>
                <p style="font-size: 0.85em; color: #666;">包裹发出前，您可以自主取消并退回份额。</p>
                <button type="submit" name="action" value="cancel" class="btn btn-red" onclick="return confirm('确定取消预约吗？份额将立即退回。')">取消预约 (Cancel Order)</button>
            <?php elseif ($order['state'] == 2): ?>
                <p style="font-size: 0.85em; color: #666;">包裹已送达。您可以隐藏此条记录以保持列表整洁。</p>
                <button type="submit" name="action" value="hide" class="btn" onclick="return confirm('确定要隐藏此记录吗？')">隐藏此记录 (Hide Record)</button>
            <?php else: ?>
                <p style="margin:0; color:#666;">包裹运输期间不可由用户执行取消或隐藏操作。</p>
            <?php endif; ?>
        </form>
    </div>

</div>

</body>
</html>
