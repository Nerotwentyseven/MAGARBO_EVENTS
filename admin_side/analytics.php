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

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$validStatuses = "'confirmed','ongoing','completed'";

$baseAnalyticsWhere = "
    booking_status = 'Approved'
    AND event_status IN ($validStatuses)
    AND total_price > 0
    AND event_date IS NOT NULL
    AND YEAR(event_date) = $selectedYear
";

// GET AVAILABLE YEARS
$yearOptions = [];
$yearSql = "
    SELECT DISTINCT YEAR(event_date) AS yr
    FROM bookings
    WHERE booking_status = 'Approved'
        AND event_status IN ($validStatuses)
        AND total_price > 0
        AND user_id IS NOT NULL
        AND event_date IS NOT NULL
    ORDER BY yr DESC
";
$yearRes = mysqli_query($conn, $yearSql);

if ($yearRes) {
    while ($yrRow = mysqli_fetch_assoc($yearRes)) {
        if (!empty($yrRow['yr'])) {
            $yearOptions[] = (int)$yrRow['yr'];
        }
    }
}

if (empty($yearOptions)) {
    $yearOptions[] = (int)date('Y');
}

if (!in_array($selectedYear, $yearOptions, true)) {
    $selectedYear = $yearOptions[0];
}

// 1) TOTAL REVENUE / TOTAL BOOKINGS / AVG ORDER VALUE
$summarySql = "
    SELECT 
        COUNT(*) AS total_bookings,
        COALESCE(SUM(total_price), 0) AS total_revenue
    FROM bookings
    WHERE $baseAnalyticsWhere
";
$summaryRes = mysqli_query($conn, $summarySql);
$summary = mysqli_fetch_assoc($summaryRes);

$totalBookings = (int)($summary['total_bookings'] ?? 0);
$totalRevenue = (float)($summary['total_revenue'] ?? 0);
$avgOrderValue = $totalBookings > 0 ? ($totalRevenue / $totalBookings) : 0;

// 2) MONTHLY REVENUE
$monthlyRevenue = array_fill(0, 12, 0);
$monthlyRevenueSql = "
    SELECT 
        MONTH(event_date) AS month_num,
        COALESCE(SUM(total_price), 0) AS total
    FROM bookings
    WHERE $baseAnalyticsWhere
    GROUP BY MONTH(event_date)
    ORDER BY MONTH(event_date)
";
$monthlyRevenueRes = mysqli_query($conn, $monthlyRevenueSql);

if ($monthlyRevenueRes) {
    while ($row = mysqli_fetch_assoc($monthlyRevenueRes)) {
        $monthIndex = (int)$row['month_num'] - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyRevenue[$monthIndex] = (float)$row['total'];
        }
    }
}

// 3) MONTHLY EVENT VOLUME
$monthlyVolume = array_fill(0, 12, 0);
$monthlyVolumeSql = "
    SELECT 
        MONTH(event_date) AS month_num,
        COUNT(*) AS total
    FROM bookings
    WHERE $baseAnalyticsWhere
    GROUP BY MONTH(event_date)
    ORDER BY MONTH(event_date)
";
$monthlyVolumeRes = mysqli_query($conn, $monthlyVolumeSql);

if ($monthlyVolumeRes) {
    while ($row = mysqli_fetch_assoc($monthlyVolumeRes)) {
        $monthIndex = (int)$row['month_num'] - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $monthlyVolume[$monthIndex] = (int)$row['total'];
        }
    }
}

// 4) EVENTS BY TYPE + EVENT TYPE PERFORMANCE
$typeLabels = [];
$typeCounts = [];
$typeRevenue = [];

$typeSql = "
    SELECT 
        COALESCE(NULLIF(event_type, ''), 'Other') AS event_type_name,
        COUNT(*) AS total_events,
        COALESCE(SUM(total_price), 0) AS total_revenue
    FROM bookings
    WHERE $baseAnalyticsWhere
    GROUP BY COALESCE(NULLIF(event_type, ''), 'Other')
    ORDER BY total_events DESC, event_type_name ASC
";
$typeRes = mysqli_query($conn, $typeSql);

if ($typeRes) {
    while ($row = mysqli_fetch_assoc($typeRes)) {
        $typeLabels[] = $row['event_type_name'];
        $typeCounts[] = (int)$row['total_events'];
        $typeRevenue[] = (float)$row['total_revenue'];
    }
}

if (empty($typeLabels)) {
    $typeLabels = ['No Data'];
    $typeCounts = [1];
    $typeRevenue = [0];
}

// 5) CUSTOMER ACQUISITION (NEW CUSTOMERS THIS YEAR BY MONTH)
// First-ever approved valid booking month for each user
$newCustomersMonthly = array_fill(0, 12, 0);

$newCustomerSql = "
    SELECT MONTH(first_event_date) AS month_num, COUNT(*) AS total_new
    FROM (
        SELECT user_id, MIN(event_date) AS first_event_date
        FROM bookings
        WHERE booking_status = 'Approved'
            AND event_status IN ($validStatuses)
            AND total_price > 0
            AND YEAR(event_date) = $selectedYear
            AND user_id IS NOT NULL
        GROUP BY user_id
    ) first_bookings
    WHERE YEAR(first_event_date) = $selectedYear
    GROUP BY MONTH(first_event_date)
    ORDER BY MONTH(first_event_date)
";
$newCustomerRes = mysqli_query($conn, $newCustomerSql);

if ($newCustomerRes) {
    while ($row = mysqli_fetch_assoc($newCustomerRes)) {
        $monthIndex = (int)$row['month_num'] - 1;
        if ($monthIndex >= 0 && $monthIndex < 12) {
            $newCustomersMonthly[$monthIndex] = (int)$row['total_new'];
        }
    }
}

// 6) CUSTOMER TYPES (NEW VS REPEAT in selected year)
$customerTypesSql = "
    SELECT
        SUM(CASE WHEN yearly.total_bookings = 1 THEN 1 ELSE 0 END) AS new_customers,
        SUM(CASE WHEN yearly.total_bookings > 1 THEN 1 ELSE 0 END) AS repeat_customers
    FROM (
        SELECT user_id, COUNT(*) AS total_bookings
        FROM bookings
        WHERE booking_status = 'Approved'
          AND event_status IN ($validStatuses)
          AND YEAR(event_date) = $selectedYear
          AND user_id IS NOT NULL
        GROUP BY user_id
    ) yearly
";
$customerTypesRes = mysqli_query($conn, $customerTypesSql);
$customerTypeRow = mysqli_fetch_assoc($customerTypesRes);

$newCustomersCount = (int)($customerTypeRow['new_customers'] ?? 0);
$repeatCustomersCount = (int)($customerTypeRow['repeat_customers'] ?? 0);

if (($newCustomersCount + $repeatCustomersCount) === 0) {
    $newCustomersCount = 1;
    $repeatCustomersCount = 0;
}

$monthLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo Events - Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="analytics.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="ADMIN-REPORTS">
    <?php include('sidebar.php'); ?>

    <main class="frame">
        <header class="header-content">
            <div>
                <h1>Reports & Analytics</h1>
                <p>Visual insights for Magarbo Events performance</p>
            </div>

            <div class="reports-actions">
    <form method="GET" id="yearForm">
        <select name="year" class="year-select" onchange="this.form.submit()">
            <?php foreach ($yearOptions as $yr): ?>
                <option value="<?php echo $yr; ?>" <?php echo $selectedYear === $yr ? 'selected' : ''; ?>>
                    Year <?php echo $yr; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <button type="button" class="export-btn" onclick="openExportConfirmModal()">
        <i class="fas fa-file-export"></i> Export Report
    </button>
</div>
        </header>

        <div class="stats-grid">

            <div class="stat-box">
                <span class="label">Total Revenue</span>
                <h2 class="value">₱<?php echo number_format($totalRevenue, 2); ?></h2>
                <span class="stat-note">All confirmed event earnings</span>
            </div>

            <div class="stat-box">
                <span class="label">Total Bookings</span>
                <h2 class="value"><?php echo number_format($totalBookings); ?></h2>
                <span class="stat-note">Approved & active events this year</span>
            </div>

            <div class="stat-box">
                <span class="label">Avg. Order Value</span>
                <h2 class="value">₱<?php echo number_format($avgOrderValue, 2); ?></h2>
                <span class="stat-note">Average spending per booking</span>
            </div>

        </div>

        <div class="chart-row">
            <div class="chart-card">
                <h3>Revenue Over Time</h3>
                <div class="chart-container"><canvas id="revLineChart"></canvas></div>
            </div>

            <div class="chart-card">
                <h3>Events by Type</h3>
                <div class="chart-container"><canvas id="typeDoughnutChart"></canvas></div>
            </div>
        </div>

        <div class="chart-row">
            <div class="chart-card">
                <h3>Monthly Event Volume</h3>
                <div class="chart-container"><canvas id="volumeBarChart"></canvas></div>
            </div>

            <div class="chart-card">
                <h3>Event Type Performance</h3>
                <div class="chart-container"><canvas id="perfHorizontalChart"></canvas></div>
            </div>
        </div>

        <div class="chart-row" style="margin-bottom: 30px;">
            <div class="chart-card">
                <h3>New Customer Acquisition</h3>
                <div class="chart-container"><canvas id="customerLineChart"></canvas></div>
            </div>

            <div class="chart-card">
                <h3>Customer Types</h3>
                <div class="chart-container"><canvas id="customerPieChart"></canvas></div>
            </div>
        </div>
    </main>
</div>

<div id="exportConfirmModal" class="confirm-overlay" style="display:none;">
    <div class="confirm-card">
        <div class="confirm-icon-wrap">
            <i class="fas fa-file-export"></i>
        </div>

        <h3>Export Report?</h3>
        <p>This will download the Reports & Analytics data for Year <?php echo $selectedYear; ?>.</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeExportConfirmModal()">Cancel</button>
            <button type="button" class="confirm-btn approve" onclick="confirmExportReport()">Export</button>
        </div>
    </div>
</div>

<script>
const isDark = document.body.classList.contains('dark-mode');
const textColor = isDark ? '#ffffff' : '#2d3436';
const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.05)';

Chart.defaults.color = textColor;
Chart.defaults.font.family = 'Inter, sans-serif';

const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            labels: {
                color: textColor,
                boxWidth: 12
            }
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            grid: { color: gridColor },
            ticks: { color: textColor }
        },
        x: {
            grid: { display: false },
            ticks: { color: textColor }
        }
    }
};

const monthLabels = <?php echo json_encode($monthLabels); ?>;
const monthlyRevenue = <?php echo json_encode($monthlyRevenue); ?>;
const monthlyVolume = <?php echo json_encode($monthlyVolume); ?>;
const typeLabels = <?php echo json_encode($typeLabels); ?>;
const typeCounts = <?php echo json_encode($typeCounts); ?>;
const typeRevenue = <?php echo json_encode($typeRevenue); ?>;
const newCustomersMonthly = <?php echo json_encode($newCustomersMonthly); ?>;
const customerTypeData = <?php echo json_encode([$newCustomersCount, $repeatCustomersCount]); ?>;

// 1. Revenue Line
new Chart(document.getElementById('revLineChart'), {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Revenue',
            data: monthlyRevenue,
            borderColor: '#bf9225',
            backgroundColor: 'rgba(191,146,37,0.1)',
            fill: true,
            tension: 0.35
        }]
    },
    options: commonOptions
});

// 2. Events by Type
new Chart(document.getElementById('typeDoughnutChart'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeCounts,
            backgroundColor: ['#bf9225', '#444', '#888', '#bbb', '#d6b35c', '#6b7280', '#a3a3a3'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: textColor,
                    boxWidth: 12
                }
            }
        }
    }
});

// 3. Monthly Event Volume
new Chart(document.getElementById('volumeBarChart'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Events',
            data: monthlyVolume,
            backgroundColor: '#bf9225'
        }]
    },
    options: commonOptions
});

// 4. Event Type Performance
new Chart(document.getElementById('perfHorizontalChart'), {
    type: 'bar',
    data: {
        labels: typeLabels,
        datasets: [{
            label: 'Revenue',
            data: typeRevenue,
            backgroundColor: '#bf9225'
        }]
    },
    options: {
        ...commonOptions,
        indexAxis: 'y'
    }
});

// 5. New Customer Acquisition
new Chart(document.getElementById('customerLineChart'), {
    type: 'line',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'New Customers',
            data: newCustomersMonthly,
            borderColor: '#bf9225',
            backgroundColor: 'rgba(191,146,37,0.1)',
            tension: 0.3,
            fill: true
        }]
    },
    options: commonOptions
});

// 6. Customer Types
new Chart(document.getElementById('customerPieChart'), {
    type: 'pie',
    data: {
        labels: ['New', 'Repeat'],
        datasets: [{
            data: customerTypeData,
            backgroundColor: ['#bf9225', '#333'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: textColor,
                    boxWidth: 12
                }
            }
        }
    }
});

function openExportConfirmModal() {
    document.getElementById('exportConfirmModal').style.display = 'flex';
}

function closeExportConfirmModal() {
    document.getElementById('exportConfirmModal').style.display = 'none';
}

function confirmExportReport() {
    // close modal muna
    document.getElementById('exportConfirmModal').style.display = 'none';

    // delay konti para smooth UI
    setTimeout(function() {
        window.location.href = 'export_analytics.php?year=<?php echo $selectedYear; ?>';
    }, 150);
}
</script>

</body>
</html>