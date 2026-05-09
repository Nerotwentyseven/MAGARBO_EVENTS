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

$booked_dates = [];

$fullDateSql = "SELECT event_date, COUNT(*) as count
                FROM bookings
                WHERE booking_status = 'Approved'
                  AND LOWER(COALESCE(event_status, 'draft')) != 'completed'
                GROUP BY event_date";

$fullDateResult = mysqli_query($conn, $fullDateSql);

if ($fullDateResult && mysqli_num_rows($fullDateResult) > 0) {
    while ($row = mysqli_fetch_assoc($fullDateResult)) {
        $booked_dates[$row['event_date']] = (int)$row['count'];

    }
}

$menuOptions = [];
$menuSql = "SELECT id, menu_name, price FROM menus WHERE status = 'Active' ORDER BY menu_name ASC";
$menuResult = mysqli_query($conn, $menuSql);

if ($menuResult) {
    while ($menuRow = mysqli_fetch_assoc($menuResult)) {
        $menuOptions[] = $menuRow;
    }
}

$packageOptions = [];
$packageSql = "SELECT id, package_name FROM packages WHERE status = 'Active' ORDER BY package_name ASC";
$packageResult = mysqli_query($conn, $packageSql);

if ($packageResult) {
    while ($packageRow = mysqli_fetch_assoc($packageResult)) {
        $packageOptions[] = $packageRow;
    }
}

$sql = "SELECT b.*, 
               COALESCE(NULLIF(b.client_name, ''), CONCAT(u.firstname,' ',u.lastname)) AS client_name,
               u.email AS client_email,
               u.phone AS client_contact
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.booking_status = 'Approved'
          AND b.event_status IN ('draft','confirmed','ongoing','completed')
        ORDER BY b.event_date ASC, b.created_at ASC, b.id ASC";

        $result = mysqli_query($conn, $sql);

        $totalRevenue = 0;
        $totalCollected = 0;
        $totalConfirmed = 0;
        $totalDraft = 0;

        $events = [];
        $defaultDateFrom = '';
        $defaultDateTo = '';

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {


        $status = strtolower(trim($row['event_status'] ?? ''));
        if ($status === '') {
            $status = 'draft';
        }
        $actualTotal = (float)($row['total_price'] ?? 0);

        $downpayment = (float)($row['downpayment'] ?? 0);

        $totalPaidFromPayments = 0;

        $paySql = "SELECT COALESCE(SUM(amount), 0) AS total_paid
                FROM booking_payments
                WHERE booking_id = " . (int)$row['id'] . "
                    AND payment_status = 'Paid'";
        $payRes = mysqli_query($conn, $paySql);

        if ($payRes && mysqli_num_rows($payRes) > 0) {
            $payRow = mysqli_fetch_assoc($payRes);
            $totalPaidFromPayments = (float)($payRow['total_paid'] ?? 0);
        }

        $paidAmount = $totalPaidFromPayments > 0 ? $totalPaidFromPayments : $downpayment;

        // iwas sobra sa total
        if ($actualTotal > 0 && $paidAmount > $actualTotal) {
            $paidAmount = $actualTotal;
        }

        if ($status === 'draft' || $actualTotal <= 0) {
            $balance = 0;
        } else {
            $balance = max($actualTotal - $paidAmount, 0);
        }

        if (in_array($status, ['confirmed', 'ongoing', 'completed'], true) && $actualTotal > 0) {
            $totalRevenue += $actualTotal;
            $totalCollected += $paidAmount;
        }

        if ($status === 'confirmed') $totalConfirmed++;
        if ($status === 'draft') $totalDraft++;

        $displayEventId = !empty($row['display_order_id'])
            ? $row['display_order_id']
            : ('ORD-' . $row['id']);

        $events[] = [
            "id"            => $row['id'],
            'display_id' => $displayEventId,
            'created_at' => $row['created_at'] ?? '',
            "title" => !empty($row['package_name']) ? $row['package_name'] : "Event",
            "name"          => $row['client_name'],
            "email"         => $row['client_email'],
            "contact"       => $row['client_contact'],
            "type"          => strtolower($row['event_type'] ?? "general"),
            "status"        => $status,
            "date"          => $row['event_date'],
            "time" => (!empty($row['event_time']) && $row['event_time'] !== 'N/A')
            ? $row['event_time']
            : 'N/A',
            "venue"         => $row['venue'] ?? 'N/A',
            "service_type"  => $row['service_type'] ?? 'N/A',
            "total"         => $actualTotal,
            "paid"          => $paidAmount,
            "downpayment"   => $downpayment,
            "balance"       => $balance,
            "request"       => $row['request'] ?? '',
            "selected_theme"=> !empty(trim((string)$row['selected_theme'])) ? trim($row['selected_theme']) : 'N/A',
            "package"       => $row['package_name'] ?? '',
            "event"         => $row['event_type'] ?? '',
            "menu"          => $row['menu_selection'] ?? '',
            "religion"      => $row['religion'] ?? 'N/A'
        ];
    }

usort($events, function ($a, $b) {

    $statusPriority = [
        'draft'     => 1,
        'confirmed' => 2,
        'ongoing'   => 3,
        'completed' => 4
    ];

    $aStatus = strtolower($a['status'] ?? '');
    $bStatus = strtolower($b['status'] ?? '');

    $aPriority = $statusPriority[$aStatus] ?? 99;
    $bPriority = $statusPriority[$bStatus] ?? 99;

    if ($aPriority !== $bPriority) {
        return $aPriority <=> $bPriority;
    }

    if ($aStatus === 'draft' && $bStatus === 'draft') {
        $aCreated = strtotime($a['created_at'] ?? 'now');
        $bCreated = strtotime($b['created_at'] ?? 'now');
        return $bCreated <=> $aCreated;
    }

    if ($aStatus === 'confirmed' && $bStatus === 'confirmed') {
        $aEventDate = strtotime($a['date'] ?? 'now');
        $bEventDate = strtotime($b['date'] ?? 'now');

        if ($aEventDate !== $bEventDate) {
            return $aEventDate <=> $bEventDate;
        }

        $aCreated = strtotime($a['created_at'] ?? 'now');
        $bCreated = strtotime($b['created_at'] ?? 'now');
        return $aCreated <=> $bCreated;
    }

    if ($aStatus === 'ongoing' && $bStatus === 'ongoing') {
        $aEventDate = strtotime($a['date'] ?? 'now');
        $bEventDate = strtotime($b['date'] ?? 'now');

        if ($aEventDate !== $bEventDate) {
            return $aEventDate <=> $bEventDate;
        }

        $aCreated = strtotime($a['created_at'] ?? 'now');
        $bCreated = strtotime($b['created_at'] ?? 'now');
        return $aCreated <=> $bCreated;
    }

    $aEventDate = strtotime($a['date'] ?? 'now');
    $bEventDate = strtotime($b['date'] ?? 'now');

    if ($aEventDate !== $bEventDate) {
        return $aEventDate <=> $bEventDate;
    }

    return strtotime($a['created_at'] ?? 'now') <=> strtotime($b['created_at'] ?? 'now');
});
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
    <link rel="stylesheet" href="events.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="admin-main-container">
    <?php include('sidebar.php'); ?>

    <main class="main-content-area">
        <header class="page-header">
            <div class="header-left">
                <h1>Events Management</h1>
                <p>Create and manage all events and their progress</p>
            </div>

            <div class="header-actions">
                <div class="date-filter-wrap" id="dateFilterWrap">
                    <button type="button" class="btn-date-trigger" id="dateRangeToggleBtn">
                        <i class="far fa-calendar-alt"></i>
                        <span>Date Range</span>
                        <i class="fas fa-chevron-down date-caret"></i>
                    </button>

                    <div class="date-range-dropdown" id="dateRangeDropdown">
                        <div class="date-range-dropdown-header">
                            <h4>Date Range</h4>
                            <button type="button" class="date-range-close" id="dateRangeCloseBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <div class="date-range-fields">
                            <div class="date-input-wrap">
                                <label for="eventDateFrom">From</label>
                                <input type="date" id="eventDateFrom" value="<?php echo $defaultDateFrom; ?>">
                            </div>

                            <div class="date-input-wrap">
                                <label for="eventDateTo">To</label>
                                <input type="date" id="eventDateTo" value="<?php echo $defaultDateTo; ?>">
                            </div>
                        </div>

                        <div class="date-range-actions">
                            <button type="button" class="btn-date-clear" id="clearDateRangeBtn">Clear</button>
                            <button type="button" class="btn-date-apply" id="applyDateRangeBtn">Apply</button>
                        </div>
                    </div>
                </div>

                <button class="btn-create-event" onclick="openModal('createModal')">
                    <i class="fas fa-plus"></i> Create Event
                </button>
            </div>
        </header>

        <div class="scrollable-wrapper">
            <div class="stats-container">
                <div class="stat-box">
                    <small>Total Revenue</small>
                    <h2>₱<?php echo number_format($totalRevenue, 2); ?></h2>
                    <span>From priced events</span>
                </div>
                <div class="stat-box">
                    <small>Collected</small>
                    <h2>₱<?php echo number_format($totalCollected, 2); ?></h2>
                    <span>Total amount collected</span>
                </div>
                <div class="stat-box">
                    <small>Confirmed Events</small>
                    <h2><?php echo $totalConfirmed; ?></h2>
                    <span>Events with pricing set</span>
                </div>
                <div class="stat-box">
                    <small>Draft Events</small>
                    <h2><?php echo $totalDraft; ?></h2>
                    <span>Pending pricing confirmation</span>
                </div>
            </div>

            <div class="events-toolbar">
                <div class="filter-tabs">
                    <button class="tab-item active" data-status="all">All Events</button>
                    <button class="tab-item" data-status="draft">Draft</button>
                    <button class="tab-item" data-status="confirmed">Confirmed</button>
                    <button class="tab-item" data-status="ongoing">Ongoing</button>
                    <button class="tab-item" data-status="completed">Completed</button>
                </div>

                <div class="search-box-wrap">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        id="eventSearchInput"
                        placeholder="Search client, event type, venue, package..."
                    >
                </div>
            </div>

            <div class="events-list">
                <?php foreach ($events as $e): ?>
                    <div
                    class="event-card"
                    data-status="<?php echo htmlspecialchars($e['status']); ?>"
                    data-date="<?php echo htmlspecialchars($e['date']); ?>"
                    
                    data-search="<?php echo htmlspecialchars(strtolower(
                        $e['name'] . ' ' .
                        $e['event'] . ' ' .
                        $e['venue'] . ' ' .
                        $e['package'] . ' ' .
                        $e['service_type'] . ' ' .
                        $e['selected_theme']
                    )); ?>"
                    id="card-<?php echo (int)$e['id']; ?>"
                >
                        <div class="card-main-info">
                            <div class="card-header-row">
                            <div>
                                <span class="client-name"><?php echo $e['name']; ?></span>
                                <div style="margin-top: 4px; font-size: 12px; font-weight: 700; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($e['display_id']); ?>
                                </div>
                            </div>

                            <div class="tag-group">
                                <span class="badge type-<?php echo $e['type']; ?>"><?php echo $e['type']; ?></span>
                                <span class="badge status-badge status-<?php echo $e['status']; ?>"><?php echo $e['status']; ?></span>
                            </div>
                        </div>

                            <div class="details-row">
                                <span><i class="far fa-calendar"></i> <?php echo $e['date']; ?> | <?php echo $e['time'] !== 'N/A' ? date('g:i A', strtotime($e['time'])) : 'N/A'; ?></span>
                                <span><i class="fas fa-map-marker-alt"></i> <?php echo $e['venue']; ?></span>
                                <span class="price-indicator">
                                    <?php echo ($e['status'] === 'draft' || $e['total'] <= 0) ? 'Not set' : '₱' . number_format($e['total'], 2); ?>
                                </span>
                            </div>

                            <div class="pricing-summary-box">
                                <div class="p1-item">
                                    <small>Total Price</small>
                                    <p class="card-total-val">
                                        <?php echo ($e['status'] === 'draft' || $e['total'] <= 0) ? 'Not set' : '₱' . number_format($e['total'], 2); ?>
                                    </p>
                                </div>

                                <div class="p1-item">
                                    <small>Paid</small>
                                    <p class="card-paid-val">
                                        <?php echo ($e['status'] === 'draft' || $e['total'] <= 0) ? 'Not started' : '₱' . number_format($e['paid'], 2); ?>
                                    </p>
                                </div>

                                <div class="p1-item">
                                    <small>Balance</small>
                                    <p class="bal-text">
                                        <?php echo ($e['status'] === 'draft' || $e['total'] <= 0) ? 'Not available' : '₱' . number_format($e['balance'], 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="card-btns">
                            <div class="status-actions">
                                <?php if($e['status'] == 'draft'): ?>
                                    <button class="btn-gold"
                                        onclick="openPricing(
                                            '<?php echo (int)$e['id']; ?>',
                                            '<?php echo htmlspecialchars($e['name'], ENT_QUOTES); ?>',
                                            '<?php echo number_format((float)$e['downpayment'], 2, '.', ''); ?>',
                                            ''
                                        )">
                                        Set Pricing
                                    </button>

                                <?php elseif($e['status'] == 'confirmed'): ?>
                                    <button class="btn-outline" onclick="triggerUpdate('<?php echo (int)$e['id']; ?>')">Update Price</button>
                                    <button class="btn-gold" onclick="updateStatus('<?php echo (int)$e['id']; ?>', 'ongoing')">Start Event</button>

                                <?php elseif($e['status'] == 'ongoing'): ?>

                                <?php if ((float)$e['balance'] <= 0): ?>
                                    <button class="btn-gold" onclick="updateStatus('<?php echo (int)$e['id']; ?>', 'completed')">
                                        Complete
                                    </button>
                                <?php else: ?>
                                    <button class="btn-gray" disabled title="Cannot complete while there is remaining balance">
                                        Complete
                                    </button>
                                <?php endif; ?>

                                <?php else: ?>
                                    <button class="btn-gray" disabled>Completed</button>
                                <?php endif; ?>
                            </div>

                            <button class="btn-icon-detail" onclick="showFullDetails(<?php echo htmlspecialchars(json_encode($e)); ?>)">
                                <i class="fas fa-list-ul"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<div id="createModal" class="modal-overlay">
    <div class="modal-card-large">
        <div class="m-header">
            <div>
                <h3>Create New Event</h3>
                <p>Create a draft event for client confirmation.</p>
            </div>
            <span class="close-btn" onclick="closeModal('createModal')">&times;</span>
        </div>

        <form action="create_event.php" method="POST" class="m-body grid-2" id="createEventForm">
            <div class="m-inputs">
                <div class="field">
                    <label>Client Name</label>
                    <input type="text" name="client_name" required>
                </div>

                <div class="field">
                    <label>Email</label>
                    <input type="email" name="client_email" required>
                </div>

                <div class="field">
                    <label>Contact</label>
                    <input type="text" name="client_contact" required>
                </div>

                <div class="field">
                    <label>Event Type</label>
                    <input 
                        type="text" 
                        name="event_type" 
                        id="eventTypeSelect" 
                        list="eventTypeOptions"
                        placeholder="Type or select event type"
                        required
                        oninput="handleAdminEventType()"
                    >
                    <datalist id="eventTypeOptions">
                        <option value="Anniversary">
                        <option value="Birthday">
                        <option value="Catering">
                        <option value="Christening">
                        <option value="Corporate">
                        <option value="Wedding">
                    </datalist>
                </div>

                <div class="field" id="religionFieldWrap" style="display:none;">
                    <label>Religion</label>
                    <select name="religion" id="religionField">
                        <option value="">Select religion</option>
                        <option value="Catholic">Catholic</option>
                        <option value="Christian">Christian</option>
                        <option value="Iglesia ni Cristo">Iglesia ni Cristo</option>
                        <option value="Muslim">Muslim</option>
                    </select>
                </div>

                <div class="field">
                    <label>Service Type</label>
                    <select name="service_type" id="serviceTypeSelect" required onchange="handleServiceTypeChange()">
                        <option value="">Select service type</option>
                        <option value="Catering Only">Catering Only</option>
                        <option value="Catering with Styling">Catering with Styling</option>
                        <option value="Styling & Decoration Only">Styling & Decoration Only</option>
                    </select>
                </div>

                <div class="field">
                    <label>Date</label>
                    <input type="date" name="event_date" id="createEventDate" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="field">
                    <label>Time</label>
                    <input type="time" name="event_time" required>
                </div>

                <div class="field">
                    <label>Venue</label>
                    <input type="text" name="venue" placeholder="Enter venue name" required>
                </div>

                <div class="field" id="packageFieldWrap" style="display:none;">
                    <label>Package</label>
                    <select name="package_name" id="packageField">
                        <option value="">Select package</option>
                        <?php foreach ($packageOptions as $pkg): ?>
                            <option value="<?php echo htmlspecialchars($pkg['package_name']); ?>">
                                <?php echo htmlspecialchars($pkg['package_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="menuFieldWrap" style="display:none; grid-column: 1 / -1; margin-top: 10px;">
                    <label class="modal-section-label">Menu Selection</label>

                    <div class="menu-field-wrap-box">
                        <?php foreach ($menuOptions as $menu): ?>
                            <div class="menu-row">
                                <div class="menu-row-main">
                                    <label class="menu-row-label">
                                        <input 
                                            type="checkbox" 
                                            class="menu-check"
                                            data-target="qty_<?php echo $menu['id']; ?>"
                                            onchange="toggleMenuQty(this)"
                                        >
                                        <span class="menu-row-name"><?php echo htmlspecialchars($menu['menu_name']); ?></span>
                                    </label>
                                    <div class="menu-row-price">
                                        ₱<?php echo number_format((float)$menu['price'], 2); ?> each
                                    </div>
                                </div>

                                <div class="menu-qty-controls">
                                    <button type="button"
                                        class="menu-btn-minus"
                                        onclick="changeMenuQty('qty_<?php echo $menu['id']; ?>', -1)">
                                        -
                                    </button>

                                    <input
                                        type="number"
                                        id="qty_<?php echo $menu['id']; ?>"
                                        name="menu_qty[<?php echo htmlspecialchars($menu['menu_name']); ?>]"
                                        value="0"
                                        min="0"
                                        readonly
                                        class="menu-qty-input"
                                    >

                                    <button type="button"
                                        class="menu-btn-plus"
                                        onclick="changeMenuQty('qty_<?php echo $menu['id']; ?>', 1)">
                                        +
                                    </button>
                                </div>

                                <div class="menu-subtotal">
                                    <span id="subtotal_<?php echo $menu['id']; ?>">₱0.00</span>
                                </div>

                                <input type="hidden" id="price_<?php echo $menu['id']; ?>" value="<?php echo (float)$menu['price']; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="menu-grand-total-box">
                        <span>Total Menu Cost</span>
                        <span id="menuGrandTotal" class="menu-grand-total-value">₱0.00</span>
                    </div>

                    <small class="menu-help-text">
                        Select one or more menus and adjust quantity if needed.
                    </small>
                </div>
            </div>

            <div class="m-request">
                <label>Customer Request</label>
                <textarea name="request" placeholder="Enter customer request"></textarea>
                <button type="submit" class="btn-gold" style="width:100%; margin-top:20px;">Save Draft Event</button>
            </div>
        </form>
    </div>
</div>

<div id="pricingModal" class="modal-overlay">
    <div class="modal-card-small">
        <div class="m-header"><h3>Set Pricing</h3><span class="close-btn" onclick="closeModal('pricingModal')">&times;</span></div>
        <div class="m-body">
            <p>Event for: <strong id="pClientName"></strong></p>
            <input type="hidden" id="pEventId">
            <div class="field pricing-field-spacing">
                <label>Total Price (₱)</label>
                <input type="number" id="pTotalInput" placeholder="0.00">
            </div>
            <div class="field pricing-field-spacing-sm">
                <label>Paid / Downpayment</label>
                <input type="text" id="pDownpaymentInput" value="₱0.00" disabled class="pricing-disabled-input">
            </div>
            <button class="btn-gold" style="width:100%; margin-top:20px;" onclick="confirmPrice()">Confirm Pricing</button>
        </div>
    </div>
</div>

<div id="detailsModal" class="modal-overlay">
    <div class="modal-card-large">
        <div class="m-header">
            <div>
                <h3 id="eventDetailsTitle">Event Details</h3>
                <p id="eventDetailsSubtitle">Complete information for this event</p>
            </div>
            <span class="close-btn" onclick="closeModal('detailsModal')">&times;</span>
        </div>
        <div class="m-body grid-2" id="fullDetailsBody"></div>
    </div>
</div>

<div id="confirmModal" class="confirm-overlay">
    <div class="confirm-card">
        <div class="confirm-icon-wrap">
            <i class="fas fa-exclamation"></i>
        </div>

        <h3>Confirm Action</h3>
        <p id="confirmMessage">Are you sure?</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeConfirm()">No</button>
            <button type="button" class="confirm-btn approve" id="confirmProceedBtn">Yes</button>
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

    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    let confirmTargetUrl = '';

    function openConfirm(event, url, message) {
        if (event) event.preventDefault();

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
        const confirmBtn = document.getElementById('confirmProceedBtn');

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function () {
                if (confirmTargetUrl) {
                    window.location.href = confirmTargetUrl;
                }
            });
        }
    });

    function triggerUpdate(id) {
    const card = document.getElementById('card-' + id);
    if (!card) {
        alert('Event card not found.');
        return;
    }

    const nameEl = card.querySelector('.client-name');
    const name = nameEl ? nameEl.innerText : 'Client';

    const paidEl = card.querySelector('.card-paid-val');
    let paidText = paidEl ? paidEl.innerText : '₱0.00';
    let paidAmount = parseFloat(String(paidText).replace(/[^0-9.-]/g, '')) || 0;

    const totalEl = card.querySelector('.card-total-val');
    let totalText = totalEl ? totalEl.innerText : '';
    let totalAmount = parseFloat(String(totalText).replace(/[^0-9.-]/g, '')) || 0;

    openPricing(id, name, paidAmount, totalAmount);
}

    function openPricing(id, name, downpaymentAmount = 0, totalAmount = '') {
    document.getElementById('pEventId').value = id;
    document.getElementById('pClientName').innerText = name;

    document.getElementById('pTotalInput').value = totalAmount !== '' ? totalAmount : '';

    const downpayment = Number(downpaymentAmount || 0);
    document.getElementById('pDownpaymentInput').value =
        '₱' + downpayment.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

    openModal('pricingModal');
}

    function confirmPrice() {
        const id = document.getElementById('pEventId').value;
        const total = parseFloat(document.getElementById('pTotalInput').value) || 0;

        if (total <= 0) {
            alert('Please enter a valid total price.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'set_event_price.php';

        const eventInput = document.createElement('input');
        eventInput.type = 'hidden';
        eventInput.name = 'event_id';
        eventInput.value = id;

        const totalInput = document.createElement('input');
        totalInput.type = 'hidden';
        totalInput.name = 'total_price';
        totalInput.value = total;

        form.appendChild(eventInput);
        form.appendChild(totalInput);

        document.body.appendChild(form);
        form.submit();
    }

    function updateStatus(id, status) {
        const url = 'update_event_status.php?id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status);

        if (status === 'ongoing') {
            confirmTargetUrl = url;
            document.getElementById('confirmMessage').innerText = 'Start this event?';
            document.getElementById('confirmModal').style.display = 'flex';
            return false;
        }

        if (status === 'completed') {
            confirmTargetUrl = url;
            document.getElementById('confirmMessage').innerText = 'Mark this event as completed?';
            document.getElementById('confirmModal').style.display = 'flex';
            return false;
        }

        window.location.href = url;
    }

    function handleAdminEventType() {
        const eventType = document.getElementById('eventTypeSelect')?.value || '';
        const religionWrap = document.getElementById('religionFieldWrap');
        const religionField = document.getElementById('religionField');

        if (!religionWrap || !religionField) return;

        if (eventType.toLowerCase() === 'wedding') {
            religionWrap.style.display = 'block';
            religionField.required = true;
        } else {
            religionWrap.style.display = 'none';
            religionField.required = false;
            religionField.value = '';
        }
    }

    function handleServiceTypeChange() {
        const serviceType = document.getElementById('serviceTypeSelect')?.value || '';

        const packageWrap = document.getElementById('packageFieldWrap');
        const packageField = document.getElementById('packageField');

        const menuWrap = document.getElementById('menuFieldWrap');
        const menuChecks = document.querySelectorAll('.menu-check');

        if (!packageWrap || !packageField || !menuWrap) return;

        if (serviceType === 'Catering Only') {
            menuWrap.style.display = 'block';
            packageWrap.style.display = 'none';

            packageField.required = false;
            packageField.value = '';

            menuChecks.forEach(cb => {
                cb.checked = false;
            });
        } 
        else if (serviceType === 'Styling & Decoration Only') {
            menuWrap.style.display = 'none';
            packageWrap.style.display = 'block';

            packageField.required = true;

            menuChecks.forEach(cb => {
                cb.checked = false;
            });
        } 
        else if (serviceType === 'Catering with Styling') {
            menuWrap.style.display = 'block';
            packageWrap.style.display = 'block';

            packageField.required = true;
        } 
        else {
            menuWrap.style.display = 'none';
            packageWrap.style.display = 'none';

            packageField.required = false;
            packageField.value = '';

            menuChecks.forEach(cb => {
                cb.checked = false;
            });
        }
    }

    function updateMenuTotals() {
    let grandTotal = 0;

    document.querySelectorAll('.menu-check').forEach(checkbox => {
        const targetId = checkbox.getAttribute('data-target');
        const qtyInput = document.getElementById(targetId);
        if (!qtyInput) return;

        const id = targetId.replace('qty_', '');
        const priceInput = document.getElementById('price_' + id);
        const subtotalEl = document.getElementById('subtotal_' + id);

        const qty = parseInt(qtyInput.value, 10) || 0;
        const price = parseFloat(priceInput?.value || 0);
        const subtotal = qty * price;

        if (subtotalEl) {
            subtotalEl.textContent = '₱' + subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        grandTotal += subtotal;
    });

    const grandEl = document.getElementById('menuGrandTotal');
    if (grandEl) {
        grandEl.textContent = '₱' + grandTotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
}

function toggleMenuQty(checkbox) {
    const targetId = checkbox.getAttribute('data-target');
    const qtyInput = document.getElementById(targetId);

    if (!qtyInput) return;

    if (checkbox.checked) {
        if (parseInt(qtyInput.value, 10) <= 0) {
            qtyInput.value = 1;
        }
    } else {
        qtyInput.value = 0;
    }

    updateMenuTotals();
}

function changeMenuQty(inputId, change) {
    const qtyInput = document.getElementById(inputId);
    if (!qtyInput) return;

    let current = parseInt(qtyInput.value, 10) || 0;
    let next = current + change;

    if (next < 0) next = 0;
    qtyInput.value = next;

    const row = qtyInput.closest('.menu-row');
    const checkbox = row ? row.querySelector('.menu-check') : null;

    if (checkbox) {
        checkbox.checked = next > 0;
    }

    updateMenuTotals();
}

    function showFullDetails(data) {
        const titleEl = document.getElementById('eventDetailsTitle');
        const subtitleEl = document.getElementById('eventDetailsSubtitle');

        if (titleEl) {
            titleEl.innerText = 'Event Details - ' + data.display_id;
        }

        if (subtitleEl) {
            subtitleEl.innerText = 'Complete information for this event';
        }

        const totalValue = parseFloat(data.total) || 0;
        const isDraft = data.status === 'draft' || totalValue <= 0;
        const paidValue = isDraft ? 0 : (parseFloat(data.paid) || 0);
        const balanceValue = isDraft ? 0 : Math.max(totalValue - paidValue, 0);
        const totalText = isDraft
        ? 'NOT SET'
        : '₱' + totalValue.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        const religionHTML = (data.event === 'Wedding')
            ? `<div class="field"><label>Religion</label><p>${data.religion || 'N/A'}</p></div>`
                            
            : '';

        const menuBlock = (() => {
            if (!data.menu || data.menu === '' || data.menu === 'N/A') return '';

            try {
                const parsed = JSON.parse(data.menu);
                let totalMenuPrice = 0;

                let html = `
                    <div class="modal-menu-block">
                        <label class="modal-section-label">MENU SELECTED</label>
                        <div class="modal-menu-card">
                            <div class="modal-menu-scroll">
                `;

                Object.values(parsed).forEach(item => {
                    const name = item.name || item;
                    const price = parseFloat(item.price || 0);
                    const qty = parseInt(item.qty || item.quantity || 1);
                    const subtotal = price * qty;

                    totalMenuPrice += subtotal;

                    html += `
                        <div class="modal-menu-item">
                            <div class="modal-menu-main">
                                <span>${name}</span>
                                <span>₱${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                            <div class="modal-menu-sub">
                                <span>₱${price.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} x ${qty}</span>
                                <span>Subtotal: ₱${subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    `;
                });

                html += `
                            </div>
                            <div class="modal-menu-total">
                                <span>Total Food Cost</span>
                                <span>₱${totalMenuPrice.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                            </div>
                        </div>
                    </div>
                `;

                return html;
            } catch (e) {
                return `
                    <div class="modal-menu-block">
                        <label class="modal-section-label">MENU SELECTED</label>
                        <div class="modal-menu-fallback">
                            ${data.menu}
                        </div>
                    </div>
                `;
            }
        })();

        const rawVenue = String(data.venue || 'N/A').trim();
        let formattedVenue = rawVenue;

        if (rawVenue !== 'N/A') {
            const parts = rawVenue.split(',').map(part => part.trim()).filter(Boolean);

            let cityIndex = parts.findIndex(part => {
                const p = part.toLowerCase();
                return (
                    p.startsWith('city of') ||
                    p.includes(' city') ||
                    p.includes('municipality')
                );
            });

            let provinceIndex = parts.findIndex(part => {
                const p = part.toLowerCase();
                return (
                    p === 'albay' ||
                    p === 'camarines sur' ||
                    p === 'camarines norte' ||
                    p === 'sorsogon' ||
                    p === 'catanduanes' ||
                    p === 'masbate' ||
                    p.includes('province')
                );
            });

            let splitIndex = -1;

            if (cityIndex > 0) {
                splitIndex = cityIndex - 1;
            } else if (provinceIndex > 1) {
                splitIndex = provinceIndex - 2;
            }

            if (splitIndex <= 0 && parts.length >= 3) {
                splitIndex = parts.length - 3;
            }

            if (splitIndex <= 0) {
                splitIndex = 1;
            }

            const firstLine = parts.slice(0, splitIndex).join(', ');
            const secondLine = parts.slice(splitIndex).join(', ');

            formattedVenue = firstLine;
            if (secondLine) {
                formattedVenue += '\n' + secondLine;
            }
        }

        const body = document.getElementById('fullDetailsBody');
        body.innerHTML = `
            <div class="m-inputs">
                <div class="field"><label>Event Title</label><p>${data.title}</p></div>
                <div class="field"><label>Client Name</label><p>${data.name}</p></div>
                <div class="field"><label>Email</label><p>${data.email}</p></div>
                <div class="field"><label>Contact</label><p>${data.contact}</p></div>
                <div class="field"><label>Event Type</label><p>${data.event}</p></div>
                <div class="field"><label>Service Type</label><p>${data.service_type || 'N/A'}</p></div>
                ${religionHTML}
                <div class="field"><label>Venue</label><p>${formattedVenue}</p></div>
                <div class="field"><label>Date & Time</label><p>${data.date} | ${formatTime12hr(data.time)}</p></div>
                <div class="field"><label>Package</label><p>${data.package || 'N/A'}</p></div>
                <div class="field"><label>Selected Theme</label><p>${(data.selected_theme && String(data.selected_theme).trim() !== '') ? data.selected_theme : 'N/A'}</p></div>
                <div class="field"><label>Status</label><p class="modal-status-text">${data.status}</p></div>
                <div class="field"><label>Total Price</label><p>${totalText}</p></div>
                <div class="field"><label>Paid / Downpayment</label><p>${isDraft ? 'Not started' : '₱' + paidValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p></div>
                <div class="field"><label>Balance</label><p>${isDraft ? 'Not started' : '₱' + balanceValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</p></div>
            </div>

            <div class="m-request" style="max-height:260px; overflow-y:auto;">
                <label>Customer Request</label>
                <div class="modal-request-box">
                    ${data.request || 'No request provided.'}
                </div>
            </div>

            ${menuBlock}
        `;

        openModal('detailsModal');
        }

            let activeEventStatus = 'all';

        function applyEventFilters() {
            const searchInput = document.getElementById('eventSearchInput');
            const fromInput = document.getElementById('eventDateFrom');
            const toInput = document.getElementById('eventDateTo');

            const searchText = (searchInput?.value || '').trim().toLowerCase();
            const fromDate = fromInput?.value || '';
            const toDate = toInput?.value || '';

            document.querySelectorAll('.event-card').forEach(card => {
                const status = card.dataset.status || '';
                const eventDate = card.dataset.date || '';
                const searchableText = card.dataset.search || '';

                const statusMatch = activeEventStatus === 'all' || status === activeEventStatus;
                const searchMatch = !searchText || searchableText.includes(searchText);
                const fromMatch = !fromDate || eventDate >= fromDate;
                const toMatch = !toDate || eventDate <= toDate;

                if (statusMatch && searchMatch && fromMatch && toMatch) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        document.querySelectorAll('.tab-item').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                activeEventStatus = this.dataset.status || 'all';
                applyEventFilters();
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('eventSearchInput');
            const fromInput = document.getElementById('eventDateFrom');
            const toInput = document.getElementById('eventDateTo');
            const clearBtn = document.getElementById('clearDateRangeBtn');
            const applyBtn = document.getElementById('applyDateRangeBtn');
            const createDateInput = document.getElementById('createEventDate');

            const dateToggleBtn = document.getElementById('dateRangeToggleBtn');
            const dateDropdown = document.getElementById('dateRangeDropdown');
            const dateCloseBtn = document.getElementById('dateRangeCloseBtn');
            const dateFilterWrap = document.getElementById('dateFilterWrap');

            if (searchInput) {
                searchInput.addEventListener('input', applyEventFilters);
            }

            if (dateToggleBtn && dateDropdown) {
                dateToggleBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    dateDropdown.classList.toggle('show');
                    dateToggleBtn.classList.toggle('active');
                });
            }

            if (dateCloseBtn && dateDropdown && dateToggleBtn) {
                dateCloseBtn.addEventListener('click', function () {
                    dateDropdown.classList.remove('show');
                    dateToggleBtn.classList.remove('active');
                });
            }

            if (applyBtn) {
                applyBtn.addEventListener('click', function () {
                    applyEventFilters();
                    if (dateDropdown && dateToggleBtn) {
                        dateDropdown.classList.remove('show');
                        dateToggleBtn.classList.remove('active');
                    }
                });
            }

            if (clearBtn) {
                clearBtn.addEventListener('click', function () {
                    if (fromInput) fromInput.value = '';
                    if (toInput) toInput.value = '';
                    applyEventFilters();

                    if (dateDropdown && dateToggleBtn) {
                        dateDropdown.classList.remove('show');
                        dateToggleBtn.classList.remove('active');
                    }
                });
            }

            document.addEventListener('click', function (e) {
                if (
                    dateFilterWrap &&
                    !dateFilterWrap.contains(e.target) &&
                    dateDropdown &&
                    dateToggleBtn
                ) {
                    dateDropdown.classList.remove('show');
                    dateToggleBtn.classList.remove('active');
                }
            });

            if (createDateInput) {
                createDateInput.addEventListener('change', function () {
                    const selectedDate = this.value;

                    if (bookedDates[selectedDate] && bookedDates[selectedDate] >= 2) {
                        alert("This date is already fully booked.");
                        this.value = "";
                    }
                });
            }

            handleAdminEventType();
            handleServiceTypeChange();
            applyEventFilters();
            });

            const bookedDates = <?php echo json_encode($booked_dates); ?>;

        
</script>
</body>
</html>

