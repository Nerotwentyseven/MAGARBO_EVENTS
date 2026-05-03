<?php
session_name('ADMINSESSID');
require_once 'admin_auth.php';
require_once '../db_connection.php';
$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

if (isset($_GET['user_id'])) {
    $selected_user_id = (int) $_GET['user_id'];

    $mark_read = "UPDATE chat_messages 
                  SET is_read = 1 
                  WHERE user_id = ? AND sender = 'user'";
    $stmt_read = mysqli_prepare($conn, $mark_read);

    if (!$stmt_read) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt_read, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_read);
} else {
    $selected_user_id = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_text = trim($_POST['reply_text'] ?? '');
    $reply_user_id = (int) ($_POST['user_id'] ?? 0);

    if ($reply_user_id > 0 && $reply_text !== '') {
        $insert_reply = "INSERT INTO chat_messages (user_id, sender, message, is_read) 
            VALUES (?, 'admin', ?, 0)";
        $stmt_reply = mysqli_prepare($conn, $insert_reply);

        if (!$stmt_reply) {
            die("Prepare failed: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt_reply, "is", $reply_user_id, $reply_text);

        if (!mysqli_stmt_execute($stmt_reply)) {
            die("Execute failed: " . mysqli_stmt_error($stmt_reply));
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
    }

    header("Location: messages.php?user_id=" . $reply_user_id);
    exit();
}

$total_messages = 0;
$unread_messages = 0;
$read_messages = 0;

$total_messages_q = "SELECT COUNT(*) AS total FROM chat_messages";
$total_messages_r = mysqli_query($conn, $total_messages_q);
if ($total_messages_r) {
    $total_messages = mysqli_fetch_assoc($total_messages_r)['total'] ?? 0;
}

$unread_q = "SELECT COUNT(*) AS total FROM chat_messages WHERE sender = 'user' AND is_read = 0";
$unread_r = mysqli_query($conn, $unread_q);
if ($unread_r) {
    $unread_messages = mysqli_fetch_assoc($unread_r)['total'] ?? 0;
}

$read_q = "SELECT COUNT(*) AS total FROM chat_messages WHERE sender = 'user' AND is_read = 1";
$read_r = mysqli_query($conn, $read_q);
if ($read_r) {
    $read_messages = mysqli_fetch_assoc($read_r)['total'] ?? 0;
}

$inbox_sql = "
    SELECT 
        m.user_id,
        CONCAT(u.firstname, ' ', u.lastname) AS full_name,
        u.email,
        u.last_active,
        m.message,
        m.created_at,
        m.is_read,
        m.sender,
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

$chat_result = false;
$selected_user = null;

if ($selected_user_id > 0) {
    $user_sql = "SELECT id, firstname, lastname, email, last_active FROM users WHERE id = ?";
    $stmt_user = mysqli_prepare($conn, $user_sql);

    if (!$stmt_user) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt_user, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_user);
    $user_result = mysqli_stmt_get_result($stmt_user);
    $selected_user = mysqli_fetch_assoc($user_result);

    $chat_sql = "SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC, id ASC";
    $stmt_chat = mysqli_prepare($conn, $chat_sql);

    if (!$stmt_chat) {
        die("Prepare failed: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt_chat, "i", $selected_user_id);
    mysqli_stmt_execute($stmt_chat);
    $chat_result = mysqli_stmt_get_result($stmt_chat);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1.0" />
    <title>Messages | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="messages.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="admin-main-container">
    <?php include('sidebar.php'); ?>

    <main class="main-content-area">
        <header class="page-title-section">
            <h1>Messages</h1>
            <p>View and respond to customer inquiries</p>
        </header>

        <div class="msg-stats-grid">
            <div class="msg-stat-card">
                <span class="stat-tag">Total Messages</span>
                <span class="stat-num"><?php echo $total_messages; ?></span>
            </div>
            <div class="msg-stat-card">
                <span class="stat-tag" style="color:#ef4444;">Unread Messages</span>
                <span class="stat-num" style="color:#ef4444;"><?php echo $unread_messages; ?></span>
            </div>
            <div class="msg-stat-card">
                <span class="stat-tag">Read Messages</span>
                <span class="stat-num"><?php echo $read_messages; ?></span>
            </div>
        </div>

        <div class="messaging-main-grid">
            <div class="inbox-container-card">
                <div class="inbox-search-header">
                    <h3 style="margin-bottom:15px;">Inbox</h3>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" id="msgFilter" placeholder="Search name or message..." onkeyup="filterMessages()">
                    </div>
                </div>

                <div class="inbox-scroll-list" id="inboxList">
                    <?php if ($inbox_result && mysqli_num_rows($inbox_result) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($inbox_result)): ?>
                            <a href="messages.php?user_id=<?php echo $row['user_id']; ?>" 
                            class="inbox-item <?php echo ($selected_user_id == $row['user_id']) ? 'active-msg' : ''; ?>"
                            style="
                                display:block; 
                                text-decoration:none; 
                                color:inherit; 
                                border-left: 4px solid <?php echo ($row['unread_count'] > 0 && $row['user_id'] != $selected_user_id) ? '#bf9225' : 'transparent'; ?>;
                                background: <?php echo ($row['unread_count'] > 0 && $row['user_id'] != $selected_user_id) ? '#fff8e6' : '#fff'; ?>;
                                padding: 14px 16px;
                            ">

                                <div class="inbox-item-top" style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                                    <div>
                                        <div class="sender-name">
                                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                        </div>

                                        <?php $is_online = !empty($row['last_active']) && (strtotime($row['last_active']) > (time() - 25)); ?>
                                        <div style="font-size:12px; margin-top:3px; color: <?php echo $is_online ? 'green' : '#999'; ?>; font-weight:600;">
                                            ● <?php echo $is_online ? 'Online' : 'Offline'; ?>
                                        </div>
                                    </div>

                                    <?php if ($row['unread_count'] > 0 && $row['user_id'] != $selected_user_id): ?>
                                        <div style="background:#bf9225; color:#fff; min-width:24px; height:24px; border-radius:999px; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; padding:0 8px;">
                                            <?php echo $row['unread_count']; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="inbox-item-subj" style="margin-top:10px; font-weight:600;">
                                    <?php echo ($row['unread_count'] > 0 && $row['user_id'] != $selected_user_id) ? $row['unread_count'] . ' new message' . ($row['unread_count'] > 1 ? 's' : '') : 'Conversation'; ?>
                                </div>

                                <p class="inbox-item-preview" style="margin-top:6px;">
                                    <?php echo htmlspecialchars(mb_strimwidth($row['message'], 0, 55, '...')); ?>
                                </p>

                                <div style="font-size:12px; color:#888; margin-top:8px;">
                                    <?php echo date('M d, Y g:i A', strtotime($row['created_at'])); ?>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p style="padding:20px; color:#888;">No messages found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="message-viewer-card" id="messageViewer" style="display:flex; flex-direction:column;">
                <?php if ($selected_user && $chat_result): ?>
                    <div class="chat-viewer-header">
                        <h2 style="font-size:24px; font-weight:700; margin:0;">
                            <?php echo htmlspecialchars($selected_user['firstname'] . ' ' . $selected_user['lastname']); ?>
                        </h2>
                        <div class="chat-viewer-email">
                            <?php echo htmlspecialchars($selected_user['email']); ?>
                        </div>
                        <?php
                        $selected_is_online = !empty($selected_user['last_active']) && (strtotime($selected_user['last_active']) > (time() - 25));
                        ?>
                        <div id="selectedUserStatus"
                            style="font-size:13px; color: <?php echo $selected_is_online ? 'green' : '#999'; ?>; font-weight:600;">
                            ● <?php echo $selected_is_online ? 'Online' : 'Offline'; ?>
                        </div>
                    </div>

                    <div class="chat-body-scroll" id="chatThread">
                        <?php
                        $chat_rows = [];
                        if ($chat_result) {
                            while ($row = mysqli_fetch_assoc($chat_result)) {
                                $chat_rows[] = $row;
                            }
                        }
                        $last_admin_index = -1;
                        foreach ($chat_rows as $i => $row) {
                            if ($row['sender'] === 'admin') {
                                $last_admin_index = $i;
                            }
                        }
                        ?>

                        <?php foreach ($chat_rows as $index => $msg): ?>
                            <div class="message-bubble <?php echo ($msg['sender'] === 'admin') ? 'msg-right' : 'msg-left'; ?>" style="margin-bottom:12px;">
                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>

                                <div style="font-size:11px; margin-top:6px; opacity:.7;">
                                    <?php echo date('M d, Y g:i A', strtotime($msg['created_at'])); ?>
                                </div>

                                <?php if ($msg['sender'] === 'admin' && $index === $last_admin_index): ?>
                                    <div style="font-size:11px; margin-top:4px; text-align:right; opacity:.8;">
                                        <?php echo ((int)$msg['is_read'] === 1) ? 'Seen' : 'Sent'; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <form id="replyForm" method="POST" class="reply-form">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>">
                        <div class="reply-form-row">
                            <textarea id="replyText" name="reply_text" placeholder="Type your response here..." required></textarea>
                           <button type="submit" name="send_reply" class="reply-send-btn">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="empty-view-state">
                        <i class="far fa-envelope-open empty-view-icon"></i>
                        <p class="empty-view-text">Select a message to view details</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function filterMessages() {
    let input = document.getElementById('msgFilter').value.toLowerCase();
    let items = document.getElementsByClassName('inbox-item');
    for (let i = 0; i < items.length; i++) {
        let text = items[i].innerText.toLowerCase();
        items[i].style.display = text.includes(input) ? "block" : "none";
    }
}

let selectedUserId = <?php echo (int)$selected_user_id; ?>;
let heartbeatPolling = null;
let isSendingReply = false;
let adminShouldForceScroll = false;
let adminIsReadingOldMessages = false;

function isThreadNearBottom(el, threshold = 100) {
    if (!el) return true;
    return (el.scrollHeight - el.scrollTop - el.clientHeight) <= threshold;
}

function updateAdminReadingState() {
    const thread = document.getElementById('chatThread');
    if (!thread) return;

    adminIsReadingOldMessages = !isThreadNearBottom(thread, 120);
}

function scrollThreadToBottom(force = false) {
    const thread = document.getElementById('chatThread');
    if (!thread) return;

    if (force || !adminIsReadingOldMessages) {
        thread.scrollTop = thread.scrollHeight;
        adminIsReadingOldMessages = false;
    }
}

function pingAdminHeartbeat() {
    fetch('ajax.php?action=heartbeat', {
        method: 'GET',
        cache: 'no-store',
        credentials: 'same-origin'
    }).catch(err => console.log('Admin heartbeat failed:', err));
}

function handleAdminPresenceReturn() {
    pingAdminHeartbeat();
    refreshInbox();
}

async function refreshInbox() {
    try {
        const res = await fetch(`ajax.php?action=fetch_inbox&selected_user_id=${selectedUserId}`, {
            cache: 'no-store'
        });
        const data = await res.json();
        if (data.success) {
            document.getElementById('inboxList').innerHTML = data.html;
            filterMessages();

            const activeInboxItem = document.querySelector(`.inbox-item[data-user-id="${selectedUserId}"]`);
            const selectedUserStatus = document.getElementById('selectedUserStatus');

            if (activeInboxItem && selectedUserStatus) {
                const isOnline = activeInboxItem.getAttribute('data-online') === '1';
                selectedUserStatus.textContent = isOnline ? '● Online' : '● Offline';
                selectedUserStatus.style.color = isOnline ? 'green' : '#999';
            }
        }
    } catch (err) {
        console.error('Inbox refresh failed:', err);
    }
}

async function refreshChat() {
    if (!selectedUserId) return;

    try {
        const chatThread = document.getElementById('chatThread');
        const wasNearBottom = isThreadNearBottom(chatThread, 120);

        const res = await fetch(`ajax.php?action=fetch_chat&user_id=${selectedUserId}`, {
            cache: 'no-store'
        });
        const data = await res.json();

        if (data.success && chatThread) {
            const oldScrollHeight = chatThread.scrollHeight;
            const oldScrollTop = chatThread.scrollTop;

            chatThread.innerHTML = data.html;

            

            if (adminShouldForceScroll) {
                scrollThreadToBottom(true);
                adminShouldForceScroll = false;
            } else if (wasNearBottom && !adminIsReadingOldMessages) {
                scrollThreadToBottom();
            } else {
                const newScrollHeight = chatThread.scrollHeight;
                chatThread.scrollTop = oldScrollTop + (newScrollHeight - oldScrollHeight);
            }
        }

        refreshInbox();
    } catch (err) {
        console.error('Chat refresh failed:', err);
    }
}

async function sendReplyAjax(e) {
    e.preventDefault();
    if (isSendingReply || !selectedUserId) return;

    const replyText = document.getElementById('replyText');
    const message = replyText.value.trim();
    if (!message) return;

    isSendingReply = true;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('user_id', selectedUserId);
    formData.append('reply_text', message);

    try {
        const res = await fetch('ajax.php', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();

        if (data.success) {
            replyText.value = '';
            adminShouldForceScroll = true;
            await refreshChat();
        } else {
            alert(data.message || 'Failed to send reply.');
        }
    } catch (err) {
        console.error('Send failed:', err);
        alert('Failed to send reply.');
    } finally {
        isSendingReply = false;
    }
}

window.onload = function() {
    scrollThreadToBottom();

    const chatThread = document.getElementById('chatThread');
    if (chatThread) {
        chatThread.addEventListener('scroll', updateAdminReadingState, { passive: true });
        updateAdminReadingState();
    }

    const links = document.querySelectorAll('.menu-link');
    links.forEach(link => {
        if (link.innerText.includes("Messages")) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });

    const replyForm = document.getElementById('replyForm');
    if (replyForm) {
        replyForm.addEventListener('submit', sendReplyAjax);
    }

    const replyText = document.getElementById('replyText');
    if (replyText) {
        replyText.addEventListener('scroll', function(e) {
            e.stopPropagation();
        });

        replyText.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (replyForm) {
                    replyForm.requestSubmit();
                }
            }
        });
    }

        pingAdminHeartbeat();
    heartbeatPolling = setInterval(pingAdminHeartbeat, 10000);

    refreshInbox();

    if (selectedUserId) {
        refreshChat();
        chatPolling = setInterval(refreshChat, 2500);
        inboxPolling = setInterval(refreshInbox, 2500);
    } else {
        inboxPolling = setInterval(refreshInbox, 2500);
    }
};

window.addEventListener('focus', handleAdminPresenceReturn);
window.addEventListener('pageshow', handleAdminPresenceReturn);

document.addEventListener('visibilitychange', function () {
    if (!document.hidden) {
        handleAdminPresenceReturn();
    }
});
</script>

</body>
</html>