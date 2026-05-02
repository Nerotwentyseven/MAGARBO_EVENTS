<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';
require_once 'phone_otp_config.php';

if (!isset($_SESSION['user_id'])) {
    echo "No user session found.";
    exit();
}

$user_id = (int)$_SESSION['user_id'];

function phoneFlash(string $type, string $message, string $redirect = 'verify_phone_otp.php') {
    $_SESSION['phone_flash'] = [
        'type' => $type,
        'message' => $message
    ];
    header("Location: {$redirect}");
    exit();
}

function maskPhoneDisplay($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) !== 11) return $phone;
    return substr($digits, 0, 4) . ' *** ' . substr($digits, -4);
}

$stmt = mysqli_prepare($conn, "
    SELECT phone, pending_phone, phone_verified, phone_otp, phone_otp_expires, phone_otp_sent_at, phone_otp_attempts, phone_otp_resend_count
    FROM users
    WHERE id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$user) {
    exit('User not found.');
}

$pendingPhone = trim($user['pending_phone'] ?? '');

if ($pendingPhone === '') {
    $_SESSION['phone_flash'] = [
        'type' => 'error',
        'message' => 'No pending mobile number found. Please set your mobile number first.'
    ];
    header("Location: profile.php?view=profile");
    exit();
}

/* RESEND OTP */
if (isset($_GET['resend']) && $_GET['resend'] == '1') {
    $resendCount = (int)($user['phone_otp_resend_count'] ?? 0);

    if ($resendCount >= PHONE_OTP_MAX_RESENDS) {
        phoneFlash('error', 'Maximum resend limit reached. Please start again.', 'change_phone.php');
    }

    if (!empty($user['phone_otp_sent_at'])) {
        $secondsSinceLastSend = time() - strtotime($user['phone_otp_sent_at']);
        if ($secondsSinceLastSend < PHONE_OTP_RESEND_SECONDS) {
            $remaining = PHONE_OTP_RESEND_SECONDS - $secondsSinceLastSend;
            phoneFlash('error', "Please wait {$remaining} seconds before resending OTP.");
        }
    }

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PHONE_OTP_EXPIRY_MINUTES . ' minutes'));
    $sentAt = date('Y-m-d H:i:s');
    $newResendCount = $resendCount + 1;

    $upd = mysqli_prepare($conn, "
        UPDATE users
        SET phone_otp = ?,
            phone_otp_expires = ?,
            phone_otp_sent_at = ?,
            phone_otp_attempts = 0,
            phone_otp_resend_count = ?
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($upd, "sssii", $otpHash, $expiresAt, $sentAt, $newResendCount, $user_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    $message = "Your Magarbo code is {$otp}. Valid for " . PHONE_OTP_EXPIRY_MINUTES . " minutes. Do not share this code.";
    $send = sendSemaphoreSMS($pendingPhone, $message);

    if (!$send['success']) {
        phoneFlash('error', 'Failed to resend OTP: ' . $send['message']);
    }

    phoneFlash('success', 'A new OTP has been sent to your mobile number.');
}

/* VERIFY OTP */
/* VERIFY OTP */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = preg_replace('/\D/', '', $_POST['otp'] ?? '');

    if ($otp === '') {
        phoneFlash('error', 'Please enter the OTP code.');
    }

    if (!preg_match('/^\d{6}$/', $otp)) {
        phoneFlash('error', 'Please enter a valid 6-digit OTP code.');
    }

    // Check if OTP exists
    if (empty($user['phone_otp']) || empty($user['phone_otp_expires'])) {
        phoneFlash('error', 'OTP request not found.', 'change_phone.php');
    }

    // Check expiry
    if (strtotime($user['phone_otp_expires']) < time()) {
        phoneFlash('error', 'OTP has expired. Please request a new OTP.', 'change_phone.php');
    }

    // Check attempts
    $attempts = (int)($user['phone_otp_attempts'] ?? 0);
    if ($attempts >= PHONE_OTP_MAX_ATTEMPTS) {
        phoneFlash('error', 'Maximum OTP attempts reached. Please request a new OTP.', 'change_phone.php');
    }

    // 🔥 IMPORTANT: VERIFY HASHED OTP
    $isValid = password_verify($otp, $user['phone_otp']);

    if (!$isValid) {
        $attempts++;

        $upd = mysqli_prepare($conn, "UPDATE users SET phone_otp_attempts = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "ii", $attempts, $user_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        $remaining = PHONE_OTP_MAX_ATTEMPTS - $attempts;

        phoneFlash('error', "Invalid OTP. {$remaining} attempt(s) remaining.");
    }

    // 🔥 SUCCESS → SAVE PHONE
    $finalize = mysqli_prepare($conn, "
        UPDATE users
        SET phone = pending_phone,
            pending_phone = NULL,
            phone_verified = 1,
            phone_otp = NULL,
            phone_otp_expires = NULL,
            phone_otp_sent_at = NULL,
            phone_otp_attempts = 0,
            phone_otp_resend_count = 0
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($finalize, "i", $user_id);
    mysqli_stmt_execute($finalize);
    mysqli_stmt_close($finalize);

    $_SESSION['phone_flash'] = [
        'type' => 'success',
        'message' => 'Mobile number verified successfully.'
    ];

    header("Location: profile.php?view=profile");
    exit();
}

$flash = $_SESSION['phone_flash'] ?? null;
unset($_SESSION['phone_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Mobile Number</title>
    <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container" style="max-width:700px; margin:40px auto;">
        <div class="content-box">
            <h2 style="margin-bottom:18px;">Verify Mobile Number</h2>

            <?php if ($flash): ?>
                <div style="margin-bottom:16px; padding:12px 14px; border-radius:8px; background:<?php echo $flash['type'] === 'success' ? '#eaf7ee' : '#fdecea'; ?>; color:<?php echo $flash['type'] === 'success' ? '#1f7a3d' : '#b42318'; ?>;">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <p style="margin-bottom:18px;">
                We sent an OTP to <strong><?php echo htmlspecialchars(maskPhoneDisplay($pendingPhone)); ?></strong>.
            </p>

            <form method="POST">
                <div class="form-group">
                    <label>Enter OTP</label>
                    <input type="text" name="otp" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" placeholder="6-digit code" required>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                    <button type="submit" class="btn-schedule" style="border:none; cursor:pointer;">
                        Verify OTP
                    </button>

                    <a href="verify_phone_otp.php?resend=1"
                    id="resendOtpBtn"
                    class="btn-schedule"
                    style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; opacity:.55; pointer-events:none;">
                        Resend in 60s
                    </a>

                    <a href="change_phone.php" class="btn-schedule" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                        Back
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
    let resendSeconds = 60;
    const resendBtn = document.getElementById('resendOtpBtn');

    const resendTimer = setInterval(() => {
        resendSeconds--;

        if (resendSeconds > 0) {
            resendBtn.textContent = `Resend in ${resendSeconds}s`;
            resendBtn.style.opacity = '.55';
            resendBtn.style.pointerEvents = 'none';
        } else {
            clearInterval(resendTimer);
            resendBtn.textContent = 'Resend OTP';
            resendBtn.style.opacity = '1';
            resendBtn.style.pointerEvents = 'auto';
        }
    }, 1000);
    </script>

</body>
</html>