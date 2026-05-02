<?php
require_once '../db_connection.php';

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die("Invalid package.");
}

$sql = "SELECT * FROM packages WHERE id = ? AND status = 'Active' LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$pkg = mysqli_fetch_assoc($result);

if (!$pkg) {
    die("Package not found.");
}

$features = [];
$includes = [];

$fRes = mysqli_query($conn, "SELECT feature_text FROM package_features WHERE package_id = {$id}");
while ($f = mysqli_fetch_assoc($fRes)) {
    $features[] = $f['feature_text'];
}

$iRes = mysqli_query($conn, "SELECT include_text FROM package_includes WHERE package_id = {$id}");
while ($i = mysqli_fetch_assoc($iRes)) {
    $includes[] = $i['include_text'];
}

$pageTitle = $pkg['package_name'] . " | Magarbo Events";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="details.css">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="back-link" onclick="window.history.back()">
                <i class="fa-solid fa-arrow-left"></i> Back to Home
            </div>
        </div>
    </header>

    <main class="container">
        <div class="main-layout">
            <section class="content-area">
                <h1><?php echo htmlspecialchars($pkg['package_name']); ?></h1>
                <p class="subtitle"><?php echo htmlspecialchars($pkg['description']); ?></p>

                <img src="../<?php echo !empty($pkg['image_path']) ? $pkg['image_path'] : 'images/default-package.jpg'; ?>" alt="<?php echo htmlspecialchars($pkg['package_name']); ?>" class="hero-img">

                <div class="alert-box">
                    <div class="alert-header">
                        <i class="fa-solid fa-circle-exclamation icon-small"></i>
                        <h3>Important Booking Information:</h3>
                    </div>
                    <ul class="alert-list">
                        <li><span class="bold">₱2,000 Down Payment Required:</span> A non-refundable deposit is required to secure your booking.</li>
                        <li><span class="bold">No Refund Policy:</span> All payments are final. Cancellations will not be refunded.</li>
                        <li><span class="bold">Event Date:</span> Please confirm your event date is at least 7 days from booking.</li>
                        <li><span class="bold">Final Guest Count:</span> Must be provided 48 hours before the event.</li>
                    </ul>
                </div>

                <div class="tab-switcher">
                    <button id="tab-overview" class="tab-btn active" onclick="switchTab('overview')">Overview</button>
                    <button id="tab-include" class="tab-btn" onclick="switchTab('include')">What's Include</button>
                </div>

                <div id="content-overview">
                    <h2>Styling Features</h2>
                    <p class="section-desc">This package focuses on event styling and decoration. Food and venue arrangements are customized separately during booking.</p>

                    <?php foreach ($features as $feature): ?>
                    <div class="feature-card">
                        <i class="fa-solid fa-check check"></i>
                        <span><?php echo htmlspecialchars($feature); ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div class="gallery-banner">
                        <div class="gallery-text">
                            <p class="bold">See Our Work</p>
                            <p class="muted">Browse photos from past events to see our styling and decoration work</p>
                        </div>
                        <a href="gallery.php" class="gallery-btn">View Gallery</a>
                    </div>
                </div>

                <div id="content-include" style="display: none;">
                    <h2>Package Inclusions</h2>

                    <?php foreach ($includes as $include): ?>
                    <div class="feature-card">
                        <span class="gold-bullet">•</span>
                        <span><?php echo htmlspecialchars($include); ?></span>
                    </div>
                    <?php endforeach; ?>

                    <div class="additional-services">
                        <h4>Additional Services Available</h4>
                        <ul>
                            <li><i class="fa-solid fa-check check-blue"></i> <strong>Food & Catering:</strong> Customize your menu during booking</li>
                            <li><i class="fa-solid fa-check check-blue"></i> <strong>Venue Arrangement:</strong> Specify your venue or choose from our partners</li>
                            <li><i class="fa-solid fa-check check-blue"></i> <strong>Custom Add-ons:</strong> Extra decorations, lighting, or styling elements (additional charges apply)</li>
                        </ul>
                    </div>

                    <div class="note-box">
                        <p class="bold">Note on Pricing:</p>
                        <p class="muted">The package price shown is a starting base price for styling services only. Final pricing will be determined based on your specific requirements including food choices, venue, guest count, and any custom additions you request during booking.</p>
                    </div>
                </div>
            </section>

            <aside class="sidebar">
                <div class="sticky-card">
                    <span class="label">Start from</span>
                    <p class="amount">₱<?php echo number_format($pkg['price_start']); ?></p>
                    <span class="sub-label">Base styling package only</span>

                    <div class="info-block">
                        <strong>What's included:</strong> Event styling and decoration setup
                        <p class="muted" style="margin-top:5px;">Food, venue, and custom add-ons are quoted separately based on your requirements</p>
                    </div>

                    <div class="info-block">
                        <strong>Note:</strong> Magarbo Events welcomes couples from all religions. You may select your ceremony preference during booking so we can adjust the setup accordingly.
                    </div>

                    <div class="deposit-card">
                        <p class="deposit-title">₱2,000 Deposit</p>
                        <p class="deposit-desc">Required down payment</p>
                    </div>
                </div>
            </aside>
        </div>
    </main>

    <script>
        function switchTab(type) {
            const overview = document.getElementById('content-overview');
            const include = document.getElementById('content-include');
            const btnOver = document.getElementById('tab-overview');
            const btnInc = document.getElementById('tab-include');

            if (type === 'overview') {
                overview.style.display = 'block';
                include.style.display = 'none';
                btnOver.classList.add('active');
                btnInc.classList.remove('active');
            } else {
                overview.style.display = 'none';
                include.style.display = 'block';
                btnInc.classList.add('active');
                btnOver.classList.remove('active');
            }
        }
    </script>
</body>
</html>