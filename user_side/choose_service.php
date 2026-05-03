<?php
session_name('USERSESSID');
session_start();
require_once 'user_auth.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_service = $_POST['service'];
    $_SESSION['service_type'] = $selected_service;
    
    if ($selected_service == 'Catering Only') {
        $_SESSION['package'] = "N/A (Catering Only)";
        header("Location: choose_menu.php");
    } 
    elseif ($selected_service == 'Styling & Decoration Only') {
        $_SESSION['package'] = "N/A (Styling & Decoration Only)";
        $_SESSION['menu_cart'] = json_encode([]); // Empty cart
        $_SESSION['total_pax'] = 0;
        $_SESSION['total_cost'] = 0;
        
        header("Location: choose_package.php"); 
    } 
    else {
        header("Location: choose_package.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo Events | Choose Service</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="choose.css?v=<?php echo time(); ?>" />
</head>
<body>
    <div class="main-wrapper">
        <div class="nav-container">
            <div class="back-home" onclick="location.href='index.php'">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Home</span>
            </div>
        </div>

        <div class="header-section">
            <h1 class="p">Book Your Event with Magarbo Events</h1>
            <p class="text-wrapper-2">Complete the form below to book your perfect event</p>
        </div>

        <div class="stepper">
            <div class="step active">1</div>
            <div class="step">2</div>
            <div class="step">3</div>
            <div class="step">4</div>
        </div>

        <div class="glass-card">
            <div class="info-banner">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <div class="info-text">
                    <strong>Choose Your Service Type</strong>
                    <span>Select the type of service you need for your event. This will customize the booking flow accordingly.</span>
                </div>
            </div>

            <p class="question">What service do you need?</p>

            <form action="" method="POST" id="flowForm">
                <label class="service-option">
                    <input type="radio" name="service" value="Catering Only" required>
                    <span class="custom-radio"></span>
                    <div class="option-content">
                        <strong>Catering Only</strong>
                        <span>Food service only, no styling or decoration packages</span>
                    </div>
                </label>

                <label class="service-option">
                    <input type="radio" name="service" value="Catering with Styling">
                    <span class="custom-radio"></span>
                    <div class="option-content">
                        <strong>Catering with Styling</strong>
                        <span>Complete service with food and decoration packages</span>
                    </div>
                </label>

                <label class="service-option">
                    <input type="radio" name="service" value="Styling & Decoration Only">
                    <span class="custom-radio"></span>
                    <div class="option-content">
                        <strong>Styling & Decoration Only</strong>
                        <span>Event styling and decoration package without food service</span>
                    </div>
                </label>

                <div class="button-footer">
                    <button type="button" class="btn-prev" onclick="location.href='index.php'"><i class="fa-solid fa-arrow-left"></i> Previous</button>
                    <button type="submit" class="btn-next">Next</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>