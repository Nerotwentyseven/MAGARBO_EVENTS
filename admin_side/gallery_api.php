<?php
session_name('ADMINSESSID');
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

function respond($status, $message, $data = [])
{
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function clean($conn, $value)
{
    return mysqli_real_escape_string($conn, trim((string)$value));
}

function uploadImageFile($file, $targetDir = '../uploads/themes/')
{
    if (!isset($file) || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return [
            'status' => false,
            'message' => 'No valid image uploaded.'
        ];
    }

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxFileSize = 10 * 1024 * 1024;

    $originalName = $file['name'];
    $tmpName = $file['tmp_name'];
    $fileSize = $file['size'];

    if (!is_uploaded_file($tmpName)) {
        return [
            'status' => false,
            'message' => 'Uploaded file is invalid.'
        ];
    }

    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $mimeType = mime_content_type($tmpName);

    if (!in_array($extension, $allowedExtensions, true)) {
        return [
            'status' => false,
            'message' => 'Only JPG, JPEG, PNG, GIF, and WEBP are allowed.'
        ];
    }

    if (!in_array($mimeType, $allowedMimeTypes, true)) {
        return [
            'status' => false,
            'message' => 'Invalid image file type.'
        ];
    }

    if ($fileSize > $maxFileSize) {
        return [
            'status' => false,
            'message' => 'Image is too large. Maximum is 10MB.'
        ];
    }

    $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $newFileName = $safeBaseName . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;

    $relativePath = 'uploads/themes/' . $newFileName;
    $fullPath = '../' . $relativePath;

    if (!move_uploaded_file($tmpName, $fullPath)) {
        return [
            'status' => false,
            'message' => 'Failed to move uploaded image.'
        ];
    }

    return [
        'status' => true,
        'relative_path' => $relativePath
    ];
}

if ($action === 'list') {
    $albums = [];
    $photos = [];
    $themes = [];
    $theme_categories = [];

    $sqlAlbums = "
        SELECT ga.*, COUNT(gp.id) AS photo_count
        FROM gallery_albums ga
        LEFT JOIN gallery_photos gp ON ga.id = gp.album_id
        GROUP BY ga.id
        ORDER BY ga.created_at DESC
    ";
    $resultAlbums = mysqli_query($conn, $sqlAlbums);

    if ($resultAlbums) {
        while ($row = mysqli_fetch_assoc($resultAlbums)) {
            $albums[] = $row;
        }
    }

    $sqlPhotos = "
        SELECT 
            gp.id,
            gp.album_id,
            gp.photo_path,
            gp.original_name,
            gp.file_type,
            gp.uploaded_at,
            ga.album_name,
            ga.category,
            ga.event_date,
            ga.description
        FROM gallery_photos gp
        INNER JOIN gallery_albums ga ON gp.album_id = ga.id
        ORDER BY gp.uploaded_at DESC, gp.id DESC
    ";
    $resultPhotos = mysqli_query($conn, $sqlPhotos);

    if ($resultPhotos) {
        while ($row = mysqli_fetch_assoc($resultPhotos)) {
            $photos[] = $row;
        }
    }

    $sqlThemeCategories = "
        SELECT id, category_name, created_at
        FROM theme_categories
        ORDER BY category_name ASC
    ";
    $resultThemeCategories = mysqli_query($conn, $sqlThemeCategories);

    if ($resultThemeCategories) {
        while ($row = mysqli_fetch_assoc($resultThemeCategories)) {
            $theme_categories[] = $row;
        }
    }

    $sqlThemes = "
        SELECT 
            gt.id,
            gt.category_id,
            tc.category_name AS category,
            gt.theme_name,
            gt.theme_photo,
            gt.theme_details,
            gt.is_active,
            gt.pick_count,
            gt.created_at,
            gt.updated_at
        FROM gallery_themes gt
        INNER JOIN theme_categories tc ON gt.category_id = tc.id
        ORDER BY gt.pick_count DESC, gt.created_at DESC, gt.id DESC
    ";
    $resultThemes = mysqli_query($conn, $sqlThemes);

    if ($resultThemes) {
        while ($row = mysqli_fetch_assoc($resultThemes)) {
            $themes[] = $row;
        }
    }

    respond('success', 'Gallery loaded successfully.', [
        'albums' => $albums,
        'photos' => $photos,
        'themes' => $themes,
        'theme_categories' => $theme_categories
    ]);
}

if ($action === 'create_album') {
    $album_name = clean($conn, $_POST['album_name'] ?? '');
    $category = clean($conn, $_POST['category'] ?? '');
    $description = clean($conn, $_POST['description'] ?? '');

    if ($album_name === '' || $category === '') {
        respond('error', 'Album name and category are required.');
    }

    $check = mysqli_query($conn, "SELECT id FROM gallery_albums WHERE album_name = '$album_name' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        respond('error', 'Album name already exists.');
    }

    $sql = "
        INSERT INTO gallery_albums (album_name, category, description)
        VALUES ('$album_name', '$category', '$description')
    ";

    if (mysqli_query($conn, $sql)) {
        respond('success', 'Album created successfully.');
    } else {
        respond('error', 'Failed to create album: ' . mysqli_error($conn));
    }
}

if ($action === 'delete_album') {
    $album_id = (int)($_POST['album_id'] ?? 0);

    if ($album_id <= 0) {
        respond('error', 'Invalid album ID.');
    }

    $photoQuery = mysqli_query($conn, "SELECT photo_path FROM gallery_photos WHERE album_id = $album_id");
    if ($photoQuery) {
        while ($photo = mysqli_fetch_assoc($photoQuery)) {
            $fullPath = '../' . $photo['photo_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }

    if (mysqli_query($conn, "DELETE FROM gallery_albums WHERE id = $album_id")) {
        respond('success', 'Album deleted successfully.');
    } else {
        respond('error', 'Failed to delete album: ' . mysqli_error($conn));
    }
}

if ($action === 'upload_photos') {
    $album_id = (int)($_POST['album_id'] ?? 0);

    if ($album_id <= 0) {
        respond('error', 'Invalid album selected.');
    }

    if (!isset($_FILES['photos']) || empty($_FILES['photos']['name'][0])) {
        respond('error', 'No files selected.');
    }

    $albumCheck = mysqli_query($conn, "SELECT id FROM gallery_albums WHERE id = $album_id LIMIT 1");
    if (!$albumCheck || mysqli_num_rows($albumCheck) === 0) {
        respond('error', 'Selected album does not exist.');
    }

    $uploadDir = '../uploads/gallery/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4'];
    $maxFileSize = 20 * 1024 * 1024;

    $uploadedCount = 0;

    foreach ($_FILES['photos']['tmp_name'] as $index => $tmpName) {
        if ($_FILES['photos']['error'][$index] !== UPLOAD_ERR_OK) {
            continue;
        }

        if (!is_uploaded_file($tmpName)) {
            continue;
        }

        $originalName = $_FILES['photos']['name'][$index];
        $fileSize = $_FILES['photos']['size'][$index];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = mime_content_type($tmpName);

        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            continue;
        }

        if ($fileSize > $maxFileSize) {
            continue;
        }

        $fileType = 'image';
        if ((function_exists('str_starts_with') && str_starts_with($mimeType, 'video/')) || $extension === 'mp4') {
            $fileType = 'video';
        }

        $safeBaseName = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $newFileName = $safeBaseName . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $extension;

        $relativePath = 'uploads/gallery/' . $newFileName;
        $fullPath = '../' . $relativePath;

        if (move_uploaded_file($tmpName, $fullPath)) {
            $safeOriginalName = clean($conn, $originalName);
            $safeRelativePath = clean($conn, $relativePath);
            $safeFileType = clean($conn, $fileType);

            $insert = "
                INSERT INTO gallery_photos (album_id, photo_path, original_name, file_type)
                VALUES ($album_id, '$safeRelativePath', '$safeOriginalName', '$safeFileType')
            ";

            if (mysqli_query($conn, $insert)) {
                $uploadedCount++;
            }
        }
    }

    if ($uploadedCount > 0) {
        respond('success', $uploadedCount . ' media file(s) uploaded successfully.');
    } else {
        respond('error', 'No valid media files were uploaded.');
    }
}

if ($action === 'delete_photo') {
    $photo_id = (int)($_POST['photo_id'] ?? 0);

    if ($photo_id <= 0) {
        respond('error', 'Invalid photo ID.');
    }

    $query = mysqli_query($conn, "SELECT photo_path FROM gallery_photos WHERE id = $photo_id LIMIT 1");
    $photo = $query ? mysqli_fetch_assoc($query) : null;

    if (!$photo) {
        respond('error', 'Photo not found.');
    }

    $fullPath = '../' . $photo['photo_path'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    if (mysqli_query($conn, "DELETE FROM gallery_photos WHERE id = $photo_id")) {
        respond('success', 'Photo deleted successfully.');
    } else {
        respond('error', 'Failed to delete photo.');
    }
}

if ($action === 'create_theme_category') {
    $category_name = clean($conn, $_POST['category_name'] ?? '');

    if ($category_name === '') {
        respond('error', 'Category name is required.');
    }

    $check = mysqli_query($conn, "SELECT id FROM theme_categories WHERE category_name = '$category_name' LIMIT 1");
    if ($check && mysqli_num_rows($check) > 0) {
        respond('error', 'Theme category already exists.');
    }

    $sql = "INSERT INTO theme_categories (category_name) VALUES ('$category_name')";

    if (mysqli_query($conn, $sql)) {
        respond('success', 'Theme category created successfully.');
    } else {
        respond('error', 'Failed to create theme category: ' . mysqli_error($conn));
    }
}

if ($action === 'create_theme') {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $theme_name = clean($conn, $_POST['theme_name'] ?? '');
    $theme_details = clean($conn, $_POST['theme_details'] ?? '');

    if ($category_id <= 0 || $theme_name === '') {
        respond('error', 'Category and theme name are required.');
    }

    if (!isset($_FILES['theme_photo'])) {
        respond('error', 'Theme photo is required.');
    }

    $checkCategory = mysqli_query($conn, "SELECT id FROM theme_categories WHERE id = $category_id LIMIT 1");
    if (!$checkCategory || mysqli_num_rows($checkCategory) === 0) {
        respond('error', 'Selected theme category does not exist.');
    }

    $checkTheme = mysqli_query(
        $conn,
        "SELECT id FROM gallery_themes
         WHERE category_id = $category_id AND theme_name = '$theme_name'
         LIMIT 1"
    );

    if ($checkTheme && mysqli_num_rows($checkTheme) > 0) {
        respond('error', 'This theme already exists in that category.');
    }

    $upload = uploadImageFile($_FILES['theme_photo'], '../uploads/themes/');

    if (!$upload['status']) {
        respond('error', $upload['message']);
    }

    $theme_photo = clean($conn, $upload['relative_path']);

    $sql = "
        INSERT INTO gallery_themes (category_id, theme_name, theme_photo, theme_details, is_active, pick_count)
        VALUES ($category_id, '$theme_name', '$theme_photo', '$theme_details', 1, 0)
    ";

    if (mysqli_query($conn, $sql)) {
        respond('success', 'Theme created successfully.');
    } else {
        $fullPath = '../' . $theme_photo;
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        respond('error', 'Failed to create theme: ' . mysqli_error($conn));
    }
}

if ($action === 'toggle_theme_status') {
    $theme_id = (int)($_POST['theme_id'] ?? 0);
    $is_active = (int)($_POST['is_active'] ?? -1);

    if ($theme_id <= 0) {
        respond('error', 'Invalid theme ID.');
    }

    if (!in_array($is_active, [0, 1], true)) {
        respond('error', 'Invalid theme status.');
    }

    $checkTheme = mysqli_query($conn, "SELECT id FROM gallery_themes WHERE id = $theme_id LIMIT 1");
    if (!$checkTheme || mysqli_num_rows($checkTheme) === 0) {
        respond('error', 'Theme not found.');
    }

    $sql = "UPDATE gallery_themes SET is_active = $is_active WHERE id = $theme_id";

    if (mysqli_query($conn, $sql)) {
        respond('success', 'Theme status updated successfully.');
    } else {
        respond('error', 'Failed to update theme status: ' . mysqli_error($conn));
    }
}

if ($action === 'delete_theme') {
    $theme_id = (int)($_POST['theme_id'] ?? 0);

    if ($theme_id <= 0) {
        respond('error', 'Invalid theme ID.');
    }

    $themeQuery = mysqli_query($conn, "SELECT theme_photo FROM gallery_themes WHERE id = $theme_id LIMIT 1");
    $theme = $themeQuery ? mysqli_fetch_assoc($themeQuery) : null;

    if (!$theme) {
        respond('error', 'Theme not found.');
    }

    $fullPath = '../' . $theme['theme_photo'];
    if (file_exists($fullPath)) {
        unlink($fullPath);
    }

    if (mysqli_query($conn, "DELETE FROM gallery_themes WHERE id = $theme_id")) {
        respond('success', 'Theme deleted successfully.');
    } else {
        respond('error', 'Failed to delete theme: ' . mysqli_error($conn));
    }
}

if ($action === 'delete_theme_category') {
    $category_id = (int)($_POST['category_id'] ?? 0);

    if ($category_id <= 0) {
        respond('error', 'Invalid category ID.');
    }

    $check = mysqli_query($conn, "SELECT id FROM theme_categories WHERE id = $category_id LIMIT 1");
    if (!$check || mysqli_num_rows($check) === 0) {
        respond('error', 'Theme category not found.');
    }

    $themeFiles = [];
    $themeQuery = mysqli_query($conn, "
        SELECT theme_photo 
        FROM gallery_themes 
        WHERE category_id = $category_id
    ");

    if ($themeQuery) {
        while ($row = mysqli_fetch_assoc($themeQuery)) {
            if (!empty($row['theme_photo'])) {
                $themeFiles[] = $row['theme_photo'];
            }
        }
    }

    if (mysqli_query($conn, "DELETE FROM theme_categories WHERE id = $category_id")) {

        // Delete image files
        foreach ($themeFiles as $file) {
            $fullPath = '../' . $file;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        respond('success', 'Theme category deleted successfully.');
    } else {
        respond('error', 'Failed to delete category: ' . mysqli_error($conn));
    }
}

respond('error', 'Invalid action.');
?>