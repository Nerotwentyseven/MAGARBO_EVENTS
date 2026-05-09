<?php
session_name('ADMINSESSID');
require_once 'admin_auth.php';
require_once '../db_connection.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'heartbeat') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit();
    }

    echo json_encode([
        'success' => true
    ]);
    exit();
}

if ($action === 'heartbeat') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['admin_logged_in'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized'
        ]);
        exit();
    }

    $now = date('Y-m-d H:i:s');

    echo json_encode([
        'success' => true,
        'time' => $now
    ]);
    exit();
}

function formatChatTime($datetime) {
    return date('M d, Y g:i A', strtotime($datetime));
}

if ($action === 'fetch_inbox') {
    $selected_user_id = (int) ($_GET['selected_user_id'] ?? 0);

    $inbox_sql = "
        SELECT 
            m.user_id,
            CONCAT(u.firstname, ' ', u.lastname) AS full_name,
            u.email,
            u.last_active,
            m.message,
            m.created_at,
            (
                SELECT COUNT(*) 
                FROM chat_messages cm
                WHERE cm.user_id = m.user_id 
                  AND cm.sender = 'user' 
                  AND cm.is_read = 0
            ) AS unread_count
        FROM chat_messages m
        INNER JOIN (
            SELECT user_id, MAX(id) AS last_id
            FROM chat_messages
            GROUP BY user_id
        ) latest ON m.id = latest.last_id
        INNER JOIN users u ON u.id = m.user_id
        ORDER BY m.created_at DESC
    ";

    $inbox_result = mysqli_query($conn, $inbox_sql);

    ob_start();

    if ($inbox_result && mysqli_num_rows($inbox_result) > 0) {
        while ($row = mysqli_fetch_assoc($inbox_result)) {
            $is_online = !empty($row['last_active']) && (strtotime($row['last_active']) > (time() - 25));
            $is_active = ($selected_user_id == $row['user_id']);
            $has_unread = ($row['unread_count'] > 0 && !$is_active);
            ?>
            <a href="messages.php?user_id=<?php echo $row['user_id']; ?>" 
                class="inbox-item <?php echo $is_active ? 'active-msg' : ''; ?>"
                data-user-id="<?php echo (int)$row['user_id']; ?>"
                data-online="<?php echo $is_online ? '1' : '0'; ?>"
                style="
                    display:block; 
                    text-decoration:none; 
                    color:inherit; 
                    border-left: 4px solid <?php echo $has_unread ? '#bf9225' : 'transparent'; ?>;
                    background: <?php echo $has_unread ? '#fff8e6' : '#fff'; ?>;
                    padding: 14px 16px;
               ">

                <div class="inbox-item-top" style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <div>
                        <div class="sender-name">
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                        </div>

                        <div style="font-size:12px; margin-top:3px; color: <?php echo $is_online ? 'green' : '#999'; ?>; font-weight:600;">
                            ● <?php echo $is_online ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>

                    <?php if ($has_unread): ?>
                        <div style="background:#bf9225; color:#fff; min-width:24px; height:24px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; padding:0 8px;">
                            <?php echo (int)$row['unread_count']; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="inbox-item-subj" style="margin-top:10px; font-weight:600;">
                    <?php echo $has_unread ? $row['unread_count'] . ' new message' . ($row['unread_count'] > 1 ? 's' : '') : 'Conversation'; ?>
                </div>

                <p class="inbox-item-preview" style="margin-top:6px;">
                    <?php echo htmlspecialchars(mb_strimwidth($row['message'], 0, 55, '...')); ?>
                </p>

                <div style="font-size:12px; color:#888; margin-top:8px;">
                    <?php echo formatChatTime($row['created_at']); ?>
                </div>
            </a>
            <?php
        }
    } else {
        echo '<p style="padding:20px; color:#888;">No messages found.</p>';
    }

    echo json_encode([
        'success' => true,
        'html' => ob_get_clean()
    ]);
    exit();
}

if ($action === 'fetch_chat') {
    $selected_user_id = (int) ($_GET['user_id'] ?? 0);

    if ($selected_user_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid user.'
        ]);
        exit();
    }

    $user_sql = "SELECT last_active FROM users WHERE id = ?";
    $stmt_user = mysqli_prepare($conn, $user_sql);
    mysqli_stmt_bind_param($stmt_user, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_user);
    $user_result = mysqli_stmt_get_result($stmt_user);
    $user_row = mysqli_fetch_assoc($user_result);

    $is_online = !empty($user_row['last_active']) && (strtotime($user_row['last_active']) > (time() - 25));
    $status_html = '<span style="color:' . ($is_online ? 'green' : '#999') . '; font-weight:600;">● ' . ($is_online ? 'Online' : 'Offline') . '</span>';

    $mark_read = "UPDATE chat_messages
                SET is_read = 1
                WHERE user_id = ? AND sender = 'user' AND is_read = 0";
    $stmt_read = mysqli_prepare($conn, $mark_read);
    mysqli_stmt_bind_param($stmt_read, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_read);

    $chat_sql = "SELECT sender, message, created_at, is_read
                 FROM chat_messages
                 WHERE user_id = ?
                 ORDER BY created_at ASC, id ASC";
    $stmt_chat = mysqli_prepare($conn, $chat_sql);
    mysqli_stmt_bind_param($stmt_chat, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_chat);
    $chat_result = mysqli_stmt_get_result($stmt_chat);

    $chat_rows = [];
        while ($msg = mysqli_fetch_assoc($chat_result)) {
            $chat_rows[] = $msg;
        }

        $last_admin_index = -1;
        foreach ($chat_rows as $i => $row) {
            if ($row['sender'] === 'admin') {
                $last_admin_index = $i;
            }
        }

        ob_start();
        foreach ($chat_rows as $index => $msg) {
            ?>
            <div class="message-bubble <?php echo ($msg['sender'] === 'admin') ? 'msg-right' : 'msg-left'; ?>" style="margin-bottom:12px;">
                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                <div style="font-size:11px; margin-top:6px; opacity:.7;">
                    <?php echo formatChatTime($msg['created_at']); ?>
                </div>

                <?php if ($msg['sender'] === 'admin' && $index === $last_admin_index): ?>
                    <div style="font-size:11px; margin-top:4px; text-align:right; opacity:.8;">
                        <?php echo ((int)$msg['is_read'] === 1) ? 'Seen' : 'Sent'; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }

    echo json_encode([
        'success' => true,
        'html' => ob_get_clean(),
        'status_html' => $status_html,
        'is_online' => $is_online
    ]);
    exit();
    }

if ($action === 'send_message') {
    $reply_text = trim($_POST['reply_text'] ?? '');
    $reply_user_id = (int) ($_POST['user_id'] ?? 0);

    if ($reply_user_id <= 0 || $reply_text === '') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid input.'
        ]);
        exit();
    }

    $insert_reply = "INSERT INTO chat_messages (user_id, sender, message, is_read)
        VALUES (?, 'admin', ?, 0)";
    $stmt_reply = mysqli_prepare($conn, $insert_reply);
    mysqli_stmt_bind_param($stmt_reply, "is", $reply_user_id, $reply_text);

    if (!mysqli_stmt_execute($stmt_reply)) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to send message.'
        ]);
        exit();
    }

    $notif_title = "New Message!";
    $notif_message = "Admin replied to your query. Click to view.";
    $notif_link = "profile.php?view=message";

    $insert_notif = "INSERT INTO notifications (user_id, type, title, message, link, is_read)
                     VALUES (?, 'message', ?, ?, ?, 0)";
    $stmt_notif = mysqli_prepare($conn, $insert_notif);

    if ($stmt_notif) {
        mysqli_stmt_bind_param($stmt_notif, "isss", $reply_user_id, $notif_title, $notif_message, $notif_link);
        mysqli_stmt_execute($stmt_notif);
    }

    echo json_encode([
        'success' => true
    ]);
    exit();
}

if ($action === 'fetch_appointments_admin') {
    $sql = "SELECT a.*, 
            CONCAT(u.firstname, ' ', u.lastname) AS full_name,
            u.email,
            u.phone
            FROM appointments a
            JOIN users u ON a.user_id = u.id
            ORDER BY 
                CASE 
                    WHEN a.cancelled_at IS NOT NULL THEN a.cancelled_at
                    ELSE a.created_at
                END DESC,
                a.id DESC";

    $result = mysqli_query($conn, $sql);

    $data = [];
    $total = 0;
    $pending = 0;
    $approved = 0;
    $completed = 0;
    $cancelled = 0;
   

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
            $total++;

            if ($row['status'] === 'Pending') $pending++;
            if ($row['status'] === 'Approved') $approved++;
            if ($row['status'] === 'Completed') $completed++;
            if ($row['status'] === 'Cancelled') $cancelled++;
        }
    }

    ob_start();
    if (!empty($data)) {
        foreach ($data as $apt) {
            $displayAppointmentId = !empty($apt['display_appointment_id'])
    ? $apt['display_appointment_id']
    : 'APT' . str_pad((int)$apt['id'], 3, '0', STR_PAD_LEFT);

            $canUndo = false;
            if (
                $apt['status'] === 'Cancelled' &&
                !empty($apt['cancelled_at']) &&
                strtotime($apt['cancelled_at']) >= strtotime('-3 days')
            ) {
                $canUndo = true;
            }
            ?>
            <tr
                data-status="<?php echo strtolower($apt['status']); ?>"
                data-date="<?php echo htmlspecialchars($apt['apt_date']); ?>"
                data-search="<?php echo strtolower(htmlspecialchars($displayAppointmentId . ' ' . $apt['full_name'] . ' ' . $apt['purpose'])); ?>"
            >
                <td><?php echo htmlspecialchars($displayAppointmentId); ?></td>
                <td><?php echo htmlspecialchars($apt['full_name']); ?></td>
                <td><?php echo htmlspecialchars($apt['purpose']); ?></td>
                <td><?php echo date("m/d/Y", strtotime($apt['apt_date'])); ?></td>
                <td><?php echo htmlspecialchars($apt['apt_time']); ?></td>
                <td>
                    <span class="status-tag <?php echo strtolower($apt['status']); ?>">
                        <?php echo strtolower($apt['status']); ?>
                    </span>
                </td>
                <td class="action-group">

                    <?php if ($apt['status'] == 'Pending'): ?>
                        <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Approved"
                        onclick="return openConfirm(event, this.href, 'Approve this appointment?')"
                        class="btn-action-icon">
                            <i class="fas fa-check"></i>
                        </a>

                        <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Cancelled"
                        onclick="return openConfirm(event, this.href, 'Cancel this appointment?')"
                        class="btn-action-icon">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php elseif ($apt['status'] == 'Approved'): ?>
                        <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=Completed"
                        onclick="return openConfirm(event, this.href, 'Mark this appointment as completed?')"
                        class="btn-action-icon">
                            <i class="fas fa-check-double"></i>
                        </a>
                    <?php endif; ?>

                    <button class="btn-action-icon"
                        onclick="showDetails(
                            '<?php echo htmlspecialchars($displayAppointmentId, ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['full_name'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['email'] ?? 'N/A', ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['phone'] ?? 'N/A', ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['purpose'], ENT_QUOTES); ?>',
                            '<?php echo date("m/d/Y", strtotime($apt['apt_date'])); ?>',
                            '<?php echo htmlspecialchars($apt['apt_time'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['status'], ENT_QUOTES); ?>',
                            '<?php echo htmlspecialchars($apt['notes'] ?? 'No notes provided.', ENT_QUOTES); ?>'
                        )">
                        <i class="fas fa-eye"></i>
                    </button>

                    <?php if ($canUndo): ?>
                        <a href="update_appointment.php?id=<?php echo $apt['id']; ?>&status=UndoCancel"
                        onclick="return openConfirm(event, this.href, 'Undo this cancelled appointment?')"
                        class="btn-action-icon undo">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>

                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="7">No appointments found</td></tr>';
    }

    $table_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'pending' => $pending,
        'approved' => $approved,
        'completed' => $completed,
        'cancelled' => $cancelled,
        'table_html' => $table_html
    ]);
    exit();
}

if ($action === 'fetch_orders_admin') {
    $sql = "SELECT 
        b.*,
        CONCAT(u.firstname, ' ', u.lastname) AS full_name,
        u.email AS user_email,
        u.phone AS user_phone,
        IFNULL(b.downpayment, 0) AS downpayment_amount,
        CASE
            WHEN b.downpayment IS NOT NULL AND b.downpayment > 0 THEN 'Paid'
            WHEN bp.payment_status = 'Paid' THEN 'Paid'
            ELSE 'Pending'
        END AS payment_status
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN booking_payments bp
        ON bp.id = (
            SELECT bp2.id
            FROM booking_payments bp2
            WHERE bp2.booking_id = b.id
            ORDER BY bp2.paid_at DESC, bp2.id DESC
            LIMIT 1
        )
        ORDER BY
    CASE
        WHEN b.booking_status = 'Pending' THEN 1
        WHEN b.booking_status = 'Approved' THEN 2
        WHEN b.booking_status = 'Rejected' THEN 3
        WHEN b.booking_status = 'Cancelled' THEN 4
        ELSE 5
    END ASC,

    CASE
        WHEN b.booking_status = 'Pending' THEN b.created_at
        ELSE NULL
    END DESC,

    CASE
        WHEN b.booking_status = 'Approved' THEN b.event_date
        ELSE NULL
    END ASC,

    CASE
        WHEN b.booking_status = 'Approved' THEN b.created_at
        ELSE NULL
    END ASC,

    b.id ASC";

    $result = mysqli_query($conn, $sql);

    $data = [];
    $total = 0;
    $pending = 0;
    $approved = 0;
    $rejected = 0;
    $cancelled = 0;

    

    if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $total++;

        if (($row['booking_status'] ?? '') === 'Pending') $pending++;
        if (($row['booking_status'] ?? '') === 'Approved') $approved++;
        if (($row['booking_status'] ?? '') === 'Rejected') $rejected++;
    if (($row['booking_status'] ?? '') === 'Cancelled') $cancelled++;

        if (empty($row['display_order_id'])) {
            $row['display_order_id'] = 'ORD-' . $row['id'];
        }

        $data[] = $row;
    }
}

    ob_start();

    if (!empty($data)) {
        foreach ($data as $o) {
            $menuItems = [];
            $hasFoods = false;

            if (!empty($o['menu_selection']) && $o['menu_selection'] !== 'None') {
                $decodedMenu = json_decode($o['menu_selection'], true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedMenu)) {
                    foreach ($decodedMenu as $item) {
                        if (is_array($item)) {
                            $itemName = $item['name'] ?? 'Selected Menu';
                            $itemPrice = isset($item['price']) ? (float)$item['price'] : 0;

                            $itemQty = 1;
                            if (isset($item['qty']) && is_numeric($item['qty'])) {
                                $itemQty = (int)$item['qty'];
                            } elseif (isset($item['quantity']) && is_numeric($item['quantity'])) {
                                $itemQty = (int)$item['quantity'];
                            }

                            $menuItems[] = [
                                'name'     => $itemName,
                                'price'    => $itemPrice,
                                'qty'      => $itemQty,
                                'subtotal' => $itemPrice * $itemQty
                            ];
                        }
                    }

                    if (!empty($menuItems)) {
                        $hasFoods = true;
                    }
                } else {
                    $menuItems[] = [
                        'name'     => $o['menu_selection'],
                        'price'    => 0,
                        'qty'      => 1,
                        'subtotal' => 0
                    ];
                    $hasFoods = true;
                }
            }

            $modalData = [
                "id"             => $o['display_order_id'],
                "name"           => $o['full_name'],
                "event"          => $o['event_type'],
                "date"           => date("M d, Y", strtotime($o['event_date'])),
                "email"          => $o['user_email'],
                "phone"          => $o['user_phone'] ?? 'N/A',
                "service"        => $o['service_type'] ?? 'N/A',
                "venue"          => $o['venue'] ?? 'N/A',
                "time"           => $o['event_time'] ?? 'N/A',
                "religion"       => !empty($o['religion']) ? $o['religion'] : 'N/A',
                "package"        => !empty($o['package_name']) ? $o['package_name'] : 'N/A',
                "notes"          => $o['request'] ?? 'No notes',
                "selected_theme" => !empty($o['selected_theme']) ? $o['selected_theme'] : 'N/A',
                "downpayment"    => (float)($o['downpayment_amount'] ?? 0),
                "payment_status" => $o['payment_status'] ?? 'Pending',
                "hasFoods"       => $hasFoods,
                "menuItems"      => $menuItems
            ];

            $json_data = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
            $status = strtolower($o['booking_status']);

            $canUndo = false;
            if (
                $o['booking_status'] === 'Rejected' &&
                !empty($o['rejected_at']) &&
                strtotime($o['rejected_at']) >= strtotime('-3 days')
            ) {
                $canUndo = true;
            }
            ?>
            <tr
                data-status="<?php echo $status; ?>"
                data-date="<?php echo htmlspecialchars($o['event_date']); ?>"
                data-search="<?php echo strtolower(htmlspecialchars($o['display_order_id'] . ' ' . $o['full_name'] . ' ' . $o['event_type'])); ?>"
            >
                <td><?php echo htmlspecialchars($o['display_order_id']); ?></td>
                <td><?php echo htmlspecialchars($o['full_name']); ?></td>
                <td><?php echo htmlspecialchars($o['event_type']); ?></td>
                <td><?php echo date("m/d/Y", strtotime($o['event_date'])); ?></td>
                <td>
                    <span class="status-tag <?php echo $status; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </td>
                <td class="action-group">
                    <?php if ($o['booking_status'] === 'Pending'): ?>
                        <a href="update_status.php?id=<?php echo $o['id']; ?>&status=Approved"
                        class="btn-action-icon check"
                        onclick="return openConfirm(event, this.href, 'Approve this booking?')">
                            <i class="fas fa-check"></i>
                        </a>

                        <a href="update_status.php?id=<?php echo $o['id']; ?>&status=Rejected"
                        class="btn-action-icon times"
                        onclick="return openConfirm(event, this.href, 'Reject this booking?')">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>

                    <button class="btn-action-icon eye"
                            onclick='openDetails(<?php echo $json_data; ?>)'>
                        <i class="fas fa-eye"></i>
                    </button>

                    <?php if ($canUndo): ?>
                        <a href="update_status.php?id=<?php echo $o['id']; ?>&status=UndoReject"
                        class="btn-action-icon undo"
                        onclick="return openConfirm(event, this.href, 'Undo this reject order?')">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
        }
    } else {
        echo "<tr><td colspan='6' style='text-align:center;'>No bookings found.</td></tr>";
    }

    echo json_encode([
        'success'   => true,
        'total'     => $total,
        'pending'   => $pending,
        'approved'  => $approved,
        'rejected'  => $rejected,
        'cancelled' => $cancelled,
        'table_html'=> ob_get_clean()
    ]);
    exit();
}

if ($action === 'fetch_reviews_admin') {
    $sql = "SELECT r.*, 
        CONCAT(u.firstname, ' ', u.lastname) AS full_name,
        (SELECT COUNT(*) FROM review_images ri WHERE ri.review_id = r.id) AS photo_count
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        ORDER BY r.created_at DESC, r.id DESC";

    $result = mysqli_query($conn, $sql);

    $data = [];
    $total = 0;
    $visible = 0;
    $hidden = 0;
    $totalRating = 0;

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
            $total++;
            $totalRating += (int)$row['rating'];
            if ($row['status'] === 'Visible') $visible++;
            if ($row['status'] === 'Hidden') $hidden++;
        }
    }

    $reviewDisplayIds = [];
    $idSql = "SELECT id, created_at FROM reviews ORDER BY DATE(created_at) DESC, created_at ASC, id ASC";
    $idRes = mysqli_query($conn, $idSql);
    $generatedReviewIds = [];

    if ($idRes) {
        while ($idRow = mysqli_fetch_assoc($idRes)) {
            $dateKey = !empty($idRow['created_at']) ? date('Ymd', strtotime($idRow['created_at'])) : date('Ymd');
            $baseReviewId = 'REV' . $dateKey;
            if (!isset($generatedReviewIds[$baseReviewId])) {
                $generatedReviewIds[$baseReviewId] = 1;
                $displayReviewId = $baseReviewId;
            } else {
                $generatedReviewIds[$baseReviewId]++;
                $displayReviewId = $baseReviewId . '-' . $generatedReviewIds[$baseReviewId];
            }
            $reviewDisplayIds[(int)$idRow['id']] = $displayReviewId;
        }
    }

    $average = $total > 0 ? round($totalRating / $total, 1) : 0;

    ob_start();
    if (!empty($data)) {
        foreach ($data as $review) {
            $displayReviewId = $reviewDisplayIds[(int)$review['id']] ?? ('REV' . date('Ymd', strtotime($review['created_at'])));
            ?>
            <tr 
                data-status="<?php echo strtolower($review['status']); ?>"
                data-deleted="<?php echo !empty($review['deleted_at']) ? '1' : '0'; ?>"
                data-date="<?php echo !empty($review['created_at']) ? date('Y-m-d', strtotime($review['created_at'])) : ''; ?>"
                data-photos="<?php echo $review['photo_count']; ?>"
            >
                <td><?php echo $displayReviewId; ?></td>
                <td><?php echo htmlspecialchars($review['full_name']); ?></td>
                <td class="rating-stars"><?php echo str_repeat('★', (int)$review['rating']); ?></td>
                <td class="comment-cell"><?php echo htmlspecialchars($review['comment']); ?></td>
                
                <td>
                    <span class="photo-count-tag <?php echo ($review['photo_count'] > 0) ? 'has-photos' : ''; ?>">
                        <i class="fa-solid fa-image"></i> <?php echo (int)$review['photo_count']; ?>
                    </span>
                </td>

                <td>
                    <span class="status-tag <?php echo strtolower($review['status']); ?>">
                        <?php echo strtolower($review['status']); ?>
                    </span>
                </td>
                <td><?php echo date("m/d/Y", strtotime($review['created_at'])); ?></td>
                <td class="action-group">
                    <?php if ($review['status'] === 'Visible'): ?>
                        <a href="toggle_review_status.php?id=<?php echo (int)$review['id']; ?>&status=Hidden"
                           onclick="return openReviewActionConfirm(event, this.href, 'Hide this review?', 'hide')"
                           class="btn-action-text hide-btn">Hide</a>
                    <?php else: ?>
                        <a href="toggle_review_status.php?id=<?php echo (int)$review['id']; ?>&status=Visible"
                           onclick="return openReviewActionConfirm(event, this.href, 'Show this review?', 'show')"
                           class="btn-action-text show-btn">Show</a>
                    <?php endif; ?>

                    <?php 
                    $canUndo = (!empty($review['deleted_at']) && strtotime($review['deleted_at']) >= strtotime('-3 days'));
                    if (empty($review['deleted_at'])): 
                    ?>
                        <a href="delete_review_admin.php?id=<?php echo (int)$review['id']; ?>"
                           onclick="return openReviewActionConfirm(event, this.href, 'Delete this review?', 'delete')"
                           class="btn-action-text delete-btn">Delete</a>
                    <?php endif; ?>

                    <?php if ($canUndo): ?>
                        <a href="undo_review.php?id=<?php echo (int)$review['id']; ?>"
                           onclick="return openReviewActionConfirm(event, this.href, 'Undo this deleted review?', 'undo')"
                           class="btn-action-icon undo">
                            <i class="fas fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>

                    <button type="button" class="btn-action-icon"
                        onclick="showDetails(
                            '<?php echo $displayReviewId; ?>',
                            '<?php echo htmlspecialchars($review['full_name'], ENT_QUOTES); ?>',
                            '<?php echo (int)$review['rating']; ?>',
                            '<?php echo htmlspecialchars($review['status'], ENT_QUOTES); ?>',
                            '<?php echo date('m/d/Y', strtotime($review['created_at'])); ?>',
                            '<?php echo htmlspecialchars($review['comment'], ENT_QUOTES); ?>',
                            '<?php echo $review['id']; ?>'
                        )">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php
        }
    } else {
        echo '<tr><td colspan="8" style="text-align:center;">No reviews found</td></tr>';
    }

    $table_html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'total' => $total,
        'visible' => $visible,
        'hidden' => $hidden,
        'average' => $average,
        'table_html' => $table_html
    ]);
    exit();
}

if ($action === 'get_review_images') {
    $review_id = (int)($_GET['review_id'] ?? 0);

    if ($review_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid review ID.'
        ]);
        exit();
    }

    $sql = "SELECT image_path
            FROM review_images
            WHERE review_id = ?
            ORDER BY id ASC";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $review_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $images = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $images[] = [
            'image_path' => $row['image_path']
        ];
    }

    echo json_encode([
        'success' => true,
        'images' => $images
    ]);
    exit();
}


echo json_encode([
    'success' => false,
    'message' => 'Invalid action.'
]);