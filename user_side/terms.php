<?php
session_name('USERSESSID');
session_start();

$pageTitle = "Terms and Conditions | Magarbo Events";

$backLink = 'index.php';
$backText = 'Back to Home';

if (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'login.php') !== false) {
    $backLink = 'login.php#register';
    $backText = 'Back to Register';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body {
            background: #f9f9f9;
            font-family: 'Roboto', sans-serif;
            color: #333;
        }

        .terms-page {
            max-width: 980px;
            margin: 110px auto 60px;
            padding: 0 20px;
        }

        .terms-card {
            background: #fff;
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #eee;
        }

        .terms-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .terms-header i {
            color: #bf9225;
            font-size: 34px;
            margin-bottom: 12px;
        }

        .terms-header h1 {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #222;
        }

        .terms-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .terms-section {
            margin-bottom: 28px;
        }

        .terms-section h2 {
            font-size: 18px;
            color: #bf9225;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .terms-section p,
        .terms-section li {
            font-size: 14.5px;
            color: #555;
            line-height: 1.7;
        }

        .terms-section ul {
            padding-left: 22px;
            margin-top: 8px;
        }

        .terms-section li {
            margin-bottom: 8px;
            list-style: disc;
        }

        .terms-note {
            background: #fff8ea;
            border: 1px solid #f0e2bd;
            border-radius: 14px;
            padding: 18px;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 25px;
        }

        .terms-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .terms-btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }

        .terms-btn.gold {
            background: #bf9225;
            color: #fff;
        }

        .terms-btn.gray {
            background: #e5e5e5;
            color: #333;
        }

        @media (max-width: 600px) {
            .terms-page {
                margin-top: 90px;
            }

            .terms-card {
                padding: 28px 20px;
            }

            .terms-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="terms-page">
    <div class="terms-card">
        <div class="terms-header">
            <i class="fa-solid fa-file-contract"></i>
            <h1>Terms and Conditions</h1>
            <p>
                Please read these Terms and Conditions carefully before using Magarbo Events’ booking services.
                By creating an account or submitting a booking request, you agree to the terms stated below.
            </p>
        </div>

        <div class="terms-section">
            <h2>1. Use of the Website</h2>
            <p>
                Magarbo Events provides an online platform where clients can view packages, menus, galleries,
                and submit event booking requests. Users must provide accurate and complete information when
                creating an account or booking an event.
            </p>
        </div>

        <div class="terms-section">
            <h2>2. Account Responsibility</h2>
            <p>
                Users are responsible for keeping their login credentials secure. Any activity made using the
                user’s account will be considered authorized by the account owner unless reported immediately.
            </p>
        </div>

        <div class="terms-section">
            <h2>3. Booking Requests</h2>
            <ul>
                <li>All submitted bookings are subject to review and approval by the Magarbo Events admin.</li>
                <li>A booking is not considered confirmed until it has been reviewed and approved.</li>
                <li>Clients must provide correct event details, including date, time, venue, service type, package, menu, and special requests.</li>
                <li>Magarbo Events may contact the client for clarification before approving a booking.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>4. Downpayment Policy</h2>
            <p>
                A minimum downpayment of ₱2,000 is required for booking processing and confirmation.
                The client may pay more than the minimum amount if preferred. Any paid amount will be reflected
                in the client’s payment record and remaining balance.
            </p>
        </div>

        <div class="terms-section">
            <h2>5. Pricing and Balance</h2>
            <ul>
                <li>Package and menu prices may vary depending on venue size, customization requests, guest count, and event requirements.</li>
                <li>The final price may be updated by the admin after reviewing the booking details.</li>
                <li>Any remaining balance must be settled based on the payment arrangement agreed upon with Magarbo Events.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>6. Cancellation and Refund Policy</h2>
            <p>
                Clients cannot directly cancel a booking with an automatic refund. If a booking needs to be declined
                or cancelled, it must be reviewed by the Magarbo Events admin first.
            </p>
            <ul>
                <li>Refunds are only processed when the admin declines or approves the refund request.</li>
                <li>Approved refunds may be returned through GCash or cash upon pick-up/arrangement.</li>
                <li>If a return/refund is approved, Magarbo Events may refund 75% of the paid amount, depending on the situation and preparation already made.</li>
                <li>No refund is guaranteed unless confirmed by the admin.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>7. Event Changes and Client Requests</h2>
            <p>
                Any changes to event details, venue, menu, package, or special instructions must be communicated
                as early as possible. Magarbo Events will try to accommodate changes, but approval may depend on
                availability, preparation status, and additional costs.
            </p>
        </div>

        <div class="terms-section">
            <h2>8. Client Responsibilities</h2>
            <ul>
                <li>The client must ensure that the venue is accessible and suitable for the requested service.</li>
                <li>The client must provide accurate contact information for coordination.</li>
                <li>The client must be available for confirmation messages, payment updates, and event coordination.</li>
            </ul>
        </div>

        <div class="terms-section">
            <h2>9. Privacy and Data Use</h2>
            <p>
                Magarbo Events collects user information such as name, contact number, email address, event details,
                booking history, and payment-related records for booking, communication, and service processing.
                Personal information will be handled responsibly and will not be sold to unauthorized third parties.
            </p>
        </div>

        <div class="terms-section">
            <h2>10. Website Updates</h2>
            <p>
                Magarbo Events may update these Terms and Conditions when necessary. Continued use of the website
                after updates means the user accepts the revised terms.
            </p>
        </div>

        <div class="terms-note">
            <strong>Note:</strong> These Terms and Conditions are created for Magarbo Events’ booking system and should
            be reviewed by the business owner before final use.
        </div>

        <div class="terms-actions">
            <a href="<?php echo $backLink; ?>" class="terms-btn gold">
                <?php echo $backText; ?>
            </a>
        </div>
    </div>
</div>

</body>
</html>