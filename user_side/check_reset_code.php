<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
$otp = trim($_POST['otp_code']);

$stmt = mysqli_prepare($conn, "
    SELECT id FROM users 
    WHERE email=? AND otp_code=? AND otp_expires_at > NOW()
");
mysqli_stmt_bind_param($stmt, "ss", $email, $otp);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (mysqli_fetch_assoc($res)) {
    $_SESSION['reset_verified'] = true;
    header("Location: reset_password.php");
    exit();
} else {
    echo "<script>alert('Invalid or expired code'); window.history.back();</script>";
}