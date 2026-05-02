<?php
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: events.php");
    exit();
}

$client_name    = trim($_POST['client_name'] ?? '');
$client_email   = trim($_POST['client_email'] ?? '');
$client_contact = trim($_POST['client_contact'] ?? '');
$event_type     = trim($_POST['event_type'] ?? '');
$event_date     = trim($_POST['event_date'] ?? '');
$event_time     = trim($_POST['event_time'] ?? '');
$venue          = trim($_POST['venue'] ?? '');
$service_type   = trim($_POST['service_type'] ?? '');
$package_name   = trim($_POST['package_name'] ?? '');
$menu_selection = $_POST['menu_qty'] ?? [];
$request        = trim($_POST['request'] ?? '');
$religion       = trim($_POST['religion'] ?? '');

// REQUIRED CHECK
if (
    $client_name === '' ||
    $client_email === '' ||
    $client_contact === '' ||
    $event_type === '' ||
    $event_date === '' ||
    $event_time === '' ||
    $venue === '' ||
    $service_type === ''
) {
    header("Location: events.php?error=missing_fields");
    exit();
}

// BLOCK FULLY BOOKED DATE (max 2 approved bookings)
$checkSql = "SELECT COUNT(*) AS total
             FROM bookings
             WHERE event_date = ?
             AND booking_status = 'Approved'";
$checkStmt = mysqli_prepare($conn, $checkSql);
mysqli_stmt_bind_param($checkStmt, "s", $event_date);
mysqli_stmt_execute($checkStmt);
$checkResult = mysqli_stmt_get_result($checkStmt);
$checkRow = mysqli_fetch_assoc($checkResult);

if (($checkRow['total'] ?? 0) >= 2) {
    header("Location: events.php?error=fully_booked");
    exit();
}

// WEDDING RELIGION RULE
if (strcasecmp($event_type, 'Wedding') === 0) {
    if ($religion === '') {
        header("Location: events.php?error=religion_required");
        exit();
    }
} else {
    $religion = null;
}

// SERVICE TYPE RULES
$valid_menu_selection = [];

if (is_array($menu_selection)) {
    foreach ($menu_selection as $menu_name => $qty) {
        $menu_name = trim($menu_name);
        $qty = (int)$qty;

        if ($menu_name !== '' && $qty > 0) {
            $price_sql = "SELECT price FROM menus WHERE menu_name = ? AND status = 'Active' LIMIT 1";
            $stmt_price = mysqli_prepare($conn, $price_sql);
            mysqli_stmt_bind_param($stmt_price, "s", $menu_name);
            mysqli_stmt_execute($stmt_price);
            $price_result = mysqli_stmt_get_result($stmt_price);
            $price_row = mysqli_fetch_assoc($price_result);

            $price = (float)($price_row['price'] ?? 0);
            $subtotal = $price * $qty;

            $valid_menu_selection[] = [
                'name' => $menu_name,
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $subtotal
            ];
        }
    }
}

if ($service_type === 'Catering Only') {
    $package_name = null;

    if (empty($valid_menu_selection)) {
        header("Location: events.php?error=menu_required");
        exit();
    }
} elseif ($service_type === 'Styling & Decoration Only') {
    $menu_selection = null;

    if ($package_name === '') {
        header("Location: events.php?error=package_required");
        exit();
    }
} elseif ($service_type === 'Catering with Styling') {
    if ($package_name === '') {
        header("Location: events.php?error=package_required");
        exit();
    }

    if (empty($valid_menu_selection)) {
        header("Location: events.php?error=menu_required");
        exit();
    }
} else {
    header("Location: events.php?error=invalid_service_type");
    exit();
}

// FIND EXISTING USER BY EMAIL
$user_id = null;
$findUserSql = "SELECT id FROM users WHERE email = ? LIMIT 1";
$findUserStmt = mysqli_prepare($conn, $findUserSql);
mysqli_stmt_bind_param($findUserStmt, "s", $client_email);
mysqli_stmt_execute($findUserStmt);
$findUserResult = mysqli_stmt_get_result($findUserStmt);

if ($findUserRow = mysqli_fetch_assoc($findUserResult)) {
    $user_id = (int)$findUserRow['id'];
} else {
    // CREATE USER IF NOT FOUND
    $name_parts = preg_split('/\s+/', $client_name, 2);
    $firstname = $name_parts[0] ?? $client_name;
    $lastname  = $name_parts[1] ?? '';
    $default_password = password_hash('12345678', PASSWORD_DEFAULT);

    $insertUserSql = "INSERT INTO users (firstname, lastname, email, phone, password)
                      VALUES (?, ?, ?, ?, ?)";
    $insertUserStmt = mysqli_prepare($conn, $insertUserSql);
    mysqli_stmt_bind_param($insertUserStmt, "sssss", $firstname, $lastname, $client_email, $client_contact, $default_password);

    if (!mysqli_stmt_execute($insertUserStmt)) {
        header("Location: events.php?error=user_create_failed");
        exit();
    }

    $user_id = mysqli_insert_id($conn);
}

// MENU JSON
$menu_selection_json = null;

$menu_selection_json = null;

if (!empty($valid_menu_selection)) {
    $menu_selection_json = json_encode($valid_menu_selection, JSON_UNESCAPED_UNICODE);
}

$booking_status = 'Approved';
$event_status   = 'draft';
$total_price    = 0.00;

$sql = "INSERT INTO bookings (
            user_id,
            client_name,
            event_type,
            service_type,
            package_name,
            menu_selection,
            event_date,
            event_time,
            venue,
            total_price,
            request,
            booking_status,
            event_status,
            religion
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    header("Location: events.php?error=prepare_failed");
    exit();
}

mysqli_stmt_bind_param(
    $stmt,
    "issssssssdssss",
    $user_id,
    $client_name,
    $event_type,
    $service_type,
    $package_name,
    $menu_selection_json,
    $event_date,
    $event_time,
    $venue,
    $total_price,
    $request,
    $booking_status,
    $event_status,
    $religion
);

if (mysqli_stmt_execute($stmt)) {
    header("Location: events.php?success=event_created");
    exit();
} else {
    header("Location: events.php?error=save_failed");
    exit();
}
?>