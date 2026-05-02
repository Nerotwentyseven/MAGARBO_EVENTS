<?php
session_name('ADMINSESSID');
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: package_management.php");
    exit();
}

$package_id   = (int)($_POST['package_id'] ?? 0);
$package_name = trim($_POST['package_name'] ?? '');
$price_start  = (float)($_POST['price_start'] ?? 0);
$description  = trim($_POST['description'] ?? '');
$status       = trim($_POST['status'] ?? 'Active');

$features_json = $_POST['features_json'] ?? '[]';
$includes_json = $_POST['includes_json'] ?? '[]';

if ($package_name === '') {
    header("Location: package_management.php?error=missing_fields");
    exit();
}

$image_path = null;

// IMAGE UPLOAD
if (isset($_FILES['package_image']) && !empty($_FILES['package_image']['name'])) {
    $uploadDir = "../uploads/packages/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $ext = pathinfo($_FILES['package_image']['name'], PATHINFO_EXTENSION);
    $safeName = 'pkg_' . time() . '_' . rand(1000, 9999) . '.' . strtolower($ext);
    $targetPath = $uploadDir . $safeName;

    if (move_uploaded_file($_FILES['package_image']['tmp_name'], $targetPath)) {
        $image_path = "uploads/packages/" . $safeName;
    }
}

// UPDATE EXISTING PACKAGE
if ($package_id > 0) {
    if ($image_path) {
        $sql = "UPDATE packages 
                SET package_name = ?, price_start = ?, image_path = ?, description = ?, status = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdsssi", $package_name, $price_start, $image_path, $description, $status, $package_id);
    } else {
        $sql = "UPDATE packages 
                SET package_name = ?, price_start = ?, description = ?, status = ?
                WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sdssi", $package_name, $price_start, $description, $status, $package_id);
    }

    if (!mysqli_stmt_execute($stmt)) {
        die("Error updating package: " . mysqli_error($conn));
    }

    // DELETE OLD FEATURES
    $delFeat = mysqli_prepare($conn, "DELETE FROM package_features WHERE package_id = ?");
    mysqli_stmt_bind_param($delFeat, "i", $package_id);
    mysqli_stmt_execute($delFeat);

    // DELETE OLD INCLUDES
    $delInc = mysqli_prepare($conn, "DELETE FROM package_includes WHERE package_id = ?");
    mysqli_stmt_bind_param($delInc, "i", $package_id);
    mysqli_stmt_execute($delInc);

} else {
    // INSERT NEW PACKAGE
    $sql = "INSERT INTO packages (package_name, price_start, image_path, description, status)
            VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sdsss", $package_name, $price_start, $image_path, $description, $status);

    if (!mysqli_stmt_execute($stmt)) {
        die("Error inserting package: " . mysqli_error($conn));
    }

    $package_id = mysqli_insert_id($conn);
}

// DECODE FEATURES / INCLUDES
$features = json_decode($features_json, true);
$includes = json_decode($includes_json, true);

if (!is_array($features)) $features = [];
if (!is_array($includes)) $includes = [];

// SAVE FEATURES
$featStmt = mysqli_prepare($conn, "INSERT INTO package_features (package_id, feature_text) VALUES (?, ?)");
foreach ($features as $feature) {
    $feature = trim($feature);
    if ($feature !== '') {
        mysqli_stmt_bind_param($featStmt, "is", $package_id, $feature);
        mysqli_stmt_execute($featStmt);
    }
}

// SAVE INCLUDES
$incStmt = mysqli_prepare($conn, "INSERT INTO package_includes (package_id, include_text) VALUES (?, ?)");
foreach ($includes as $include) {
    $include = trim($include);
    if ($include !== '') {
        mysqli_stmt_bind_param($incStmt, "is", $package_id, $include);
        mysqli_stmt_execute($incStmt);
    }
}

header("Location: package_management.php?success=saved");
exit();
?>