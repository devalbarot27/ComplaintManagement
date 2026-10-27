<?php

if (!isset($complaint, $obconn) || !is_array($complaint) || !($obconn instanceof PDO)) {
    return;
}

require_once __DIR__ . '/complaint_closure_approval_helpers.php';

$approvalComplaintId = (int) ($complaint['id'] ?? 0);
$approvalLatest = complaint_closure_approval_latest($obconn, $approvalComplaintId);
$approvalStatus = strtolower(trim((string) ($approvalLatest['status'] ?? '')));
$approvalStatusLabel = complaint_closure_approval_status_label($approvalStatus);
$approvalEngineerName = complaint_closure_approval_user_label(
    $obconn,
    (int) ($approvalLatest['approver_user_id'] ?? 0)
);
$approvalRemarks = trim((string) ($approvalLatest['decision_remarks'] ?? ''));
$approvalDecidedAt = trim((string) ($approvalLatest['decided_at'] ?? ''));
$currentActorId = (int) (current_user_id($obconn) ?? 0);
$creatorId = (int) ($complaint['added_by'] ?? 0);
$isMappedEngineer = $approvalLatest !== null
    && $currentActorId > 0
    && $currentActorId === (int) ($approvalLatest['approver_user_id'] ?? 0);
$canDecideApproval = $isMappedEngineer && $approvalStatus === 'pending';
$canResendApproval = $currentActorId > 0
    && $currentActorId === $creatorId
    && $approvalStatus === 'rejected';
$requiresVayuApproval = complaint_closure_approval_requires_vayu_engineer($obconn, $approvalComplaintId);
$canSendApproval = $requiresVayuApproval
    && $currentActorId > 0
    && $currentActorId === $creatorId
    && $approvalLatest === null
    && in_array((int) ($complaint['status'] ?? 0), [COMPLAINT_STATUS_PENDING, COMPLAINT_STATUS_PENDING_HO], true);
$approvalReturnFrom = strtolower(trim((string) ($from ?? 'entry')));
if (!in_array($approvalReturnFrom, ['entry', 'list', 'approval'], true)) {
    $approvalReturnFrom = 'entry';
}
$showApprovalCard = $approvalLatest !== null || $canSendApproval;
?>
<?php if ($showApprovalCard) { ?>
<div class="card border-1 shadow-sm mb-3 complaint-details-history-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-person-check text-secondary"></i>
            <strong>Closure Approval</strong>
        </div>
        <span class="badge border border-secondary text-secondary"><?php echo htmlspecialchars($approvalStatusLabel, ENT_QUOTES, 'UTF-8'); ?></span>
    </div>
    <div class="card-body complaint-form-body px-3 pt-3 pb-3">
        <?php if ($approvalLatest !== null) { ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="text-muted small">Vayu Engineer</div>
                <div><?php echo htmlspecialchars($approvalEngineerName, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Approval Status</div>
                <div><?php echo htmlspecialchars($approvalStatusLabel, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small">Decided On</div>
                <div><?php echo $approvalDecidedAt !== '' ? htmlspecialchars(date('d M Y h:i A', strtotime($approvalDecidedAt)), ENT_QUOTES, 'UTF-8') : '-'; ?></div>
            </div>
            <?php if ($approvalRemarks !== '') { ?>
            <div class="col-12">
                <div class="text-muted small">Remarks</div>
                <div><?php echo nl2br(htmlspecialchars($approvalRemarks, ENT_QUOTES, 'UTF-8')); ?></div>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
        <p class="mb-3">This complaint stays Pending until the mapped Vayu Engineer approves it. The status changes to Pending With HO after approval.</p>
        <?php } ?>

        <?php if ($canDecideApproval) { ?>
        <form method="post" action="complaint_closure_approval.php" id="closureApprovalDecisionForm">
            <input type="hidden" name="complaint_id" value="<?php echo $approvalComplaintId; ?>">
            <input type="hidden" name="return_from" value="<?php echo htmlspecialchars($approvalReturnFrom, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="mb-3">
                <label class="form-label" for="closureApprovalRemarks">Remarks</label>
                <textarea class="form-control" name="remarks" id="closureApprovalRemarks" maxlength="500" rows="3" placeholder="Enter approval or rejection remarks"></textarea>
                <div class="text-danger small mt-1" id="closureApprovalRemarksError" style="display:none;">A rejection remark is required.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-dark btn-sm" name="approval_action" value="approve">Approve</button>
                <button type="submit" class="btn btn-outline-dark btn-sm" name="approval_action" value="reject" id="closureApprovalRejectBtn">Reject</button>
            </div>
        </form>
        <script>
            document.getElementById('closureApprovalDecisionForm').addEventListener('submit', function (event) {
                var rejectButton = document.getElementById('closureApprovalRejectBtn');
                var remarks = document.getElementById('closureApprovalRemarks');
                var error = document.getElementById('closureApprovalRemarksError');
                if (event.submitter === rejectButton && remarks && remarks.value.trim() === '') {
                    event.preventDefault();
                    if (error) {
                        error.style.display = '';
                    }
                    remarks.focus();
                }
            });
        </script>
        <?php } elseif ($canResendApproval || $canSendApproval) { ?>
        <form method="post" action="complaint_closure_approval.php">
            <input type="hidden" name="complaint_id" value="<?php echo $approvalComplaintId; ?>">
            <input type="hidden" name="return_from" value="<?php echo htmlspecialchars($approvalReturnFrom, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn btn-dark btn-sm" name="approval_action" value="<?php echo $canResendApproval ? 'resend' : 'send'; ?>">
                <?php echo $canResendApproval ? 'Resend for Approval' : 'Send for Approval'; ?>
            </button>
        </form>
        <?php } elseif ($approvalStatus === 'approved') { ?>
        <p class="mb-0 text-muted">This request is approved. Further approval or resubmission stays closed unless a new approval cycle starts.</p>
        <?php } elseif ($approvalStatus === 'pending') { ?>
        <p class="mb-0 text-muted">Waiting for the mapped Vayu Engineer to approve or reject this request.</p>
        <?php } ?>
    </div>
</div>
<?php } ?>