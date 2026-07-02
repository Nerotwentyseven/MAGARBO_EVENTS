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
            
            <div class="notif-wrapper">
    <i class="fas fa-bell bell-icon" onclick="toggleNotifications(event)"></i>

    <span id="adminNotifBadge" class="notif-badge">0</span>

    <div id="notif-dropdown" class="notif-dropdown">
        <div class="notif-title">Notifications</div>

        <div class="notif-list" id="adminNotifList">
            <div class="notif-item">
                <strong>Loading...</strong><br>
                <small>Checking notifications</small>
            </div>
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
    document.getElementById('notif-dropdown')?.classList.toggle('active');
}

document.addEventListener('click', function(event) {
    if (!event.target.closest('.notif-wrapper')) {
        document.getElementById('notif-dropdown')?.classList.remove('active');
    }
});

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function(match) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[match];
    });
}

function timeAgo(dateString) {
    if (!dateString) return '';

    const date = new Date(dateString.replace(' ', 'T'));
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'Just now';

    const mins = Math.floor(diff / 60);
    if (mins < 60) return mins + ' min' + (mins > 1 ? 's' : '') + ' ago';

    const hours = Math.floor(mins / 60);
    if (hours < 24) return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';

    const days = Math.floor(hours / 24);
    if (days < 30) return days + ' day' + (days > 1 ? 's' : '') + ' ago';

    return '30+ days ago';
}

function markAdminNotif(type, id, link, lastId) {
    fetch('admin_notifications.php?action=mark_read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}&last_id=${encodeURIComponent(lastId || id)}`
    }).finally(() => {
        window.location.href = link;
    });
}

function loadAdminNotifications() {
    fetch('admin_notifications.php?action=list')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('adminNotifBadge');
            const list = document.getElementById('adminNotifList');

            if (!data.success) return;

            if (data.total > 0) {
                badge.textContent = data.total > 99 ? '99+' : data.total;
                badge.style.display = 'inline-flex';
            } else {
                badge.textContent = '0';
                badge.style.display = 'none';
            }

            if (!data.items || data.items.length === 0) {
                list.innerHTML = `
                    <div class="notif-item">
                        <strong>No new notifications</strong><br>
                        <small>Everything is up to date</small>
                    </div>
                `;
                return;
            }

            list.innerHTML = data.items.map(item => `
                <div class="notif-item ${item.is_read == 1 ? 'read' : 'unread'}"
                    onclick="markAdminNotif('${escapeHtml(item.type)}', '${escapeHtml(item.id)}', '${escapeHtml(item.link)}', '${escapeHtml(item.last_id)}')">
                    <strong>${escapeHtml(item.title)}</strong><br>
                    <small>${escapeHtml(item.text)}</small>
                    <span class="notif-time">${timeAgo(item.time)}</span>
                </div>
            `).join('');
        })
        .catch(err => console.log(err));
}

const sidebarMenu = document.querySelector('.sidebar-menu');

if (sidebarMenu) {
    const savedSidebarScroll = sessionStorage.getItem('sidebarMenuScroll');

    if (savedSidebarScroll !== null) {
        sidebarMenu.scrollTop = parseInt(savedSidebarScroll, 10);
    }

    sidebarMenu.addEventListener('scroll', function () {
        sessionStorage.setItem('sidebarMenuScroll', sidebarMenu.scrollTop);
    });

    document.querySelectorAll('.sidebar-menu .menu-link').forEach(link => {
        link.addEventListener('click', function () {
            sessionStorage.setItem('sidebarMenuScroll', sidebarMenu.scrollTop);
        });
    });
}

loadAdminNotifications();
setInterval(loadAdminNotifications, 5000);
</script>