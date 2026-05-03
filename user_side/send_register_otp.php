<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';
require_once 'mailer.php';

if (
    !isset($_SESSION['reg_firstname']) ||
    !isset($_SESSION['reg_lastname']) ||
    !isset($_SESSION['reg_email']) ||
    !isset($_SESSION['reg_password'])
) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Session Expired',
        'message' => 'Please fill out the registration form again.',
        'form' => 'register'
    ];
    header("Location: login.php");
    exit();
}

$email = $_SESSION['reg_email'];
$firstname = $_SESSION['reg_firstname'];

$otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$_SESSION['reg_otp'] = $otp;
$_SESSION['reg_otp_expiry'] = time() + 600;
$_SESSION['reg_resend_available_at'] = time() + 60;

$subject = "Magarbo Events - Email Verification Code";
$body = "
    <h2>Email Verification</h2>
    <p>Hello {$firstname},</p>
    <p>Your verification code is:</p>
    <h1 style='letter-spacing: 5px;'>{$otp}</h1>
    <p>This code will expire in 10 minutes.</p>
";

$send = sendMail($email, $firstname, $subject, $body);

if ($send === true) {
    header("Location: verify_register_otp.php");
    exit();
} else {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Email Send Failed',
        'message' => 'Failed to send verification code. Please try again.',
        'form' => 'register'
    ];
    header("Location: login.php");
    exit();
}