<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/module_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid module record.';
    header('Location: modules.php');
    exit;
}

try {
    if (!module_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Module not found or already deleted.';
        header('Location: modules.php');
        exit;
    }

    module_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Module deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete module.';
}

header('Location: modules.php');
exit;
