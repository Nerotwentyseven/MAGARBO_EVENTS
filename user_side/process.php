<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 1. KUNIN ANG DATA MULA SA POST (Galing booking.php)
$user_id    = $_SESSION['user_id'];
$event_type = $_POST['event_type'] ?? 'N/A';
$religion = $_POST['religion'] ?? null;
$event_date = $_POST['event_date'] ?? 'N/A';
$event_time = $_POST['event_time'] ?? 'N/A';
$venue      = $_POST['venue'] ?? 'N/A';
$request    = $_POST['request'] ?? '';
$selected_theme_id = !empty($_POST['selected_theme_id']) ? (int) $_POST['selected_theme_id'] : null;
$selected_theme = trim($_POST['selected_theme'] ?? '');

// fallback: kung walang selected_theme pero may request na may "theme"
if ($selected_theme === '' && !empty($request)) {
    if (preg_match('/"([^"]+)"\s*theme/i', $request, $matches)) {
        $selected_theme = trim($matches[1]);
    }
}


// 2. KUNIN ANG DATA MULA SA SESSION (Galing sa mga naunang steps)
// Siguraduhin na ang mga ito ay may default values para iwas "Undefined variable"
$service_type = $_SESSION['service_type'] ?? 'N/A';
$package      = $_SESSION['package'] ?? 'N/A';
$total_cost   = $_SESSION['total_cost'] ?? 0;
$total_pax    = $_SESSION['total_pax'] ?? 0;
$menu_json    = $_SESSION['menu_cart'] ?? '[]';
$cart_data    = json_decode($menu_json, true);

// 3. DEFINE VARIABLES PARA SA DISPLAY (Ito yung mga nag-error sa iyo)
$formatted_time = ($event_time !== 'N/A') ? date("g:i A", strtotime($event_time)) : 'N/A';
$is_styling_only = ($service_type === 'Styling & Decoration Only');

// 4. SAVE SA DATABASE
// 4. HUWAG MUNA MAG-INSERT SA DATABASE. 
// I-save muna lahat ng details sa Session para bitbit sila hanggang Payment Success.
$_SESSION['temp_booking_data'] = [
    'user_id'           => $user_id,
    'event_type'        => $event_type,
    'service_type'      => $service_type,
    'package_name'      => $package,
    'menu_selection'    => $menu_json,
    'event_date'        => $event_date,
    'event_time'        => $event_time,
    'venue'             => $venue,
    'total_price'       => $total_cost,
    'request'           => $request,
    'selected_theme_id' => $selected_theme_id,
    'selected_theme'    => $selected_theme,
    'religion'          => $religion,
    'suggestion_text' => $request,
    
];

// Alisin mo rin yung $_SESSION['last_booking_id'] dito dahil wala pa tayong ID.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Magarbo Events | Review & Payment</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="process.css?v=<?php echo time(); ?>" />
</head>
<body>
    <div class="REVIEW-AND-PAYMENT">
        <div class="header-nav">
            <div class="back-home" onclick="location.href='index.php'">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Home</span>
            </div>
        </div>

        <div class="title-section">
            <h1 class="p">Book Your Event with Magarbo Events</h1>
            <p class="text-wrapper-2">Complete the form below to book your perfect event</p>
        </div>

        <div class="stepper">
            <div class="step active">1</div>
            <div class="step active">2</div>
            <div class="step active">3</div>
            <div class="step active">4</div>
        </div>

        <div class="main-card">
            <div class="admin-approval-banner">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Booking Awaits Admin Approval</span>
            </div>

            <p class="after-submitting">
                After submitting, your booking will be reviewed by our admin. Once approved, you'll receive confirmation via email and SMS.
            </p>

            <div class="review-section">
                <div class="section-title">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Review & Payment</span>
                </div>

                <div class="summary-container">
                    <h2 class="summary-title">Booking Summary</h2>
                    
                    <div class="summary-grid">
                        <div class="summary-item">
                            <span class="label">Event Type:</span>
                            <span class="value"><?php echo $event_type; ?></span>
                        </div>
                        
                        <?php if($event_type === 'Wedding'): ?>
                        <div class="summary-item">
                            <span class="label">Religion:</span>
                            <span class="value"><?php echo $religion; ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="summary-item">
                            <span class="label">Package:</span>
                            <span class="value"><?php echo $package; ?></span>
                        </div>

                        <div class="summary-item">
                            <span class="label">Service Type:</span>
                            <span class="value"><?php echo $service_type; ?></span>
                        </div>

                        <div class="summary-item">
                            <span class="label">Date & Time:</span>
                            <span class="value"><?php echo $event_date . ' at ' . $formatted_time; ?></span>
                        </div>

                        <div class="summary-item">
                            <span class="label">Venue:</span>
                            <span class="value">
<?php
$rawVenue = trim((string)$venue);

if ($rawVenue === '' || $rawVenue === 'N/A') {
    echo 'N/A';
} else {
    $parts = array_values(array_filter(array_map('trim', explode(',', $rawVenue)), 'strlen'));

    $cityIndex = -1;
    $provinceIndex = -1;

    foreach ($parts as $i => $part) {
        $p = strtolower($part);

        if (
            $p === 'albay' ||
            $p === 'camarines sur' ||
            $p === 'camarines norte' ||
            $p === 'sorsogon' ||
            $p === 'catanduanes' ||
            $p === 'masbate' ||
            str_contains($p, 'province')
        ) {
            $provinceIndex = $i;
            break;
        }
    }

    foreach ($parts as $i => $part) {
        $p = strtolower($part);

        if (
            str_contains($p, 'city of') ||
            str_contains($p, ' city') ||
            str_contains($p, 'municipality')
        ) {
            $cityIndex = $i;
            break;
        }
    }

    $splitIndex = -1;

    if ($cityIndex > 0) {
        $splitIndex = $cityIndex - 1;
    } elseif ($provinceIndex > 1) {
        $splitIndex = $provinceIndex - 2;
    }

    if ($splitIndex <= 0 && count($parts) >= 3) {
        $splitIndex = count($parts) - 3;
    }

    if ($splitIndex <= 0) {
        $splitIndex = 1;
    }

    $firstLine = implode(', ', array_slice($parts, 0, $splitIndex));
    $secondLine = implode(', ', array_slice($parts, $splitIndex));

    echo htmlspecialchars($firstLine);

    if ($secondLine !== '') {
        echo '<br>' . htmlspecialchars($secondLine);
    }
}
?>
</span>
                        </div>

                        <?php if (!empty($selected_theme)): ?>
                        <div class="summary-item">
                            <span class="label">Selected Theme:</span>
                            <span class="value"><?php echo htmlspecialchars($selected_theme); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="summary-item full-width">
                            <span class="label">Selected Menu:</span>
                            <div class="request-box" style="background: #fdfdfd; border: 1px solid #eee;">
                                <?php if(!$is_styling_only && !empty($cart_data)): ?>
                                    <ul style="list-style: none; padding: 0; margin: 0;">
                                        <?php foreach($cart_data as $item): ?>
                                            <li style="display: flex; justify-content: space-between; padding: 5px 0; border-bottom: 1px solid #f5f5f5;">
                                                <span><strong><?php echo $item['name']; ?></strong> (x<?php echo $item['qty']; ?>)</span>
                                                <span>₱<?php echo number_format($item['qty'] * $item['price'], 2); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif($is_styling_only): ?>
                                    <span style="color: #666; font-style: italic;">Styling & Decoration Only (No food menu selected).</span>
                                <?php else: ?>
                                    <span style="color: #999;">No menu selected.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="summary-item full-width">
                            <span class="label">Customer Request:</span>
                            <div class="request-box"><?php echo nl2br(htmlspecialchars($request)); ?></div>
                        </div>
                    </div>

                    <div class="total-section">
                        <hr class="line">
                        <div class="total-row">
                            <span class="total-label">Total Pax:</span>
                            <span class="total-value"><?php echo ($is_styling_only) ? "N/A" : $total_pax . " Persons"; ?></span>
                        </div>
                        <div class="total-row">
                            <span class="total-label">Estimated Total:</span>
                            <span class="total-value" style="color: #B28926; font-size: 22px;">₱<?php echo number_format($total_cost, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-footer">
                <button type="button" class="btn-prev" onclick="history.back()">Previous</button>
                <button type="button" class="btn-next" onclick="location.href='payment.php'">Proceed to Payment</button>
            </div>
        </div>
    </div>
</body>
</html>