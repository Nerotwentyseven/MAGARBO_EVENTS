<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int) $_GET['id'];
    $status = trim($_GET['status']);

    $allowed = ['Approved', 'Cancelled', 'Completed', 'UndoCancel'];

    if (in_array($status, $allowed, true)) {

        // KUNIN MUNA ANG user_id NG APPOINTMENT
        $get_user_sql = "SELECT user_id, purpose, apt_date FROM appointments WHERE id = ?";
        $stmt_get = mysqli_prepare($conn, $get_user_sql);
        mysqli_stmt_bind_param($stmt_get, "i", $id);
        mysqli_stmt_execute($stmt_get);
        $result_get = mysqli_stmt_get_result($stmt_get);
        $appointment = mysqli_fetch_assoc($result_get);

        if ($appointment) {
            $user_id = (int) $appointment['user_id'];
            $purpose = $appointment['purpose'] ?? 'appointment';
            $apt_date = !empty($appointment['apt_date'])
                ? date("F j, Y", strtotime($appointment['apt_date']))
                : 'the selected date';

            if ($status === 'Cancelled') {
                $sql = "UPDATE appointments 
                        SET status = ?, cancelled_at = NOW() 
                        WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $status, $id);

            } elseif ($status === 'UndoCancel') {
                $newStatus = 'Pending';
                $sql = "UPDATE appointments 
                        SET status = ?, cancelled_at = NULL 
                        WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $newStatus, $id);

            } else {
                $sql = "UPDATE appointments 
                        SET status = ?, cancelled_at = NULL 
                        WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "si", $status, $id);
            }

            if (mysqli_stmt_execute($stmt)) {

                $type = 'appointment';
                $link = 'profile.php?view=appointments';

                if ($status === 'Approved') {
                    $title = 'Appointment Approved';
                    $message = "Your appointment for {$purpose} on {$apt_date} has been approved.";
                } elseif ($status === 'Cancelled') {
                    $title = 'Appointment Cancelled';
                    $message = "Your appointment for {$purpose} on {$apt_date} has been cancelled.";
                } elseif ($status === 'Completed') {
                    $title = 'Appointment Completed';
                    $message = "Your appointment for {$purpose} on {$apt_date} has been marked as completed.";
                } else {
                    $title = 'Appointment Restored';
                    $message = "Your appointment for {$purpose} on {$apt_date} has been restored and is now pending again.";
                }

                $check_sql = "SELECT id FROM notifications 
                              WHERE user_id = ? AND type = ? AND title = ? AND message = ? 
                              LIMIT 1";
                $stmt_check = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($stmt_check, "isss", $user_id, $type, $title, $message);
                mysqli_stmt_execute($stmt_check);
                $check_result = mysqli_stmt_get_result($stmt_check);

                if (mysqli_num_rows($check_result) == 0) {
                    $insert_notif = "INSERT INTO notifications (user_id, type, title, message, link, is_read) 
                                     VALUES (?, ?, ?, ?, ?, 0)";
                    $stmt_notif = mysqli_prepare($conn, $insert_notif);
                    mysqli_stmt_bind_param($stmt_notif, "issss", $user_id, $type, $title, $message, $link);
                    mysqli_stmt_execute($stmt_notif);
                }
            }
        }
    }
}

header("Location: appointments.php");
exit();
?>