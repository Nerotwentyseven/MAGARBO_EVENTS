<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('ADMINSESSID');
    session_start();
}

if (
    !isset($_SESSION['admin_logged_in']) ||
    $_SESSION['admin_logged_in'] !== true ||
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['admin_email'])
) {
    header("Location: login_admin.php");
    exit();
}
?>