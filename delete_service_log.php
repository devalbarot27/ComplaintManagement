<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/after_market_access_helpers.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid service log record.';
    header('Location: service_log.php');
    exit;
}

if (!after_market_user_can_access_record($obconn, 'service_logs', $id)) {
    $_SESSION['error_message'] = 'Record not found or already deleted.';
    header('Location: service_log.php');
    exit;
}

try {
    $stmt = $obconn->prepare('
        UPDATE service_logs
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['success_message'] = 'Service log deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete service log.';
}

header('Location: service_log.php');
exit;
