<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_approval_configuration_helpers.php';

require_system_admin($obconn);
user_approval_config_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: user_approval_configurations.php');
    exit;
}

try {
    if (!user_approval_config_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: user_approval_configurations.php');
        exit;
    }

    user_approval_config_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'User approval configuration deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete user approval configuration.';
}

header('Location: user_approval_configurations.php');
exit;
