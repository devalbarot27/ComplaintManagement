<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_master_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);

if (!customer_master_action_permissions($obconn)['delete']) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to delete customers.';
    header('Location: customer_master.php');
    exit;
}

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: customer_master.php');
    exit;
}

try {
    if (!customer_master_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: customer_master.php');
        exit;
    }

    customer_master_soft_delete($obconn, $id, current_username());
    $_SESSION['success_message'] = 'Customer deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete customer.';
}

header('Location: customer_master.php');
exit;
