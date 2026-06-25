<?php
session_start();
include 'pdo_obconn.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/complaint_assignment_mail_helpers.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/complaint_datatable_helpers.php';

$redirect = 'new_complaint.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}

if (!complaint_user_can_closure($obconn)) {
    $_SESSION['error_message'] = 'Access denied. Complaint closure is available to Dealer Users only.';
    header('Location: ' . $redirect);
    exit;
}
 
$complaint_id = (int) ($_POST['complaint_id'] ?? 0);
$call_closure = trim($_POST['call_closure'] ?? '');
$closure_remarks = trim($_POST['closure_remarks'] ?? '');
$reassign_assign_complaint = trim($_POST['reassign_complaint'] ?? '');
$reassign_remarks = trim($_POST['reassign_remarks'] ?? '');
 
if ($complaint_id <= 0 || !in_array($call_closure, ['Yes', 'No'], true)) {
    $_SESSION['error_message'] = 'Please select call closure Yes or No.';
    header('Location: ' . $redirect);
    exit;
}
 
if ($call_closure === 'Yes' && $closure_remarks === '') {
    $_SESSION['error_message'] = 'Closure remarks are required when call closure is Yes.';
    header('Location: ' . $redirect);
    exit;
}
 
if ($call_closure === 'No' && $reassign_assign_complaint === '') {
    $_SESSION['error_message'] = 'Reassign Assign To is required when call closure is No.';
    header('Location: ' . $redirect);
    exit;
}

if ($call_closure === 'No') {
    complaint_entry_require_permission($obconn, 'reassign-complaint');
}
 
if ($call_closure === 'No' && strlen($reassign_remarks) > 500) {
    $_SESSION['error_message'] = 'Remarks cannot exceed 500 characters.';
    header('Location: ' . $redirect);
    exit;
}

if ($call_closure === 'No' && ($assigneeError = complaint_validate_elgi_engineer_assignee($obconn, $reassign_assign_complaint)) !== null) {
    $_SESSION['error_message'] = $assigneeError;
    header('Location: ' . $redirect);
    exit;
}

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
        header('Location: ' . $redirect);
        exit;
    }
 
    if ((int) $complaint['status'] !== COMPLAINT_STATUS_PENDING_HO) {
        $_SESSION['error_message'] = 'Closure is only allowed for complaints pending with HO.';
        header('Location: ' . $redirect);
        exit;
    }
 
    $serviceStmt = $obconn->prepare("
        SELECT COUNT(*) AS total
        FROM complaint_service_updates
        WHERE complaint_id = :complaint_id
    ");
    $serviceStmt->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $serviceStmt->execute();
 
    if ((int) $serviceStmt->fetch(PDO::FETCH_ASSOC)['total'] === 0) {
        $_SESSION['error_message'] = 'Service update is required before complaint closure.';
        header('Location: ' . $redirect);
        exit;
    }
 
    $closed_by = current_user_id($obconn);
    if ($closed_by === null || $closed_by <= 0) {
        $_SESSION['error_message'] = 'Unable to resolve logged-in user.';
        header('Location: ' . $redirect);
        exit;
    }

    $userName = current_username();

    $obconn->beginTransaction();

    $insert = $obconn->prepare("
        INSERT INTO complaint_closures
        (
            complaint_id,
            call_closure,
            closure_remarks,
            reassignment_details,
            closed_by,
            username
        )
        VALUES
        (
            :complaint_id,
            :call_closure,
            :closure_remarks,
            :reassignment_details,
            :closed_by,
            :username
        )
    ");

    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':call_closure', $call_closure);
    $insert->bindValue(':closure_remarks', $call_closure === 'Yes' ? $closure_remarks : null);
    $insert->bindValue(':reassignment_details', $call_closure === 'No' ? $reassign_remarks : null);
    $insert->bindValue(':closed_by', $closed_by, PDO::PARAM_INT);
    $insert->bindValue(':username', $userName);
    $insert->execute();

    $newStatus = $call_closure === 'Yes' ? COMPLAINT_STATUS_RESOLVED : COMPLAINT_STATUS_REOPEN;

    if ($call_closure === 'No') {
        $assigned_by = $closed_by;
        $assigned_to = complaint_resolve_assignee_user_id($obconn, $reassign_assign_complaint);

        if ($assigned_to <= 0) {
            throw new PDOException('Invalid assignee.');
        }

        $assign_complaint_datetime = date('Y-m-d H:i:s');

        $assignmentInsert = $obconn->prepare("
            INSERT INTO complaint_assignments
            (
                complaint_id,
                assign_complaint,
                assigned_to,
                assign_complaint_datetime,
                remarks,
                assigned_by,
                username
            )
            VALUES
            (
                :complaint_id,
                :assign_complaint,
                :assigned_to,
                :assign_complaint_datetime,
                :remarks,
                :assigned_by,
                :username
            )
        ");

        $assignmentInsert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
        $assignmentInsert->bindValue(':assign_complaint', $reassign_assign_complaint);
        $assignmentInsert->bindValue(':assigned_to', $assigned_to, PDO::PARAM_INT);
        $assignmentInsert->bindValue(':assign_complaint_datetime', $assign_complaint_datetime);
        $assignmentInsert->bindValue(':remarks', $reassign_remarks !== '' ? $reassign_remarks : null);
        $assignmentInsert->bindValue(':assigned_by', $assigned_by, PDO::PARAM_INT);
        $assignmentInsert->bindValue(':username', $userName);
        $assignmentInsert->execute();

        $reassignActivity = 'Complaint reassigned to ' . $reassign_assign_complaint
            . ' on ' . date('d M Y, h:i A', strtotime($assign_complaint_datetime))
            . ' after closure No.';

        if ($reassign_remarks !== '') {
            $reassignActivity .= ' Remarks: ' . $reassign_remarks;
        }

        complaint_log_activity(
            $obconn,
            $complaint_id,
            'Reassignment',
            $reassignActivity,
            $assigned_by
        );
    }
 
    $update = $obconn->prepare("
        UPDATE complaints
        SET status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':status', $newStatus, PDO::PARAM_INT);
    $update->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $update->execute();
 
    if ($call_closure === 'Yes') {
        $activityDescription = 'Call closure marked Yes. Complaint resolved.';
        if ($closure_remarks !== '') {
            $activityDescription .= ' Remarks: ' . $closure_remarks;
        }
        $activityDescription .= ' Status changed to Resolved.';
    } else {
        $activityDescription = 'Call closure marked No. Complaint reassigned to '
            . $reassign_assign_complaint
            . '. Status changed to Re-Open.';
    }
 
    complaint_log_activity(
        $obconn,
        $complaint_id,
        'Closure',
        $activityDescription,
        $closed_by
    );
 
    $obconn->commit();

    if ($call_closure === 'Yes') {
        complaint_closure_notify_email($obconn, $complaint_id, $closure_remarks);
        $_SESSION['success_message'] = 'Complaint closed successfully.';
    } else {
        complaint_assignment_notify_email(
            $obconn,
            $complaint_id,
            $reassign_assign_complaint,
            $assign_complaint_datetime,
            $reassign_remarks,
            true
        );
        $_SESSION['success_message'] = 'Complaint closed with No. Reassigned successfully.';
    }
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    $_SESSION['error_message'] = 'Failed to save complaint closure.';
}
 
header('Location: ' . $redirect);
exit;
 
 
/*
session_start();
include 'pdo_obconn.php';
 
$redirect = 'new_complaint.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirect);
    exit;
}
 
$complaint_id = (int) ($_POST['complaint_id'] ?? 0);
$call_closure = trim($_POST['call_closure'] ?? '');
$closure_remarks = trim($_POST['closure_remarks'] ?? '');
$reassignment_details = trim($_POST['reassignment_details'] ?? '');
 

if ($complaint_id <= 0 || !in_array($call_closure, ['Yes', 'No'], true)) {
    $_SESSION['error_message'] = 'Please select call closure Yes or No.';
    header('Location: ' . $redirect);
    exit;
}
 
if ($call_closure === 'Yes' && $closure_remarks === '') {
    $_SESSION['error_message'] = 'Closure remarks are required when call closure is Yes.';
    header('Location: ' . $redirect);
    exit;
}
 
if ($call_closure === 'No' && $reassignment_details === '') {
    $_SESSION['error_message'] = 'Reassignment details are required when call closure is No.';
    header('Location: ' . $redirect);
    exit;
}
 
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
        header('Location: ' . $redirect);
        exit;
    }
 
    if ((int) $complaint['status'] !== COMPLAINT_STATUS_PENDING_HO) {
        $_SESSION['error_message'] = 'Closure is only allowed for complaints pending with HO.';
        header('Location: ' . $redirect);
        exit;
    }
 
    $serviceStmt = $obconn->prepare("
        SELECT COUNT(*) AS total
        FROM complaint_service_updates
        WHERE complaint_id = :complaint_id
    ");
    $serviceStmt->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $serviceStmt->execute();
 
    if ((int) $serviceStmt->fetch(PDO::FETCH_ASSOC)['total'] === 0) {
        $_SESSION['error_message'] = 'Service update is required before complaint closure.';
        header('Location: ' . $redirect);
        exit;
    }
 
    $closed_by = 1;
 
    $obconn->beginTransaction();
 
    $insert = $obconn->prepare("
        INSERT INTO complaint_closures
        (
            complaint_id,
            call_closure,
            closure_remarks,
            reassignment_details,
            closed_by
        )
        VALUES
        (
            :complaint_id,
            :call_closure,
            :closure_remarks,
            :reassignment_details,
            :closed_by
        )
    ");
 
    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':call_closure', $call_closure);
    $insert->bindValue(':closure_remarks', $call_closure === 'Yes' ? $closure_remarks : null);
    $insert->bindValue(':reassignment_details', $call_closure === 'No' ? $reassignment_details : null);
    $insert->bindValue(':closed_by', $closed_by, PDO::PARAM_INT);
    $insert->execute();
 
    $newStatus = $call_closure === 'Yes' ? COMPLAINT_STATUS_RESOLVED : COMPLAINT_STATUS_REOPEN;
 
    $update = $obconn->prepare("
        UPDATE complaints
        SET status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':status', $newStatus, PDO::PARAM_INT);
    $update->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $update->execute();
 
    $activityDescription = $call_closure === 'Yes'
        ? 'Complaint closed and marked as resolved.'
        : 'Closure attempted – reassignment required. ' . $reassignment_details;
 
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
            'Closure',
            :activity_description,
            :user_id
        )
    ");
 
    $log->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $log->bindValue(':activity_description', $activityDescription);
    $log->bindValue(':user_id', $closed_by, PDO::PARAM_INT);
    $log->execute();
 
    $obconn->commit();
 
    if ($call_closure === 'Yes') {
        $_SESSION['success_message'] = 'Complaint closed successfully.';
    } else {
        $_SESSION['success_message'] = 'Closure recorded. You can reassign this complaint from Complaint History using the Assign button.';
    }
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    $_SESSION['error_message'] = 'Failed to save complaint closure.';
}
 
header('Location: ' . $redirect);
exit;
*/