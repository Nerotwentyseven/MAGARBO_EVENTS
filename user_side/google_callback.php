<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('USERSESSID');
    session_start();
}

require_once '../db_connection.php';
require_once '../vendor/autoload.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method."
    ]);
    exit;
}

$credential = trim($_POST['credential'] ?? '');

if ($credential === '') {
    echo json_encode([
        "success" => false,
        "message" => "No credential received."
    ]);
    exit;
}

try {
    $client = new Google\Client([
        'client_id' => '494722959577-eqn360i1mh8ofe5bltle94qr884ksavv.apps.googleusercontent.com'
    ]);

    $payload = $client->verifyIdToken($credential);

    if (!$payload) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid Google token."
        ]);
        exit;
    }

    $googleId  = trim($payload['sub'] ?? '');
    $email     = strtolower(trim($payload['email'] ?? ''));
    $firstname = trim($payload['given_name'] ?? '');
    $lastname  = trim($payload['family_name'] ?? '');

    if ($googleId === '' || $email === '') {
        echo json_encode([
            "success" => false,
            "message" => "Incomplete Google account data."
        ]);
        exit;
    }

    $stmt = mysqli_prepare($conn, "
        SELECT id, firstname, lastname, status
        FROM users
        WHERE email = ? OR google_id = ?
        LIMIT 1
    ");
    mysqli_stmt_bind_param($stmt, "ss", $email, $googleId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($res)) {
        $userId = (int)$user['id'];

        if (($user['status'] ?? 'Active') !== 'Active') {
            echo json_encode([
                "success" => false,
                "message" => "Your account has been " . strtolower($user['status']) . ". Please contact the admin."
            ]);
            exit;
        }

        $update = mysqli_prepare($conn, "
            UPDATE users
            SET google_id = ?,
                email_verified = 1,
                auth_provider = CASE
                    WHEN password_set = 1 THEN 'both'
                    ELSE 'google'
                END
            WHERE id = ?
        ");
        mysqli_stmt_bind_param($update, "si", $googleId, $userId);
        mysqli_stmt_execute($update);

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['full_name'] = trim($user['firstname'] . ' ' . $user['lastname']);
    } else {
        $insert = mysqli_prepare($conn, "
        INSERT INTO users (
            firstname,
            lastname,
            email,
            password,
            google_id,
            auth_provider,
            password_set,
            email_verified,
            status
        )
        VALUES (?, ?, ?, '', ?, 'google', 0, 1, 'Active')
    ");

mysqli_stmt_bind_param($insert, "ssss", $firstname, $lastname, $email, $googleId);
        mysqli_stmt_execute($insert);

        $userId = mysqli_insert_id($conn);

        $date = date("Ymd");
        $base = "USR" . $date;

        $query = mysqli_query($conn, "
            SELECT COUNT(*) as total
            FROM users
            WHERE display_user_id LIKE '$base%'
        ");
        $row = mysqli_fetch_assoc($query);
        $count = (int)$row['total'];

        $displayId = ($count === 0) ? $base : $base . '-' . ($count + 1);

        $upd = mysqli_prepare($conn, "UPDATE users SET display_user_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd, "si", $displayId, $userId);
        mysqli_stmt_execute($upd);

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['full_name'] = trim($firstname . ' ' . $lastname);
    }

    session_regenerate_id(true);

    echo json_encode([
        "success" => true
    ]);
    exit;

} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Google sign-in failed."
    ]);
    exit;
}