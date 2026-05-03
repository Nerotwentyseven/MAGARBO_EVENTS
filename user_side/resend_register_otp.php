<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';
require_once 'mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);
    exit();
}

if (!isset($_SESSION['reg_email'], $_SESSION['reg_firstname'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Registration session expired. Please register again.'
    ]);
    exit();
}

$email = $_SESSION['reg_email'];
$firstname = $_SESSION['reg_firstname'];

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$_SESSION['reg_otp'] = $otp;
$_SESSION['reg_otp_expiry'] = time() + 600; // 10 minutes
$_SESSION['reg_resend_available_at'] = time() + 60; // resend allowed after 60s

$subject = "Magarbo Events - New Email Verification Code";
$body = "
    <h2>Email Verification</h2>
    <p>Hello {$firstname},</p>
    <p>Your new verification code is:</p>
    <h1 style='letter-spacing: 5px;'>{$otp}</h1>
    <p>This code will expire in 10 minutes.</p>
";

$send = sendMail($email, $firstname, $subject, $body);

if ($send === true) {
    echo json_encode([
        'success' => true
    ]);
    exit();
}

echo json_encode([
    'success' => false,
    'message' => 'Failed to send a new code.'
]);
exit();