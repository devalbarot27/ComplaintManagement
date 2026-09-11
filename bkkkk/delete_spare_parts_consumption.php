<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/after_market_access_helpers.php';
require_once 'includes/spare_parts_helpers.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid spare parts record.';
    header('Location: spare_parts_consumption.php');
    exit;
}

if (!after_market_user_can_access_record($obconn, 'spare_parts_consumption', $id)) {
    $_SESSION['error_message'] = 'Record not found or already deleted.';
    header('Location: spare_parts_consumption.php');
    exit;
}

try {
    $stmt = $obconn->prepare('
        UPDATE spare_parts_consumption
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    spare_parts_soft_delete_items_for_consumption($obconn, $id);

    $_SESSION['success_message'] = 'Spare parts record deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete spare parts record.';
}

header('Location: spare_parts_consumption.php');
exit;
