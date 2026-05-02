<?php
session_name('USERSESSID');
session_start();

$pageTitle = "Privacy Policy | Magarbo Events";

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

        .privacy-page {
            max-width: 980px;
            margin: 110px auto 60px;
            padding: 0 20px;
        }

        .privacy-card {
            background: #fff;
            border-radius: 18px;
            padding: 40px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.06);
            border: 1px solid #eee;
        }

        .privacy-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .privacy-header i {
            color: #bf9225;
            font-size: 34px;
            margin-bottom: 12px;
        }

        .privacy-header h1 {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #222;
        }

        .privacy-header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .privacy-section {
            margin-bottom: 28px;
        }

        .privacy-section h2 {
            font-size: 18px;
            color: #bf9225;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .privacy-section p,
        .privacy-section li {
            font-size: 14.5px;
            color: #555;
            line-height: 1.7;
        }

        .privacy-section ul {
            padding-left: 22px;
            margin-top: 8px;
        }

        .privacy-section li {
            margin-bottom: 8px;
        }

        .privacy-note {
            background: #fff8ea;
            border: 1px solid #f0e2bd;
            border-radius: 14px;
            padding: 18px;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin-top: 25px;
        }

        .privacy-actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 35px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
        }

        .btn-gold {
            background: #bf9225;
            color: #fff;
        }

        .btn-gray {
            background: #e5e5e5;
            color: #333;
        }

        @media (max-width: 600px) {
            .privacy-card {
                padding: 28px 20px;
            }

            .privacy-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<div class="privacy-page">
    <div class="privacy-card">

        <div class="privacy-header">
            <i class="fa-solid fa-shield-halved"></i>
            <h1>Privacy Policy</h1>
            <p>
                This Privacy Policy explains how Magarbo Events collects, uses, and protects your personal information.
            </p>
        </div>

        <div class="privacy-section">
            <h2>1. Information We Collect</h2>
            <ul>
                <li>Full name, email address, and contact number</li>
                <li>Event details (date, venue, package, menu selection)</li>
                <li>Booking history and transaction records</li>
                <li>Messages and communications within the system</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>2. How We Use Your Information</h2>
            <p>Your information is used to:</p>
            <ul>
                <li>Process and manage event bookings</li>
                <li>Communicate updates, approvals, and notifications</li>
                <li>Improve our services and user experience</li>
                <li>Maintain records for transactions and customer support</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>3. Data Protection</h2>
            <p>
                Magarbo Events takes appropriate measures to protect your personal data.
                Your information is stored securely and access is limited only to authorized personnel.
            </p>
        </div>

        <div class="privacy-section">
            <h2>4. Sharing of Information</h2>
            <p>
                We do not sell or share your personal data with third parties except when necessary
                for processing your booking or required by law.
            </p>
        </div>

        <div class="privacy-section">
            <h2>5. User Rights</h2>
            <ul>
                <li>You have the right to access your personal data</li>
                <li>You may request corrections or updates to your information</li>
                <li>You may request deletion of your account (subject to active bookings)</li>
            </ul>
        </div>

        <div class="privacy-section">
            <h2>6. Cookies and Tracking</h2>
            <p>
                The system may use basic cookies or session data to keep you logged in and improve system performance.
            </p>
        </div>

        <div class="privacy-section">
            <h2>7. Changes to Policy</h2>
            <p>
                This Privacy Policy may be updated from time to time. Continued use of the system means
                you accept any changes made.
            </p>
        </div>

        <div class="privacy-note">
            <strong>Note:</strong> This Privacy Policy is intended for Magarbo Events’ system and should be reviewed by the business owner for official use.
        </div>

        <div class="privacy-actions">
            <a href="<?php echo $backLink; ?>" class="btn btn-gold">
                <?php echo $backText; ?>
            </a>
        </div>

    </div>
</div>

</body>
</html>