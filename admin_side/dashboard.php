<?php
session_name('ADMINSESSID');
session_start();

require_once 'admin_auth.php';
require_once '../db_connection.php';

$theme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $theme = $themeRow['theme'] ?? 'Light Mode';
}


// TOTAL ORDERS TODAY (lahat ng orders na ginawa today)
$todaySql = "SELECT COUNT(*) AS total_today
             FROM bookings
             WHERE DATE(created_at) = CURDATE()";
$todayResult = mysqli_query($conn, $todaySql);
$todayData = mysqli_fetch_assoc($todayResult);
$totalToday = $todayData['total_today'] ?? 0;

// YESTERDAY (lahat din ng orders kahapon)
$yesterdaySql = "SELECT COUNT(*) AS total_yesterday
                 FROM bookings
                 WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
$yesterdayResult = mysqli_query($conn, $yesterdaySql);
$yesterdayData = mysqli_fetch_assoc($yesterdayResult);
$totalYesterday = $yesterdayData['total_yesterday'] ?? 0;

// PERCENT CHANGE
if ($totalYesterday > 0) {
    $percentChange = (($totalToday - $totalYesterday) / $totalYesterday) * 100;
} else {
    $percentChange = ($totalToday > 0) ? 100 : 0;
}
$trendText = ($percentChange >= 0 ? '+' : '') . round($percentChange) . "% from yesterday";

// PENDING APPROVALS
$pendingSql = "SELECT COUNT(*) AS total_pending
               FROM bookings
               WHERE booking_status = 'Pending'";
$pendingResult = mysqli_query($conn, $pendingSql);
$pendingData = mysqli_fetch_assoc($pendingResult);
$totalPending = $pendingData['total_pending'] ?? 0;


// RECENT ORDERS (max 5, pending only)
$recentSql = "SELECT b.*, 
              CONCAT(u.firstname, ' ', u.lastname) AS full_name
              FROM bookings b
              JOIN users u ON b.user_id = u.id
              WHERE b.booking_status = 'Pending'
              ORDER BY b.id DESC
              LIMIT 5";
$recentResult = mysqli_query($conn, $recentSql);

$calendarSql = "SELECT b.event_date, b.event_time, b.event_type, b.venue,
                       CONCAT(u.firstname, ' ', u.lastname) AS full_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                WHERE b.booking_status = 'Approved'
                ORDER BY b.event_date ASC, b.event_time ASC";

$calendarResult = mysqli_query($conn, $calendarSql);

$upcomingSql = "SELECT event_type, event_date, event_status
                FROM bookings
                WHERE event_status IN ('draft','confirmed','ongoing')
                AND event_date >= CURDATE()
                ORDER BY event_date ASC
                LIMIT 5";

$upcomingResult = mysqli_query($conn, $upcomingSql);

$upcomingCountSql = "SELECT COUNT(*) AS total_upcoming
                     FROM bookings
                     WHERE event_status IN ('draft','confirmed','ongoing')
                     AND event_date >= CURDATE()";

$upcomingCountResult = mysqli_query($conn, $upcomingCountSql);
$upcomingCountData = mysqli_fetch_assoc($upcomingCountResult);
$totalUpcoming = $upcomingCountData['total_upcoming'] ?? 0;

$nextEventSql = "SELECT event_type, event_date
                 FROM bookings
                 WHERE event_status IN ('draft','confirmed','ongoing')
                 AND event_date >= CURDATE()
                 ORDER BY event_date ASC
                 LIMIT 1";

$nextEventResult = mysqli_query($conn, $nextEventSql);
$nextEvent = mysqli_fetch_assoc($nextEventResult);

$eventsByDate = [];
// REVENUE THIS MONTH
$revenueMonthSql = "SELECT COALESCE(SUM(
                        CASE 
                            WHEN event_status = 'draft' THEN 0
                            ELSE total_price
                        END
                    ), 0) AS revenue_this_month
                    FROM bookings
                    WHERE booking_status = 'Approved'
                    AND MONTH(event_date) = MONTH(CURDATE())
                    AND YEAR(event_date) = YEAR(CURDATE())";

$revenueMonthResult = mysqli_query($conn, $revenueMonthSql);
$revenueMonthData = mysqli_fetch_assoc($revenueMonthResult);
$revenueThisMonth = (float)($revenueMonthData['revenue_this_month'] ?? 0);

// REVENUE LAST MONTH
$revenueLastMonthSql = "SELECT COALESCE(SUM(
                            CASE 
                                WHEN event_status = 'draft' THEN 0
                                ELSE total_price
                            END
                        ), 0) AS revenue_last_month
                        FROM bookings
                        WHERE booking_status = 'Approved'
                        AND MONTH(event_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
                        AND YEAR(event_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";

$revenueLastMonthResult = mysqli_query($conn, $revenueLastMonthSql);
$revenueLastMonthData = mysqli_fetch_assoc($revenueLastMonthResult);
$revenueLastMonth = (float)($revenueLastMonthData['revenue_last_month'] ?? 0);

// PERCENT CHANGE FOR REVENUE
if ($revenueLastMonth > 0) {
    $revenuePercentChange = (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100;
} else {
    $revenuePercentChange = ($revenueThisMonth > 0) ? 100 : 0;
}

$revenueTrendText = ($revenuePercentChange >= 0 ? '+' : '') . round($revenuePercentChange) . "% from last month";

if ($calendarResult && mysqli_num_rows($calendarResult) > 0) {
    while ($row = mysqli_fetch_assoc($calendarResult)) {
        $dateKey = $row['event_date'];

        if (!isset($eventsByDate[$dateKey])) {
            $eventsByDate[$dateKey] = [];
        }

        $eventsByDate[$dateKey][] = [
            'name'  => $row['full_name'],
            'event' => $row['event_type'] ?? 'Event',
            'time'  => (!empty($row['event_time']) && $row['event_time'] !== '00:00:00')
                        ? date("g:i A", strtotime($row['event_time']))
                        : 'N/A',
            'venue' => $row['venue'] ?? 'N/A'
        ];
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="dashboard.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
 
</head>
<body class="<?php echo ($theme === 'Dark Mode') ? 'dark-mode' : ''; ?>">
    <div class="ADMIN-DASHBOARD">
        <?php include('sidebar.php'); ?>

        <div class="main-content">
            <div class="header-titles">
                <h1 class="page-title">Dashboard</h1>
                <p class="sub-title">Welcome back! Here's what's happening with your events today.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">Total Orders Today</div>
                    <div class="stat-value"><?php echo $totalToday; ?></div>
                    <div class="stat-trend"><?php echo $trendText; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Upcoming Events</div>
                    <div class="stat-value"><?php echo $totalUpcoming; ?></div>
                    <div class="stat-trend">
                    <?php 
                    if ($nextEvent) {
                        echo "Next: " . $nextEvent['event_type'] . " (" . date("M d", strtotime($nextEvent['event_date'])) . ")";
                    } else {
                        echo "No upcoming events";
                    }
                    ?>
                </div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Revenue This Month</div>
                    <div class="stat-value">₱<?php echo number_format($revenueThisMonth, 2); ?></div>
                    <div class="stat-trend"><?php echo $revenueTrendText; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Pending Approvals</div>
                    <div class="stat-value"><?php echo $totalPending; ?></div>
                    <div class="stat-trend">Requires Action</div>
                </div>
            </div>

            <div class="middle-layout">
                <div class="card calendar-card">
                    <div class="calendar-header-ui">
                        <h2 class="card-title">Event Calendar</h2>
                        <div class="calendar-controls">
                            <select id="monthSelect" onchange="renderCalendar()"></select>
                            <select id="yearSelect" onchange="renderCalendar()"></select>
                        </div>
                    </div>
                    <div class="calendar-ui">
                        <div class="calendar-grid-header">
                            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
                        </div>
                        <div id="calendarDays" class="calendar-days"></div>
                    </div>
                </div>

                <div class="card upcoming-card">
                    <h2 class="card-title">Upcoming Events</h2>
                    <div class="event-list">
<?php
if ($upcomingResult && mysqli_num_rows($upcomingResult) > 0) {
    while ($e = mysqli_fetch_assoc($upcomingResult)) {

        $eventName = $e['event_type'];
        $eventDate = date("M d, Y", strtotime($e['event_date']));
        $status = strtolower($e['event_status']);
?>
    <div class="event-item">
        <div class="event-meta">
            <span class="event-name"><?php echo $eventName; ?></span>
            <span class="event-date"><?php echo $eventDate; ?></span>
        </div>

        <div class="event-badge <?php echo $status; ?>">
            <?php echo ucfirst($status); ?>
        </div>
    </div>

<?php
    }
} else {
    echo "<p style='text-align:center;'>No upcoming events.</p>";
}
?>
</div>
                </div>
            </div>

            <div class="card recent-orders-card">
                <div class="card-header-flex">
                <h2 class="card-title">Recent Orders</h2>
                <a href="orders.php" class="btn-view-all" style="text-decoration: none; display: inline-block; text-align: center;">View All</a>
            </div>
                <div class="table-responsive">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th><th>Customer</th><th>Event Type</th><th>Date</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (mysqli_num_rows($recentResult) > 0) {
                                while ($row = mysqli_fetch_assoc($recentResult)) {
                            ?>
                                <tr>
                                    <td>ORD-<?php echo $row['id']; ?></td>
                                    <td><?php echo $row['full_name']; ?></td>
                                    <td><?php echo $row['event_type']; ?></td>
                                    <td><?php echo date("m/d/Y", strtotime($row['event_date'])); ?></td>
                                    <td>
                                        <span class="status-tag pending">
                                            <?php echo strtolower($row['booking_status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align:center;'>No pending orders.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="eventModal" class="modal-overlay">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 id="modalTitle">Event Details</h3>
                    <span class="close-modal" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="modalDateText" style="font-weight: 700; margin-bottom: 10px;"></p>
                    <p id="modalDescription" style="font-size: 14px; line-height: 1.5;"></p>
                    <button class="modal-btn" onclick="closeModal()">Close Details</button>
                </div>
            </div>
        </div>

    <script>
    const dbEvents = <?php echo json_encode($eventsByDate); ?>;

    const monthSelect = document.getElementById('monthSelect');
    const yearSelect = document.getElementById('yearSelect');
    const calendarDays = document.getElementById('calendarDays');
    const months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    function initSelectors() {
        const currentYear = new Date().getFullYear();

        monthSelect.innerHTML = '';
        yearSelect.innerHTML = '';

        for (let i = 0; i < 12; i++) {
            let opt = document.createElement('option');
            opt.value = i;
            opt.textContent = months[i];
            if (i === new Date().getMonth()) opt.selected = true;
            monthSelect.appendChild(opt);
        }

        for (let i = currentYear - 5; i <= currentYear + 50; i++) {
            let opt = document.createElement('option');
            opt.value = i;
            opt.textContent = i;
            if (i === currentYear) opt.selected = true;
            yearSelect.appendChild(opt);
        }
    }

    function renderCalendar() {
        calendarDays.innerHTML = '';

        const month = parseInt(monthSelect.value, 10);
        const year = parseInt(yearSelect.value, 10);

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        for (let i = 0; i < firstDay; i++) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'cal-day empty';
            calendarDays.appendChild(emptyDiv);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const div = document.createElement('div');
            div.className = 'cal-day';
            div.innerHTML = `<span>${day}</span>`;

            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = dbEvents[dateStr] || [];

            if (dayEvents.length > 0) {
                div.classList.add('has-event');

                let dotsHtml = '<div class="dots">';
                const dotCount = Math.min(dayEvents.length, 2);

                for (let i = 0; i < dotCount; i++) {
                    dotsHtml += '<span class="dot red"></span>';
                }

                dotsHtml += '</div>';
                div.innerHTML += dotsHtml;
            }

            div.addEventListener('click', function () {
                showEventModal(dateStr);
            });

            calendarDays.appendChild(div);
        }
    }

    function showEventModal(dateStr) {
        const modal = document.getElementById('eventModal');
        const dateText = document.getElementById('modalDateText');
        const descText = document.getElementById('modalDescription');
        const title = document.getElementById('modalTitle');

        const events = dbEvents[dateStr] || [];

        const selectedDate = new Date(dateStr + 'T00:00:00');
        const formattedDate = selectedDate.toLocaleDateString('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });

        dateText.innerText = formattedDate;

        if (events.length > 0) {
            title.innerText = events.length > 1 ? 'Multiple Events' : 'Event Details';

            let html = '';
            events.forEach(ev => {
                html += `
                    <div style="margin-bottom:12px;">
                        <strong>${ev.event}</strong><br>
                        Client: ${ev.name}<br>
                        Time: ${ev.time}<br>
                        Venue: ${ev.venue}
                    </div>
                `;
            });

            descText.innerHTML = html;
        } else {
            title.innerText = 'No Events';
            descText.innerHTML = 'No approved events scheduled for this date.';
        }

        modal.classList.add('active');
        modal.style.display = 'flex';
    }

    function closeModal() {
        const modal = document.getElementById('eventModal');
        modal.classList.remove('active');
        modal.style.display = 'none';
    }

    // close pag click sa labas
    document.getElementById('eventModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });

    // close pag ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    initSelectors();
    renderCalendar();
</script>
</body>
</html>