<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $sql = "DELETE FROM packages WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
}

header("Location: package_management.php");
exit();
?>