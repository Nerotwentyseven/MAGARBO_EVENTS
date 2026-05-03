<?php
session_name('USERSESSID');
session_start();
require_once 'user_auth.php';
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
            "id" => $menu['id'],
            "title" => $menu['menu_name'],
            "price" => (float)$menu['price'],
            "price_unit" => trim($menu['price_unit'] ?? 'Per Head'),
            "pop" => $percent . "% popularity",
            "items" => $grouped
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['menu_cart'] = $_POST['cart_json'];
    $_SESSION['total_pax'] = $_POST['total_pax'];
    $_SESSION['total_cost'] = $_POST['total_cost'];
    header("Location: booking.php");
    exit();
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
    <title>Select Your Menu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="choose_menu.css?v=<?php echo time(); ?>">
</head>
<body>
<div class="container">
    <header>
        <div class="back-action" onclick="window.location.href='index.php'">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to Home</span>
        </div>
        <div class="title-section">
            <h1>Select Your Menu</h1>
            <p>Choose from our delicious menu options for your package</p>
        </div>
        <div class="steps">
            <div class="step active">1</div>
            <div class="step active">2</div>
            <div class="step">3</div>
            <div class="step">4</div>
        </div>
    </header>

    <div class="main-layout">
        <div class="menu-grid">
            <?php if (!empty($menus)): ?>
                <?php foreach($menus as $m): ?>
                <div class="menu-card"
                     data-id="<?= (int)$m['id'] ?>"
                     data-name="<?= htmlspecialchars($m['title']) ?>"
                     data-price="<?= (float)$m['price'] ?>">
                    <div class="tag"><?= htmlspecialchars($m['pop']) ?></div>

                    <div class="price">
                        <?= $m['price'] > 0 ? "₱".number_format($m['price'], 0) : "Contact pricing" ?>
                        <span><?= htmlspecialchars($m['price_unit'] ?: 'Per Head') ?></span>
                    </div>

                    <h3 class="menu-title"><?= htmlspecialchars($m['title']) ?></h3>

                    <div class="menu-items">
                        <?php if (!empty($m['items'])): ?>
                            <?php foreach($m['items'] as $cat => $list): ?>
                                <?php if (empty($list)) continue; ?>
                                <div class="item-row">
                                    <?php $meta = getCategoryMeta($cat); ?>
                                        <i class="fa-solid <?= $meta['icon']; ?> <?= $meta['class']; ?>"></i>
                                    <div>
                                        <strong><?= htmlspecialchars($cat) ?></strong>
                                        <ul>
                                            <?php foreach($list as $i): ?>
                                                <li><?= htmlspecialchars($i) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>No menu items available.</p>
                        <?php endif; ?>
                    </div>

                    <div class="qty-box">
                        <button class="qty-btn" onclick="updateCart(<?= (int)$m['id'] ?>, -1)">-</button>
                        <input type="text" id="qty-<?= (int)$m['id'] ?>" value="0" readonly>
                        <button class="qty-btn" onclick="updateCart(<?= (int)$m['id'] ?>, 1)">+</button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No active menus available.</p>
            <?php endif; ?>
        </div>

        <div class="cart-sidebar">
            <div class="cart-header"><i class="fa-solid fa-cart-shopping" style="color:var(--gold)"></i> Your Cart</div>
            <div id="cart-list">No menu selected yet.</div>
            <div class="summary">
                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                    <span>Total Persons:</span><span id="pax-val">0</span>
                </div>
                <div class="total-row">
                    <span>Total Cost:</span><span id="cost-val" style="color:var(--gold);">₱0</span>
                </div>
                <form action="" method="POST" id="menuForm">
                    <input type="hidden" name="cart_json" id="cart_json">
                    <input type="hidden" name="total_pax" id="hidden_pax">
                    <input type="hidden" name="total_cost" id="hidden_cost">
                    <button type="submit" class="btn-details" id="continueBtn" disabled>
                        Continue to Event Details
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cart = {};

function updateCart(id, change) {
    const card = document.querySelector(`.menu-card[data-id="${id}"]`);
    const name = card.dataset.name;
    const price = parseFloat(card.dataset.price);
    const input = document.getElementById(`qty-${id}`);
    let currentQty = parseInt(input.value);
    let newQty = currentQty + change;

    if (newQty < 0) return;

    input.value = newQty;

    if (newQty > 0) {
        cart[id] = { name, price, qty: newQty };
    } else {
        delete cart[id];
    }

    renderCart();
}

function renderCart() {
    const list = document.getElementById('cart-list');
    const paxEl = document.getElementById('pax-val');
    const costEl = document.getElementById('cost-val');
    const continueBtn = document.getElementById('continueBtn');

    let html = '';
    let totalPax = 0;
    let totalCost = 0;

    Object.values(cart).forEach(item => {
        totalPax += item.qty;
        totalCost += (item.qty * item.price);

        html += `<div style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #f9f9f9; padding-bottom:5px;">
                    <span style="max-width:180px">${item.name} (x${item.qty})</span>
                    <span style="font-weight:bold">₱${(item.qty * item.price).toLocaleString()}</span>
                 </div>`;
    });

    list.innerHTML = html || 'No menu selected yet.';
    paxEl.innerText = totalPax;
    costEl.innerText = '₱' + totalCost.toLocaleString();

    document.getElementById('cart_json').value = JSON.stringify(cart);
    document.getElementById('hidden_pax').value = totalPax;
    document.getElementById('hidden_cost').value = totalCost;

    continueBtn.disabled = totalPax <= 0;
}

document.getElementById('menuForm').addEventListener('submit', function(e) {
    if (Object.keys(cart).length === 0) {
        e.preventDefault();
        alert("Please select at least one menu before continuing.");
    }
});

renderCart();
</script>
</body>
</html>