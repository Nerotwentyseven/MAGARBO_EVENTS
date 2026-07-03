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

$sql = "SELECT a.*, 
        CONCAT(u.firstname, ' ', u.lastname) AS full_name,
        u.email,
        u.phone
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        ORDER BY 
            CASE 
                WHEN a.cancelled_at IS NOT NULL THEN a.cancelled_at
                ELSE a.created_at
            END DESC,
            a.id DESC";

$result = mysqli_query($conn, $sql);

$data = [];
$total = 0;
$pending = 0;
$approved = 0;
$completed = 0;
$cancelled = 0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
        $total++;

        if ($row['status'] === 'Pending') $pending++;
        if ($row['status'] === 'Approved') $approved++;
        if ($row['status'] === 'Completed') $completed++;
        if ($row['status'] === 'Cancelled') $cancelled++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Appointments | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" /> 
    <link rel="stylesheet" href="appointments.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="admin-main-container">
    
    <?php include('sidebar.php'); ?>

    <main class="main-content-area">
        <header class="page-header">
    <div class="header-left">
        <h1>Appointments</h1>
        <p>Manage customer appointment requests</p>
    </div>

    <div class="header-actions">
        <div class="date-filter-wrap">
            <button type="button" class="btn-date-trigger" id="dateRangeBtn" onclick="toggleDateDropdown()">
                <i class="far fa-calendar-alt"></i>
                <span>Date Range</span>
                <i class="fa-solid fa-chevron-down date-caret"></i>
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
                <span class="stat-tag">Total Appointments</span>
                <span class="stat-num" id="stat-total"><?php echo $total; ?></span>
            </div>
            <div class="msg-stat-card">
                <span class="stat-tag" style="color: #bf9225;">Pending</span>
                <span class="stat-num" id="stat-pending" style="color: #bf9225;"><?php echo $pending; ?></span>
            </div>
            <div class="msg-stat-card">
                <span class="stat-tag" style="color: #2b8a3e;">Approved</span>
                <span class="stat-num" id="stat-approved" style="color: #2b8a3e;"><?php echo $approved; ?></span>
            </div>
            <div class="msg-stat-card">
                <span class="stat-tag" style="color: #2563eb;">Completed</span>
                <span class="stat-num" id="stat-completed" style="color: #2563eb;"><?php echo $completed; ?></span>
            </div>
        </div>

        <div class="appointments-toolbar">
    <div class="filter-tabs-container">
        <button class="filter-btn active" onclick="filterTable('all', this)">All Status</button>
        <button class="filter-btn" onclick="filterTable('pending', this)">Pending</button>
        <button class="filter-btn" onclick="filterTable('approved', this)">Approved</button>
        <button class="filter-btn" onclick="filterTable('completed', this)">Completed</button>
        <button class="filter-btn" onclick="filterTable('cancelled', this)">Cancelled</button>
    </div>

    <div class="search-box-wrap">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="appointmentSearchInput" placeholder="Search customer, purpose, or ID..." onkeyup="applyAllAppointmentFilters()">
    </div>
</div>

        <div class="appointment-table-container">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Purpose</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if(!empty($data)): ?>
                    <?php foreach($data as $apt): ?>
                <?php
                    $displayAppointmentId = !empty($apt['display_appointment_id'])
                    ? $apt['display_appointment_id']
                    : 'APT' . (!empty($apt['apt_date']) ? date('Ymd', strtotime($apt['apt_date'])) : date('Ymd'));
                    ?>
                    <tr
                        data-status="<?php echo strtolower($apt['status']); ?>"
                        data-date="<?php echo htmlspecialchars($apt['apt_date']); ?>"
                        data-search="<?php echo strtolower(htmlspecialchars($displayAppointmentId . ' ' . $apt['full_name'] . ' ' . $apt['purpose'])); ?>"
                    >
                        <td><?php echo htmlspecialchars($displayAppointmentId); ?></td>
                        <td><?php echo $apt['full_name']; ?></td>
                        <td><?php echo $apt['purpose']; ?></td>
                        <td><?php echo date("m/d/Y", strtotime($apt['apt_date'])); ?></td>
                        <td><?php echo $apt['apt_time']; ?></td>
                        <td>
                            <span class="status-tag <?php echo strtolower($apt['status']); ?>">
                                <?php echo strtolower($apt['status']); ?>
                            </span>
                        </td>
                        <td class="action-group">

<?php if($apt['status'] == 'Pending'): ?>
    <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Approved"
    onclick="return openConfirm(event, this.href, 'Approve this appointment?')"
    class="btn-action-icon">
        <i class="fas fa-check"></i>
    </a>

    <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Cancelled"
    onclick="return openConfirm(event, this.href, 'Cancel this appointment?')"
    class="btn-action-icon">
        <i class="fas fa-times"></i>
    </a>
<?php elseif($apt['status'] == 'Approved'): ?>
    <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Completed"
    onclick="return openConfirm(event, this.href, 'Mark this appointment as completed?')"
    class="btn-action-icon">
        <i class="fas fa-check-double"></i>
    </a>
<?php endif; ?>

<button class="btn-action-icon"
    onclick="showDetails(
        '<?php echo htmlspecialchars($displayAppointmentId, ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['full_name'], ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['email'] ?? 'N/A', ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['phone'] ?? 'N/A', ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['purpose'], ENT_QUOTES); ?>',
        '<?php echo date("m/d/Y", strtotime($apt['apt_date'])); ?>',
        '<?php echo htmlspecialchars($apt['apt_time'], ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['status'], ENT_QUOTES); ?>',
        '<?php echo htmlspecialchars($apt['notes'] ?? 'No notes provided.', ENT_QUOTES); ?>'
    )">
    <i class="fas fa-eye"></i>
</button>

<?php
$canUndo = false;
if (
    $apt['status'] == 'Cancelled' &&
    !empty($apt['cancelled_at']) &&
    strtotime($apt['cancelled_at']) >= strtotime('-3 days')
) {
    $canUndo = true;
}
?>

<?php if($canUndo): ?>
    <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=UndoCancel"
    onclick="return openConfirm(event, this.href, 'Undo this cancelled appointment?')"
    class="btn-action-icon undo">
        <i class="fas fa-rotate-left"></i>
    </a>
<?php endif; ?>

                    </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td colspan="7">No appointments found</td></tr>
                    <?php endif; ?>
                    </tbody>
            </table>
        </div>
    </main>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-title">Appointment Details - <span id="det-id"></span></span>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">

            <div class="modal-grid">

                <div class="info-card">
                    <label>Customer Name</label>
                    <div class="card-body">
                        <p id="det-name"></p>
                    </div>
                </div>

                <div class="info-card">
                    <label>Preferred Date</label>
                    <div class="card-body">
                        <p id="det-date"></p>
                    </div>
                </div>

                <div class="info-card">
                    <label>Email</label>
                    <div class="card-body">
                        <p id="det-email"></p>
                    </div>
                </div>

                <div class="info-card">
                    <label>Preferred Time</label>
                    <div class="card-body">
                        <p id="det-time"></p>
                    </div>
                </div>

                <div class="info-card">
                    <label>Phone</label>
                    <div class="card-body">
                        <p id="det-phone"></p>
                    </div>
                </div>

                <div class="info-card">
                    <label>Purpose of Visit</label>
                    <div class="card-body">
                        <p id="det-purpose"></p>
                    </div>
                </div>

                <div class="info-card full-width">
                    <label>Notes</label>
                    <div class="notes-box">
                        <p id="det-notes"></p>
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

<script>
let currentAppointmentFilter = 'all';
let currentDateFrom = '';
let currentDateTo = '';
let appointmentsAdminPolling = null;
let confirmTargetUrl = '';

function filterTable(status, btn = null) {
    currentAppointmentFilter = status;

    // I-save ang current tab
    localStorage.setItem('appointmentsActiveTab', status);

    if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    applyAllAppointmentFilters();
}

function applyAllAppointmentFilters() {
    const rows = document.querySelectorAll('#tableBody tr');
    const searchValue = (document.getElementById('appointmentSearchInput')?.value || '').toLowerCase();

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowDate = row.getAttribute('data-date') || '';
        const rowSearch = row.getAttribute('data-search') || '';

        let showByStatus = currentAppointmentFilter === 'all' || rowStatus === currentAppointmentFilter;
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
    if (!dropdown || !btn) return;

    dropdown.classList.toggle('show');
    btn.classList.toggle('active');
}

function closeDateDropdown() {
    document.getElementById('dateRangeDropdown')?.classList.remove('show');
    document.getElementById('dateRangeBtn')?.classList.remove('active');
}

function applyDateRange() {
    currentDateFrom = document.getElementById('dateFrom').value;
    currentDateTo = document.getElementById('dateTo').value;
    applyAllAppointmentFilters();
    closeDateDropdown();
}

function clearDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    currentDateFrom = '';
    currentDateTo = '';
    applyAllAppointmentFilters();
    closeDateDropdown();
}

function showDetails(id, name, email, phone, purpose, date, time, status, notes) {
    document.getElementById('det-id').innerText = id;
    document.getElementById('det-name').innerText = name;
    document.getElementById('det-email').innerText = email;
    document.getElementById('det-phone').innerText = phone;
    document.getElementById('det-purpose').innerText = purpose;
    document.getElementById('det-date').innerText = date;
    document.getElementById('det-time').innerText = time;
    document.getElementById('det-notes').innerText = notes;
    document.getElementById('viewModal').style.display = "flex";
}

function closeModal() {
    document.getElementById('viewModal').style.display = "none";
}

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

async function refreshAdminAppointments() {
    try {
        const res = await fetch('ajax.php?action=fetch_appointments_admin', {
            cache: 'no-store'
        });
        const data = await res.json();

        if (data.success) {
            const tableBody = document.getElementById('tableBody');
            if (tableBody) tableBody.innerHTML = data.table_html;

            const statTotal = document.getElementById('stat-total');
            const statPending = document.getElementById('stat-pending');
            const statApproved = document.getElementById('stat-approved');
            const statCompleted = document.getElementById('stat-completed');

            if (statTotal) statTotal.textContent = data.total;
            if (statPending) statPending.textContent = data.pending;
            if (statApproved) statApproved.textContent = data.approved;
            if (statCompleted) statCompleted.textContent = data.completed;

            applyAllAppointmentFilters();
        }
    } catch (err) {
        console.error('Admin appointments refresh failed:', err);
    }
}

function startAdminAppointmentsPolling() {
    if (appointmentsAdminPolling) clearInterval(appointmentsAdminPolling);
    appointmentsAdminPolling = setInterval(refreshAdminAppointments, 3000);
}

document.addEventListener('DOMContentLoaded', function () {
    const confirmProceedBtn = document.getElementById('confirmProceedBtn');
    const confirmModal = document.getElementById('confirmModal');

    if (confirmProceedBtn) {
        confirmProceedBtn.addEventListener('click', function () {
            if (confirmTargetUrl) {
                window.location.href = confirmTargetUrl;
            }
        });
    }

    if (confirmModal) {
        confirmModal.addEventListener('click', function (e) {
            if (e.target === confirmModal) {
                closeConfirm();
            }
        });
    }
});

window.onload = function() {
    const links = document.querySelectorAll('.menu-link');
    links.forEach(link => {
        link.classList.remove('active');
        if (link.innerText.trim().includes("Appointments")) {
            link.classList.add('active');
        }
    });

    refreshAdminAppointments();

    const savedTab = localStorage.getItem('appointmentsActiveTab') || 'all';

    const savedBtn = document.querySelector(
        `.filter-btn[onclick*="'${savedTab}'"]`
    );

    if (savedBtn) {
        filterTable(savedTab, savedBtn);
    } else {
        filterTable('all', document.querySelector('.filter-btn'));
    }

    startAdminAppointmentsPolling();
};
</script>

</body>
</html>