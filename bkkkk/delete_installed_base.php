<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/after_market_access_helpers.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid installed base record.';
    header('Location: installed_base.php');
    exit;
}

if (!after_market_user_can_access_record($obconn, 'installed_base', $id)) {
    $_SESSION['error_message'] = 'Record not found or already deleted.';
    header('Location: installed_base.php');
    exit;
}

try {
    $stmt = $obconn->prepare('
        UPDATE installed_base
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['success_message'] = 'Installed base record deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete installed base record.';
}

header('Location: installed_base.php');
exit;
