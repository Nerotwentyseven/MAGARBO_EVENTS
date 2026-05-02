<?php
session_name('ADMINSESSID');
session_start();

require_once '../db_connection.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];

    $allowed = ['ongoing', 'completed'];

    if ($id > 0 && in_array($status, $allowed, true)) {
        $sql = "UPDATE bookings
                SET event_status = ?
                WHERE id = ? AND booking_status = 'Approved'";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $id);
        mysqli_stmt_execute($stmt);
    }
}

header("Location: events.php");
exit();
?>