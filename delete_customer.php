<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_helpers.php';

require_system_admin($obconn);

$cuno = trim((string) base64_decode($_GET['id'] ?? '', true));

if ($cuno === '') {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: customers.php');
    exit;
}

try {
    if (!customer_get_by_cuno($obconn, $cuno)) {
        $_SESSION['error_message'] = 'Customer not found or already deleted.';
        header('Location: customers.php');
        exit;
    }

    customer_delete($obconn, $cuno);
    $_SESSION['success_message'] = 'Customer deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete customer.';
}

header('Location: customers.php');
exit;
