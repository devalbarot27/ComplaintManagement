<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/warranty_claims_helpers.php';

warranty_claims_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid record.';
    header('Location: service_claims.php');
    exit;
}

try {
    $record = service_claim_get_by_id($obconn, $id);
    if (!$record) {
        $_SESSION['error_message'] = 'Service claim not found or already deleted.';
        header('Location: service_claims.php');
        exit;
    }

    if (!service_claims_user_can_access_claim($obconn, $record)) {
        $_SESSION['error_message'] = 'Access denied. You cannot delete this service claim.';
        header('Location: service_claims.php');
        exit;
    }

    service_claim_soft_delete($obconn, $id);
    $_SESSION['success_message'] = 'Service claim deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete service claim.';
}

header('Location: service_claims.php');
exit;