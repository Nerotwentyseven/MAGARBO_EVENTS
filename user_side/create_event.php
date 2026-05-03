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
$menu_selection = $_POST['menu_selection'] ?? '';
$request        = trim($_POST['request'] ?? '');
$religion       = trim($_POST['religion'] ?? '');

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
    die("Missing required fields.");
}

if (strcasecmp($event_type, 'Wedding') !== 0) {
    $religion = null;
} elseif ($religion === '') {
    die("Religion is required for wedding events.");
}

if ($service_type === 'Catering Only') {
    $package_name = null;

    if (empty($menu_selection) || !is_array($menu_selection)) {
        die("Please select at least one menu for Catering Only.");
    }
} elseif ($service_type === 'Styling & Decoration Only') {
    $menu_selection = null;

    if ($package_name === '') {
        die("Please select a package for Styling & Decoration Only.");
    }
} elseif ($service_type === 'Catering with Styling') {
    if ($package_name === '') {
        die("Please select a package for Catering with Styling.");
    }

    if (empty($menu_selection) || !is_array($menu_selection)) {
        die("Please select at least one menu for Catering with Styling.");
    }
} else {
    die("Invalid service type.");
}

$user_id = null;

$find_user_sql = "SELECT id FROM users WHERE email = ? LIMIT 1";
$stmt_find = mysqli_prepare($conn, $find_user_sql);
mysqli_stmt_bind_param($stmt_find, "s", $client_email);
mysqli_stmt_execute($stmt_find);
$result_find = mysqli_stmt_get_result($stmt_find);

if ($user = mysqli_fetch_assoc($result_find)) {
    $user_id = (int)$user['id'];

    $name_parts = preg_split('/\s+/', $client_name, 2);
    $firstname = $name_parts[0] ?? $client_name;
    $lastname  = $name_parts[1] ?? '';

    $update_user_sql = "UPDATE users SET firstname = ?, lastname = ?, phone = ? WHERE id = ?";
    $stmt_update = mysqli_prepare($conn, $update_user_sql);
    mysqli_stmt_bind_param($stmt_update, "sssi", $firstname, $lastname, $client_contact, $user_id);
    mysqli_stmt_execute($stmt_update);
} else {
    $name_parts = preg_split('/\s+/', $client_name, 2);
    $firstname = $name_parts[0] ?? $client_name;
    $lastname  = $name_parts[1] ?? '';

    $default_password = password_hash('12345678', PASSWORD_DEFAULT);

    $insert_user_sql = "INSERT INTO users (firstname, lastname, email, phone, password) VALUES (?, ?, ?, ?, ?)";
    $stmt_user = mysqli_prepare($conn, $insert_user_sql);
    mysqli_stmt_bind_param($stmt_user, "sssss", $firstname, $lastname, $client_email, $client_contact, $default_password);

    if (!mysqli_stmt_execute($stmt_user)) {
        die("Failed to create client user: " . mysqli_error($conn));
    }

    $user_id = mysqli_insert_id($conn);
}

$menu_selection_json = null;

if (is_array($menu_selection) && !empty($menu_selection)) {
    $menu_data = [];

    foreach ($menu_selection as $menu_name) {
        $menu_name = trim($menu_name);
        if ($menu_name !== '') {
            $menu_data[] = [
                'name' => $menu_name
            ];
        }
    }

    if (!empty($menu_data)) {
        $menu_selection_json = json_encode($menu_data, JSON_UNESCAPED_UNICODE);
    }
}

$booking_status = 'Approved';
$event_status = 'Draft';
$total_price = 0.00;

$insert_sql = "INSERT INTO bookings 
    (user_id, event_type, service_type, package_name, menu_selection, event_date, event_time, venue, total_price, request, booking_status, event_status, religion)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt_insert = mysqli_prepare($conn, $insert_sql);

if (!$stmt_insert) {
    die("Prepare failed: " . mysqli_error($conn));
}

mysqli_stmt_bind_param(
    $stmt_insert,
    "isssssssdssss",
    $user_id,
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

if (!mysqli_stmt_execute($stmt_insert)) {
    die("Failed to create event booking: " . mysqli_stmt_error($stmt_insert));
}

header("Location: events.php?success=created");
exit();
?>