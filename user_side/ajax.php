<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized'
    ]);
    exit();
}

$user_id = (int) $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'check_account_status') {
    $stmt = mysqli_prepare($conn, "SELECT status FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (!$result || mysqli_num_rows($result) === 0) {
        session_unset();
        session_destroy();

        echo json_encode([
            'success' => false,
            'logout' => true
        ]);
        exit();
    }

    $user = mysqli_fetch_assoc($result);
    $status = $user['status'] ?? 'Active';

    if ($status !== 'Active') {
        session_unset();
        session_destroy();

        echo json_encode([
            'success' => false,
            'logout' => true,
            'message' => 'Your account has been ' . strtolower($status) . '.'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'logout' => false
    ]);
    exit();
}

if ($action === 'heartbeat') {
    mysqli_query($conn, "UPDATE users SET last_active = NOW() WHERE id = {$user_id}");

    echo json_encode([
        'success' => true,
        'message' => 'Heartbeat updated'
    ]);
    exit();
}

function formatChatTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

if ($action === 'fetch_chat') {
    $mark_admin_read = "UPDATE chat_messages 
                        SET is_read = 1 
                        WHERE user_id = ? AND sender = 'admin' AND is_read = 0";
    $stmt_mark = mysqli_prepare($conn, $mark_admin_read);
    mysqli_stmt_bind_param($stmt_mark, "i", $user_id);
    mysqli_stmt_execute($stmt_mark);

    $get_chat = "SELECT id, sender, message, created_at, is_read
                 FROM chat_messages
                 WHERE user_id = ?
                 ORDER BY created_at ASC, id ASC";
    $stmt_chat = mysqli_prepare($conn, $get_chat);
    mysqli_stmt_bind_param($stmt_chat, "i", $user_id);
    mysqli_stmt_execute($stmt_chat);
    $result_chat = mysqli_stmt_get_result($stmt_chat);

    $chat_messages = [];
    while ($row = mysqli_fetch_assoc($result_chat)) {
        $chat_messages[] = $row;
    }

    ob_start();

    if (!empty($chat_messages)) {
        $last_user_index = -1;
        for ($i = count($chat_messages) - 1; $i >= 0; $i--) {
            if ($chat_messages[$i]['sender'] === 'user') {
                $last_user_index = $i;
                break;
            }
        }

        foreach ($chat_messages as $index => $msg) {
            ?>
            <div class="bubble <?php echo ($msg['sender'] === 'user') ? 'sent' : 'received'; ?>">
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                <div style="font-size:11px; margin-top:6px; opacity:0.8;">
                    <?php echo formatChatTime($msg['created_at']); ?>
                </div>

                <?php if ($msg['sender'] === 'user' && $index === $last_user_index): ?>
                    <div style="font-size:11px; margin-top:4px; text-align:right; opacity:0.8;">
                        <?php echo ((int)$msg['is_read'] === 1) ? 'Seen' : 'Sent'; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    } else {
        ?>
        <div style="text-align:center; color:#888; font-size:14px; margin:auto;">
            No messages yet. Start chatting with Magarbo Events Team.
        </div>
        <?php
    }

    echo json_encode([
        'success' => true,
        'html' => ob_get_clean()
    ]);
    exit();
}

if ($action === 'send_message') {
    $message_text = trim($_POST['message_text'] ?? '');

    if ($message_text === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Empty message.'
        ]);
        exit();
    }

    $insert_msg = "INSERT INTO chat_messages (user_id, sender, message, is_read)
                   VALUES (?, 'user', ?, 0)";
    $stmt_msg = mysqli_prepare($conn, $insert_msg);

    if (!$stmt_msg) {
        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed.'
        ]);
        exit();
    }

    mysqli_stmt_bind_param($stmt_msg, "is", $user_id, $message_text);

    if (!mysqli_stmt_execute($stmt_msg)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send message.'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true
    ]);
    exit();
}

if ($action === 'get_notifications') {
    $delete_old = "DELETE FROM notifications 
                   WHERE user_id = ? 
                   AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt_delete = mysqli_prepare($conn, $delete_old);
    mysqli_stmt_bind_param($stmt_delete, "i", $user_id);
    mysqli_stmt_execute($stmt_delete);

    $count_sql = "SELECT COUNT(*) AS total FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = mysqli_prepare($conn, $count_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $count = mysqli_fetch_assoc($res)['total'] ?? 0;

    $list_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
    $stmt2 = mysqli_prepare($conn, $list_sql);
    mysqli_stmt_bind_param($stmt2, "i", $user_id);
    mysqli_stmt_execute($stmt2);
    $res2 = mysqli_stmt_get_result($stmt2);

    $notifications = [];

    while ($row = mysqli_fetch_assoc($res2)) {
        $row['created_at'] = date('M d, Y g:i A', strtotime($row['created_at']));
        $notifications[] = $row;
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $count,
        'notifications' => $notifications
    ]);
    exit();
}

if ($action === 'fetch_events') {
    $query_bookings = "SELECT * FROM bookings WHERE user_id = ? ORDER BY id DESC";
    $stmt_b = mysqli_prepare($conn, $query_bookings);
    mysqli_stmt_bind_param($stmt_b, "i", $user_id);
    mysqli_stmt_execute($stmt_b);
    $result_bookings = mysqli_stmt_get_result($stmt_b);

    $upcoming_events = [];
    $completed_events = [];

    while ($row = mysqli_fetch_assoc($result_bookings)) {
        $bookingStatus = strtolower(trim($row['booking_status'] ?? ''));
        $eventStatus   = strtolower(trim($row['event_status'] ?? ''));

        if ($bookingStatus === 'completed' || $eventStatus === 'completed') {
            $completed_events[] = $row;
        } else {
            $upcoming_events[] = $row;
        }
    }

    ob_start();
    if (!empty($upcoming_events)) {
        foreach ($upcoming_events as $event) {
            ?>
            <div class="card" style="margin-bottom: 15px; cursor: default;">
                <div style="width: 100%;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <strong style="font-size: 16px; color: var(--gold);">
                            <?php echo htmlspecialchars($event['event_type']); ?>
                        </strong>
                        <span style="font-size: 12px; color: #888;">
                            <?php echo htmlspecialchars($event['booking_status']); ?>
                        </span>
                    </div>
                    <div style="margin-top: 8px; font-size: 14px; line-height: 1.6;">
                        <p><b>Venue:</b> <?php echo htmlspecialchars($event['venue']); ?></p>
                        <p><b>Date & Time:</b>
                            <?php echo date("F j, Y", strtotime($event['event_date'])); ?>
                            at
                            <?php echo !empty($event['event_time']) ? date("g:i A", strtotime($event['event_time'])) : 'N/A'; ?>
                        </p>
                        <p><b>Package:</b> <?php echo htmlspecialchars($event['package_name'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="empty-state">
            <i class="fa-regular fa-calendar-minus"></i>
            <p>No upcoming events. Ready to celebrate?</p>
            <a href="choose_service.php" class="btn-schedule" style="text-decoration:none; display:inline-block;">Book Now</a>
        </div>
        <?php
    }
    $upcoming_html = ob_get_clean();

    ob_start();
    if (!empty($completed_events)) {
        foreach ($completed_events as $event) {
            ?>
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
            <?php
        }
    } else {
        ?>
        <p style="color:#888;">No finished events yet.</p>
        <?php
    }
    $completed_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'upcoming_html' => $upcoming_html,
        'completed_html' => $completed_html
    ]);
    exit();
}

if ($action === 'fetch_appointments') {
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

    ob_start();

    if (!empty($my_appointments)) {
        foreach ($my_appointments as $apt) {
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
                            <strong style="font-size: 16px; color: var(--gold);">
                                <?php echo htmlspecialchars($apt['purpose']); ?>
                            </strong>

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 12px; color: #888; font-weight: bold;">
                                    <?php echo strtoupper($apt['status']); ?>
                                </span>

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
            <?php
        }
    } else {
        ?>
        <p style="color:#888; text-align: center; padding: 20px;">No appointments found.</p>
        <?php
    }

    echo json_encode([
        'success' => true,
        'html' => ob_get_clean()
    ]);
    exit();
}


echo json_encode([
    'success' => false,
    'message' => 'Invalid action.'
]);
exit();