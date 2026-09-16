<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/amc_helpers.php';

amc_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid AMC contract.';
    header('Location: amc.php');
    exit;
}

if (!amc_action_permissions($obconn)['delete']) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to delete AMC contracts.';
    header('Location: amc.php');
    exit;
}

try {
    $stmt = $obconn->prepare('
        UPDATE amc_contracts
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['success_message'] = 'AMC contract deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete AMC contract.';
}

header('Location: amc.php');
exit;
