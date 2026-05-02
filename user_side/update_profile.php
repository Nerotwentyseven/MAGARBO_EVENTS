<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');

    if ($firstname === '' || $lastname === '') {
        header("Location: profile.php?view=profile&error=missing_fields");
        exit();
    }

    $update_sql = "UPDATE users 
                   SET firstname = ?, lastname = ?
                   WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($stmt_update, "ssi", $firstname, $lastname, $user_id);
    mysqli_stmt_execute($stmt_update);

    $_SESSION['full_name'] = trim($firstname . ' ' . $lastname);
}

header("Location: profile.php?view=profile&success=updated");
exit();
?>