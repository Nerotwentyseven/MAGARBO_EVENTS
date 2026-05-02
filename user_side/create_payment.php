<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['temp_booking_data']) || !is_array($_SESSION['temp_booking_data'])) {
    header("Location: index.php");
    exit();
}

$allowedMethods = ['gcash', 'maya'];
$method = strtolower(trim($_GET['method'] ?? ''));

if (!in_array($method, $allowedMethods, true)) {
    header("Location: payment.php");
    exit();
}

// minimum downpayment = 2000, user may pay higher
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 2000.00;

if ($amount < 2000) {
    $amount = 2000.00;
}

// private internal booking reference
if (!isset($_SESSION['booking_ref']) || empty($_SESSION['booking_ref'])) {
    $_SESSION['booking_ref'] = "MAG-" . date("Ymd") . "-" . rand(100, 999);
}
$booking_reference = $_SESSION['booking_ref'];

// unique payment attempt reference
$payment_attempt_reference = "PAY-" . date("YmdHis") . "-" . rand(100, 999);

// snapshot ng buong booking data
$booking_payload = json_encode($_SESSION['temp_booking_data'], JSON_UNESCAPED_UNICODE);

if ($booking_payload === false) {
    die("Failed to encode booking data.");
}

$d = $_SESSION['temp_booking_data'];
$user_id = (int)($d['user_id'] ?? ($_SESSION['user_id'] ?? 0));

if ($user_id <= 0) {
    die("Invalid user session.");
}

// reuse existing pending payment kung same booking + same method
$existingSql = "SELECT id, payment_method, payment_status
                FROM booking_payments
                WHERE booking_reference = ?
                ORDER BY id DESC
                LIMIT 1";

$existingStmt = mysqli_prepare($conn, $existingSql);

if ($existingStmt) {
    mysqli_stmt_bind_param($existingStmt, "s", $booking_reference);
    mysqli_stmt_execute($existingStmt);
    $existingRes = mysqli_stmt_get_result($existingStmt);
    $existingRow = mysqli_fetch_assoc($existingRes);
    mysqli_stmt_close($existingStmt);

    if ($existingRow) {
        $existingStatus = trim($existingRow['payment_status'] ?? '');
        $existingMethod = strtolower(trim($existingRow['payment_method'] ?? ''));

        if (
            $existingStatus === 'Pending' &&
            $existingMethod === $method &&
            (float)($existingRow['amount'] ?? 0) == $amount
        ) {
            $existingPaymentId = (int)$existingRow['id'];
            header("Location: paymongo_checkout.php?pid=" . $existingPaymentId);
            exit();
        }
    }
}

$cancelOld = mysqli_prepare($conn, "
    UPDATE booking_payments
    SET payment_status = 'Cancelled',
        note = CONCAT(IFNULL(note,''), ' | Replaced by new attempt.')
    WHERE booking_reference = ?
      AND payment_status = 'Pending'
");

if ($cancelOld) {
    mysqli_stmt_bind_param($cancelOld, "s", $booking_reference);
    mysqli_stmt_execute($cancelOld);
    mysqli_stmt_close($cancelOld);
}

$sql = "INSERT INTO booking_payments (
            booking_id,
            booking_reference,
            payment_attempt_reference,
            booking_payload,
            amount,
            payment_method,
            payment_status,
            provider,
            payment_date,
            note
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database prepare failed: " . mysqli_error($conn));
}

$booking_id = null;
$payment_status = 'Pending';
$provider = $method;
$payment_date = date('Y-m-d');
$note = 'Initial downpayment request created.';

mysqli_stmt_bind_param(
    $stmt,
    "isssdsssss",
    $booking_id,
    $booking_reference,
    $payment_attempt_reference,
    $booking_payload,
    $amount,
    $method,
    $payment_status,
    $provider,
    $payment_date,
    $note
);

if (!mysqli_stmt_execute($stmt)) {
    die("Insert payment failed: " . mysqli_stmt_error($stmt));
}

$payment_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

$_SESSION['last_payment_id'] = $payment_id;

header("Location: paymongo_checkout.php?pid=" . $payment_id);
exit();
?>