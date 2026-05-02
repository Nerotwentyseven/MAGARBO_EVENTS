<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'], $_SESSION['new_phone'])) exit();

$otp = $_POST['otp'] ?? '';

$stmt = mysqli_prepare($conn, "
SELECT id FROM users 
WHERE id=? AND phone_otp=? AND phone_otp_expires > NOW()
");
mysqli_stmt_bind_param($stmt, "is", $_SESSION['user_id'], $otp);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

if (mysqli_fetch_assoc($res)) {

    $new_phone = $_SESSION['new_phone'];

    $update = mysqli_prepare($conn, "
    UPDATE users 
    SET phone=?, phone_otp=NULL, phone_otp_expires=NULL 
    WHERE id=?
    ");
    mysqli_stmt_bind_param($update, "si", $new_phone, $_SESSION['user_id']);
    mysqli_stmt_execute($update);

    unset($_SESSION['new_phone']);

    echo "<script>
    alert('Phone updated successfully!');
    window.location='profile.php';
    </script>";

} else {
    echo "<script>alert('Invalid or expired code'); window.history.back();</script>";
}