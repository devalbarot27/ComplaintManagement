<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/contact_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

admin_ensure_session_role($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

if (!contact_action_permissions($obconn)['delete']) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to delete contacts.';
    header('Location: contact.php');
    exit;
}

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: contact.php');
    exit;
}

try {
    if (!contact_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: contact.php');
        exit;
    }

    contact_soft_delete($obconn, $id, current_username());
    $_SESSION['success_message'] = 'Contact deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete contact.';
}

header('Location: contact.php');
exit;
