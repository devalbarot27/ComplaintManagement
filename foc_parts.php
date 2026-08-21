<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';



warranty_claims_ensure_schema($obconn);

$success_message = '';
$error_message   = '';
$field_errors    = [];
$userName        = current_username();

$canCreateFoc = rbac_user_can($obconn, 'foc-parts', 'create-foc');
$canApproveL1 = rbac_user_can($obconn, 'foc-parts', 'approve-l1-foc');
$canApproveL2 = rbac_user_can($obconn, 'foc-parts', 'approve-l2-foc');

// ─── Handle FOC Claim Submission (Process 1, steps 1-6) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_foc_claim'])) {
    if (!$canCreateFoc) {
        header('Location: access_denied.php');
        exit;
    }
    $complaintId    = (int) ($_POST['complaint_id'] ?? 0);
    $justification  = trim($_POST['justification'] ?? '');
    $warrantyStatus = trim($_POST['warranty_status'] ?? '');
    $cartItems      = json_decode($_POST['cart_items'] ?? '[]', true);

    $complaint = warranty_claims_find_complaint($obconn, $complaintId);
    $items     = [];
    $hasNewParts = false;

    if (is_array($cartItems)) {
        foreach ($cartItems as $cartItem) {
            $partNumber = trim((string) ($cartItem['part_number'] ?? ''));
            $qty        = (int) ($cartItem['qty'] ?? 0);
            if ($partNumber === '' || $qty <= 0) {
                continue;
            }
            $source = (($cartItem['source'] ?? 'new') === 'existing') ? 'existing' : 'new';
            if ($source === 'new') {
                $hasNewParts = true;
            }
            $items[] = [
                'part_number' => $partNumber,
                'part_description' => trim((string) ($cartItem['part_description'] ?? '')),
                'qty' => $qty,
                'source' => $source,
                'source_reference_id' => (int) ($cartItem['source_reference_id'] ?? 0),
            ];
        }
    }

    // Validation
    if ($complaint === null) {
        $field_errors['complaint_id'] = 'Please select a valid Call Ticket Number.';
        $error_message = $field_errors['complaint_id'];
    } elseif ($items === []) {
        $error_message = 'Please add at least one part to the cart.';
    } elseif (!in_array($warrantyStatus, [WARRANTY_STATUS_UNDER, WARRANTY_STATUS_NOT_UNDER], true)) {
        $field_errors['warranty_status'] = 'Please select the Machine Warranty Status.';
        $error_message = $field_errors['warranty_status'];
    } elseif ($hasNewParts && $justification === '') {
        $error_message = 'Justification is required.';
    } elseif (strlen($justification) > 500) {
        $error_message = 'Justification cannot exceed 500 characters.';
    } else {
        try {
            $obconn->beginTransaction();

            $stmt = $obconn->prepare("
                INSERT INTO foc_claims
                (
                    complaint_id,
                    justification,
                    warranty_status,
                    l1_status,
                    l2_status,
                    l1_approver_user_id,
                    l2_approver_user_id,
                    overall_status,
                    created_by_username
                )
                VALUES
                (
                    :complaint_id,
                    :justification,
                    :warranty_status,
                    :l1_status,
                    :l2_status,
                    :l1_approver_user_id,
                    :l2_approver_user_id,
                    :overall_status,
                    :created_by_username
                )
                RETURNING id
            ");
            $stmt->bindValue(':complaint_id',        $complaintId, PDO::PARAM_INT);
            $stmt->bindValue(':justification',        $justification !== '' ? $justification : null);
            $stmt->bindValue(':warranty_status',      $warrantyStatus);
            $stmt->bindValue(':l1_status',             FOC_STAGE_PENDING);
            $stmt->bindValue(':l2_status',             FOC_STAGE_PENDING);
            $stmt->bindValue(':l1_approver_user_id',  DEFAULT_APPROVER_USER_ID, PDO::PARAM_INT);
            $stmt->bindValue(':l2_approver_user_id',  DEFAULT_APPROVER_USER_ID, PDO::PARAM_INT);
            $stmt->bindValue(':overall_status',        'Pending L1 Approval');
            $stmt->bindValue(':created_by_username',  $userName);
            $stmt->execute();

            $newClaimId = (int) $stmt->fetchColumn();

            foc_claim_insert_items($obconn, $newClaimId, $items);

            $obconn->commit();

            warranty_claims_notify_role_holders(
                $obconn,
                'foc-parts',
                'approve-l1-foc',
                'New FOC Claim Pending L1 Approval',
                'FOC claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' needs Lock-in Engineer approval.',
                $newClaimId
            );

            $_SESSION['success_message'] = 'FOC claim submitted successfully. Pending L1 (Lock-in Engineer) approval.';
            header('Location: foc_parts.php');
            exit;
        } catch (PDOException $e) {
            $obconn->rollBack();
            $error_message = 'Failed to submit FOC claim. Please try again.';
        }
    }
}

// ─── Handle L1 / L2 approval decisions (Process 1, steps 7-9) ────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['foc_decision'])) {
    $claimId  = (int) ($_POST['claim_id'] ?? 0);
    $level    = trim($_POST['level'] ?? '');
    $decision = trim($_POST['foc_decision'] ?? '');
    $remarks  = trim($_POST['approval_remarks'] ?? '');
    // Send the user back to the Approvals dashboard if that's where the decision was submitted from.
    $redirectTo = ($_POST['return_to'] ?? '') === 'approvals.php' ? 'approvals.php' : 'foc_parts.php';

    $canActOnLevel = ($level === 'l1' && $canApproveL1) || ($level === 'l2' && $canApproveL2);

    if (!$canActOnLevel || !in_array($decision, [FOC_STAGE_APPROVED, FOC_STAGE_REJECTED], true) || $claimId <= 0) {
        header('Location: access_denied.php');
        exit;
    }

    if ($decision === FOC_STAGE_REJECTED && $remarks === '') {
        $_SESSION['error_message'] = 'Remarks are required to reject a claim.';
        header('Location: ' . $redirectTo);
        exit;
    }

    $claimStmt = $obconn->prepare('SELECT * FROM foc_claims WHERE id = :id AND deleted_at IS NULL');
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false) {
        $_SESSION['error_message'] = 'FOC claim not found.';
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($level === 'l1' && $claim['l1_status'] !== FOC_STAGE_PENDING) {
        $_SESSION['error_message'] = 'This claim has already been actioned at L1.';
        header('Location: ' . $redirectTo);
        exit;
    }

    if ($level === 'l2' && ($claim['l1_status'] !== FOC_STAGE_APPROVED || $claim['l2_status'] !== FOC_STAGE_PENDING)) {
        $_SESSION['error_message'] = 'This claim is not ready for L2 approval.';
        header('Location: ' . $redirectTo);
        exit;
    }

    // A claim assigned to a specific approver can only be actioned by that user (null = unassigned/legacy claim).
    $approverColumn = $level === 'l1' ? 'l1_approver_user_id' : 'l2_approver_user_id';
    if ($claim[$approverColumn] !== null && (int) $claim[$approverColumn] !== current_user_id($obconn)) {
        header('Location: access_denied.php');
        exit;
    }

    if ($level === 'l1') {
        $overallStatus = $decision === FOC_STAGE_APPROVED ? 'Pending L2 Approval' : 'Rejected';
        $update = $obconn->prepare("
            UPDATE foc_claims
            SET l1_status = :status, l1_by_username = :by, l1_at = CURRENT_TIMESTAMP,
                l1_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->bindValue(':status', $decision);
        $update->bindValue(':by', $userName);
        $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
        $update->bindValue(':overall_status', $overallStatus);
        $update->bindValue(':id', $claimId, PDO::PARAM_INT);
        $update->execute();

        if ($decision === FOC_STAGE_APPROVED) {
            warranty_claims_notify_role_holders(
                $obconn,
                'foc-parts',
                'approve-l2-foc',
                'FOC Claim Pending L2 Approval',
                'FOC claim #' . $claimId . ' has been approved at L1 and needs Business Head approval.',
                $claimId
            );
        }
    } else {
        // L2 approval finalises the claim; ERP LN pushes the FOC Sales Order + Zero-Value Invoice next.
        $overallStatus = $decision === FOC_STAGE_APPROVED ? 'Approved' : 'Rejected';
        $update = $obconn->prepare("
            UPDATE foc_claims
            SET l2_status = :status, l2_by_username = :by, l2_at = CURRENT_TIMESTAMP,
                l2_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->bindValue(':status', $decision);
        $update->bindValue(':by', $userName);
        $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
        $update->bindValue(':overall_status', $overallStatus);
        $update->bindValue(':id', $claimId, PDO::PARAM_INT);
        $update->execute();
    }

    $_SESSION['success_message'] = 'FOC claim #' . $claimId . ' has been ' . strtolower($decision) . ' at ' . strtoupper($level) . '.';
    header('Location: ' . $redirectTo);
    exit;
}

// ─── Fetch existing claims for the datatable ─────────────────────────────────
$claims = [];
try {
    $claimStmt = $obconn->query("
        SELECT
            fc.id, fc.complaint_id, fc.justification,
            fc.warranty_status, fc.l1_status, fc.l1_by_username, fc.l1_at, fc.l1_remarks,
            fc.l2_status, fc.l2_by_username, fc.l2_at, fc.l2_remarks,
            fc.overall_status, fc.ln_order_number, fc.created_by_username, fc.created_at,
            c.fab_number, c.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(fc.created_by_username), ''), '-') AS created_by_name,
            (
                SELECT STRING_AGG(fci.part_number, E'\\n' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS part_numbers,
            (
                SELECT STRING_AGG(COALESCE(fci.part_description, ''), E'\\n' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS part_names,
            (
                SELECT STRING_AGG(fci.qty::text, E'\\n' ORDER BY fci.id)
                FROM foc_claim_items fci
                WHERE fci.foc_claim_id = fc.id
            ) AS part_qtys
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(fc.created_by_username))
           AND um.deleted_at IS NULL
        WHERE fc.deleted_at IS NULL
        ORDER BY fc.created_at DESC
    ");
    $claims = $claimStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

$recentComplaints = warranty_claims_recent_complaints($obconn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOC Part Claim</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet">
    <link href="css/complaint_buttons.css" rel="stylesheet">
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_form.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="css/select2_change.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">

    <?php include 'sidebar.php'; ?>

    <div class="content">

        <!-- Flash messages -->
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
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

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <div class="page-subtitle">
                    Request Free of Cost (FOC) parts under warranty against an existing call ticket.
                </div>
            </div>
            <div class="header-btn-group">
                 <?php if ($canCreateFoc): ?>
                <button class="new-order-btn btn-complaint-primary" id="openFocForm" type="button">
                    <i class="bi bi-plus-lg"></i> New FOC Claim
                </button>
                <?php endif; ?>
                <button class="close-form-btn cancel-btn" id="closeFocForm" type="button" style="display:none;">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </div>
        </div>

        <!-- ── Claim Entry Form ───────────────────────────────────────────── -->
        <div class="complaint-form-card" id="focFormCard" style="display:none;">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">New FOC Part Claim</h2>
                        <p class="complaint-form-header__subtitle">
                            Submit a warranty claim for a free-of-cost replacement part against a call ticket.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" id="focClaimForm" novalidate>
                <div class="complaint-form-body">

                    <!-- Section 1 – Call Ticket -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Call Ticket</h3>
                                <p class="complaint-form-section__hint">Select the complaint (call ticket) this FOC request relates to.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-8 form-group">
                                <label class="form-label" for="complaintId">
                                    <i class="bi bi-upc-scan"></i> Call Ticket Number <span class="text-danger">*</span>
                                </label>
                                <select class="form-control<?= isset($field_errors['complaint_id']) ? ' is-invalid' : '' ?>" id="complaintId" name="complaint_id">
                                    <option value="">-- Select Call Ticket --</option>
                                    <?php foreach ($recentComplaints as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        <?= (((int) ($_POST['complaint_id'] ?? 0)) === (int) $c['id']) ? 'selected' : '' ?>>
                                        #<?= (int) $c['id'] ?> - <?= htmlspecialchars($c['fab_number']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="complaint_id"><?= htmlspecialchars($field_errors['complaint_id'] ?? '') ?></div>
                            </div>
                            <div class="col-md-2 form-group d-flex align-items-end mt-5">
                                <?php
                                    $selectedComplaintId = (int) ($_POST['complaint_id'] ?? 0);
                                    $viewTicketHref = $selectedComplaintId > 0
                                        ? 'complaint_details.php?id=' . rawurlencode(base64_encode((string) $selectedComplaintId))
                                        : '#';
                                ?>
                                <a href="<?= htmlspecialchars($viewTicketHref, ENT_QUOTES, 'UTF-8') ?>" id="viewTicketLink" target="_blank" rel="noopener"
                                    class="btn btn-outline-secondary w-100<?= $selectedComplaintId > 0 ? '' : ' disabled' ?>">
                                    <i class="bi bi-box-arrow-up-right"></i> View Ticket Details
                                </a>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">
                                    <i class="bi bi-shield-check"></i> Machine Warranty Status <span class="text-danger">*</span>
                                </label>
                                <select class="form-control<?= isset($field_errors['warranty_status']) ? ' is-invalid' : '' ?>" id="warrantyStatus" name="warranty_status">
                                    <option value="">-- Select Warranty Status --</option>
                                    <option value="<?= WARRANTY_STATUS_UNDER ?>"
                                        <?= (($_POST['warranty_status'] ?? '') === WARRANTY_STATUS_UNDER) ? 'selected' : '' ?>>
                                        Under Warranty
                                    </option>
                                    <option value="<?= WARRANTY_STATUS_NOT_UNDER ?>"
                                        <?= (($_POST['warranty_status'] ?? '') === WARRANTY_STATUS_NOT_UNDER) ? 'selected' : '' ?>>
                                        Not Under Warranty
                                    </option>
                                </select>
                                <div class="text-danger validation-msg" data-field="warranty_status"><?= htmlspecialchars($field_errors['warranty_status'] ?? '') ?></div>
                                <small class="text-muted">Shown to L1/L2 approvers to help their decision. To be auto-populated once ERP LN warranty lookup is integrated.</small>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2 – Parts Cart -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Parts</h3>
                                <p class="complaint-form-section__hint">Pick from parts already recorded for this call ticket, or search and add a new part.</p>
                            </div>
                        </div>

                        <div id="existingItemsPanel" style="display:none;">
                            <label class="form-label"><i class="bi bi-box-seam"></i> Items Already Recorded for This Call Ticket</label>
                            <div class="table-responsive mb-2">
                                <table class="table table-sm table-bordered" id="existingItemsTable">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;"></th>
                                            <th>Part Number</th>
                                            <th>Description</th>
                                            <th style="width:110px;">Qty</th>
                                        </tr>
                                    </thead>
                                    <tbody id="existingItemsBody"></tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mb-3" id="addExistingItemsBtn">
                                <i class="bi bi-plus-lg"></i> Add Checked Items to Cart
                            </button>
                        </div>
                        <div id="existingItemsEmpty" class="text-muted mb-3" style="display:none;">
                            No parts have been recorded yet for this call ticket's service visit.
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-6 form-group">
                                <label class="form-label" for="itemSearch">
                                    <i class="bi bi-search"></i> Search &amp; Add New Item
                                </label>
                                <select class="form-control" id="itemSearch" style="width:100%;">
                                    <option value="">-- Search Item --</option>
                                </select>
                            </div>
                            <div class="col-md-2 form-group">
                                <label class="form-label" for="newItemQty">Quantity</label>
                                <input type="number" class="form-control" id="newItemQty" value="1" min="1" max="9999">
                            </div>
                            <div class="col-md-2 form-group">
                                <button type="button" class="btn btn-complaint-primary w-100" id="addNewItemBtn" disabled>
                                    <i class="bi bi-plus-lg"></i> Add to Cart
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered" id="cartItemsTable">
                                <thead>
                                    <tr>
                                        <th>Part Number</th>
                                        <th>Description</th>
                                        <th style="width:100px;">Qty</th>
                                        <th style="width:90px;">Source</th>
                                        <th style="width:60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="cartItemsBody">
                                    <tr id="cartEmptyRow">
                                        <td colspan="5" class="text-muted text-center">No parts added yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-danger validation-msg" data-field="cart_items"></div>
                        <input type="hidden" name="cart_items" id="cartItemsInput" value="[]">
                    </section>

                    <!-- Section 3 – Justification -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">Justification</h3>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-12 form-group">
                                <label class="form-label" for="justification">
                                    <i class="bi bi-chat-left-text"></i> Justification
                                    <span class="text-danger" id="justificationRequired" style="display:none;">*</span>
                                </label>
                                <textarea class="form-control" id="justification" name="justification"
                                    rows="2" placeholder="Reason for the FOC request (max 500 characters)"
                                    maxlength="500"><?= htmlspecialchars($_POST['justification'] ?? '') ?></textarea>
                                <div class="text-danger validation-msg" data-field="justification"></div>
                            </div>
                        </div>
                    </section>

                </div><!-- /.complaint-form-body -->

                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-outline-secondary" id="cancelFocForm">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" name="submit_foc_claim" class="btn btn-complaint-primary">
                        <i class="bi bi-send"></i> Submit Claim
                    </button>
                </div>
            </form>
        </div>
        <!-- ── End Form ───────────────────────────────────────────────────── -->

        <!-- ── Claims List ────────────────────────────────────────────────── -->
        <div class="complaint-form-card show" id="focTableCard">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">FOC Part Claims</h2>
                        <p class="complaint-form-header__subtitle">
                            Track warranty FOC requests, L1/L2 approval and claim status.
                        </p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table id="focClaimsTable" class="table table-hover booking-table w-100">
                        <thead>
                            <tr>
                                <th width="6%">#</th>
                                <th width="10%">Call Ticket</th>
                                <th width="12%">Fab Number</th>
                                <th width="14%">Customer</th>
                                <th width="12%">Part Number</th>
                                <th width="14%">Part Name</th>
                                <th width="8%">Qty</th>
                                <th width="10%">Warranty</th>
                                <th width="10%">Lock-in Engineer</th>
                                <th width="10%">Business Head</th>
                                <th width="14%">Overall Status</th>
                                <th width="10%">Submitted By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $i => $row): ?>
                            <?php
                                $claimId = (int) $row['id'];
                                $complaintId = (int) $row['complaint_id'];
                                $encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
                            ?>
                            <tr>
                                <td><?= $claimId ?></td>
                                <td>
                                    <a href="complaint_details.php?id=<?= htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="fw-semibold text-decoration-none">
                                        #<?= $complaintId ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['fab_number'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['customer_name'] ?? '-')) ?></td>
                                <td><?= nl2br(htmlspecialchars($row['part_numbers'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars($row['part_names'] ?? '')) ?></td>
                                <td><?= nl2br(htmlspecialchars($row['part_qtys'] ?? '')) ?></td>
                                <td>
                                    <span class="status-badge border border-dark ">
                                        <?= htmlspecialchars((string) ($row['warranty_status'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars((string) ($row['l1_status'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars((string) ($row['l2_status'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars((string) ($row['overall_status'] ?? '-')) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['created_by_name'] ?? $row['created_by_username'] ?? '-')) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- ── End Claims List ───────────────────────────────────────────── -->

    </div><!-- /.content -->
</div><!-- /.main-wrapper -->

<script>
(function () {
    const openBtn   = document.getElementById('openFocForm');
    const closeBtn  = document.getElementById('closeFocForm');
    const cancelBtn = document.getElementById('cancelFocForm');
    const formCard  = document.getElementById('focFormCard');
    const tableCard = document.getElementById('focTableCard');
    const complaintSelect = document.getElementById('complaintId');
    const viewTicketLink  = document.getElementById('viewTicketLink');

    function showForm() {
        if (!formCard) return;
        formCard.style.display = 'block';
        tableCard.style.display = 'none';
        if (openBtn) openBtn.style.display  = 'none';
        if (closeBtn) closeBtn.style.display = '';
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) return;
        formCard.style.display = 'none';
        tableCard.style.display = 'block';
        if (openBtn) openBtn.style.display  = '';
        if (closeBtn) closeBtn.style.display = 'none';
    }

    if (openBtn) openBtn.addEventListener('click', showForm);
    if (closeBtn) closeBtn.addEventListener('click', hideForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

    function complaintDetailsUrl(id) {
        return 'complaint_details.php?id=' + encodeURIComponent(btoa(String(id)));
    }

    function syncViewTicketLink() {
        if (!viewTicketLink || !complaintSelect) {
            return;
        }
        const id = String(complaintSelect.value || '').trim();
        if (id) {
            viewTicketLink.href = complaintDetailsUrl(id);
            viewTicketLink.classList.remove('disabled');
            viewTicketLink.setAttribute('aria-disabled', 'false');
        } else {
            viewTicketLink.href = '#';
            viewTicketLink.classList.add('disabled');
            viewTicketLink.setAttribute('aria-disabled', 'true');
        }
    }

    if (complaintSelect && viewTicketLink) {
        syncViewTicketLink();
        complaintSelect.addEventListener('change', syncViewTicketLink);
        if (typeof $ !== 'undefined') {
            $(complaintSelect).on('change select2:select select2:clear', syncViewTicketLink);
        }
        viewTicketLink.addEventListener('click', function (e) {
            const id = String(complaintSelect.value || '').trim();
            if (!id) {
                e.preventDefault();
                return;
            }
            this.href = complaintDetailsUrl(id);
        });
    }

    // ── Parts cart (existing items for the call ticket + item-master search) ──
    const cartBody       = document.getElementById('cartItemsBody');
    const cartInput       = document.getElementById('cartItemsInput');
    const existingPanel   = document.getElementById('existingItemsPanel');
    const existingEmpty   = document.getElementById('existingItemsEmpty');
    const existingBody    = document.getElementById('existingItemsBody');
    const addExistingBtn  = document.getElementById('addExistingItemsBtn');
    const addNewItemBtn   = document.getElementById('addNewItemBtn');
    const newItemQtyInput = document.getElementById('newItemQty');
    let cart = [];

    function renderCart() {
        if (!cartBody) return;
        if (cart.length === 0) {
            cartBody.innerHTML = '<tr id="cartEmptyRow"><td colspan="5" class="text-muted text-center">No parts added yet.</td></tr>';
        } else {
            cartBody.innerHTML = cart.map(function (item, index) {
                return '<tr>' +
                    '<td>' + escapeHtml(item.part_number) + '</td>' +
                    '<td>' + escapeHtml(item.part_description || '') + '</td>' +
                    '<td>' + item.qty + '</td>' +
                    '<td><span class="status-badge border border-dark">' + item.source + '</span></td>' +
                    '<td><button type="button" class="btn btn-sm btn-outline-danger" data-remove-index="' + index + '"><i class="bi bi-trash"></i></button></td>' +
                    '</tr>';
            }).join('');
        }
        if (cartInput) cartInput.value = JSON.stringify(cart);
        if (cart.length > 0) {
            const cartMsg = document.querySelector('.validation-msg[data-field="cart_items"]');
            if (cartMsg) cartMsg.textContent = '';
        }
        syncJustificationRequired();
    }

    function cartHasNewParts() {
        return cart.some(function (item) {
            return item.source === 'new';
        });
    }

    function syncJustificationRequired() {
        const required = cartHasNewParts();
        const star = document.getElementById('justificationRequired');
        const textarea = document.getElementById('justification');
        const msg = document.querySelector('.validation-msg[data-field="justification"]');
        if (star) {
            star.style.display = required ? '' : 'none';
        }
        if (textarea && !required) {
            textarea.classList.remove('is-invalid');
        }
        if (msg && !required) {
            msg.textContent = '';
        }
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    }

    function addToCart(newItem) {
        const existing = cart.find(function (item) {
            return item.part_number === newItem.part_number && item.source === newItem.source;
        });
        if (existing) {
            existing.qty += newItem.qty;
        } else {
            cart.push(newItem);
        }
        renderCart();
    }

    if (cartBody) {
        cartBody.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-remove-index]');
            if (!btn) return;
            cart.splice(parseInt(btn.getAttribute('data-remove-index'), 10), 1);
            renderCart();
        });
    }

    if (complaintSelect) {
        complaintSelect.addEventListener('change', function () {
            const complaintId = this.value;
            if (!existingPanel || !existingBody) return;
            if (!complaintId) {
                existingPanel.style.display = 'none';
                existingEmpty.style.display = 'none';
                return;
            }
            $.ajax({
                url: 'foc_parts_data.php',
                method: 'GET',
                dataType: 'json',
                data: { action: 'complaint_items', complaint_id: complaintId }
            }).done(function (res) {
                const items = (res && res.items) || [];
                if (items.length === 0) {
                    existingPanel.style.display = 'none';
                    existingEmpty.style.display = 'block';
                    return;
                }
                existingEmpty.style.display = 'none';
                existingPanel.style.display = 'block';
                existingBody.innerHTML = items.map(function (item, idx) {
                    return '<tr>' +
                        '<td><input type="checkbox" class="form-check-input existing-item-check" data-idx="' + idx + '"></td>' +
                        '<td>' + escapeHtml(item.part_number) + '</td>' +
                        '<td>' + escapeHtml(item.part_description || '') + '</td>' +
                        '<td><input type="number" class="form-control form-control-sm existing-item-qty" data-idx="' + idx + '" value="' + item.qty + '" min="1"></td>' +
                        '</tr>';
                }).join('');
                existingBody.dataset.items = JSON.stringify(items);
            }).fail(function () {
                existingPanel.style.display = 'none';
                existingEmpty.style.display = 'none';
            });
        });
    }

    if (addExistingBtn) {
        addExistingBtn.addEventListener('click', function () {
            const items = JSON.parse(existingBody.dataset.items || '[]');
            existingBody.querySelectorAll('.existing-item-check:checked').forEach(function (checkbox) {
                const idx = parseInt(checkbox.getAttribute('data-idx'), 10);
                const source = items[idx];
                if (!source) return;
                const qtyInput = existingBody.querySelector('.existing-item-qty[data-idx="' + idx + '"]');
                const qty = qtyInput ? parseInt(qtyInput.value, 10) : source.qty;
                addToCart({
                    part_number: source.part_number,
                    part_description: source.part_description,
                    qty: qty > 0 ? qty : 1,
                    source: 'existing',
                    source_reference_id: source.source_reference_id
                });
                checkbox.checked = false;
            });
        });
    }

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#itemSearch').select2({
            placeholder: 'Search Item',
            allowClear: true,
            width: '100%',
            ajax: {
                url: 'foc_parts_data.php',
                type: 'GET',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { action: 'search_items', search: params.term };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                },
                cache: true
            }
        });

        $('#itemSearch').on('select2:select select2:clear', function () {
            if (addNewItemBtn) addNewItemBtn.disabled = !$('#itemSearch').val();
        });
    }

    if (addNewItemBtn) {
        addNewItemBtn.addEventListener('click', function () {
            const selected = $('#itemSearch').select2('data')[0];
            if (!selected || !selected.part_number) return;
            const qty = parseInt(newItemQtyInput.value, 10) || 1;
            addToCart({
                part_number: selected.part_number,
                part_description: selected.part_description,
                qty: qty,
                source: 'new',
                source_reference_id: null
            });
            $('#itemSearch').val(null).trigger('change');
            newItemQtyInput.value = 1;
            addNewItemBtn.disabled = true;
        });
    }

    const focClaimForm = document.getElementById('focClaimForm');
    const warrantySelect = document.getElementById('warrantyStatus');
    const justificationInput = document.getElementById('justification');

    function setFieldError(field, message) {
        const msg = document.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function clearFieldError(field, input) {
        setFieldError(field, '');
        if (input) {
            input.classList.remove('is-invalid');
        }
    }

    if (complaintSelect) {
        complaintSelect.addEventListener('change', function () {
            clearFieldError('complaint_id', complaintSelect);
        });
        if (typeof $ !== 'undefined') {
            $(complaintSelect).on('change select2:select select2:clear', function () {
                clearFieldError('complaint_id', complaintSelect);
            });
        }
    }
    if (warrantySelect) {
        warrantySelect.addEventListener('change', function () {
            clearFieldError('warranty_status', warrantySelect);
        });
    }

    if (focClaimForm) {
        focClaimForm.addEventListener('submit', function (e) {
            setFieldError('cart_items', '');
            setFieldError('justification', '');
            if (justificationInput) justificationInput.classList.remove('is-invalid');

            let blocked = false;
            let firstInvalid = null;

            if (complaintSelect && String(complaintSelect.value || '').trim() === '') {
                e.preventDefault();
                blocked = true;
                setFieldError('complaint_id', 'Please select a Call Ticket Number.');
                complaintSelect.classList.add('is-invalid');
                firstInvalid = firstInvalid || complaintSelect;
            }
            if (warrantySelect && String(warrantySelect.value || '').trim() === '') {
                e.preventDefault();
                blocked = true;
                setFieldError('warranty_status', 'Please select the Machine Warranty Status.');
                warrantySelect.classList.add('is-invalid');
                firstInvalid = firstInvalid || warrantySelect;
            }
            if (cart.length === 0) {
                e.preventDefault();
                blocked = true;
                setFieldError('cart_items', 'Please add at least one part to the cart.');
                firstInvalid = firstInvalid || document.getElementById('cartItemsTable');
            }
            if (cartHasNewParts() && justificationInput && justificationInput.value.trim() === '') {
                e.preventDefault();
                blocked = true;
                setFieldError('justification', 'Justification is required when additional/new parts are added.');
                justificationInput.classList.add('is-invalid');
                firstInvalid = firstInvalid || justificationInput;
            }
            if (blocked && firstInvalid && typeof firstInvalid.focus === 'function') {
                firstInvalid.focus();
            }
        });
    }

    renderCart();

    // Show form if there was a POST validation error
    <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    showForm();
    <?php endif; ?>

    // DataTable
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#focClaimsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            language: { emptyTable: 'No FOC claims submitted yet.' }
        });
    }
})();
</script>
<script>
// Require remarks before a Reject decision can be submitted (Approve stays optional).
document.addEventListener('click', function (e) {
    const btn = e.target.closest('button[value="Rejected"]');
    if (!btn) return;
    const form = btn.closest('form');
    const remarksInput = form && form.querySelector('input[name="approval_remarks"], input[name="l1_remarks"]');
    if (remarksInput && remarksInput.value.trim() === '') {
        e.preventDefault();
        remarksInput.classList.add('is-invalid');
        remarksInput.focus();
        alert('Please enter remarks before rejecting.');
    }
});
</script>
</body>
</html>
