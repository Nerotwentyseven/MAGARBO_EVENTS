<?php
session_name('ADMINSESSID');
session_start();

require_once 'admin_auth.php';
require_once '../db_connection.php';

$adminTheme = 'Light Mode';
$themeQuery = mysqli_query($conn, "SELECT theme FROM admin_settings WHERE id = 1 LIMIT 1");
if ($themeQuery && mysqli_num_rows($themeQuery) > 0) {
    $themeRow = mysqli_fetch_assoc($themeQuery);
    $adminTheme = $themeRow['theme'] ?? 'Light Mode';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery Management | Magarbo Events</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="gallery_management.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="sidebar.css?v=<?php echo time(); ?>" />
    
</head>
<body class="<?php echo ($adminTheme === 'Dark Mode') ? 'dark-mode' : ''; ?>">
    <div class="ADMIN-GALLERY">
        <?php include('sidebar.php'); ?>

        <div class="main-content">
            <div class="header-section">
                <div>
                    <h1 class="page-title">Gallery Management</h1>
                    <p class="subtitle">Manage albums and uploaded event media</p>
                </div>
                <div class="action-buttons">
                    <button class="billing-btn white" onclick="openModal('createAlbumModal')">
                        <i class="fa-solid fa-plus"></i> Create Album
                    </button>

                    <button class="billing-btn gold" onclick="openModal('uploadModal')">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Media
                    </button>

                    <div class="action-dropdown">
                        <button type="button" class="billing-btn white dropdown-toggle" onclick="toggleActionMenu()">
                            <i class="fa-solid fa-ellipsis"></i> More
                        </button>

                        <div class="action-dropdown-menu" id="actionDropdownMenu">
                            <button type="button" class="dropdown-item" onclick="openActionModal('createThemeModal')">
                                <i class="fa-solid fa-palette"></i> Add Theme
                            </button>
                            <button type="button" class="dropdown-item" onclick="openActionModal('createThemeCategoryModal')">
                                <i class="fa-solid fa-folder-plus"></i> Add Theme Category
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-label">TOTAL ALBUMS</div>
                    <div class="stat-number" id="stat-albums">0</div>
                    <div class="stat-note">All created albums</div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">TOTAL MEDIA</div>
                    <div class="stat-number" id="stat-photos">0</div>
                    <div class="stat-note">Photos and videos uploaded</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">TOTAL THEMES</div>
                    <div class="stat-number" id="stat-themes">0</div>
                    <div class="stat-note">All created themes</div>
                </div>
            </div>

            <div class="pill-tabs-wrapper">
                <div class="pill-tabs-container">
                    <button class="pill-tab active" id="btn-albums" onclick="switchTab('albums')">Albums</button>
                    <button class="pill-tab" id="btn-photos" onclick="switchTab('photos')">All Photos</button>
                    <button class="pill-tab" id="btn-videos" onclick="switchTab('videos')">All Videos</button>
                    <button class="pill-tab" id="btn-themes" onclick="switchTab('themes')">Themes</button>
                </div>
            </div>

            <div id="albums-view" class="gallery-content active">
                <div class="menu-grid" id="albumContainer"></div>
            </div>

            <div id="photos-view" class="gallery-content">
                <div class="photo-grid" id="allPhotosGrid"></div>
            </div>

            <div id="videos-view" class="gallery-content">
                <div class="photo-grid" id="allVideosGrid"></div>
            </div>
            <div id="themes-view" class="gallery-content">
                <div class="menu-grid" id="themeCategoryContainer"></div>
            </div>
        </div>
    </div>

    <div id="viewAlbumModal" class="modal-bg" style="display:none;">
        <div class="modal-box-large">
            <div class="modal-header-billing">
                <div>
                    <h2 id="viewAlbumTitle">Album Name</h2>
                    <p id="viewAlbumSubtitle">Viewing photos in this album</p>
                </div>
                <button class="header-cancel" onclick="closeModal('viewAlbumModal')">Close</button>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="photo-grid" id="albumPhotosGrid"></div>
            </div>
        </div>
    </div>

    <div id="createAlbumModal" class="modal-bg" style="display:none;">
        <div class="modal-box">
            <div class="modal-header-billing">
                <div>
                    <h2>Create New Album</h2>
                    <p>Create a new album for uploaded event media</p>
                </div>
                <div class="modal-header-btns">
                    <button class="header-cancel" onclick="closeModal('createAlbumModal')">Cancel</button>
                    <button class="header-create" id="createAlbumBtn" onclick="saveAlbum()">Create Album</button>
                </div>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="form-group">
                    <label>Album Name</label>
                    <input type="text" id="newAlbumName" placeholder="Enter album name">
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" id="newAlbumCategory" placeholder="Wedding, Birthday, Graduation, etc.">
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="newAlbumDesc" placeholder="Describe this album" rows="4"></textarea>
                </div>
            </div>
        </div>
    </div>

    <div id="uploadModal" class="modal-bg" style="display:none;">
        <div class="modal-box">
            <div class="modal-header-billing">
                <div>
                    <h2>Upload Media</h2>
                    <p>Add one or more photos or videos to a selected album</p>
                </div>
                <div class="modal-header-btns">
                    <button class="header-cancel" onclick="closeModal('uploadModal')">Cancel</button>
                    <button class="header-create" id="uploadPhotoBtn" onclick="uploadToGallery()">Upload Media</button>
                </div>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="form-group">
                    <label>Album</label>
                    <select id="albumDropdown">
                        <option value="" disabled selected>Select an album</option>
                    </select>
                </div>



                <div class="form-group">
                    <label>Upload Files</label>
                    <div class="file-upload-container">
                        <input type="file" id="photoFiles" accept="image/*,video/mp4" multiple onchange="handleFiles(event)">
                        <p id="fileCountText">Choose Files (Multiple allowed)</p>
                    </div>
                    <div id="previewWrapper" class="preview-scroll-container"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="viewPhotoModal" class="modal-bg" style="display:none;">
        <div class="modal-box-large">
            <button class="close-modal-btn" onclick="closeModal('viewPhotoModal')">&times;</button>
            <div class="photo-detail-layout">
                <div class="detail-left">
                    <div id="fullMediaDisplay"></div>
                </div>
                <div class="detail-right">
                    <h2 id="displayPhotoTitle">Photo Detail</h2>
                    <div class="info-block">
                        <label>Album</label>
                        <p id="infoAlbum">N/A</p>

                        <label>Category</label>
                        <p id="infoCategory">N/A</p>

                        <label>Upload Date</label>
                        <p id="infoUploadDate">N/A</p>
                    </div>
                    <div class="detail-footer-actions">
                        <button class="btn-detail-dl" id="downloadPhotoBtn">
                            <i class="fa fa-download"></i> Download
                        </button>
                        <button class="btn-detail-del" id="confirmDelete">
                            <i class="fa fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="galleryDeleteModal" class="confirm-overlay" style="display:none;">
        <div class="confirm-card">
            <div class="confirm-icon-wrap delete-mode">
                <i class="fas fa-trash"></i>
            </div>

            <h3 id="galleryDeleteTitle">Delete</h3>
            <p id="galleryDeleteMessage">Are you sure?</p>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn cancel" onclick="closeGalleryDelete()">No</button>
                <button type="button" class="confirm-btn delete-confirm" id="galleryDeleteProceedBtn">Delete</button>
            </div>
        </div>
    </div>

    <div id="createThemeModal" class="modal-bg" style="display:none;">
        <div class="modal-box">
            <div class="modal-header-billing">
                <div>
                    <h2>Create Theme</h2>
                    <p>Add a theme for booking suggestions</p>
                </div>
                <div class="modal-header-btns">
                    <button class="header-cancel" onclick="closeModal('createThemeModal')">Cancel</button>
                    <button class="header-create" id="createThemeBtn" onclick="saveTheme()">Create Theme</button>
                </div>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="form-group">
                    <label>Category</label>
                    <select id="themeCategorySelect">
                        <option value="" disabled selected>Select category</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Theme Name</label>
                    <input type="text" id="themeName" placeholder="Royal Gold, Elegant White, Rustic Garden">
                </div>

                <div class="form-group">
                    <label>Theme Details</label>
                    <textarea id="themeDetails" rows="4" placeholder="Short description of this theme"></textarea>
                </div>

                <div class="form-group">
                    <label>Theme Photo</label>
                    <div class="file-upload-container">
                        <input type="file" id="themePhoto" accept="image/*">
                        <p>Choose 1 theme image</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="createThemeCategoryModal" class="modal-bg" style="display:none;">
        <div class="modal-box">
            <div class="modal-header-billing">
                <div>
                    <h2>Create Theme Category</h2>
                    <p>Add a new category for organizing theme suggestions</p>
                </div>
                <div class="modal-header-btns">
                    <button class="header-cancel" onclick="closeModal('createThemeCategoryModal')">Cancel</button>
                    <button class="header-create" id="createThemeCategoryBtn" onclick="saveThemeCategory()">Create Category</button>
                </div>
            </div>

            <div class="modal-body scrollable-modal-body">
                <div class="form-group">
                    <label>Category Name</label>
                    <input type="text" id="newThemeCategory" placeholder="Wedding, Birthday, Christening...">
                </div>
            </div>
        </div>
    </div>

    <div id="viewThemeCategoryModal" class="modal-bg" style="display:none;">
        <div class="modal-box-large">
            <div class="modal-header-billing">
                <div>
                    <h2 id="viewThemeCategoryTitle">Theme Category</h2>
                    <p id="viewThemeCategorySubtitle">Viewing themes in this category</p>
                </div>
                <button class="header-cancel" onclick="closeModal('viewThemeCategoryModal')">Close</button>
            </div>
            <div class="modal-body scrollable-modal-body">
                <div class="menu-grid" id="themeCategoryThemesGrid"></div>
            </div>
        </div>
    </div>

    <div id="galleryAlertModal" class="confirm-overlay" style="display:none;">
        <div class="confirm-card">
            <div id="galleryAlertIcon" class="confirm-icon-wrap">
                <i class="fa-solid fa-circle-info"></i>
            </div>

            <h3 id="galleryAlertTitle">Notice</h3>
            <p id="galleryAlertMessage">Message here</p>

            <div class="confirm-actions">
                <button type="button" class="confirm-btn approve" onclick="closeGalleryAlert()">
                    OK
                </button>
            </div>
        </div>
    </div>

    <script>
        let galleryDeleteAction = null;

        function openGalleryDelete(title, message, callback) {
            const modal = document.getElementById('galleryDeleteModal');
            const titleEl = document.getElementById('galleryDeleteTitle');
            const messageEl = document.getElementById('galleryDeleteMessage');

            if (titleEl) titleEl.innerText = title;
            if (messageEl) messageEl.innerText = message;

            galleryDeleteAction = callback;

            if (modal) {
                modal.style.display = 'flex';
            }
        }

        function closeGalleryDelete() {
            const modal = document.getElementById('galleryDeleteModal');
            if (modal) {
                modal.style.display = 'none';
            }
            galleryDeleteAction = null;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const proceedBtn = document.getElementById('galleryDeleteProceedBtn');
            const modal = document.getElementById('galleryDeleteModal');

            if (proceedBtn) {
                proceedBtn.onclick = async function () {
                    if (galleryDeleteAction) {
                        await galleryDeleteAction();
                    }
                    closeGalleryDelete();
                };
            }

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) {
                        closeGalleryDelete();
                    }
                });
            }
        });

        

        let albums = [];
        let globalPhotos = [];
        let globalThemes = [];
        let globalThemeCategories = [];
        let currentPhotoId = null;
        let currentThemeCategoryId = null;
        let currentThemeCategoryName = '';

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';

            if (id === 'viewThemeCategoryModal') {
                currentThemeCategoryId = null;
                currentThemeCategoryName = '';
            }
        }

        function switchTab(type) {
            localStorage.setItem('galleryActiveTab', type);

            document.querySelectorAll('.pill-tab').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.gallery-content').forEach(content => content.classList.remove('active'));

            document.getElementById('btn-' + type).classList.add('active');
            document.getElementById(type + '-view').classList.add('active');
        }

        function handleFiles(event) {
            const files = event.target.files;
            const previewWrapper = document.getElementById('previewWrapper');
            previewWrapper.innerHTML = '';

            document.getElementById('fileCountText').innerText = files.length > 0
                ? `${files.length} file(s) selected`
                : 'Choose Files (Multiple allowed)';

            Array.from(files).forEach(file => {
                const reader = new FileReader();

                reader.onload = function(e) {
                    if (file.type.startsWith('video/')) {
                        const video = document.createElement('video');
                        video.src = e.target.result;
                        video.className = 'mini-preview';
                        video.muted = true;
                        video.playsInline = true;
                        previewWrapper.appendChild(video);
                    } else if (file.type.startsWith('image/')) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'mini-preview';
                        previewWrapper.appendChild(img);
                    }
                };

                reader.readAsDataURL(file);
            });
        }

        async function saveTheme() {
            const btn = document.getElementById('createThemeBtn');

            const category_id = document.getElementById('themeCategorySelect').value;
            const theme_name = document.getElementById('themeName').value.trim();
            const theme_details = document.getElementById('themeDetails').value.trim();
            const themePhotoInput = document.getElementById('themePhoto');

            if (!category_id || !theme_name) {
                alert('Category and theme name are required.');
                return;
            }

            if (!themePhotoInput.files || themePhotoInput.files.length === 0) {
                alert('Please choose 1 theme photo.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const formData = new FormData();
                formData.append('action', 'create_theme');
                formData.append('category_id', category_id); // ✅ FIX
                formData.append('theme_name', theme_name);
                formData.append('theme_details', theme_details);
                formData.append('theme_photo', themePhotoInput.files[0]);

                const response = await fetch('gallery_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                    openGalleryAlert(
                        result.status === 'success' ? 'Upload Successful' : 'Upload Failed',
                        result.message,
                        result.status === 'success' ? 'success' : 'error'
                    );

                    if (result.status === 'success') {
                    document.getElementById('themeCategorySelect').value = '';
                    document.getElementById('themeName').value = '';
                    document.getElementById('themeDetails').value = '';
                    themePhotoInput.value = '';

                    closeModal('createThemeModal');
                    loadGallery();
                }
            } catch (error) {
                alert('Failed to create theme.');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Create Theme';
            }
        }

        async function saveThemeCategory() {
            const btn = document.getElementById('createThemeCategoryBtn');
            const name = document.getElementById('newThemeCategory').value.trim();

            if (!name) {
                alert('Category name is required.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Creating...';

            try {
                const formData = new FormData();
                formData.append('action', 'create_theme_category');
                formData.append('category_name', name);

                const res = await fetch('gallery_api.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await res.json();
                openGalleryAlert(
                    data.status === 'success' ? 'Category Created' : 'Create Failed',
                    data.message,
                    data.status === 'success' ? 'success' : 'error'
                );

                if (data.status === 'success') {
                    document.getElementById('newThemeCategory').value = '';
                    closeModal('createThemeCategoryModal');
                    loadGallery();
                }
                } catch (error) {
                    openGalleryAlert('Create Failed', 'Failed to create theme category.', 'error');
                } finally {
                btn.disabled = false;
                btn.textContent = 'Create Category';
            }
        }

        function renderThemes() {
            const container = document.getElementById('themeCategoryContainer');

            if (!globalThemeCategories.length) {
                container.innerHTML = '<p style="padding:20px;">No theme categories found.</p>';
                return;
            }

            const categoryCards = globalThemeCategories.map(category => {
                const categoryId = Number(category.id);
                const categoryName = category.category_name || 'Unnamed Category';

                const items = globalThemes.filter(theme => Number(theme.category_id) === categoryId);

                const totalPicked = items.reduce((sum, item) => {
                    return sum + Number(item.pick_count || 0);
                }, 0);

                const activeCount = items.filter(item => Number(item.is_active) === 1).length;

                return `
                    <div class="menu-box">
                        <div class="menu-header">
                            <h3>${escapeHtml(categoryName)}</h3>
                            <p>${items.length} theme(s) in this category</p>
                            <small>
                                Picked: ${totalPicked} | Active Themes: ${activeCount}
                            </small>
                        </div>

                        <div class="actions">
                            <button class="view-btn-pill" onclick="openThemeCategoryModalById(${categoryId}, '${escapeJs(categoryName)}')">
                                <i class="fa-solid fa-eye"></i> View
                            </button>
                            <button class="delete-btn-pill" onclick="deleteThemeCategory(${categoryId}, '${escapeJs(categoryName)}')">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = categoryCards.join('');
        }

        async function toggleThemeStatus(themeId, currentStatus) {
            try {
                const formData = new FormData();
                formData.append('action', 'toggle_theme_status');
                formData.append('theme_id', themeId);
                formData.append('is_active', currentStatus == 1 ? 0 : 1);

                const response = await fetch('gallery_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
            openGalleryAlert(
                result.status === 'success' ? 'Theme Updated' : 'Update Failed',
                result.message,
                result.status === 'success' ? 'success' : 'error'
            );

            if (result.status === 'success') {
                await loadGallery();

                if (currentThemeCategoryId && currentThemeCategoryName) {
                    openThemeCategoryModalById(currentThemeCategoryId, currentThemeCategoryName);
                }
            }
            } catch (error) {
                openGalleryAlert('Update Failed', 'Failed to update theme status.', 'error');
            }
        }

        async function deleteTheme(themeId, themeName) {
            openGalleryDelete(
                'Delete Theme',
                `Delete theme "${themeName}"?`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_theme');
                        formData.append('theme_id', themeId);

                        const response = await fetch('gallery_api.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        openGalleryAlert(
                            result.status === 'success' ? 'Theme Deleted' : 'Delete Failed',
                            result.message,
                            result.status === 'success' ? 'success' : 'error'
                        );

                        if (result.status === 'success') {
                            loadGallery();
                        }
                    } catch (error) {
                        alert('Failed to delete theme.');
                    }
                }
            );
        }

        function toggleActionMenu() {
            const menu = document.getElementById('actionDropdownMenu');
            if (menu) {
                menu.classList.toggle('show');
            }
        }

        function openActionModal(id) {
            const menu = document.getElementById('actionDropdownMenu');
            if (menu) {
                menu.classList.remove('show');
            }
            openModal(id);
        }

        document.addEventListener('click', function (e) {
            const dropdown = document.querySelector('.action-dropdown');
            const menu = document.getElementById('actionDropdownMenu');

            if (!dropdown || !menu) return;

            if (!dropdown.contains(e.target)) {
                menu.classList.remove('show');
            }
        });

        function openThemeCategoryModalById(categoryId, categoryName) {
            currentThemeCategoryId = Number(categoryId);
            currentThemeCategoryName = categoryName;

            const filteredThemes = globalThemes.filter(
                theme => Number(theme.category_id) === Number(categoryId)
            );

            document.getElementById('viewThemeCategoryTitle').innerText = categoryName;
            document.getElementById('viewThemeCategorySubtitle').innerText = 'Viewing themes in this category';

            const grid = document.getElementById('themeCategoryThemesGrid');

            if (!filteredThemes.length) {
                grid.innerHTML = '<p style="grid-column: 1 / -1; text-align:center; padding:20px;">No themes in this category.</p>';
            } else {
                grid.innerHTML = filteredThemes.map(theme => {
                    const imageHtml = theme.theme_photo
                        ? `<img src="../${theme.theme_photo}" class="theme-preview-img" alt="${escapeHtml(theme.theme_name)}">`
                        : `<div class="no-preview">No Preview</div>`;

                    return `
                        <div class="menu-box">
                            <div class="theme-preview">
                                ${imageHtml}
                            </div>

                            <div class="menu-header">
                                <h3>${escapeHtml(theme.theme_name)}</h3>
                                <p>${escapeHtml(theme.theme_details || 'No theme details available')}</p>
                                <small>
                                    Picked: ${theme.pick_count} |
                                    Status: ${Number(theme.is_active) === 1 ? 'Active' : 'Inactive'}
                                </small>
                            </div>

                            <div class="actions">
                                <button class="view-btn-pill" onclick="toggleThemeStatus(${theme.id}, ${Number(theme.is_active)})">
                                    <i class="fa-solid fa-repeat"></i>
                                    ${Number(theme.is_active) === 1 ? 'Deactivate' : 'Activate'}
                                </button>
                                <button class="delete-btn-pill" onclick="deleteTheme(${theme.id}, '${escapeJs(theme.theme_name)}')">
                                    <i class="fa-solid fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            openModal('viewThemeCategoryModal');
        }

        async function deleteThemeCategory(categoryId, categoryName) {
            if (!categoryId || categoryId <= 0) {
                alert('Invalid category.');
                return;
            }

            openGalleryDelete(
                'Delete Theme Category',
                `Delete category "${categoryName}"? This will also delete all themes inside it.`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_theme_category');
                        formData.append('category_id', categoryId);

                        const response = await fetch('gallery_api.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        openGalleryAlert(
                            result.status === 'success' ? 'Category Deleted' : 'Delete Failed',
                            result.message,
                            result.status === 'success' ? 'success' : 'error'
                        );

                        if (result.status === 'success') {
                            closeModal('viewThemeCategoryModal');
                            loadGallery();
                        }
                    } catch (error) {
                        alert('Failed to delete theme category.');
                    }
                }
            );
        }

        function openGalleryAlert(title, message, type = 'info') {
            const modal = document.getElementById('galleryAlertModal');
            const iconWrap = document.getElementById('galleryAlertIcon');
            const titleEl = document.getElementById('galleryAlertTitle');
            const messageEl = document.getElementById('galleryAlertMessage');

            if (!modal || !iconWrap || !titleEl || !messageEl) return;

            iconWrap.className = 'confirm-icon-wrap';

            let iconClass = 'fa-solid fa-circle-info';

            if (type === 'success') {
                iconWrap.classList.add('success-mode');
                iconClass = 'fa-solid fa-check';
            } else if (type === 'error') {
                iconWrap.classList.add('error-mode');
                iconClass = 'fa-solid fa-xmark';
            } else {
                iconWrap.classList.add('info-mode');
                iconClass = 'fa-solid fa-circle-info';
            }

            iconWrap.innerHTML = `<i class="${iconClass}"></i>`;
            titleEl.innerText = title;
            messageEl.innerText = message;
            modal.style.display = 'flex';
        }

        function closeGalleryAlert() {
            const modal = document.getElementById('galleryAlertModal');
            if (modal) modal.style.display = 'none';
        }

        async function loadGallery() {
            try {
                const response = await fetch('gallery_api.php?action=list');
                const result = await response.json();

                if (result.status === 'success') {
                    albums = result.data.albums || [];
                    globalPhotos = result.data.photos || [];
                    globalThemes = result.data.themes || [];
                    globalThemeCategories = result.data.theme_categories || [];

                    const categories = result.data.theme_categories || [];
                    const select = document.getElementById('themeCategorySelect');

                    if (select) {
                        select.innerHTML = '<option value="" disabled selected>Select category</option>';

                        categories.forEach(cat => {
                            const option = document.createElement('option');
                            option.value = cat.id;
                            option.textContent = cat.category_name;
                            select.appendChild(option);
                        });
                    }

                    renderAlbums();
                    renderPhotos();
                    renderVideos();
                    renderThemes();
                    updateDropdown();
                    updateStats();
                } else {
                    openGalleryAlert('Load Failed', result.message, 'error');
                }
                } catch (error) {
                    openGalleryAlert('Load Failed', 'Failed to load gallery data.', 'error');
                }
        }

        async function saveAlbum() {
            const createBtn = document.getElementById('createAlbumBtn');
            const album_name = document.getElementById('newAlbumName').value.trim();
            const category = document.getElementById('newAlbumCategory').value.trim();
            const description = document.getElementById('newAlbumDesc').value.trim();

            if (!album_name || !category) {
                openGalleryAlert('Missing Fields', 'Album name and category are required.', 'info');
                return;
            }

            createBtn.disabled = true;
            createBtn.textContent = 'Creating...';

            try {
                const formData = new FormData();
                formData.append('action', 'create_album');
                formData.append('album_name', album_name);
                formData.append('category', category);
                formData.append('description', description);

                const response = await fetch('gallery_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                openGalleryAlert(
                    result.status === 'success' ? 'Album Created' : 'Create Failed',
                    result.message,
                    result.status === 'success' ? 'success' : 'error'
                );

                if (result.status === 'success') {
                    document.getElementById('newAlbumName').value = '';
                    document.getElementById('newAlbumCategory').value = '';
                    document.getElementById('newAlbumDesc').value = '';

                    closeModal('createAlbumModal');
                    loadGallery();
                }
            } catch (error) {
                openGalleryAlert('Create Failed', 'Failed to create album.', 'error');
            } finally {
                createBtn.disabled = false;
                createBtn.textContent = 'Create Album';
            }
        }

        function renderAlbums() {
            const container = document.getElementById('albumContainer');

            if (!albums.length) {
                container.innerHTML = '<p style="padding:20px;">No albums found.</p>';
                return;
            }

            container.innerHTML = albums.map(album => `
                <div class="menu-box">
                    <div class="menu-header">
                        <h3>${escapeHtml(album.album_name)}</h3>
                        <p>${escapeHtml(album.description || 'No description available')}</p>
                        <small>
                            Category: ${escapeHtml(album.category)} |
                            Photos: ${album.photo_count}
                        </small>
                    </div>
                    <div class="actions">
                        <button class="view-btn-pill" onclick="openAlbumModal(${album.id}, '${escapeJs(album.album_name)}')">
                            <i class="fa-solid fa-eye"></i> View
                        </button>
                        <button class="delete-btn-pill" onclick="deleteAlbum(${album.id}, '${escapeJs(album.album_name)}')">
                            <i class="fa-solid fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            `).join('');
        }

        async function deleteAlbum(albumId, albumName) {
            openGalleryDelete(
                'Delete Album',
                `Delete album "${albumName}"? This will also delete its media.`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_album');
                        formData.append('album_id', albumId);

                        const response = await fetch('gallery_api.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                        openGalleryAlert(
                            result.status === 'success' ? 'Album Deleted' : 'Delete Failed',
                            result.message,
                            result.status === 'success' ? 'success' : 'error'
                        );

                        if (result.status === 'success') {
                            loadGallery();
                        }
                        } catch (error) {
                            openGalleryAlert('Delete Failed', 'Failed to delete album.', 'error');
                        }
                }
            );
        }

        function updateDropdown() {
            const select = document.getElementById('albumDropdown');
            select.innerHTML = '<option value="" disabled selected>Select an album</option>';

            albums.forEach(album => {
                const option = document.createElement('option');
                option.value = album.id;
                option.textContent = album.album_name;
                select.appendChild(option);
            });
        }

        async function uploadToGallery() {
            const uploadBtn = document.getElementById('uploadPhotoBtn');
            const album_id = document.getElementById('albumDropdown').value;
            const fileInput = document.getElementById('photoFiles');

            if (!album_id || fileInput.files.length === 0) {
                openGalleryAlert('Missing Details', 'Please select an album and choose file(s).', 'info');
                return;
            }

            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';

            try {
                const formData = new FormData();
                formData.append('action', 'upload_photos');
                formData.append('album_id', album_id);

                Array.from(fileInput.files).forEach(file => {
                    formData.append('photos[]', file);
                });

                const response = await fetch('gallery_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                alert(result.message);

                if (result.status === 'success') {
                    document.getElementById('previewWrapper').innerHTML = '';
                    document.getElementById('fileCountText').innerText = 'Choose Files (Multiple allowed)';
                    document.getElementById('photoFiles').value = '';
                    document.getElementById('albumDropdown').selectedIndex = 0;

                    closeModal('uploadModal');
                    loadGallery();
                }
            } catch (error) {
                openGalleryAlert('Upload Failed', 'Upload failed. Please try again.', 'error');
            } finally {
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload Media';
            }
        }

        function renderPhotos() {
            const grid = document.getElementById('allPhotosGrid');
            const onlyPhotos = globalPhotos.filter(photo => (photo.file_type || 'image') === 'image');

            if (!onlyPhotos.length) {
                grid.innerHTML = '<p style="padding:20px;">No photos found.</p>';
                return;
            }

            grid.innerHTML = onlyPhotos.map(photo => `
                <div class="photo-card" onclick="openPhotoDetail(${photo.id})">
                    <img src="../${photo.photo_path}" alt="${escapeHtml(photo.original_name || photo.album_name)}">
                </div>
            `).join('');
        }
        function renderVideos() {
            const grid = document.getElementById('allVideosGrid');
            const onlyVideos = globalPhotos.filter(photo => photo.file_type === 'video');

            if (!onlyVideos.length) {
                grid.innerHTML = '<p style="padding:20px;">No videos found.</p>';
                return;
            }

            grid.innerHTML = onlyVideos.map(photo => `
                <div class="photo-card" onclick="openPhotoDetail(${photo.id})">
                    <video class="admin-video-thumb" muted playsinline>
                        <source src="../${photo.photo_path}" type="video/mp4">
                    </video>
                    <div class="video-badge-admin">
                        <i class="fa-solid fa-play"></i>
                    </div>
                </div>
            `).join('');
        }

        function openAlbumModal(albumId, albumName) {
            const filtered = globalPhotos.filter(photo => Number(photo.album_id) === Number(albumId));

            document.getElementById('viewAlbumTitle').innerText = albumName;
            document.getElementById('viewAlbumSubtitle').innerText = 'Viewing photos in this album';

            const grid = document.getElementById('albumPhotosGrid');

            if (!filtered.length) {
                grid.innerHTML = '<p style="grid-column: 1 / -1; text-align:center; padding:20px;">No photos in this album.</p>';
            } else {
                grid.innerHTML = filtered.map(photo => `
                <div class="photo-card" onclick="openPhotoDetail(${photo.id})">
                    ${(photo.file_type === 'video')
                        ? `
                            <video class="admin-video-thumb" muted playsinline>
                                <source src="../${photo.photo_path}" type="video/mp4">
                            </video>
                            <div class="video-badge-admin">
                                <i class="fa-solid fa-play"></i>
                            </div>
                        `
                        : `
                            <img src="../${photo.photo_path}" alt="${escapeHtml(photo.original_name || photo.album_name)}">
                        `
                    }
                </div>
            `).join('');
            }

            openModal('viewAlbumModal');
        }

        function openPhotoDetail(id) {
            const photo = globalPhotos.find(item => Number(item.id) === Number(id));
            if (!photo) return;

            currentPhotoId = photo.id;

            const mediaContainer = document.getElementById('fullMediaDisplay');

            if (photo.file_type === 'video') {
                mediaContainer.innerHTML = `
                    <video controls autoplay playsinline style="width:100%; max-height:78vh; border-radius:14px; background:#000;">
                        <source src="../${photo.photo_path}" type="video/mp4">
                    </video>
                `;
            } else {
                mediaContainer.innerHTML = `
                    <img src="../${photo.photo_path}" alt="Media Preview" style="width:100%; max-height:78vh; object-fit:contain; border-radius:14px;">
                `;
            }
            document.getElementById('displayPhotoTitle').innerText = photo.original_name || 'Photo Detail';
            document.getElementById('infoAlbum').innerText = photo.album_name || 'N/A';
            document.getElementById('infoCategory').innerText = photo.category || 'N/A';
            document.getElementById('infoUploadDate').innerText = photo.uploaded_at || 'N/A';

            const downloadBtn = document.getElementById('downloadPhotoBtn');
            downloadBtn.onclick = () => {
                const link = document.createElement('a');
                link.href = '../' + photo.photo_path;
                link.download = photo.original_name || 'gallery-photo';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            };

            const deleteBtn = document.getElementById('confirmDelete');
        deleteBtn.onclick = () => {
            const isVideo = photo.file_type === 'video';
            const mediaLabel = isVideo ? 'video' : 'photo';

            openGalleryDelete(
                `Delete ${isVideo ? 'Video' : 'Photo'}`,
                `Are you sure you want to delete this ${mediaLabel}?`,
                async () => {
                    try {
                        const formData = new FormData();
                        formData.append('action', 'delete_photo');
                        formData.append('photo_id', currentPhotoId);

                        const response = await fetch('gallery_api.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();
                    openGalleryAlert(
                        result.status === 'success'
                            ? `${isVideo ? 'Video' : 'Photo'} Deleted`
                            : 'Delete Failed',
                        result.message,
                        result.status === 'success' ? 'success' : 'error'
                    );

                    if (result.status === 'success') {
                        closeModal('viewPhotoModal');
                        loadGallery();
                    }
                    } catch (error) {
                        openGalleryAlert('Delete Failed', `Failed to delete ${mediaLabel}.`, 'error');
                    }
                }
            );
        };

            openModal('viewPhotoModal');
        }

        function updateStats() {
            document.getElementById('stat-albums').innerText = albums.length;
            document.getElementById('stat-photos').innerText = globalPhotos.length;
            document.getElementById('stat-themes').innerText = globalThemes.length;
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, function(match) {
                return {
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    '"': '&quot;',
                    "'": '&#39;'
                }[match];
            });
        }

        function escapeJs(str) {
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'");
        }

        loadGallery();

        const savedGalleryTab = localStorage.getItem('galleryActiveTab') || 'albums';

        if (document.getElementById('btn-' + savedGalleryTab) && document.getElementById(savedGalleryTab + '-view')) {
            switchTab(savedGalleryTab);
        } else {
            switchTab('albums');
        }
    </script>
</body>
</html>