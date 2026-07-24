<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/product_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);
$updatedBy = current_user_id($obconn);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: products.php');
    exit;
}

try {
    if (!product_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Product not found or already deleted.';
        header('Location: products.php');
        exit;
    }

    product_soft_delete($obconn, $id, $updatedBy);
    $_SESSION['success_message'] = 'Product deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete product.';
}

header('Location: products.php');
exit;
