<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('USERSESSID');
    session_start();
}
?>
<script>
window.isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
let heartbeatInterval = null;

function pingUserHeartbeat() {
    if (!isLoggedIn) return;

    fetch('ajax.php?action=heartbeat', {
        method: 'GET',
        cache: 'no-store',
        credentials: 'same-origin'
    }).catch(error => console.log('Heartbeat fetch error:', error));
}

function startHeartbeat() {
    if (!isLoggedIn) return;
    if (heartbeatInterval) return;

    pingUserHeartbeat();
    heartbeatInterval = setInterval(pingUserHeartbeat, 10000);
}

function stopHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }
}

window.addEventListener('load', function () {
    if (!document.hidden) {
        startHeartbeat();
    }
});

window.addEventListener('focus', function () {
    if (!document.hidden) {
        startHeartbeat();
    }
});

window.addEventListener('pageshow', function () {
    if (!document.hidden) {
        startHeartbeat();
    }
});

document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
        stopHeartbeat();
    } else {
        startHeartbeat();
    }
});

window.addEventListener('pagehide', stopHeartbeat);
window.addEventListener('beforeunload', stopHeartbeat);
</script>