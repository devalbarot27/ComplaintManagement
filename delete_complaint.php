<?php
 
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/complaint_status.php';
 
$id = (int)base64_decode($_GET['id'] ?? '', true);
 
if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid complaint.';
    header('Location: new_complaint.php');
    exit;
}

if (!complaint_user_can_access_entry_complaint($obconn, $id)) {
    $_SESSION['error_message'] = 'Access denied. You do not have permission to delete this complaint.';
    header('Location: new_complaint.php');
    exit;
}
 
try {
    $checkStmt = $obconn->prepare("
        SELECT id
        FROM complaints
        WHERE id = :id
        AND deleted_at IS NULL
    ");
    $checkStmt->bindValue(':id', $id, PDO::PARAM_INT);
    $checkStmt->execute();
 
    if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
        $_SESSION['error_message'] = 'Complaint not found or already deleted.';
        header('Location: new_complaint.php');
        exit;
    }
 
    $obconn->beginTransaction();
 
    complaint_log_activity(
        $obconn,
        $id,
        'Deleted',
        'Complaint deleted from Complaint History.',
        1
    );
 
    $stmt = $obconn->prepare("
        UPDATE complaints
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE id = :id
        AND deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
 
    $obconn->commit();
 
    $_SESSION['success_message'] = 'Complaint deleted successfully.';
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    $_SESSION['error_message'] = 'Failed to delete complaint.';
}
 
header('Location: new_complaint.php');
exit;
 
 

/*
session_start();
include 'pdo_obconn.php';

$id = (int)base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    $_SESSION['error_message'] = 'Invalid complaint.';
    header('Location: index.php');
    exit;
}

$stmt = $obconn->prepare("
    UPDATE complaints
    SET deleted_at = CURRENT_TIMESTAMP
    WHERE id = :id
    AND deleted_at IS NULL
");

$stmt->bindValue(':id', $id, PDO::PARAM_INT);

if ($stmt->execute() && $stmt->rowCount() > 0) {
    $_SESSION['success_message'] = 'Complaint deleted successfully.';
} else {
    $_SESSION['error_message'] = 'Complaint not found or already deleted.';
}

header('Location: new_complaint.php');
exit;
*/