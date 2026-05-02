<?php
session_name('ADMINSESSID');
session_start();

require_once 'admin_auth.php';
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: billing.php");
    exit;
}

$bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
$amount    = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0;
$method    = trim($_POST['payment_method'] ?? '');
$date      = trim($_POST['payment_date'] ?? date('Y-m-d'));
$note      = trim($_POST['note'] ?? '');

if ($bookingId <= 0 || $amount <= 0 || $method === '' || $date === '') {
    header("Location: billing.php?error=invalid_payment");
    exit;
}

/* Kunin booking */
$bookingSql = "SELECT id, total_price, downpayment, event_status 
               FROM bookings 
               WHERE id = ? 
               LIMIT 1";

$bookingStmt = mysqli_prepare($conn, $bookingSql);
if (!$bookingStmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($bookingStmt, "i", $bookingId);
mysqli_stmt_execute($bookingStmt);
$bookingRes = mysqli_stmt_get_result($bookingStmt);
$booking = mysqli_fetch_assoc($bookingRes);
mysqli_stmt_close($bookingStmt);

if (!$booking) {
    header("Location: billing.php?error=booking_not_found");
    exit;
}

$totalPrice = (float)($booking['total_price'] ?? 0);
$baseDownpayment = (float)($booking['downpayment'] ?? 0);
$eventStatus = strtolower(trim($booking['event_status'] ?? ''));

/* Billing only works for confirmed / ongoing / completed */
if (!in_array($eventStatus, ['confirmed', 'ongoing', 'completed'], true) || $totalPrice <= 0) {
    header("Location: billing.php?error=not_billable");
    exit;
}

/* Kunin lahat ng paid payments */
$sumSql = "SELECT COALESCE(SUM(amount), 0) AS total_paid
           FROM booking_payments
           WHERE booking_id = ?
             AND payment_status = 'Paid'";

$sumStmt = mysqli_prepare($conn, $sumSql);
if (!$sumStmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($sumStmt, "i", $bookingId);
mysqli_stmt_execute($sumRes = $sumStmt);
$sumResult = mysqli_stmt_get_result($sumStmt);
$sumRow = mysqli_fetch_assoc($sumResult);
mysqli_stmt_close($sumStmt);

$totalPaidFromPayments = (float)($sumRow['total_paid'] ?? 0);

/*
    IMPORTANT:
    Huwag i-add ang downpayment + booking_payments amount.
    Kung may Paid rows sa booking_payments, yun na ang total paid.
    Kung wala pa, fallback lang sa bookings.downpayment.
*/
$currentPaid = $totalPaidFromPayments > 0 ? $totalPaidFromPayments : $baseDownpayment;
$currentBalance = max($totalPrice - $currentPaid, 0);

if ($currentBalance <= 0) {
    header("Location: billing.php?error=no_balance");
    exit;
}

/* Huwag palagpasin sa remaining balance */
if ($amount > $currentBalance) {
    $amount = $currentBalance;
}

if ($amount <= 0) {
    header("Location: billing.php?error=invalid_amount");
    exit;
}

$bookingReference = "MANUAL-" . date("YmdHis") . "-" . rand(100, 999);
$paymentAttemptReference = "PAY-" . date("YmdHis") . "-" . rand(100, 999);
$paymentStatus = "Paid";
$provider = "manual";

$insertSql = "INSERT INTO booking_payments (
                booking_id,
                booking_reference,
                payment_attempt_reference,
                amount,
                payment_method,
                payment_status,
                provider,
                payment_date,
                paid_at,
                note
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";

$insertStmt = mysqli_prepare($conn, $insertSql);
if (!$insertStmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $insertStmt,
    "issdsssss",
    $bookingId,
    $bookingReference,
    $paymentAttemptReference,
    $amount,
    $method,
    $paymentStatus,
    $provider,
    $date,
    $note
);

if (!mysqli_stmt_execute($insertStmt)) {
    die("Insert failed: " . mysqli_stmt_error($insertStmt));
}

mysqli_stmt_close($insertStmt);

header("Location: billing.php?success=payment_added");
exit;
?>