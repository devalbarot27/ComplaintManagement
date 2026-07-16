<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/complaint_category_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: complaint_categories.php');
    exit;
}

try {
    if (!complaint_category_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Complaint category not found or already deleted.';
        header('Location: complaint_categories.php');
        exit;
    }

    complaint_category_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Complaint category deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete complaint category.';
}

header('Location: complaint_categories.php');
exit;