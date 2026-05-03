<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    exit();
}

$user_id = $_SESSION['user_id'];

$update = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = mysqli_prepare($conn, $update);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);

header("Location: " . $_GET['redirect']);
exit();