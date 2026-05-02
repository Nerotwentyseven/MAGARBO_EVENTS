<?php
session_name('ADMINSESSID');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login_admin.php");
    exit();
}

require_once '../db_connection.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$status = $_GET['status'] ?? '';

if ($id > 0 && in_array($status, ['Visible', 'Hidden'])) {
    $stmt = mysqli_prepare($conn, "UPDATE reviews SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $status, $id);
    mysqli_stmt_execute($stmt);
}

header("Location: review_management.php");
exit();