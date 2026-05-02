<?php
session_name('ADMINSESSID');
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['event_id'] ?? 0);
    $total = (float)($_POST['total_price'] ?? 0);

    if ($id <= 0 || $total <= 0) {
        header("Location: events.php?error=invalid_price");
        exit();
    }

    $newStatus = 'confirmed';

    $sql = "UPDATE bookings
            SET total_price = ?, event_status = ?
            WHERE id = ? AND booking_status = 'Approved'";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "dsi", $total, $newStatus, $id);

    if ($stmt && mysqli_stmt_execute($stmt)) {
        header("Location: events.php?success=price_updated");
        exit();
    }
}

header("Location: events.php");
exit();
?>