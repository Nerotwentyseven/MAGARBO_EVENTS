<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

$payment_id = (int)($_GET['pid'] ?? 0);

if ($payment_id <= 0) {
    die("Invalid payment reference.");
}

$stmt = mysqli_prepare($conn, "
    SELECT id, payment_status, payment_method
    FROM booking_payments
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Database error: " . mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "i", $payment_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$payment = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$payment) {
    die("Payment record not found.");
}

$status = trim($payment['payment_status'] ?? '');
$method = strtolower(trim($payment['payment_method'] ?? ''));

if ($status === 'Pending' || $status === 'Paid') {
    header("Location: payment_process.php?pid={$payment_id}&method={$method}");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #222;
        }
        .page-wrap {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 24px 16px;
        }
        .status-card {
            width: 100%;
            max-width: 460px;
            background: #fff;
            border-radius: 22px;
            border: 1px solid #ececec;
            box-shadow: 0 12px 30px rgba(0,0,0,.08);
            overflow: hidden;
            text-align: center;
        }
        .card-header {
            padding: 22px 22px 18px;
            border-bottom: 1px solid #f0f0f0;
        }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 20px;
            color: #111;
        }
        .brand i { color: #bf9225; }
        .card-body { padding: 28px 24px 26px; }
        .icon-box {
            width: 86px;
            height: 86px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff8eb;
            border: 1px solid #eee2c1;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-box i {
            font-size: 38px;
            color: #bf9225;
        }
        .title {
            margin: 0 0 10px;
            font-size: 30px;
            font-weight: 800;
            color: #111;
        }
        .message {
            margin: 0 auto 20px;
            max-width: 360px;
            font-size: 15px;
            line-height: 1.7;
            color: #666;
        }
        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            width: 100%;
            border: none;
            border-radius: 14px;
            text-decoration: none;
            font-size: 16px;
            font-weight: 800;
            padding: 15px 18px;
            background: #bf9225;
            color: #fff;
        }
        .btn:hover { background: #a87d15; }
    </style>
</head>
<body>
    <div class="page-wrap">
        <div class="status-card">
            <div class="card-header">
                <div class="brand">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Magarbo Events</span>
                </div>
            </div>

            <div class="card-body">
                <div class="icon-box">
                    <i class="fa-solid fa-hourglass-half"></i>
                </div>

                <h1 class="title">Processing Payment</h1>
                <p class="message">
                    Your payment is being verified. Please wait while we complete your booking.
                </p>

                <a href="payment.php" class="btn">
                    <i class="fa-solid fa-wallet"></i>
                    Back to Payment
                </a>
            </div>
        </div>
    </div>
</body>
</html>