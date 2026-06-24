<?php
session_start();
include 'pdo_obconn.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/complaint_assignment_mail_helpers.php';
require_once 'includes/current_username_helpers.php';
include 'includes/complaint_status.php';
 
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: new_complaint.php');
    exit;
}
 
$complaint_id = (int)($_POST['complaint_id'] ?? 0);
$assign_complaint = trim($_POST['assign_complaint'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');
 
$redirect = 'new_complaint.php';
 
if ($complaint_id <= 0 || $assign_complaint === '') {
    $_SESSION['error_message'] = 'Assign To is required.';
    header('Location: ' . $redirect);
    exit;
}
 
if (strlen($remarks) > 500) {
    $_SESSION['error_message'] = 'Remarks cannot exceed 500 characters.';
    header('Location: ' . $redirect);
    exit;
}

if (($assigneeError = complaint_validate_elgi_engineer_assignee($obconn, $assign_complaint)) !== null) {
    $_SESSION['error_message'] = $assigneeError;
    header('Location: ' . $redirect);
    exit;
}
 
$assigned_by = current_user_id($obconn);
if ($assigned_by === null || $assigned_by <= 0) {
    $_SESSION['error_message'] = 'Unable to resolve logged-in user.';
    header('Location: ' . $redirect);
    exit;
}

$assigned_to = complaint_resolve_assignee_user_id($obconn, $assign_complaint);

if ($assigned_to <= 0) {
    $_SESSION['error_message'] = 'Selected assignee must be an active ELGi Engineer.';
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

    if ((int) $complaint['status'] !== COMPLAINT_STATUS_OPEN) {
        $_SESSION['error_message'] = 'Manual assign is only allowed for open complaints.';
        header('Location: ' . $redirect);
        exit;
    }

    $assign_complaint_datetime = date('Y-m-d H:i:s');

    $obconn->beginTransaction();
 
    $insert = $obconn->prepare("
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
 
    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':assign_complaint', $assign_complaint);
    $insert->bindValue(':assigned_to', $assigned_to, PDO::PARAM_INT);
    $insert->bindValue(':assign_complaint_datetime', $assign_complaint_datetime);
    $insert->bindValue(':remarks', $remarks !== '' ? $remarks : null);
    $insert->bindValue(':assigned_by', $assigned_by, PDO::PARAM_INT);
    $insert->bindValue(':username', current_username());
    $insert->execute();

    $update = $obconn->prepare('UPDATE complaints SET status = :status WHERE id = :id');
    $update->bindValue(':status', COMPLAINT_STATUS_IN_PROGRESS, PDO::PARAM_INT);
    $update->bindValue(':id', $complaint_id, PDO::PARAM_INT);
    $update->execute();

    $activityDescription = 'Complaint assigned to ' . $assign_complaint
        . ' on ' . date('d M Y, h:i A', strtotime($assign_complaint_datetime))
        . '. Status changed to In Progress.';
 
    if ($remarks !== '') {
        $activityDescription .= ' Remarks: ' . $remarks;
    }
 
    complaint_log_activity(
        $obconn,
        $complaint_id,
        'Assignment',
        $activityDescription,
        $assigned_by
    );
 
    $obconn->commit();

    complaint_assignment_notify_email(
        $obconn,
        $complaint_id,
        $assign_complaint,
        $assign_complaint_datetime,
        $remarks
    );

    $_SESSION['success_message'] = 'Complaint assigned successfully.';
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    $_SESSION['error_message'] = 'Failed to assign complaint.';
}
 
header('Location: ' . $redirect);
exit;

/*
session_start();
include 'pdo_obconn.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$complaint_id = (int)($_POST['complaint_id'] ?? 0);
$assign_complaint = trim($_POST['assign_complaint'] ?? '');
$assign_complaint_datetime = trim($_POST['assign_complaint_datetime'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($complaint_id <= 0 || $assign_complaint === '' || $assign_complaint_datetime === '') {
    $_SESSION['error_message'] = 'Assign To and Assign Date Time are required.';
    header('Location: new_complaint.php');
    exit;
}

if (strlen($remarks) > 500) {
    $_SESSION['error_message'] = 'Remarks cannot exceed 500 characters.';
    header('Location: new_complaint.php');
    exit;
}

$assigned_by = 1;
$assigned_to = 1;

try {
    $obconn->beginTransaction();

    $insert = $obconn->prepare("
        INSERT INTO complaint_assignments
        (
            complaint_id,
            assign_complaint,
            assigned_to,
            assign_complaint_datetime,
            remarks,
            assigned_by
        )
        VALUES
        (
            :complaint_id,
            :assign_complaint,
            :assigned_to,
            :assign_complaint_datetime,
            :remarks,
            :assigned_by
        )
    ");

    $insert->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $insert->bindValue(':assign_complaint', $assign_complaint);
    $insert->bindValue(':assigned_to', $assigned_to, PDO::PARAM_INT);
    $insert->bindValue(':assign_complaint_datetime', $assign_complaint_datetime);
    $insert->bindValue(':remarks', $remarks);
    $insert->bindValue(':assigned_by', $assigned_by, PDO::PARAM_INT);
    $insert->execute();

    $update = $obconn->prepare("UPDATE complaints SET status = 2 WHERE id = :id");
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
            'Assignment',
            :activity_description,
            :user_id
        )
    ");

    $log->bindValue(':complaint_id', $complaint_id, PDO::PARAM_INT);
    $log->bindValue(':activity_description', 'Complaint assigned to ' . $assign_complaint);
    $log->bindValue(':user_id', $assigned_by, PDO::PARAM_INT);
    $log->execute();

    $obconn->commit();

    $_SESSION['success_message'] = 'Complaint assigned successfully.';
} catch (PDOException $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    $_SESSION['error_message'] = 'Failed to assign complaint.';
}

header('Location: new_complaint.php');
*/
exit;
