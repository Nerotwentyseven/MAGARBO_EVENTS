<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

$payment_id = (int)($_GET['pid'] ?? 0);

if ($payment_id > 0) {
    $upd = mysqli_prepare($conn, "
        UPDATE booking_payments
        SET payment_status = 'Cancelled',
            note = CONCAT(IFNULL(note, ''), ' | Customer cancelled PayMongo checkout.')
        WHERE id = ? AND payment_status = 'Pending'
    ");

    if ($upd) {
        mysqli_stmt_bind_param($upd, "i", $payment_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

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
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.08);
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

        .brand i {
            color: #bf9225;
        }

        .card-body {
            padding: 28px 24px 26px;
        }

        .icon-box {
            width: 86px;
            height: 86px;
            margin: 0 auto 18px;
            border-radius: 50%;
            background: #fff2f2;
            border: 1px solid #f2cccc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon-box i {
            font-size: 38px;
            color: #d93025;
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

        .notice-box {
            background: #fff8eb;
            border: 1px solid #eee2c1;
            border-radius: 16px;
            padding: 14px 16px;
            margin: 0 0 22px;
            text-align: left;
        }

        .notice-box .notice-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 800;
            color: #8a670d;
        }

        .notice-box p {
            margin: 0;
            font-size: 14px;
            line-height: 1.6;
            color: #6a6a6a;
        }

        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
            transition: 0.2s ease;
        }

        .btn-primary {
            background: #bf9225;
            color: #fff;
        }

        .btn-primary:hover {
            background: #a87d15;
        }

        .btn-secondary {
            background: #f3f3f3;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e9e9e9;
        }

        @media (max-width: 480px) {
            .status-card {
                border-radius: 18px;
            }

            .card-header {
                padding: 18px 16px 16px;
            }

            .card-body {
                padding: 24px 16px 22px;
            }

            .title {
                font-size: 25px;
            }

            .message {
                font-size: 14px;
            }
        }
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
                    <i class="fa-solid fa-circle-xmark"></i>
                </div>

                <h1 class="title">Payment Cancelled</h1>

                <p class="message">
                    Your payment was not completed. No booking has been finalized yet.
                </p>

                <div class="notice-box">
                    <div class="notice-title">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>What happens next?</span>
                    </div>
                    <p>
                        You can return to the payment page and choose GCash or Maya again to continue your booking.
                    </p>
                </div>

                <div class="btn-group">
                    <a href="payment.php" class="btn btn-primary">
                        <i class="fa-solid fa-wallet"></i>
                        Back to Payment
                    </a>

                    <a href="index.php" class="btn btn-secondary">
                        <i class="fa-solid fa-house"></i>
                        Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>