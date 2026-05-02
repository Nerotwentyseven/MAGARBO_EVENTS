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

$sql = "SELECT * FROM users
        ORDER BY
            DATE(COALESCE(created_at, NOW())) DESC,
            created_at ASC,
            id ASC";
$result = mysqli_query($conn, $sql);

$data = [];
$totalUsers = 0;
$activeUsers = 0;
$deactivatedUsers = 0;
$deletedUsers = 0;

/*
|--------------------------------------------------------------------------
| Generate display IDs like:
| USR-20260418
| USR-20260418-2
|--------------------------------------------------------------------------
*/
$generatedUserIds = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $rowStatus = trim($row['status'] ?? '');
        if ($rowStatus === '') {
            $rowStatus = 'Active';
        }

        $row['status'] = $rowStatus;

        if (!empty($row['display_user_id'])) {
            $row['display_id'] = $row['display_user_id'];
        } else {
            $dateKey = !empty($row['created_at'])
                ? date('Ymd', strtotime($row['created_at']))
                : '00000000';

            $baseId = 'USR' . $dateKey;

            if (!isset($generatedUserIds[$baseId])) {
                $generatedUserIds[$baseId] = 1;
                $row['display_id'] = $baseId;
            } else {
                $generatedUserIds[$baseId]++;
                $row['display_id'] = $baseId . '-' . $generatedUserIds[$baseId];
            }
        }

        $data[] = $row;
        $totalUsers++;

        if ($rowStatus === 'Active') $activeUsers++;
        if ($rowStatus === 'Deactivated') $deactivatedUsers++;
        if ($rowStatus === 'Deleted') $deletedUsers++;
    }
}

$current_page = 'user_management.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>User Management | Magarbo Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="user_management.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="admin-main-container">
    <?php include('sidebar.php'); ?>

    <main class="main-content-area">
        <header class="page-title-section">
            <h1>User Management</h1>
            <p>Manage registered users and review account information.</p>
        </header>

        <div class="msg-stats-grid">
            <div class="msg-stat-card">
                <span class="stat-tag">Total Users</span>
                <span class="stat-num" id="stat-total"><?php echo $totalUsers; ?></span>
                <span class="stat-sub">All registered user accounts</span>
            </div>

            <div class="msg-stat-card active-accent">
                <span class="stat-tag">Active</span>
                <span class="stat-num" id="stat-active"><?php echo $activeUsers; ?></span>
                <span class="stat-sub">Currently active user accounts</span>
            </div>

            <div class="msg-stat-card deactivated-accent">
                <span class="stat-tag">Deactivated</span>
                <span class="stat-num" id="stat-deactivated"><?php echo $deactivatedUsers; ?></span>
                <span class="stat-sub">Temporarily disabled user accounts</span>
            </div>

            <div class="msg-stat-card deleted-accent">
                <span class="stat-tag">Deleted</span>
                <span class="stat-num" id="stat-deleted"><?php echo $deletedUsers; ?></span>
                <span class="stat-sub">Soft-deleted accounts with undo option</span>
            </div>
        </div>

        <div class="users-toolbar">
            <div class="filter-tabs-container">
                <button class="filter-btn active" onclick="filterTable('all', this)">All Users</button>
                <button class="filter-btn" onclick="filterTable('active', this)">Active</button>
                <button class="filter-btn" onclick="filterTable('deactivated', this)">Deactivated</button>
                <button class="filter-btn" onclick="filterTable('deleted', this)">Deleted</button>
            </div>

            <div class="search-box-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="userSearchInput" placeholder="Search name, email, phone, or ID..." onkeyup="applyAllUserFilters()">
            </div>
        </div>

        <div class="user-table-container">
            <table class="user-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (!empty($data)): ?>
                        <?php foreach ($data as $user): ?>
                            <?php
                                $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
                                $status = strtolower($user['status'] ?? 'active');
                                $canUndoDelete = false;

                                if (
                                    ($user['status'] ?? '') === 'Deleted' &&
                                    !empty($user['deleted_at']) &&
                                    strtotime($user['deleted_at']) >= strtotime('-3 days')
                                ) {
                                    $canUndoDelete = true;
                                }
                            ?>
                            <tr
                                data-status="<?php echo htmlspecialchars($status); ?>"
                                data-search="<?php echo strtolower(htmlspecialchars(
                                    ($user['display_id'] ?? '') . ' ' .
                                    $fullName . ' ' .
                                    ($user['email'] ?? '') . ' ' .
                                    ($user['phone'] ?? '') . ' ' .
                                    ($user['status'] ?? '')
                                )); ?>"
                            >
                                <td><?php echo htmlspecialchars($user['display_id']); ?></td>
                                <td><?php echo htmlspecialchars($fullName ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-tag <?php echo $status; ?>">
                                        <?php echo htmlspecialchars($user['status'] ?? 'Active'); ?>
                                    </span>
                                </td>
                                <td class="action-group">

                                    <button type="button" class="btn-action-icon"
                                        onclick="showDetails(
                                            '<?php echo htmlspecialchars($user['display_id'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($fullName ?: 'N/A', ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($user['firstname'] ?? 'N/A', ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($user['lastname'] ?? 'N/A', ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($user['email'] ?? 'N/A', ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($user['phone'] ?? 'N/A', ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($user['status'] ?? 'Active', ENT_QUOTES); ?>',
                                            '<?php echo !empty($user['created_at']) ? date("m/d/Y", strtotime($user['created_at'])) : 'N/A'; ?>'
                                        )">
                                        <i class="fas fa-eye"></i>
                                    </button>

                                    <?php if (($user['status'] ?? 'Active') === 'Active'): ?>
                                        <a href="update_user_status.php?id=<?php echo (int)$user['id']; ?>&status=Deactivated"
                                           onclick="return openUserActionConfirm(event, this.href, 'Deactivate this user?', 'deactivate')"
                                           class="btn-action-text deactivate-btn">
                                            Deactivate
                                        </a>

                                        <a href="update_user_status.php?id=<?php echo (int)$user['id']; ?>&status=Deleted"
                                           onclick="return openUserActionConfirm(event, this.href, 'Delete this user?', 'delete')"
                                           class="btn-action-text delete-btn">
                                            Delete
                                        </a>

                                    <?php elseif (($user['status'] ?? '') === 'Deactivated'): ?>
                                        <a href="update_user_status.php?id=<?php echo (int)$user['id']; ?>&status=Active"
                                           onclick="return openUserActionConfirm(event, this.href, 'Activate this user?', 'activate')"
                                           class="btn-action-text activate-btn">
                                            Activate
                                        </a>

                                        <a href="update_user_status.php?id=<?php echo (int)$user['id']; ?>&status=Deleted"
                                           onclick="return openUserActionConfirm(event, this.href, 'Delete this user?', 'delete')"
                                           class="btn-action-text delete-btn">
                                            Delete
                                        </a>

                                    <?php elseif (($user['status'] ?? '') === 'Deleted' && $canUndoDelete): ?>
                                        <a href="update_user_status.php?id=<?php echo (int)$user['id']; ?>&status=Active"
                                           onclick="return openUserActionConfirm(event, this.href, 'Undo delete for this user?', 'undo')"
                                           class="btn-action-icon undo-icon"
                                           title="Undo">
                                            <i class="fas fa-rotate-left"></i>
                                        </a>
                                    <?php endif; ?>

                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No users found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content user-detail-width">
        <button type="button" class="close-btn user-close-btn" onclick="closeModal()">&times;</button>

        <div class="modal-header user-modal-header">
            <div class="header-text">
                <h3>User Details - <span id="det-id">--</span></h3>
                <p class="sub-header">View complete user account information</p>
            </div>
        </div>

        <div class="modal-body user-modal-body">
            <div class="user-modal-grid">
                <div class="user-box-item">
                    <label>Full Name</label>
                    <p id="det-fullname">--</p>
                </div>

                <div class="user-box-item">
                    <label>Email</label>
                    <p id="det-email">--</p>
                </div>

                <div class="user-box-item">
                    <label>First Name</label>
                    <p id="det-firstname">--</p>
                </div>

                <div class="user-box-item">
                    <label>Last Name</label>
                    <p id="det-lastname">--</p>
                </div>

                <div class="user-box-item">
                    <label>Phone</label>
                    <p id="det-phone">--</p>
                </div>

                <div class="user-box-item">
                    <label>Status</label>
                    <p id="det-status">--</p>
                </div>

                <div class="user-box-item user-full">
                    <label>Date Created</label>
                    <p id="det-date">--</p>
                </div>
            </div>

            <button class="user-modal-btn-secondary" onclick="closeModal()">Close Window</button>
        </div>
    </div>
</div>

<div id="userActionConfirmModal" class="confirm-overlay">
    <div class="confirm-card">
        <div class="confirm-icon-wrap" id="userConfirmIconWrap">
            <i id="userConfirmIcon" class="fas fa-user-slash"></i>
        </div>

        <h3 id="userConfirmTitle">Confirm Action</h3>
        <p id="userConfirmMessage">Are you sure?</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeUserActionConfirm()">No</button>
            <button type="button" class="confirm-btn approve" id="userConfirmProceedBtn">Yes</button>
        </div>
    </div>
</div>

<script>
let userConfirmUrl = '';
let currentUserFilter = 'all';

function openUserActionConfirm(event, url, message, type = 'delete') {
    if (event) event.preventDefault();

    userConfirmUrl = url;

    const modal = document.getElementById('userActionConfirmModal');
    const msg = document.getElementById('userConfirmMessage');
    const title = document.getElementById('userConfirmTitle');
    const iconWrap = document.getElementById('userConfirmIconWrap');
    const icon = document.getElementById('userConfirmIcon');
    const proceedBtn = document.getElementById('userConfirmProceedBtn');

    msg.innerText = message;

    iconWrap.classList.remove('delete-mode', 'activate-mode', 'deactivate-mode', 'undo-mode');
    proceedBtn.classList.remove('delete-confirm', 'activate-confirm', 'deactivate-confirm', 'undo-confirm');

    if (type === 'delete') {
        title.innerText = 'Delete User';
        icon.className = 'fas fa-trash';
        iconWrap.classList.add('delete-mode');
        proceedBtn.classList.add('delete-confirm');
        proceedBtn.innerText = 'Delete';
    } else if (type === 'activate') {
        title.innerText = 'Activate User';
        icon.className = 'fas fa-user-check';
        iconWrap.classList.add('activate-mode');
        proceedBtn.classList.add('activate-confirm');
        proceedBtn.innerText = 'Activate';
    } else if (type === 'undo') {
        title.innerText = 'Undo Delete';
        icon.className = 'fas fa-rotate-left';
        iconWrap.classList.add('undo-mode');
        proceedBtn.classList.add('undo-confirm');
        proceedBtn.innerText = 'Undo';
    } else {
        title.innerText = 'Deactivate User';
        icon.className = 'fas fa-user-slash';
        iconWrap.classList.add('deactivate-mode');
        proceedBtn.classList.add('deactivate-confirm');
        proceedBtn.innerText = 'Deactivate';
    }

    modal.style.display = 'flex';
    return false;
}

function closeUserActionConfirm() {
    document.getElementById('userActionConfirmModal').style.display = 'none';
    userConfirmUrl = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const proceedBtn = document.getElementById('userConfirmProceedBtn');

    if (proceedBtn) {
        proceedBtn.addEventListener('click', function () {
            if (userConfirmUrl) {
                window.location.href = userConfirmUrl;
            }
        });
    }
});

function filterTable(status, btn = null) {
    currentUserFilter = status;

    if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    applyAllUserFilters();
}

function applyAllUserFilters() {
    const rows = document.querySelectorAll('#tableBody tr');
    const searchValue = (document.getElementById('userSearchInput')?.value || '').toLowerCase();

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status') || '';
        const rowSearch = row.getAttribute('data-search') || '';

        let showByStatus = currentUserFilter === 'all' || rowStatus === currentUserFilter;
        let showBySearch = rowSearch.includes(searchValue);

        row.style.display = (showByStatus && showBySearch) ? '' : 'none';
    });
}

function showDetails(id, fullName, firstName, lastName, email, phone, status, date) {
    document.getElementById('det-id').innerText = id;
    document.getElementById('det-fullname').innerText = fullName;
    document.getElementById('det-firstname').innerText = firstName;
    document.getElementById('det-lastname').innerText = lastName;
    document.getElementById('det-email').innerText = email;
    document.getElementById('det-phone').innerText = phone;
    document.getElementById('det-status').innerText = status;
    document.getElementById('det-date').innerText = date;
    document.getElementById('viewModal').style.display = "flex";
}

function closeModal() {
    document.getElementById('viewModal').style.display = "none";
}

window.onload = function() {
    const links = document.querySelectorAll('.menu-link');
    links.forEach(link => {
        link.classList.remove('active');
        if (link.innerText.trim().includes("User")) {
            link.classList.add('active');
        }
    });
};
</script>

</body>
</html>