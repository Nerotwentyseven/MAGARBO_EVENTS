<?php
session_name('USERSESSID');
session_start();
$pageTitle = "Catering Menu | Magarbo Events";
require_once '../db_connection.php';

$menus = [];

$totalMenuBookings = 0;
$totalStmt = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE menu_selection IS NOT NULL
       AND menu_selection != ''
       AND booking_status = 'Approved'"
);

if ($totalStmt && $rowTotal = mysqli_fetch_assoc($totalStmt)) {
    $totalMenuBookings = (int)$rowTotal['total'];
}

$sql = "SELECT * FROM menus WHERE status = 'Active' ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($menu = mysqli_fetch_assoc($result)) {
        $menu_id = (int)$menu['id'];

        $itemsSql = "SELECT category, item_name FROM menu_items WHERE menu_id = ? ORDER BY id ASC";
        $stmtItems = mysqli_prepare($conn, $itemsSql);
        mysqli_stmt_bind_param($stmtItems, "i", $menu_id);
        mysqli_stmt_execute($stmtItems);
        $itemsResult = mysqli_stmt_get_result($stmtItems);

        $grouped = [];

        while ($item = mysqli_fetch_assoc($itemsResult)) {
            $cat = trim($item['category'] ?? '');
            $itemName = trim($item['item_name'] ?? '');

            if ($cat === '' || $itemName === '') continue;

            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }

            $grouped[$cat][] = $itemName;
        }

        $menuBookings = 0;
        $likeName = "%" . $menu['menu_name'] . "%";

        $bStmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS total
             FROM bookings
             WHERE menu_selection LIKE ?
               AND booking_status = 'Approved'"
        );
        mysqli_stmt_bind_param($bStmt, "s", $likeName);
        mysqli_stmt_execute($bStmt);
        $bRes = mysqli_stmt_get_result($bStmt);

        if ($bRow = mysqli_fetch_assoc($bRes)) {
            $menuBookings = (int)$bRow['total'];
        }

        $percent = ($totalMenuBookings > 0)
            ? round(($menuBookings / $totalMenuBookings) * 100)
            : 0;

        $menus[] = [
            "title" => $menu['menu_name'],
            "popularity" => $percent . "% popularity",
            "price_text" => ((float)$menu['price'] > 0)
                ? "₱" . number_format($menu['price'], 0)
                : "Contact for pricing",
            "price_unit" => trim($menu['price_unit'] ?? 'Per Head'),
            "is_contact" => ((float)$menu['price'] <= 0),
            "categories" => $grouped
        ];
    }
}

function getCategoryMeta($categoryName) {
    $normalized = strtolower(trim($categoryName));

    if (str_contains($normalized, 'soup')) {
        return ['icon' => 'fa-bowl-food', 'class' => 'icon-soup'];
    }

    if (str_contains($normalized, 'hot')) {
        return ['icon' => 'fa-fire-burner', 'class' => 'icon-hot'];
    }

    if (str_contains($normalized, 'dessert')) {
        return ['icon' => 'fa-ice-cream', 'class' => 'icon-dessert'];
    }

    if (str_contains($normalized, 'drink') || str_contains($normalized, 'juice') || str_contains($normalized, 'beverage')) {
        return ['icon' => 'fa-glass-water', 'class' => 'icon-drink'];
    }

    return ['icon' => 'fa-utensils', 'class' => 'icon-default'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="menu.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

    <?php include 'header.php'; ?>

        <section class="menu-hero">
            <h1><i class="fa-solid fa-utensils"></i> Our Food Menu</h1>
            <p>Discover our carefully crafted menu selections featuring Filipino favorites and international cuisine. Browse our available menu sets for your event.</p>
        </section>

        <main class="menu-container">
        <section class="menu-grid">
            <?php if (!empty($menus)): ?>
                <?php foreach ($menus as $menu): ?>
                <div class="menu-card">
                    <div class="menu-card-header">
                        <span class="badge"><?php echo htmlspecialchars($menu['popularity']); ?></span>
                        <div class="price-info">
                            <span class="price" <?php echo $menu['is_contact'] ? 'style="font-size: 1rem;"' : ''; ?>>
                                <?php echo htmlspecialchars($menu['price_text']); ?>
                            </span>
                            <span class="per-head"><?php echo htmlspecialchars($menu['price_unit'] ?: 'Per Head'); ?></span>
                        </div>
                    </div>

                    <h3><?php echo htmlspecialchars($menu['title']); ?></h3>

                    <?php if (!empty($menu['categories'])): ?>
                        <?php foreach ($menu['categories'] as $categoryName => $items): ?>
                                <?php
                                    if (empty($items)) continue;
                                    $catMeta = getCategoryMeta($categoryName);
                                ?>
                                <div class="menu-section">
                                    <h4 class="menu-section-title">
                                        <i class="fa-solid <?php echo $catMeta['icon']; ?> <?php echo $catMeta['class']; ?>"></i>
                                        <?php echo strtoupper(htmlspecialchars($categoryName)); ?>
                                    </h4>
                                <ul>
                                    <?php foreach ($items as $item): ?>
                                        <li><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="menu-section">
                            <ul>
                                <li>No menu items listed.</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No active menus available.</p>
            <?php endif; ?>
        </section>
    </main>

    <section class="cta-banner">
        <div class="cta-content">
            <h2>Ready to Book Your Event?</h2>
            <p>Choose from our delicious menu options and let us cater your special occasion</p>
            <div class="cta-buttons">
                <a href="choose_service.php" class="btn btn-light">Book Event Now</a>
                <a href="packages.php" class="btn btn-outline-light">View Packages</a>
            </div>
        </div>
    </section>

    <?php include 'online_heartbeat.php'; ?>

</body>
</html>