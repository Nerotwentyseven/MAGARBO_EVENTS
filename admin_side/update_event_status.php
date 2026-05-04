<?php
session_name('ADMINSESSID');
session_start();

require_once '../db_connection.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int)$_GET['id'];
    $status = $_GET['status'];

    $allowed = ['ongoing', 'completed'];

    if ($id > 0 && in_array($status, $allowed, true)) {

        if ($status === 'completed') {
            $checkSql = "SELECT 
                            b.total_price,
                            b.downpayment,
                            COALESCE(SUM(CASE WHEN bp.payment_status = 'Paid' THEN bp.amount ELSE 0 END), 0) AS total_paid
                         FROM bookings b
                         LEFT JOIN booking_payments bp ON bp.booking_id = b.id
                         WHERE b.id = ? 
                           AND b.booking_status = 'Approved'
                         GROUP BY b.id, b.total_price, b.downpayment";

            $checkStmt = mysqli_prepare($conn, $checkSql);
            mysqli_stmt_bind_param($checkStmt, "i", $id);
            mysqli_stmt_execute($checkStmt);
            $result = mysqli_stmt_get_result($checkStmt);
            $payment = mysqli_fetch_assoc($result);

            if (!$payment) {
                header("Location: events.php");
                exit();
            }

            $totalPrice = (float)$payment['total_price'];
            $totalPaidFromPayments = (float)$payment['total_paid'];
            $downpayment = (float)$payment['downpayment'];

            $totalPaid = $totalPaidFromPayments > 0 ? $totalPaidFromPayments : $downpayment;
            $balance = max($totalPrice - $totalPaid, 0);

            if ($balance > 0) {
                header("Location: events.php?error=balance_remaining");
                exit();
            }
        }

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