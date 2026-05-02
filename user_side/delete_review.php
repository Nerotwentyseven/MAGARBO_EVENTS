<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$review_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($review_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM reviews WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $review_id, $user_id);
    mysqli_stmt_execute($stmt);
}

header("Location: review.php");
exit();