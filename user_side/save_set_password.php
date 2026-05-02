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
$new_pass = $_POST['new_password'] ?? '';
$confirm_pass = $_POST['confirm_password'] ?? '';

if ($new_pass === '' || $confirm_pass === '') {
    echo "<script>alert('Please fill in all fields.'); window.history.back();</script>";
    exit();
}

if ($new_pass !== $confirm_pass) {
    echo "<script>alert('Passwords do not match.'); window.history.back();</script>";
    exit();
}

if (strlen($new_pass) < 8) {
    echo "<script>alert('Password must be at least 8 characters long.'); window.history.back();</script>";
    exit();
}

$hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

$stmt = mysqli_prepare($conn, "
    UPDATE users
    SET password = ?, password_set = 1,
        auth_provider = CASE
            WHEN auth_provider = 'google' THEN 'both'
            ELSE auth_provider
        END
    WHERE id = ?
");
mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

if (mysqli_stmt_execute($stmt)) {
    echo "<script>alert('Password saved successfully.'); window.location='profile.php?view=profile';</script>";
    exit();
} else {
    echo "<script>alert('Failed to save password.'); window.history.back();</script>";
    exit();
}
?>