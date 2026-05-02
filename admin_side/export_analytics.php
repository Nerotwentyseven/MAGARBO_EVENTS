<?php
session_name('ADMINSESSID');
session_start();

require_once 'admin_auth.php';
require_once '../db_connection.php';

$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$validStatuses = "'confirmed','ongoing','completed'";

$filename = "magarbo_reports_analytics_" . $selectedYear . ".csv";

header("Content-Type: text/csv; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

$output = fopen("php://output", "w");

// Excel UTF-8 fix
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

fputcsv($output, ["Magarbo Events - Reports & Analytics"]);
fputcsv($output, ["Year", $selectedYear]);
fputcsv($output, []);

fputcsv($output, ["Booking ID", "Client Name", "Event Type", "Package", "Event Date", "Booking Status", "Event Status", "Total Price"]);

$sql = "
    SELECT 
        b.id,
        b.event_type,
        b.package_name,
        b.event_date,
        b.booking_status,
        b.event_status,
        b.total_price,
        u.firstname,
        u.lastname
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    WHERE b.booking_status = 'Approved'
      AND LOWER(b.event_status) IN ($validStatuses)
      AND YEAR(b.event_date) = ?
    ORDER BY b.event_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $selectedYear);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$totalRevenue = 0;
$totalBookings = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $clientName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
    if ($clientName === '') {
        $clientName = 'Guest / Unknown';
    }

    $totalRevenue += (float)$row['total_price'];
    $totalBookings++;

    fputcsv($output, [
        $row['id'],
        $clientName,
        $row['event_type'],
        $row['package_name'],
        $row['event_date'],
        $row['booking_status'],
        $row['event_status'],
        number_format((float)$row['total_price'], 2, '.', '')
    ]);
}

fputcsv($output, []);
fputcsv($output, ["Summary"]);
fputcsv($output, ["Total Revenue", number_format($totalRevenue, 2, '.', '')]);
fputcsv($output, ["Total Bookings", $totalBookings]);
fputcsv($output, ["Average Order Value", $totalBookings > 0 ? number_format($totalRevenue / $totalBookings, 2, '.', '') : "0.00"]);

fclose($output);
exit();