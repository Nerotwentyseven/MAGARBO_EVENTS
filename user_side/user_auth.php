<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('USERSESSID');
    session_start();
}

require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$userId = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) === 0) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$user = mysqli_fetch_assoc($result);
$status = $user['status'] ?? 'Active';

if ($status !== 'Active') {
    session_unset();
    session_destroy();

    session_name('USERSESSID');
    session_start();

    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => $status === 'Deleted' ? 'Account Deleted' : 'Account Deactivated',
        'message' => $status === 'Deleted'
            ? 'Your account has been deleted. Please contact the admin.'
            : 'Your account has been deactivated. Please contact the admin.',
        'form' => 'login'
    ];

    header("Location: login.php");
    exit();
}
?>