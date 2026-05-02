<?php
session_name('ADMINSESSID');
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $menu_id = (int)($_POST['menu_id'] ?? 0);
    $menu_name = trim($_POST['menu_name'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $price_unit = trim($_POST['price_unit'] ?? 'Per Head');
    $description = trim($_POST['description'] ?? '');
    $menuItemsJson = $_POST['menu_items_json'] ?? '{}';

    if ($menu_name === '') {
        header("Location: menu_management.php?error=missing_name");
        exit();
    }

    if ($price <= 0) {
        header("Location: menu_management.php?error=invalid_price");
        exit();
    }

    if ($price_unit === '') {
        $price_unit = 'Per Head';
    }

    $itemsData = json_decode($menuItemsJson, true);
    if (!is_array($itemsData)) {
        $itemsData = [];
    }

    mysqli_begin_transaction($conn);

    try {
        if ($menu_id > 0) {
            $sql = "UPDATE menus 
                    SET menu_name = ?, price = ?, price_unit = ?, description = ? 
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception("Prepare failed for menu update: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "sdssi", $menu_name, $price, $price_unit, $description, $menu_id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating menu: " . mysqli_error($conn));
            }
        } else {
            $sql = "INSERT INTO menus (menu_name, price, price_unit, description) 
                    VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                throw new Exception("Prepare failed for menu insert: " . mysqli_error($conn));
            }

            mysqli_stmt_bind_param($stmt, "sdss", $menu_name, $price, $price_unit, $description);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error inserting menu: " . mysqli_error($conn));
            }

            $menu_id = mysqli_insert_id($conn);
        }

        // Delete old items first
        $deleteItemsSql = "DELETE FROM menu_items WHERE menu_id = ?";
        $stmtDelete = mysqli_prepare($conn, $deleteItemsSql);

        if (!$stmtDelete) {
            throw new Exception("Prepare failed for deleting old menu items: " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmtDelete, "i", $menu_id);

        if (!mysqli_stmt_execute($stmtDelete)) {
            throw new Exception("Error deleting old menu items: " . mysqli_error($conn));
        }

        // Insert current items
        $insertItemSql = "INSERT INTO menu_items (menu_id, category, item_name) VALUES (?, ?, ?)";
        $stmtItem = mysqli_prepare($conn, $insertItemSql);

        if (!$stmtItem) {
            throw new Exception("Prepare failed for menu_items insert: " . mysqli_error($conn));
        }

        foreach ($itemsData as $category => $items) {
            $category = trim((string)$category);

            if ($category === '' || !is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $item = trim((string)$item);

                if ($item !== '') {
                    mysqli_stmt_bind_param($stmtItem, "iss", $menu_id, $category, $item);

                    if (!mysqli_stmt_execute($stmtItem)) {
                        throw new Exception("Error inserting menu item: " . mysqli_error($conn));
                    }
                }
            }
        }

        mysqli_commit($conn);

        header("Location: menu_management.php?success=saved");
        exit();

    } catch (Exception $e) {
        mysqli_rollback($conn);
        die($e->getMessage());
    }
}

header("Location: menu_management.php");
exit();
?>