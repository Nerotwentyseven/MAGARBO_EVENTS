<?php
session_name('USERSESSID');
session_start();
$pageTitle = "Event Gallery | Magarbo Events";
require_once '../db_connection.php';

$selectedCategory = $_GET['category'] ?? 'All Photos';
$selectedCategorySafe = mysqli_real_escape_string($conn, $selectedCategory);

$filters = [];

$totalPhotosQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_photos WHERE file_type = 'image'");
$totalPhotosRow = $totalPhotosQuery ? mysqli_fetch_assoc($totalPhotosQuery) : ['total' => 0];
$totalPhotos = (int)($totalPhotosRow['total'] ?? 0);

$totalVideosQuery = mysqli_query($conn, "SELECT COUNT(*) AS total FROM gallery_photos WHERE file_type = 'video'");
$totalVideosRow = $totalVideosQuery ? mysqli_fetch_assoc($totalVideosQuery) : ['total' => 0];
$totalVideos = (int)($totalVideosRow['total'] ?? 0);

$filters[] = [
    'label' => 'All Photos',
    'count' => $totalPhotos,
    'active' => ($selectedCategory === 'All Photos')
];

$filters[] = [
    'label' => 'All Videos',
    'count' => $totalVideos,
    'active' => ($selectedCategory === 'All Videos')
];

$categoryQuery = mysqli_query($conn, "
    SELECT ga.category, COUNT(gp.id) AS total
    FROM gallery_albums ga
    LEFT JOIN gallery_photos gp ON ga.id = gp.album_id
    GROUP BY ga.category
    ORDER BY ga.category ASC
");

if ($categoryQuery) {
    while ($row = mysqli_fetch_assoc($categoryQuery)) {
        $filters[] = [
            'label' => $row['category'],
            'count' => (int)$row['total'],
            'active' => ($selectedCategory === $row['category'])
        ];
    }
}

if ($selectedCategory === 'All Photos') {
    $imagesQuery = mysqli_query($conn, "
        SELECT 
            gp.id,
            gp.photo_path,
            gp.original_name,
            gp.file_type,
            ga.album_name,
            ga.category,
            ga.event_date
        FROM gallery_photos gp
        JOIN gallery_albums ga ON gp.album_id = ga.id
        WHERE gp.file_type = 'image'
        ORDER BY gp.id DESC
    ");
} elseif ($selectedCategory === 'All Videos') {
    $imagesQuery = mysqli_query($conn, "
        SELECT 
            gp.id,
            gp.photo_path,
            gp.original_name,
            gp.file_type,
            ga.album_name,
            ga.category,
            ga.event_date
        FROM gallery_photos gp
        JOIN gallery_albums ga ON gp.album_id = ga.id
        WHERE gp.file_type = 'video'
        ORDER BY gp.id DESC
    ");
} else {
    $imagesQuery = mysqli_query($conn, "
        SELECT 
            gp.id,
            gp.photo_path,
            gp.original_name,
            gp.file_type,
            ga.album_name,
            ga.category,
            ga.event_date
        FROM gallery_photos gp
        JOIN gallery_albums ga ON gp.album_id = ga.id
        WHERE ga.category = '$selectedCategorySafe'
        ORDER BY gp.id DESC
    ");
}

$images = [];
if ($imagesQuery) {
    while ($row = mysqli_fetch_assoc($imagesQuery)) {
        $images[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="gallery.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
</head>
<body>

    <?php include 'header.php'; ?>

    <section class="hero-section">
        <div class="hero-content">
            <h1><i class="fa-regular fa-images"></i> Event Gallery</h1>
            <p>Browse through our collection of beautiful events and celebrations. See how Magarbo Events brings unforgettable moments to life.</p>
        </div>
    </section>

    <main class="main-container">
        <aside class="sidebar">
            <div class="filter-card">
                <ul>
                    <?php foreach ($filters as $filter): ?>
                        <li class="filter-item <?php echo $filter['active'] ? 'active' : ''; ?>">
                            <a href="?category=<?php echo urlencode($filter['label']); ?>">
                                <span><?php echo htmlspecialchars($filter['label']); ?></span>
                                <span class="count"><?php echo (int)$filter['count']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </aside>

        <section class="gallery-scroll-area">
            <div class="gallery-grid">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $img): ?>
                        <div class="gallery-item"
                            onclick="openPreview('../<?php echo htmlspecialchars($img['photo_path']); ?>', '<?php echo htmlspecialchars($img['file_type'] ?? 'image'); ?>')">

                            <?php if (($img['file_type'] ?? 'image') === 'video'): ?>
                                <video class="gallery-video" muted playsinline>
                                    <source src="../<?php echo htmlspecialchars($img['photo_path']); ?>" type="video/mp4">
                                </video>
                                <div class="video-badge"><i class="fa-solid fa-play"></i></div>
                            <?php else: ?>
                                <img 
                                    src="../<?php echo htmlspecialchars($img['photo_path']); ?>" 
                                    alt="<?php echo htmlspecialchars($img['original_name'] ?: $img['album_name']); ?>"
                                >
                            <?php endif; ?>

                            <div class="gallery-meta">
                                <h4><?php echo htmlspecialchars($img['album_name']); ?></h4>
                                <p>
                                    <?php echo htmlspecialchars($img['category']); ?>
                                    <?php if (!empty($img['event_date'])): ?>
                                        • <?php echo htmlspecialchars($img['event_date']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="gallery-empty">
                        No gallery media available yet.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="imagePreviewModal" class="image-preview-modal">

        <span class="close-preview" onclick="closePreview()">&times;</span>

        <button class="viewer-nav prev" onclick="changePreview(-1)">
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <div id="previewContent"></div>

        <button class="viewer-nav next" onclick="changePreview(1)">
            <i class="fa-solid fa-chevron-right"></i>
        </button>

    </div>

    <script>
        let galleryImages = [];
        let currentIndex = 0;

        function openPreview(src, type = 'image') {

            const items = document.querySelectorAll('.gallery-item img, .gallery-item video');

            galleryImages = [];

            items.forEach(el => {

                if (el.tagName === 'IMG') {
                    galleryImages.push({
                        src: new URL(el.src, window.location.href).href,
                        type: 'image'
                    });
                }

                if (el.tagName === 'VIDEO') {
                    const source = el.querySelector('source');
                    if (source) {
                        galleryImages.push({
                            src: new URL(source.src, window.location.href).href,
                            type: 'video'
                        });
                    }
                }
            });

            const normalizedSrc = new URL(src, window.location.href).href;

            currentIndex = galleryImages.findIndex(i => i.src === normalizedSrc);

            if (currentIndex === -1) {
                currentIndex = 0;
            }
            
            renderPreview();

            document.getElementById('imagePreviewModal').style.display = 'flex';
        }

        function renderPreview() {
            const previewContent = document.getElementById('previewContent');
            const item = galleryImages[currentIndex];

            if (!item) return;

            if (item.type === 'video') {
                previewContent.innerHTML = `
                    <video controls playsinline preload="metadata"
                        style="max-width:90vw; max-height:90vh; border-radius:14px;">
                        <source src="${item.src}" type="video/mp4">
                    </video>
                `;
            } else {
                previewContent.innerHTML = `
                    <img src="${item.src}"
                        style="max-width:90vw; max-height:90vh; border-radius:14px;">
                `;
            }
        }

        function changePreview(direction) {

            currentIndex += direction;

            if (currentIndex < 0) {
                currentIndex = galleryImages.length - 1;
            }

            if (currentIndex >= galleryImages.length) {
                currentIndex = 0;
            }

            renderPreview();
        }

        function closePreview() {
            document.getElementById('imagePreviewModal').style.display = 'none';
            document.getElementById('previewContent').innerHTML = '';
        }

        document.getElementById('imagePreviewModal').addEventListener('click', function (e) {
            if (e.target === this) {
                closePreview();
            }
        });

        const isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

        function pingUserHeartbeat() {
            if (!isLoggedIn) return;

            fetch('ajax.php?action=heartbeat', {
                method: 'GET',
                cache: 'no-store',
                credentials: 'same-origin'
            }).catch(error => console.log('Heartbeat fetch error:', error));
        }

        pingUserHeartbeat();
        setInterval(pingUserHeartbeat, 10000);

        window.addEventListener('focus', pingUserHeartbeat);
        window.addEventListener('pageshow', pingUserHeartbeat);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                pingUserHeartbeat();
            }
        });
    </script>
</body>
</html>