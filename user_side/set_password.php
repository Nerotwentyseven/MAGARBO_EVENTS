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
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <div class="password-wrapper">
                            <input type="password" id="new_password" name="new_password" required placeholder="At least 8 characters">
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('new_password', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="password-wrapper">
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repeat new password">
                            <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Save Password</button>
                </form>

                <div class="back-btn-wrapper">
                    <a href="profile.php?view=profile" class="btn-back">
                        ← Back to Profile
                    </a>
            </div>
         </div>
    </main>
<script>
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);

    if (input.type === "password") {
        input.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        input.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>
</body>
</html>