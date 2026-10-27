<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/help_support_helpers.php';

help_support_ensure_schema($obconn);
help_support_ensure_rbac($obconn);

if (!help_support_action_permissions($obconn)['delete']) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to delete Help & Support items.';
    header('Location: help_support.php');
    exit;
}

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: help_support.php');
    exit;
}

try {
    $record = help_support_get_by_id($obconn, $id);
    if ($record === null) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: help_support.php');
        exit;
    }

    help_support_soft_delete($obconn, $id);
    help_support_delete_stored_file($record['attachment_path'] ?? '');
    $_SESSION['success_message'] = 'Help & Support item deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete Help & Support item.';
}

header('Location: help_support.php');
exit;
