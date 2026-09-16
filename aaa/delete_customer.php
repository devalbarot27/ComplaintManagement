<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: customers.php');
    exit;
}

try {
    if (!customer_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: customers.php');
        exit;
    }

    customer_soft_delete($obconn, $id, current_username());
    $_SESSION['success_message'] = 'Customer sync record deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete customer sync record.';
}

header('Location: customers.php');
exit;