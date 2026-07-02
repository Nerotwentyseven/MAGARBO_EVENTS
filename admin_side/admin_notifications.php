<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

$settings = [];
$settingsRes = mysqli_query($conn, "SELECT notif_order, notif_msg, notif_appointment FROM admin_settings WHERE id = 1 LIMIT 1");

if ($settingsRes) {
    $settings = mysqli_fetch_assoc($settingsRes) ?: [];
}

$notif_order_enabled = (int)($settings['notif_order'] ?? 1);
$notif_msg_enabled = (int)($settings['notif_msg'] ?? 1);
$notif_appointment_enabled = (int)($settings['notif_appointment'] ?? 1);

if ($action === 'mark_read') {
    $type = $_POST['type'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $lastId = (int)($_POST['last_id'] ?? 0);

    if ($type === 'message' && $id > 0 && $lastId > 0) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO admin_notification_reads (item_type, item_id, last_item_id, read_at)
            VALUES ('message', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                last_item_id = VALUES(last_item_id),
                read_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, "ii", $id, $lastId);
        mysqli_stmt_execute($stmt);

        echo json_encode(['success' => true]);
        exit;
    }

    if (in_array($type, ['appointment', 'order'], true) && $id > 0) {
        $stmt = mysqli_prepare($conn, "
            INSERT INTO admin_notification_reads (item_type, item_id, last_item_id, read_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
                last_item_id = VALUES(last_item_id),
                read_at = NOW()
        ");
        mysqli_stmt_bind_param($stmt, "sii", $type, $id, $id);
        mysqli_stmt_execute($stmt);

        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false]);
    exit;
}

$items = [];

if ($notif_msg_enabled === 1) {
    $sql = "
        SELECT 
            cm.user_id,
            MAX(cm.id) AS latest_message_id,
            COUNT(*) AS total_messages,
            MAX(cm.created_at) AS latest_time,
            r.last_item_id,
            r.read_at,
            COALESCE(CONCAT(u.firstname, ' ', u.lastname), u.email, 'Customer') AS customer_name
        FROM chat_messages cm
        LEFT JOIN users u ON u.id = cm.user_id
        LEFT JOIN admin_notification_reads r
            ON r.item_type = 'message'
           AND r.item_id = cm.user_id
        WHERE cm.sender = 'user'
          AND cm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY cm.user_id, r.last_item_id, r.read_at, u.firstname, u.lastname, u.email
        ORDER BY latest_time DESC
    ";

    $res = mysqli_query($conn, $sql);

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $latestMessageId = (int)$row['latest_message_id'];
        $lastSeenMessageId = (int)($row['last_item_id'] ?? 0);
        $isRead = ($lastSeenMessageId >= $latestMessageId) ? 1 : 0;

        $items[] = [
            'type' => 'message',
            'id' => (int)$row['user_id'],
            'last_id' => $latestMessageId,
            'title' => $isRead ? 'Viewed message' : 'New message',
            'text' => $row['customer_name'] . ' sent a message',
            'link' => 'messages.php?user_id=' . (int)$row['user_id'],
            'time' => $row['latest_time'],
            'is_read' => $isRead
        ];
    }
}

if ($notif_appointment_enabled === 1) {
    $sql = "
        SELECT 
            a.id,
            a.created_at,
            r.read_at,
            COALESCE(CONCAT(u.firstname, ' ', u.lastname), u.email, 'Customer') AS customer_name
        FROM appointments a
        LEFT JOIN users u ON u.id = a.user_id
        LEFT JOIN admin_notification_reads r
            ON r.item_type = 'appointment' 
           AND r.item_id = a.id
        WHERE a.status = 'Pending'
          AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY a.created_at DESC
    ";

    $res = mysqli_query($conn, $sql);

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $items[] = [
            'type' => 'appointment',
            'id' => (int)$row['id'],
            'last_id' => (int)$row['id'],
            'title' => empty($row['read_at']) ? 'Pending appointment' : 'Viewed appointment',
            'text' => $row['customer_name'] . ' has a pending appointment',
            'link' => 'appointments.php',
            'time' => $row['created_at'],
            'is_read' => empty($row['read_at']) ? 0 : 1
        ];
    }
}

if ($notif_order_enabled === 1) {
    $sql = "
        SELECT 
            b.id,
            b.created_at,
            r.read_at,
            COALESCE(CONCAT(u.firstname, ' ', u.lastname), u.email, 'Customer') AS customer_name
        FROM bookings b
        LEFT JOIN users u ON u.id = b.user_id
        LEFT JOIN admin_notification_reads r
            ON r.item_type = 'order' 
           AND r.item_id = b.id
        WHERE b.booking_status = 'Pending'
          AND b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY b.created_at DESC
    ";

    $res = mysqli_query($conn, $sql);

    while ($res && $row = mysqli_fetch_assoc($res)) {
        $items[] = [
            'type' => 'order',
            'id' => (int)$row['id'],
            'last_id' => (int)$row['id'],
            'title' => empty($row['read_at']) ? 'Pending order' : 'Viewed order',
            'text' => $row['customer_name'] . ' has a pending order',
            'link' => 'orders.php',
            'time' => $row['created_at'],
            'is_read' => empty($row['read_at']) ? 0 : 1
        ];
    }
}

usort($items, function ($a, $b) {
    $readA = (int)($a['is_read'] ?? 0);
    $readB = (int)($b['is_read'] ?? 0);

    if ($readA !== $readB) {
        return $readA <=> $readB;
    }

    return strtotime($b['time']) <=> strtotime($a['time']);
});

$unreadTotal = 0;

foreach ($items as $item) {
    if ((int)$item['is_read'] === 0) {
        $unreadTotal++;
    }
}

echo json_encode([
    'success' => true,
    'total' => $unreadTotal,
    'items' => $items
]);