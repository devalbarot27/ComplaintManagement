<?php
session_start();
include 'pdo_obconn.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid spare parts record.';
    header('Location: spare_parts_consumption.php');
    exit;
}

try {
    $checkStmt = $obconn->prepare('
        SELECT id FROM spare_parts_consumption
        WHERE id = :id AND deleted_at IS NULL
    ');
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();

    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error_message'] = 'Record not found or already deleted.';
        header('Location: spare_parts_consumption.php');
        exit;
    }

    $stmt = $obconn->prepare('
        UPDATE spare_parts_consumption
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $_SESSION['success_message'] = 'Spare parts record deleted successfully.';
} catch (PDOException $e) {
    $_SESSION['error_message'] = 'Failed to delete spare parts record.';
}

header('Location: spare_parts_consumption.php');
exit;
