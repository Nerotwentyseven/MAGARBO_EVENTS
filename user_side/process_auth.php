<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

function setAlert($type, $title, $message, $form = 'login') {
    $_SESSION['alert'] = [
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'form' => $form
    ];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action_type'] ?? '';

    if ($action == 'register') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $terms_agreed = isset($_POST['terms_agreed']);

    if (
    empty($firstname) ||
    empty($lastname) ||
    empty($email) ||
    empty($password) ||
    empty($confirm)
) {
    setAlert('error', 'Incomplete Form', 'Please fill in all required fields.', 'register');
    header("Location: login.php");
    exit();
}

    if (!$terms_agreed) {
        setAlert('error', 'Terms Required', 'Please agree to the Terms of Service and Privacy Policy.', 'register');
        header("Location: login.php");
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setAlert('error', 'Invalid Email', 'Please enter a valid email address.', 'register');
        header("Location: login.php");
        exit();
    }


    if ($password !== $confirm) {
        setAlert('error', 'Password Mismatch', 'Passwords do not match.', 'register');
        header("Location: login.php");
        exit();
    }

    if (strlen($password) < 8) {
        setAlert('error', 'Weak Password', 'Password must be at least 8 characters long.', 'register');
        header("Location: login.php");
        exit();
    }

    // Check existing email
    $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
    mysqli_stmt_bind_param($check_stmt, "s", $email);
    mysqli_stmt_execute($check_stmt);
    $result = mysqli_stmt_get_result($check_stmt);

    if (mysqli_num_rows($result) > 0) {
        mysqli_stmt_close($check_stmt);
        setAlert('error', 'Email Already Registered', 'This email is already registered.', 'register');
        header("Location: login.php");
        exit();
    }
    mysqli_stmt_close($check_stmt);

    $_SESSION['reg_firstname'] = $firstname;
    $_SESSION['reg_lastname']  = $lastname;
    $_SESSION['reg_email']     = $email;
    $_SESSION['reg_password']  = password_hash($password, PASSWORD_DEFAULT);

    header("Location: send_register_otp.php");
    exit();
}

    else if ($action == 'login') {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember_me']);

        if (empty($email) || empty($password)) {
            setAlert('error', 'Missing Login Details', 'Please enter both email and password.', 'login');
            header("Location: login.php");
            exit();
        }

        $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);

            if (password_verify($password, $user['password'])) {

                $accountStatus = $user['status'] ?? 'Active';

                if ($accountStatus !== 'Active') {
                    mysqli_stmt_close($stmt);

                    if ($accountStatus === 'Deactivated') {
                        setAlert('error', 'Account Deactivated', 'Your account has been deactivated. Please contact the admin.', 'login');
                    } elseif ($accountStatus === 'Deleted') {
                        setAlert('error', 'Account Deleted', 'Your account has been deleted. Please contact the admin.', 'login');
                    } else {
                        setAlert('error', 'Account Not Active', 'Your account is not active. Please contact the admin.', 'login');
                    }

                    header("Location: login.php");
                    exit();
                }

                $_SESSION['user_id']    = $user['id'];
                $_SESSION['full_name']  = $user['firstname'] . " " . $user['lastname'];
                $_SESSION['user_email'] = $user['email'];

                if ($remember) {
                    setcookie("user_login", $email, time() + (86400 * 30), "/");
                }

                mysqli_stmt_close($stmt);
                header("Location: index.php");
                exit();
            } else {
                mysqli_stmt_close($stmt);
                setAlert('error', 'Invalid Password', 'Invalid password! Please try again.', 'login');
                header("Location: login.php");
                exit();
            }
        } else {
            mysqli_stmt_close($stmt);
            setAlert('error', 'Account Not Found', 'No account found with that email.', 'login');
            header("Location: login.php");
            exit();
        }
    }
} else {
    header("Location: login.php");
    exit();
}
?>