<?php
session_name('USERSESSID');
session_start();
$pageTitle = "Event Styling Packages | Magarbo Events";
require_once '../db_connection.php';

$packages = [];

// TOTAL APPROVED PACKAGE BOOKINGS
$totalPackageBookings = 0;
$totalStmt = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE package_name IS NOT NULL
       AND package_name != ''
       AND booking_status = 'Approved'"
);

if ($totalStmt && $rowTotal = mysqli_fetch_assoc($totalStmt)) {
    $totalPackageBookings = (int)$rowTotal['total'];
}

$sql = "SELECT * FROM packages WHERE status = 'Active' ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($pkg = mysqli_fetch_assoc($result)) {
        $features = [];

        $fRes = mysqli_query($conn, "SELECT feature_text FROM package_features WHERE package_id = {$pkg['id']}");
        while ($f = mysqli_fetch_assoc($fRes)) {
            $features[] = $f['feature_text'];
        }

        $packageBookings = 0;
        $pStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM bookings
             WHERE package_name = ?
               AND booking_status = 'Approved'"
        );
        mysqli_stmt_bind_param($pStmt, "s", $pkg['package_name']);
        mysqli_stmt_execute($pStmt);
        $pRes = mysqli_stmt_get_result($pStmt);

        if ($pRow = mysqli_fetch_assoc($pRes)) {
            $packageBookings = (int)$pRow['total'];
        }

        $percent = ($totalPackageBookings > 0)
            ? round(($packageBookings / $totalPackageBookings) * 100)
            : 0;

        $packages[] = [
            "title" => $pkg['package_name'],
            "price" => "Start from ₱" . number_format($pkg['price_start']),
            "image" => !empty($pkg['image_path']) ? $pkg['image_path'] : "images/default-package.jpg",
            "link" => "package_details.php?id=" . $pkg['id'],
            "pop" => $percent . "% popularity",
            "desc" => $pkg['description'],
            "features" => $features
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="packages.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="packages-page-body">

    <nav class="top-nav">
        <div class="back-home" onclick="location.href='index.php'">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Home</span>
        </div>
    </nav>

    <header class="header-details">
        <div class="header-content">
            <h1>Event Styling Packages</h1>
            <p class="header-desc">
                Choose from our carefully crafted styling packages designed to make your event unforgettable. 
                Each package is tailored to provide a unique atmosphere for your special celebration.
            </p>
            <div class="notification-box">
                <i class="fa-solid fa-circle-info"></i> 
                Note: Prices may vary based on venue size and specific customization requests.
            </div>
        </div>
    </header>

    <div class="container">
        <p class="package-count">Showing <?php echo count($packages); ?> packages</p>
        
        <div class="packages-grid">
            <?php foreach ($packages as $p): ?>
                <div class="card-detail">
                    <div class="card-image" style="position: relative;">
                        <img src="../<?php echo $p['image']; ?>" alt="<?php echo $p['title']; ?>">
                        <span class="badge-pop" style="position: absolute; top: 15px; left: 15px; background: #bf9225; color: white; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; z-index: 10;">
                            <?php echo htmlspecialchars($p['pop']); ?>
                        </span>
                    </div>
                    
                    <div class="card-body">
    <div class="card-header">
        <h3><?php echo $p['title']; ?></h3>
        
        

        <span class="price-detail"><?php echo $p['price']; ?></span>
    </div>
    
    <p class="card-desc"><?php echo $p['desc']; ?></p>
    
    <ul class="features-list">
        <?php foreach ($p['features'] as $feature): ?>
            <li><?php echo $feature; ?></li>
        <?php endforeach; ?>
    </ul>
    
    <a href="<?php echo $p['link']; ?>" class="btn-details" style="text-decoration: none; display: inline-block; text-align: center;">
        <i class="fa-regular fa-eye"></i> View Details
    </a>
</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include 'online_heartbeat.php'; ?>
    
</body>
</html>