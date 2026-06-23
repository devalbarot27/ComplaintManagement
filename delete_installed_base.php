<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid installed base record.';
    header('Location: installed_base.php');
    exit;
}

try {
    $checkStmt = $obconn->prepare('
        SELECT id
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: installed_base.php');
        exit;
    }

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
