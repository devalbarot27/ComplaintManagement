<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/admin_access_helpers.php';
require_once 'includes/complaint_closure_approval_helpers.php';
require_once 'includes/current_username_helpers.php';

admin_ensure_session_role($obconn);

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

$returnFrom = strtolower(trim((string) ($_POST['return_from'] ?? 'entry')));
if (!in_array($returnFrom, ['entry', 'list', 'approval'], true)) {
    $returnFrom = 'entry';
}

$complaintId = (int) ($_POST['complaint_id'] ?? 0);
$encodedId = rawurlencode(base64_encode((string) $complaintId));
$redirect = $complaintId > 0
    ? 'complaint_details.php?id=' . $encodedId . '&from=' . $returnFrom
    : ($returnFrom === 'approval' ? 'complaint_closure_approval.php' : 'new_complaint.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    complaint_closure_approval_require_page_access($obconn);
    require __DIR__ . '/includes/complaint_closure_approval_page.php';
    exit;
}

$action = strtolower(trim((string) ($_POST['approval_action'] ?? '')));
if ($action === 'approve' || $action === 'reject') {
    $redirect = 'complaint_closure_approval.php';
}
$remarks = trim((string) ($_POST['remarks'] ?? ''));
$actorId = (int) (current_user_id($obconn) ?? 0);

try {
    if ($action === 'resend' || $action === 'send') {
        $creatorId = complaint_closure_approval_creator_id($obconn, $complaintId);
        if ($actorId <= 0 || $actorId !== $creatorId) {
            $_SESSION['error_message'] = 'Only the user who created the complaint can send it for closure approval.';
            header('Location: ' . $redirect);
            exit;
        }

        $latest = complaint_closure_approval_latest($obconn, $complaintId);
        $latestStatus = strtolower(trim((string) ($latest['status'] ?? '')));
        if ($action === 'resend' && $latestStatus !== 'rejected') {
            $_SESSION['error_message'] = 'Resend for Approval is available only after the Vayu Engineer rejects the request.';
            header('Location: ' . $redirect);
            exit;
        }
        if ($action === 'send' && $latest !== null) {
            $_SESSION['error_message'] = 'A closure approval request already exists for this complaint.';
            header('Location: ' . $redirect);
            exit;
        }

        $obconn->beginTransaction();
        $request = complaint_closure_approval_begin_request($obconn, $complaintId);
        $obconn->commit();
        try {
            complaint_closure_approval_notify_request($obconn, $request, $complaintId);
        } catch (Throwable $notifyError) {
            error_log('Closure approval notification failed: ' . $notifyError->getMessage());
        }
        if (($request['status'] ?? '') === 'sent') {
            $_SESSION['success_message'] = $request['message'];
        } else {
            $_SESSION['error_message'] = $request['message'] !== ''
                ? $request['message']
                : 'Unable to send the closure approval request.';
        }
    } elseif ($action === 'approve' || $action === 'reject') {
        $obconn->beginTransaction();
        $result = complaint_closure_approval_decide($obconn, $complaintId, $actorId, $action, $remarks);
        if (empty($result['ok'])) {
            $obconn->rollBack();
            $_SESSION['error_message'] = $result['message'];
        } else {
            $obconn->commit();
            try {
                complaint_closure_approval_notify_decision($obconn, $result, $complaintId);
            } catch (Throwable $notifyError) {
                error_log('Closure approval notification failed: ' . $notifyError->getMessage());
            }
            $_SESSION['success_message'] = $result['message'];
        }
    } else {
        $_SESSION['error_message'] = 'Invalid closure approval action.';
    }
} catch (Throwable $e) {
    if ($obconn->inTransaction()) {
        $obconn->rollBack();
    }
    error_log('Complaint closure approval failed: ' . $e->getMessage());
    $_SESSION['error_message'] = 'Unable to update the closure approval request.';
}

header('Location: ' . $redirect);
exit;
