<?php
session_name('ADMINSESSID');
require_once 'admin_auth.php';
require_once '../db_connection.php';

$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

$menu_sets = [];
$totalMenus = 0;
$totalItems = 0;
$totalPrice = 0;
$highestPopularity = 0;

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

$sql = "SELECT * FROM menus ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($menu = mysqli_fetch_assoc($result)) {
        $menu_id = (int)$menu['id'];

        $itemsSql = "SELECT category, item_name FROM menu_items WHERE menu_id = ? ORDER BY id ASC";
        $stmtItems = mysqli_prepare($conn, $itemsSql);
        mysqli_stmt_bind_param($stmtItems, "i", $menu_id);
        mysqli_stmt_execute($stmtItems);
        $itemsResult = mysqli_stmt_get_result($stmtItems);

        $categories = [];

        while ($itemRow = mysqli_fetch_assoc($itemsResult)) {
            $cat = trim($itemRow['category'] ?? '');
            $itemName = trim($itemRow['item_name'] ?? '');

            if ($cat === '' || $itemName === '') continue;

            if (!isset($categories[$cat])) {
                $categories[$cat] = [];
            }

            $categories[$cat][] = $itemName;
            $totalItems++;
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

        $menu_sets[] = [
            "id" => $menu['id'],
            "name" => $menu['menu_name'],
            "popularity" => $percent . "% popularity",
            "price" => $menu['price'],
            "price_unit" => $menu['price_unit'] ?? 'Per Head',
            "description" => $menu['description'] ?? '',
            "categories" => $categories
        ];

        $totalMenus++;
        $totalPrice += (float)$menu['price'];

        if ($percent > $highestPopularity) {
            $highestPopularity = $percent;
        }
    }
}

$avgPrice = $totalMenus > 0 ? $totalPrice / $totalMenus : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Menu Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="menu_management.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">
    <div class="ADMIN-MENU">
        <?php include('sidebar.php'); ?>

        <div class="main-content">
            <div class="header-section">
                <div class="title-group">
                    <h1 class="page-title">Menu Management</h1>
                    <p class="subtitle">Create and manage menu sets for your events and catering services</p>
                </div>
                <button class="add-menu-btn" onclick="openAddModal()"><i class="fa fa-plus"></i> Add Menu Set</button>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-label">Total Menu Sets</span>
                    <span class="stat-number"><?php echo $totalMenus; ?></span>
                    <span class="stat-desc">All menu sets</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Total Items</span>
                    <span class="stat-number"><?php echo $totalItems; ?></span>
                    <span class="stat-desc">All menu items</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Avg. Price</span>
                    <span class="stat-number">₱<?php echo number_format($avgPrice, 0); ?></span>
                    <span class="stat-desc">Average price</span>
                </div>
                <div class="stat-card">
                    <span class="stat-label">Most Popular</span>
                    <span class="stat-number"><?php echo $highestPopularity; ?>%</span>
                    <span class="stat-desc">Highest popularity</span>
                </div>
            </div>

            <div class="section-divider">
                <i class="fa fa-utensils"></i> Menu Sets <span class="badge"><?php echo $totalMenus; ?> sets</span>
            </div>

            <div class="menu-grid">
                <?php foreach($menu_sets as $menu): ?>
                <div class="menu-box">
                    <div class="menu-header">
                        <div class="name-col">
                            <h3><?php echo htmlspecialchars($menu['name']); ?></h3>
                            <p><?php echo htmlspecialchars($menu['popularity']); ?></p>
                        </div>
                        <div class="price-col">
                            ₱<?php echo number_format($menu['price'], 2); ?>
                            <span><?php echo htmlspecialchars($menu['price_unit'] ?: 'Per Head'); ?></span>
                        </div>
                    </div>

                    <p class="menu-desc"><?php echo htmlspecialchars($menu['description']); ?></p>

                    <?php foreach($menu['categories'] as $cat_name => $items): ?>
                    <div class="category">
                        <span class="cat-label"><?php echo htmlspecialchars($cat_name); ?></span>
                        <ul>
                            <?php foreach($items as $item): ?>
                                <li><span class="orange-dot"></span> <?php echo htmlspecialchars($item); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>

                    <div class="actions">
                        <button class="edit-btn" onclick='openEditModal(<?php echo json_encode($menu, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                            <i class="fa fa-edit"></i> Edit
                        </button>
                        <a class="delete-btn"
                           href="delete_menu.php?id=<?php echo $menu['id']; ?>"
                           onclick="return openDeleteConfirm(event, this.href, 'Delete this menu set?')">
                            <i class="fa fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="menuModal" class="mg-modal-overlay">
        <div class="mg-modal-content">
            <form id="menuSetForm" method="POST" action="save_menu.php">
                <input type="hidden" name="menu_id" id="editMenuId">
                <input type="hidden" name="menu_items_json" id="menuItemsJson">

                <div class="mg-modal-header">
                    <div>
                        <h2 class="mg-modal-title" id="modalTitle">Add New Menu Set</h2>
                        <p class="mg-modal-subtitle">Configure menu details and items</p>
                    </div>
                    <div class="mg-header-btns">
                        <button type="button" class="mg-btn-cancel" onclick="closeMenuModal()">Cancel</button>
                        <button type="submit" class="mg-btn-submit" id="saveBtn">Create Menu Set</button>
                    </div>
                </div>

                <div class="mg-form-grid">
                    <div class="mg-input-group">
                        <label class="mg-label">Menu Name</label>
                        <input type="text" id="editMenuName" name="menu_name" class="mg-input-main" placeholder="Enter menu name" required>
                    </div>

                    <div class="mg-input-group">
                        <label class="mg-label">Price (₱)</label>
                        <input type="number" step="0.01" id="editMenuPrice" name="price" class="mg-input-main" placeholder="0" required>
                    </div>

                    <div class="mg-input-group">
                        <label class="mg-label">Price Unit</label>
                        <input type="text" id="editMenuPriceUnit" name="price_unit" class="mg-input-main" placeholder="Enter price unit" required>
                    </div>

                    <div class="mg-input-group" style="grid-column: span 3;">
                        <label class="mg-label">Description</label>
                        <textarea id="editMenuDescription" name="description" class="mg-input-main" placeholder="Enter menu description"></textarea>
                    </div>
                </div>

                <div class="mg-dynamic-header">
                    <h3 class="mg-menu-items-title">Menu Categories & Items</h3>
                    <button type="button" class="mg-btn-add-category" onclick="addCategoryBlock()">
                        <i class="fa fa-plus"></i> Add Category
                    </button>
                </div>

                <div id="dynamicCategoryContainer"></div>
            </form>
        </div>
    </div>

    <div id="deleteConfirmModal" class="confirm-overlay">
        <div class="confirm-card">
            <div class="confirm-icon-wrap delete-mode">
                <i class="fas fa-trash"></i>
            </div>

            <h3>Delete Menu</h3>
            <p id="deleteConfirmMessage">Delete this menu set?</p>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn cancel" onclick="closeDeleteConfirm()">No</button>
                <button type="button" class="confirm-btn delete-confirm" id="deleteConfirmProceedBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
    let deleteConfirmUrl = '';
    let categoryCounter = 0;

    function openDeleteConfirm(event, url, message) {
        if (event) event.preventDefault();
        deleteConfirmUrl = url;
        document.getElementById('deleteConfirmMessage').innerText = message;
        document.getElementById('deleteConfirmModal').style.display = 'flex';
        return false;
    }

    function closeDeleteConfirm() {
        document.getElementById('deleteConfirmModal').style.display = 'none';
        deleteConfirmUrl = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const deleteBtn = document.getElementById('deleteConfirmProceedBtn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function () {
                if (deleteConfirmUrl) {
                    window.location.href = deleteConfirmUrl;
                }
            });
        }
    });

    function createCategoryBlock(categoryName = '', items = [], insertAtTop = true) {
        categoryCounter++;
        const blockId = 'categoryBlock_' + categoryCounter;

        const block = document.createElement('div');
        block.className = 'mg-category-card dynamic-category-card';
        block.id = blockId;

        block.innerHTML = `
            <div class="mg-category-top">
                <input type="text" class="mg-input-main mg-category-name" placeholder="Category name (e.g. Soup)" value="${escapeHtml(categoryName)}">
                <button type="button" class="mg-btn-remove-category" onclick="removeCategoryBlock('${blockId}')">
                    <i class="fa fa-trash"></i>
                </button>
            </div>

            <div class="mg-category-header">
                <div class="mg-add-row full-width">
                    <input type="text" class="mg-input-item category-item-input" placeholder="Add item...">
                    <button type="button" class="mg-btn-plus" onclick="addDynamicItem('${blockId}')">+</button>
                </div>
            </div>

            <div class="mg-item-list"></div>
        `;

        const container = document.getElementById('dynamicCategoryContainer');

        if (insertAtTop && container.firstChild) {
            container.insertBefore(block, container.firstChild);
        } else {
            container.appendChild(block);
        }

        if (Array.isArray(items)) {
            items.forEach(item => {
                addDynamicItem(blockId, item);
            });
        }
    }

    function addCategoryBlock() {
        createCategoryBlock('', [], true);
    }

    function removeCategoryBlock(blockId) {
        const block = document.getElementById(blockId);
        if (block) block.remove();
    }

    function addDynamicItem(blockId, itemName = '') {
        const block = document.getElementById(blockId);
        if (!block) return;

        const input = block.querySelector('.category-item-input');
        const list = block.querySelector('.mg-item-list');

        const value = (itemName || input.value).trim();
        if (value === '') return;

        const itemDiv = document.createElement('div');
        itemDiv.className = 'mg-added-item';
        itemDiv.innerHTML = `
            <span>${escapeHtml(value)}</span>
            <i class="fa-regular fa-trash-can mg-delete-icon" onclick="this.parentElement.remove()"></i>
        `;
        list.appendChild(itemDiv);

        if (!itemName) input.value = '';
    }

    function collectDynamicMenuData() {
        const data = {};
        const blocks = document.querySelectorAll('.dynamic-category-card');

        blocks.forEach(block => {
            const categoryInput = block.querySelector('.mg-category-name');
            const categoryName = (categoryInput?.value || '').trim();
            if (categoryName === '') return;

            const items = [];
            block.querySelectorAll('.mg-added-item span').forEach(span => {
                const val = span.innerText.trim();
                if (val !== '') items.push(val);
            });

            if (items.length > 0) {
                data[categoryName] = items;
            }
        });

        return data;
    }

    document.getElementById('menuSetForm').addEventListener('submit', function () {
        const data = collectDynamicMenuData();
        document.getElementById('menuItemsJson').value = JSON.stringify(data);
    });

    function openAddModal() {
        const modal = document.getElementById('menuModal');
        modal.style.display = 'flex';

        document.getElementById('menuSetForm').reset();
        document.getElementById('editMenuId').value = '';
        document.getElementById('editMenuDescription').value = '';
        document.getElementById('editMenuPriceUnit').value = '';
        document.getElementById('dynamicCategoryContainer').innerHTML = '';

        document.getElementById('modalTitle').innerText = "Add New Menu Set";
        document.getElementById('saveBtn').innerText = "Create Menu Set";
    }

    function openEditModal(menuData) {
        const modal = document.getElementById('menuModal');
        modal.style.display = 'flex';

        document.getElementById('dynamicCategoryContainer').innerHTML = '';

        document.getElementById('editMenuId').value = menuData.id;
        document.getElementById('editMenuName').value = menuData.name;
        document.getElementById('editMenuPrice').value = menuData.price;
        document.getElementById('editMenuPriceUnit').value = menuData.price_unit || '';
        document.getElementById('editMenuDescription').value = menuData.description || '';

        document.getElementById('modalTitle').innerText = "Edit Menu Set";
        document.getElementById('saveBtn').innerText = "Save Changes";

        if (menuData.categories && Object.keys(menuData.categories).length > 0) {
            const entries = Object.entries(menuData.categories);

            for (let i = entries.length - 1; i >= 0; i--) {
                const [category, items] = entries[i];
                createCategoryBlock(category, items || [], true);
            }
        } else {
            createCategoryBlock('', [], true);
        }
    }

    function closeMenuModal() {
        document.getElementById('menuModal').style.display = 'none';
    }

    window.onclick = function(event) {
        let modal = document.getElementById('menuModal');
        if (event.target == modal) {
            closeMenuModal();
        }
    }

    function escapeHtml(str) {
        return String(str)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
    </script>
</body>
</html>