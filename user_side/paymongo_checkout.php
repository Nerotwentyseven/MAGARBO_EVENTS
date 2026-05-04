<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

$payment_id = (int)($_GET['pid'] ?? 0);

if ($payment_id <= 0) {
    die("Invalid payment ID.");
}

$stmt = mysqli_prepare($conn, "SELECT * FROM booking_payments WHERE id = ? LIMIT 1");
if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$payment) {
    die("Payment record not found.");
}

$selected_method = strtolower(trim($payment['payment_method'] ?? ''));

if (!in_array($selected_method, ['gcash', 'maya'], true)) {
    die("Invalid payment method.");
}

$paymongo_method = ($selected_method === 'maya') ? 'paymaya' : 'gcash';

$baseAmount = (float)($payment['amount'] ?? 0);
$processingFee = (float)($payment['processing_fee'] ?? 0);
$amountCharged = (float)($payment['amount_charged'] ?? 0);

$amount = $amountCharged > 0 ? $amountCharged : ($baseAmount + $processingFee);

if ($amount <= 0) {
    $amount = $baseAmount;
}

$booking_reference = trim($payment['booking_reference'] ?? '');
$payment_attempt_reference = trim($payment['payment_attempt_reference'] ?? '');

if ($amount <= 0 || $booking_reference === '') {
    die("Invalid payment details.");
}

$user_id = (int)($_SESSION['temp_booking_data']['user_id'] ?? 0);

$customerName = "Customer";
$customerEmail = "noemail@example.com";
$customerPhone = "";

if ($user_id > 0) {
    $userStmt = mysqli_prepare($conn, "SELECT firstname, lastname, email, phone FROM users WHERE id = ? LIMIT 1");
    if ($userStmt) {
        mysqli_stmt_bind_param($userStmt, "i", $user_id);
        mysqli_stmt_execute($userStmt);
        $userRes = mysqli_stmt_get_result($userStmt);
        $userRow = mysqli_fetch_assoc($userRes);
        mysqli_stmt_close($userStmt);

        if ($userRow) {
            $first = trim($userRow['firstname'] ?? '');
            $last  = trim($userRow['lastname'] ?? '');
            $customerName = trim($first . ' ' . $last);
            $customerEmail = trim($userRow['email'] ?? '');
            $customerPhone = trim($userRow['phone'] ?? '');

            if ($customerName === '') {
                $customerName = "Customer";
            }

            if ($customerEmail === '') {
                $customerEmail = "noemail@example.com";
            }
        }
    }
}

$secretKey = 'sk_test_vjMC8YA7ph3KubZ6D4Pdm5sx';

$host = $_SERVER['HTTP_HOST']; //pwede halion pag may domain na
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'; //pwede man

$baseUrl = $scheme . '://' . $host . '/magarbo_system_current/user_side'; //temporary muna

$amountInCentavos = (int) round($amount * 100);

$payload = [
    "data" => [
        "attributes" => [
            "billing" => [
                "name" => $customerName,
                "email" => $customerEmail,
                "phone" => $customerPhone
            ],
            "send_email_receipt" => false,
            "show_description" => true,
            "show_line_items" => true,
            "description" => "Booking downpayment",
            "line_items" => [
                [
                    "currency" => "PHP",
                    "amount" => $amountInCentavos,
                    "name" => "Booking Downpayment + Processing Fee",
                    "quantity" => 1,
                    "description" => "Reservation downpayment with payment processing fee"
                ]
            ],
            "payment_method_types" => [$paymongo_method],
            "success_url" => $baseUrl . "/payment_success.php?pid=" . $payment_id,
            "cancel_url"  => $baseUrl . "/payment_cancel.php?pid=" . $payment_id,
            "metadata" => [
                "payment_id" => $payment_id,
                "payment_attempt_reference" => $payment_attempt_reference,
                "payment_method" => $selected_method
            ]
        ]
    ]
];

$ch = curl_init("https://api.paymongo.com/v1/checkout_sessions");

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
    die("cURL error: " . htmlspecialchars($curlError));
}

$result = json_decode($response, true);

if ($httpCode < 200 || $httpCode >= 300) {
    echo "<pre>";
    echo "PayMongo API error:\n";
    print_r($result);
    echo "</pre>";
    exit();
}

$checkoutSessionId = $result['data']['id'] ?? null;
$checkoutUrl = $result['data']['attributes']['checkout_url'] ?? null;

if (!$checkoutUrl) {
    echo "<pre>";
    echo "No checkout URL returned:\n";
    print_r($result);
    echo "</pre>";
    exit();
}

$provider = 'paymongo';
$provider_reference = $checkoutSessionId;

$upd = mysqli_prepare($conn, "
    UPDATE booking_payments
    SET provider = ?, provider_reference = ?
    WHERE id = ?
");

if ($upd) {
    mysqli_stmt_bind_param($upd, "ssi", $provider, $provider_reference, $payment_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
}

header("Location: " . $checkoutUrl);
exit();
?>