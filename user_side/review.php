<?php
session_name('USERSESSID');
session_start();
require_once '../db_connection.php';

// Optional login lang: pwede makita reviews kahit guest
$user_id = $_SESSION['user_id'] ?? null;
$isLoggedIn = !empty($user_id);

$account_name = "Guest";

if ($isLoggedIn) {
    $userStmt = mysqli_prepare($conn, "SELECT firstname, lastname FROM users WHERE id = ?");
    mysqli_stmt_bind_param($userStmt, "i", $user_id);
    mysqli_stmt_execute($userStmt);
    $userRes = mysqli_stmt_get_result($userStmt);

    if ($userRow = mysqli_fetch_assoc($userRes)) {
        $account_name = trim(($userRow['firstname'] ?? '') . ' ' . ($userRow['lastname'] ?? ''));

        if ($account_name === '') {
            $account_name = "User";
        }
    }
}


// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!$isLoggedIn) {
        header("Location: login.php");
        exit();
    }
    $rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = trim($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5 && $comment !== '') {
        $insertStmt = mysqli_prepare($conn, "INSERT INTO reviews (user_id, rating, comment) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($insertStmt, "iis", $user_id, $rating, $comment);
        mysqli_stmt_execute($insertStmt);
    }

    header("Location: review.php");
    exit();
}

// Kunin lahat ng visible reviews kasama user info
$reviews = [];
$sql = "SELECT r.id, r.user_id, r.rating, r.comment, r.created_at,
               u.firstname, u.lastname
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'Visible'
        ORDER BY r.id DESC";
$res = mysqli_query($conn, $sql);

if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        $full_name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        if ($full_name === '') {
            $full_name = "User";
        }

        $name_parts = explode(' ', $full_name);
        $initial = '';
        foreach ($name_parts as $part) {
            if ($part !== '') {
                $initial .= strtoupper(substr($part, 0, 1));
            }
            if (strlen($initial) >= 2) break;
        }

        if ($initial === '') {
            $initial = strtoupper(substr($full_name, 0, 2));
        }

        $reviews[] = [
            'id'      => $row['id'],
            'user_id' => $row['user_id'],
            'name'    => $full_name,
            'rating'  => (int)$row['rating'],
            'comment' => $row['comment'],
            'time'    => $row['created_at'],
            'initial' => $initial
        ];
    }
}

// Stats
$total_reviews = count($reviews);
$total_rating = 0;
$rating_count = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

foreach ($reviews as $r) {
    $total_rating += $r['rating'];
    if (isset($rating_count[$r['rating']])) {
        $rating_count[$r['rating']]++;
    }
}

$average_rating = $total_reviews > 0 ? round($total_rating / $total_reviews, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Customer Reviews | Magarbo Events</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="stylesheet" href="review.css?v=<?php echo time(); ?>" />
<style>
.review-modal {
    position: fixed;
    top: 0; left: 0; right:0; bottom:0;
    background: rgba(0,0,0,0.5);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 2000;
}
.review-modal.active { display: flex; }
.review-form {
    background: white;
    padding: 30px;
    border-radius: 20px;
    width: 400px;
    max-width: 90%;
    display: flex;
    flex-direction: column;
    gap: 15px;
}
.review-form h3 { margin: 0; color: var(--gold); }
.review-form textarea, .review-form select {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid rgba(191,146,37,0.2);
    outline: none;
}
.review-form button {
    background: var(--gold);
    color: white;
    border: none;
    padding: 12px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
}
.review-form .close-btn {
    background: #ccc;
    color: #333;
}
.delete-link {
    color: #ff4d4d;
    font-size: 14px;
    text-decoration: none;
}
</style>
</head>
<body>

<header class="fixed-header">
<div class="header-inner">
    <div class="back-home" onclick="location.href='index.php'">
        <i class="fa-solid fa-arrow-left"></i>
        <span>Back to Home</span>
    </div>
    <div class="nav-title">Customer Reviews</div>
    <div style="width: 120px;"></div> 
</div>
</header>

<div class="scroll-content">
<div class="container-60">

<div class="stats-grid">
    <div class="stat-card">
        <div class="val-big"><?= $average_rating ?></div>
        <div class="stars-gold">
            <?php for($i=1; $i<=5; $i++): ?>
                <i class="fa-solid fa-star" style="color:<?= $i <= round($average_rating) ? 'var(--gold)' : '#eee' ?>;"></i>
            <?php endfor; ?>
        </div>
        <div class="val-label">Average Rating</div>
    </div>

    <div class="stat-card">
        <div class="val-big"><?= $total_reviews ?></div>
        <div class="icon-gold"><i class="fa-regular fa-comment-dots"></i></div>
        <div class="val-label">Total Reviews</div>
    </div>

    <div class="stat-card distribution-box">
        <div class="dist-header">Rating Distribution</div>
        <?php foreach($rating_count as $star => $count):
            $percent = $total_reviews ? ($count / $total_reviews) * 100 : 0;
        ?>
        <div class="dist-row">
            <span class="s-text"><?= $star ?> ★</span>
            <div class="p-bg"><div class="p-fill" style="width: <?= $percent ?>%;"></div></div>
            <span class="c-text"><?= $count ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="toolbar">
    <div class="filter-wrapper">
        <i class="fa-solid fa-filter"></i>
        <select id="starFilter" onchange="filterReviews()">
            <option value="all">All Ratings</option>
            <option value="5">5 Stars</option>
            <option value="4">4 Stars</option>
            <option value="3">3 Stars</option>
            <option value="2">2 Stars</option>
            <option value="1">1 Star</option>
        </select>
    </div>
    <button class="btn-gold-write" onclick="<?php echo $isLoggedIn ? 'openReviewForm()' : 'openLoginRequiredModal()'; ?>">
        Write a Review
    </button>
</div>

<div class="reviews-list">
<?php if (!empty($reviews)): ?>
    <?php foreach($reviews as $r):
        $time = !empty($r['time']) ? date("F j, Y", strtotime($r['time'])) : date("F j, Y");
        $is_my_comment = ($user_id == $r['user_id']);
    ?>
    <div class="review-card-item" data-rating="<?= (int)$r['rating'] ?>">
        <div class="item-header">
            <div class="user-meta">
                <div class="avatar-circle"><?= htmlspecialchars($r['initial']) ?></div>
                <div class="user-details">
                    <strong><?= htmlspecialchars($r['name']) ?></strong>
                    <span><?= htmlspecialchars($time) ?></span>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:12px;">
                <?php if ($is_my_comment): ?>
                    <a class="delete-link" href="delete_review.php?id=<?= (int)$r['id'] ?>" onclick="return openDeleteReviewModal(event, this.href)">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                <?php endif; ?>
                <div class="item-stars"><?= str_repeat('⭐', (int)$r['rating']) ?></div>
            </div>
        </div>

        <div class="item-body">
            <i class="fa-solid fa-quote-left quote-gold"></i>
            <p>"<?= htmlspecialchars($r['comment']) ?>"</p>
        </div>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="review-card-item">
        <div class="item-body">
            <p style="margin:0;">No reviews yet.</p>
        </div>
    </div>
<?php endif; ?>
</div>

</div>
</div>

<div class="review-modal" id="reviewModal">
<form class="review-form" method="POST">
    <h3>Write a Review</h3>

    <select name="rating" required>
        <option value="">Select Rating</option>
        <option value="5">5 Stars</option>
        <option value="4">4 Stars</option>
        <option value="3">3 Stars</option>
        <option value="2">2 Stars</option>
        <option value="1">1 Star</option>
    </select>

    <textarea name="comment" placeholder="Write your review..." rows="4" required></textarea>

    <div style="display:flex; gap:10px;">
        <button type="submit" name="submit_review">Submit</button>
        <button type="button" class="close-btn" onclick="closeReviewForm()">Cancel</button>
    </div>
</form>
</div>

<div id="deleteReviewModal" class="confirm-overlay" style="display:none;">
    <div class="confirm-card">
        <div class="confirm-icon-wrap delete-mode">
            <i class="fas fa-trash"></i>
        </div>

        <h3>Delete Review</h3>
        <p>Are you sure you want to delete this review?</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeDeleteReviewModal()">Cancel</button>
            <button type="button" class="confirm-btn delete-confirm" id="confirmDeleteReviewBtn">Delete</button>
        </div>
    </div>
</div>

<div id="loginRequiredModal" class="confirm-overlay" style="display:none;">
    <div class="confirm-card">
        <div class="confirm-icon-wrap login-mode">
            <i class="fas fa-lock"></i>
        </div>

        <h3>Login Required</h3>
        <p>Please login first before writing a review.</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeLoginRequiredModal()">Cancel</button>
            <button type="button" class="confirm-btn login-confirm" onclick="window.location.href='login.php'">Login</button>
        </div>
    </div>
</div>

<script>
let deleteReviewUrl = '';

function openLoginRequiredModal() {
    const modal = document.getElementById('loginRequiredModal');
    if (modal) modal.style.display = 'flex';
}

function closeLoginRequiredModal() {
    const modal = document.getElementById('loginRequiredModal');
    if (modal) modal.style.display = 'none';
}

function filterReviews() {
    const val = document.getElementById('starFilter').value;
    const cards = document.querySelectorAll('.review-card-item');

    cards.forEach(card => {
        card.style.display = (val === 'all' || card.getAttribute('data-rating') === val) ? 'block' : 'none';
    });
}

function openReviewForm() {
    document.getElementById('reviewModal').classList.add('active');
}

function closeReviewForm() {
    document.getElementById('reviewModal').classList.remove('active');
}

function openDeleteReviewModal(event, url) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    deleteReviewUrl = url;

    const modal = document.getElementById('deleteReviewModal');
    if (modal) {
        modal.style.display = 'flex';
    }

    return false;
}

function closeDeleteReviewModal() {
    const modal = document.getElementById('deleteReviewModal');
    if (modal) {
        modal.style.display = 'none';
    }
    deleteReviewUrl = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('confirmDeleteReviewBtn');
    const modal = document.getElementById('deleteReviewModal');

    if (btn) {
        btn.onclick = function () {
            if (deleteReviewUrl) {
                window.location.href = deleteReviewUrl;
            }
        };
    }

    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeDeleteReviewModal();
            }
        });
    }
});
</script>

</body>
</html>