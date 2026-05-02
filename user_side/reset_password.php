<?php
session_name('USERSESSID');
session_start();

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_verified'])) {
    header("Location: forgot_password.php");
    exit();
}

$email = $_SESSION['reset_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password | Magarbo</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <main class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <h2>Create New Password</h2>
                <p>Account: <?php echo htmlspecialchars($email); ?></p>

                <form action="update_password.php" method="POST">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required placeholder="At least 8 characters">
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required placeholder="Repeat new password">
                    </div>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>