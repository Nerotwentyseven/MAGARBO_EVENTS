<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

$PAYMONGO_SECRET_KEY = 'sk_test_vjMC8YA7ph3KubZ6D4Pdm5sx';

function normalizePhonePH($phone) {
    $digits = preg_replace('/\D/', '', $phone);

    if (strlen($digits) === 11 && substr($digits, 0, 2) === '09') {
        return '63' . substr($digits, 1);
    }

    if (strlen($digits) === 12 && substr($digits, 0, 2) === '63') {
        return $digits;
    }

    return '';
}

function sendBookingSms($phone, $message) {
    $semaphoreApiKey = '6f4ef2e2ec634fad72c59a89950c7f82';

    $number = normalizePhonePH($phone);

    if ($number === '') {
        return false;
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://api.semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'apikey' => $semaphoreApiKey,
        'number' => $number,
        'message' => $message,
        'sendername' => 'MAGARBO'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response !== false;
}

function createPayMongoRefund($secretKey, $paymentId, $amount)
{
    $amountCentavos = (int) round($amount * 100);

    if ($paymentId === '' || $amountCentavos <= 0) {
        return [
            'success' => false,
            'message' => 'Invalid payment ID or refund amount.'
        ];
    }

    $payload = [
        "data" => [
            "attributes" => [
                "amount" => $amountCentavos,
                "payment_id" => $paymentId,
                "reason" => "requested_by_customer",
                "notes" => "Booking cancelled by admin"
            ]
        ]
    ];

    $ch = curl_init("https://api.paymongo.com/v1/refunds");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "content-type: application/json",
            "authorization: Basic " . base64_encode($secretKey . ":")
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return [
            'success' => false,
            'message' => $curlError
        ];
    }

    $result = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        return [
            'success' => false,
            'message' => $result['errors'][0]['detail'] ?? 'Refund request failed.'
        ];
    }

    return [
        'success' => true,
        'refund_id' => $result['data']['id'] ?? '',
        'refund_status' => $result['data']['attributes']['status'] ?? 'pending'
    ];
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $booking_id = (int) $_GET['id'];
    $status = trim($_GET['status']);

    $allowed_status = ['Approved', 'Cancelled'];

    if (in_array($status, $allowed_status, true)) {

        $get_booking_sql = "SELECT 
                                b.id, b.user_id, b.event_type, b.event_date, b.selected_theme_id, 
                                b.theme_counted, b.booking_status, b.cancelled_at,
                                u.firstname, u.lastname, u.phone
                            FROM bookings b
                            INNER JOIN users u ON b.user_id = u.id
                            WHERE b.id = ?";
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
            $user_phone = trim($booking['phone'] ?? '');
            $customer_name = trim(($booking['firstname'] ?? '') . ' ' . ($booking['lastname'] ?? ''));

            if ($status === 'Approved' || $status === 'UndoCancel') {
                $checkDate = $booking['event_date'];
                $checkSql = "SELECT COUNT(*) as total FROM bookings 
                            WHERE event_date = ? 
                            AND booking_status = 'Approved' 
                            AND id != ?";
                $checkStmt = mysqli_prepare($conn, $checkSql);
                mysqli_stmt_bind_param($checkStmt, "si", $checkDate, $booking_id);
                mysqli_stmt_execute($checkStmt);
                $checkRes = mysqli_stmt_get_result($checkStmt);
                $checkRow = mysqli_fetch_assoc($checkRes);
                mysqli_stmt_close($checkStmt);

                if ((int)$checkRow['total'] >= 2) {
                    header("Location: orders.php?error=date_full");
                    exit();
                }
            }

            mysqli_begin_transaction($conn);

            try {
                if ($status === 'Approved') {
                    $event_status = 'draft';

                    $query = "UPDATE bookings
                        SET booking_status = ?, event_status = ?, cancelled_at = NULL, created_at = NOW()
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

                $paySql = "SELECT 
                id, payment_status, amount, amount_charged,
                provider_transaction_id, refund_id
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
    $paymongoPaymentId = trim($paymentRow['provider_transaction_id'] ?? '');
    $existingRefundId = trim($paymentRow['refund_id'] ?? '');

    if ($status === 'Cancelled' && $current_payment_status === 'Paid') {
        if ($existingRefundId !== '') {
            throw new Exception("Refund already exists for this booking.");
        }

        if ($paymongoPaymentId === '') {
            throw new Exception("Missing PayMongo payment ID. Check webhook first.");
        }

        $refundAmount = (float)($paymentRow['amount_charged'] ?? 0);

        if ($refundAmount <= 0) {
            $refundAmount = (float)($paymentRow['amount'] ?? 0);
        }

        $refundResult = createPayMongoRefund(
            $PAYMONGO_SECRET_KEY,
            $paymongoPaymentId,
            $refundAmount
        );

        if (!$refundResult['success']) {
            throw new Exception("Refund failed: " . $refundResult['message']);
        }

        $refundId = $refundResult['refund_id'];
        $refundStatus = $refundResult['refund_status'];
        $newPaymentStatus = ($refundStatus === 'succeeded') ? 'Refunded' : 'Refund Pending';

        $updPay = mysqli_prepare(
            $conn,
            "UPDATE booking_payments
             SET payment_status = ?,
                 refund_id = ?,
                 refund_status = ?,
                 refund_amount = ?,
                 refunded_at = IF(? = 'succeeded', NOW(), refunded_at),
                 note = CONCAT(IFNULL(note, ''), ' | Admin cancelled booking. PayMongo refund created.')
             WHERE id = ?"
        );

        if (!$updPay) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param(
            $updPay,
            "sssdsi",
            $newPaymentStatus,
            $refundId,
            $refundStatus,
            $refundAmount,
            $refundStatus,
            $payment_row_id
        );

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
                    $smsMessage = "Hi {$customer_name}, your {$event_type} booking on {$event_date} has been approved by Magarbo Events. Thank you!";
                } elseif ($status === 'Cancelled') {
                    $type = 'booking_cancelled';
                    $title = 'Booking Cancelled';
                    $message = "Your {$event_type} booking on {$event_date} has been cancelled. Your payment refund has been processed.";
                    $smsMessage = "Hi {$customer_name}, your {$event_type} booking on {$event_date} has been cancelled by Magarbo Events. Your refund has been processed.";
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

                if ($user_phone !== '' && $old_status !== $status) {
                    sendBookingSms($user_phone, $smsMessage);
                }

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