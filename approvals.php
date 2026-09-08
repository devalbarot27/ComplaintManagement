<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/installed_base_helpers.php';
require_once 'includes/user_helpers.php';
require_once 'includes/order_approval_helpers.php';

warranty_claims_ensure_schema($obconn);
order_approval_ensure_schema($obconn);

$approvalFlags = user_current_approval_flags($obconn);
$canApproveL1Foc = $approvalFlags['l1'];
$canApproveL2Foc = $approvalFlags['l2'];
$canApproveL1Service = rbac_user_can($obconn, 'service-claims', 'approve-l1');
$canApproveL1Service = true;
$canApproval          = rbac_user_can($obconn, 'approvals', 'view');
$canOrderApproval     = order_approval_can_access($obconn);

if (!$canApproval && !$canOrderApproval) {
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


    // L2 approval is handled here directly (not via foc_claim_apply_decision) so a
    // successful approve also pushes the claim's parts to ERP LN as a zero-value
    // Sales Order, atomically: LN failure rolls back the L2 status change too.
    if ($level === 'l2' && $decision === FOC_STAGE_APPROVED) {
        if (!($obconn instanceof PDO) || !($dpconn instanceof PDO)) {
            $_SESSION['error_message'] = 'Database connection is not available, so the L2 approval was not saved. Please try again.';
            header('Location: approvals.php');
            exit;
        }

        $claimStmt = $obconn->prepare('SELECT * FROM foc_claims WHERE id = :id AND deleted_at IS NULL');
        $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
        $claimStmt->execute();
        $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);



        if ($claim === false) {
            $_SESSION['error_message'] = 'FOC claim not found.';
            header('Location: approvals.php');
            exit;
        }

        if ($claim['l1_status'] !== FOC_STAGE_APPROVED || $claim['l2_status'] !== FOC_STAGE_PENDING) {
            $_SESSION['error_message'] = 'This claim is not ready for L2 approval.';
            header('Location: approvals.php');
            exit;
        }
   


        if ($claim['l2_approver_user_id'] !== null && (int) $claim['l2_approver_user_id'] !== current_user_id($obconn)) {
            error_log("approvals.php: access_denied - claim #{$claimId} is assigned to l2_approver_user_id={$claim['l2_approver_user_id']} but current user is " . $userName . '.');
            header('Location: access_denied.php');
        }

  

        try {
            $obconn->beginTransaction();

            $lnRefNo = foc_claim_submit_ln_order(
                $obconn,
                $dpconn,
                $claimId,
                $_SESSION['customer_number_vayu'] ?? '',
                $_SESSION['usr_name'] ?? ''
            );
        
            $update = $obconn->prepare("
                UPDATE foc_claims
                SET l2_status = :status, l2_by_username = :by, l2_at = CURRENT_TIMESTAMP,
                    l2_remarks = :remarks, overall_status = 'Approved', updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ");
            $update->bindValue(':status', FOC_STAGE_APPROVED);
            $update->bindValue(':by', $userName);
            $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
            $update->bindValue(':id', $claimId, PDO::PARAM_INT);
            $update->execute();
            $lnUpdate = $obconn->prepare('UPDATE foc_claims SET ln_order_number = :ln_order_number WHERE id = :id');
            $lnUpdate->bindValue(':ln_order_number', $lnRefNo);
            $lnUpdate->bindValue(':id', $claimId, PDO::PARAM_INT);
            $lnUpdate->execute();

            $obconn->commit();
            error_log("approvals.php: claim #{$claimId} approved at L2 and pushed to LN as {$lnRefNo}.");
            $_SESSION['success_message'] = 'FOC claim #' . $claimId . ' has been approved at L2 and pushed to LN (order ' . $lnRefNo . ').';
        } catch (Throwable $e) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }
            error_log('approvals.php: FOC claim #' . $claimId . ' LN submission failed (' . get_class($e) . '): ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $_SESSION['error_message'] = 'The ERP LN order could not be created, so the L2 approval was not saved. Please try again.';
        }

        header('Location: approvals.php');
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

// Order-level L1 / L2 approval, then AO Number via LN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['order_decision']) || isset($_POST['cart_decision']))) {
    $requestId = (int) ($_POST['claim_id'] ?? 0);
    $decision = trim((string) ($_POST['order_decision'] ?? $_POST['cart_decision'] ?? ''));
    $remarks = trim((string) ($_POST['order_remarks'] ?? $_POST['cart_remarks'] ?? ''));
    $request = order_approval_get_by_id($obconn, $requestId);

    if (!$request) {
        $_SESSION['error_message'] = 'Order approval request not found.';
    } elseif (!order_approval_is_assigned_to_current_user($obconn, $request)) {
        header('Location: access_denied.php');
        exit;
    } else {
        $result = order_approval_decide($obconn, $request, $decision, $remarks, $userName);
        if (!empty($result['error'])) {
            $_SESSION['error_message'] = $result['error'];
        } else {
            $successMessage = trim((string) ($result['message'] ?? ''));
            if ($successMessage === '') {
                $successMessage = 'Order ' . ($request['refno'] ?? '') . ' has been ' . strtolower($decision) . '.';
            }
            if (!empty($result['generate_ao']) && trim((string) ($result['refno'] ?? '')) !== '') {
                require_once __DIR__ . '/orderClass.php';
                $aoMessage = preg_replace('/\s*AO Number will be generated\.?$/i', '', $successMessage);
                $aoMessage = trim((string) $aoMessage);
                if ($aoMessage === '') {
                    $aoMessage = 'The order has been approved.';
                }
                try {
                    $orderService = new orderClass($obconn, $dpconn);
                    $aoError = $orderService->generateAoNumberAfterApproval((string) $result['refno']);
                    if ($aoError !== null) {
                        $aoMessage .= ' AO Number was not generated: ' . $aoError;
                    } else {
                        $aoMessage .= ' AO Number generation has been requested from LN.';
                    }
                } catch (Throwable $e) {
                    error_log('generateAoNumberAfterApproval: ' . $e->getMessage() . ' @ ' . $e->getLine());
                    $aoMessage .= ' AO Number was not generated. Please use Re-Push later.';
                }
                $_SESSION['approval_success_modal'] = [
                    'title' => 'Order Approved Successfully',
                    'message' => $aoMessage,
                    'status' => (string) $result['refno'],
                    'redirect' => 'recent_orders.php?order_no=' . rawurlencode((string) $result['refno']),
                ];
            } else {
                $_SESSION['success_message'] = $successMessage;
            }
        }
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
            c.fab_number, cm.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(fc.created_by_username), ''), '-') AS created_by_name,
            (
                SELECT STRING_AGG(fci.part_number || ' x' || fci.qty, ', ' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS items_summary
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
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
            sc.*, c.fab_number, cm.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(sc.created_by_username), ''), '-') AS created_by_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
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

try {
    foreach (order_approval_pending_items($obconn, $dpconn ?? null) as $orderItem) {
        $approvalItems[] = $orderItem;
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
    if (($item['claim_type'] ?? '') === 'cart') {
        continue;
    }
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

$approvalSuccessModal = null;
if (!empty($_SESSION['approval_success_modal']) && is_array($_SESSION['approval_success_modal'])) {
    $approvalSuccessModal = $_SESSION['approval_success_modal'];
    unset($_SESSION['approval_success_modal']);
}
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
    <link href="css/success_modal.css" rel="stylesheet">
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
    <script src="js/success_modal.js"></script>
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
            <?php unset($_SESSION['success_message']);
            endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php unset($_SESSION['error_message']);
            endif; ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">
                        Pending Lock-in Engineer and Business Head approvals for FOC Parts, Service Claims, and Order Approvals (Level 1 / Level 2).
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
                            <p class="complaint-form-header__subtitle">FOC Parts, Service Claims, and orders waiting for Level 1 or Level 2 approval. AO Number is generated only after required order approvals are completed.</p>
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
                                    <th width="12%">Fab / Order Ref</th>
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
                                    $complaintId = (int) ($row['complaint_id'] ?? 0);
                                    $encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
                                    $submittedOn = trim((string) ($row['created_at'] ?? ''));
                                    $submittedOnTs = $submittedOn !== '' ? strtotime($submittedOn) : 0;
                                    $submittedOnLabel = $submittedOnTs ? date('d M Y H:i', $submittedOnTs) : '-';
                                    $submittedOnSort = $submittedOnTs ? date('Y-m-d H:i:s', $submittedOnTs) : '';
                                    $isCart = ($row['claim_type'] ?? '') === 'cart';
                                    $typeLabel = $row['claim_type'] === 'foc'
                                        ? 'FOC Parts'
                                        : ($isCart ? 'Order Approval' : 'Service Claim');
                                    ?>
                                    <tr>
                                        <td></td>
                                        <td>
                                            <span class="status-badge border border-dark"><?= htmlspecialchars($typeLabel) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($isCart): ?>
                                                -
                                            <?php else: ?>
                                            <a href="complaint_details.php?id=<?= htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">
                                                #<?= $complaintId ?>
                                            </a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="recent_order_details.php?refno=<?= htmlspecialchars((string) ($row['ref_no'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">
                                            <?php if ($isCart): ?>
                                                <?= htmlspecialchars((string) ($row['item_code'] ?? $row['fab_number'] ?? '-')) ?>
                                            <?php else: ?>
                                                <?= installed_base_fab_link_html($obconn, (string) ($row['fab_number'] ?? ''), $installedBaseIdByFab) ?>
                                            <?php endif; ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($row['customer_name'] ?? '-')) ?></td>
                                        <td>
                                            <?php if (($row['claim_type'] ?? '') === 'foc' || $isCart): ?>
                                                <?= !empty($row['details_html']) ? $row['details_html'] : htmlspecialchars((string) ($row['details'] ?? '-')) ?>
                                            <?php else: ?>
                                                <?= htmlspecialchars((string) ($row['details'] ?? '')) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isCart): ?>
                                                -
                                            <?php else: ?>
                                            <span class="status-badge border border-dark">
                                                <?= htmlspecialchars((string) ($row['warranty_label'] ?? '-')) ?>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge border border-dark">
                                                <?= htmlspecialchars((string) ($row['stage_label'] ?? '-')) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($row['created_by'] ?? '-')) ?></td>
                                        <td data-order="<?= htmlspecialchars($submittedOnSort, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($submittedOnLabel) ?></td>
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
                                                data-item-code="<?= htmlspecialchars((string) ($row['item_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-item-name="<?= htmlspecialchars((string) ($row['item_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-price-type="<?= htmlspecialchars((string) ($row['price_type_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-unit-price="<?= htmlspecialchars((string) ($row['unit_price'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-qty="<?= htmlspecialchars((string) ($row['qty'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-order-type="<?= htmlspecialchars((string) ($row['order_type_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-approval-level="<?= htmlspecialchars((string) ($row['approval_level_label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-delivery-address-type="<?= htmlspecialchars((string) ($row['delivery_address_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-dealer-address="<?= htmlspecialchars((string) ($row['dealer_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-name="<?= htmlspecialchars((string) ($row['end_customer_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-email="<?= htmlspecialchars((string) ($row['end_customer_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-street1="<?= htmlspecialchars((string) ($row['end_customer_street1'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-street2="<?= htmlspecialchars((string) ($row['end_customer_street2'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-pincode="<?= htmlspecialchars((string) ($row['end_customer_pincode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-city="<?= htmlspecialchars((string) ($row['end_customer_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-district="<?= htmlspecialchars((string) ($row['end_customer_district'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-end-customer-state="<?= htmlspecialchars((string) ($row['end_customer_state'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-l1-status="<?= htmlspecialchars((string) ($row['l1_status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-l1-approved-by="<?= htmlspecialchars((string) ($row['l1_approved_by'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-l1-approved-at="<?= htmlspecialchars((string) ($row['l1_approved_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                data-l1-remarks="<?= htmlspecialchars((string) ($row['l1_remarks'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
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
                                <h3 class="complaint-form-section__title" id="viewDetailsSectionTitle">Claim Details</h3>
                                <p class="complaint-form-section__hint" id="viewDetailsSectionHint">Call ticket, customer and current review status.</p>
                            </div>
                        </div>
                        <div class="row g-3" id="viewClaimFocFields">
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
                        <div class="row g-3 d-none" id="viewCartFields">
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-hash"></i> Order Ref</label>
                                <div class="approval-detail-value" id="viewCartItemCode"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-person"></i> Customer</label>
                                <div class="approval-detail-value" id="viewCartCustomer"></div>
                            </div>
                            <div class="col-12 form-group d-none" id="viewCartDealerAddressWrap">
                                <label class="form-label"><i class="bi bi-geo-alt"></i> Dealer Address</label>
                                <div class="approval-detail-value" id="viewCartDealerAddress" style="white-space: pre-wrap;"></div>
                            </div>
                            <div class="col-12 d-none" id="viewCartEndCustomerWrap">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label mb-0"><i class="bi bi-geo-alt"></i> End Customer Details</label>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">End Customer Name</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerName"></div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Email</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerEmail"></div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Street 1</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerStreet1"></div>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label class="form-label">Street 2</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerStreet2"></div>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">Pincode</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerPincode"></div>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">City</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerCity"></div>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">District</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerDistrict"></div>
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label class="form-label">State</label>
                                        <div class="approval-detail-value" id="viewCartEndCustomerState"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 form-group">
                                <label class="form-label"><i class="bi bi-box-seam"></i> Products</label>
                                <div class="approval-detail-value" id="viewCartItemName"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-list-ol"></i> Product Lines</label>
                                <div class="approval-detail-value" id="viewCartQty"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-shield-check"></i> Approval Level</label>
                                <div class="approval-detail-value" id="viewCartApprovalLevel"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label"><i class="bi bi-flag"></i> Status</label>
                                <div class="approval-detail-value">
                                    <span class="status-badge border border-dark" id="viewCartStatus"></span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group d-none">
                                <label class="form-label"><i class="bi bi-currency-rupee"></i> Unit Price</label>
                                <div class="approval-detail-value" id="viewCartUnitPrice"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-person-check"></i> Requested By</label>
                                <div class="approval-detail-value" id="viewCartRequestedBy"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label"><i class="bi bi-calendar3"></i> Requested On</label>
                                <div class="approval-detail-value" id="viewCartRequestedOn"></div>
                            </div>
                            <div class="col-12 d-none" id="viewCartL1ApprovalWrap">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label mb-0"><i class="bi bi-shield-check"></i> Level 1 Approval</label>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">L1 Approval Status</label>
                                        <div class="approval-detail-value">
                                            <span class="status-badge border border-dark" id="viewCartL1Status"></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Approved By</label>
                                        <div class="approval-detail-value" id="viewCartL1ApprovedBy"></div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="form-label">Approval At</label>
                                        <div class="approval-detail-value" id="viewCartL1ApprovedAt"></div>
                                    </div>
                                    <div class="col-12 form-group">
                                        <label class="form-label">Remark</label>
                                        <div class="approval-detail-value" id="viewCartL1Remarks" style="white-space: pre-wrap;"></div>
                                    </div>
                                </div>
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
        (function() {
            const viewModalEl = document.getElementById('viewClaimModal');
            const viewModal = new bootstrap.Modal(viewModalEl);
            const decisionForm = document.getElementById('decisionForm');
            const decisionField = document.getElementById('decisionField');
            const decisionRemarks = document.getElementById('decisionRemarks');
            const remarksError = document.getElementById('decisionRemarksError');
            const remarksWrap = document.getElementById('decisionRemarksWrap');
            const remarksLabel = document.getElementById('decisionRemarksLabel');
            const formDivider = document.getElementById('decisionFormDivider');
            const approveBtn = document.getElementById('modalApproveBtn');
            const rejectBtn = document.getElementById('modalRejectBtn');
            const ccsYesBtn = document.getElementById('modalCcsYesBtn');
            const ccsNoBtn = document.getElementById('modalCcsNoBtn');
            const focUnderBtn = document.getElementById('modalFocUnderBtn');
            const focNotUnderBtn = document.getElementById('modalFocNotUnderBtn');

            function setHidden(el, hidden) {
                el.classList.toggle('d-none', hidden);
            }

            document.querySelectorAll('.btn-view-claim').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const d = btn.dataset;
                    const canDecide = d.canDecide === '1';
                    const actionType = d.actionType || 'approve';
                    const isFoc = d.type === 'foc';
                    const isCart = d.type === 'cart';
                    const submittedOn = d.submittedOn ? new Date(d.submittedOn.replace(' ', 'T')) : null;
                    const submittedOnLabel = submittedOn && !isNaN(submittedOn.getTime()) ?
                        submittedOn.toLocaleString('en-GB', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        }) :
                        (d.submittedOn || '-');

                    const focFields = document.getElementById('viewClaimFocFields');
                    const cartFields = document.getElementById('viewCartFields');
                    setHidden(focFields, isCart);
                    setHidden(cartFields, !isCart);

                    if (isCart) {
                        document.getElementById('viewClaimTitle').textContent = 'Order Approval';
                        document.getElementById('viewClaimSubtitle').textContent = 'Review the full order and take Level 1 or Level 2 action.';
                        document.getElementById('viewClaimIcon').className = 'bi bi-clipboard-check';
                        document.getElementById('viewDetailsSectionTitle').textContent = 'Order Details';
                        document.getElementById('viewDetailsSectionHint').textContent = 'Entire order is approved at each level. AO Number is generated after required approvals.';
                        document.getElementById('viewCartItemCode').textContent = d.itemCode || d.fabNumber || '-';
                        document.getElementById('viewCartCustomer').textContent = d.customerName || '-';
                        const endCustomerWrap = document.getElementById('viewCartEndCustomerWrap');
                        const dealerAddressWrap = document.getElementById('viewCartDealerAddressWrap');
                        const isEndCustomer = d.deliveryAddressType === 'end_customer';
                        const isDealerAddress = d.deliveryAddressType === 'dealer';
                        setHidden(endCustomerWrap, !isEndCustomer);
                        setHidden(dealerAddressWrap, !isDealerAddress);
                        if (isDealerAddress) {
                            document.getElementById('viewCartDealerAddress').textContent = d.dealerAddress || '-';
                        }
                        if (isEndCustomer) {
                            document.getElementById('viewCartEndCustomerName').textContent = d.endCustomerName || '-';
                            document.getElementById('viewCartEndCustomerEmail').textContent = d.endCustomerEmail || '-';
                            document.getElementById('viewCartEndCustomerStreet1').textContent = d.endCustomerStreet1 || '-';
                            document.getElementById('viewCartEndCustomerStreet2').textContent = d.endCustomerStreet2 || '-';
                            document.getElementById('viewCartEndCustomerPincode').textContent = d.endCustomerPincode || '-';
                            document.getElementById('viewCartEndCustomerCity').textContent = d.endCustomerCity || '-';
                            document.getElementById('viewCartEndCustomerDistrict').textContent = d.endCustomerDistrict || '-';
                            document.getElementById('viewCartEndCustomerState').textContent = d.endCustomerState || '-';
                        }
                        const productsEl = document.getElementById('viewCartItemName');
                        if (d.detailsHtml) {
                            productsEl.innerHTML = d.detailsHtml;
                        } else {
                            productsEl.textContent = d.itemName || d.details || '-';
                        }
                        document.getElementById('viewCartUnitPrice').textContent = d.unitPrice || '-';
                        document.getElementById('viewCartQty').textContent = d.qty || '-';
                        document.getElementById('viewCartApprovalLevel').textContent = d.approvalLevel || '-';
                        document.getElementById('viewCartStatus').textContent = d.overallStatus || 'Pending';
                        document.getElementById('viewCartRequestedBy').textContent = d.submittedBy || '-';
                        document.getElementById('viewCartRequestedOn').textContent = submittedOnLabel;
                        const l1Wrap = document.getElementById('viewCartL1ApprovalWrap');
                        const isL2Approval = d.level === 'level_2';
                        setHidden(l1Wrap, !isL2Approval);
                        if (isL2Approval) {
                            document.getElementById('viewCartL1Status').textContent = d.l1Status || '-';
                            document.getElementById('viewCartL1ApprovedBy').textContent = d.l1ApprovedBy || '-';
                            document.getElementById('viewCartL1ApprovedAt').textContent = d.l1ApprovedAt || '-';
                            document.getElementById('viewCartL1Remarks').textContent = d.l1Remarks || '-';
                        }
                    } else {
                        document.getElementById('viewClaimTitle').textContent = (isFoc ? 'FOC Parts Claim' : 'Service Claim') + ' #' + d.claimId;
                        document.getElementById('viewClaimSubtitle').textContent = isFoc ?
                            'Review FOC part claim details and take action.' :
                            'Review service claim details and take action.';
                        document.getElementById('viewClaimIcon').className = isFoc ? 'bi bi-shield-check' : 'bi bi-clipboard-check';
                        document.getElementById('viewDetailsSectionTitle').textContent = 'Claim Details';
                        document.getElementById('viewDetailsSectionHint').textContent = 'Call ticket, customer and current review status.';
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
                    }

                    const decisionHint = document.getElementById('viewClaimDecisionHint');
                    if (isCart) {
                        decisionHint.textContent = 'Approve or reject this entire order. Remarks are required to reject.';
                    } else if (actionType === 'ccs_service') {
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
                    remarksLabel.innerHTML = actionType === 'approve' ?
                        'Remarks <span class="text-muted">(required to reject)</span>' :
                        'Remarks <span class="text-muted">(optional)</span>';

                    viewModal.show();
                });
            });

            document.getElementById('modalApproveBtn').addEventListener('click', function() {
                decisionField.value = 'Approved';
                decisionForm.submit();
            });

            document.getElementById('modalRejectBtn').addEventListener('click', function() {
                if (decisionRemarks.value.trim() === '') {
                    remarksError.style.display = 'block';
                    decisionRemarks.focus();
                    return;
                }
                decisionField.value = 'Rejected';
                decisionForm.submit();
            });

            document.getElementById('modalCcsYesBtn').addEventListener('click', function() {
                decisionField.value = 'Yes';
                decisionForm.submit();
            });

            document.getElementById('modalCcsNoBtn').addEventListener('click', function() {
                decisionField.value = 'No';
                decisionForm.submit();
            });

            document.getElementById('modalFocUnderBtn').addEventListener('click', function() {
                decisionField.value = 'Under Warranty';
                decisionForm.submit();
            });

            document.getElementById('modalFocNotUnderBtn').addEventListener('click', function() {
                decisionField.value = 'Not Under Warranty';
                decisionForm.submit();
            });

            if (typeof $.fn.DataTable !== 'undefined' && document.getElementById('approvalsTable')) {
                $('#approvalsTable').DataTable({
                    order: [
                        [9, 'desc']
                    ],
                    pageLength: 10,
                    columnDefs: [{
                        targets: 0,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    }, {
                        orderable: false,
                        targets: -1
                    }],
                    language: {
                        emptyTable: 'There are no pending Warranty / CCS or Order Approval items to show.'
                    }
                });
            }

            var approvalSuccessModal = <?= json_encode($approvalSuccessModal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            if (approvalSuccessModal && typeof SuccessModal !== 'undefined') {
                SuccessModal.show({
                    title: approvalSuccessModal.title || 'Order Approved Successfully',
                    message: approvalSuccessModal.message || 'The order has been approved.',
                    status: approvalSuccessModal.status || '',
                    onClose: function() {
                        window.location.href = approvalSuccessModal.redirect || 'recent_orders.php';
                    }
                });
            }
        })();
    </script>
</body>

</html>