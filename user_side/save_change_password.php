<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php?view=profile");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if ($current_password === '' || $new_password === '' || $confirm_password === '') {
    echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    exit();
}

if ($new_password !== $confirm_password) {
    echo "<script>alert('New passwords do not match.'); window.history.back();</script>";
    exit();
}

if (strlen($new_password) < 8) {
    echo "<script>alert('New password must be at least 8 characters long.'); window.history.back();</script>";
    exit();
}

$stmt = mysqli_prepare($conn, "SELECT password, password_set FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    echo "<script>alert('User not found.'); window.location='login.php';</script>";
    exit();
}

if ((int)($user['password_set'] ?? 0) === 0) {
    echo "<script>alert('Please set your password first.'); window.location='set_password.php';</script>";
    exit();
}

$stored_password = $user['password'] ?? '';

if (empty($stored_password) || !password_verify($current_password, $stored_password)) {
    echo "<script>alert('Current password is incorrect.'); window.history.back();</script>";
    exit();
}

$new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

$update = mysqli_prepare($conn, "
    UPDATE users
    SET password = ?, password_set = 1,
        auth_provider = CASE
            WHEN auth_provider = 'google' THEN 'both'
            ELSE auth_provider
        END
    WHERE id = ?
");
mysqli_stmt_bind_param($update, "si", $new_hashed_password, $user_id);

if (mysqli_stmt_execute($update)) {
    echo "<script>alert('Password changed successfully.'); window.location='profile.php?view=profile';</script>";
    exit();
} else {
    echo "<script>alert('Failed to change password.'); window.history.back();</script>";
    exit();
}
?>