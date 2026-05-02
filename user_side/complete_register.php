<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (
    !isset($_SESSION['reg_firstname']) ||
    !isset($_SESSION['reg_lastname']) ||
    !isset($_SESSION['reg_email']) ||
    !isset($_SESSION['reg_password']) ||
    !isset($_SESSION['reg_otp']) ||
    !isset($_SESSION['reg_otp_expiry'])
) {
    header("Location: login.php");
    exit();
}

$enteredOtp = trim((string)($_POST['otp_code'] ?? ''));
$sessionOtp = trim((string)($_SESSION['reg_otp'] ?? ''));

if ($enteredOtp === '') {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Missing Code',
        'message' => 'Please enter the verification code.',
        'form' => 'register'
    ];
    header("Location: verify_register_otp.php");
    exit();
}

if (time() > $_SESSION['reg_otp_expiry']) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Code Expired',
        'message' => 'Your verification code has expired. Please register again.',
        'form' => 'register'
    ];
    header("Location: login.php");
    exit();
}

if ($enteredOtp !== $sessionOtp) {
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Invalid Code',
        'message' => 'The verification code you entered is incorrect.',
        'form' => 'register'
    ];
    header("Location: verify_register_otp.php");
    exit();
}

// Final duplicate re-check before insert
$email = $_SESSION['reg_email'];

$check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
mysqli_stmt_bind_param($check_stmt, "s", $email);
mysqli_stmt_execute($check_stmt);
$result = mysqli_stmt_get_result($check_stmt);

if (mysqli_num_rows($result) > 0) {
    mysqli_stmt_close($check_stmt);
    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Email Already Registered',
        'message' => 'This email is already registered.',
        'form' => 'register'
    ];
    header("Location: login.php");
    exit();
}
mysqli_stmt_close($check_stmt);

// Final insert
$firstname = $_SESSION['reg_firstname'];
$lastname  = $_SESSION['reg_lastname'];
$hashed_password = $_SESSION['reg_password'];

$insert_stmt = mysqli_prepare($conn, "
    INSERT INTO users (firstname, lastname, email, password, email_verified)
    VALUES (?, ?, ?, ?, 1)
");
mysqli_stmt_bind_param($insert_stmt, "ssss", $firstname, $lastname, $email, $hashed_password);

if (mysqli_stmt_execute($insert_stmt)) {
    $newUserId = mysqli_insert_id($conn);

    // Generate permanent display_user_id
    $dateKey = date('Ymd');
    $baseId = 'USR' . $dateKey;
    $finalId = $baseId;
    $count = 1;

    while (true) {
        $check = mysqli_prepare($conn, "SELECT id FROM users WHERE display_user_id = ? LIMIT 1");
        mysqli_stmt_bind_param($check, "s", $finalId);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($res) === 0) {
            mysqli_stmt_close($check);
            break;
        }

        mysqli_stmt_close($check);
        $count++;
        $finalId = $baseId . '-' . $count;
    }

    $upd = mysqli_prepare($conn, "UPDATE users SET display_user_id = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, "si", $finalId, $newUserId);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);

    unset(
        $_SESSION['reg_firstname'],
        $_SESSION['reg_lastname'],
        $_SESSION['reg_email'],
        $_SESSION['reg_password'],
        $_SESSION['reg_otp'],
        $_SESSION['reg_otp_expiry']
    );

    $_SESSION['alert'] = [
        'type' => 'success',
        'title' => 'Account Created',
        'message' => 'Email verified successfully! You can now sign in.',
        'form' => 'login'
    ];

    header("Location: login.php");
    exit();
} else {
    $error = mysqli_error($conn);
    mysqli_stmt_close($insert_stmt);

    $_SESSION['alert'] = [
        'type' => 'error',
        'title' => 'Registration Failed',
        'message' => 'Something went wrong: ' . $error,
        'form' => 'register'
    ];

    header("Location: login.php");
    exit();
}