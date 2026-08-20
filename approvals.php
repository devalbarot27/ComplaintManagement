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

// ─── FOC Parts claims awaiting an action this user can take ─────────────────
$focClaims = [];
if ($canApproveL1Foc || $canApproveL2Foc) {
    $stageConditions = [];
    if ($canApproveL1Foc) {
        $stageConditions[] = "fc.l1_status = 'Pending'";
    }
    if ($canApproveL2Foc) {
        $stageConditions[] = "(fc.l1_status = 'Approved' AND fc.l2_status = 'Pending')";
    }

    try {
        $stmt = $obconn->query("
            SELECT
                fc.id, fc.complaint_id, fc.warranty_status, fc.l1_status, fc.l2_status,
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
              AND (" . implode(' OR ', $stageConditions) . ")
            ORDER BY fc.created_at DESC
        ");
        $focClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist yet; silently continue
    }
}

// ─── Service claims awaiting L1 approval ─────────────────────────────────────
$serviceClaims = [];
if ($canApproveL1Service) {
    try {
        $stmt = $obconn->query("
            SELECT sc.*, c.fab_number, c.customer_name
            FROM service_claims sc
            INNER JOIN complaints c ON c.id = sc.complaint_id
            WHERE sc.deleted_at IS NULL
              AND sc.overall_status = 'Pending L1 Approval'
            ORDER BY sc.created_at DESC
        ");
        $serviceClaims = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table may not exist yet; silently continue
    }
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
                    Items across Warranty Management awaiting your approval.
                </div>
            </div>
        </div>

        <?php if ($canApproveL1Foc || $canApproveL2Foc): ?>
        <div class="complaint-form-card mb-4">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">FOC Parts</h2>
                        <p class="complaint-form-header__subtitle">Free-of-cost part claims pending your approval.</p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table class="table table-bordered datatable-standard">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Call Ticket</th>
                                <th>Fab Number</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>L1 (Lock-in Engineer)</th>
                                <th>L2 (Business Head)</th>
                                <th>Submitted By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($focClaims === []): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No FOC claims pending your approval.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($focClaims as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="complaint_details.php?id=<?= rawurlencode(base64_encode((string) $row['complaint_id'])) ?>" target="_blank" rel="noopener">
                                        #<?= (int) $row['complaint_id'] ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($row['fab_number']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['items_summary'] ?? '') ?></td>
                                <td><span class="badge <?= foc_stage_badge_class($row['l1_status']) ?>"><?= htmlspecialchars($row['l1_status']) ?></span></td>
                                <td><span class="badge <?= foc_stage_badge_class($row['l2_status']) ?>"><?= htmlspecialchars($row['l2_status']) ?></span></td>
                                <td><?= htmlspecialchars($row['created_by_username']) ?></td>
                                <td>
                                    <?php if ($canApproveL1Foc && $row['l1_status'] === FOC_STAGE_PENDING): ?>
                                        <form method="POST" action="foc_parts.php" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="level" value="l1">
                                            <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="Remarks">
                                            <button type="submit" name="foc_decision" value="Approved" class="btn btn-sm btn-success">Approve</button>
                                            <button type="submit" name="foc_decision" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    <?php elseif ($canApproveL2Foc && $row['l1_status'] === FOC_STAGE_APPROVED && $row['l2_status'] === FOC_STAGE_PENDING): ?>
                                        <form method="POST" action="foc_parts.php" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="level" value="l2">
                                            <input type="text" name="approval_remarks" class="form-control form-control-sm" placeholder="Remarks">
                                            <button type="submit" name="foc_decision" value="Approved" class="btn btn-sm btn-success">Approve</button>
                                            <button type="submit" name="foc_decision" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($canApproveL1Service): ?>
        <div class="complaint-form-card mb-4">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Service Claims</h2>
                        <p class="complaint-form-header__subtitle">Warranty service claims pending L1 approval.</p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table class="table table-bordered datatable-standard">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Call Ticket</th>
                                <th>Fab Number</th>
                                <th>Customer</th>
                                <th>KM Travelled</th>
                                <th>Service Date</th>
                                <th>Submitted By</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($serviceClaims === []): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted">No service claims pending L1 approval.</td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($serviceClaims as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="complaint_details.php?id=<?= rawurlencode(base64_encode((string) $row['complaint_id'])) ?>" target="_blank" rel="noopener">
                                        #<?= (int) $row['complaint_id'] ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($row['fab_number']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['km_travelled']) ?></td>
                                <td><?= htmlspecialchars($row['service_date']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_username']) ?></td>
                                <td>
                                    <form method="POST" action="service_claims.php" class="d-flex gap-1">
                                        <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                        <input type="text" name="l1_remarks" class="form-control form-control-sm" placeholder="Remarks">
                                        <button type="submit" name="l1_decision" value="Approved" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="l1_decision" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$canApproveL1Foc && !$canApproveL2Foc && !$canApproveL1Service): ?>
        <div class="alert alert-info">You do not have any pending approval actions assigned to your role.</div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
