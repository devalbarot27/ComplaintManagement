<?php
 
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/complaint_assignment_helpers.php';
require_once 'includes/complaint_datatable_helpers.php';
include 'includes/service_report_helpers.php';
require_once 'includes/complaint_service_log_helpers.php'; // 10-07-26

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    rbac_access_denied_redirect();
}

complaint_assigned_require_permission($obconn, 'service-update');
 
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

// Start 10-07-26
/*
$serviceLogError = complaint_service_log_validate_for_service_update($obconn, $complaint_id);
*/
require_once 'includes/complaint_service_update_save_helpers.php';
$serviceLogError = complaint_service_update_validate_service_log($obconn, $complaint_id);
if ($serviceLogError !== null) {
    $_SESSION['error_message'] = $serviceLogError;
    header('Location: dse_lse_complaint_list.php');
    exit;
}
// END

$uploadError = service_report_validate_uploads($_FILES['service_report'] ?? null);

if ($uploadError !== null) {
    $_SESSION['error_message'] = $uploadError;
    header('Location: dse_lse_complaint_list.php');
    exit;
}

$uploadFiles = service_report_normalize_uploads($_FILES['service_report'] ?? null);

$storedFileNames = null;
$storedPaths = [];
 
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

    if (!complaint_user_can_access_assigned_complaint($obconn, $complaint_id)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to update this complaint.';
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
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $_SESSION['error_message'] = 'Unable to create upload directory.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }

    try {
        $uploadResult = service_report_store_uploads($uploadFiles, $uploadDir);
        $storedFileNames = $uploadResult['stored'];
        $storedPaths = $uploadResult['paths'];
    } catch (RuntimeException $e) {
        service_report_delete_files($storedPaths);
        $_SESSION['error_message'] = $e->getMessage();
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
 
    $created_by = current_user_id($obconn);
    if ($created_by === null || $created_by <= 0) {
        $_SESSION['error_message'] = 'Unable to resolve logged-in user.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }

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
            created_by,
            username
        )
        VALUES
        (
            :complaint_id,
            :assignment_id,
            :customer_visit_date,
            :complaint_action_taken,
            :part_replaced,
            :service_report,
            :created_by,
            :username
        )
    ");
 
    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':assignment_id', $assignment_id, PDO::PARAM_INT);
    $insert->bindValue(':customer_visit_date', $customer_visit_date);
    $insert->bindValue(':complaint_action_taken', $complaint_action_taken);
    $insert->bindValue(':part_replaced', $part_replaced !== '' ? $part_replaced : null);
    $insert->bindValue(':service_report', $storedFileNames);
    $insert->bindValue(':created_by', $created_by, PDO::PARAM_INT);
    $insert->bindValue(':username', current_username());
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
 
    if ($storedFileNames !== null) {
        $reportCount = count(service_report_parse_filenames($storedFileNames));
        $activityDescription .= $reportCount === 1
            ? '. Service report uploaded.'
            : '. Service reports uploaded (' . $reportCount . ' files).';
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

    service_report_delete_files($storedPaths);

    $_SESSION['error_message'] = 'Failed to save service update.';
}

header('Location: dse_lse_complaint_list.php');
exit;