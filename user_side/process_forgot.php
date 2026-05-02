<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';
require_once 'mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: forgot_password.php");
    exit();
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '') {
    header("Location: forgot_password.php?error=empty");
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT id, firstname FROM users WHERE email = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    header("Location: forgot_password.php?error=not_found");
    exit();
}

// 🔥 Generate OTP
$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Save sa DB
$update = mysqli_prepare($conn, "UPDATE users SET otp_code=?, otp_expires_at=? WHERE id=?");
mysqli_stmt_bind_param($update, "ssi", $otp, $expires, $user['id']);
mysqli_stmt_execute($update);

// Send email
$subject = "Magarbo Events - Password Reset Code";

$body = "
<h2>Password Reset</h2>
<p>Hello {$user['firstname']},</p>
<p>Your verification code is:</p>
<h1 style='letter-spacing:5px;'>$otp</h1>
<p>This code will expire in 10 minutes.</p>
";

$send = sendMail($email, $user['firstname'], $subject, $body);

if ($send === true) {
    $_SESSION['reset_email'] = $email;
    header("Location: verify_reset_code.php");
    exit();
} else {
    echo "Email error: " . $send;
}