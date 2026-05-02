<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT email, auth_provider, password_set FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    header("Location: login.php");
    exit();
}

$isGoogleOnly = (($user['auth_provider'] ?? 'local') === 'google' && (int)($user['password_set'] ?? 1) === 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Password | Magarbo Events</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <main class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <h2><?php echo $isGoogleOnly ? 'Set Password' : 'Change Password'; ?></h2>
                <p>Account: <?php echo htmlspecialchars($user['email']); ?></p>

                <form action="save_set_password.php" method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="At least 8 characters">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Repeat new password">
                    </div>

                    <button type="submit" class="btn-primary">Save Password</button>
                </form>

                <p style="margin-top:12px;">
                    <a href="profile.php?view=profile">Back to Profile</a>
                </p>
            </div>
        </div>
    </main>
</body>
</html>