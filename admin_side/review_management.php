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

$idSql = "SELECT id, created_at
          FROM reviews
          ORDER BY DATE(created_at) DESC, created_at ASC, id ASC";

$idRes = mysqli_query($conn, $idSql);

$generatedReviewIds = [];

if ($idRes) {
    while ($idRow = mysqli_fetch_assoc($idRes)) {
        $dateKey = !empty($idRow['created_at'])
            ? date('Ymd', strtotime($idRow['created_at']))
            : date('Ymd');

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

$current_page = 'review_management.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Review Management | Magarbo Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="review_management.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">

<div class="admin-main-container">
    
    <?php include('sidebar.php'); ?>

    <main class="main-content-area">
        <header class="page-header">
            <div class="header-left">
                <h1>Review Management</h1>
                <p>Manage customer reviews and control which feedback appears on the website.</p>
            </div>

            <div class="header-actions">
                <div class="date-filter-wrap">
                    <button type="button" class="btn-date-trigger" id="dateRangeBtn" onclick="toggleDateDropdown()">
                        <i class="far fa-calendar-alt"></i>
                        <span>Date Range</span>
                        <i class="fas fa-chevron-down date-caret"></i>
                    </button>

                    <div class="date-range-dropdown" id="dateRangeDropdown">
                        <div class="date-range-dropdown-header">
                            <h4>Date Range</h4>
                            <button type="button" class="date-range-close" onclick="closeDateDropdown()">&times;</button>
                        </div>

                        <div class="date-range-fields">
                            <div class="date-input-wrap">
                                <label>From</label>
                                <input type="date" id="dateFrom">
                            </div>
                            <div class="date-input-wrap">
                                <label>To</label>
                                <input type="date" id="dateTo">
                            </div>
                        </div>

                        <div class="date-range-actions">
                            <button type="button" class="btn-date-clear" onclick="clearDateRange()">Clear</button>
                            <button type="button" class="btn-date-apply" onclick="applyDateRange()">Apply</button>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="msg-stats-grid">
            <div class="msg-stat-card">
                <span class="stat-tag">Total Reviews</span>
                <span class="stat-num" id="stat-total"><?php echo $total; ?></span>
                <span class="stat-sub">All submitted customer feedback</span>
            </div>

            <div class="msg-stat-card">
                <span class="stat-tag">Visible Reviews</span>
                <span class="stat-num" id="stat-visible"><?php echo $visible; ?></span>
                <span class="stat-sub">Currently shown on the website</span>
            </div>

            <div class="msg-stat-card">
                <span class="stat-tag">Hidden Reviews</span>
                <span class="stat-num" id="stat-hidden"><?php echo $hidden; ?></span>
                <span class="stat-sub">Temporarily hidden from public view</span>
            </div>

            <div class="msg-stat-card">
                <span class="stat-tag">Average Rating</span>
                <span class="stat-num" id="stat-average"><?php echo $average; ?></span>
                <span class="stat-sub">Overall rating from all reviews</span>
            </div>
        </div>

        <div class="reviews-toolbar">
            <div class="filter-tabs-container">
                <button class="filter-btn active" onclick="filterTable('all', this)">All Reviews</button>
                <button class="filter-btn" onclick="filterTable('visible', this)">Visible</button>
                <button class="filter-btn" onclick="filterTable('hidden', this)">Hidden</button>
                <button class="filter-btn" onclick="filterTable('deleted', this)">Deleted</button>
            </div>

            <div class="search-box-wrap">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="reviewSearchInput" placeholder="Search customer, comment, or ID..." onkeyup="applyAllReviewFilters()">
            </div>
        </div>

        <div class="review-table-container">
            <table class="review-table">
                <thead>
                    <tr>
                        <th>Review ID</th>
                        <th>Customer</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Photos</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <?php if (!empty($data)): ?>
                    <?php foreach ($data as $review): ?>
                    <?php
                        $displayReviewId = $reviewDisplayIds[(int)$review['id']] ?? ('REV' . date('Ymd', strtotime($review['created_at'])));
                    ?>
                        <tr 
                            data-status="<?php echo strtolower($review['status']); ?>"
                            data-deleted="<?php echo !empty($review['deleted_at']) ? '1' : '0'; ?>"
                            data-date="<?php echo !empty($review['created_at']) ? date('Y-m-d', strtotime($review['created_at'])) : ''; ?>"
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
                                    class="btn-action-text hide-btn">
                                        Hide
                                    </a>
                                <?php else: ?>
                                    <a href="toggle_review_status.php?id=<?php echo (int)$review['id']; ?>&status=Visible"
                                    onclick="return openReviewActionConfirm(event, this.href, 'Show this review?', 'show')"
                                    class="btn-action-text show-btn">
                                        Show
                                    </a>
                                <?php endif; ?>

                                <?php
                                $canUndo = false;

                                if (!empty($review['deleted_at']) && strtotime($review['deleted_at']) >= strtotime('-3 days')) {
                                    $canUndo = true;
                                }
                                ?>

                                <?php if (empty($review['deleted_at'])): ?>
                                    <a href="delete_review_admin.php?id=<?php echo (int)$review['id']; ?>"
                                        onclick="return openReviewActionConfirm(event, this.href, 'Delete this review?', 'delete')"
                                        class="btn-action-text delete-btn">
                                        Delete
                                    </a>
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
                                        '<?php echo date("m/d/Y", strtotime($review['created_at'])); ?>',
                                        '<?php echo htmlspecialchars($review['comment'], ENT_QUOTES); ?>',
                                        '<?php echo $review['id']; ?>' 
                                    )">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center;">No reviews found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content review-detail-width">
        <button type="button" class="close-btn review-close-btn" onclick="closeModal()">&times;</button>

        <div class="modal-header review-modal-header">
            <div class="header-text">
                <h3>Review Details - <span id="det-id">--</span></h3>
                <p class="sub-header">View complete customer feedback information</p>
            </div>
        </div>

        <div class="modal-body review-modal-body">
            <div class="review-modal-grid">
                <div class="review-box-item">
                    <label>Customer Name</label>
                    <p id="det-name">--</p>
                </div>

                <div class="review-box-item">
                    <label>Rating</label>
                    <p id="det-rating">--</p>
                </div>

                <div class="review-box-item">
                    <label>Status</label>
                    <p id="det-status">--</p>
                </div>

                <div class="review-box-item">
                    <label>Date</label>
                    <p id="det-date">--</p>
                </div>

                <div class="review-box-item review-full">
                    <label>Comment</label>
                    <div class="review-notes-box">
                        <p id="det-comment">--</p>
                    </div>
                </div>

                <div class="review-box-item review-full">
                    <label>Review Photos</label>
                    <div id="det-images-container" style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px;">
                        </div>
                </div>
            </div>

            <button class="review-modal-btn-secondary" onclick="closeModal()">Close Window</button>
        </div>
    </div>
</div>

<div id="reviewActionConfirmModal" class="confirm-overlay">
    <div class="confirm-card">
        <div class="confirm-icon-wrap" id="reviewConfirmIconWrap">
            <i id="reviewConfirmIcon" class="fas fa-eye-slash"></i>
        </div>

        <h3 id="reviewConfirmTitle">Confirm Action</h3>
        <p id="reviewConfirmMessage">Are you sure?</p>

        <div class="confirm-actions">
            <button type="button" class="confirm-btn cancel" onclick="closeReviewActionConfirm()">No</button>
            <button type="button" class="confirm-btn approve" id="reviewConfirmProceedBtn">Yes</button>
        </div>
    </div>
</div>

<div id="imageViewerModal" class="image-viewer-modal">
    <span class="close-image-viewer" onclick="closeImageViewer()">&times;</span>

    <button class="viewer-nav prev" onclick="changeViewerImage(-1)">
        <i class="fa-solid fa-chevron-left"></i>
    </button>

    <img id="viewerImage" class="viewer-image">

    <button class="viewer-nav next" onclick="changeViewerImage(1)">
        <i class="fa-solid fa-chevron-right"></i>
    </button>
</div>

<script>
let reviewConfirmUrl = '';

function openReviewActionConfirm(event, url, message, type = 'hide') {
    if (event) event.preventDefault();

    reviewConfirmUrl = url;

    const modal = document.getElementById('reviewActionConfirmModal');
    const msg = document.getElementById('reviewConfirmMessage');
    const title = document.getElementById('reviewConfirmTitle');
    const iconWrap = document.getElementById('reviewConfirmIconWrap');
    const icon = document.getElementById('reviewConfirmIcon');
    const proceedBtn = document.getElementById('reviewConfirmProceedBtn');

    msg.innerText = message;

    iconWrap.classList.remove('delete-mode', 'hide-mode', 'show-mode', 'undo-mode');
    proceedBtn.classList.remove('delete-confirm', 'hide-confirm', 'show-confirm', 'undo-confirm');

    if (type === 'delete') {
        title.innerText = 'Delete Review';
        icon.className = 'fas fa-trash';
        iconWrap.classList.add('delete-mode');
        proceedBtn.classList.add('delete-confirm');
        proceedBtn.innerText = 'Delete';
    } else if (type === 'undo') {
        title.innerText = 'Undo Delete';
        icon.className = 'fas fa-rotate-left';
        iconWrap.classList.add('undo-mode'); // 🔥 PALIT
        proceedBtn.classList.add('undo-confirm'); // 🔥 PALIT
        proceedBtn.innerText = 'Undo';
    } else if (type === 'show') {
        title.innerText = 'Show Review';
        icon.className = 'fas fa-eye';
        iconWrap.classList.add('show-mode');
        proceedBtn.classList.add('show-confirm');
        proceedBtn.innerText = 'Show';
    } else {
        title.innerText = 'Hide Review';
        icon.className = 'fas fa-eye-slash';
        iconWrap.classList.add('hide-mode');
        proceedBtn.classList.add('hide-confirm');
        proceedBtn.innerText = 'Hide';
    }

    modal.style.display = 'flex';
    return false;
}

function closeReviewActionConfirm() {
    document.getElementById('reviewActionConfirmModal').style.display = 'none';
    reviewConfirmUrl = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const proceedBtn = document.getElementById('reviewConfirmProceedBtn');

    if (proceedBtn) {
        proceedBtn.addEventListener('click', function () {
            if (reviewConfirmUrl) {
                window.location.href = reviewConfirmUrl;
            }
        });
    }
});


let reviewsPolling = null;
let currentReviewFilter = 'all';
let currentDateFrom = '';
let currentDateTo = '';

function filterTable(status, btn = null) {
    currentReviewFilter = status;

    localStorage.setItem('reviewsActiveTab', status);

    if (btn) {
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
    }

    applyAllReviewFilters();
}

function applyAllReviewFilters() {
    const rows = document.querySelectorAll('#tableBody tr');
    const searchValue = (document.getElementById('reviewSearchInput')?.value || '').toLowerCase().trim();

    rows.forEach(row => {
        const isDeleted = row.getAttribute('data-deleted') === '1';
        const rowStatus = (row.getAttribute('data-status') || '').toLowerCase();
        const rowDate = row.getAttribute('data-date') || '';
        const rowText = row.innerText.toLowerCase();

        let showByStatus = false;

        if (currentReviewFilter === 'all') {
            showByStatus = !isDeleted;
        } else if (currentReviewFilter === 'deleted') {
            showByStatus = isDeleted;
        } else {
            showByStatus = !isDeleted && rowStatus === currentReviewFilter;
        }

        let showBySearch = !searchValue || rowText.includes(searchValue);

        let showByDate = true;
        if (currentDateFrom && rowDate && rowDate < currentDateFrom) showByDate = false;
        if (currentDateTo && rowDate && rowDate > currentDateTo) showByDate = false;

        row.style.display = (showByStatus && showBySearch && showByDate) ? '' : 'none';
    });
}

function toggleDateDropdown() {
    const dropdown = document.getElementById('dateRangeDropdown');
    const btn = document.getElementById('dateRangeBtn');
    dropdown.classList.toggle('show');
    btn.classList.toggle('active');
}

function closeDateDropdown() {
    document.getElementById('dateRangeDropdown').classList.remove('show');
    document.getElementById('dateRangeBtn').classList.remove('active');
}

function applyDateRange() {
    currentDateFrom = document.getElementById('dateFrom').value;
    currentDateTo = document.getElementById('dateTo').value;
    applyAllReviewFilters();
    closeDateDropdown();
}

function clearDateRange() {
    document.getElementById('dateFrom').value = '';
    document.getElementById('dateTo').value = '';
    currentDateFrom = '';
    currentDateTo = '';
    applyAllReviewFilters();
    closeDateDropdown();
}

async function refreshAdminReviews() {
    try {
        const res = await fetch('ajax.php?action=fetch_reviews_admin', {
            cache: 'no-store'
        });
        const data = await res.json();

        if (data.success) {
            const tableBody = document.getElementById('tableBody');
            const statTotal = document.getElementById('stat-total');
            const statVisible = document.getElementById('stat-visible');
            const statHidden = document.getElementById('stat-hidden');
            const statAverage = document.getElementById('stat-average');

            if (tableBody) tableBody.innerHTML = data.table_html;
            if (statTotal) statTotal.textContent = data.total;
            if (statVisible) statVisible.textContent = data.visible;
            if (statHidden) statHidden.textContent = data.hidden;
            if (statAverage) statAverage.textContent = data.average;

            applyAllReviewFilters();
        }
    } catch (err) {
        console.error('Reviews refresh failed:', err);
    }
}

function startReviewsPolling() {
    if (reviewsPolling) clearInterval(reviewsPolling);
    reviewsPolling = setInterval(refreshAdminReviews, 3000);
}



let currentGalleryImages = [];
let currentImageIndex = 0;

function showDetails(id, name, rating, status, date, comment, db_id) {
    document.getElementById('det-id').innerText = id;
    document.getElementById('det-name').innerText = name;
    document.getElementById('det-rating').innerText = rating + ' star(s)';
    document.getElementById('det-status').innerText = status;
    document.getElementById('det-date').innerText = date;
    document.getElementById('det-comment').innerText = comment;

    const container = document.getElementById('det-images-container');
    container.innerHTML = '<p style="color:gray;"><i class="fas fa-spinner fa-spin"></i> Loading photos...</p>';

    fetch('ajax.php?action=get_review_images&review_id=' + db_id)
        .then(res => res.json())
        .then(data => {
            container.innerHTML = '';
            currentGalleryImages = [];

            if (data.success && data.images && data.images.length > 0) {
                data.images.forEach((img, index) => {
                    const fullPath = '../' + img.image_path;
                    currentGalleryImages.push(fullPath);

                    const imgTag = document.createElement('img');
                    imgTag.src = fullPath;
                    imgTag.style.cssText = `
                        width: 100px;
                        height: 100px;
                        object-fit: cover;
                        border-radius: 8px;
                        border: 1px solid #ddd;
                        cursor: pointer;
                    `;

                    imgTag.onclick = () => openImageViewer(index);

                    container.appendChild(imgTag);
                });
            } else {
                container.innerHTML = '<p style="color:gray; font-style:italic;">No photos uploaded for this review.</p>';
            }
        });

    document.getElementById('viewModal').style.display = 'flex';
}

function openImageViewer(index) {
    currentImageIndex = index;
    document.getElementById('viewerImage').src =
        currentGalleryImages[currentImageIndex];
    document.getElementById('imageViewerModal').style.display = 'flex';
}

function closeImageViewer() {
    document.getElementById('imageViewerModal').style.display = 'none';
}

function changeViewerImage(direction) {
    currentImageIndex += direction;

    if (currentImageIndex < 0) {
        currentImageIndex = currentGalleryImages.length - 1;
    }

    if (currentImageIndex >= currentGalleryImages.length) {
        currentImageIndex = 0;
    }

    document.getElementById('viewerImage').src =
        currentGalleryImages[currentImageIndex];
}

document.getElementById('imageViewerModal').addEventListener('click', function(e) {
    if (e.target.id === 'imageViewerModal') {
        closeImageViewer();
    }
});

function closeModal() {
    document.getElementById('viewModal').style.display = "none";
}


window.onload = function() {
    refreshAdminReviews();

    const savedReviewTab = localStorage.getItem('reviewsActiveTab') || 'all';

    const savedReviewBtn = document.querySelector(
        `.filter-btn[onclick*="'${savedReviewTab}'"]`
    );

    if (savedReviewBtn) {
        filterTable(savedReviewTab, savedReviewBtn);
    } else {
        filterTable('all', document.querySelector('.filter-btn'));
    }

    startReviewsPolling();

    const links = document.querySelectorAll('.menu-link');
    links.forEach(link => {
        link.classList.remove('active');
        if (link.innerText.trim().includes("Review")) {
            link.classList.add('active');
        }
    });
};
</script>

</body>
</html>