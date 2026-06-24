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
    $deletedRecord = complaint_category_get_deleted_by_id($obconn, $id);
    if (!$deletedRecord) {
        $_SESSION['error_message'] = 'Deleted complaint category not found.';
        header('Location: complaint_categories.php');
        exit;
    }

    if (complaint_category_name_exists($obconn, (string) $deletedRecord['name'])) {
        $_SESSION['error_message'] = 'Cannot restore: an active category with the same name already exists.';
        header('Location: complaint_categories.php');
        exit;
    }

    complaint_category_restore($obconn, $id);
    $_SESSION['success_message'] = 'Complaint category restored successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to restore complaint category.';
}

header('Location: complaint_categories.php');
exit;
