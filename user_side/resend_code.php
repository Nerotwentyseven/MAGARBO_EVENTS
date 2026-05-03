<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';
require_once 'mailer.php';

header('Content-Type: application/json');

if (!isset($_SESSION['reset_email'])) {
    echo json_encode(["success" => false]);
    exit();
}

$email = $_SESSION['reset_email'];

$stmt = mysqli_prepare($conn, "SELECT id, firstname FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    echo json_encode(["success" => false]);
    exit();
}

$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$update = mysqli_prepare($conn, "UPDATE users SET otp_code=?, otp_expires_at=? WHERE id=?");
mysqli_stmt_bind_param($update, "ssi", $otp, $expires, $user['id']);
mysqli_stmt_execute($update);

$subject = "Magarbo Events - New Code";

$body = "
<h2>New Verification Code</h2>
<p>Hello {$user['firstname']},</p>
<h1 style='letter-spacing:5px;'>$otp</h1>
<p>This code will expire in 10 minutes.</p>
";

$send = sendMail($email, $user['firstname'], $subject, $body);

echo json_encode(["success" => $send === true]);