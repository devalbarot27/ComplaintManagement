<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/cdoc_helpers.php';
cdoc_ensure_schema($obconn);
if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}
require_once 'includes/rbac_page_guard.php';

$id = cdoc_decoded_id($_GET['id'] ?? '');

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid document.';
    header('Location: documentation.php');
    exit;
}

if (empty(cdoc_action_permissions($obconn)['delete'])) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to remove documents.';
    header('Location: documentation.php');
    exit;
}

$document = cdoc_find_by_id($obconn, $id);
if (!$document) {
    $_SESSION['error_message'] = 'Document not found or already removed.';
    header('Location: documentation.php');
    exit;
}

try {
    if (cdoc_soft_delete($obconn, $id)) {
        cdoc_delete_stored_file($document['stored_filename'] ?? null);
        $_SESSION['success_message'] = 'Document removed from the dealer portal.';
    } else {
        $_SESSION['error_message'] = 'Failed to remove document.';
    }
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to remove document.';
}

header('Location: documentation.php');
exit;
