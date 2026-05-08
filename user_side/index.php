<?php
session_name('USERSESSID');
session_start();
$pageTitle = "Magarbo Events | Making Every Occasion Grand";
include 'header.php';
require_once '../db_connection.php';

$targetReviewId = null;

if (isset($_GET['t'])) {

    $token = $_GET['t'];

    $stmt = mysqli_prepare($conn,
        "SELECT id FROM reviews WHERE review_token = ?"
    );

    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);

    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        $targetReviewId = $row['id'];
    }
}

$packages = [];

$totalPackageBookings = 0;
$totalPackageStmt = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE package_name IS NOT NULL
       AND package_name != ''
       AND booking_status = 'Approved'"
);

if ($totalPackageStmt && $rowTotal = mysqli_fetch_assoc($totalPackageStmt)) {
    $totalPackageBookings = (int)$rowTotal['total'];
}

$pkgSql = "SELECT * FROM packages WHERE status = 'Active' ORDER BY id DESC LIMIT 3";
$pkgRes = mysqli_query($conn, $pkgSql);

if ($pkgRes && mysqli_num_rows($pkgRes) > 0) {
    while ($pkg = mysqli_fetch_assoc($pkgRes)) {
        $features = [];

        $fStmt = mysqli_prepare($conn, "SELECT feature_text FROM package_features WHERE package_id = ? LIMIT 3");
        mysqli_stmt_bind_param($fStmt, "i", $pkg['id']);
        mysqli_stmt_execute($fStmt);
        $fRes = mysqli_stmt_get_result($fStmt);

        while ($f = mysqli_fetch_assoc($fRes)) {
            $features[] = $f['feature_text'];
        }

        $packageBookings = 0;
$bStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE package_name = ?
       AND booking_status = 'Approved'"
);
        mysqli_stmt_bind_param($bStmt, "s", $pkg['package_name']);
        mysqli_stmt_execute($bStmt);
        $bRes = mysqli_stmt_get_result($bStmt);

        if ($bRow = mysqli_fetch_assoc($bRes)) {
    $packageBookings = (int)$bRow['total'];
}

$percent = ($totalPackageBookings > 0)
    ? round(($packageBookings / $totalPackageBookings) * 100)
    : 0;

        $packages[] = [
            "title" => $pkg['package_name'],
            "price" => "Start from ₱" . number_format($pkg['price_start']),
            "pop" => $percent . "% popularity",
            "img" => !empty($pkg['image_path']) ? $pkg['image_path'] : "images/default-package.jpg",
            "desc" => $pkg['description'] ?? '',
            "features" => $features,
            "link" => "package_details.php?id=" . $pkg['id']
        ];
    }
}
$averageRating = 0;
$totalReviewCount = 0;

$avgSql = "SELECT 
            COUNT(*) AS total_reviews,
            AVG(rating) AS avg_rating
           FROM reviews
           WHERE status = 'Visible'";
$avgRes = mysqli_query($conn, $avgSql);

if ($avgRes && $avgRow = mysqli_fetch_assoc($avgRes)) {
    $totalReviewCount = (int)$avgRow['total_reviews'];
    $averageRating = $totalReviewCount > 0 ? round((float)$avgRow['avg_rating'], 1) : 0;
}

$reviews = [];

$reviewSql = "SELECT r.id, r.user_id, r.rating, r.comment, r.created_at,
                     u.firstname, u.lastname,
                     GROUP_CONCAT(ri.image_path ORDER BY ri.id ASC SEPARATOR '|||') AS images
              FROM reviews r
              JOIN users u ON r.user_id = u.id
              LEFT JOIN review_images ri ON ri.review_id = r.id
              WHERE r.status = 'Visible'
              GROUP BY r.id
              ORDER BY r.id DESC
              LIMIT 3";

$reviewRes = mysqli_query($conn, $reviewSql);

if ($reviewRes && mysqli_num_rows($reviewRes) > 0) {
    while ($row = mysqli_fetch_assoc($reviewRes)) {
        $full_name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        $images = !empty($row['images']) ? explode('|||', $row['images']) : [];

        $full_name = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        if ($full_name === '') {
            $full_name = "User";
        }

        $parts = explode(' ', $full_name);
        $initial = '';
        foreach ($parts as $p) {
            if ($p !== '') {
                $initial .= strtoupper(substr($p, 0, 1));
            }
            if (strlen($initial) >= 2) break;
        }

        if ($initial === '') {
            $initial = strtoupper(substr($full_name, 0, 2));
        }

        $reviews[] = [
            'id'         => $row['id'],
            'user_id'    => $row['user_id'],
            'initial'    => $initial,
            'name'       => $full_name,
            'time'       => date("F j, Y", strtotime($row['created_at'])),
            'comment'    => $row['comment'],
            'rating'     => (int)$row['rating'],
            'images' => $images,
            'image_path' => $row['image_path'] ?? null,
            'review_link'=> "review.php#review-" . $row['id']
        ];
    }
}
?>

<script>
function checkAuth(e) {
    const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

    if (!isLoggedIn) {
        e.preventDefault();
        openLoginRequiredModal();
        return false;
    }

    return true;
}

function openLoginRequiredModal() {
    const modal = document.getElementById('loginRequiredModal');
    if (modal) modal.style.display = 'flex';
}

function closeLoginRequiredModal() {
    const modal = document.getElementById('loginRequiredModal');
    if (modal) modal.style.display = 'none';
}
</script>

<div id="loginRequiredModal" class="confirm-overlay" style="display:none;">
    <div class="confirm-card">
        <div class="confirm-icon-wrap login-mode">
            <i class="fas fa-lock"></i>
        </div>

        <h3>Login Required</h3>
        <p>To make your occasion grand, please log in to your account first.</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeLoginRequiredModal()">Later</button>
            <button type="button" class="confirm-btn login-confirm" onclick="window.location.href='login.php'">Login Now</button>
        </div>
    </div>
</div>

<section id="home" class="hero">
    <div class="hero-content">
        <h1>Magarbo Events</h1>
        <p>Making Every Occasion Grand</p>
        <div class="hero-buttons">
            <a href="choose_service.php" onclick="return checkAuth(event)" class="btn-primary"
                style="min-width: 150px; text-decoration: none; display: inline-block;">Book Now</a>
        </div>
    </div>
</section>

<section id="packages" class="bg-light-gray packages-section">
    <div class="section-container">
        <h2 class="section-title">Event Styling Packages</h2>
        <p class="section-subtitle">Choose from our carefully crafted styling packages designed to make your event
            unforgettable.</p>
        <div class="grid-3">
            <?php foreach ($packages as $p): ?>
                <div class="card package-card">
                    <div class="img-container">
                        <img src="../<?php echo htmlspecialchars($p['img']); ?>" 
                        alt="<?php echo htmlspecialchars($p['title']); ?>" class="card-img">
                        <span class="badge"><?php echo htmlspecialchars($p['pop']); ?></span>
                    </div>
                    <div class="card-content">
                        <div class="card-header">
                            <h3><?php echo htmlspecialchars($p['title']); ?></h3>
                            <p class="price"><?php echo htmlspecialchars($p['price']); ?></p>
                        </div>
                        <p class="desc"><?php echo htmlspecialchars($p['desc']); ?></p>
                        
                        <ul>
                            <?php foreach ($p['features'] as $f): ?>
                                <li><?php echo htmlspecialchars($f); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <a href="<?php echo htmlspecialchars($p['link']); ?>" class="btn-details">View Details</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="steps-section">
    <div class="section-container">
        <h2 class="section-title">Plan Your Event in 4 Simple Steps</h2>
<p class="steps-note">
    From choosing your service to submitting your event details, Magarbo Events makes the booking process simple, clear, and stress-free.
</p>

<div class="steps-grid">
            <div class="step-card">
                <div class="icon-circle"><i class="fa-solid fa-list-check"></i></div>
                <div class="step-number">1</div>
                <h4>Choose Service Type</h4>
                <p>Catering only, styling only, or both</p>
            </div>
            <div class="step-card">
                <div class="icon-circle"><i class="fa-solid fa-box-open"></i></div>
                <div class="step-number">2</div>
                <h4>Select Package/Menu</h4>
                <p>Pick your perfect options</p>
            </div>
            <div class="step-card">
                <div class="icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="step-number">3</div>
                <h4>Event Details</h4>
                <p>Date, time, venue & preferences</p>
            </div>
            <div class="step-card">
                <div class="icon-circle"><i class="fa-solid fa-circle-check"></i></div>
                <div class="step-number">4</div>
                <h4>Review & Submit</h4>
                <p>Confirm and await approval</p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 30px;">
            <a href="choose_service.php" onclick="checkAuth(event)" class="btn-primary"
                style="min-width: 150px; text-decoration: none; display: inline-block;">Book Now</a>
        </div>
    </div>
</section>

<section class="features">
    <div class="section-container">
        <div class="features-header">
            
            <h2>Why Choose Magarbo Events?</h2>
            <p>
                We combine organized planning, quality service, and thoughtful event execution to make every celebration feel smooth and memorable.
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <h4>Hassle-Free Booking</h4>
                <p>A simple booking flow that helps clients submit event details clearly and easily.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-utensils"></i></div>
                <h4>Event Menus</h4>
                <p>Carefully prepared menu options suitable for birthdays, weddings, and special gatherings.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-user-tie"></i></div>
                <h4>Professional Staff</h4>
                <p>A reliable team focused on proper coordination, service, and client assistance.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fa-solid fa-clock"></i></div>
                <h4>On-Time Service</h4>
                <p>We value punctuality and preparation to keep your event organized from start to finish.</p>
            </div>
        </div>
    </div>
</section>

<section id="about" class="about-section">
    <div class="section-container">
        <h2 class="section-title">About Magarbo Events</h2>
        <p class="about-intro-text">
            Magarbo Events is a professional events and catering service dedicated to creating memorable celebrations.
            From intimate gatherings to large-scale events, we focus on delivering quality service, well-prepared food,
            and smooth event execution. Our team works closely with clients to ensure every detail aligns with their
            needs and expectations.
        </p>

        <div class="about-grid">
            <div class="about-text-content">
                <h3>Our Story</h3>
                <p>Magarbo Events started with a passion for bringing people together through food and well-planned
                    events. Over time, the business has grown by building strong relationships with clients and
                    partners, focusing on reliability, consistency, and customer satisfaction.</p>
                <p>Our name "Magarbo" comes from the Filipino word meaning "to make grand" or "to celebrate
                    magnificently." This philosophy drives everything we do.</p>
                <div class="about-features-row">
                    <div class="feature-item"><i class="fa-solid fa-medal"></i>
                        <div><strong>Quality Service</strong><br><small>We focus on delivering quality service and
                                well-prepared food.</small></div>
                    </div>
                    <div class="feature-item"><i class="fa-regular fa-heart"></i>
                        <div><strong>Client-Focused Approach</strong><br><small>We work closely with clients to ensure
                                every detail aligns.</small></div>
                    </div>
                </div>
            </div>
            <div class="about-visual-group">
                <img src="../images/story.jpg" alt="Catering" class="main-story-img">
                <div class="mission-values-grid">
                    <div class="mv-card">
                        <h4>Our Mission</h4>
                        <p>To provide high-quality event and catering services that help clients celebrate moments with
                            ease.</p>
                    </div>
                    <div class="mv-card">
                        <h4>Our Values</h4>
                        <p>To become a trusted and preferred events partner known for excellent service and quality
                            food.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="cta-banner-wrapper">
            <h3>Ready to Make Your Event Grand?</h3>
            <p>Let us bring our passion for excellence to your next celebration.</p>
            <div class="cta-action-btns">
                <a href="choose_service.php" onclick="checkAuth(event)" class="btn-gold-fill"
                    style="text-decoration: none; display: inline-block;">Get Started</a>
                <a href="#contact" class="btn-gray-fill">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<section class="testimonials-section">
    <div class="section-container">
        <h2 class="section-title">What Our Clients Say</h2>

        <p class="testimonial-note">
            Real feedback from our clients who trusted Magarbo Events to make their celebrations memorable and well-organized.
        </p>

        <div class="overall-rating">
            <div class="stars-gold">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i
                    class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
            </div>
            <span class="rating-number">
                <?php echo $averageRating; ?> out of 5 (<?php echo $totalReviewCount; ?> reviews)
            </span>
        </div>
        <div class="reviews-grid">
            <?php foreach ($reviews as $r): 
                $is_my_comment = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $r['user_id']);
            ?>
                <div class="review-card" style="position: relative;">

                
                    <div class="card-top-row">
                        <div class="stars-gold-small">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star" style="color: <?= $i <= $r['rating'] ? '#bf9225' : '#ddd'; ?>"></i>
                            <?php endfor; ?>
                        </div>

                        <?php if (!empty($r['image_path'])): ?>
                    <a href="<a href="review.php" onclick="goToReview(event, <?= (int)$r['id'] ?>)">" style="display:inline-block;">
                        <img src="../<?= htmlspecialchars($r['image_path']) ?>" 
                            style="width:35px; height:35px; object-fit:cover; border-radius:6px; margin-top:6px; opacity:0.9;">
                    </a>
                <?php endif; ?>

                        <div style="display: flex; gap: 10px; align-items: center;">
                            <?php if ($is_my_comment): ?>
                                <a href="delete_review.php?id=<?php echo (int)$r['id']; ?>"
                                onclick="return openDeleteReviewModal(event, this.href)" title="Delete my comment"
                                style="color: #ff4d4d; font-size: 14px;"><i class="fa-solid fa-trash"></i></a>
                            <?php endif; ?>
                            <i class="fa-solid fa-quote-right gold-quote"></i>
                        </div>
                    </div>
                    <p class="review-text">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                    <?php if (!empty($r['images'])): ?>
                        <a href="<?= htmlspecialchars($r['review_link']) ?>" class="review-img-group">

                            <?php 
                            $count = 0;
                            foreach ($r['images'] as $img): 
                                if ($count >= 3) break;
                            ?>
                                <img src="../<?= htmlspecialchars($img) ?>" class="review-thumb">
                            <?php 
                                $count++;
                            endforeach; 
                            ?>

                            <?php if (count($r['images']) > 3): ?>
                                <div class="more-overlay">
                                    +<?= count($r['images']) - 3 ?>
                                </div>
                            <?php endif; ?>

                        </a>
                    <?php endif; ?>
                    <div class="card-footer">
                        <div class="client-profile">
                            <div class="client-avatar"><?php echo htmlspecialchars($r['initial']); ?></div>
                            <span class="client-name"><?php echo htmlspecialchars($r['name']); ?></span>
                        </div>
                        <span class="post-date"><?php echo htmlspecialchars($r['time']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="center-btn-wrapper" style="text-align: center; margin-top: 40px;">
            <a href="review.php" class="btn-see-reviews" style="text-decoration: none; display: inline-block;">See All
                Reviews</a>
        </div>
    </div>
</section>

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

<script>
let deleteReviewUrl = '';

function openDeleteReviewModal(event, url) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    deleteReviewUrl = url;
    document.getElementById('deleteReviewModal').style.display = 'flex';
    return false;
}

function closeDeleteReviewModal() {
    document.getElementById('deleteReviewModal').style.display = 'none';
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

function goToReview(event, id) {
    event.preventDefault();
    sessionStorage.setItem("scrollToReview", id);
    window.location.href = "review.php";
}
</script>

<?php include 'footer.php'; ?>