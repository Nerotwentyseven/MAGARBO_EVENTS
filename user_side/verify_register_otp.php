<?php
session_name('USERSESSID');
session_start();

if (!isset($_SESSION['reg_email'])) {
    header("Location: login.php");
    exit();
}

$alert = $_SESSION['alert'] ?? null;
if ($alert && ($alert['form'] ?? '') !== 'register') {
    $alert = null;
}

$remainingSeconds = 0;
if (isset($_SESSION['reg_resend_available_at'])) {
    $remainingSeconds = max(0, $_SESSION['reg_resend_available_at'] - time());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email | Magarbo Events</title>
    <link rel="stylesheet" href="login.css">
    <style>
        h2{
            text-align: center;
            margin-bottom: 8px;
        }

        .otp-input {
            width: 100%;
            padding: 12px;
            font-size: 20px;
            text-align: center;
            letter-spacing: 8px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            transition: all 0.2s ease;
        }

        .otp-input:focus {
            border-color: #BF9225;
            box-shadow: 0 0 0 2px rgba(191, 146, 37, 0.15);
        }

        .otp-note {
            margin-top: 10px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }

        .resend-wrap {
            margin-top: 14px;
            text-align: center;
        }

        #resendBtn {
            background: none;
            border: none;
            color: #aaa;
            font-size: 13px;
            font-weight: 500;
            cursor: not-allowed;
        }

        #resendBtn.enabled {
            color: #BF9225;
            cursor: pointer;
        }

        #resendBtn.enabled:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            margin-top: 16px;
        }

        .otp-email {
            text-align: center;
            margin-bottom: 14px;
            line-height: 1.5;
        }

        .otp-status {
            margin-top: 10px;
            text-align: center;
            font-size: 13px;
            color: #BF9225;
            min-height: 18px;
        }

        .otp-alert {
            margin-bottom: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.4;
        }

        .otp-alert.error {
            background: #fff1f1;
            color: #b42318;
            border: 1px solid #f4c7c7;
        }

        .otp-alert.success {
            background: #f0fff4;
            color: #166534;
            border: 1px solid #b7ebc6;
        }
    </style>
</head>
<body>
    <main class="login-wrapper">
        <div class="login-container">
            <div class="login-card">
                <h2>Verify Your Email</h2>

                <p class="otp-email">
                    We sent a verification code to <br>
                    <strong><?php echo htmlspecialchars($_SESSION['reg_email']); ?></strong>
                </p>

                <?php if ($alert): ?>
                    <div class="otp-alert <?php echo htmlspecialchars($alert['type']); ?>">
                        <strong><?php echo htmlspecialchars($alert['title']); ?></strong><br>
                        <?php echo htmlspecialchars($alert['message']); ?>
                    </div>
                <?php endif; ?>

                <form action="complete_register.php" method="POST">
                    <div class="form-group">
                        <label>Verification Code</label>
                        <input
                            type="text"
                            name="otp_code"
                            maxlength="6"
                            required
                            class="otp-input"
                            placeholder="------"
                            oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                        >
                    </div>

                    <button type="submit" class="btn-primary">Verify & Create Account</button>
                </form>

                <div class="otp-note">
                    Code expires in 10 minutes
                </div>

                <div class="resend-wrap">
                    Didn’t receive the code?
                    <button id="resendBtn" type="button">Resend</button>
                </div>

                <div id="otpStatus" class="otp-status"></div>
            </div>
        </div>
    </main>

    <script>
        let countdown = <?php echo (int)$remainingSeconds; ?>;
        const resendBtn = document.getElementById('resendBtn');
        const otpStatus = document.getElementById('otpStatus');
        let resendTimer = null;

        function enableResend() {
            resendBtn.disabled = false;
            resendBtn.classList.add('enabled');
            resendBtn.textContent = 'Resend Code';
        }

        function disableResend(seconds) {
            resendBtn.disabled = true;
            resendBtn.classList.remove('enabled');
            resendBtn.textContent = `Resend (${seconds}s)`;
        }

        function startResendCountdown(seconds) {
            clearInterval(resendTimer);

            countdown = seconds;

            if (countdown <= 0) {
                enableResend();
                return;
            }

            disableResend(countdown);

            resendTimer = setInterval(() => {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(resendTimer);
                    enableResend();
                } else {
                    disableResend(countdown);
                }
            }, 1000);
        }

        startResendCountdown(countdown);

        resendBtn.addEventListener('click', function () {
            if (resendBtn.disabled) return;

            resendBtn.disabled = true;
            resendBtn.classList.remove('enabled');
            resendBtn.textContent = 'Sending...';
            otpStatus.textContent = '';

            fetch('resend_register_otp.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    otpStatus.textContent = 'A new code has been sent.';
                    startResendCountdown(60);
                } else {
                    otpStatus.textContent = data.message || 'Failed to resend code.';
                    enableResend();
                }
            })
            .catch(() => {
                otpStatus.textContent = 'Something went wrong while resending.';
                enableResend();
            });
        });
    </script>
</body>
</html>
<?php unset($_SESSION['alert']); ?>