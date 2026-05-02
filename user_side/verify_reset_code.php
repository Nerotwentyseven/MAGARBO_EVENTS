<?php
session_name('USERSESSID');
session_start();

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | Magarbo Events</title>
    <link rel="stylesheet" href="login.css">

    <style>
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
        
        h2{
            text-align: center;
            margin-bottom: 8px;
        }

        .otp-input:focus {
            border-color: #BF9225;
            box-shadow: 0 0 0 2px rgba(191,146,37,0.2);
        }

        .otp-note {
            margin-top: 10px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }

        .resend-link {
            display: block;
            margin-top: 12px;
            font-size: 13px;
            text-align: center;
        }

        .resend-link a {
            color: #BF9225;
            text-decoration: none;
            font-weight: 500;
        }

        .resend-link a:hover {
            text-decoration: underline;
        }

        .btn-primary {
            width: 100%;
            margin-top: 16px;
        }

        #resendBtn {
            background: none;
            border: none;
            color: #aaa;
            font-size: 13px;
            cursor: not-allowed;
            font-weight: 500;
        }

        #resendBtn.enabled {
            color: #BF9225;
            cursor: pointer;
        }

        #resendBtn.enabled:hover {
            text-decoration: underline;
        }
        
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-container">
        <div class="login-card">

            <h2>Enter Verification Code</h2>
            <p style="text-align:center; margin-bottom:15px;">
                We sent a 6-digit code to <br>
                <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
            </p>

            <form action="check_reset_code.php" method="POST">
                <input 
                    type="text" 
                    name="otp_code" 
                    maxlength="6" 
                    required 
                    class="otp-input"
                    placeholder="------"
                >

                <button type="submit" class="btn-primary">Verify Code</button>
            </form>

            <div class="otp-note">
                Code expires in 10 minutes
            </div>

            <div class="resend-link">
                Didn’t receive the code?
                <button id="resendBtn" disabled>Resend (60s)</button>
            </div>

        </div>
    </div>
</div>

<script>
let countdown = 60;
const btn = document.getElementById('resendBtn');

const timer = setInterval(() => {
    countdown--;
    btn.textContent = `Resend (${countdown}s)`;

    if (countdown <= 0) {
        clearInterval(timer);
        btn.textContent = "Resend Code";
        btn.disabled = false;
        btn.classList.add("enabled");
    }
}, 1000);

btn.addEventListener("click", function () {
    if (btn.disabled) return;

    btn.disabled = true;
    btn.classList.remove("enabled");
    btn.textContent = "Sending...";

    fetch("resend_code.php", {
        method: "POST"
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            countdown = 60;
            btn.textContent = `Resend (${countdown}s)`;

            const newTimer = setInterval(() => {
                countdown--;
                btn.textContent = `Resend (${countdown}s)`;

                if (countdown <= 0) {
                    clearInterval(newTimer);
                    btn.textContent = "Resend Code";
                    btn.disabled = false;
                    btn.classList.add("enabled");
                }
            }, 1000);

        } else {
            btn.textContent = "Resend Code";
            btn.disabled = false;
            btn.classList.add("enabled");
            alert("Failed to resend code");
        }
    });
});
</script>

</body>
</html>