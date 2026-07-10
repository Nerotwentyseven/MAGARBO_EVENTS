<?php
session_name('USERSESSID');
session_start();

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$alert = $_SESSION['alert'] ?? null;
unset($_SESSION['alert']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magarbo | Login & Register</title>
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <nav class="top-bar">
        <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
    </nav>

    <main class="login-wrapper">
        <div class="login-container">
            <header class="page-header">
                <h1>Magarbo Events</h1>
                <p id="header-desc">Welcome back! Please enter your details.</p>
            </header>

            <div class="toggle-container">
                <button type="button" class="toggle-btn active" id="login-toggle" onclick="toggleView('login')">Sign In</button>
                <button type="button" class="toggle-btn" id="register-toggle" onclick="toggleView('register')">Create Account</button>
            </div>

            <div class="login-card">
                <h2 class="card-title" id="card-title">
                    <i class="fa-solid fa-right-to-bracket" id="title-icon"></i> <span id="title-text">Sign In</span>
                </h2>

                <form id="auth-form" action="process_auth.php" method="POST">
                    <input type="hidden" name="action_type" id="action_type" value="login">

                    <div id="reg-name-row" class="form-row" style="display: none;">
                        <div class="form-group">
                            <label>First Name</label>
                            <input type="text" name="firstname" placeholder="First name">
                        </div>
                        <div class="form-group">
                            <label>Last Name</label>
                            <input type="text" name="lastname" placeholder="Last name">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Email Address</label>
                        <div class="input-wrapper">
                            <i class="fa-regular fa-envelope input-icon"></i>
                            <input type="email" name="email" placeholder="Enter your email" required>
                        </div>
                    </div>

                    

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="password" id="password" placeholder="Enter password" required>
                            <i class="fa-regular fa-eye show-pass-icon" onclick="togglePass('password')"></i>
                        </div>
                    </div>

                    <div class="form-group" id="reg-confirm" style="display: none;">
                        <label>Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm password">
                            <i class="fa-regular fa-eye show-pass-icon" onclick="togglePass('confirm_password')"></i>
                        </div>
                    </div>

                    <div class="form-actions" id="login-actions">
                        <label class="remember-me">
                            <input type="checkbox" name="remember_me"> Remember me
                        </label>
                        <a href="forgot_password.php" class="forgot-pass">Forgot password?</a>
                    </div>

                    <div class="form-actions" id="reg-terms" style="display: none;">
                        <label class="remember-me">
                            <input type="checkbox" name="terms_agreed" id="terms_agreed"> 
                            I agree to the 
                            <a href="terms.php" style="color: #6366f1; text-decoration: underline;">Terms of Service</a> 
                            and 
                            <a href="privacy.php"  style="color: #6366f1; text-decoration: underline;">Privacy Policy</a>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary" id="submit-btn">Sign In</button>
                </form>

                <div class="divider"><span>Or continue with</span></div>
                <div class="social-buttons">

                    <!-- GOOGLE SIGN IN -->
                    <div id="g_id_onload"
                        data-client_id="494722959577-eqn360i1mh8ofe5bltle94qr884ksavv.apps.googleusercontent.com"
                        data-callback="handleGoogleResponse"
                        data-locale="en">
                    </div>

                    <div class="google-btn-wrapper">
                        <div id="googleSignInBtn"></div>
                    </div>

                </div>
                <p class="card-footer" id="footer-text">
                    Don't have an account? <a href="javascript:void(0)" onclick="toggleView('register')">Sign up</a>
                </p>
            </div>
        </div>
    </main>

    <?php if ($alert): ?>
    <div class="custom-alert-overlay show" id="customAlertOverlay" data-form="<?php echo htmlspecialchars($alert['form']); ?>">
        <div class="custom-alert-box <?php echo htmlspecialchars($alert['type']); ?>">
            <button class="custom-alert-close" id="closeAlertBtn" aria-label="Close alert">&times;</button>

            <div class="custom-alert-icon">
                <?php if ($alert['type'] === 'success'): ?>
                    ✓
                <?php else: ?>
                    !
                <?php endif; ?>
            </div>

            <h3 class="custom-alert-title"><?php echo htmlspecialchars($alert['title']); ?></h3>
            <p class="custom-alert-message"><?php echo htmlspecialchars($alert['message']); ?></p>

            <button class="custom-alert-btn" id="alertOkBtn">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://accounts.google.com/gsi/client?hl=en" async defer></script>

    <script>
        function toggleView(mode) {
            const isReg = mode === 'register';
            document.getElementById('login-toggle').classList.toggle('active', !isReg);
            document.getElementById('register-toggle').classList.toggle('active', isReg);
            document.getElementById('action_type').value = mode;
            
            document.getElementById('reg-name-row').style.display = isReg ? 'flex' : 'none';
            
            document.getElementById('reg-confirm').style.display = isReg ? 'block' : 'none';
            document.getElementById('reg-terms').style.display = isReg ? 'flex' : 'none';
            document.getElementById('login-actions').style.display = isReg ? 'none' : 'flex';

            document.getElementById('title-text').innerText = isReg ? 'Sign Up' : 'Sign In';
            document.getElementById('title-icon').className = isReg ? 'fa-solid fa-user-plus' : 'fa-solid fa-right-to-bracket';
            document.getElementById('submit-btn').innerText = isReg ? 'Create Account' : 'Sign In';
            document.getElementById('header-desc').innerText = isReg ? "Create an account or sign in" : "Welcome back! Please enter your details.";
            document.getElementById('footer-text').innerHTML = isReg ? 
                'Already have an account? <a href="javascript:void(0)" onclick="toggleView(\'login\')">Sign In</a>' : 
                'Don\'t have an account? <a href="javascript:void(0)" onclick="toggleView(\'register\')">Sign up</a>';
        }

        function togglePass(id) {
            const x = document.getElementById(id);
            x.type = x.type === "password" ? "text" : "password";
        }



        function showCustomAlert(type, title, message, form = 'login') {
            const oldOverlay = document.getElementById('customAlertOverlay');
            if (oldOverlay) {
                oldOverlay.remove();
            }

            const icon = type === 'success' ? '✓' : '!';

            const overlay = document.createElement('div');
            overlay.className = 'custom-alert-overlay show';
            overlay.id = 'customAlertOverlay';
            overlay.setAttribute('data-form', form);

            overlay.innerHTML = `
                <div class="custom-alert-box ${type}">
                    <button class="custom-alert-close" id="closeAlertBtn" aria-label="Close alert">&times;</button>

                    <div class="custom-alert-icon">${icon}</div>

                    <h3 class="custom-alert-title">${title}</h3>
                    <p class="custom-alert-message">${message}</p>

                    <button class="custom-alert-btn" id="alertOkBtn">OK</button>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.classList.add('alert-open');

            function closeAlert() {
                overlay.classList.remove('show');
                document.body.classList.remove('alert-open');
            }

            const okBtn = overlay.querySelector('#alertOkBtn');
            const closeBtn = overlay.querySelector('#closeAlertBtn');

            if (okBtn) okBtn.addEventListener('click', closeAlert);
            if (closeBtn) closeBtn.addEventListener('click', closeAlert);

            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closeAlert();
                }
            });

            if (form === 'register') {
                toggleView('register');
            } else {
                toggleView('login');
            }
        }



        document.getElementById('auth-form').onsubmit = function(e) {
            const actionType = document.getElementById('action_type').value;

            if (actionType === 'register') {
                const pass = document.getElementById('password').value.trim();
                const confirmPass = document.getElementById('confirm_password').value.trim();
                const terms = document.getElementById('terms_agreed');

                const fname = document.querySelector('input[name="firstname"]').value.trim();
                const lname = document.querySelector('input[name="lastname"]').value.trim();
                

                if (!fname || !lname) {
                    e.preventDefault();
                    showCustomAlert('error', 'Incomplete Form', 'Please fill in your full name.', 'register');
                    return false;
                }

               

                if (pass !== confirmPass) {
                    e.preventDefault();
                    showCustomAlert('error', 'Password Mismatch', 'Passwords do not match.', 'register');
                    return false;
                }

                if (!terms.checked) {
                    e.preventDefault();
                    showCustomAlert('error', 'Terms Required', 'Please agree to the Terms of Service and Privacy Policy.', 'register');
                    return false;
                }
            }
        };

        
        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('customAlertOverlay');
            const okBtn = document.getElementById('alertOkBtn');
            const closeBtn = document.getElementById('closeAlertBtn');

            if (overlay) {
                document.body.classList.add('alert-open');

                function closeAlert() {
                    overlay.classList.remove('show');
                    document.body.classList.remove('alert-open');
                }

                if (okBtn) {
                    okBtn.addEventListener('click', closeAlert);
                }

                if (closeBtn) {
                    closeBtn.addEventListener('click', closeAlert);
                }

                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay) {
                        closeAlert();
                    }
                });
            }
        });


        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('customAlertOverlay');

            if (overlay) {
                const targetForm = overlay.getAttribute('data-form');

                if (targetForm === 'register') {
                    toggleView('register');
                } else {
                    toggleView('login');
                }
            }
        });

        function handleGoogleResponse(response) {
            fetch('google_callback.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'credential=' + encodeURIComponent(response.credential)
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    showCustomAlert('error', 'Google Sign-In Failed', data.message || 'Unable to sign in with Google.', 'login');
                }
            })
            .catch(() => {
                showCustomAlert('error', 'Server Error', 'Something went wrong while signing in with Google.', 'login');
            });
        }
        window.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash === '#register') {
        toggleView('register');
    }
});

function renderGoogleButton() {
    const googleBtn = document.getElementById('googleSignInBtn');
    if (!googleBtn || !window.google) return;

    googleBtn.innerHTML = '';

    const wrapperWidth = googleBtn.parentElement.offsetWidth;
    const buttonWidth = Math.min(wrapperWidth, 398);

    google.accounts.id.initialize({
        client_id: "494722959577-eqn360i1mh8ofe5bltle94qr884ksavv.apps.googleusercontent.com",
        callback: handleGoogleResponse
    });

    google.accounts.id.renderButton(googleBtn, {
        type: "standard",
        theme: "outline",
        size: "large",
        text: "continue_with",
        shape: "rectangular",
        logo_alignment: "left",
        width: buttonWidth,
        locale: "en"
    });
}

window.addEventListener('load', renderGoogleButton);
window.addEventListener('resize', function () {
    clearTimeout(window.googleResizeTimer);
    window.googleResizeTimer = setTimeout(renderGoogleButton, 200);
});


    </script>
</body>
</html>