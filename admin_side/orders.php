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

$sql = "SELECT 
            b.*,
            CONCAT(u.firstname, ' ', u.lastname) AS full_name,
            u.email AS user_email,
            u.phone AS user_phone,

            IFNULL(b.downpayment, 0) AS downpayment_amount,

            CASE
                WHEN b.downpayment IS NOT NULL AND b.downpayment > 0 THEN 'Paid'
                WHEN bp.payment_status = 'Paid' THEN 'Paid'
                ELSE 'Pending'
            END AS payment_status

        FROM bookings b
        JOIN users u ON b.user_id = u.id
        LEFT JOIN booking_payments bp 
            ON bp.id = (
                SELECT bp2.id
                FROM booking_payments bp2
                WHERE bp2.booking_id = b.id
                ORDER BY bp2.paid_at DESC, bp2.id DESC
                LIMIT 1
            )
        ORDER BY
            CASE
                WHEN b.booking_status = 'Pending' THEN 1
                WHEN b.booking_status = 'Approved' THEN 2
                WHEN b.booking_status = 'Cancelled' THEN 3
                ELSE 4
            END ASC,

            CASE
                WHEN b.booking_status = 'Pending' THEN b.created_at
                ELSE NULL
            END DESC,

            CASE
                WHEN b.booking_status = 'Approved' THEN b.event_date
                ELSE NULL
            END ASC,

            CASE
                WHEN b.booking_status = 'Approved' THEN b.created_at
                ELSE NULL
            END ASC,

            CASE
                WHEN b.booking_status = 'Cancelled' THEN b.cancelled_at
                ELSE NULL
            END DESC,

            b.id ASC";

$result = mysqli_query($conn, $sql);

$data = [];
$totalOrders = 0;
$pendingOrders = 0;
$approvedOrders = 0;
$cancelledOrders = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $totalOrders++;

        if (($row['booking_status'] ?? '') === 'Pending') $pendingOrders++;
        if (($row['booking_status'] ?? '') === 'Approved') $approvedOrders++;
        if (($row['booking_status'] ?? '') === 'Cancelled') $cancelledOrders++;

        // gamitin ang nasa database na display_order_id
        if (empty($row['display_order_id'])) {
            $row['display_order_id'] = 'ORD-' . $row['id'];
        }

        $data[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Orders Management | Magarbo Events</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
        <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
        <link rel="stylesheet" href="orders.css?v=<?php echo time(); ?>" />
    </head>
    <body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

    <div class="admin-main-container">
        <?php include('sidebar.php'); ?>

        <main class="main-content-area">
            <header class="page-header">
    <div class="header-left">
        <h1>Orders Management</h1>
        <p>Manage all event bookings and orders</p>
    </div>

    <div class="header-actions">
        <div class="date-filter-wrap">
            <button type="button" class="btn-date-trigger" id="dateRangeBtn" onclick="toggleDateDropdown()">
                <i class="far fa-calendar-alt"></i>
                <span>Date Range</span>
                <i class="fas fa-chevron-down date-caret"></i>
            </button>

            <div class="date-range-dropdown" id="dateRangeDropdown">
                <div class="date-range-dropdown-header">
                    <h4>Date Range</h4>
                    <button type="button" class="date-range-close" onclick="closeDateDropdown()">&times;</button>
                </div>

                <div class="date-range-fields">
                    <div class="date-input-wrap">
                        <label>From</label>
                        <input type="date" id="dateFrom">
                    </div>
                    <div class="date-input-wrap">
                        <label>To</label>
                        <input type="date" id="dateTo">
                    </div>
                </div>

                <div class="date-range-actions">
                    <button type="button" class="btn-date-clear" onclick="clearDateRange()">Clear</button>
                    <button type="button" class="btn-date-apply" onclick="applyDateRange()">Apply</button>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="msg-stats-grid">
    <div class="msg-stat-card">
        <span class="stat-tag">Total Orders</span>
        <span class="stat-num" id="stat-total"><?php echo $totalOrders; ?></span>
        <span class="stat-note">All booking requests received</span>
    </div>

    <div class="msg-stat-card">
        <span class="stat-tag" style="color:#bf9225;">Pending</span>
        <span class="stat-num" id="stat-pending" style="color:#bf9225;"><?php echo $pendingOrders; ?></span>
        <span class="stat-note">Waiting for admin review</span>
    </div>

    <div class="msg-stat-card">
        <span class="stat-tag" style="color:#2b8a3e;">Approved</span>
        <span class="stat-num" id="stat-approved" style="color:#2b8a3e;"><?php echo $approvedOrders; ?></span>
        <span class="stat-note">Accepted booking requests</span>
    </div>

    <div class="msg-stat-card">
        <span class="stat-tag" style="color:#dc2626;">Cancelled</span>
        <span class="stat-num" id="stat-cancelled" style="color:#dc2626;"><?php echo $cancelledOrders; ?></span>
        <span class="stat-note">Cancelled within booking records</span>
    </div>
</div>

<div class="orders-toolbar">
    <div class="filter-tabs-container">
        <button class="filter-btn active" onclick="filterOrders('all', this)">All Orders</button>
        <button class="filter-btn" onclick="filterOrders('pending', this)">Pending</button>
        <button class="filter-btn" onclick="filterOrders('approved', this)">Approved</button>
        <button class="filter-btn" onclick="filterOrders('cancelled', this)">Cancelled</button>
    </div>

    <div class="search-box-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="orderSearchInput" placeholder="Search customer, event, or order ID..." onkeyup="applyAllOrderFilters()">
    </div>
</div>

<div class="orders-table-container">
    
                <table class="orders-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="ordersTableBody">
                    <?php

                    if (!empty($data)) {
                        foreach ($data as $o) {

                            $menuItems = [];
                            $hasFoods = false;

                            if (!empty($o['menu_selection']) && $o['menu_selection'] !== 'None') {
                                $decodedMenu = json_decode($o['menu_selection'], true);

                                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMenu)) {
                                    foreach ($decodedMenu as $key => $item) {
                                        if (is_array($item)) {
                                            $itemName = $item['name'] ?? 'Selected Menu';
                                            $itemPrice = isset($item['price']) ? (float)$item['price'] : 0;

                                            // quantity support: qty or quantity, default 1
                                            $itemQty = 1;
                                            if (isset($item['qty']) && is_numeric($item['qty'])) {
                                                $itemQty = (int)$item['qty'];
                                            } elseif (isset($item['quantity']) && is_numeric($item['quantity'])) {
                                                $itemQty = (int)$item['quantity'];
                                            }

                                            $menuItems[] = [
                                                'name'     => $itemName,
                                                'price'    => $itemPrice,
                                                'qty'      => $itemQty,
                                                'subtotal' => $itemPrice * $itemQty
                                            ];
                                        }
                                    }

                                    if (!empty($menuItems)) {
                                        $hasFoods = true;
                                    }
                                } else {
                                    $menuItems[] = [
                                        'name'     => $o['menu_selection'],
                                        'price'    => 0,
                                        'qty'      => 1,
                                        'subtotal' => 0
                                    ];
                                    $hasFoods = true;
                                }
                            }

                            $modalData = [
                                "id"        => $o['display_order_id'],
                                "name"      => $o['full_name'],
                                "event"     => $o['event_type'],
                                "date"      => date("M d, Y", strtotime($o['event_date'])),
                                "email"     => $o['user_email'],
                                "phone"     => $o['user_phone'] ?? 'N/A',
                                "service"   => $o['service_type'] ?? 'N/A',
                                "venue"     => $o['venue'] ?? 'N/A',
                                "time" => (!empty($o['event_time']) && $o['event_time'] !== 'N/A')
                                ? $o['event_time']
                                : 'N/A',
                                "religion"  => !empty($o['religion']) ? $o['religion'] : 'N/A',
                                "package"   => !empty($o['package_name']) ? $o['package_name'] : 'N/A',
                                "notes"     => $o['request'] ?? 'No notes',
                                "selected_theme" => $o['selected_theme'] ?? 'N/A',
                                "downpayment" => isset($o['downpayment_amount']) ? (float)$o['downpayment_amount'] : 0,
                                "payment_status" => $o['payment_status'] ?? 'Pending',
                                "hasFoods"  => $hasFoods,
                                "menuItems" => $menuItems
                            ];

                            $json_data = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
                            $status = strtolower($o['booking_status']);
                            ?>
                            <tr
                                data-status="<?php echo $status; ?>"
                                data-date="<?php echo htmlspecialchars($o['event_date']); ?>"
                                data-search="<?php echo strtolower(htmlspecialchars($o['display_order_id'] . ' ' . $o['full_name'] . ' ' . $o['event_type'])); ?>"
                            >
                                <td><?php echo htmlspecialchars($o['display_order_id']); ?></td>
                                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($o['event_type']); ?></td>
                                <td><?php echo date("m/d/Y", strtotime($o['event_date'])); ?></td>
                                <td>
                                    <span class="status-tag <?php echo $status; ?>">
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td class="action-group">
                                <?php if ($o['booking_status'] === 'Pending'): ?>
                                    <a href="update_status.php?id=<?php echo $o['id']; ?>&status=Approved"
                                    class="btn-action-icon check"
                                    onclick="return openConfirm(event, this.href, 'Approve this booking?')">
                                        <i class="fas fa-check"></i>
                                    </a>

                                    <a href="update_status.php?id=<?php echo $o['id']; ?>&status=Cancelled"
                                    class="btn-action-icon times"
                                    onclick="return openConfirm(event, this.href, 'Cancel this booking?')">
                                        <i class="fas fa-times"></i>
                                    </a>
                                <?php endif; ?>

                                <?php
                                $canUndo = false;
                                if (
                                    $o['booking_status'] === 'Cancelled' &&
                                    !empty($o['cancelled_at']) &&
                                    strtotime($o['cancelled_at']) >= strtotime('-3 days')
                                ) {
                                    $canUndo = true;
                                }
                                ?>

                                <button class="btn-action-icon eye"
                                        onclick='openDetails(<?php echo $json_data; ?>)'>
                                    <i class="fas fa-eye"></i>
                                </button>

                                <?php if ($canUndo): ?>
                                    <a href="update_status.php?id=<?php echo $o['id']; ?>&status=UndoCancel"
                                    class="btn-action-icon undo"
                                    onclick="return openConfirm(event, this.href, 'Undo this cancelled order?')">
                                        <i class="fas fa-rotate-left"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                            </tr>
                    <?php
                        }
                    } else {
                        echo "<tr><td colspan='6' style='text-align:center;'>No bookings found.</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div id="detailsModal" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-top">
                <div>
                    <h2 id="m-id">Order Details</h2>
                    <p>Complete information for this booking</p>
                </div>
                <span class="close-x" onclick="closeModal()">&times;</span>
            </div>
            
            <div class="modal-body-scroll">
                <div class="modal-grid">
                    <div class="modal-col">
                        <div class="info-card">
                            <label>CUSTOMER INFORMATION</label>
                            <div class="card-body">
                                <small>Full Name</small><p id="m-name"></p>
                                <small>Email Address</small><p id="m-email"></p>
                                <small>Contact Number</small><p id="m-phone"></p>
                            </div>
                        </div>
                        <div class="info-card">
                            <label>EVENT LOGISTICS</label>
                            <div class="card-body">
                                <div style="display:flex; justify-content:space-between;">
                                    <div><small>Event Type</small><p id="m-event"></p></div>
                                    <div><small>Date</small><p id="m-date"></p></div>
                                </div>
                                <div class="event-logistics-row" style="margin-top:10px;">
                                    <div class="event-logistics-item">
                                        <small>Time</small>
                                        <p id="m-time"></p>
                                    </div>
                                </div>

                                <div class="event-logistics-row venue-row" style="margin-top:10px;">
                                    <div class="event-logistics-item full-width">
                                        <small>Venue</small>
                                        <p id="m-venue"></p>
                                    </div>
                                </div>
                                <div style="margin-top:10px;"><small>Religion</small><p id="m-religion"></p></div>
                                <div style="margin-top:10px;"><small>Package</small><p id="m-package"></p></div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-col">
                        <div class="info-card">
                            <label>SERVICE TYPE</label>
                            <div class="card-body">
                                <p id="m-service"></p>
                                <small id="service-desc" style="text-transform:none;"></small>
                            </div>
                        </div>
                        <div class="info-card">
                            <label>PAYMENT</label>
                            <div class="card-body">
                                <small>Downpayment</small>
                                <p id="m-downpayment" style="font-size:16px; font-weight:800;">₱0.00</p>

                                <small style="margin-top:10px; display:block;">Payment Status</small>
                                <p id="m-payment-status" style="font-size:14px; font-weight:700; color:#bf9225;">Pending</p>
                            </div>
                        </div>
                        <div class="info-card">
                            <label>SPECIAL NOTES</label>
                            <div id="m-notes" class="notes-box-v2" ></div>
                        </div>
                        <div class="info-card">
                            <label>SELECTED THEME</label>
                            <div class="card-body">
                                <p id="m-selected-theme">N/A</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="food-section" style="display:none; margin-top:10px;">
    <div class="food-card">
        <div id="m-menu-list"></div>
        <div class="food-row-total" style="margin-top:10px;">
            <span>Total Food Cost</span>
            <span id="m-menu-total"></span>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>

    <div id="confirmModal" class="confirm-overlay">
        <div class="confirm-card">
            <div class="confirm-icon-wrap">
                <i class="fas fa-exclamation"></i>
            </div>

            <h3>Confirm Action</h3>
            <p id="confirmMessage">Are you sure you want to continue?</p>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn cancel" onclick="closeConfirm()">No</button>
                <button type="button" class="confirm-btn approve" id="confirmProceedBtn">Yes</button>
            </div>
        </div>
    </div>

    <div id="errorModal" class="confirm-overlay">
        <div class="confirm-card">
            <div class="confirm-icon-wrap" style="background:#fef2f2; color:#dc2626;">
                <i class="fas fa-ban"></i>
            </div>
            <h3>Cannot Approve</h3>
            <p id="errorMessage">This date is already fully booked.</p>
            <div class="confirm-actions">
                <button type="button" class="confirm-btn approve" style="background:#dc2626;" onclick="document.getElementById('errorModal').style.display='none'">Okay</button>
            </div>
        </div>
    </div>

    <script>

function formatTime12hr(timeStr) {
    if (!timeStr || timeStr === 'N/A') return 'N/A';
    if (/am|pm/i.test(timeStr)) return timeStr.trim();
    
    const parts = timeStr.split(':');
    let hour = parseInt(parts[0], 10);
    const minute = parts[1] || '00';
    if (isNaN(hour)) return timeStr;
    const period = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12 || 12;
    return `${hour}:${minute} ${period}`;
}

function openDetails(data) {
    document.getElementById('m-id').innerText = "Order Details - " + data.id;
    document.getElementById('m-name').innerText = data.name;
    document.getElementById('m-email').innerText = data.email;
    document.getElementById('m-phone').innerText = data.phone;
    document.getElementById('m-event').innerText = data.event;
    document.getElementById('m-date').innerText = data.date;
    document.getElementById('m-time').innerText = formatTime12hr(data.time);
    let venue = data.venue || '';

    let parts = venue.split(',');

    let firstLine = parts[0] || '';
    let secondLine = parts.slice(1).join(',').trim();

    let formatted = firstLine;
    if (secondLine) {
        formatted += "\n" + secondLine;
    }

document.getElementById('m-venue').innerText = formatted;
    document.getElementById('m-religion').innerText = data.religion || "N/A";
    document.getElementById('m-package').innerText = data.package || "N/A";
    document.getElementById('m-service').innerText = data.service;
    document.getElementById('m-notes').innerText = data.notes;
    document.getElementById('m-selected-theme').innerText = data.selected_theme || 'N/A';
    document.getElementById('m-downpayment').innerText =
    "₱" + Number(data.downpayment || 0).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    document.getElementById('m-payment-status').innerText = data.payment_status || 'Pending';
    document.getElementById('service-desc').innerText = data.hasFoods ? "Food services included" : "Service services only";

    const foodSec = document.getElementById('food-section');
    const menuList = document.getElementById('m-menu-list');
    const totalBox = document.getElementById('m-menu-total');

    menuList.innerHTML = '';
    let grandTotal = 0;

    if (data.hasFoods && data.menuItems && data.menuItems.length > 0) {
        foodSec.style.display = 'block';

        data.menuItems.forEach(item => {
            const itemPrice = parseFloat(item.price) || 0;
            const itemQty = parseInt(item.qty) || 1;
            const itemSubtotal = parseFloat(item.subtotal) || (itemPrice * itemQty);

            grandTotal += itemSubtotal;

            menuList.innerHTML += `
                <div class="food-row-main" style="margin-bottom:6px;">
                    <span>${item.name}</span>
                    <span>₱${itemSubtotal.toLocaleString()}</span>
                </div>
                <div class="food-row-sub" style="margin-bottom:10px;">
                    <span>₱${itemPrice.toLocaleString()} x ${itemQty}</span>
                    <span>Subtotal: ₱${itemSubtotal.toLocaleString()}</span>
                </div>
            `;
        });

        totalBox.innerText = "₱" + grandTotal.toLocaleString();
    } else {
        foodSec.style.display = 'none';
        totalBox.innerText = "₱0";
    }

    document.getElementById('detailsModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

let confirmTargetUrl = '';

function openConfirm(event, url, message) {
    event.preventDefault();
    confirmTargetUrl = url;

    document.getElementById('confirmMessage').innerText = message;
    document.getElementById('confirmModal').style.display = 'flex';

    return false;
}

function closeConfirm() {
    document.getElementById('confirmModal').style.display = 'none';
    confirmTargetUrl = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const confirmProceedBtn = document.getElementById('confirmProceedBtn');

    if (confirmProceedBtn) {
        confirmProceedBtn.addEventListener('click', function () {
            if (confirmTargetUrl) {
                window.location.href = confirmTargetUrl;
            }
        });
    }
});

window.onclick = function(e) {
    if (e.target.className == 'modal-overlay') closeModal();
}


let ordersPolling = null;

async function refreshAdminOrders() {
    try {
        const res = await fetch('ajax.php?action=fetch_orders_admin', {
            cache: 'no-store'
        });
        const data = await res.json();

        if (data.success) {
            const countEl = document.getElementById('ordersCount');
            const tbodyEl = document.getElementById('ordersTableBody');

            if (countEl) countEl.textContent = data.total;
            if (tbodyEl) tbodyEl.innerHTML = data.table_html;

            const statTotal = document.getElementById('stat-total');
            const statPending = document.getElementById('stat-pending');
            const statApproved = document.getElementById('stat-approved');
            const statCancelled = document.getElementById('stat-cancelled');

            if (statTotal) statTotal.textContent = data.total;
            if (statPending) statPending.textContent = data.pending;
            if (statApproved) statApproved.textContent = data.approved;
            if (statCancelled) statCancelled.textContent = data.cancelled;

            applyAllOrderFilters();
        }
    } catch (err) {
        console.error('Orders refresh failed:', err);
    }
}

function startOrdersPolling() {
    if (ordersPolling) clearInterval(ordersPolling);
    ordersPolling = setInterval(refreshAdminOrders, 3000);
}

let currentOrderFilter = 'all';
let currentDateFrom = '';
let currentDateTo = '';

function filterOrders(status, btn = null) {
    currentOrderFilter = status;

    if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    applyAllOrderFilters();
}

function applyAllOrderFilters() {
    const rows = document.querySelectorAll('#ordersTableBody tr');
    const searchValue = (document.getElementById('orderSearchInput')?.value || '').toLowerCase();

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowDate = row.getAttribute('data-date') || '';
        const rowSearch = row.getAttribute('data-search') || '';

        let showByStatus = currentOrderFilter === 'all' || rowStatus === currentOrderFilter;
        let showBySearch = rowSearch.includes(searchValue);

        let showByDate = true;
        if (currentDateFrom && rowDate < currentDateFrom) showByDate = false;
        if (currentDateTo && rowDate > currentDateTo) showByDate = false;

        row.style.display = (showByStatus && showBySearch && showByDate) ? '' : 'none';
    });
}

function toggleDateDropdown() {
    const dropdown = document.getElementById('dateRangeDropdown');
    const btn = document.getElementById('dateRangeBtn');
    dropdown.classList.toggle('show');
    btn.classList.toggle('active');
}

function closeDateDropdown() {
    document.getElementById('dateRangeDropdown').classList.remove('show');
    document.getElementById('dateRangeBtn').classList.remove('active');
}

function applyDateRange() {
    currentDateFrom = document.getElementById('dateFrom').value;
    currentDateTo = document.getElementById('dateTo').value;
    applyAllOrderFilters();
    closeDateDropdown();
}

function clearDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    currentDateFrom = '';
    currentDateTo = '';
    applyAllOrderFilters();
    closeDateDropdown();
}

window.onload = function() {
    const links = document.querySelectorAll('.menu-link');
    links.forEach(link => {
        link.classList.remove('active');
        if (link.querySelector('span') && link.querySelector('span').innerText.trim() === "Orders") {
            link.classList.add('active');
        } else if (link.innerText.trim() === "Orders") {
            link.classList.add('active');
        }
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('error') === 'date_full') {
        document.getElementById('errorMessage').innerText = 'Cannot approve this booking. The selected event date is already fully booked (maximum 2 events per day).';
        document.getElementById('errorModal').style.display = 'flex';
        window.history.replaceState({}, '', 'orders.php');
    }


    refreshAdminOrders();
    startOrdersPolling();
};
</script>
    </body>
    </html>