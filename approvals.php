<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';

warranty_claims_ensure_schema($obconn);

$canApproveL1Foc     = rbac_user_can($obconn, 'foc-parts', 'approve-l1-foc');
$canApproveL2Foc     = rbac_user_can($obconn, 'foc-parts', 'approve-l2-foc');
$canApproveL1Service = rbac_user_can($obconn, 'service-claims', 'approve-l1');
$canMarkCcs          = rbac_user_can($obconn, 'service-claims', 'mark-warranty');

// --- Combined list: FOC/Service claims pending CCS/warranty or L1/L2 action --
$approvalItems = [];

try {
    $stmt = $obconn->query("
        SELECT
            fc.id, fc.complaint_id, fc.warranty_status, fc.justification, fc.l1_status, fc.l2_status,
            fc.overall_status, fc.created_by_username, fc.created_at,
            c.fab_number, c.customer_name,
            (
                SELECT STRING_AGG(fci.part_number || ' x' || fci.qty, ', ' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS items_summary
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
        WHERE fc.deleted_at IS NULL
          AND (
                fc.warranty_status IS NULL
                OR BTRIM(fc.warranty_status) = ''
                OR fc.warranty_status = 'Pending'
          )
        ORDER BY fc.created_at DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $approvalItems[] = [
            'claim_type'      => 'foc',
            'id'              => (int) $row['id'],
            'complaint_id'    => (int) $row['complaint_id'],
            'fab_number'      => $row['fab_number'],
            'customer_name'   => $row['customer_name'],
            'details'         => $row['items_summary'] ?? '',
            'warranty_label'  => trim((string) ($row['warranty_status'] ?? '')) !== '' ? $row['warranty_status'] : 'Pending',
            'warranty_class'  => warranty_status_badge_class($row['warranty_status']),
            'justification'   => $row['justification'] ?? '',
            'stage_label'     => 'Pending CCS Review',
            'overall_status'  => $row['overall_status'],
            'created_by'      => $row['created_by_username'],
            'created_at'      => $row['created_at'],
            'level'           => 'ccs',
            'action_url'      => 'foc_parts.php',
            'decision_field'  => 'mark_foc_warranty',
            'remarks_field'   => '',
            'action_type'     => 'ccs_foc',
            'can_decide'      => $canMarkCcs,
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

if ($canApproveL1Foc || $canApproveL2Foc) {
    $currentUserId = current_user_id($obconn);
    $stageConditions = [];
    if ($canApproveL1Foc) {
        $stageConditions[] = "(fc.l1_status = 'Pending' AND (fc.l1_approver_user_id = :uid_l1 OR fc.l1_approver_user_id IS NULL))";
    }
    if ($canApproveL2Foc) {
        $stageConditions[] = "(fc.l1_status = 'Approved' AND fc.l2_status = 'Pending' AND (fc.l2_approver_user_id = :uid_l2 OR fc.l2_approver_user_id IS NULL))";
    }

    try {
        $stmt = $obconn->prepare("
            SELECT
                fc.id, fc.complaint_id, fc.warranty_status, fc.justification, fc.l1_status, fc.l2_status,
                fc.l1_approver_user_id, fc.l2_approver_user_id,
                fc.overall_status, fc.created_by_username, fc.created_at,
                c.fab_number, c.customer_name,
                (
                    SELECT STRING_AGG(fci.part_number || ' x' || fci.qty, ', ' ORDER BY fci.id)
                    FROM foc_claim_items fci
                    WHERE fci.foc_claim_id = fc.id
                ) AS items_summary
            FROM foc_claims fc
            INNER JOIN complaints c ON c.id = fc.complaint_id
            WHERE fc.deleted_at IS NULL
              AND fc.warranty_status IN ('" . WARRANTY_STATUS_UNDER . "', '" . WARRANTY_STATUS_NOT_UNDER . "')
              AND (" . implode(' OR ', $stageConditions) . ")
            ORDER BY fc.created_at DESC
        ");
        if ($canApproveL1Foc) {
            $stmt->bindValue(':uid_l1', $currentUserId, PDO::PARAM_INT);
        }
        if ($canApproveL2Foc) {
            $stmt->bindValue(':uid_l2', $currentUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $level = null;
            if ($canApproveL1Foc && $row['l1_status'] === FOC_STAGE_PENDING
                && ($row['l1_approver_user_id'] === null || (int) $row['l1_approver_user_id'] === $currentUserId)) {
                $level = 'l1';
            } elseif ($canApproveL2Foc && $row['l1_status'] === FOC_STAGE_APPROVED && $row['l2_status'] === FOC_STAGE_PENDING
                && ($row['l2_approver_user_id'] === null || (int) $row['l2_approver_user_id'] === $currentUserId)) {
                $level = 'l2';
            }
            if ($level === null) {
                continue;
            }
            $approvalItems[] = [
                'claim_type'     => 'foc',
                'id'             => (int) $row['id'],
                'complaint_id'   => (int) $row['complaint_id'],
                'fab_number'     => $row['fab_number'],
                'customer_name'  => $row['customer_name'],
                'details'        => $row['items_summary'] ?? '',
                'warranty_label' => $row['warranty_status'],
                'warranty_class' => warranty_status_badge_class($row['warranty_status']),
                'justification'  => $row['justification'] ?? '',
                'stage_label'    => 'L1: ' . $row['l1_status'] . ' / L2: ' . $row['l2_status'],
                'overall_status' => $row['overall_status'],
                'created_by'     => $row['created_by_username'],
                'created_at'     => $row['created_at'],
                'level'          => $level,
                'action_url'     => 'foc_parts.php',
                'decision_field' => 'foc_decision',
                'remarks_field'  => 'approval_remarks',
                'action_type'    => 'approve',
                'can_decide'     => true,
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist yet; silently continue
    }
}

try {
    $stmt = $obconn->query("
        SELECT sc.*, c.fab_number, c.customer_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        WHERE sc.deleted_at IS NULL
          AND sc.overall_status = 'Pending CCS Review'
        ORDER BY sc.created_at DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $approvalItems[] = [
            'claim_type'     => 'service',
            'id'             => (int) $row['id'],
            'complaint_id'   => (int) $row['complaint_id'],
            'fab_number'     => $row['fab_number'],
            'customer_name'  => $row['customer_name'],
            'details'        => 'KM: ' . $row['km_travelled'] . ' | Service Date: ' . $row['service_date'],
            'warranty_label' => 'Pending',
            'warranty_class' => 'bg-warning text-dark',
            'justification'  => '',
            'stage_label'    => 'Pending CCS Review',
            'overall_status' => $row['overall_status'],
            'created_by'     => $row['created_by_username'],
            'created_at'     => $row['created_at'],
            'level'          => 'ccs',
            'action_url'     => 'service_claims.php',
            'decision_field' => 'mark_warranty',
            'remarks_field'  => 'ccs_remarks',
            'action_type'    => 'ccs_service',
            'can_decide'     => $canMarkCcs,
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

if ($canApproveL1Service) {
    $currentUserId = current_user_id($obconn);
    try {
        $stmt = $obconn->prepare("
            SELECT sc.*, c.fab_number, c.customer_name
            FROM service_claims sc
            INNER JOIN complaints c ON c.id = sc.complaint_id
            WHERE sc.deleted_at IS NULL
              AND sc.overall_status = 'Pending L1 Approval'
              AND (sc.l1_approver_user_id = :uid OR sc.l1_approver_user_id IS NULL)
            ORDER BY sc.created_at DESC
        ");
        $stmt->bindValue(':uid', $currentUserId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $approvalItems[] = [
                'claim_type'     => 'service',
                'id'             => (int) $row['id'],
                'complaint_id'   => (int) $row['complaint_id'],
                'fab_number'     => $row['fab_number'],
                'customer_name'  => $row['customer_name'],
                'details'        => 'KM: ' . $row['km_travelled'] . ' | Service Date: ' . $row['service_date'],
                'warranty_label' => $row['ccs_warranty_claim'] !== null && $row['ccs_warranty_claim'] !== '' ? $row['ccs_warranty_claim'] : 'Pending',
                'warranty_class' => !empty($row['ccs_warranty_claim']) ? ($row['ccs_warranty_claim'] === 'Yes' ? 'bg-success' : 'bg-secondary') : 'bg-warning text-dark',
                'justification'  => '',
                'stage_label'    => 'L1: ' . $row['l1_status'],
                'overall_status' => $row['overall_status'],
                'created_by'     => $row['created_by_username'],
                'created_at'     => $row['created_at'],
                'level'          => 'l1',
                'action_url'     => 'service_claims.php',
                'decision_field' => 'l1_decision',
                'remarks_field'  => 'l1_remarks',
                'action_type'    => 'approve',
                'can_decide'     => true,
            ];
        }
    } catch (PDOException $e) {
        // Table may not exist yet; silently continue
    }
}

usort($approvalItems, static function (array $a, array $b): int {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approvals</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet">
    <link href="css/complaint_buttons.css" rel="stylesheet">
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_form.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">

    <?php include 'sidebar.php'; ?>

    <div class="content">

        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <div class="page-header">
            <div>
                <div class="page-subtitle">
                    Pending Warranty / CCS review and L1/L2 approvals for FOC Parts and Service Claims.
                </div>
            </div>
        </div>

        <?php if ($approvalItems !== []): ?>
        <div class="complaint-form-card show mb-4">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Pending Approvals</h2>
                        <p class="complaint-form-header__subtitle">FOC Parts and Service Claims pending CCS warranty review or approval.</p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table class="table table-bordered datatable-standard">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Fab Number</th>
                                <th>Customer</th>
                                <th>Details</th>
                                <th>Warranty / CCS</th>
                                <th>Stage</th>
                                <th>Submitted By</th>
                                <th>Submitted On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvalItems as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><span class="badge <?= $row['claim_type'] === 'foc' ? 'bg-primary' : 'bg-info text-dark' ?>"><?= $row['claim_type'] === 'foc' ? 'FOC Parts' : 'Service Claim' ?></span></td>
                                <td><?= htmlspecialchars($row['fab_number']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['details']) ?></td>
                                <td><span class="badge <?= $row['warranty_class'] ?>"><?= htmlspecialchars($row['warranty_label']) ?></span></td>
                                <td><?= htmlspecialchars($row['stage_label']) ?></td>
                                <td><?= htmlspecialchars($row['created_by']) ?></td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-view-claim"
                                        data-type="<?= htmlspecialchars($row['claim_type']) ?>"
                                        data-claim-id="<?= (int) $row['id'] ?>"
                                        data-complaint-id="<?= (int) $row['complaint_id'] ?>"
                                        data-fab-number="<?= htmlspecialchars($row['fab_number']) ?>"
                                        data-customer-name="<?= htmlspecialchars($row['customer_name']) ?>"
                                        data-details="<?= htmlspecialchars($row['details']) ?>"
                                        data-warranty="<?= htmlspecialchars($row['warranty_label']) ?>"
                                        data-justification="<?= htmlspecialchars($row['justification']) ?>"
                                        data-stage="<?= htmlspecialchars($row['stage_label']) ?>"
                                        data-overall-status="<?= htmlspecialchars($row['overall_status']) ?>"
                                        data-submitted-by="<?= htmlspecialchars($row['created_by']) ?>"
                                        data-submitted-on="<?= htmlspecialchars($row['created_at']) ?>"
                                        data-level="<?= htmlspecialchars($row['level']) ?>"
                                        data-action="<?= htmlspecialchars($row['action_url']) ?>"
                                        data-decision-field="<?= htmlspecialchars($row['decision_field']) ?>"
                                        data-remarks-field="<?= htmlspecialchars($row['remarks_field']) ?>"
                                        data-action-type="<?= htmlspecialchars($row['action_type']) ?>"
                                        data-can-decide="<?= !empty($row['can_decide']) ? '1' : '0' ?>"
                                    >View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-info">There are no pending Warranty / CCS or approval items to show.</div>
        <?php endif; ?>

    </div>
</div>

<!-- Shared claim details modal: view + decide (Approve/Reject with mandatory reject comment). -->
<div class="modal fade" id="viewClaimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewClaimTitle">Claim Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Call Ticket</dt>
                    <dd class="col-sm-8"><a id="viewClaimTicketLink" href="#" target="_blank" rel="noopener">#<span id="viewClaimTicket"></span></a></dd>
                    <dt class="col-sm-4">Fab Number</dt>
                    <dd class="col-sm-8" id="viewClaimFab"></dd>
                    <dt class="col-sm-4">Customer</dt>
                    <dd class="col-sm-8" id="viewClaimCustomer"></dd>
                    <dt class="col-sm-4">Details</dt>
                    <dd class="col-sm-8" id="viewClaimDetails"></dd>
                    <dt class="col-sm-4">Warranty / CCS</dt>
                    <dd class="col-sm-8" id="viewClaimWarranty"></dd>
                    <dt class="col-sm-4">Justification</dt>
                    <dd class="col-sm-8" id="viewClaimJustification"></dd>
                    <dt class="col-sm-4">Stage</dt>
                    <dd class="col-sm-8" id="viewClaimStage"></dd>
                    <dt class="col-sm-4">Overall Status</dt>
                    <dd class="col-sm-8" id="viewClaimOverall"></dd>
                    <dt class="col-sm-4">Submitted By</dt>
                    <dd class="col-sm-8" id="viewClaimSubmittedBy"></dd>
                    <dt class="col-sm-4">Submitted On</dt>
                    <dd class="col-sm-8" id="viewClaimSubmittedOn"></dd>
                </dl>
                <hr id="decisionFormDivider">
                <form id="decisionForm" method="POST">
                    <input type="hidden" name="claim_id" id="decisionClaimId">
                    <input type="hidden" name="level" id="decisionLevel">
                    <input type="hidden" name="return_to" value="approvals.php">
                    <input type="hidden" id="decisionField" value="">
                    <div class="mb-2" id="decisionRemarksWrap">
                        <label for="decisionRemarks" class="form-label" id="decisionRemarksLabel">Remarks <span class="text-muted">(required to reject)</span></label>
                        <textarea id="decisionRemarks" class="form-control" rows="2"></textarea>
                        <div id="decisionRemarksError" class="text-danger small mt-1" style="display:none;">A comment is required to reject.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="modalCcsNoBtn">No</button>
                <button type="button" class="btn btn-success d-none" id="modalCcsYesBtn">Yes</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="modalFocNotUnderBtn">Not Under Warranty</button>
                <button type="button" class="btn btn-success d-none" id="modalFocUnderBtn">Under Warranty</button>
                <button type="button" class="btn btn-danger" id="modalRejectBtn">Reject</button>
                <button type="button" class="btn btn-success" id="modalApproveBtn">Approve</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const viewModalEl      = document.getElementById('viewClaimModal');
    const viewModal        = new bootstrap.Modal(viewModalEl);
    const decisionForm     = document.getElementById('decisionForm');
    const decisionField    = document.getElementById('decisionField');
    const decisionRemarks  = document.getElementById('decisionRemarks');
    const remarksError     = document.getElementById('decisionRemarksError');
    const remarksWrap      = document.getElementById('decisionRemarksWrap');
    const remarksLabel     = document.getElementById('decisionRemarksLabel');
    const formDivider      = document.getElementById('decisionFormDivider');
    const approveBtn       = document.getElementById('modalApproveBtn');
    const rejectBtn        = document.getElementById('modalRejectBtn');
    const ccsYesBtn        = document.getElementById('modalCcsYesBtn');
    const ccsNoBtn         = document.getElementById('modalCcsNoBtn');
    const focUnderBtn      = document.getElementById('modalFocUnderBtn');
    const focNotUnderBtn   = document.getElementById('modalFocNotUnderBtn');

    function setHidden(el, hidden) {
        el.classList.toggle('d-none', hidden);
    }

    document.querySelectorAll('.btn-view-claim').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = btn.dataset;
            const canDecide = d.canDecide === '1';
            const actionType = d.actionType || 'approve';

            document.getElementById('viewClaimTitle').textContent = (d.type === 'foc' ? 'FOC Parts Claim' : 'Service Claim') + ' #' + d.claimId;
            document.getElementById('viewClaimTicket').textContent = d.complaintId;
            document.getElementById('viewClaimTicketLink').href = 'complaint_details.php?id=' + encodeURIComponent(btoa(d.complaintId));
            document.getElementById('viewClaimFab').textContent = d.fabNumber;
            document.getElementById('viewClaimCustomer').textContent = d.customerName;
            document.getElementById('viewClaimDetails').textContent = d.details;
            document.getElementById('viewClaimWarranty').textContent = d.warranty;
            document.getElementById('viewClaimJustification').textContent = d.justification || '—';
            document.getElementById('viewClaimStage').textContent = d.stage;
            document.getElementById('viewClaimOverall').textContent = d.overallStatus;
            document.getElementById('viewClaimSubmittedBy').textContent = d.submittedBy;
            document.getElementById('viewClaimSubmittedOn').textContent = d.submittedOn;

            decisionForm.action = d.action;
            document.getElementById('decisionClaimId').value = d.claimId;
            document.getElementById('decisionLevel').value = d.level;
            decisionField.name = d.decisionField || '';
            decisionField.value = '';
            decisionRemarks.name = d.remarksField || '';
            decisionRemarks.value = '';
            remarksError.style.display = 'none';

            setHidden(formDivider, !canDecide);
            setHidden(decisionForm, !canDecide);
            setHidden(approveBtn, !canDecide || actionType !== 'approve');
            setHidden(rejectBtn, !canDecide || actionType !== 'approve');
            setHidden(ccsYesBtn, !canDecide || actionType !== 'ccs_service');
            setHidden(ccsNoBtn, !canDecide || actionType !== 'ccs_service');
            setHidden(focUnderBtn, !canDecide || actionType !== 'ccs_foc');
            setHidden(focNotUnderBtn, !canDecide || actionType !== 'ccs_foc');
            setHidden(remarksWrap, !canDecide || actionType === 'ccs_foc');
            remarksLabel.innerHTML = actionType === 'approve'
                ? 'Remarks <span class="text-muted">(required to reject)</span>'
                : 'Remarks <span class="text-muted">(optional)</span>';

            viewModal.show();
        });
    });

    document.getElementById('modalApproveBtn').addEventListener('click', function () {
        decisionField.value = 'Approved';
        decisionForm.submit();
    });

    document.getElementById('modalRejectBtn').addEventListener('click', function () {
        if (decisionRemarks.value.trim() === '') {
            remarksError.style.display = 'block';
            decisionRemarks.focus();
            return;
        }
        decisionField.value = 'Rejected';
        decisionForm.submit();
    });

    document.getElementById('modalCcsYesBtn').addEventListener('click', function () {
        decisionField.value = 'Yes';
        decisionForm.submit();
    });

    document.getElementById('modalCcsNoBtn').addEventListener('click', function () {
        decisionField.value = 'No';
        decisionForm.submit();
    });

    document.getElementById('modalFocUnderBtn').addEventListener('click', function () {
        decisionField.value = 'Under Warranty';
        decisionForm.submit();
    });

    document.getElementById('modalFocNotUnderBtn').addEventListener('click', function () {
        decisionField.value = 'Not Under Warranty';
        decisionForm.submit();
    });
})();
</script>
</body>
</html>