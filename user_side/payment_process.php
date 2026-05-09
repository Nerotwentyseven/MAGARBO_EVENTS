<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

$success = false;
$error_message = '';

$payment_id = (int)($_GET['pid'] ?? 0);
$method = strtolower(trim($_GET['method'] ?? ''));

if ($payment_id <= 0) {
    $error_message = "Invalid payment reference.";
}

if (empty($error_message) && !in_array($method, ['gcash', 'maya'], true)) {
    $error_message = "Invalid payment method.";
}

$payment = null;

if (empty($error_message)) {
    $paymentSql = "SELECT * FROM booking_payments WHERE id = ? LIMIT 1";
    $paymentStmt = mysqli_prepare($conn, $paymentSql);

    if (!$paymentStmt) {
        $error_message = mysqli_error($conn);
    } else {
        mysqli_stmt_bind_param($paymentStmt, "i", $payment_id);
        mysqli_stmt_execute($paymentStmt);
        $paymentRes = mysqli_stmt_get_result($paymentStmt);
        $payment = mysqli_fetch_assoc($paymentRes);
        mysqli_stmt_close($paymentStmt);

        if (!$payment) {
            $error_message = "Payment record not found.";
        }
    }
}

if (empty($error_message)) {
    $paymentMethod = strtolower(trim($payment['payment_method'] ?? ''));
    $paymentStatus = trim($payment['payment_status'] ?? '');

    if ($paymentMethod !== $method) {
        $error_message = "Payment method mismatch.";
    }

    if ($paymentStatus === 'Paid' && !empty($payment['booking_id'])) {
        $success = true;
    } elseif (!in_array($paymentStatus, ['Pending', 'Paid'], true)) {
        $error_message = "This payment is no longer valid.";
    }
}

if (empty($error_message) && !$success) {
    mysqli_begin_transaction($conn);

    try {
        $payloadJson = $payment['booking_payload'] ?? '';
        $d = json_decode($payloadJson, true);

        if (!is_array($d)) {
            throw new Exception("Booking payload is missing or invalid.");
        }

        $status = "Pending";

        $user_id        = (int)($d['user_id'] ?? 0);
        $event_type     = trim($d['event_type'] ?? '');
        $service_type   = trim($d['service_type'] ?? '');
        $package_name   = trim($d['package_name'] ?? '');
        $menu_selection = $d['menu_selection'] ?? '';
        $event_date     = trim($d['event_date'] ?? '');
        $raw_time = trim($d['event_time'] ?? '');

        if ($raw_time !== '' && $raw_time !== 'N/A') {
            $normalized = strtoupper(preg_replace('/\s+/', ' ', $raw_time));

            $t_obj = DateTime::createFromFormat('g:i A', $normalized)
                ?: DateTime::createFromFormat('h:i A', $normalized)
                ?: DateTime::createFromFormat('G:i', $normalized)
                ?: DateTime::createFromFormat('H:i', $normalized);

            $event_time = $t_obj ? $t_obj->format('g:i A') : $raw_time;
        } else {
            $event_time = 'N/A';
        }
        $venue          = trim($d['venue'] ?? '');
        $total_price    = (float)($d['total_price'] ?? 0);
        $request        = trim($d['request'] ?? '');
        $selected_theme_id = !empty($d['selected_theme_id']) ? (int)$d['selected_theme_id'] : null;
        $selected_theme = trim($d['selected_theme'] ?? '');
        $religion       = trim($d['religion'] ?? '');
        $suggestion_text = trim($d['suggestion_text'] ?? '');

        if ($user_id <= 0) {
            throw new Exception("Invalid booking user.");
        }

        if ($event_type === '' || $event_date === '' || $event_time === '') {
            throw new Exception("Incomplete booking details.");
        }

        $sql = "INSERT INTO bookings (
            user_id, event_type, service_type, package_name, menu_selection,
            event_date, event_time, venue, total_price, request,
            selected_theme_id, selected_theme, religion, booking_status, suggestion_text, downpayment
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            throw new Exception(mysqli_error($conn));
        }

        $downpayment_amount = (float)($payment['amount'] ?? 0);

mysqli_stmt_bind_param(
            $stmt,
            "isssssssdsissssd",
            $user_id,
            $event_type,
            $service_type,
            $package_name,
            $menu_selection,
            $event_date,
            $event_time,
            $venue,
            $total_price,
            $request,
            $selected_theme_id,
            $selected_theme,
            $religion,
            $status,
            $suggestion_text,
            $downpayment_amount
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }

        $newBookingId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $fixDownpaymentStmt = mysqli_prepare($conn, "
            UPDATE bookings
            SET downpayment = ?
            WHERE id = ?
        ");
        if (!$fixDownpaymentStmt) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($fixDownpaymentStmt, "di", $downpayment_amount, $newBookingId);

        if (!mysqli_stmt_execute($fixDownpaymentStmt)) {
            throw new Exception(mysqli_stmt_error($fixDownpaymentStmt));
        }

        mysqli_stmt_close($fixDownpaymentStmt);

$dateKey = !empty($event_date) ? date('Ymd', strtotime($event_date)) : date('Ymd');

$rebuildSql = "
    SELECT 
        b.id,
        u.firstname
    FROM bookings b
    JOIN users u ON u.id = b.user_id
    WHERE b.event_date = ?
    ORDER BY 
        b.created_at ASC,
        b.id ASC
";

$rebuildStmt = mysqli_prepare($conn, $rebuildSql);
if (!$rebuildStmt) {
    throw new Exception(mysqli_error($conn));
}

mysqli_stmt_bind_param($rebuildStmt, "s", $event_date);
mysqli_stmt_execute($rebuildStmt);
$rebuildRes = mysqli_stmt_get_result($rebuildStmt);

$sequence = 1;

while ($bookingRow = mysqli_fetch_assoc($rebuildRes)) {
    $nameSource = trim($bookingRow['firstname'] ?? '');
    if ($nameSource === '') {
        $nameSource = 'ORD';
    }

    $cleanName = preg_replace('/[^a-zA-Z]/', '', $nameSource);
    $prefix = strtoupper(substr($cleanName, 0, 3));
    if ($prefix === '') {
        $prefix = 'ORD';
    }

    $baseId = $prefix . $dateKey;
    $finalId = ($sequence === 1) ? $baseId : $baseId . '-' . $sequence;

    $upd = mysqli_prepare($conn, "UPDATE bookings SET display_order_id = ? WHERE id = ?");
    if (!$upd) {
        throw new Exception(mysqli_error($conn));
    }

    $bookingIdToUpdate = (int)$bookingRow['id'];
    mysqli_stmt_bind_param($upd, "si", $finalId, $bookingIdToUpdate);

    if (!mysqli_stmt_execute($upd)) {
        throw new Exception(mysqli_stmt_error($upd));
    }

    mysqli_stmt_close($upd);
    $sequence++;
}

mysqli_stmt_close($rebuildStmt);

        $provider = 'paymongo';

        $updateSql = "UPDATE booking_payments
                      SET booking_id = ?,
                          payment_status = 'Paid',
                          payment_date = CURDATE(),
                          paid_at = NOW(),
                          provider = ?,
                          note = CONCAT(IFNULL(note, ''), ' | Payment confirmed and booking created.')
                      WHERE id = ?";

        $updateStmt = mysqli_prepare($conn, $updateSql);

        if (!$updateStmt) {
            throw new Exception(mysqli_error($conn));
        }

        mysqli_stmt_bind_param($updateStmt, "isi", $newBookingId, $provider, $payment_id);

        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception(mysqli_stmt_error($updateStmt));
        }

        mysqli_stmt_close($updateStmt);

        mysqli_commit($conn);

        $success = true;

        unset($_SESSION['temp_booking_data']);
        unset($_SESSION['booking_ref']);
        unset($_SESSION['last_payment_id']);

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="payment_process.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="success-card">
        <?php if ($success): ?>
            <script>
                localStorage.removeItem('magarbo_booking_draft_v1');
            </script>
            <div class="icon"><i class="fa-solid fa-circle-check"></i></div>
            <h1>Booking Received!</h1>
            <p>Your down payment has been processed. Our admin will review your booking shortly. Check your profile for updates.</p>
            <a href="profile.php" class="btn-profile">View My Bookings</a>
        <?php else: ?>
            <div class="icon" style="color: #e74c3c;"><i class="fa-solid fa-circle-xmark"></i></div>
            <h1>System Error</h1>
            <p>Something went wrong while saving your booking.</p>

            <?php if (!empty($error_message)): ?>
                <p style="margin-top:10px; color:#999; font-size:13px;">
                    <?php echo htmlspecialchars($error_message); ?>
                </p>
            <?php endif; ?>

            <a href="payment.php" class="btn-profile" style="background: #333;">Back to Payment</a>
        <?php endif; ?>
    </div>
    
</body>
</html>