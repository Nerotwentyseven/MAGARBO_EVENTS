<?php
session_name('ADMINSESSID');
session_start();

require_once 'admin_auth.php';
require_once '../db_connection.php';
require_once '../user_side/mailer.php';

function sendAccountStatusEmail($email, $name, $status, $oldStatus = '') {
    if ($status === 'Active') {

            if ($oldStatus === 'Deactivated') {
                $subject = "Magarbo Events - Account Reactivated";
                $body = "
                    <h2>Account Reactivated</h2>
                    <p>Hello {$name},</p>
                    <p>Your Magarbo Events account has been reactivated and is now active.</p>
                    <p>You can now log in again.</p>
                    <br>
                    <p>Thank you,<br>Magarbo Events</p>
                ";
            } else {
                $subject = "Magarbo Events - Account Restored";
                $body = "
                    <h2>Account Restored</h2>
                    <p>Hello {$name},</p>
                    <p>Your Magarbo Events account has been restored and is now active.</p>
                    <p>You can now log in again.</p>
                    <br>
                    <p>Thank you,<br>Magarbo Events</p>
                ";
            }
        } elseif ($status === 'Deactivated') {
        $subject = "Magarbo Events - Account Deactivated";
        $body = "
            <h2>Account Deactivated</h2>
            <p>Hello {$name},</p>
            <p>Your Magarbo Events account has been deactivated by the admin.</p>
            <p>Please contact Magarbo Events if you think this is a mistake.</p>
            <br>
            <p>Thank you,<br>Magarbo Events</p>
        ";
    } elseif ($status === 'Deleted') {
        $subject = "Magarbo Events - Account Deleted";
        $body = "
            <h2>Account Deleted</h2>
            <p>Hello {$name},</p>
            <p>Your Magarbo Events account has been deleted by the admin.</p>
            <p>Please contact Magarbo Events if you have any questions.</p>
            <br>
            <p>Thank you,<br>Magarbo Events</p>
        ";
    } else {
        return false;
    }

    return sendMail($email, $name, $subject, $body);
}

if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = (int) $_GET['id'];
    $status = trim($_GET['status']);

    $allowed = ['Active', 'Deactivated', 'Deleted'];

    if (in_array($status, $allowed, true)) {

        $userStmt = mysqli_prepare($conn, "
            SELECT firstname, lastname, email, status
            FROM users
            WHERE id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($userStmt, "i", $id);
        mysqli_stmt_execute($userStmt);
        $userResult = mysqli_stmt_get_result($userStmt);
        $user = mysqli_fetch_assoc($userResult);

        if ($user) {
            $oldStatus = $user['status'] ?? '';
            $name = trim($user['firstname'] . ' ' . $user['lastname']);
            $email = $user['email'];

            if ($status === 'Deleted') {
                $sql = "UPDATE users SET status = 'Deleted', deleted_at = NOW() WHERE id = ?";
            } elseif ($status === 'Active') {
                $sql = "UPDATE users SET status = 'Active', deleted_at = NULL WHERE id = ?";
            } else {
                $sql = "UPDATE users SET status = 'Deactivated' WHERE id = ?";
            }

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);

            if ($oldStatus !== $status && !empty($email)) {
                sendAccountStatusEmail($email, $name, $status, $oldStatus);
            }
        }
    }
}

header("Location: user_management.php");
exit();
?>