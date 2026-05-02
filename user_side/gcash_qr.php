<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['temp_booking_data']) || !is_array($_SESSION['temp_booking_data'])) {
    header("Location: index.php");
    exit();
}

$payment_id = (int)($_GET['pid'] ?? 0);

if ($payment_id <= 0) {
    die("Invalid payment ID.");
}

$sql = "SELECT * FROM booking_payments WHERE id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$payment) {
    die("Payment record not found.");
}

if (strtolower($payment['payment_method']) !== 'gcash') {
    die("Invalid payment method for this page.");
}

$booking_reference = $payment['booking_reference'] ?? ($_SESSION['booking_ref'] ?? 'N/A');
$amount = (float)($payment['amount'] ?? 2000);

$isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GCash Payment | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f7f7f7;
            color: #222;
        }

        .page-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .payment-card {
            width: 100%;
            max-width: 760px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 28px;
        }

        .top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
        }

        .back-link {
            text-decoration: none;
            color: #333;
            font-weight: 600;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
        }

        .brand img {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }

        .brand h1 {
            font-size: 24px;
        }

        .brand p {
            color: #666;
            font-size: 14px;
            margin-top: 4px;
        }

        .summary-box {
            background: #f9fafb;
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 22px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding: 8px 0;
            font-size: 15px;
        }

        .summary-row strong {
            color: #111;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            align-items: start;
        }

        .panel {
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 20px;
            background: #fff;
        }

        .panel h2 {
            font-size: 18px;
            margin-bottom: 12px;
        }

        .panel p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .qr-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }

        .qr-box img {
            width: 240px;
            max-width: 100%;
            border: 1px solid #ddd;
            border-radius: 14px;
            background: #fff;
            padding: 12px;
        }

        .steps {
            margin-top: 12px;
            padding-left: 18px;
            color: #555;
            line-height: 1.8;
            font-size: 14px;
        }

        .mobile-box {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .btn-primary {
            background: #0057ff;
            color: #fff;
        }

        .btn-primary:hover {
            opacity: 0.92;
        }

        .btn-secondary {
            background: #f1f3f5;
            color: #222;
        }

        .btn-success {
            background: #16a34a;
            color: #fff;
        }

        .note {
            margin-top: 18px;
            background: #fff8e7;
            border: 1px solid #f0deb0;
            color: #7a5a00;
            padding: 14px;
            border-radius: 12px;
            font-size: 14px;
            line-height: 1.6;
        }

        .dev-box {
            margin-top: 18px;
            border: 1px dashed #cfcfcf;
            background: #fafafa;
            border-radius: 12px;
            padding: 14px;
        }

        .dev-box p {
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 768px) {
            .payment-card {
                padding: 20px;
            }

            .main-content {
                grid-template-columns: 1fr;
            }

            .brand h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="payment-card">
            <div class="top-bar">
                <a href="payment.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Payment Method</a>
            </div>

            <div class="brand">
                <img 
                    src="https://upload.wikimedia.org/wikipedia/commons/5/52/GCash_logo.svg" 
                    alt="GCash"
                    class="payment-logo"
                    onerror="this.onerror=null;this.src='https://via.placeholder.com/80x80?text=GCash';"
                >
                <div>
                    <h1>Pay with GCash</h1>
                    <p>Secure your booking by paying the required down payment.</p>
                </div>
            </div>

            <div class="summary-box">
                <div class="summary-row">
                    <span>Booking Reference</span>
                    <strong><?php echo htmlspecialchars($booking_reference); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Payment ID</span>
                    <strong>#<?php echo (int)$payment_id; ?></strong>
                </div>
                <div class="summary-row">
                    <span>Amount to Pay</span>
                    <strong>₱<?php echo number_format($amount, 2); ?></strong>
                </div>
                <div class="summary-row">
                    <span>Status</span>
                    <strong><?php echo htmlspecialchars($payment['payment_status'] ?? 'Pending'); ?></strong>
                </div>
            </div>

            <div class="main-content">
                <?php if ($isMobile): ?>
                    <div class="panel">
                        <h2>Mobile Payment</h2>
                        <div class="mobile-box">
                            <p>
                                Since you are using a phone, the preferred flow is to continue using the official GCash payment path once available in your live setup.
                            </p>

                            <a href="#" class="btn btn-primary" onclick="alert('Sa real integration, dito ka magre-redirect sa official GCash flow.'); return false;">
                                <i class="fa-solid fa-wallet"></i> Continue with GCash
                            </a>

                            <a href="payment.php" class="btn btn-secondary">
                                <i class="fa-solid fa-rotate-left"></i> Change Payment Method
                            </a>
                        </div>

                        <div class="note">
                            For now, placeholder pa ito. Sa real setup, dito manggagaling ang official mobile payment flow ni GCash.
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Payment Instructions</h2>
                        <ol class="steps">
                            <li>Tap the GCash button above.</li>
                            <li>Complete the payment in the official payment page/app.</li>
                            <li>Wait for payment confirmation.</li>
                            <li>Your booking will only be finalized after verified payment.</li>
                        </ol>

                        <div class="dev-box">
                            <p><strong>Dev note:</strong> habang wala pang live GCash integration, puwede mong gamitin muna ang manual testing flow.</p>

                            <form action="payment_process.php" method="get" style="margin-top:12px;">
                                <input type="hidden" name="pid" value="<?php echo (int)$payment_id; ?>">
                                <input type="hidden" name="method" value="gcash">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-circle-check"></i> Simulate Paid Payment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="panel">
                        <h2>Scan & Pay</h2>
                        <div class="qr-box">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?php echo urlencode('GCASH|MAGARBO|' . $booking_reference . '|AMOUNT|' . number_format($amount, 2, '.', '')); ?>" alt="GCash QR">
                            <p>Scan this QR using your GCash app to continue payment.</p>
                        </div>

                        <div class="note">
                            This QR is for UI/testing placeholder only.
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Instructions</h2>
                        <ol class="steps">
                            <li>Open your GCash app on your phone.</li>
                            <li>Use Scan QR.</li>
                            <li>Scan the QR code shown on this screen.</li>
                            <li>Complete payment and wait for confirmation.</li>
                        </ol>

                        <div class="dev-box">
                            <p><strong>Dev note:</strong> placeholder pa lang ini</p>

                            <form action="payment_process.php" method="get" style="margin-top:12px;">
                                <input type="hidden" name="pid" value="<?php echo (int)$payment_id; ?>">
                                <input type="hidden" name="method" value="gcash">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa-solid fa-circle-check"></i> Simulate Paid Payment
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>