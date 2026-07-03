<?php
session_name('ADMINSESSID');
session_start();
require_once 'admin_auth.php';
require_once '../db_connection.php';

function ensureAdminSettingsRow(mysqli $conn): void {
    $check = mysqli_query($conn, "SELECT id FROM admin_settings WHERE id = 1 LIMIT 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        $sql = "INSERT INTO admin_settings (
                    id, company_name, email, phone, website, address, description,
                    facebook, instagram, logo_path, theme,
                    notif_order, notif_msg, notif_appointment,
                    language, timezone
                ) VALUES (
                    1, 'Magarbo Events', 'info@magarboevents.com', '+63 985 414 8843',
                    'www.magarboevents.com', 'Barangay 8B Tigbi Tiwi Albay, Philippines',
                    'Premier event planning and catering services.',
                    'facebook.com/magarboevents', '@magarboevents', 'img/logo.png',
                    'Light Mode', 1, 1, 1, 'English (US)', 'Asia/Manila'
                )";
        mysqli_query($conn, $sql);
    }
}

function getAdminSettings(mysqli $conn): array {
    ensureAdminSettingsRow($conn);
    $res = mysqli_query($conn, "SELECT * FROM admin_settings WHERE id = 1 LIMIT 1");
    return $res ? (mysqli_fetch_assoc($res) ?: []) : [];
}

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

ensureAdminSettingsRow($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = getAdminSettings($conn);

    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');
    $language = 'English (US)';
    $theme = trim($_POST['theme'] ?? 'Light Mode');
    $timezone = 'Asia/Manila';

    $notif_order = isset($_POST['notif_order']) ? 1 : 0;
    $notif_msg = isset($_POST['notif_msg']) ? 1 : 0;
    $notif_appointment = isset($_POST['notif_appointment']) ? 1 : 0;

    $logo_path = $current['logo_path'] ?? 'img/logo.png';

    if (isset($_FILES['logo']) && (int)($_FILES['logo']['error'] ?? 4) === 0) {
        $target_dir = "img/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $originalName = basename($_FILES["logo"]["name"]);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($ext, $allowed, true)) {
            $file_name = time() . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $originalName);
            $target_file = $target_dir . $file_name;

            if (move_uploaded_file($_FILES["logo"]["tmp_name"], $target_file)) {
                $logo_path = $target_file;
            }
        }
    }

    $stmt = mysqli_prepare($conn, "
        UPDATE admin_settings SET
            company_name = ?,
            email = ?,
            phone = ?,
            website = ?,
            address = ?,
            description = ?,
            facebook = ?,
            instagram = ?,
            logo_path = ?,
            theme = ?,
            notif_order = ?,
            notif_msg = ?,
            notif_appointment = ?,
            language = ?,
            timezone = ?
        WHERE id = 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssssiiiss",
            $company_name,
            $email,
            $phone,
            $website,
            $address,
            $description,
            $facebook,
            $instagram,
            $logo_path,
            $theme,
            $notif_order,
            $notif_msg,
            $notif_appointment,
            $language,
            $timezone
        );
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $_SESSION['settings_saved'] = '1';
    header("Location: admin_settings.php");
    exit;
}

$s = getAdminSettings($conn);

date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | Magarbo Events</title>
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="admin_settings.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo (($s['theme'] ?? 'Light Mode') === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="ADMIN-LAYOUT">
    <aside class="sidebar-wrapper">
        <?php include('sidebar.php'); ?>
    </aside>

    <main class="main-content">
        <div class="header-container">
            <h1 class="text-title">Settings</h1>
            <p class="text-subtitle">Manage your business profile, security, and dashboard appearance.</p>
        </div>

        <?php if (!empty($_SESSION['settings_saved'])): ?>
            <div id="settingsToast" class="toast-success">
                <i class="fas fa-check-circle"></i>
                <span>All settings updated successfully!</span>
            </div>
            <?php unset($_SESSION['settings_saved']); ?>
        <?php endif; ?>

        <div class="tabs-nav">
            <div class="tabs-container">
                <button class="tab-link active" onclick="openTab(event, 'profile')">Business Profile</button>
                <button class="tab-link" onclick="openTab(event, 'notifications')">Notifications</button>
                <button class="tab-link" onclick="openTab(event, 'system')">System Preferences</button>
            </div>
        </div>

        <form action="" method="POST" enctype="multipart/form-data">
            <div class="settings-card">

                <div id="profile" class="tab-content active">
                    <div class="section-header"><i class="fa-solid fa-briefcase"></i> <span>Business Information</span></div>

                    <div class="profile-header-box">
                        <div class="image-preview-container">
                            <img id="preview-img" src="<?php echo e($s['logo_path'] ?? 'img/logo.png'); ?>" class="logo-preview" />
                            <label for="logo-upload" class="upload-badge"><i class="fa-solid fa-camera"></i></label>
                            <input type="file" name="logo" id="logo-upload" hidden onchange="previewImage(event)">
                        </div>
                        <div class="profile-title">
                            <h3><?php echo e($s['company_name'] ?? 'Magarbo Events'); ?></h3>
                            <p>Global Admin Settings</p>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group"><label>Company Name</label><input type="text" name="company_name" value="<?php echo e($s['company_name'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Official Email</label><input type="email" name="email" value="<?php echo e($s['email'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Contact Number</label><input type="text" name="phone" value="<?php echo e($s['phone'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Official Website</label><input type="text" name="website" value="<?php echo e($s['website'] ?? ''); ?>"></div>
                    </div>

                    <div class="form-group full-width"><label>Full Office Address</label><input type="text" name="address" value="<?php echo e($s['address'] ?? ''); ?>"></div>
                    <div class="form-group full-width"><label>Business Bio / Description</label><textarea name="description"><?php echo e($s['description'] ?? ''); ?></textarea></div>

                    <div class="social-links">
                        <p class="label-heading">Social Media Presence</p>
                        <div class="form-grid">
                            <div class="form-group"><label><i class="fa-brands fa-facebook"></i> Facebook URL</label><input type="text" name="facebook" value="<?php echo e($s['facebook'] ?? ''); ?>"></div>
                            <div class="form-group"><label><i class="fa-brands fa-instagram"></i> Instagram Username</label><input type="text" name="instagram" value="<?php echo e($s['instagram'] ?? ''); ?>"></div>
                        </div>
                    </div>
                </div>

                <div id="notifications" class="tab-content">
                    <div class="section-header"><i class="fa-solid fa-bell"></i> <span>Notification Channels</span></div>

                    <div class="notification-list">
                        <div class="notif-items">
                            <div class="notif-info">
                                <strong>Order Notifications</strong>
                                <p>Receive alerts whenever a new event booking is made.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notif_order" <?php echo !empty($s['notif_order']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="notif-items">
                            <div class="notif-info">
                                <strong>Message Alerts</strong>
                                <p>Get notified for incoming customer inquiries.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notif_msg" <?php echo !empty($s['notif_msg']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="notif-items">
                            <div class="notif-info">
                                <strong>Appointments Alert</strong>
                                <p>Receive alerts whenever a new appointment is made.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="notif_appointment" <?php echo !empty($s['notif_appointment']) ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="system" class="tab-content">
                    <div class="section-header"><i class="fa-solid fa-gears"></i> <span>Interface Settings</span></div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label>System Language</label>
                            <input type="text" value="English (US)" readonly>
                            <input type="hidden" name="language" value="English (US)">
                        </div>

                        <div class="form-group">
                            <label>Default Timezone</label>
                            <input type="text" value="Asia/Manila" readonly>
                            <input type="hidden" name="timezone" value="Asia/Manila">
                        </div>

                        <div class="form-group">
                            <label>Dashboard Theme</label>
                            <select name="theme" id="theme-selector" onchange="applyTheme()">
                                <option value="Light Mode" <?php echo (($s['theme'] ?? '') === 'Light Mode') ? 'selected' : ''; ?>>Light Mode</option>
                                <option value="Dark Mode" <?php echo (($s['theme'] ?? '') === 'Dark Mode') ? 'selected' : ''; ?>>Dark Mode</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="footer-actions">
                    <button type="submit" class="btn-primary-save">Update All Settings</button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
function openTab(evt, tabName) {
    localStorage.setItem('adminSettingsActiveTab', tabName);

    let i, content, links;
    content = document.getElementsByClassName("tab-content");
    for (i = 0; i < content.length; i++) content[i].classList.remove("active");

    links = document.getElementsByClassName("tab-link");
    for (i = 0; i < links.length; i++) links[i].classList.remove("active");

    document.getElementById(tabName).classList.add("active");

    if (evt && evt.currentTarget) {
        evt.currentTarget.classList.add("active");
    }
}

function previewImage(event) {
    const file = event.target.files[0];
    if (!file) return;

    let reader = new FileReader();
    reader.onload = function() {
        document.getElementById('preview-img').src = reader.result;
    };
    reader.readAsDataURL(file);
}

function applyTheme() {
    const val = document.getElementById('theme-selector').value;
    if (val === 'Dark Mode') document.body.classList.add('dark-mode');
    else document.body.classList.remove('dark-mode');
}

window.addEventListener('load', function () {
    const savedSettingsTab = localStorage.getItem('adminSettingsActiveTab') || 'profile';

    const savedSettingsBtn = document.querySelector(
        `.tab-link[onclick*="'${savedSettingsTab}'"]`
    );

    if (savedSettingsBtn && document.getElementById(savedSettingsTab)) {
        openTab({ currentTarget: savedSettingsBtn }, savedSettingsTab);
    }
});
</script>
</body>
</html>