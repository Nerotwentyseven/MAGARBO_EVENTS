<?php
session_name('ADMINSESSID');
session_start();
require_once 'admin_auth.php';
require_once '../db_connection.php';
$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

$sql = "
    SELECT 
        b.id,
        b.display_order_id,
        b.downpayment,
        b.event_status,
        b.total_price,
        b.event_date,
        b.package_name,
        b.event_type,
        COALESCE(NULLIF(b.client_name, ''), CONCAT(u.firstname, ' ', u.lastname)) AS customer,
        COALESCE(SUM(CASE WHEN bp.payment_status = 'Paid' THEN bp.amount ELSE 0 END), 0) AS total_paid_from_payments
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN booking_payments bp ON bp.booking_id = b.id
    WHERE b.booking_status = 'Approved'
      AND b.event_status IN ('confirmed','ongoing','completed')
      AND b.total_price > 0
    GROUP BY 
        b.id, b.event_status, b.total_price, b.event_date, b.package_name, b.event_type,
        b.client_name, u.firstname, u.lastname
    ORDER BY b.created_at DESC, b.id DESC
";

$result = mysqli_query($conn, $sql);

$payments = [];
$totalRevenue = 0;
$totalPendingBalance = 0;
$totalCollected = 0;
$generatedDisplayIds = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $bookingId = (int)$row['id'];
        $total = (float)($row['total_price'] ?? 0);
        $baseDownpayment = (float)($row['downpayment'] ?? 0);
        $totalPaidFromPayments = (float)($row['total_paid_from_payments'] ?? 0);

        /*
            Huwag pagsamahin ang bookings.downpayment + booking_payments.amount
            kasi ang initial downpayment ay nasa booking_payments na rin.
        */
        $paid = $totalPaidFromPayments > 0 ? $totalPaidFromPayments : $baseDownpayment;

        // para hindi lumagpas sa total kung sumobra ang input
        if ($total > 0 && $paid > $total) {
            $paid = $total;
        }

        $balance = max($total - $paid, 0);
        $status = ($total > 0 && $balance <= 0.01) ? 'paid' : 'pending';

        $history = [];

        if ($baseDownpayment > 0 && $totalPaidFromPayments <= 0) {
            $history[] = [
                'date'   => !empty($row['event_date']) ? date('m/d/Y', strtotime($row['event_date'])) : '--',
                'method' => 'System',
                'amount' => number_format($baseDownpayment, 2),
                'note'   => 'Initial downpayment',
                'type'   => 'Downpayment'
            ];
        }

        $histSql = "SELECT payment_date, payment_method, amount, note
                FROM booking_payments
                WHERE booking_id = $bookingId
                AND payment_status = 'Paid'
                ORDER BY payment_date DESC, id DESC";
        $histRes = mysqli_query($conn, $histSql);

        if ($histRes && mysqli_num_rows($histRes) > 0) {
            while ($h = mysqli_fetch_assoc($histRes)) {
                $history[] = [
                    'date'   => date('m/d/Y', strtotime($h['payment_date'])),
                    'method' => $h['payment_method'] ?: 'N/A',
                    'amount' => number_format((float)$h['amount'], 2),
                    'note'   => $h['note'] ?? '',
                    'type'   => 'Paid / Downpayment'
                ];
            }
        }

        $clientNameSource = trim($row['customer'] ?? 'ORD');
        $nameParts = preg_split('/\s+/', $clientNameSource);
        $baseName = strtolower($nameParts[0] ?? 'ord');
        $cleanName = preg_replace('/[^a-z]/i', '', $baseName);
        $shortName = strtoupper(substr($cleanName, 0, 3));

        if ($shortName === '') {
            $shortName = 'ORD';
        }

        $eventDateRaw = $row['event_date'] ?? date('Y-m-d');
        $eventTimestamp = strtotime($eventDateRaw);
        $baseDisplayId = $shortName . date('Ymd', $eventTimestamp);

        $displayBillingId = !empty($row['display_order_id'])
            ? $row['display_order_id']
            : $baseDisplayId;

        $payments[] = [
            'id'          => $displayBillingId,
            'booking_id'  => $bookingId,
            'customer'    => $row['customer'],
            'order_id'    => $displayBillingId,
            'total'       => $total,
            'paid'        => $paid,
            'down'        => $baseDownpayment,
            'balance'     => $balance,
            'status'      => $status,
            'due_date'    => !empty($row['event_date']) ? date('m/d/Y', strtotime($row['event_date'])) : '--',
            'event_status'=> $row['event_status'],
            'history'     => $history
        ];

        $totalRevenue += $total;
        $totalCollected += $paid;
        $totalPendingBalance += $balance;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo Events - Payments & Billing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="billing.css?v=<?php echo time(); ?>">
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="ADMIN-PAYMENT">
    <?php include('sidebar.php'); ?>

    <main class="frame">
        <header class="page-header">
            <div class="header-left">
                <h1>Payments & Billing</h1>
                <p>Track and manage customer transactions and history</p>
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

        <div class="stats-grid">
            
                <div class="stat-card">
                    <span class="label">Total Revenue</span>
                    <span class="value">₱<?php echo number_format($totalRevenue, 2); ?></span>
                    <span class="stat-note">All confirmed event earnings</span>
                </div>

                <div class="stat-card">
                    <span class="label">Pending Balance</span>
                    <span class="value">₱<?php echo number_format($totalPendingBalance, 2); ?></span>
                    <span class="stat-note">Remaining unpaid amounts</span>
                </div>

                <div class="stat-card">
                    <span class="label">Collected Payments</span>
                    <span class="value">₱<?php echo number_format($totalCollected, 2); ?></span>
                    <span class="stat-note">Total received payments</span>
                </div>
            
        </div>

        <div class="action-bar">
            <div class="filter-container">
                <div class="filter-item active" onclick="filterPayments('all', this)">All</div>
                <div class="filter-item" onclick="filterPayments('pending', this)">Pending</div>
                <div class="filter-item" onclick="filterPayments('paid', this)">Paid</div>
            </div>
            <div class="search-container">
                <i class="fa fa-search"></i>
                <input type="text" id="billingSearch" placeholder="Search customer or ID..." onkeyup="applyAllBillingFilters()">
            </div>
        </div>

        <div class="table-card">
            <table id="billingTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): 
                        $p_json = htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8');
                    ?>
                    <tr 
                        class="payment-row"
                        data-status="<?= htmlspecialchars($p['status']) ?>"
                        data-date="<?= !empty($p['due_date']) ? date('Y-m-d', strtotime($p['due_date'])) : '' ?>"
                        data-search="<?= htmlspecialchars(strtolower($p['id'] . ' ' . $p['customer'] . ' ' . $p['order_id'])) ?>"
                    >
                        <td><?= htmlspecialchars($p['id']) ?></td>
                        <td><strong><?= htmlspecialchars($p['customer']) ?></strong></td>
                        <td><?= $p['total'] > 0 ? '₱' . number_format($p['total'], 2) : 'Not set' ?></td>
                        <td>₱<?= number_format($p['paid'], 2) ?></td>
                        <td class="balance-text">₱<?= number_format($p['balance'], 2) ?></td>
                        <td><span class="status-pill status-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span></td>
                        <td class="action-btns">
                            <?php if($p['status'] == 'pending'): ?>
                                <button class="btn-add" onclick='triggerAddPayment(<?= $p_json ?>)'>+</button>
                            <?php endif; ?>
                            <button class="btn-view" onclick='triggerViewDetails(<?= $p_json ?>)'>👁</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:30px; color:#777;">No billing records found.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="addPaymentModal" class="modal-overlay">
    <div class="modal-content add-modal">
        <button class="close-modal" onclick="closeModal('addPaymentModal')">&times;</button>
        <div class="modal-header">
            <div class="header-text">
                <h3>Record Payment</h3>
                <p>Receiving payment from <span id="add-cust-name" style="font-weight:700;">--</span></p>
            </div>
        </div>

        <div class="modal-body">
            <div class="balance-banner">
                <span>Remaining Balance:</span>
                <strong id="add-balance">₱0</strong>
            </div>

            <form action="record_payment.php" method="POST">
                <input type="hidden" name="booking_id" id="pay_booking_id">

                <div class="form-container">
                    <div class="modal-field">
                        <label>Payment Amount</label>
                        <input type="number" step="0.01" min="0.01" id="pay_amt" name="payment_amount" placeholder="0.00" required>
                    </div>
                    <div class="modal-field">
                        <label>Payment Method</label>
                        <select id="pay_meth" name="payment_method" required>
                            <option value="" disabled selected>Select method</option>
                            <option value="Cash">Cash</option>
                            <option value="GCash">GCash</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="modal-field">
                        <label>Payment Date</label>
                        <input type="date" id="pay_date" name="payment_date" required>
                    </div>
                    <div class="modal-field">
                        <label>Note</label>
                        <input type="text" name="note" placeholder="Optional note">
                    </div>
                </div>

                <button class="modal-submit-btn" type="submit">Confirm Payment</button>
            </form>
        </div>
    </div>
</div>

<div id="viewDetailsModal" class="modal-overlay">
    <div class="modal-content detail-width">
        <button class="close-modal-box" onclick="closeModal('viewDetailsModal')">&times;</button>
        <div class="modal-header">
            <div class="header-text">
                <h3>Payment Details - <span id="det-id">--</span></h3>
                <p class="sub-header">View complete payment information and transaction history</p>
            </div>
        </div>

        <div class="modal-body">
                <div class="modal-grid">
                <div class="modal-box-item">
                    <label>Customer</label>
                    <p id="v-cust">--</p>
                </div>

                <div class="modal-box-item">
                    <label>Order ID</label>
                    <p id="v-order">--</p>
                </div>

                <div class="modal-box-item">
                    <label>Total Amount</label>
                    <p id="v-total">₱0</p>
                </div>

                <div class="modal-box-item">
                    <label>Paid / Downpayment</label>
                    <p id="v-paid">₱0</p>
                </div>

                <div class="modal-box-item">
                    <label>Due Date</label>
                    <p id="v-due">--</p>
                </div>

                <div class="modal-box-item">
                    <label>Remaining Balance</label>
                    <p id="v-bal-inner" class="modal-highlight">₱0</p>
                </div>
            </div>

            <div class="history-section">
                <h4 class="history-label">Payment History</h4>
                <div id="history-container" class="history-list"></div>
            </div>

            <button class="modal-btn-secondary" onclick="closeModal('viewDetailsModal')">Close Window</button>
        </div>
    </div>
</div>

<script>
function openModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = "flex";
    setTimeout(() => { modal.classList.add('active'); }, 10);
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('active');
    setTimeout(() => { modal.style.display = "none"; }, 300);
}

function triggerAddPayment(data) {
    document.getElementById('add-cust-name').innerText = data.customer;
    document.getElementById('add-balance').innerText = '₱' + Number(data.balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('pay_booking_id').value = data.booking_id;
    document.getElementById('pay_amt').value = '';
    document.getElementById('pay_amt').max = Number(data.balance).toFixed(2);
    document.getElementById('pay_date').value = new Date().toISOString().split('T')[0];
    openModal('addPaymentModal');
}

function triggerViewDetails(data) {
    document.getElementById('det-id').innerText = data.id;
    document.getElementById('v-cust').innerText = data.customer;
    document.getElementById('v-order').innerText = data.order_id;
    document.getElementById('v-total').innerText = '₱' + Number(data.total).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('v-paid').innerText = '₱' + Number(data.paid).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
    });
    document.getElementById('v-bal-inner').innerText = '₱' + Number(data.balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
    document.getElementById('v-due').innerText = data.due_date;

    const container = document.getElementById('history-container');
    container.innerHTML = '';

    data.history.forEach(h => {
        container.innerHTML += `
            <div class="history-item-card">
                <div class="hi-info">
                    <strong>₱${h.amount}</strong>
                    <span>${h.date} - ${h.method}</span>
                    <small>${h.note || ''}</small>
                </div>
                <div class="hi-tag">${h.type}</div>
            </div>
        `;
    });

    openModal('viewDetailsModal');
}

let currentBillingFilter = 'all';
let currentDateFrom = '';
let currentDateTo = '';

function filterPayments(status, el) {
    currentBillingFilter = status;

    document.querySelectorAll('.filter-item').forEach(i => i.classList.remove('active'));
    el.classList.add('active');

    applyAllBillingFilters();
}

function applyAllBillingFilters() {
    const searchValue = (document.getElementById('billingSearch')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.payment-row');

    rows.forEach(row => {
        const rowStatus = (row.dataset.status || '').toLowerCase();
        const rowDate = row.dataset.date || '';
        const rowSearch = (row.dataset.search || '').toLowerCase();

        const showByStatus = currentBillingFilter === 'all' || rowStatus === currentBillingFilter;
        const showBySearch = !searchValue || rowSearch.includes(searchValue);

        let showByDate = true;
        if (currentDateFrom && rowDate < currentDateFrom) showByDate = false;
        if (currentDateTo && rowDate > currentDateTo) showByDate = false;

        row.style.display = (showByStatus && showBySearch && showByDate) ? 'table-row' : 'none';
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
    const fromVal = document.getElementById('dateFrom').value;
    const toVal = document.getElementById('dateTo').value;

    if (fromVal && toVal && fromVal > toVal) {
        alert('The "From" date cannot be later than the "To" date.');
        return;
    }

    currentDateFrom = fromVal;
    currentDateTo = toVal;
    applyAllBillingFilters();
    closeDateDropdown();
}

function clearDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    currentDateFrom = '';
    currentDateTo = '';
    applyAllBillingFilters();
    closeDateDropdown();
}

document.addEventListener('click', function (e) {
    const wrap = document.querySelector('.date-filter-wrap');
    const dropdown = document.getElementById('dateRangeDropdown');
    const btn = document.getElementById('dateRangeBtn');

    if (wrap && !wrap.contains(e.target) && dropdown && btn) {
        dropdown.classList.remove('show');
        btn.classList.remove('active');
    }
});
</script>
</body>
</html>