<?php
require_once '../db_connection.php';
$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");

if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

$settings = [];
$settingsRes = mysqli_query($conn, "SELECT notif_order, notif_msg, notif_appointment FROM admin_settings WHERE id = 1 LIMIT 1");

if ($settingsRes) {
    $settings = mysqli_fetch_assoc($settingsRes) ?: [];
}

$notif_order_enabled = (int)($settings['notif_order'] ?? 1);
$notif_msg_enabled = (int)($settings['notif_msg'] ?? 1);
$notif_appointment_enabled = (int)($settings['notif_appointment'] ?? 1);


$current_page = basename($_SERVER['PHP_SELF']);

$unread_messages = 0;
if ($notif_msg_enabled === 1) {
    $msg_sql = "SELECT COUNT(*) AS total FROM chat_messages WHERE sender = 'user' AND is_read = 0";
    $msg_result = mysqli_query($conn, $msg_sql);
    if ($msg_result) {
        $msg_row = mysqli_fetch_assoc($msg_result);
        $unread_messages = (int)($msg_row['total'] ?? 0);
    }
}

$pending_appointments = 0;
if ($notif_appointment_enabled === 1) {
    $apt_sql = "SELECT COUNT(*) AS total FROM appointments WHERE status = 'Pending'";
    $apt_result = mysqli_query($conn, $apt_sql);
    if ($apt_result) {
        $apt_row = mysqli_fetch_assoc($apt_result);
        $pending_appointments = (int)($apt_row['total'] ?? 0);
    }
}

$pending_orders = 0;
if ($notif_order_enabled === 1) {
    $order_sql = "SELECT COUNT(*) AS total FROM bookings WHERE booking_status = 'Pending'";
    $order_result = mysqli_query($conn, $order_sql);
    if ($order_result) {
        $order_row = mysqli_fetch_assoc($order_result);
        $pending_orders = (int)($order_row['total'] ?? 0);
    }
}

$total_admin_notifs = $unread_messages + $pending_appointments + $pending_orders;
?>

<div class="sidebar-container">
    <div class="sidebar-header">
        <div class="brand-box">
            <img class="logo-img" src="logo.jpg" alt="Logo" />
            <div class="brand-text">
                <div class="name">Magarbo Events</div>
                <div class="role">Admin Panel</div>
            </div>
            
            <div class="notif-wrapper" style="position: relative;">
                <i class="fas fa-bell bell-icon" onclick="toggleNotifications(event)" style="cursor:pointer; position:relative;"></i>

                <?php if ($total_admin_notifs > 0): ?>
                <span class="notif-badge" style="
                        position:absolute;
                        top:-6px;
                        right:-8px;
                        background:#ef4444;
                        color:#fff;
                        font-size:11px;
                        min-width:18px;
                        height:18px;
                        border-radius:999px;
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        padding:0 5px;
                        font-weight:700;
                    ">
                        <?php echo $total_admin_notifs; ?>
                    </span>
                <?php endif; ?>

                <div id="notif-dropdown" class="notif-dropdown">
                    <div class="notif-title">Recent Updates</div>
                    <div class="notif-list">

                        <div id="messageNotifRow">
                        <?php if ($unread_messages > 0): ?>
                            <a href="messages.php" class="notif-item" style="display:block; text-decoration:none; color:inherit;">
                                <strong>New customer messages</strong><br>
                                <small><?php echo $unread_messages; ?> unread customer message<?php echo $unread_messages > 1 ? 's' : ''; ?> waiting for response</small>
                            </a>
                        <?php endif; ?>
                    </div>

                        <?php if ($pending_appointments > 0): ?>
                            <a href="appointments.php" class="notif-item" style="display:block; text-decoration:none; color:inherit;">
                                <strong>Pending appointments</strong><br>
                                <small>Needs approval or update</small>
                            </a>
                        <?php endif; ?>

                        <?php if ($pending_orders > 0): ?>
                            <a href="orders.php" class="notif-item" style="display:block; text-decoration:none; color:inherit;">
                                <strong>Pending orders</strong><br>
                                <small>Booking requests waiting for approval</small>
                            </a>
                        <?php endif; ?>

                        <?php if ($total_admin_notifs === 0): ?>
                            <div class="notif-item">
                                <strong>No new notifications</strong><br>
                                <small>Everything is up to date</small>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <a href="dashboard.php" class="menu-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i> <span>Dashboard</span>
        </a>
        <a href="messages.php" class="menu-link <?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i> <span>Messages</span>
        </a>
        <a href="appointments.php" class="menu-link <?php echo ($current_page == 'appointments.php') ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i> <span>Appointments</span>
        </a>
        <a href="orders.php" class="menu-link <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">
            <i class="fas fa-shopping-bag"></i> <span>Orders</span>
        </a>
        <a href="events.php" class="menu-link <?php echo ($current_page == 'events.php') ? 'active' : ''; ?>">
            <i class="fas fa-star"></i> <span>Events</span>
        </a>
        <a href="package_management.php" class="menu-link <?php echo ($current_page == 'package_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-box"></i> <span>Package Management</span>
        </a>
        <a href="menu_management.php" class="menu-link <?php echo ($current_page == 'menu_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-utensils"></i> <span>Menu Management</span>
        </a>
        <a href="review_management.php" class="menu-link <?php echo ($current_page == 'review_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-comments"></i> <span>Review Management</span>
        </a>
        <a href="gallery_management.php" class="menu-link <?php echo ($current_page == 'gallery_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-images"></i> <span>Gallery Management</span>
        </a>
        <a href="user_management.php" class="menu-link <?php echo ($current_page == 'user_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>User Management</span>
        </a>
        <a href="billing.php" class="menu-link <?php echo ($current_page == 'billing.php') ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i> <span>Payment & Billing</span>
        </a>
        <a href="employees.php" class="menu-link <?php echo ($current_page == 'employees.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> <span>Employee List</span>
        </a>
        <a href="analytics.php" class="menu-link <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> <span>Reports & Analytics</span>
        </a>
        <a href="admin_settings.php" class="menu-link <?php echo ($current_page == 'admin_settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> <span>Settings</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
    </div>
</div>

<script>
    function toggleNotifications(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notif-dropdown');
        dropdown.classList.toggle('active');
    }

    window.onclick = function(event) {
        if (!event.target.matches('.bell-icon')) {
            const dropdowns = document.getElementsByClassName("notif-dropdown");
            for (let i = 0; i < dropdowns.length; i++) {
                let openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('active')) {
                    openDropdown.classList.remove('active');
                }
            }
        }
    };

    
    function loadAdminNotifications() {
        fetch('admin_notifications.php')
            .then(res => res.json())
            .then(data => {

    const badge = document.querySelector('.notif-badge');
    const wrapper = document.querySelector('.notif-wrapper');
    const messageRow = document.getElementById('messageNotifRow');

    if (data.total > 0) {
        if (badge) {
            badge.textContent = data.total;
        } else {
            const span = document.createElement('span');
            span.className = 'notif-badge';
            span.textContent = data.total;
            wrapper.appendChild(span);
        }
    } else {
        if (badge) badge.remove();
    }

    const unreadMessages = parseInt(data.unread_messages ?? data.messages ?? 0);

    if (messageRow) {
        if (unreadMessages > 0) {
            messageRow.innerHTML = `
                <a href="messages.php" class="notif-item" style="display:block; text-decoration:none; color:inherit;">
                    <strong>New customer messages</strong><br>
                    <small>${unreadMessages} unread customer message${unreadMessages > 1 ? 's' : ''} waiting for response</small>
                </a>
            `;
        } else {
            messageRow.innerHTML = '';
        }
    }

})
            .catch(err => console.log(err));
    }

    setInterval(loadAdminNotifications, 5000);

    loadAdminNotifications();
</script>