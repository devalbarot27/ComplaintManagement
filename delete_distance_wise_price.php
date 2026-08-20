<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/distance_wise_price_helpers.php';

require_system_admin($obconn);
distance_wise_price_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: distance_wise_prices.php');
    exit;
}

try {
    if (!distance_wise_price_get_by_id($obconn, $id)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: distance_wise_prices.php');
        exit;
    }

    distance_wise_price_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Distance wise price deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete distance wise price.';
}

header('Location: distance_wise_prices.php');
exit;