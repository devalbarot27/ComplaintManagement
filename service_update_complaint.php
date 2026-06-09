<?php
 
session_start();
include 'pdo_obconn.php';
include 'includes/complaint_activity_helpers.php';
include 'includes/complaint_status.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$complaint_id = (int) ($_POST['complaint_id'] ?? 0);
$customer_visit_date = trim($_POST['customer_visit_date'] ?? '');
$complaint_action_taken = trim($_POST['complaint_action_taken'] ?? '');
$part_replaced = trim($_POST['part_replaced'] ?? '');
 
if ($complaint_id <= 0 || $customer_visit_date === '' || $complaint_action_taken === '') {
    $_SESSION['error_message'] = 'Customer visit date and complaint action taken are required.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}

$visitDate = DateTime::createFromFormat('Y-m-d', $customer_visit_date);
$today = new DateTime('today');

if (!$visitDate || $visitDate->format('Y-m-d') !== $customer_visit_date) {
    $_SESSION['error_message'] = 'Invalid customer visit date.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}


 
if (
    !isset($_FILES['service_report'])
    || (int) $_FILES['service_report']['error'] !== UPLOAD_ERR_OK
) {
    $_SESSION['error_message'] = 'Service report upload is required.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$originalName = (string) $_FILES['service_report']['name'];
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
 
if (!in_array($extension, $allowedExtensions, true)) {
    $_SESSION['error_message'] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$maxFileSize = 2 * 1024 * 1024;
if ((int) $_FILES['service_report']['size'] > $maxFileSize) {
    $_SESSION['error_message'] = 'Service report must be 2 MB or smaller.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$storedFileName = null;
$targetPath = null;
 
try {
    $complaintStmt = $obconn->prepare("
        SELECT id, status
        FROM complaints
        WHERE id = :id
        AND deleted_at IS NULL
    ");
    $complaintStmt->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $complaintStmt->execute();
    $complaint = $complaintStmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$complaint) {
        $_SESSION['error_message'] = 'Complaint not found.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    if (!in_array((int) $complaint['status'], [COMPLAINT_STATUS_IN_PROGRESS, COMPLAINT_STATUS_REOPEN], true)) {
        $_SESSION['error_message'] = 'Service update is only allowed for complaints in progress or re-open.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $assignmentStmt = $obconn->prepare("
        SELECT id
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
        ORDER BY assign_complaint_datetime DESC
        LIMIT 1
    ");
    $assignmentStmt->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $assignmentStmt->execute();
    $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$assignment) {
        $_SESSION['error_message'] = 'No assignment found for this complaint.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $assignment_id = (int) $assignment['id'];
 
    $uploadDir = __DIR__ . '/uploads/service_reports/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        $_SESSION['error_message'] = 'Unable to create upload directory.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $storedFileName = uniqid('service_report_', true) . '.' . $extension;
    $targetPath = $uploadDir . $storedFileName;
 
    if (!move_uploaded_file($_FILES['service_report']['tmp_name'], $targetPath)) {
        $_SESSION['error_message'] = 'Unable to save uploaded file.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $created_by = 1;
 
    $obconn->beginTransaction();
 
    $insert = $obconn->prepare("
        INSERT INTO complaint_service_updates
        (
            complaint_id,
            assignment_id,
            customer_visit_date,
            complaint_action_taken,
            part_replaced,
            service_report,
            created_by
        )
        VALUES
        (
            :complaint_id,
            :assignment_id,
            :customer_visit_date,
            :complaint_action_taken,
            :part_replaced,
            :service_report,
            :created_by
        )
    ");
 
    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':assignment_id', $assignment_id, PDO::PARAM_INT);
    $insert->bindValue(':customer_visit_date', $customer_visit_date);
    $insert->bindValue(':complaint_action_taken', $complaint_action_taken);
    $insert->bindValue(':part_replaced', $part_replaced !== '' ? $part_replaced : null);
    $insert->bindValue(':service_report', $storedFileName);
    $insert->bindValue(':created_by', $created_by, PDO::PARAM_INT);
    $insert->execute();

    $assignmentUpdate = $obconn->prepare('
        UPDATE complaint_assignments
        SET is_service_updated = 1
        WHERE id = :id
    ');
    $assignmentUpdate->bindValue(':id', $assignment_id, PDO::PARAM_INT);
    $assignmentUpdate->execute();
 
    $update = $obconn->prepare('
        UPDATE complaints
        SET status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ');
    $update->bindValue(':status', COMPLAINT_STATUS_PENDING_HO, PDO::PARAM_INT);
    $update->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $update->execute();

    $activityDescription = 'Service update recorded for customer visit on '
        . date('d M Y', strtotime($customer_visit_date))
        . '. Action taken: ' . $complaint_action_taken
        . '. Status changed to Pending With HO.';
 
    if ($part_replaced !== '') {
        $activityDescription .= '. Part replaced: ' . $part_replaced;
    }
 
    if ($storedFileName !== null) {
        $activityDescription .= '. Service report uploaded.';
    }
 
    complaint_log_activity(
        $obconn,
        $complaint_id,
        'Service Update',
        $activityDescription,
        $created_by
    );
 
    $obconn->commit();
 
   // $_SESSION['success_message'] = 'Service update saved successfully.';
    $_SESSION['success_message'] = 'Service update is currently pending with HO (Head Office) for approval.';
    
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
 
    if (!empty($storedFileName) && isset($targetPath) && is_file($targetPath)) {
        unlink($targetPath);
    }
 
    $_SESSION['error_message'] = 'Failed to save service update.';
}
 
header('Location: dse_lse_complaint_list.php');
exit;
 
 
/*
session_start();
include 'pdo_obconn.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$complaint_id = (int) ($_POST['complaint_id'] ?? 0);
$customer_visit_date = trim($_POST['customer_visit_date'] ?? '');
$complaint_action_taken = trim($_POST['complaint_action_taken'] ?? '');
$part_replaced = trim($_POST['part_replaced'] ?? '');
 
if ($complaint_id <= 0 || $customer_visit_date === '' || $complaint_action_taken === '') {
    $_SESSION['error_message'] = 'Customer visit date and complaint action taken are required.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
if (
    !isset($_FILES['service_report'])
    || (int) $_FILES['service_report']['error'] !== UPLOAD_ERR_OK
) {
    $_SESSION['error_message'] = 'Service report upload is required.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$originalName = (string) $_FILES['service_report']['name'];
$extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
 
if (!in_array($extension, $allowedExtensions, true)) {
    $_SESSION['error_message'] = 'Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$maxFileSize = 5 * 1024 * 1024;
if ((int) $_FILES['service_report']['size'] > $maxFileSize) {
    $_SESSION['error_message'] = 'Service report must be 5 MB or smaller.';
    header('Location: dse_lse_complaint_list.php');
    exit;
}
 
$storedFileName = null;
$targetPath = null;
 
try {
    $complaintStmt = $obconn->prepare("
        SELECT id, status
        FROM complaints
        WHERE id = :id
        AND deleted_at IS NULL
    ");
    $complaintStmt->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $complaintStmt->execute();
    $complaint = $complaintStmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$complaint) {
        $_SESSION['error_message'] = 'Complaint not found.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    if (!in_array((int) $complaint['status'], [COMPLAINT_STATUS_IN_PROGRESS, COMPLAINT_STATUS_REOPEN], true)) {
        $_SESSION['error_message'] = 'Service update is only allowed for complaints in progress or re-open.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $assignmentStmt = $obconn->prepare("
        SELECT id
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
        ORDER BY assign_complaint_datetime DESC
        LIMIT 1
    ");
    $assignmentStmt->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $assignmentStmt->execute();
    $assignment = $assignmentStmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$assignment) {
        $_SESSION['error_message'] = 'No assignment found for this complaint.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $assignment_id = (int) $assignment['id'];
 
    $uploadDir = __DIR__ . '/uploads/service_reports/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        $_SESSION['error_message'] = 'Unable to create upload directory.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 

    $storedFileName = uniqid('service_report_', true) . '.' . $extension;
    $targetPath = $uploadDir . $storedFileName;

//echo $_FILES['service_report']['tmp_name'];
//echo ' - '.$targetPath;
//die();
 
    if (!move_uploaded_file($_FILES['service_report']['tmp_name'], $targetPath)) {
        $_SESSION['error_message'] = 'Unable to save uploaded file.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $created_by = 1;
 
    $obconn->beginTransaction();
 
    $insert = $obconn->prepare("
        INSERT INTO complaint_service_updates
        (
            complaint_id,
            assignment_id,
            customer_visit_date,
            complaint_action_taken,
            part_replaced,
            service_report,
            created_by
        )
        VALUES
        (
            :complaint_id,
            :assignment_id,
            :customer_visit_date,
            :complaint_action_taken,
            :part_replaced,
            :service_report,
            :created_by
        )
    ");
 
    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':assignment_id', $assignment_id, PDO::PARAM_INT);
    $insert->bindValue(':customer_visit_date', $customer_visit_date);
    $insert->bindValue(':complaint_action_taken', $complaint_action_taken);
    $insert->bindValue(':part_replaced', $part_replaced !== '' ? $part_replaced : null);
    $insert->bindValue(':service_report', $storedFileName);
    $insert->bindValue(':created_by', $created_by, PDO::PARAM_INT);
    $insert->execute();
 
    $update = $obconn->prepare("
        UPDATE complaints
        SET updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $update->execute();
 
    $log = $obconn->prepare("
        INSERT INTO complaint_activity_logs
        (
            complaint_id,
            activity_type,
            activity_description,
            user_id
        )
        VALUES
        (
            :complaint_id,
            'Service Update',
            :activity_description,
            :user_id
        )
    ");
 
    $log->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $log->bindValue(':activity_description', 'Service update recorded for visit on ' . $customer_visit_date);
    $log->bindValue(':user_id', $created_by, PDO::PARAM_INT);
    $log->execute();
 
    $obconn->commit();
 
    $_SESSION['success_message'] = 'Service update saved successfully.';
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
 
    if (!empty($storedFileName) && isset($targetPath) && is_file($targetPath)) {
        unlink($targetPath);
    }
 
    $_SESSION['error_message'] = 'Failed to save service update.';
}
 
header('Location: dse_lse_complaint_list.php');
exit;
*/