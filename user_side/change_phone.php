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

function phoneFlash(string $type, string $message, string $redirect = 'change_phone.php') {
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
    SELECT phone, pending_phone, phone_verified, phone_otp_sent_at
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

$currentPhone = trim($user['phone'] ?? '');
$pendingPhone = trim($user['pending_phone'] ?? '');
$phoneVerified = (int)($user['phone_verified'] ?? 0) === 1;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phoneInput = trim($_POST['phone'] ?? '');
    $normalizedPhone = normalizePHNumber($phoneInput);

    if (!$normalizedPhone) {
        phoneFlash('error', 'Please enter a valid Philippine mobile number.');
    }

    if ($currentPhone !== '' && $normalizedPhone === $currentPhone) {
        phoneFlash('error', 'That is already your current mobile number.');
    }

    $dup = mysqli_prepare($conn, "
        SELECT id
        FROM users
        WHERE id != ?
          AND phone = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($dup, "is", $user_id, $normalizedPhone);
    mysqli_stmt_execute($dup);
    $dupRes = mysqli_stmt_get_result($dup);

    if (mysqli_num_rows($dupRes) > 0) {
        mysqli_stmt_close($dup);
        phoneFlash('error', 'This mobile number is already used by another account.');
    }
    mysqli_stmt_close($dup);

    if (!empty($user['phone_otp_sent_at'])) {
        $secondsSinceLastSend = time() - strtotime($user['phone_otp_sent_at']);
        if ($secondsSinceLastSend < PHONE_OTP_RESEND_SECONDS) {
            $remaining = PHONE_OTP_RESEND_SECONDS - $secondsSinceLastSend;
            phoneFlash('error', "Please wait {$remaining} seconds before requesting another OTP.");
        }
    }

    $otp = (string)random_int(100000, 999999);
    $otpHash = password_hash($otp, PASSWORD_DEFAULT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . PHONE_OTP_EXPIRY_MINUTES . ' minutes'));
    $sentAt = date('Y-m-d H:i:s');

    $update = mysqli_prepare($conn, "
        UPDATE users
        SET pending_phone = ?,
            phone_otp = ?,
            phone_otp_expires = ?,
            phone_otp_sent_at = ?,
            phone_otp_attempts = 0,
            phone_otp_resend_count = 0
        WHERE id = ?
    ");
    mysqli_stmt_bind_param($update, "ssssi", $normalizedPhone, $otpHash, $expiresAt, $sentAt, $user_id);
    mysqli_stmt_execute($update);
    mysqli_stmt_close($update);

    $message = "Your Magarbo code is {$otp}. Valid for " . PHONE_OTP_EXPIRY_MINUTES . " minutes. Do not share this code.";
    $send = sendSemaphoreSMS($normalizedPhone, $message);

    if (!$send['success']) {
        phoneFlash('error', 'Failed to send OTP: ' . $send['message']);
    }

    phoneFlash('success', 'OTP sent to your mobile number.', 'verify_phone_otp.php');
}

$flash = $_SESSION['phone_flash'] ?? null;
unset($_SESSION['phone_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $currentPhone !== '' ? 'Change' : 'Set'; ?> Mobile Number</title>
    <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container" style="max-width:700px; margin:40px auto;">
        <div class="content-box">
            <h2 style="margin-bottom:18px;">
                <?php echo $currentPhone !== '' ? 'Change Mobile Number' : 'Set Mobile Number'; ?>
            </h2>

            <?php if ($flash): ?>
                <div style="margin-bottom:16px; padding:12px 14px; border-radius:8px; background:<?php echo $flash['type'] === 'success' ? '#eaf7ee' : '#fdecea'; ?>; color:<?php echo $flash['type'] === 'success' ? '#1f7a3d' : '#b42318'; ?>;">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <?php if ($currentPhone !== ''): ?>
                <p style="margin-bottom:8px;"><strong>Current number:</strong> <?php echo htmlspecialchars(maskPhoneDisplay($currentPhone)); ?></p>
                <p style="margin-bottom:20px; color:#666;">
                    Status:
                    <strong style="color: <?php echo $phoneVerified ? '#198754' : '#dc3545'; ?>;">
                        <?php echo $phoneVerified ? 'Verified' : 'Unverified'; ?>
                    </strong>
                </p>
            <?php endif; ?>

            <?php if ($pendingPhone !== ''): ?>
                <p style="margin-bottom:20px; color:#666;">
                    Pending new number: <strong><?php echo htmlspecialchars(maskPhoneDisplay($pendingPhone)); ?></strong>
                </p>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="phone" placeholder="09XXXXXXXXX" required>
                </div>

                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:18px;">
                    <button type="submit" class="btn-schedule" style="border:none; cursor:pointer;">
                        Send OTP
                    </button>

                    <a href="profile.php?view=profile" class="btn-schedule" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                        Back to Profile
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>