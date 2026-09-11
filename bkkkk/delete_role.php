<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/role_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid role record.';
    header('Location: roles.php');
    exit;
}

try {
    if (!role_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Role not found or already deleted.';
        header('Location: roles.php');
        exit;
    }

    role_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Role deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete role.';
}

header('Location: roles.php');
exit;
