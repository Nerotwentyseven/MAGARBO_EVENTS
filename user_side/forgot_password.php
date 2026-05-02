<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Magarbo Events</title>
    <link rel="stylesheet" href="login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-bar">
        <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Login</a>
    </nav>

    <main class="login-wrapper">
        <div class="login-container">
            <header class="page-header">
                <h1>Magarbo Events</h1>
                <p>No worries! Enter your email and we'll send you instructions to reset your password.</p>
            </header>

            <div class="login-card">
                <h2 class="card-title">
                    <i class="fa-solid fa-key"></i> <span>Reset Password</span>
                </h2>

                <form id="forgot-form" action="process_forgot.php" method="POST">
                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="Enter your registered email" required>
                        </div>
                    </div>

                    <button type="submit" class="btn-primary" style="margin-top: 1rem;">Send Reset Link</button>
                </form>

                <p class="card-footer">
                    Remembered your password? <a href="login.php">Sign In</a>
                </p>
            </div>
        </div>
    </main>

    <style>
        /* small adjustment lang kaya digdi ko na ilaag */
        .page-header p {
            max-width: 400px;
            margin: 0.5rem auto 0;
            line-height: 1.5;
            color: #64748b;
        }
        .card-footer {
            text-align: center;
            margin-top: 16px;
            font-size: 13px;
            color: #888;
        }
    </style>
</body>
</html>