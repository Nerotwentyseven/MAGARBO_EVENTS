<?php
session_name('USERSESSID');
session_start();
require_once 'user_auth.php';
require_once '../db_connection.php';

$packages = [];

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
        $pkg_id = (int)$pkg['id'];

        $features = [];
        $includes = [];

        $fStmt = mysqli_prepare($conn, "SELECT feature_text FROM package_features WHERE package_id = ? ORDER BY id ASC");
        mysqli_stmt_bind_param($fStmt, "i", $pkg_id);
        mysqli_stmt_execute($fStmt);
        $fRes = mysqli_stmt_get_result($fStmt);
        while ($f = mysqli_fetch_assoc($fRes)) {
            $features[] = $f['feature_text'];
        }

        $iStmt = mysqli_prepare($conn, "SELECT include_text FROM package_includes WHERE package_id = ? ORDER BY id ASC");
        mysqli_stmt_bind_param($iStmt, "i", $pkg_id);
        mysqli_stmt_execute($iStmt);
        $iRes = mysqli_stmt_get_result($iStmt);
        while ($i = mysqli_fetch_assoc($iRes)) {
            $includes[] = $i['include_text'];
        }

        $packageBookings = 0;
        $bStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM bookings
             WHERE package_name = ?
               AND booking_status = 'Approved'"
        );
        mysqli_stmt_bind_param($bStmt, "s", $pkg['package_name']);
        mysqli_stmt_execute($bStmt);
        $bRes = mysqli_stmt_get_result($bStmt);

        if ($bRow = mysqli_fetch_assoc($bRes)) {
            $packageBookings = (int)$bRow['total'];
        }

        $percent = ($totalPackageBookings > 0)
            ? round(($packageBookings / $totalPackageBookings) * 100)
            : 0;

        $packages[] = [
            "id" => $pkg_id,
            "title" => $pkg['package_name'],
            "price_value" => (float)$pkg['price_start'],
            "price_text" => "Start from ₱" . number_format((float)$pkg['price_start']),
            "popularity" => $percent . "% popularity",
            "img" => !empty($pkg['image_path']) ? "../" . $pkg['image_path'] : "../images/default-package.jpg",
            "desc" => $pkg['description'] ?? '',
            "tagline" => $pkg['description'] ?? 'Package details available upon viewing.',
            "styling" => $features,
            "include" => $includes
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['package'] = $_POST['package_name'] ?? '';
    $_SESSION['package_price'] = $_POST['package_price'] ?? '';

    if (isset($_SESSION['service_type']) && $_SESSION['service_type'] == 'Styling & Decoration Only') {
        header("Location: booking.php");
    } else {
        header("Location: choose_menu.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo Events | Select Package</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --gold: #BF9225; --gold-hover: #a37b1f; --bg: #F0F2F5; }
        body { font-family: 'Arial', sans-serif; background-color: var(--bg); margin: 0; padding: 0; color: #333; }
        .container { max-width: 1100px; margin: 20px auto; padding: 20px; }
        .header-section { margin-bottom: 30px; text-align: center; }
        .header-section h1 { color: var(--gold); font-size: 32px; font-weight: bold; margin-bottom: 10px; }
        .header-section p { color: #666; font-size: 16px; }

        .stepper { display: flex; justify-content: center; gap: 40px; margin: 30px 0; }
        .step { width: 45px; height: 45px; border-radius: 50%; background: #9da3a8; color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .step.active { background: var(--gold); }

        .package-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .pkg-card { background: white; border: 1px solid #E0E0E0; border-radius: 15px; overflow: hidden; transition: 0.3s; cursor: pointer; display: flex; flex-direction: column; }
        .pkg-card.selected { border: 2.5px solid var(--gold); box-shadow: 0 0 15px rgba(191,146,37,0.3); }
        .pkg-card img { width: 100%; height: 200px; object-fit: cover; }

        .pkg-info { padding: 20px; flex-grow: 1; display: flex; flex-direction: column; }
        .pkg-info h3 { margin: 0 0 10px; font-size: 18px; font-weight: bold; }
        .price-text { color: var(--gold); font-weight: bold; font-size: 14px; margin-bottom: 10px; display: block; }
        .desc { font-size: 13px; color: #777; margin-bottom: 15px; line-height: 1.4; min-height: 36px; }
        .neg-text { font-size: 11px; color: #999; margin-bottom: 15px; }

        .btn-view { width: 100%; padding: 10px; border: 1px solid #ddd; background: white; border-radius: 8px; cursor: pointer; font-size: 13px; margin-bottom: 8px; font-weight: bold; color: #444; }
        .btn-select { width: 100%; padding: 10px; border: none; background: var(--gold); color: white; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 13px; }

        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: white; margin: 2vh auto; width: 95%; max-width: 480px; border-radius: 20px; display: flex; flex-direction: column; max-height: 94vh; overflow: hidden; }
        .modal-body { padding: 20px; overflow-y: auto; flex-grow: 1; text-align: left; }
        .modal-body img { width: 100%; border-radius: 15px; margin-bottom: 15px; }

        .price-box { background: #f4f4f4; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .price-box strong { color: var(--gold); font-size: 20px; }

        .info-alert { background: #FFF5F5; border: 1px solid #FED7D7; border-radius: 12px; padding: 15px; margin-bottom: 20px; }
        .info-alert h4 { color: #C53030; margin: 0 0 10px; font-size: 13px; }
        .info-alert ul { margin: 0; padding-left: 20px; color: #C53030; font-size: 11px; line-height: 1.6; }

        .tab-nav { background: #EDF2F7; border-radius: 30px; display: flex; padding: 4px; margin-bottom: 15px; }
        .tab-btn { flex: 1; padding: 10px; border: none; border-radius: 25px; cursor: pointer; background: transparent; font-weight: bold; font-size: 13px; color: #4A5568; }
        .tab-btn.active { background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); color: #1A202C; }

        .list-row { background: #F8FAFC; padding: 12px; border-radius: 10px; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; font-size: 13px; border: 1px solid #E2E8F0; }
        .list-row i { color: #38A169; }

        .addon-box { background: #EBF8FF; padding: 15px; border-radius: 10px; border: 1px solid #BEE3F8; margin-top: 15px; }
        .addon-box h4 { color: #2B6CB0; margin: 0 0 8px; font-size: 13px; }
        .addon-item { font-size: 11px; color: #2C5282; margin-bottom: 4px; }

        .modal-footer { padding: 15px; background: white; border-top: 1px solid #eee; display: flex; gap: 10px; }
        .btn-modal-close { flex: 1; padding: 12px; border: none; background: #D1D5DB; border-radius: 10px; font-weight: bold; cursor: pointer; }
        .btn-modal-select { flex: 1; padding: 12px; border: none; background: var(--gold); color: white; border-radius: 10px; font-weight: bold; cursor: pointer; }

        .bottom-nav { border-top: 1px solid #EEE; padding: 20px 0; display: flex; justify-content: space-between; align-items: center; }
        .btn-prev { padding: 12px 30px; border-radius: 8px; background: #D9D9D9; border: none; cursor: pointer; font-weight: bold; }
        .btn-next { padding: 12px 60px; border-radius: 8px; background: var(--gold); color: white; border: none; font-weight: bold; cursor: pointer; opacity: 0.5; pointer-events: none; transition: 0.3s; }
        .btn-next.active { opacity: 1; pointer-events: auto; }

        @media (max-width: 900px) {
            .package-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header-section">
        <h1>Book Your Event with Magarbo Events</h1>
        <p>Choose Your Package</p>
    </div>

    <div class="stepper">
        <div class="step active">1</div><div class="step active">2</div><div class="step">3</div><div class="step">4</div>
    </div>

    <form method="POST" id="mainForm">
        <input type="hidden" name="package_name" id="selected_pkg">
        <input type="hidden" name="package_price" id="selected_price">

        <div class="package-grid">
            <?php if (!empty($packages)): ?>
                <?php foreach ($packages as $pkg): ?>
                    <div class="pkg-card"
                         id="card-<?php echo $pkg['id']; ?>"
                         onclick="selectCard('<?php echo $pkg['id']; ?>', '<?php echo htmlspecialchars($pkg['title'], ENT_QUOTES); ?>', '<?php echo $pkg['price_value']; ?>')">

                        <img src="<?php echo htmlspecialchars($pkg['img']); ?>" alt="<?php echo htmlspecialchars($pkg['title']); ?>">

                        <div class="pkg-info">
                            <h3><?php echo htmlspecialchars($pkg['title']); ?></h3>
                            <span class="price-text"><?php echo htmlspecialchars($pkg['price_text']); ?></span>
                            <p class="neg-text" style="margin-bottom:10px; color:#BF9225; font-weight:bold;">
                                <?php echo htmlspecialchars($pkg['popularity']); ?>
                            </p>
                            <p class="desc"><?php echo htmlspecialchars($pkg['desc']); ?></p>
                            <p class="neg-text">Pricing is negotiable based on your specific needs</p>

                            <button type="button" class="btn-view" onclick='event.stopPropagation(); openModal(<?php echo json_encode($pkg, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>View Details</button>
                            <button type="button" class="btn-select">Select Package</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No active packages available.</p>
            <?php endif; ?>
        </div>

        <div class="bottom-nav">
            <button type="button" class="btn-prev" onclick="history.back()">Previous</button>
            <button type="submit" class="btn-next" id="nextBtn">Next</button>
        </div>
    </form>
</div>

<div id="pkgModal" class="modal">
    <div class="modal-content">
        <div class="modal-body">
            <img id="m-img" src="">
            <h2 id="m-title" style="margin-top:0;"></h2>
            <p id="m-tagline" style="font-size:13px; color:#666; margin-bottom:15px;"></p>

            <div class="price-box">
                <span id="m-label" style="font-size:13px; color:#888;">Starting from</span>
                <strong id="m-price"></strong>
            </div>

            <div class="info-alert">
                <h4><i class="fa-solid fa-circle-exclamation"></i> Important Booking Information:</h4>
                <ul>
                    <li><strong>₱2,000 Down Payment Required:</strong> Non-refundable deposit to secure booking.</li>
                    <li><strong>No Refund Policy:</strong> All payments are final.</li>
                    <li><strong>Event Date:</strong> Confirm at least 7 days from booking.</li>
                    <li><strong>Final Guest Count:</strong> Must be provided 48 hours before.</li>
                </ul>
            </div>

            <div class="tab-nav">
                <button type="button" class="tab-btn active" id="tab-s" onclick="switchTab('s')">Styling Features</button>
                <button type="button" class="tab-btn" id="tab-i" onclick="switchTab('i')">What's Include</button>
            </div>

            <div id="items-container"></div>

            <div id="addons" class="addon-box" style="display:none;">
                <h4>Additional Services Available</h4>
                <div class="addon-item"><i class="fa-solid fa-check"></i> Food & Catering: Customize during booking</div>
                <div class="addon-item"><i class="fa-solid fa-check"></i> Venue Arrangement: Specify your venue</div>
                <div class="addon-item"><i class="fa-solid fa-check"></i> Custom Add-ons: Extra decorations/lighting</div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-close" onclick="closeModal()">Close</button>
            <button type="button" class="btn-modal-select" id="modalConfirm">Select This Package</button>
        </div>
    </div>
</div>

<script>
let activePackage = null;

function selectCard(id, name, price) {
    document.querySelectorAll('.pkg-card').forEach(c => c.classList.remove('selected'));
    const card = document.getElementById('card-' + id);
    if (card) card.classList.add('selected');

    document.getElementById('selected_pkg').value = name;
    document.getElementById('selected_price').value = price;

    const nextBtn = document.getElementById('nextBtn');
    nextBtn.classList.add('active');
}

function openModal(pkg) {
    activePackage = pkg;

    document.getElementById('m-title').innerText = pkg.title;
    document.getElementById('m-tagline').innerText = (pkg.popularity ? pkg.popularity + " • " : "") + (pkg.tagline || pkg.desc || '');
    document.getElementById('m-price').innerText = pkg.price_text;
    document.getElementById('m-img').src = pkg.img;

    renderItems('s');
    document.getElementById('pkgModal').style.display = 'block';

    document.getElementById('modalConfirm').onclick = () => {
        selectCard(pkg.id, pkg.title, pkg.price_value);
        closeModal();
    };
}

function switchTab(tab) {
    document.getElementById('tab-s').classList.toggle('active', tab === 's');
    document.getElementById('tab-i').classList.toggle('active', tab === 'i');
    renderItems(tab);
}

function renderItems(tab) {
    const container = document.getElementById('items-container');
    const addons = document.getElementById('addons');

    if (!activePackage) return;

    const items = (tab === 's') ? activePackage.styling : activePackage.include;

    container.innerHTML = (items && items.length)
        ? items.map(i => `<div class="list-row"><i class="fa-solid fa-check"></i> ${i}</div>`).join('')
        : `<div class="list-row"><i class="fa-solid fa-circle-info"></i> No items available.</div>`;

    addons.style.display = (tab === 'i') ? 'block' : 'none';
}

function closeModal() {
    document.getElementById('pkgModal').style.display = 'none';
}
</script>
</body>
</html>