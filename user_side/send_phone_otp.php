<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) exit();

$new_phone = trim($_POST['new_phone'] ?? '');

if ($new_phone === '') {
    header("Location: change_phone.php");
    exit();
}

$check = mysqli_prepare($conn, "SELECT id FROM users WHERE phone=?");
mysqli_stmt_bind_param($check, "s", $new_phone);
mysqli_stmt_execute($check);
$res = mysqli_stmt_get_result($check);

if (mysqli_fetch_assoc($res)) {
    echo "<script>alert('Phone already used'); window.history.back();</script>";
    exit();
}

$otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$stmt = mysqli_prepare($conn, "
UPDATE users 
SET phone_otp=?, phone_otp_expires=? 
WHERE id=?
");
mysqli_stmt_bind_param($stmt, "ssi", $otp, $expires, $_SESSION['user_id']);
mysqli_stmt_execute($stmt);

$_SESSION['new_phone'] = $new_phone;

echo "<script>
alert('Your OTP code is: $otp'); 
window.location='verify_phone_otp.php';
</script>";