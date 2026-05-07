<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "SELECT email FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);

if (!$user) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | Magarbo Events</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
.login-card {
    max-width: 420px;
    margin: auto;
    padding: 32px 28px;
    border-radius: 18px;
}

.login-card h2 {
    font-size: 28px;
    margin-bottom: 8px;
}

.login-card p {
    font-size: 14px;
    color: #666;
    margin-bottom: 25px;
}

.form-group {
    margin-bottom: 18px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.password-wrapper {
    position: relative;
}

.password-wrapper input {
    width: 100%;
    padding: 13px 45px 13px 14px;
    border-radius: 10px;
    border: 1px solid #ddd;
    font-size: 14px;
    box-sizing: border-box;
    transition: 0.2s;
}

.password-wrapper input:focus {
    border-color: #BF9225;
    box-shadow: 0 0 0 3px rgba(191, 146, 37, 0.12);
    outline: none;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 14px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #888;
    font-size: 15px;
    transition: 0.2s;
}

.toggle-password:hover {
    color: #BF9225;
}

.btn-primary {
    width: 100%;
    margin-top: 10px;
    padding: 13px;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    background: #BF9225;
    border: none;
    color: #fff;
    cursor: pointer;
    transition: 0.2s;
}

.btn-primary:hover {
    background: #a77d1d;
}

.back-btn-wrapper {
    margin-top: 18px;
    text-align: center;
}

.btn-back {
    display: inline-block;
    padding: 10px 16px;
    border-radius: 8px;
    border: 1px solid #BF9225;
    color: #BF9225;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: 0.2s;
}

.btn-back:hover {
    background: #BF9225;
    color: #fff;
}

@media (max-width: 480px) {
    .login-card {
        padding: 24px 18px;
        border-radius: 14px;
    }

    .login-card h2 {
        font-size: 24px;
    }
}
</style>

</head>

<body>
    <main class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <h2>Change Password</h2>
                <p>Account: <?php echo htmlspecialchars($user['email']); ?></p>

                <form action="save_change_password.php" method="POST">
                    <div class="form-group">
                    <label>Current Password</label>

                    <div class="password-wrapper">
                        <input type="password"
                            name="current_password"
                            id="current_password"
                            required
                            placeholder="Enter current password">

                        <i class="fa-solid fa-eye toggle-password"
                            onclick="togglePassword('current_password', this)"></i>
                    </div>
                </div>

                    <div class="form-group">
                        <label>New Password</label>

                        <div class="password-wrapper">
                            <input type="password"
                                name="new_password"
                                id="new_password"
                                required
                                placeholder="At least 8 characters">

                            <i class="fa-solid fa-eye toggle-password"
                                onclick="togglePassword('new_password', this)"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Confirm New Password</label>

                        <div class="password-wrapper">
                            <input type="password"
                                name="confirm_password"
                                id="confirm_password"
                                required
                                placeholder="Repeat new password">

                            <i class="fa-solid fa-eye toggle-password"
                                onclick="togglePassword('confirm_password', this)"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary">Update Password</button>
                </form>

                <div class="back-btn-wrapper">
                    <a href="profile.php?view=profile" class="btn-back">
                        ← Back to Profile
                    </a>
                </div>
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