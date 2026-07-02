    <?php
    session_name('USERSESSID');
    session_start();
    require_once 'user_auth.php';
    require_once '../db_connection.php';

    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE users SET last_active = NOW() WHERE id = $user_id");

    $user_query = "SELECT * FROM users WHERE id = '$user_id'";
    $user_result = mysqli_query($conn, $user_query);
    $user_data = mysqli_fetch_assoc($user_result);

    $user_name = $user_data['firstname'] . " " . $user_data['lastname'];
    $user_email = $user_data['email'];
    $reg_date = date("F d, Y", strtotime($user_data['created_at']));

    $authProvider = $user_data['auth_provider'] ?? 'local';
    $passwordSet = (int)($user_data['password_set'] ?? 1);
    $isGoogleOnly = ($authProvider === 'google' && $passwordSet === 0);

    if (!empty($user_data['display_user_id'])) {
    $displayUserId = $user_data['display_user_id'];
} else {
    $dateKey = !empty($user_data['created_at'])
        ? date('Ymd', strtotime($user_data['created_at']))
        : date('Ymd');

    $baseId = 'USR' . $dateKey;
    $displayUserId = $baseId;

    $sameDateUsers = [];
    $sameDateSql = "SELECT id, created_at
                    FROM users
                    WHERE DATE(created_at) = ?
                    ORDER BY created_at ASC, id ASC";
    $sameDateStmt = mysqli_prepare($conn, $sameDateSql);

    if ($sameDateStmt) {
        $dateOnly = !empty($user_data['created_at'])
            ? date('Y-m-d', strtotime($user_data['created_at']))
            : date('Y-m-d');

        mysqli_stmt_bind_param($sameDateStmt, "s", $dateOnly);
        mysqli_stmt_execute($sameDateStmt);
        $sameDateRes = mysqli_stmt_get_result($sameDateStmt);

        $count = 0;
        while ($u = mysqli_fetch_assoc($sameDateRes)) {
            $count++;

            if ((int)$u['id'] === (int)$user_data['id']) {
                $displayUserId = ($count === 1) ? $baseId : $baseId . '-' . $count;
                break;
            }
        }

        mysqli_stmt_close($sameDateStmt);
    }
}

    $query_bookings = "SELECT * FROM bookings WHERE user_id = ? ORDER BY id DESC";
    $stmt_b = mysqli_prepare($conn, $query_bookings);
    mysqli_stmt_bind_param($stmt_b, "i", $user_id);
    mysqli_stmt_execute($stmt_b);
    $result_bookings = mysqli_stmt_get_result($stmt_b);

    $upcoming_events = [];
    $completed_events = [];
    $total_bookings_count = 0;

    while ($row = mysqli_fetch_assoc($result_bookings)) {
        $total_bookings_count++;

        $bookingStatus = strtolower(trim($row['booking_status'] ?? ''));
        $eventStatus = strtolower(trim($row['event_status'] ?? ''));

        if ($bookingStatus === 'completed' || $eventStatus === 'completed') {
            $completed_events[] = $row;
        } else {
            $upcoming_events[] = $row;
        }
    }
    $display_booking = ($total_bookings_count > 1) ? $total_bookings_count . " events" : $total_bookings_count . " event";

    foreach ($upcoming_events as $event) {
        if (empty($event['event_date'])) {
            continue;
        }

        if (($event['booking_status'] ?? '') !== 'Approved') {
            continue;
        }

        $event_date_raw = date('Y-m-d', strtotime($event['event_date']));
        $event_date_formatted = date("F j, Y", strtotime($event['event_date']));
        $event_type = $event['event_type'] ?? 'Event';

        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $link = 'profile.php?view=upcoming';

        if ($event_date_raw === $today) {
            $type = 'event_reminder_today';
            $title = 'Event Reminder';
            $message = "Your {$event_type} is happening today ({$event_date_formatted}).";

            $check_sql = "SELECT id FROM notifications 
                        WHERE user_id = ? AND type = ? AND message = ?
                        LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($stmt_check, "iss", $user_id, $type, $message);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($check_result) == 0) {
                $insert_notif = "INSERT INTO notifications (user_id, type, title, message, link, is_read) 
                                VALUES (?, ?, ?, ?, ?, 0)";
                $stmt_notif = mysqli_prepare($conn, $insert_notif);
                mysqli_stmt_bind_param($stmt_notif, "issss", $user_id, $type, $title, $message, $link);
                mysqli_stmt_execute($stmt_notif);
            }
        }

        if ($event_date_raw === $tomorrow) {
            $type = 'event_reminder_tomorrow';
            $title = 'Event Reminder';
            $message = "Your {$event_type} is happening tomorrow ({$event_date_formatted}).";

            $check_sql = "SELECT id FROM notifications 
                        WHERE user_id = ? AND type = ? AND message = ?
                        LIMIT 1";
            $stmt_check = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($stmt_check, "iss", $user_id, $type, $message);
            mysqli_stmt_execute($stmt_check);
            $check_result = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($check_result) == 0) {
                $insert_notif = "INSERT INTO notifications (user_id, type, title, message, link, is_read) 
                                VALUES (?, ?, ?, ?, ?, 0)";
                $stmt_notif = mysqli_prepare($conn, $insert_notif);
                mysqli_stmt_bind_param($stmt_notif, "issss", $user_id, $type, $title, $message, $link);
                mysqli_stmt_execute($stmt_notif);
            }
        }
    }

    $query_apts = "SELECT * FROM appointments 
                WHERE user_id = ?
                ORDER BY 
                    CASE status
                        WHEN 'Approved' THEN 1
                        WHEN 'Pending' THEN 2
                        WHEN 'Cancelled' THEN 3
                        WHEN 'Completed' THEN 4
                        ELSE 5
                    END,
                    apt_date ASC";
    $stmt_a = mysqli_prepare($conn, $query_apts);
    mysqli_stmt_bind_param($stmt_a, "i", $user_id);
    mysqli_stmt_execute($stmt_a);
    $result_apts = mysqli_stmt_get_result($stmt_a);
    $my_appointments = mysqli_fetch_all($result_apts, MYSQLI_ASSOC);

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apt_date'])) {
    $purpose = $_POST['purpose'] ?? 'General Consultation';
    $date = $_POST['apt_date'];
    $time = $_POST['apt_time'] ?? '';
    $notes = $_POST['notes'] ?? '';

    $insert_apt = "INSERT INTO appointments 
    (user_id, purpose, apt_date, apt_time, notes, status) 
    VALUES (?, ?, ?, ?, ?, 'Pending')";
    $stmt_i = mysqli_prepare($conn, $insert_apt);
    mysqli_stmt_bind_param($stmt_i, "issss", $user_id, $purpose, $date, $time, $notes);

    if (mysqli_stmt_execute($stmt_i)) {
    $newAppointmentId = mysqli_insert_id($conn);

    $dateKey = !empty($date) ? date('Ymd', strtotime($date)) : date('Ymd');
    $baseId = 'APT' . $dateKey;

    $finalId = $baseId;
    $count = 1;

    while (true) {
        $check = mysqli_prepare($conn, "
            SELECT id 
            FROM appointments 
            WHERE display_appointment_id = ? 
            LIMIT 1
        ");

        if (!$check) {
            die("Prepare failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($check, "s", $finalId);
        mysqli_stmt_execute($check);
        $res = mysqli_stmt_get_result($check);

        if (mysqli_num_rows($res) === 0) {
            mysqli_stmt_close($check);
            break;
        }

        mysqli_stmt_close($check);

        $count++;
        $finalId = $baseId . '-' . $count;
    }

    $upd = mysqli_prepare($conn, "
        UPDATE appointments 
        SET display_appointment_id = ? 
        WHERE id = ?
    ");

    if (!$upd) {
        die("Update prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($upd, "si", $finalId, $newAppointmentId);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
}

    mysqli_stmt_close($stmt_i);

    header("Location: profile.php?view=appointments");
    exit();
}

    if (isset($_GET['cancel_apt'])) {
        $apt_id = (int) $_GET['cancel_apt'];
        $cancel_query = "UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending'";
        $stmt_c = mysqli_prepare($conn, $cancel_query);
        mysqli_stmt_bind_param($stmt_c, "ii", $apt_id, $user_id);
        mysqli_stmt_execute($stmt_c);
        header("Location: profile.php?view=appointments");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected_appointments'])) {
        $selected_ids = $_POST['appointment_ids'] ?? [];

        if (!empty($selected_ids) && is_array($selected_ids)) {
            $clean_ids = [];

            foreach ($selected_ids as $id) {
                $clean_ids[] = (int)$id;
            }

            $clean_ids = array_filter($clean_ids);

            if (!empty($clean_ids)) {
                $placeholders = implode(',', array_fill(0, count($clean_ids), '?'));
                $types = str_repeat('i', count($clean_ids) + 1);

                $sql_delete = "DELETE FROM appointments
                            WHERE user_id = ?
                            AND id IN ($placeholders)
                            AND (
                                    status = 'Cancelled'
                                    OR status = 'Completed'
                            )";

                $stmt_delete = mysqli_prepare($conn, $sql_delete);

                if ($stmt_delete) {
                    $params = array_merge([$user_id], $clean_ids);
                    mysqli_stmt_bind_param($stmt_delete, $types, ...$params);
                    mysqli_stmt_execute($stmt_delete);
                }
            }
        }

        header("Location: profile.php?view=appointments");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_completed_events'])) {
        $selected_ids = $_POST['completed_event_ids'] ?? [];

        if (!empty($selected_ids) && is_array($selected_ids)) {
            $clean_ids = [];

            foreach ($selected_ids as $id) {
                $clean_ids[] = (int) $id;
            }

            $clean_ids = array_filter($clean_ids);

            if (!empty($clean_ids)) {
                $placeholders = implode(',', array_fill(0, count($clean_ids), '?'));
                $types = str_repeat('i', count($clean_ids) + 1);

                $sql_delete = "DELETE FROM bookings 
                            WHERE user_id = ? 
                            AND id IN ($placeholders)
                            AND (
                                    LOWER(COALESCE(booking_status, '')) = 'completed'
                                    OR LOWER(COALESCE(event_status, '')) = 'completed'
                            )";

                $stmt_delete = mysqli_prepare($conn, $sql_delete);

                if ($stmt_delete) {
                    $params = array_merge([$user_id], $clean_ids);
                    mysqli_stmt_bind_param($stmt_delete, $types, ...$params);
                    mysqli_stmt_execute($stmt_delete);
                }
            }
        }

        header("Location: profile.php?view=completed");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])) {
        $message_text = trim($_POST['message_text'] ?? '');

        if ($message_text !== '') {
            $insert_msg = "INSERT INTO chat_messages (user_id, sender, message, is_read) VALUES (?, 'user', ?, 0)";
            $stmt_msg = mysqli_prepare($conn, $insert_msg);

            if (!$stmt_msg) {
                die("Prepare failed: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt_msg, "is", $user_id, $message_text);

            if (!mysqli_stmt_execute($stmt_msg)) {
                die("Execute failed: " . mysqli_stmt_error($stmt_msg));
            }
        }

        header("Location: profile.php?view=message");
        exit();
    }

    if ((isset($_GET['view']) && $_GET['view'] === 'message') || isset($_POST['send_msg'])) {
        $mark_admin_read = "UPDATE chat_messages 
                            SET is_read = 1 
                            WHERE user_id = ? AND sender = 'admin' AND is_read = 0";
        $stmt_mark_admin_read = mysqli_prepare($conn, $mark_admin_read);

        if ($stmt_mark_admin_read) {
            mysqli_stmt_bind_param($stmt_mark_admin_read, "i", $user_id);
            mysqli_stmt_execute($stmt_mark_admin_read);
        }
    }

    $chat_messages = [];
    $get_chat = "SELECT sender, message, created_at, is_read FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC, id ASC";
    $stmt_chat = mysqli_prepare($conn, $get_chat);

    if (!$stmt_chat) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt_chat, "i", $user_id);

    if (!mysqli_stmt_execute($stmt_chat)) {
        die("Execute failed: " . mysqli_stmt_error($stmt_chat));
    }

    $result_chat = mysqli_stmt_get_result($stmt_chat);

    while ($row = mysqli_fetch_assoc($result_chat)) {
        $chat_messages[] = $row;
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Magarbo Events - Profile</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="profile.css?v=<?php echo time(); ?>">

    </head>

    <body>

        <nav class="navbar">
            <div class="back-home" onclick="location.href='index.php'">
                <i class="fa-solid fa-arrow-left"></i>
                <span>Back to Home</span>
            </div>
            <div id="nav-title" style="color: var(--gold); font-weight: 500;"></div>
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </nav>

        <div class="container">
            <div id="welcome-header" class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</h1>
                <p>Manage your profile and view your event history</p>
            </div>

            <div class="action-cards">
                <a href="choose_service.php" class="card"><i class="fa-regular fa-calendar-check"
                        style="color:var(--gold)"></i> New Booking</a>
                <div class="card" onclick="switchSection('appointments')"><i class="fa-regular fa-clock"
                        style="color:var(--gold)"></i> Appointments</div>
                <div class="card" onclick="switchSection('message')"><i class="fa-regular fa-comment-dots"
                        style="color:var(--gold)"></i> Message</div>
            </div>

            <div class="tabs" id="tab-nav">
                <button class="tab-btn active" id="btn-profile" onclick="switchSection('profile')">Profile
                    Information</button>
                <button class="tab-btn" id="btn-upcoming" onclick="switchSection('upcoming')">Upcoming Events</button>
                <button class="tab-btn" id="btn-completed" onclick="switchSection('completed')">Completed</button>
            </div>

            <div class="content-box">
                <!-- PROFILE -->
                <div id="profile" class="section active">
                    <h3 style="font-size: 18px; margin-bottom: 20px;">Personal Information</h3>

                    <form action="update_profile.php" method="POST" class="profile-edit-form">

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-solid fa-id-card"></i></div>
                            <div class="info-content">
                                <label>User ID</label>
                                <span><?php echo htmlspecialchars($displayUserId); ?></span>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-regular fa-user"></i></div>
                            <div class="info-content">
                                <label>First Name</label>
                                <input type="text" name="firstname" value="<?php echo htmlspecialchars($user_data['firstname']); ?>" required>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-regular fa-user"></i></div>
                            <div class="info-content">
                                <label>Last Name</label>
                                <input type="text" name="lastname" value="<?php echo htmlspecialchars($user_data['lastname']); ?>" required>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-regular fa-envelope"></i></div>
                            <div class="info-content">
                                <label>Email</label>

                                <div style="display:flex; align-items:center; gap:8px;">
                                    <input type="email" 
                                        value="<?php echo htmlspecialchars($user_data['email']); ?>" 
                                        readonly 
                                        style="flex:1; background:#f5f5f5; cursor:not-allowed;">
                                    
                                    <i class="fa-solid fa-lock" style="color:#bbb; font-size:13px;"></i>
                                </div>

                                <small style="display:block; margin-top:6px; color:#888;">
                                    This email is linked to your account and cannot be changed.
                                </small>
                            </div>
                        </div>

                        

                        <?php
                        $currentPhone = trim($user_data['phone'] ?? '');
                        $hasPhone = $currentPhone !== '';
                        $phoneVerified = (int)($user_data['phone_verified'] ?? 0) === 1;

                        function maskPhone($phone) {
                            $digits = preg_replace('/\D/', '', $phone);
                            if (strlen($digits) !== 11) return $phone;
                            return substr($digits, 0, 4) . ' *** ' . substr($digits, -4);
                        }

                        $displayPhone = $hasPhone ? maskPhone($currentPhone) : '';
                        $phoneButtonText = $hasPhone ? 'Change' : 'Set';
                        ?>

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-solid fa-phone"></i></div>

                            <div class="info-content">
                                <label>Phone Number</label>

                                <div class="phone-group">
                                    <input type="text"
                                        value="<?php echo $hasPhone ? htmlspecialchars($displayPhone) : 'No mobile number added yet'; ?>"
                                        readonly
                                        style="background:#f5f5f5; cursor:not-allowed;">

                                    <a href="change_phone.php" class="btn-change-phone">
                                        <?php echo $phoneButtonText; ?>
                                    </a>
                                </div>

                                <small>
                                    <?php if ($hasPhone): ?>
                                        Status:
                                        <strong style="color: <?php echo $phoneVerified ? '#198754' : '#dc3545'; ?>;">
                                            <?php echo $phoneVerified ? 'Verified' : 'Unverified'; ?>
                                        </strong>
                                    <?php else: ?>
                                        Add your mobile number for account security and booking updates.
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>

                        <div class="info-row">
                            <div class="info-icon"><i class="fa-regular fa-calendar-days"></i></div>
                            <div class="info-content">
                                <label>Member Since</label>
                                <span><?php echo $reg_date; ?></span>
                            </div>
                        </div>

                        <div class="info-row" style="border:none;">
                            <div class="info-icon"><i class="fa-solid fa-box-archive"></i></div>
                            <div class="info-content">
                                <label>Total Booking</label>
                                <span><?php echo $display_booking; ?></span>
                            </div>
                        </div>

                        <div style="margin-top: 20px; display:flex; gap:12px; flex-wrap:wrap;">
                            <button type="submit" class="btn-schedule" style="border:none; cursor:pointer;">
                                Save Changes
                            </button>

                            <?php if ($isGoogleOnly): ?>
                                <a href="set_password.php" class="btn-schedule" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                                    Set Password
                                </a>
                            <?php else: ?>
                                <a href="change_password.php" class="btn-schedule" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">
                                    Change Password
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <div id="appointments" class="section">

                    <div class="appointments-header">
                        <div>
                            <h3>My Appointments</h3>
                            <p>Manage your consultation appointments.</p>
                        </div>

                        <button type="button" class="btn-schedule" onclick="openAptModal()">
                            <i class="fa-solid fa-plus"></i>
                            Schedule New
                        </button>
                    </div>

                    <form method="POST" id="appointmentsDeleteForm">
                        <div class="completed-action-bar" style="margin-bottom:16px;">
                            <label class="select-all">
                                <input type="checkbox" id="selectAllAppointments" onclick="toggleAllAppointments(this)">
                                Select Deletable
                            </label>

                            <button type="button" class="btn-delete-selected" onclick="confirmDeleteSelectedAppointments()">
                                <i class="fa-solid fa-trash"></i> Delete Selected
                            </button>
                        </div>

                        <div id="appointmentsContent">
                            <?php if (!empty($my_appointments)): ?>
                                <?php foreach ($my_appointments as $apt): ?>
                                    <?php
                                        $isDeletable = ($apt['status'] === 'Cancelled' || $apt['status'] === 'Completed');
                                    ?>
                                    <div class="card" style="margin-bottom: 15px; cursor: default; display: block; <?php echo ($apt['status'] == 'Cancelled') ? 'opacity: 0.7;' : ''; ?>">
                                        <div style="display:flex; align-items:flex-start; gap:12px; width:100%;">
                                            
                                            <?php if ($isDeletable): ?>
                                                <input type="checkbox"
                                                    class="appointment-delete-checkbox"
                                                    name="appointment_ids[]"
                                                    value="<?php echo (int)$apt['id']; ?>"
                                                    style="margin-top:4px; accent-color: var(--gold); flex-shrink:0;">
                                            <?php else: ?>
                                                <div style="width:16px; flex-shrink:0;"></div>
                                            <?php endif; ?>

                                            <div style="width: 100%;">
                                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                                    <strong style="font-size: 16px; color: var(--gold);"><?php echo htmlspecialchars($apt['purpose']); ?></strong>
                                                    
                                                    <div style="display: flex; align-items: center; gap: 10px;">
                                                        <span style="font-size: 12px; color: #888; font-weight: bold;"><?php echo strtoupper($apt['status']); ?></span>
                                                        
                                                        <?php if ($apt['status'] == 'Pending'): ?>
                                                            <a href="profile.php?cancel_apt=<?php echo $apt['id']; ?>" 
                                                            onclick="return openAppointmentActionModal(event, this.href, 'Cancel this appointment?', 'cancel')"
                                                            style="color: #666; font-size: 11px; text-decoration: none; border: 1px solid #ccc; padding: 2px 8px; border-radius: 4px;">
                                                                Cancel
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>

                                                <div style="margin-top: 10px; font-size: 14px; line-height: 1.6; color: #555;">
                                                    <p><b>Date:</b> <?php echo date("F j, Y", strtotime($apt['apt_date'])); ?></p>
                                                    <p><b>Time Slot:</b> <?php echo htmlspecialchars($apt['apt_time']); ?></p>
                                                    
                                                    <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #ddd;">
                                                        <p style="font-size: 13px; color: #666;">
                                                            <i class="fa-regular fa-note-sticky"></i> <b>Notes:</b><br>
                                                            <?php echo !empty($apt['notes']) ? nl2br(htmlspecialchars($apt['notes'])) : "No additional notes provided."; ?>
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#888; text-align: center; padding: 20px;">No appointments found.</p>
                            <?php endif; ?>
                        </div>

                        <input type="hidden" name="delete_selected_appointments" value="1">
                    </form>

                </div>
                <!-- MESSAGE -->
                <div id="message" class="section">
                    <div class="chat-container">
                        <div class="chat-header" style="justify-content: space-between;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <button type="button" id="messageBackBtn" onclick="closeMobileMessageView()"
                                    style="display:none; border:none; background:none; font-size:18px; cursor:pointer; color:#333;">
                                    <i class="fa-solid fa-arrow-left"></i>
                                </button>

                                <div
                                    style="width:35px; height:35px; background:var(--gold); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white;">
                                    <i class="fa-solid fa-user-tie"></i>
                                </div>

                                <span style="font-size: 14px; font-weight: 600;">Magarbo Events Team</span>
                            </div>
                        </div>

                        <div class="chat-box" id="chatBox">
                            <?php if (!empty($chat_messages)): ?>
                                <?php $last_index = count($chat_messages) - 1; ?>
                                <?php foreach ($chat_messages as $index => $msg): ?>
                                    <div class="bubble <?php echo ($msg['sender'] === 'user') ? 'sent' : 'received'; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                                        <div style="font-size:11px; margin-top:6px; opacity:0.8;">
                                            <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                                        </div>

                                        <?php if (!empty($chat_messages)): ?>
                                            <?php
                                                $last_user_index = -1;
                                                foreach ($chat_messages as $i => $row) {
                                                    if ($row['sender'] === 'user') {
                                                        $last_user_index = $i;
                                                    }
                                                }
                                            ?>

                                            <?php foreach ($chat_messages as $index => $msg): ?>
                                                <div class="bubble <?php echo ($msg['sender'] === 'user') ? 'sent' : 'received'; ?>">
                                                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                                                    <div style="font-size:11px; margin-top:6px; opacity:0.8;">
                                                        <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                                                    </div>

                                                    <?php if ($msg['sender'] === 'user' && $index === $last_user_index): ?>
                                                        <div style="font-size:11px; margin-top:4px; text-align:right; opacity:0.8;">
                                                            <?php echo ((int)$msg['is_read'] === 1) ? 'Seen' : 'Sent'; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div style="text-align:center; color:#888; font-size:14px; margin:auto;">
                                                No messages yet. Start chatting with Magarbo Events Team.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align:center; color:#888; font-size:14px; margin:auto;">
                                    No messages yet. Start chatting with Magarbo Events Team.
                                </div>
                            <?php endif; ?>
                        </div>

                        <form id="chatForm" action="profile.php" method="POST" class="chat-input"
                            onsubmit="saveMessageState()">
                            <input type="text" id="messageInput" name="message_text" placeholder="Type your message..."
                                required autocomplete="off" autocapitalize="sentences" autocorrect="on" enterkeyhint="send">
                            <button type="submit" name="send_msg" class="btn-send">
                                <i class="fa-solid fa-paper-plane"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div id="upcoming" class="section">
                    <h3>Upcoming Events</h3>

                    <div id="upcomingEventsContent">

                        <?php if (!empty($upcoming_events)): ?>
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="card" style="margin-bottom: 15px; cursor: default;">
                                    <div style="width: 100%;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <strong style="font-size: 16px; color: var(--gold);">
                                                <?php echo htmlspecialchars($event['event_type']); ?>
                                            </strong>
                                            <span style="font-size: 12px; color: #888;">
                                                <?php echo $event['booking_status']; ?>
                                            </span>
                                        </div>

                                        <div style="margin-top: 8px; font-size: 14px; line-height: 1.6;">
                                            <p><b>Venue:</b> <?php echo htmlspecialchars($event['venue']); ?></p>
                                            <p><b>Date & Time:</b>
                                                <?php echo date("F j, Y", strtotime($event['event_date'])); ?>
                                                at <?php echo date("g:i A", strtotime($event['event_time'])); ?>
                                            </p>
                                            <p><b>Package:</b> <?php echo htmlspecialchars($event['package_name']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fa-regular fa-calendar-minus"></i>
                                <p>No upcoming events. Ready to celebrate?</p>
                                <a href="choose_service.php" class="btn-schedule"
                                    style="text-decoration:none; display:inline-block;">Book Now</a>
                            </div>
                        <?php endif; ?>

                    </div>

                </div>


                <div id="completed" class="section">
                    <h3>Completed Events</h3>

                    <form method="POST" id="completedDeleteForm">

                        <div class="completed-action-bar">
                            <label class="select-all">
                                <input type="checkbox" id="selectAllCompleted" onclick="toggleAllCompleted(this)">
                                Select All
                            </label>

                            <button type="button" class="btn-delete-selected" onclick="confirmDeleteCompletedSelected()">
                                <i class="fa-solid fa-trash"></i> Delete Selected
                            </button>
                        </div>

                        <div id="completedEventsContent">

                            <?php if (!empty($completed_events)): ?>
                                <?php foreach ($completed_events as $event): ?>
                                <div class="card completed-event-card" style="margin-bottom: 15px; cursor: default;">
                                    <div style="display:flex; align-items:flex-start; gap:12px; width:100%;">
                                        
                                        <input type="checkbox"
                                            class="completed-event-checkbox"
                                            name="completed_event_ids[]"
                                            value="<?php echo (int)$event['id']; ?>"
                                            style="margin-top:4px; accent-color: var(--gold); flex-shrink:0;">

                                        <div style="width:100%;">
                                            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                                <strong style="font-size:16px; color: var(--gold);">
                                                    <?php echo htmlspecialchars($event['event_type']); ?>
                                                </strong>

                                                <span style="font-size:12px; color: green; font-weight:700;">
                                                    COMPLETED
                                                </span>
                                            </div>

                                            <div style="margin-top: 8px; font-size: 14px; line-height: 1.6;">
                                                <p><b>Venue:</b> <?php echo htmlspecialchars($event['venue']); ?></p>
                                                <p><b>Date & Time:</b>
                                                    <?php echo date("F j, Y", strtotime($event['event_date'])); ?>
                                                    at <?php echo !empty($event['event_time']) ? date("g:i A", strtotime($event['event_time'])) : 'N/A'; ?>
                                                </p>
                                                <p><b>Package:</b> <?php echo htmlspecialchars($event['package_name'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <p class="empty-text">No finished events yet.</p>
                            <?php endif; ?>

                        </div>

                        <input type="hidden" name="delete_completed_events" value="1">

                    </form>
                </div>


                <div id="aptModal" class="modal-overlay">
                    <div class="modal-content">
                        <i class="fa-solid fa-xmark close-modal" onclick="closeAptModal()"></i>
                        <h2 style="font-size: 18px;">Schedule New Appointment</h2>
                        <p style="color: #888; font-size: 13px;">Book a consultation with our planning team</p>
                        <form class="modal-grid" action="profile.php" method="POST">
                            <div class="form-group">
                                <label>Purpose of Visit</label>
                                <select name="purpose">
                                    <option>General Consultation</option>
                                    <option>Ocular Visit</option>
                                    <option>Package Customization</option>
                                    <option>Contract Signing</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="apt_date" required>
                            </div>
                            <div class="form-group full">
                                <label>Time Slot</label>
                                <select name="apt_time">
                                    <option>09:00 AM - 10:00 AM</option>
                                    <option>10:30 AM - 11:30 AM</option>
                                    <option>01:30 PM - 02:30 PM</option>
                                    <option>03:00 PM - 04:00 PM</option>
                                </select>
                            </div>
                            <div class="form-group full">
                                <label>Notes (Optional)</label>
                                <textarea name="notes" rows="3" placeholder="Tell us more about your event..."></textarea>
                            </div>
                            <div class="modal-footer" style="grid-column: span 2;">
                                <button type="button" class="btn-modal" style="background:#eee;"
                                    onclick="closeAptModal()">Cancel</button>
                                <button type="submit" class="btn-modal" style="background:var(--gold); color:white;">Submit
                                    Request</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="appointmentActionModal" class="confirm-overlay" style="display:none;">
                    <div class="confirm-card">
                        <div class="confirm-icon-wrap" id="appointmentActionIconWrap">
                            <i id="appointmentActionIcon" class="fas fa-xmark"></i>
                        </div>

                        <h3 id="appointmentActionTitle">Confirm Action</h3>
                        <p id="appointmentActionText">Are you sure?</p>

                        <div class="confirm-actions">
                            <button type="button" class="confirm-btn cancel"
                                onclick="closeAppointmentActionModal()">No</button>
                            <button type="button" class="confirm-btn" id="appointmentActionConfirmBtn">Yes</button>
                        </div>
                    </div>
                </div>

                <div id="deleteCompletedEventsModal" class="confirm-overlay" style="display:none;">
                    <div class="confirm-card">
                        <div class="confirm-icon-wrap delete-mode">
                            <i class="fas fa-trash"></i>
                        </div>

                        <h3>Delete Completed Events</h3>
                        <p>Are you sure you want to delete the selected completed event(s)?</p>

                        <div class="confirm-actions">
                            <button type="button" class="confirm-btn cancel"
                                onclick="closeDeleteCompletedModal()">Cancel</button>
                            <button type="button" class="confirm-btn delete-confirm"
                                onclick="submitDeleteCompletedForm()">Delete</button>
                        </div>
                    </div>
                </div>

                <div id="noSelectionModal" class="confirm-overlay" style="display:none;">
                    <div class="confirm-card">
                        <div class="confirm-icon-wrap" style="background:#fff7e3; color:#bf9225;">
                            <i class="fas fa-exclamation"></i>
                        </div>

                        <h3>No Selection</h3>
                        <p>Please select at least one completed event to delete.</p>

                        <div class="confirm-actions">
                            <button type="button" class="confirm-btn cancel" onclick="closeNoSelectionModal()">OK</button>
                        </div>
                    </div>
                </div>

                <div id="noAppointmentSelectionModal" class="confirm-overlay" style="display:none;">
                    <div class="confirm-card">
                        <div class="confirm-icon-wrap" style="background:#fff7e3; color:#bf9225;">
                            <i class="fas fa-exclamation"></i>
                        </div>

                        <h3>No Selection</h3>
                        <p>Please select at least one deletable appointment.</p>

                        <div class="confirm-actions">
                            <button type="button" class="confirm-btn cancel" onclick="closeNoAppointmentSelectionModal()">OK</button>
                        </div>
                    </div>
                </div>

                <div id="deleteAppointmentsModal" class="confirm-overlay" style="display:none;">
                    <div class="confirm-card">
                        <div class="confirm-icon-wrap delete-mode">
                            <i class="fas fa-trash"></i>
                        </div>

                        <h3>Delete Appointments</h3>
                        <p>Are you sure you want to delete the selected appointment(s)?</p>

                        <div class="confirm-actions">
                            <button type="button" class="confirm-btn cancel" onclick="closeDeleteAppointmentsModal()">Cancel</button>
                            <button type="button" class="confirm-btn delete-confirm" onclick="submitAppointmentsDeleteForm()">Delete</button>
                        </div>
                    </div>
                </div>

                <script>
                    function closeNoSelectionModal() {
                        const modal = document.getElementById('noSelectionModal');
                        if (modal) modal.style.display = 'none';
                    }

                    function toggleAllCompleted(source) {
                        const container = document.getElementById('completedEventsContent');
                        if (!container) return;

                        const checkboxes = container.querySelectorAll('.completed-event-checkbox');
                        checkboxes.forEach(cb => {
                            cb.checked = source.checked;
                        });
                    }

                    function confirmDeleteCompletedSelected() {
                        const checked = document.querySelectorAll('#completedEventsContent .completed-event-checkbox:checked');

                        if (checked.length === 0) {
                            const modal = document.getElementById('noSelectionModal');
                            if (modal) modal.style.display = 'flex';
                            return;
                        }

                        const modal = document.getElementById('deleteCompletedEventsModal');
                        if (modal) {
                            modal.style.display = 'flex';
                        }
                    }

                    function closeDeleteCompletedModal() {
                        const modal = document.getElementById('deleteCompletedEventsModal');
                        if (modal) {
                            modal.style.display = 'none';
                        }
                    }

                    function submitDeleteCompletedForm() {
                        const form = document.getElementById('completedDeleteForm');
                        if (form) {
                            form.submit();
                        }
                    }

                    function closeNoAppointmentSelectionModal() {
                        const modal = document.getElementById('noAppointmentSelectionModal');
                        if (modal) modal.style.display = 'none';
                    }

                    function toggleAllAppointments(source) {
                        const container = document.getElementById('appointmentsContent');
                        if (!container) return;

                        const checkboxes = container.querySelectorAll('.appointment-delete-checkbox');
                        checkboxes.forEach(cb => {
                            cb.checked = source.checked;
                        });
                    }

                    function confirmDeleteSelectedAppointments() {
                        const checked = document.querySelectorAll('#appointmentsContent .appointment-delete-checkbox:checked');

                        if (checked.length === 0) {
                            const modal = document.getElementById('noAppointmentSelectionModal');
                            if (modal) modal.style.display = 'flex';
                            return;
                        }

                        const modal = document.getElementById('deleteAppointmentsModal');
                        if (modal) modal.style.display = 'flex';
                    }

                    function closeDeleteAppointmentsModal() {
                        const modal = document.getElementById('deleteAppointmentsModal');
                        if (modal) modal.style.display = 'none';
                    }

                    function submitAppointmentsDeleteForm() {
                        const form = document.getElementById('appointmentsDeleteForm');
                        if (form) {
                            form.submit();
                        }
                    }

                    let appointmentActionUrl = '';

                    function openAppointmentActionModal(event, url, message, type = 'cancel') {
                        if (event) {
                            event.preventDefault();
                            event.stopPropagation();
                        }

                        appointmentActionUrl = url;

                        const modal = document.getElementById('appointmentActionModal');
                        const text = document.getElementById('appointmentActionText');
                        const title = document.getElementById('appointmentActionTitle');
                        const icon = document.getElementById('appointmentActionIcon');
                        const iconWrap = document.getElementById('appointmentActionIconWrap');
                        const confirmBtn = document.getElementById('appointmentActionConfirmBtn');

                        text.innerText = message;

                        iconWrap.classList.remove('cancel-mode', 'delete-mode');
                        confirmBtn.classList.remove('cancel-confirm', 'delete-confirm');

                        if (type === 'delete') {
                            title.innerText = 'Delete Appointment';
                            icon.className = 'fas fa-trash';
                            iconWrap.classList.add('delete-mode');
                            confirmBtn.classList.add('delete-confirm');
                            confirmBtn.innerText = 'Delete';
                        } else {
                            title.innerText = 'Cancel Appointment';
                            icon.className = 'fas fa-xmark';
                            iconWrap.classList.add('cancel-mode');
                            confirmBtn.classList.add('cancel-confirm');
                            confirmBtn.innerText = 'Cancel';
                        }

                        modal.style.display = 'flex';
                        return false;
                    }

                    function closeAppointmentActionModal() {
                        const modal = document.getElementById('appointmentActionModal');
                        if (modal) modal.style.display = 'none';
                        appointmentActionUrl = '';
                    }

                    document.addEventListener('DOMContentLoaded', function () {
                        const btn = document.getElementById('appointmentActionConfirmBtn');
                        const modal = document.getElementById('appointmentActionModal');

                        if (btn) {
                            btn.onclick = function () {
                                if (appointmentActionUrl) {
                                    window.location.href = appointmentActionUrl;
                                }
                            };
                        }

                        if (modal) {
                            modal.addEventListener('click', function (e) {
                                if (e.target === modal) {
                                    closeAppointmentActionModal();
                                }
                            });
                        }
                    });

                    function saveMessageState() { localStorage.setItem('activeProfileSection', 'message'); }

                    function switchSection(secId) {
                        document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
                        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

                        const targetSection = document.getElementById(secId);
                        if (targetSection) targetSection.classList.add('active');

                        const targetTab = document.getElementById('btn-' + secId);
                        if (targetTab) targetTab.classList.add('active');

                        const navTitle = document.getElementById('nav-title');
                        navTitle.innerText =
                            (secId === 'message') ? 'Messages' :
                                (secId === 'appointments') ? 'Appointments' : '';

                        localStorage.setItem('activeProfileSection', secId);

                        const backBtn = document.getElementById('messageBackBtn');

                        if (secId === 'message') {
                            openMobileMessageView();

                            if (backBtn && window.innerWidth > 768) {
                                backBtn.style.display = 'none';
                            }

                            userIsReadingOldMessages = false;

                            setTimeout(() => {
                                scrollToBottom(true);
                            }, 80);

                            refreshUserChat().then(() => {
                                setTimeout(() => {
                                    scrollToBottom(true);
                                }, 80);
                            });

                            startChatPolling();
                        } else {
                            document.body.classList.remove('mobile-message-mode');

                            if (backBtn) backBtn.style.display = 'none';

                            stopChatPolling();
                        }

                        if (secId === 'upcoming' || secId === 'completed') {
                            refreshUserEvents();
                            startEventsPolling();
                        } else {
                            stopEventsPolling();
                        }

                        if (secId === 'appointments') {
                            refreshAppointments();
                            startAppointmentsPolling();
                        } else {
                            stopAppointmentsPolling();
                        }
                    }

                    function scrollToBottom(force = false) {
                        const cb = document.getElementById("chatBox");
                        if (!cb) return;

                        if (force || !userIsReadingOldMessages) {
                            cb.scrollTop = cb.scrollHeight;
                            userIsReadingOldMessages = false;
                        }
                    }

                    function isNearBottom(el, threshold = 100) {
                        if (!el) return true;
                        return (el.scrollHeight - el.scrollTop - el.clientHeight) <= threshold;
                    }

                    function updateReadingState() {
                        const chatBox = document.getElementById('chatBox');
                        if (!chatBox) return;

                        userIsReadingOldMessages = !isNearBottom(chatBox, 120);
                    }

                    function openAptModal() { document.getElementById('aptModal').style.display = 'flex'; }
                    function closeAptModal() { document.getElementById('aptModal').style.display = 'none'; }

                    function updateViewportHeight() {
                        let vh = window.innerHeight;

                        if (window.visualViewport) {
                            vh = window.visualViewport.height + window.visualViewport.offsetTop;
                        }

                        document.documentElement.style.setProperty('--vvh', vh + 'px');

                        const nav = document.querySelector('.navbar');
                        if (nav) {
                            document.documentElement.style.setProperty('--nav-height', nav.offsetHeight + 'px');
                        }
                    }

                    function openMobileMessageView() {
                        if (window.innerWidth <= 768) {
                            document.body.classList.add('mobile-message-mode');

                            const backBtn = document.getElementById('messageBackBtn');
                            if (backBtn) backBtn.style.display = 'inline-flex';

                            updateViewportHeight();
                            scrollToBottom();
                        }
                    }

                    function closeMobileMessageView() {
                        document.body.classList.remove('mobile-message-mode');

                        const backBtn = document.getElementById('messageBackBtn');
                        if (backBtn) backBtn.style.display = 'none';

                        localStorage.setItem('activeProfileSection', 'profile');
                        switchSection('profile');
                    }


                    let chatPolling = null;
                    let sendingMessage = false;
                    let shouldForceScrollToBottom = false;
                    let userIsReadingOldMessages = false;

                    async function refreshUserChat() {
                        const messageSection = document.getElementById('message');
                        if (!messageSection || !messageSection.classList.contains('active')) return;

                        try {
                            const chatBox = document.getElementById('chatBox');
                            const wasNearBottom = isNearBottom(chatBox, 120);

                            const res = await fetch('ajax.php?action=fetch_chat', {
                                cache: 'no-store'
                            });
                            const data = await res.json();

                            if (data.success && chatBox) {
                                const oldScrollHeight = chatBox.scrollHeight;
                                const oldScrollTop = chatBox.scrollTop;
                                const isMessageSectionActive = document.getElementById('message')?.classList.contains('active');

                                chatBox.innerHTML = data.html;

                                if (shouldForceScrollToBottom) {
                                    scrollToBottom(true);
                                    shouldForceScrollToBottom = false;
                                } else if (isMessageSectionActive && !userIsReadingOldMessages && wasNearBottom) {
                                    scrollToBottom();
                                } else if (isMessageSectionActive && oldScrollTop === 0 && oldScrollHeight === 0) {
                                    scrollToBottom(true);
                                } else {
                                    const newScrollHeight = chatBox.scrollHeight;
                                    chatBox.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
                                }
                            }
                        } catch (err) {
                            console.error('User chat refresh failed:', err);
                        }
                    }

                    async function sendUserMessageAjax(e) {
                        e.preventDefault();
                        saveMessageState();

                        if (sendingMessage) return;

                        const input = document.getElementById('messageInput');
                        const message = input.value.trim();
                        if (!message) return;

                        sendingMessage = true;

                        const formData = new FormData();
                        formData.append('action', 'send_message');
                        formData.append('message_text', message);

                        try {
                            const res = await fetch('ajax.php', {
                                method: 'POST',
                                body: formData
                            });

                            const data = await res.json();

                            if (data.success) {
                                input.value = '';
                                shouldForceScrollToBottom = true;
                                await refreshUserChat();
                            } else {
                                alert(data.message || 'Failed to send message.');
                            }
                        } catch (err) {
                            console.error('User send failed:', err);
                            alert('Failed to send message.');
                        } finally {
                            sendingMessage = false;
                        }
                    }

                    function startChatPolling() {
                        if (chatPolling) clearInterval(chatPolling);
                        chatPolling = setInterval(refreshUserChat, 2500);
                    }

                    function stopChatPolling() {
                        if (chatPolling) {
                            clearInterval(chatPolling);
                            chatPolling = null;
                        }
                    }

                    let userHeartbeatPolling = null;

                    async function pingUserHeartbeat() {
                        try {
                            await fetch('ajax.php?action=heartbeat', {
                                method: 'GET',
                                cache: 'no-store',
                                credentials: 'same-origin'
                            });
                        } catch (err) {
                            console.error('User heartbeat failed:', err);
                        }
                    }

                    function startUserHeartbeat() {
                        if (userHeartbeatPolling) return;

                        pingUserHeartbeat();
                        userHeartbeatPolling = setInterval(pingUserHeartbeat, 10000);
                    }

                    function stopUserHeartbeat() {
                        if (userHeartbeatPolling) {
                            clearInterval(userHeartbeatPolling);
                            userHeartbeatPolling = null;
                        }
                    }

                    

                    let eventsPolling = null;

                    async function refreshUserEvents() {
                        const upcomingContent = document.getElementById('upcomingEventsContent');
                        const completedContent = document.getElementById('completedEventsContent');

                        if (!upcomingContent || !completedContent) return;

                        const checkedIds = [];
                        document.querySelectorAll('#completedEventsContent .completed-event-checkbox:checked')
                            .forEach(cb => checkedIds.push(cb.value));

                        try {
                            const res = await fetch('ajax.php?action=fetch_events', {
                                cache: 'no-store'
                            });
                            const data = await res.json();

                            if (data.success) {
                                upcomingContent.innerHTML = data.upcoming_html;
                                completedContent.innerHTML = data.completed_html;

                                document.querySelectorAll('#completedEventsContent .completed-event-checkbox')
                                    .forEach(cb => {
                                        if (checkedIds.includes(cb.value)) {
                                            cb.checked = true;
                                        }
                                    });
                            }
                        } catch (err) {
                            console.error('Events refresh failed:', err);
                        }
                    }

                    function startEventsPolling() {
                        if (eventsPolling) clearInterval(eventsPolling);
                        eventsPolling = setInterval(refreshUserEvents, 3000);
                    }

                    function stopEventsPolling() {
                        if (eventsPolling) {
                            clearInterval(eventsPolling);
                            eventsPolling = null;
                        }
                    }


                    let appointmentsPolling = null;

                    async function refreshAppointments() {
                        const appointmentsContent = document.getElementById('appointmentsContent');
                        if (!appointmentsContent) return;

                        const checkedIds = [];
                        document.querySelectorAll('#appointmentsContent .appointment-delete-checkbox:checked')
                            .forEach(cb => checkedIds.push(cb.value));

                        try {
                            const res = await fetch('ajax.php?action=fetch_appointments', {
                                cache: 'no-store'
                            });
                            const data = await res.json();

                            if (data.success) {
                                appointmentsContent.innerHTML = data.html;

                                document.querySelectorAll('#appointmentsContent .appointment-delete-checkbox')
                                    .forEach(cb => {
                                        if (checkedIds.includes(cb.value)) {
                                            cb.checked = true;
                                        }
                                    });

                                const selectAll = document.getElementById('selectAllAppointments');
                                if (selectAll) {
                                    const allBoxes = document.querySelectorAll('#appointmentsContent .appointment-delete-checkbox');
                                    const checkedBoxes = document.querySelectorAll('#appointmentsContent .appointment-delete-checkbox:checked');
                                    selectAll.checked = allBoxes.length > 0 && allBoxes.length === checkedBoxes.length;
                                }
                            }
                        } catch (err) {
                            console.error('Appointments refresh failed:', err);
                        }
                    }

                    function startAppointmentsPolling() {
                        if (appointmentsPolling) clearInterval(appointmentsPolling);
                        appointmentsPolling = setInterval(refreshAppointments, 3000);
                    }

                    function stopAppointmentsPolling() {
                        if (appointmentsPolling) {
                            clearInterval(appointmentsPolling);
                            appointmentsPolling = null;
                        }
                    }

                    (function () {
                        const input = document.getElementById('messageInput');
                        const chatBox = document.getElementById('chatBox');

                        if (!input || !chatBox) return;

                        function keepVisible() {
                            setTimeout(() => {
                                if (window.innerWidth <= 768) {
                                    document.body.classList.add('mobile-message-mode');
                                }

                                updateViewportHeight();

                                if (!userIsReadingOldMessages) {
                                    requestAnimationFrame(() => {
                                        scrollToBottom();
                                    });
                                }
                            }, 120);
                        }

                        function handleViewportChange() {
                            updateViewportHeight();

                            if (document.body.classList.contains('mobile-message-mode') && !userIsReadingOldMessages) {
                                requestAnimationFrame(() => {
                                    scrollToBottom();
                                });
                            }
                        }

                        chatBox.addEventListener('scroll', updateReadingState, { passive: true });

                        input.addEventListener('focus', keepVisible);
                        input.addEventListener('click', keepVisible);

                        window.addEventListener('resize', handleViewportChange);

                        if (window.visualViewport) {
                            window.visualViewport.addEventListener('resize', handleViewportChange);
                            window.visualViewport.addEventListener('scroll', handleViewportChange);
                        }

                        updateReadingState();
                        updateViewportHeight();
                    })();



                    window.onload = function () {
                        const urlParams = new URLSearchParams(window.location.search);
                        const viewParam = urlParams.get('view');
                        const savedSection = localStorage.getItem('activeProfileSection');

                        if (viewParam) switchSection(viewParam);
                        else if (savedSection) switchSection(savedSection);
                        else switchSection('profile');

                        setTimeout(() => {
                            const activeSection = document.querySelector('.section.active');
                            if (activeSection && activeSection.id === 'message') {
                                userIsReadingOldMessages = false;
                                scrollToBottom(true);
                            }
                        }, 150);

                        const activeSection = document.querySelector('.section.active');
                        if (activeSection && (activeSection.id === 'upcoming' || activeSection.id === 'completed')) {
                            refreshUserEvents();
                            startEventsPolling();
                        }

                        if (activeSection && activeSection.id === 'appointments') {
                            refreshAppointments();
                            startAppointmentsPolling();
                        }

                        const chatForm = document.getElementById('chatForm');
                            if (chatForm) {
                                chatForm.addEventListener('submit', sendUserMessageAjax);
                            }

                            if (!document.hidden) {
                                startUserHeartbeat();
                            }

                            updateViewportHeight();
                            };

                        window.addEventListener('focus', function () {
                            if (!document.hidden) {
                                startUserHeartbeat();
                            }
                        });

                        window.addEventListener('pageshow', function () {
                            if (!document.hidden) {
                                startUserHeartbeat();
                            }
                        });

                        document.addEventListener('visibilitychange', function () {
                            if (document.hidden) {
                                stopUserHeartbeat();
                            } else {
                                startUserHeartbeat();
                            }
                        });

                        window.addEventListener('pagehide', stopUserHeartbeat);
                        window.addEventListener('beforeunload', stopUserHeartbeat);


                </script>
</body>
</html>