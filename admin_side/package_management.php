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

$packages = [];
$totalPackages = 0;
$popularPackages = 0;
$totalAvg = 0;

$sql = "SELECT * FROM packages ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    while ($pkg = mysqli_fetch_assoc($result)) {
        $pkg_id = $pkg['id'];

        $features = [];
        $includes = [];

        $fStmt = mysqli_prepare($conn, "SELECT feature_text FROM package_features WHERE package_id = ?");
        mysqli_stmt_bind_param($fStmt, "i", $pkg_id);
        mysqli_stmt_execute($fStmt);
        $fRes = mysqli_stmt_get_result($fStmt);
        while ($f = mysqli_fetch_assoc($fRes)) {
            $features[] = $f['feature_text'];
        }

        $iStmt = mysqli_prepare($conn, "SELECT include_text FROM package_includes WHERE package_id = ?");
        mysqli_stmt_bind_param($iStmt, "i", $pkg_id);
        mysqli_stmt_execute($iStmt);
        $iRes = mysqli_stmt_get_result($iStmt);
        while ($i = mysqli_fetch_assoc($iRes)) {
            $includes[] = $i['include_text'];
        }

        // AUTO POPULARITY BASED ON BOOKINGS
$totalBookings = 0;
$totalStmt = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE package_name IS NOT NULL
       AND package_name != ''
       AND booking_status = 'Approved'"
);
if ($rowTotal = mysqli_fetch_assoc($totalStmt)) {
    $totalBookings = (int)$rowTotal['total'];
}

$packageBookings = 0;
$pStmt = mysqli_prepare(
    $conn,
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE package_name = ?
       AND booking_status = 'Approved'"
);
mysqli_stmt_bind_param($pStmt, "s", $pkg['package_name']);
mysqli_stmt_execute($pStmt);
$pRes = mysqli_stmt_get_result($pStmt);

if ($pRow = mysqli_fetch_assoc($pRes)) {
    $packageBookings = (int)$pRow['total'];
}

$percent = ($totalBookings > 0) 
    ? round(($packageBookings / $totalBookings) * 100) 
    : 0;

$packages[] = [
    "id" => $pkg['id'],
    "name" => $pkg['package_name'],
    "popularity" => $percent . "%",
    "popularity_num" => $percent,
    "price_start" => (float)$pkg['price_start'],
    "img" => !empty($pkg['image_path']) ? "../" . $pkg['image_path'] : "https://via.placeholder.com/600x350?text=No+Image",
    "desc" => $pkg['description'] ?? '',
    "features" => $features,
    "includes" => $includes,
    "status" => $pkg['status']
];

        $totalPackages++;
        if ($percent > 0) $popularPackages++;
        $totalAvg += (float)$pkg['price_start'];
    }
}

$avgPrice = $totalPackages > 0 ? $totalAvg / $totalPackages : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Package Management | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="package_management.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">
    <div class="ADMIN-PACKAGES">
        <?php include('sidebar.php'); ?>

        <div class="frame">
            <div class="header-container">
                <div>
                    <h1 class="div">Package Management</h1>
                    <p class="text-wrapper">Create and manage event packages for your customers</p>
                </div>
                <button class="group-wrapper" onclick="mgOpenModal('create')">
                    <div class="group-3">
                        <i class="fa-solid fa-plus" style="color: white; font-size: 14px;"></i>
                        <span class="text-wrapper-5">Create Package</span>
                    </div>
                </button>
            </div>

            <div class="stats-row">
    <div class="group">
        <span class="text-wrapper-2">Total Packages</span>
        <div class="group-2">
            <span class="text-wrapper-3"><?php echo $totalPackages; ?></span>
            <span class="text-wrapper-4">All packages</span>
        </div>
    </div>
    <div class="group relative-stat">
        <span class="text-wrapper-2">Popular Packages</span>
        <div class="group-2">
            <span class="text-wrapper-3"><?php echo $popularPackages; ?></span>
            <span class="text-wrapper-4">With bookings</span>
        </div>
    </div>
    <div class="group relative-stat">
        <span class="text-wrapper-2">Avg. Price</span>
        <div class="group-2">
            <span class="text-wrapper-3">₱<?php echo number_format($avgPrice, 0); ?></span>
            <span class="text-wrapper-4">Average package price</span>
        </div>
    </div>
</div>

            <div class="sub-header">
                <i class="fa-solid fa-box-archive element"></i>
                <span class="text-wrapper-6">All Packages</span>
                <div class="rectangle-2">
                    <span class="text-wrapper-7"><?php echo $totalPackages; ?> packages</span>
                </div>
            </div>

            <div class="package-grid">
                <?php foreach($packages as $pkg): ?>
                <div class="package-card">
                    <div class="card-top-info">
                        <div>
                            <h2 class="pkg-title"><?php echo $pkg['name']; ?></h2>
                            <div class="pkg-meta">
    <span class="pop-text"><?php echo htmlspecialchars($pkg['popularity']); ?></span>
</div>
                        </div>
                        <div class="price-info">
                            <span class="range-label">Starting Price</span>
                            <span class="range-value">₱<?php echo number_format($pkg['price_start']); ?></span>
                        </div>
                    </div>

                    <img src="<?php echo $pkg['img']; ?>" class="pkg-image" alt="Package">
                    <p class="pkg-description"><?php echo $pkg['desc']; ?></p>

                    <div class="tags-section">
                        <h4 class="section-label">Features:</h4>
                        <div class="tags-container">
                            <?php foreach(array_slice($pkg['features'], 0, 2) as $feat): ?>
                                <span class="tag"><?php echo $feat; ?></span>
                            <?php endforeach; ?>
                            <?php $moreFeatures = count($pkg['features']) - 2; ?>
                            <?php if ($moreFeatures > 0): ?>
                                <span class="tag">+<?php echo $moreFeatures; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tags-section">
                        <h4 class="section-label">Includes:</h4>
                        <div class="tags-container">
                            <?php foreach(array_slice($pkg['includes'], 0, 2) as $inc): ?>
                                <span class="tag"><?php echo $inc; ?></span>
                            <?php endforeach; ?>
                            <?php $moreIncludes = count($pkg['includes']) - 2; ?>
                            <?php if ($moreIncludes > 0): ?>
                                <span class="tag">+<?php echo $moreIncludes; ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer">
                        <button class="footer-btn" onclick='mgOpenModal("view", <?php echo json_encode($pkg); ?>)'><i class="fa-regular fa-eye"></i> View</button>
                        <button class="footer-btn" onclick='mgOpenModal("edit", <?php echo json_encode($pkg); ?>)'><i class="fa-regular fa-pen-to-square"></i> Edit</button>
                        <a href="delete_package.php?id=<?php echo $pkg['id']; ?>" class="footer-btn delete" onclick="return openDeleteConfirm(event, this.href, 'Delete this package?')" style="text-decoration:none;">
                            <i class="fa-solid fa-trash"></i> Delete
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div id="mgModalOverlay" class="mg-modal-overlay" style="display:none;">
        <div class="mg-modal-content">
            <div id="mgModalInjectedContent"></div>
        </div>
    </div>

    <div id="deleteConfirmModal" class="confirm-overlay" style="display:none;">
        <div class="confirm-card">
            <div class="confirm-icon-wrap delete-mode">
                <i class="fas fa-trash"></i>
            </div>

            <h3>Delete Package</h3>
            <p id="deleteConfirmMessage">Delete this package?</p>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn cancel" onclick="closeDeleteConfirm()">No</button>
                <button type="button" class="confirm-btn delete-confirm" id="deleteConfirmProceedBtn">Delete</button>
            </div>
        </div>
    </div>

    <script>
let deleteConfirmUrl = '';

function openDeleteConfirm(event, url, message) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    deleteConfirmUrl = url;

    const msg = document.getElementById('deleteConfirmMessage');
    const modal = document.getElementById('deleteConfirmModal');

    if (msg) msg.innerText = message;
    if (modal) modal.style.display = 'flex';

    return false;
}

function closeDeleteConfirm() {
    const modal = document.getElementById('deleteConfirmModal');
    if (modal) modal.style.display = 'none';
    deleteConfirmUrl = '';
}

document.addEventListener('DOMContentLoaded', function () {
    const deleteConfirmBtn = document.getElementById('deleteConfirmProceedBtn');
    const deleteModal = document.getElementById('deleteConfirmModal');

    if (deleteConfirmBtn) {
        deleteConfirmBtn.onclick = function () {
            if (deleteConfirmUrl) {
                window.location.href = deleteConfirmUrl;
            }
        };
    }

    if (deleteModal) {
        deleteModal.addEventListener('click', function (e) {
            if (e.target === deleteModal) {
                closeDeleteConfirm();
            }
        });
    }
});

function mgOpenModal(mode, data = null) {
    const overlay = document.getElementById('mgModalOverlay');
    const content = document.getElementById('mgModalInjectedContent');
    overlay.style.display = 'flex';

    if (mode === 'create' || mode === 'edit') {
        const features = data && data.features ? data.features : [];
        const includes = data && data.includes ? data.includes : [];

        content.innerHTML = `
            <form method="POST" action="save_package.php" enctype="multipart/form-data" id="packageForm">
                <input type="hidden" name="package_id" value="${data ? data.id : ''}">
                <input type="hidden" name="features_json" id="featuresJson">
                <input type="hidden" name="includes_json" id="includesJson">

                <div class="mg-modal-header">
                    <div>
                        <h2 class="mg-modal-title">${mode === 'create' ? 'Create New Package' : 'Edit Package'}</h2>
                        <p class="mg-modal-subtitle">Create a new event package with pricing and service details</p>
                    </div>
                    <div class="mg-header-btns">
                        <button type="button" class="mg-btn-cancel" onclick="mgCloseModal()">Cancel</button>
                        <button type="submit" class="mg-btn-submit">${mode === 'create' ? 'Create Package' : 'Save Changes'}</button>
                    </div>
                </div>

                <div class="mg-form-grid">
                    <div class="mg-col">
                        <label class="mg-label">Package Name</label>
                        <input type="text" name="package_name" class="mg-input" placeholder="Enter package name" value="${data ? data.name : ''}" required>

                        <label class="mg-label">Starting Price</label>
                        <input type="number" step="0.01" name="price_start" class="mg-input" placeholder="0" value="${data ? data.price_start : ''}" required>

                        <label class="mg-label">Status</label>
                        <select name="status" class="mg-input">
                            <option value="Active" ${!data || data.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="Inactive" ${data && data.status === 'Inactive' ? 'selected' : ''}>Inactive</option>
                        </select>
                    </div>

                    <div class="mg-col">
                        <label class="mg-label">Description</label>
                        <textarea name="description" class="mg-input mg-textarea" placeholder="Enter description">${data ? data.desc : ''}</textarea>
                    </div>

                    <div class="mg-col">
                        <div class="mg-up-row">
                            <label class="mg-label">Image</label>
                            <button type="button" class="mg-up-btn" onclick="document.getElementById('mgFileInput').click()">Upload Image</button>
                        </div>
                        <div class="mg-img-preview" id="mgPreviewBox">
                            ${data ? `<img src="${data.img}" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">` : '<span style="color:#aaa; font-size:12px;">No image</span>'}
                        </div>
                        <input type="file" name="package_image" id="mgFileInput" style="display:none" onchange="mgPreview(this)">
                    </div>

                    <div class="mg-full-width">
                        <label class="mg-label">Features</label>
                        <div class="mg-tag-box">
                            <input type="text" placeholder="Enter feature then press Add" class="mg-tag-input" id="featureInput">
                            <button type="button" class="mg-up-btn" onclick="addPkgTag('feature')">Add</button>
                            <div class="mg-tags-container" id="featureTags">
                                ${features.map(f => `<span class="mg-removable-tag">${f} <i class="fa-solid fa-xmark" onclick="this.parentElement.remove()"></i></span>`).join('')}
                            </div>
                        </div>
                    </div>

                    <div class="mg-full-width">
                        <label class="mg-label">Includes</label>
                        <div class="mg-tag-box">
                            <input type="text" placeholder="Enter include then press Add" class="mg-tag-input" id="includeInput">
                            <button type="button" class="mg-up-btn" onclick="addPkgTag('include')">Add</button>
                            <div class="mg-tags-container" id="includeTags">
                                ${includes.map(i => `<span class="mg-removable-tag">${i} <i class="fa-solid fa-xmark" onclick="this.parentElement.remove()"></i></span>`).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        `;

        setTimeout(() => {
            const form = document.getElementById('packageForm');
            if (form) {
                form.addEventListener('submit', function () {
                    const featureTexts = [];
                    document.querySelectorAll('#featureTags .mg-removable-tag').forEach(tag => {
                        featureTexts.push(tag.childNodes[0].textContent.trim());
                    });

                    const includeTexts = [];
                    document.querySelectorAll('#includeTags .mg-removable-tag').forEach(tag => {
                        includeTexts.push(tag.childNodes[0].textContent.trim());
                    });

                    document.getElementById('featuresJson').value = JSON.stringify(featureTexts);
                    document.getElementById('includesJson').value = JSON.stringify(includeTexts);
                });
            }
        }, 50);
    }

    else if (mode === 'view' && data) {
        content.innerHTML = `
            <div class="mg-modal-header">
                <div>
                    <h2 class="mg-modal-title">${data.name}</h2>
                    <p class="mg-modal-subtitle">Package full details</p>
                </div>
                <div class="mg-header-btns">
                    <button type="button" class="mg-btn-cancel" onclick="mgCloseModal()">Close</button>
                </div>
            </div>

            <div class="mg-form-grid">
                <div class="mg-col">
                    <div class="mg-img-preview" style="height:220px;">
                        <img src="${data.img}" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">
                    </div>
                </div>

                <div class="mg-col">
                    <label class="mg-label">Package Name</label>
                    <div class="mg-input">${data.name}</div>

                    <label class="mg-label">Starting Price</label>
                    <div class="mg-input">₱${Number(data.price_start).toLocaleString()}</div>

                    <label class="mg-label">Popularity</label>
                    <div class="mg-input">${data.popularity}</div>

                    <label class="mg-label">Status</label>
                    <div class="mg-input">${data.status}</div>
                </div>

                <div class="mg-col">
                    <label class="mg-label">Description</label>
                    <div class="mg-input" style="min-height:120px; white-space:pre-wrap;">${data.desc || 'No description available.'}</div>
                </div>

                <div class="mg-full-width">
                    <label class="mg-label">Features</label>
                    <div class="mg-tags-container">
                        ${data.features.length ? data.features.map(f => `<span class="tag">${f}</span>`).join('') : '<span class="tag">No features added</span>'}
                    </div>
                </div>

                <div class="mg-full-width">
                    <label class="mg-label">Includes</label>
                    <div class="mg-tags-container">
                        ${data.includes.length ? data.includes.map(i => `<span class="tag">${i}</span>`).join('') : '<span class="tag">No includes added</span>'}
                    </div>
                </div>
            </div>
        `;
    }
}

function addPkgTag(type) {
    const input = document.getElementById(type === 'feature' ? 'featureInput' : 'includeInput');
    const container = document.getElementById(type === 'feature' ? 'featureTags' : 'includeTags');
    const value = input.value.trim();

    if (!value) return;

    const span = document.createElement('span');
    span.className = 'mg-removable-tag';
    span.innerHTML = `${value} <i class="fa-solid fa-xmark" onclick="this.parentElement.remove()"></i>`;
    container.appendChild(span);

    input.value = '';
}

function mgCloseModal() {
    document.getElementById('mgModalOverlay').style.display = 'none';
}

function mgPreview(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('mgPreviewBox').innerHTML =
                `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:12px;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

window.onclick = function(event) {
    const overlay = document.getElementById('mgModalOverlay');
    if (event.target === overlay) {
        mgCloseModal();
    }
};
</script>
</body>
</html>