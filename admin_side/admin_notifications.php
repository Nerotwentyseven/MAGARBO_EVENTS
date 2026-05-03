<?php
require_once '../db_connection.php';

header('Content-Type: application/json');

$settings = [];
$settingsRes = mysqli_query($conn, "SELECT notif_order, notif_msg, notif_appointment FROM admin_settings WHERE id = 1 LIMIT 1");

if ($settingsRes) {
    $settings = mysqli_fetch_assoc($settingsRes) ?: [];
}

$notif_order_enabled = (int)($settings['notif_order'] ?? 1);
$notif_msg_enabled = (int)($settings['notif_msg'] ?? 1);
$notif_appointment_enabled = (int)($settings['notif_appointment'] ?? 1);

$msg = 0;
$apt = 0;
$order = 0;

if ($notif_msg_enabled === 1) {
    $msg_sql = "SELECT COUNT(*) AS total FROM chat_messages WHERE sender = 'user' AND is_read = 0";
    $msg_result = mysqli_query($conn, $msg_sql);
    if ($msg_result) {
        $msg = (int)(mysqli_fetch_assoc($msg_result)['total'] ?? 0);
    }
}

if ($notif_appointment_enabled === 1) {
    $apt_sql = "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'";
    $apt_result = mysqli_query($conn, $apt_sql);
    if ($apt_result) {
        $apt = (int)(mysqli_fetch_assoc($apt_result)['total'] ?? 0);
    }
}

if ($notif_order_enabled === 1) {
    $order_sql = "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'Pending'";
    $order_result = mysqli_query($conn, $order_sql);
    if ($order_result) {
        $order = (int)(mysqli_fetch_assoc($order_result)['total'] ?? 0);
    }
}

echo json_encode([
    'unread_messages' => $msg,
    'pending_appointments' => $apt,
    'pending_orders' => $order,
    'messages' => $msg,
    'appointments' => $apt,
    'orders' => $order,
    'total' => $msg + $apt + $order
]);