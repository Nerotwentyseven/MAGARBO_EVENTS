<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['reset_email'], $_SESSION['reset_verified'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];

$new = $_POST['new_password'];
$confirm = $_POST['confirm_password'];

if ($new !== $confirm) {
    echo "<script>alert('Passwords do not match'); window.history.back();</script>";
    exit();
}

$hash = password_hash($new, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "
UPDATE users 
SET password=?, password_set=1,
otp_code=NULL, otp_expires_at=NULL
WHERE email=?
");
mysqli_stmt_bind_param($stmt, "ss", $hash, $email);
mysqli_stmt_execute($stmt);

unset($_SESSION['reset_email'], $_SESSION['reset_verified']);

echo "<script>alert('Password updated!'); window.location='login.php';</script>";