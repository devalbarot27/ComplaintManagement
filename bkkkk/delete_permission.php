<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/permission_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid permission record.';
    header('Location: permissions.php');
    exit;
}

try {
    if (!permission_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Permission not found or already deleted.';
        header('Location: permissions.php');
        exit;
    }

    permission_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Permission deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete permission.';
}

header('Location: permissions.php');
exit;
