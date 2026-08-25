<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/installed_base_helpers.php';
require_once 'includes/user_approval_configuration_helpers.php';

warranty_claims_ensure_schema($obconn);
user_approval_config_ensure_schema($obconn);

$currentUserId = current_user_id($obconn);
$focApprovalLevels = user_approval_config_levels_for_user(
    $obconn,
    $currentUserId,
    user_approval_config_module_foc()
);
$canApproveL1Foc = $focApprovalLevels['l1'];
$canApproveL2Foc = $focApprovalLevels['l2'];
$serviceApprovalLevels = user_approval_config_levels_for_user(
    $obconn,
    $currentUserId,
    user_approval_config_module_service()
);
$canApproveL1Service = $serviceApprovalLevels['l1'];
$canApproval          = rbac_user_can($obconn, 'approvals', 'view');

if (!$canApproval) {
    header('Location: access_denied.php');
    exit;
}

$userName = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['foc_decision'])) {
    $claimId  = (int) ($_POST['claim_id'] ?? 0);
    $level    = trim((string) ($_POST['level'] ?? ''));
    $decision = trim((string) ($_POST['foc_decision'] ?? ''));
    $remarks  = trim((string) ($_POST['approval_remarks'] ?? ''));
    $canActOnLevel = ($level === 'l1' && $canApproveL1Foc) || ($level === 'l2' && $canApproveL2Foc);

    if (!$canActOnLevel) {
        header('Location: access_denied.php');
        exit;
    }

    $applyError = foc_claim_apply_decision($obconn, $claimId, $level, $decision, $remarks, $userName);
    if ($applyError !== null) {
        $_SESSION['error_message'] = $applyError;
    } else {
        $_SESSION['success_message'] = 'FOC claim #' . $claimId . ' has been ' . strtolower($decision) . ' at ' . strtoupper($level) . '.';
    }
    header('Location: approvals.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['l1_decision'])) {
    $claimId  = (int) ($_POST['claim_id'] ?? 0);
    $decision = trim((string) ($_POST['l1_decision'] ?? ''));
    $remarks  = trim((string) ($_POST['l1_remarks'] ?? ''));

    if (!$canApproveL1Service) {
        header('Location: access_denied.php');
        exit;
    }

    $applyError = service_claim_apply_l1_decision($obconn, $claimId, $decision, $remarks, $userName);
    if ($applyError !== null) {
        $_SESSION['error_message'] = $applyError;
    } else {
        $_SESSION['success_message'] = 'Service claim #' . $claimId . ' has been ' . strtolower($decision) . ' at L1.';
    }
    header('Location: approvals.php');
    exit;
}

// FOC: Lock-in Engineer (L1) Pending, or Business Head (L2) Pending after L1 Approved.
// Service: Lock-in Engineer (L1) Pending only.
$approvalItems = [];

try {
    $stmt = $obconn->query("
        SELECT
            fc.id, fc.complaint_id, fc.warranty_status, fc.justification, fc.l1_status, fc.l2_status,
            fc.overall_status, fc.created_by_username, fc.created_at,
            c.fab_number, c.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(fc.created_by_username), ''), '-') AS created_by_name,
            (
                SELECT STRING_AGG(fci.part_number || ' x' || fci.qty, ', ' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS items_summary
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(fc.created_by_username))
           AND um.deleted_at IS NULL
        WHERE fc.deleted_at IS NULL
          AND (
                fc.l1_status = '" . FOC_STAGE_PENDING . "'
                OR (
                    fc.l1_status = '" . FOC_STAGE_APPROVED . "'
                    AND fc.l2_status = '" . FOC_STAGE_PENDING . "'
                )
          )
        ORDER BY fc.created_at DESC
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isL1Pending = $row['l1_status'] === FOC_STAGE_PENDING;
        $level = $isL1Pending ? 'l1' : 'l2';
        $canDecide = $isL1Pending ? $canApproveL1Foc : $canApproveL2Foc;
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
            'stage_label'    => $isL1Pending
                ? 'Lock-in Engineer: Pending'
                : 'Business Head: Pending',
            'overall_status' => $row['overall_status'],
            'created_by'     => $row['created_by_name'] ?? $row['created_by_username'],
            'created_at'     => $row['created_at'],
            'level'          => $level,
            'action_url'     => 'approvals.php',
            'decision_field' => 'foc_decision',
            'remarks_field'  => 'approval_remarks',
            'action_type'    => 'approve',
            'can_decide'     => $canDecide,
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

try {
    $stmt = $obconn->query("
        SELECT
            sc.*, c.fab_number, c.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(sc.created_by_username), ''), '-') AS created_by_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(sc.created_by_username))
           AND um.deleted_at IS NULL
        WHERE sc.deleted_at IS NULL
          AND sc.l1_status = '" . SERVICE_CLAIM_L1_PENDING . "'
       AND sc.overall_status = 'Pending L1 Approval'
        ORDER BY sc.created_at DESC
    "); //    AND sc.overall_status = 'Pending L1 Approval'
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
            'stage_label'    => 'Lock-in Engineer: Pending',
            'overall_status' => $row['overall_status'],
            'created_by'     => $row['created_by_name'] ?? $row['created_by_username'],
            'created_at'     => $row['created_at'],
            'level'          => 'l1',
            'action_url'     => 'approvals.php',
            'decision_field' => 'l1_decision',
            'remarks_field'  => 'l1_remarks',
            'action_type'    => 'approve',
            'can_decide'     => $canApproveL1Service,
        ];
    }
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

usort($approvalItems, static function (array $a, array $b): int {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

$installedBaseIdByFab = [];
$focClaimMap = [];
$serviceComplaintIds = [];
foreach ($approvalItems as $item) {
    if (($item['claim_type'] ?? '') === 'foc') {
        $focClaimMap[(int) $item['id']] = (int) ($item['complaint_id'] ?? 0);
    }
    if (($item['claim_type'] ?? '') === 'service') {
        $serviceComplaintIds[] = (int) ($item['complaint_id'] ?? 0);
    }
}
$focItemsByClaim = foc_claim_items_for_claims($obconn, $focClaimMap);
$servicePartsByComplaint = complaint_service_log_parts_for_complaints($obconn, $serviceComplaintIds);
foreach ($approvalItems as &$item) {
    $item['fab_html'] = installed_base_fab_link_html($obconn, (string) ($item['fab_number'] ?? ''), $installedBaseIdByFab);
    if (($item['claim_type'] ?? '') === 'foc') {
        $item['details_html'] = foc_parts_linked_summary_html($focItemsByClaim[(int) $item['id']] ?? []);
        $item['parts_html'] = $item['details_html'];
        continue;
    }
    $item['details_html'] = '';
    $item['parts_html'] = foc_parts_linked_summary_html(
        $servicePartsByComplaint[(int) ($item['complaint_id'] ?? 0)] ?? []
    );
}
unset($item);
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
    <style>
        .approval-detail-value {
            min-height: 0;
            display: block;
            padding: 0;
            border: none;
            border-radius: 0;
            background: transparent;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
            line-height: 1.45;
        }
        .complaint-form-modal .form-group .form-label {
            margin-bottom: 4px;
        }
        .complaint-form-actions .btn {
            height: 42px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
    </style>
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
                    Pending Lock-in Engineer and Business Head approvals for FOC Parts, and pending Lock-in Engineer approvals for Service Claims.
                </div>
            </div>
        </div>

        <div class="complaint-form-card show mb-4">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Pending Approvals</h2>
                        <p class="complaint-form-header__subtitle">FOC Parts pending Lock-in Engineer or Business Head approval, and Service Claims pending Lock-in Engineer approval.</p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table id="approvalsTable" class="table table-hover booking-table w-100">
                        <thead>
                            <tr>
                                <th width="6%">#</th>
                                <th width="10%">Type</th>
                                <th width="10%">Call Ticket</th>
                                <th width="12%">Fab Number</th>
                                <th width="14%">Customer</th>
                                <th width="14%">Details</th>
                                <th width="10%">Warranty / CCS</th>
                                <th width="12%">Stage</th>
                                <th width="10%">Submitted By</th>
                                <th width="12%">Submitted On</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($approvalItems as $row): ?>
                            <?php
                                $claimId = (int) $row['id'];
                                $complaintId = (int) $row['complaint_id'];
                                $encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
                                $submittedOn = trim((string) ($row['created_at'] ?? ''));
                                $submittedOnLabel = $submittedOn !== '' ? date('d M Y H:i', strtotime($submittedOn)) : '-';
                                $typeLabel = $row['claim_type'] === 'foc' ? 'FOC Parts' : 'Service Claim';
                            ?>
                            <tr>
                                <td><?= $claimId ?></td>
                                <td>
                                    <span class="status-badge border border-dark"><?= htmlspecialchars($typeLabel) ?></span>
                                </td>
                                <td>
                                    <a href="complaint_details.php?id=<?= htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">
                                        #<?= $complaintId ?>
                                    </a>
                                </td>
                                <td><?= installed_base_fab_link_html($obconn, (string) ($row['fab_number'] ?? ''), $installedBaseIdByFab) ?></td>
                                <td><?= htmlspecialchars((string) ($row['customer_name'] ?? '-')) ?></td>
                                <td>
                                    <?php if (($row['claim_type'] ?? '') === 'foc'): ?>
                                        <?= $row['details_html'] !== '' ? $row['details_html'] : '-' ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars((string) ($row['details'] ?? '')) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars((string) ($row['warranty_label'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars((string) ($row['stage_label'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['created_by'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars($submittedOnLabel) ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-dark btn-view-claim" title="View"
                                        data-type="<?= htmlspecialchars($row['claim_type']) ?>"
                                        data-claim-id="<?= $claimId ?>"
                                        data-complaint-id="<?= $complaintId ?>"
                                        data-fab-number="<?= htmlspecialchars((string) ($row['fab_number'] ?? '')) ?>"
                                        data-fab-html="<?= htmlspecialchars((string) ($row['fab_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-customer-name="<?= htmlspecialchars((string) ($row['customer_name'] ?? '')) ?>"
                                        data-details="<?= htmlspecialchars((string) ($row['details'] ?? '')) ?>"
                                        data-details-html="<?= htmlspecialchars((string) ($row['details_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-parts-html="<?= htmlspecialchars((string) ($row['parts_html'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-warranty="<?= htmlspecialchars((string) ($row['warranty_label'] ?? '')) ?>"
                                        data-justification="<?= htmlspecialchars((string) ($row['justification'] ?? '')) ?>"
                                        data-stage="<?= htmlspecialchars((string) ($row['stage_label'] ?? '')) ?>"
                                        data-overall-status="<?= htmlspecialchars((string) ($row['overall_status'] ?? '')) ?>"
                                        data-submitted-by="<?= htmlspecialchars((string) ($row['created_by'] ?? '')) ?>"
                                        data-submitted-on="<?= htmlspecialchars((string) ($row['created_at'] ?? '')) ?>"
                                        data-level="<?= htmlspecialchars((string) ($row['level'] ?? '')) ?>"
                                        data-action="<?= htmlspecialchars((string) ($row['action_url'] ?? '')) ?>"
                                        data-decision-field="<?= htmlspecialchars((string) ($row['decision_field'] ?? '')) ?>"
                                        data-remarks-field="<?= htmlspecialchars((string) ($row['remarks_field'] ?? '')) ?>"
                                        data-action-type="<?= htmlspecialchars((string) ($row['action_type'] ?? '')) ?>"
                                        data-can-decide="<?= !empty($row['can_decide']) ? '1' : '0' ?>"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Shared claim details modal: view + decide (Approve/Reject with mandatory reject comment). -->
<div class="modal fade" id="viewClaimModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content complaint-form-modal">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-clipboard-check" id="viewClaimIcon"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title" id="viewClaimTitle">Claim Details</h2>
                        <p class="complaint-form-header__subtitle" id="viewClaimSubtitle">Review claim details and take action.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="complaint-form-body">
                <section class="complaint-form-section">
                    <div class="complaint-form-section__head">
                        <span class="complaint-form-section__badge">1</span>
                        <div>
                            <h3 class="complaint-form-section__title">Claim Details</h3>
                            <p class="complaint-form-section__hint">Call ticket, customer and current review status.</p>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-ticket-detailed"></i> Call Ticket</label>
                            <div class="approval-detail-value">
                                <a id="viewClaimTicketLink" href="#" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">#<span id="viewClaimTicket"></span></a>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-upc-scan"></i> Fab Number</label>
                            <div class="approval-detail-value" id="viewClaimFab"></div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-person"></i> Customer</label>
                            <div class="approval-detail-value" id="viewClaimCustomer"></div>
                        </div>
                        <div class="col-12 form-group">
                            <label class="form-label"><i class="bi bi-card-text"></i> Details</label>
                            <div class="approval-detail-value" id="viewClaimDetails"></div>
                        </div>
                        <div class="col-12 form-group" id="viewClaimPartsWrap">
                            <label class="form-label"><i class="bi bi-upc"></i> Part Number</label>
                            <div class="approval-detail-value" id="viewClaimParts"></div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-shield-check"></i> Warranty / CCS</label>
                            <div class="approval-detail-value">
                                <span class="status-badge border border-dark" id="viewClaimWarranty"></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-layers"></i> Stage</label>
                            <div class="approval-detail-value">
                                <span class="status-badge border border-dark" id="viewClaimStage"></span>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><i class="bi bi-flag"></i> Overall Status</label>
                            <div class="approval-detail-value">
                                <span class="status-badge border border-dark" id="viewClaimOverall"></span>
                            </div>
                        </div>
                        <div class="col-12 form-group" id="viewClaimJustificationWrap">
                            <label class="form-label"><i class="bi bi-chat-left-text"></i> Justification</label>
                            <div class="approval-detail-value" id="viewClaimJustification"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label"><i class="bi bi-person-check"></i> Submitted By</label>
                            <div class="approval-detail-value" id="viewClaimSubmittedBy"></div>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label"><i class="bi bi-calendar3"></i> Submitted On</label>
                            <div class="approval-detail-value" id="viewClaimSubmittedOn"></div>
                        </div>
                    </div>
                </section>
                <section class="complaint-form-section" id="decisionFormDivider">
                    <div class="complaint-form-section__head">
                        <span class="complaint-form-section__badge">2</span>
                        <div>
                            <h3 class="complaint-form-section__title">Decision</h3>
                            <p class="complaint-form-section__hint" id="viewClaimDecisionHint">Record remarks and submit your decision.</p>
                        </div>
                    </div>
                    <form id="decisionForm" method="POST">
                        <input type="hidden" name="claim_id" id="decisionClaimId">
                        <input type="hidden" name="level" id="decisionLevel">
                        <input type="hidden" name="return_to" value="approvals.php">
                        <input type="hidden" id="decisionField" value="">
                        <div class="form-group mb-0" id="decisionRemarksWrap">
                            <label for="decisionRemarks" class="form-label" id="decisionRemarksLabel">
                                <i class="bi bi-pencil-square"></i> Remarks <span class="text-muted">(required to reject)</span>
                            </label>
                            <textarea id="decisionRemarks" class="form-control" rows="3" placeholder="Enter remarks"></textarea>
                            <div id="decisionRemarksError" class="text-danger small mt-1" style="display:none;">A comment is required to reject.</div>
                        </div>
                    </form>
                </section>
            </div>
            <div class="complaint-form-actions">
                <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="modalCcsNoBtn">No</button>
                <button type="button" class="btn btn-primary d-none" id="modalCcsYesBtn">Yes</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="modalFocNotUnderBtn">Not Under Warranty</button>
                <button type="button" class="btn btn-primary d-none" id="modalFocUnderBtn">Under Warranty</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="modalRejectBtn">Reject</button>
                <button type="button" class="btn btn-primary d-none" id="modalApproveBtn">Approve</button>
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
            const isFoc = d.type === 'foc';
            const submittedOn = d.submittedOn ? new Date(d.submittedOn.replace(' ', 'T')) : null;
            const submittedOnLabel = submittedOn && !isNaN(submittedOn.getTime())
                ? submittedOn.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                : (d.submittedOn || '-');

            document.getElementById('viewClaimTitle').textContent = (isFoc ? 'FOC Parts Claim' : 'Service Claim') + ' #' + d.claimId;
            document.getElementById('viewClaimSubtitle').textContent = isFoc
                ? 'Review FOC part claim details and take action.'
                : 'Review service claim details and take action.';
            document.getElementById('viewClaimIcon').className = isFoc ? 'bi bi-shield-check' : 'bi bi-clipboard-check';
            document.getElementById('viewClaimTicket').textContent = d.complaintId;
            document.getElementById('viewClaimTicketLink').href = 'complaint_details.php?id=' + encodeURIComponent(btoa(d.complaintId));
            const fabEl = document.getElementById('viewClaimFab');
            if (d.fabHtml) {
                fabEl.innerHTML = d.fabHtml;
            } else {
                fabEl.textContent = d.fabNumber || '-';
            }
            document.getElementById('viewClaimCustomer').textContent = d.customerName || '-';
            const detailsEl = document.getElementById('viewClaimDetails');
            if (isFoc && d.detailsHtml) {
                detailsEl.innerHTML = d.detailsHtml;
            } else {
                detailsEl.textContent = d.details || '-';
            }
            const partsWrap = document.getElementById('viewClaimPartsWrap');
            const partsEl = document.getElementById('viewClaimParts');
            const partsHtml = d.partsHtml && d.partsHtml !== '-' ? d.partsHtml : '';
            if (!isFoc && partsHtml) {
                partsEl.innerHTML = partsHtml;
                setHidden(partsWrap, false);
            } else {
                partsEl.innerHTML = '';
                setHidden(partsWrap, true);
            }
            document.getElementById('viewClaimWarranty').textContent = d.warranty || '-';
            document.getElementById('viewClaimJustification').textContent = d.justification || '-';
            document.getElementById('viewClaimStage').textContent = d.stage || '-';
            document.getElementById('viewClaimOverall').textContent = d.overallStatus || '-';
            document.getElementById('viewClaimSubmittedBy').textContent = d.submittedBy || '-';
            document.getElementById('viewClaimSubmittedOn').textContent = submittedOnLabel;
            setHidden(document.getElementById('viewClaimJustificationWrap'), !isFoc || !d.justification);

            const decisionHint = document.getElementById('viewClaimDecisionHint');
            if (actionType === 'ccs_service') {
                decisionHint.textContent = 'Mark whether this visit is eligible for a warranty claim.';
            } else if (actionType === 'ccs_foc') {
                decisionHint.textContent = 'Confirm the machine warranty status for this FOC claim.';
            } else {
                decisionHint.textContent = 'Approve or reject this claim. Remarks are required to reject.';
            }

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

    if (typeof $.fn.DataTable !== 'undefined' && document.getElementById('approvalsTable')) {
        $('#approvalsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: { emptyTable: 'There are no pending Warranty / CCS or approval items to show.' }
        });
    }
})();
</script>
</body>
</html>