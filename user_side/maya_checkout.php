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

if (strtolower($payment['payment_method']) !== 'maya') {
    die("Invalid payment method for this page.");
}

$booking_reference = $payment['booking_reference'] ?? ($_SESSION['booking_ref'] ?? 'N/A');
$amount = (float)($payment['amount'] ?? 2000);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay with Maya | Magarbo Events</title>
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
            max-width: 700px;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 28px;
        }

        .back-link {
            text-decoration: none;
            color: #333;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 18px;
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

        .content-box {
            border: 1px solid #ececec;
            border-radius: 14px;
            padding: 22px;
        }

        .content-box h2 {
            font-size: 18px;
            margin-bottom: 12px;
        }

        .content-box p {
            font-size: 14px;
            color: #666;
            line-height: 1.7;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 18px;
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
        }

        .btn-primary {
            background: #00c3a5;
            color: #fff;
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
            background: #eefaf7;
            border: 1px solid #bfeadf;
            color: #0f766e;
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

            .brand h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="payment-card">
            <a href="payment.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Payment Method</a>

            <div class="brand">
                <img src="https://cdn.brandfetch.io/id_IE4goUp/theme/dark/logo.svg?c=1bxid64Mup7aczewSAYMX&t=1765437091118" alt="Maya">
                <div>
                    <h1>Continue with Maya</h1>
                    <p>Complete your down payment securely using Maya.</p>
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

            <div class="content-box">
                <h2>Maya Checkout</h2>
                <p>
                    This page is ready for Maya integration. In the live version, this will redirect the customer to the official Maya-hosted checkout page, where they can complete payment securely.
                </p>

                <div class="btn-group">
                    <a href="#" class="btn btn-primary" onclick="alert('WALA PA'); return false;">
                        <i class="fa-solid fa-credit-card"></i> Continue to Maya
                    </a>

                    <a href="payment.php" class="btn btn-secondary">
                        <i class="fa-solid fa-rotate-left"></i> Change Payment Method
                    </a>
                </div>

                <div class="note">
                    Placeholder pa ito habang walra pang live Maya API keys tsaka checkout integration.
                </div>

                <div class="dev-box">
                    <p><strong>Dev note:</strong> SIMULAT MUNA TAYO</p>

                    <form action="payment_process.php" method="get" style="margin-top:12px;">
                        <input type="hidden" name="pid" value="<?php echo (int)$payment_id; ?>">
                        <input type="hidden" name="method" value="maya">
                        <button type="submit" class="btn btn-success">
                            <i class="fa-solid fa-circle-check"></i> Simulate Paid Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>