<?php
require_once '../db_connection.php';

if (isset($_SESSION['user_id'])) {
    $user_id = (int) $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET last_active = NOW() WHERE id = $user_id");
}

$unread_count = 0;
$notifications = [];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    $delete_old = "DELETE FROM notifications 
                   WHERE user_id = ? 
                   AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt_delete = mysqli_prepare($conn, $delete_old);
    mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
    mysqli_stmt_execute($stmt_delete);

    $notif_count_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt_count = mysqli_prepare($conn, $notif_count_sql);
    mysqli_stmt_bind_param($stmt_count, "i", $user_id);
    mysqli_stmt_execute($stmt_count);
    $notif_count_result = mysqli_stmt_get_result($stmt_count);
    $notif_count_row = mysqli_fetch_assoc($notif_count_result);
    $unread_count = $notif_count_row['total'] ?? 0;

    $notif_list_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt_list = mysqli_prepare($conn, $notif_list_sql);
    mysqli_stmt_bind_param($stmt_list, "i", $user_id);
    mysqli_stmt_execute($stmt_list);
    $notif_list_result = mysqli_stmt_get_result($stmt_list);

    while ($row = mysqli_fetch_assoc($notif_list_result)) {
        $notifications[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($pageTitle) ? $pageTitle : "Magarbo Events"; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>" />
</head>

<body>

    <div id="authModal" class="modal-overlay">
        <div class="custom-modal">
            <i class="fa-solid fa-circle-exclamation modal-icon"></i>
            <h3>Access Restricted</h3>
            <p id="modalMessage">Please login first to access this feature.</p>
            <button class="modal-btn" onclick="location.href='login.php'">Sign In Now</button>
            <p style="margin-top: 15px; font-size: 13px; color: #888; cursor: pointer;"
                onclick="closeModal('authModal')">Close</p>
        </div>
    </div>

    <header class="navbar">
    <a href="<?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? '#home' : 'index.php#home'; ?>" class="logo" onclick="closeMobileMenu()">
    <img src="<?php echo dirname($_SERVER['PHP_SELF']) == '/' ? 'images/logo.jpg' : '../images/logo.jpg'; ?>" alt="Logo" />
    <span>Magarbo Events</span>
    </a>

    <nav class="desktop-nav">
        <ul class="nav-links">
            <li><a href="index.php#home" onclick="handleHomeClick(event)">Home</a></li>
            <li><a href="packages.php" onclick="handlePackagesClick(event)">Packages</a></li>
            <li><a href="gallery.php" onclick="handleGalleryClick(event)">Gallery</a></li>
            <li><a href="menu.php" onclick="handleMenuClick(event)">Menu</a></li>
            <li><a href="index.php#about">About Us</a></li>
            <li><a href="index.php#contact">Contact Us</a></li>
        </ul>
    </nav>

    <div class="nav-actions desktop-actions">
        <div class="notif-wrapper">
            <a href="javascript:void(0)" onclick="toggleNotif()" class="notif-bell-link">
                <i class="fa-regular fa-bell"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="notif-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>

            <div id="notifMiniAlert" class="notif-mini-alert">New Notification</div>

            
        </div>

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="profile.php" class="btn-profile"><i class="fa-regular fa-circle-user"></i> Profile</a>
        <?php else: ?>
            <button type="button" class="btn-primary" onclick="location.href='login.php'">Login</button>
        <?php endif; ?>
    </div>

    <button class="menu-toggle" id="menuToggle" type="button" onclick="toggleMobileMenu()" aria-label="Menu">
    <span></span>
    <span></span>
    <span></span>

    <?php if ($unread_count > 0): ?>
        <em class="mobile-menu-notif-badge" id="mobileNotifBadge">
    <?php echo $unread_count; ?>
    </em>
        <?php endif; ?>
    </button>
    </header>

    <div id="notifDropdown" class="notification-dropdown">
        <div class="notif-header">Notifications</div>
        <div class="notif-body">
            <?php if (!empty($notifications)): ?>
                <?php foreach ($notifications as $notif): ?>
                    <a href="mark_notification_read.php?redirect=<?php echo urlencode($notif['link'] ?? '#'); ?>"
                    class="notif-item <?php echo ($notif['is_read'] == 0) ? 'unread' : ''; ?>">
                        <div class="notif-icon">
                            <i class="fa-solid <?php echo ($notif['type'] === 'message') ? 'fa-envelope' : 'fa-calendar-star'; ?>"></i>
                        </div>
                        <div class="notif-text">
                            <p>
                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                <?php echo htmlspecialchars($notif['message']); ?>
                            </p>
                            <span><?php echo date('M d, Y g:i A', strtotime($notif['created_at'])); ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="padding:15px; color:#888;">No notifications yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="mobile-menu-overlay" id="mobileMenuOverlay" onclick="closeMobileMenu()"></div>

    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-inner">
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="javascript:void(0)" onclick="toggleNotif(event)" class="mobile-notif-link">
                    <span><i class="fa-regular fa-bell"></i> Notifications</span>
                    <?php if ($unread_count > 0): ?>
                        <span class="mobile-notif-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
            <a href="index.php#home" onclick="closeMobileMenu()">Home</a>
            <a href="packages.php" onclick="handlePackagesClick(event)">Packages</a>
            <a href="gallery.php" onclick="handleGalleryClick(event)">Gallery</a>
            <a href="menu.php" onclick="handleMenuClick(event)">Menu</a>
            <a href="index.php#about" onclick="closeMobileMenu()">About Us</a>
            <a href="index.php#contact" onclick="closeMobileMenu()">Contact Us</a>

            <div class="mobile-menu-divider"></div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="mobile-profile-link" onclick="closeMobileMenu()">Profile</a>
            <?php else: ?>
                <a href="login.php" class="mobile-login-link" onclick="closeMobileMenu()">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

        document.querySelectorAll('a[href^="index.php#"], a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');

                if (href.includes('#')) {
                    const targetId = href.split('#')[1];
                    const targetElement = document.getElementById(targetId);
                    const isIndexPage = window.location.pathname.endsWith('index.php') || window.location.pathname.endsWith('/');

                    if (targetElement && isIndexPage) {
                        e.preventDefault();
                        closeMobileMenu();
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }
            });
        });

        function handleHomeClick(e) {
            const currentPage = window.location.pathname.split('/').pop();

            if (currentPage === 'index.php' || currentPage === '') {
                e.preventDefault();

                closeMobileMenu();

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            }
        }

        function handlePackagesClick(e) {
            const currentPage = window.location.pathname.split('/').pop();

            if (currentPage === 'packages.php') {
                e.preventDefault();

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                closeMobileMenu();
            }
        }

        function handleGalleryClick(e) {
            const currentPage = window.location.pathname.split('/').pop();

            if (currentPage === 'gallery.php') {
                e.preventDefault();

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                closeMobileMenu();
            }
        }

        function handleMenuClick(e) {
            const currentPage = window.location.pathname.split('/').pop();

            if (currentPage === 'menu.php') {
                e.preventDefault();

                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });

                closeMobileMenu();
            }
        }

        function toggleNotif(e) {
        if (e) e.stopPropagation();

        const d = document.getElementById('notifDropdown');
        if (!d) return;

        const isOpen = d.style.display === 'block';

        closeMobileMenu();

        d.style.display = isOpen ? 'none' : 'block';
    }

        function openFeedback() {
            if (!isLoggedIn) {
                document.getElementById('modalMessage').innerText = "Please login first to give feedback.";
                document.getElementById('authModal').style.display = 'flex';
            } else {
                const feedbackModal = document.getElementById('feedbackModal');
                if (feedbackModal) feedbackModal.style.display = 'flex';
            }
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function setRating(n) {
            const ratingInput = document.getElementById('ratingInput');
            const stars = document.querySelectorAll('#starGroup i');

            if (ratingInput) ratingInput.value = n;
            if (stars.length) {
                stars.forEach((s, i) => s.classList.toggle('active', i < n));
            }
        }

        if (document.getElementById('ratingInput')) {
            setRating(5);
        }

        function toggleMobileMenu() {
            document.getElementById('mobileMenu').classList.toggle('active');
            document.getElementById('mobileMenuOverlay').classList.toggle('active');
            document.getElementById('menuToggle').classList.toggle('active');
            document.body.classList.toggle('menu-open');
        }

        function closeMobileMenu() {
            document.getElementById('mobileMenu').classList.remove('active');
            document.getElementById('mobileMenuOverlay').classList.remove('active');
            document.getElementById('menuToggle').classList.remove('active');
            document.body.classList.remove('menu-open');
        }

        document.addEventListener('click', function (e) {
            if (e.target.className === 'modal-overlay') e.target.style.display = 'none';

            if (
                !e.target.closest('.notif-wrapper') &&
                !e.target.closest('.mobile-notif-link') &&
                !e.target.closest('#notifDropdown')
            ) {
                        const d = document.getElementById('notifDropdown');
                        if (d) d.style.display = 'none';
            }
        });

        let lastNotifCount = 0;
        let notifMiniAlertTimeout = null;

        function showNotifMiniAlert(message = 'New Notification') {
            const alertBox = document.getElementById('notifMiniAlert');
            if (!alertBox) return;

            alertBox.textContent = message;
            alertBox.classList.add('show');

            if (notifMiniAlertTimeout) {
                clearTimeout(notifMiniAlertTimeout);
            }

            notifMiniAlertTimeout = setTimeout(() => {
                alertBox.classList.remove('show');
            }, 3000);
        }

        function refreshNotifications() {
            fetch('ajax.php?action=get_notifications')
                .then(response => response.json())
                .then(data => {
                    const badge = document.querySelector('.notif-badge');
                    let mobileBadge = document.getElementById('mobileNotifBadge');
                    const menuToggle = document.getElementById('menuToggle');
                    const bellLink = document.querySelector('.notif-bell-link');
                    const notifBody = document.querySelector('.notif-body');

                    if (!notifBody || !bellLink) return;

                    if (data.unread_count > lastNotifCount) {
                        const latestNotif = (data.notifications && data.notifications.length > 0) ? data.notifications[0] : null;

                        if (latestNotif && latestNotif.type === 'message') {
                            showNotifMiniAlert('New message from Admin');
                        } else {
                            showNotifMiniAlert('New Notification');
                        }
                    }

                    lastNotifCount = data.unread_count;

                    if (data.unread_count > 0) {

                        if (badge) {
                            badge.textContent = data.unread_count;
                        } else {
                            const span = document.createElement('span');
                            span.className = 'notif-badge';
                            span.textContent = data.unread_count;
                            bellLink.appendChild(span);
                        }

                    if (mobileBadge) {
                        mobileBadge.textContent = data.unread_count;
                    } else if (menuToggle) {
                        mobileBadge = document.createElement('em');
                        mobileBadge.className = 'mobile-menu-notif-badge';
                        mobileBadge.id = 'mobileNotifBadge';
                        mobileBadge.textContent = data.unread_count;
                        menuToggle.appendChild(mobileBadge);
                    }

                    } else {

                        if (badge) badge.remove();

                        if (mobileBadge) {
                            mobileBadge.remove();
                        }
                    }

                    let html = '';
                    if (data.notifications.length > 0) {
                        data.notifications.forEach(notif => {
                            html += `
                                <a href="mark_notification_read.php?redirect=${encodeURIComponent(notif.link ?? '#')}" class="notif-item ${notif.is_read == 0 ? 'unread' : ''}">
                                    <div class="notif-icon">
                                        <i class="fa-solid ${notif.type === 'message' ? 'fa-envelope' : 'fa-calendar-star'}"></i>
                                    </div>
                                    <div class="notif-text">
                                        <p><strong>${notif.title}</strong> ${notif.message}</p>
                                        <span>${notif.created_at}</span>
                                    </div>
                                </a>
                            `;
                        });
                    } else {
                        html = `<div style="padding:15px; color:#888; font-size:14px;">No notifications yet.</div>`;
                    }

                    notifBody.innerHTML = html;
                })
                .catch(error => console.log('Notification fetch error:', error));
        }

        setInterval(refreshNotifications, 3000);
        window.onload = function () {
            refreshNotifications();
        };

        let userHeartbeatPolling = null;

        function pingUserHeartbeat() {
            if (!isLoggedIn) return;

            fetch('ajax.php?action=check_account_status', {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            })
            .then(response => response.json())
            .then(data => {
                if (data.logout) {
                    window.location.href = 'login.php';
                    return;
                }

                return fetch('ajax.php?action=heartbeat', {
                    method: 'GET',
                    cache: 'no-store',
                    credentials: 'same-origin'
                });
            })
            .catch(error => console.log('Heartbeat/status fetch error:', error));
        }

        function startUserHeartbeat() {
            if (!isLoggedIn) return;
            if (userHeartbeatPolling) return;

            pingUserHeartbeat();
            userHeartbeatPolling = setInterval(pingUserHeartbeat, 10000);
        }

        function stopUserHeartbeat() {
            if (userHeartbeatPolling) {
                clearInterval(userHeartbeatPolling);
                userHeartbeatPolling = null;
            }
        }

        if (!document.hidden) {
            startUserHeartbeat();
        }

        window.addEventListener('focus', function () {
            if (!document.hidden) {
                startUserHeartbeat();
            }
        });

        window.addEventListener('pageshow', function () {
            if (!document.hidden) {
                startUserHeartbeat();
            }
        });

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                stopUserHeartbeat();
            } else {
                startUserHeartbeat();
            }
        });

        window.addEventListener('pagehide', stopUserHeartbeat);
        window.addEventListener('beforeunload', stopUserHeartbeat);
    </script>