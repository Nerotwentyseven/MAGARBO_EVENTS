<?php
session_name('USERSESSID');
session_start();
require_once 'user_auth.php';

if (!isset($_SESSION['temp_booking_data']) || !is_array($_SESSION['temp_booking_data'])) {
    header("Location: index.php");
    exit();
}

// minimum downpayment
$downpayment = 2000;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payment Method | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="payment.css?v=<?php echo time(); ?>" />
</head>
<body>
    <div class="payment-wrapper">
        <div class="payment-card">
            <div class="header-section">
                <a href="process.php" class="back-btn">
                    <i class="fa-solid fa-arrow-left"></i> Payment Method
                </a>
            </div>

            <div class="content-body">
                <p class="section-title">Choose Payment Method</p>

                <div class="methods-container">
                    <div class="method-item" onclick="selectMethod('gcash', this)">
                        <div class="method-info">
                            <div class="icon-box">
                                <img 
                                    src="https://upload.wikimedia.org/wikipedia/commons/5/52/GCash_logo.svg" 
                                    alt="GCash"
                                    class="payment-logo"
                                    onerror="this.onerror=null;this.src='https://via.placeholder.com/80x80?text=GCash';"
                                >
                            </div>
                            <div class="method-text">
                                <span class="name">GCash</span>
                                <span class="status">Fast and secure wallet payment</span>
                            </div>
                        </div>
                        <div class="scan-badge" id="gcashBadge">Recommended</div>
                    </div>

                    <div class="method-item" onclick="selectMethod('maya', this)">
                        <div class="method-info">
                            <div class="icon-box">
                                <img 
                                    src="https://cdn.brandfetch.io/id_IE4goUp/theme/dark/logo.svg?c=1bxid64Mup7aczewSAYMX&t=1765437091118" 
                                    alt="Maya"
                                    onerror="this.onerror=null;this.src='https://via.placeholder.com/80x80?text=Maya';"
                                >
                            </div>
                            <div class="method-text">
                                <span class="name">Maya</span>
                                <span class="status">Convenient checkout for online payments</span>
                            </div>
                        </div>
                        <div class="scan-badge">Available</div>
                    </div>
                </div>

                <div class="summary-box">
                    <div class="summary-row highlight amount-row">
                        <span>Down Payment Amount</span>
                        <span class="price-label">Minimum ₱2,000.00</span>
                    </div>

                    <div class="amount-input-wrap">
                        <span class="peso-sign">₱</span>
                        <input 
                            type="number" 
                            id="downpaymentInput" 
                            min="2000" 
                            step="100" 
                            value="<?php echo number_format($downpayment, 0, '', ''); ?>"
                        >
                    </div>

                    <hr class="divider">

                    <p class="note">
                        Minimum down payment is ₱2,000.00. You may pay more if you want.
                        The remaining balance will be settled on the event day or based on your final billing.
                    </p>
                </div>

                <div class="policy-box">
                    <div class="policy-header">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span>Payment & Refund Policy</span>
                    </div>
                    <p>
                        The minimum down payment is ₱2,000.00. Client-initiated cancellation is non-refundable.
                        If the booking is rejected by the admin, the paid down payment will be refunded.
                    </p>
                </div>

                <button id="continueBtn" class="btn-continue" disabled onclick="handleContinue()">
                    Continue
                </button>
            </div>
        </div>
    </div>

    <div id="customAlert" class="custom-alert">
        <div class="alert-box">
            <div class="alert-icon">
                <i class="fa-solid fa-circle-exclamation"></i>
            </div>
            <h3 id="alertTitle">Notice</h3>
            <p id="alertMessage">Message here</p>
            <button onclick="closeAlert()">OK</button>
        </div>
    </div>

    <script>
        let selectedMethod = "";

        function showAlert(title, message) {
    document.getElementById('alertTitle').innerText = title;
    document.getElementById('alertMessage').innerText = message;
    document.getElementById('customAlert').style.display = "flex";
}

function closeAlert() {
    document.getElementById('customAlert').style.display = "none";
}

        function selectMethod(method, el) {
            selectedMethod = method;

            document.querySelectorAll('.method-item').forEach(item => {
                item.classList.remove('selected');
            });

            el.classList.add('selected');

            const btn = document.getElementById('continueBtn');
            btn.disabled = false;
            btn.classList.add('active');

            if (method === 'gcash') {
                btn.innerText = "Continue with GCash";
            } else if (method === 'maya') {
                btn.innerText = "Continue with Maya";
            } else {
                btn.innerText = "Continue";
            }
        }

        function handleContinue() {
            if (!selectedMethod) {
                alert("Please select a payment method.");
                return;
            }

            const amountInput = document.getElementById('downpaymentInput');
            let amount = parseFloat(amountInput.value || 0);

            if (isNaN(amount) || amount < 2000) {
                showAlert("Invalid Amount", "Minimum down payment is ₱2,000.00");
                amountInput.focus();
                return;
            }

            

            window.location.href =
                "create_payment.php?method=" + encodeURIComponent(selectedMethod) +
                "&amount=" + encodeURIComponent(amount.toFixed(2));
        }
    </script>
</body>
</html>