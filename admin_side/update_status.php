<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

if (isset($_GET['id']) && isset($_GET['status'])) {
    $booking_id = (int) $_GET['id'];
    $status = trim($_GET['status']);

    $allowed_status = ['Approved', 'Cancelled', 'UndoCancel'];

    if (in_array($status, $allowed_status, true)) {

        $get_booking_sql = "SELECT id, user_id, event_type, event_date, selected_theme_id, theme_counted, booking_status, cancelled_at
                            FROM bookings
                            WHERE id = ?";
        $stmt_get = mysqli_prepare($conn, $get_booking_sql);

        if (!$stmt_get) {
            die("Database error: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt_get, "i", $booking_id);
        mysqli_stmt_execute($stmt_get);
        $result_get = mysqli_stmt_get_result($stmt_get);
        $booking = mysqli_fetch_assoc($result_get);
        mysqli_stmt_close($stmt_get);

        if ($booking) {
            $user_id = (int) $booking['user_id'];
            $event_type = $booking['event_type'];
            $event_date = date("F j, Y", strtotime($booking['event_date']));
            $selected_theme_id = (int)($booking['selected_theme_id'] ?? 0);
            $theme_counted = (int)($booking['theme_counted'] ?? 0);
            $old_status = trim($booking['booking_status'] ?? '');

            mysqli_begin_transaction($conn);

            try {
                if ($status === 'Approved') {
                    $event_status = 'draft';

                    $query = "UPDATE bookings
                              SET booking_status = ?, event_status = ?, cancelled_at = NULL
                              WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "ssi", $status, $event_status, $booking_id);

                } elseif ($status === 'Cancelled') {
                    $query = "UPDATE bookings
                              SET booking_status = ?, event_status = NULL, cancelled_at = NOW()
                              WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);

                } elseif ($status === 'UndoCancel') {
                    $canUndo = false;

                    if (
                        $old_status === 'Cancelled' &&
                        !empty($booking['cancelled_at']) &&
                        strtotime($booking['cancelled_at']) >= strtotime('-3 days')
                    ) {
                        $canUndo = true;
                    }

                    if (!$canUndo) {
                        throw new Exception("Undo period expired.");
                    }

                    $status = 'Pending';

                    $query = "UPDATE bookings
                              SET booking_status = ?, event_status = NULL, cancelled_at = NULL
                              WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $query);

                    if (!$stmt) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt, "si", $status, $booking_id);

                } else {
                    throw new Exception("Invalid status.");
                }

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception(mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);

                if (
                    $status === 'Approved' &&
                    $selected_theme_id > 0 &&
                    $theme_counted === 0 &&
                    $old_status !== 'Approved'
                ) {
                    $incTheme = mysqli_prepare(
                        $conn,
                        "UPDATE gallery_themes
                         SET pick_count = pick_count + 1
                         WHERE id = ?"
                    );

                    if (!$incTheme) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($incTheme, "i", $selected_theme_id);
                    if (!mysqli_stmt_execute($incTheme)) {
                        throw new Exception(mysqli_stmt_error($incTheme));
                    }
                    mysqli_stmt_close($incTheme);

                    $markCounted = mysqli_prepare(
                        $conn,
                        "UPDATE bookings
                         SET theme_counted = 1
                         WHERE id = ?"
                    );

                    if (!$markCounted) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($markCounted, "i", $booking_id);
                    if (!mysqli_stmt_execute($markCounted)) {
                        throw new Exception(mysqli_stmt_error($markCounted));
                    }
                    mysqli_stmt_close($markCounted);
                }

                $paySql = "SELECT id, payment_status
                           FROM booking_payments
                           WHERE booking_id = ?
                           ORDER BY id DESC
                           LIMIT 1";
                $payStmt = mysqli_prepare($conn, $paySql);

                if (!$payStmt) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_stmt_bind_param($payStmt, "i", $booking_id);
                mysqli_stmt_execute($payStmt);
                $payRes = mysqli_stmt_get_result($payStmt);
                $paymentRow = mysqli_fetch_assoc($payRes);
                mysqli_stmt_close($payStmt);

                if ($paymentRow) {
                    $payment_row_id = (int)$paymentRow['id'];
                    $current_payment_status = trim($paymentRow['payment_status'] ?? '');

                    if ($status === 'Cancelled' && $current_payment_status === 'Paid') {
                        $updPay = mysqli_prepare(
                            $conn,
                            "UPDATE booking_payments
                             SET payment_status = 'Refund Pending',
                                 note = CONCAT(IFNULL(note, ''), ' | Admin cancelled booking. Refund pending.')
                             WHERE id = ?"
                        );

                        if (!$updPay) {
                            throw new Exception(mysqli_error($conn));
                        }

                        mysqli_stmt_bind_param($updPay, "i", $payment_row_id);
                        if (!mysqli_stmt_execute($updPay)) {
                            throw new Exception(mysqli_stmt_error($updPay));
                        }
                        mysqli_stmt_close($updPay);
                    }

                    if ($status === 'Pending' && $current_payment_status === 'Refund Pending') {
                        $updPay = mysqli_prepare(
                            $conn,
                            "UPDATE booking_payments
                             SET payment_status = 'Paid',
                                 note = CONCAT(IFNULL(note, ''), ' | Admin undo cancel. Payment restored to Paid.')
                             WHERE id = ?"
                        );

                        if (!$updPay) {
                            throw new Exception(mysqli_error($conn));
                        }

                        mysqli_stmt_bind_param($updPay, "i", $payment_row_id);
                        if (!mysqli_stmt_execute($updPay)) {
                            throw new Exception(mysqli_stmt_error($updPay));
                        }
                        mysqli_stmt_close($updPay);
                    }
                }

                if ($status === 'Approved') {
                    $type = 'booking_approved';
                    $title = 'Booking Approved';
                    $message = "Your {$event_type} booking on {$event_date} has been approved.";
                } elseif ($status === 'Cancelled') {
                    $type = 'booking_cancelled';
                    $title = 'Booking Cancelled';
                    $message = "Your {$event_type} booking on {$event_date} has been cancelled.";
                } else {
                    $type = 'booking_restored';
                    $title = 'Booking Restored';
                    $message = "Your {$event_type} booking on {$event_date} has been restored to pending.";
                }

                $link = 'profile.php?view=upcoming';

                $check_sql = "SELECT id
                              FROM notifications
                              WHERE user_id = ? AND type = ? AND title = ? AND message = ?
                              LIMIT 1";
                $stmt_check = mysqli_prepare($conn, $check_sql);

                if (!$stmt_check) {
                    throw new Exception(mysqli_error($conn));
                }

                mysqli_stmt_bind_param($stmt_check, "isss", $user_id, $type, $title, $message);
                mysqli_stmt_execute($stmt_check);
                $check_result = mysqli_stmt_get_result($stmt_check);

                if (mysqli_num_rows($check_result) == 0) {
                    $insert_notif = "INSERT INTO notifications (user_id, type, title, message, link, is_read)
                                     VALUES (?, ?, ?, ?, ?, 0)";
                    $stmt_notif = mysqli_prepare($conn, $insert_notif);

                    if (!$stmt_notif) {
                        throw new Exception(mysqli_error($conn));
                    }

                    mysqli_stmt_bind_param($stmt_notif, "issss", $user_id, $type, $title, $message, $link);
                    if (!mysqli_stmt_execute($stmt_notif)) {
                        throw new Exception(mysqli_stmt_error($stmt_notif));
                    }
                    mysqli_stmt_close($stmt_notif);
                }

                mysqli_stmt_close($stmt_check);

                mysqli_commit($conn);

                header("Location: orders.php?success=status_updated");
                exit();

            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo "Error: " . htmlspecialchars($e->getMessage());
            }

        } else {
            echo "Booking not found.";
        }
    } else {
        echo "Invalid status.";
    }
} else {
    echo "Missing booking ID or status.";
}
?>