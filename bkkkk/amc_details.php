<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/amc_helpers.php';

amc_ensure_schema($obconn);

$active_menu = 'amc';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid AMC contract.');
}

$amcContract = amc_find_by_id($obconn, $id);

if (!$amcContract) {
    die('AMC contract not found.');
}

if (!amc_user_can_access_record($obconn, $amcContract)) {
    rbac_access_denied_redirect();
}

$amcPermissions = amc_action_permissions($obconn);
$canSeeAddedBy = amc_can_view_added_by($obconn);
$canEditAmc = $amcPermissions['edit'];

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_visit_status'])) {
    if (!$canEditAmc) {
        $error_message = 'Access denied. You do not have permission to update visit status.';
    } else {
        $visitId = (int) ($_POST['visit_id'] ?? 0);
        $newStatus = ($_POST['mark_visit_status'] === AMC_VISIT_COMPLETED) ? AMC_VISIT_COMPLETED : AMC_VISIT_PENDING;

        if ($visitId > 0 && amc_mark_visit_status($obconn, $visitId, $id, $newStatus)) {
            $_SESSION['success_message'] = 'Visit status updated successfully.';
            header('Location: amc_details.php?id=' . rawurlencode(base64_encode((string) $id)));
            exit;
        }

        $error_message = 'Failed to update visit status.';
    }
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$amcVisits = amc_visits_for_contract($obconn, $id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMC Contract #<?= htmlspecialchars($amcContract['contract_number']) ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        .warranty-status-badge {
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            display: inline-block;
        }
        .warranty-status--standard { background: #dcfce7; color: #166534; border-color: #86efac; }
        .warranty-status--uptime { background: #e0f2fe; color: #075985; border-color: #7dd3fc; }
        .warranty-status--out { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .warranty-status--unknown { background: #f1f5f9; color: #475569; border-color: #cbd5e1; }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <?php if ($success_message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
            <div>
                <h5 class="mb-2">AMC Contract <?= htmlspecialchars($amcContract['contract_number']) ?></h5>
                <span class="badge <?= amc_status_badge_class(amc_display_status($amcContract)) ?>"><?= htmlspecialchars(amc_display_status($amcContract)) ?></span>
            </div>
            <a href="amc.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Installed Base</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                    $amcWarrantyDetails = installed_base_warranty_details(
                        installed_base_commissioning_date_for_machine(
                            $obconn,
                            (int) ($amcContract['installed_base_id'] ?? 0),
                            (string) ($amcContract['fab_number'] ?? '')
                        )
                    );
                    ?>
                    <div class="col-md-3"><strong>Installed Base ID:</strong><br><?= htmlspecialchars($amcContract['installed_base_id'] ? ('#' . $amcContract['installed_base_id']) : '-') ?></div>
                    <div class="col-md-3"><strong>FAB Number:</strong><br><?= htmlspecialchars($amcContract['fab_number'] ?? '-') ?></div>
                    <div class="col-md-3"><strong>Equipment Model:</strong><br><?= htmlspecialchars($amcContract['product_model'] ?? '-') ?></div>
                    <div class="col-md-3"><strong>Machine Warranty:</strong><br><span class="status-badge border border-dark"><?= htmlspecialchars((string) $amcWarrantyDetails['status']) ?></span></div>
                    <?php if ($amcWarrantyDetails['end_date_label'] !== '' && $amcWarrantyDetails['end_date_label'] !== '-') { ?>
                    <div class="col-md-3"><strong><?= htmlspecialchars((string) $amcWarrantyDetails['end_date_heading']) ?>:</strong><br><?= htmlspecialchars((string) $amcWarrantyDetails['end_date_label']) ?></div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>AMC Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><strong>AMC Type:</strong><br><?= htmlspecialchars(AMC_TYPE_OPTIONS[$amcContract['amc_type']] ?? $amcContract['amc_type'] ?? '-') ?></div>
                    <div class="col-md-3"><strong>AMC Value:</strong><br><?= htmlspecialchars(number_format((float) $amcContract['amc_value'], 2)) ?></div>
                    <div class="col-md-3"><strong>AMC Start Date:</strong><br><?= htmlspecialchars($amcContract['amc_start_date']) ?></div>
                    <div class="col-md-3"><strong>AMC End Date:</strong><br><?= htmlspecialchars($amcContract['amc_end_date']) ?></div>
                    <div class="col-md-3"><strong>Visit Start Date:</strong><br><?= htmlspecialchars($amcContract['visit_start_date']) ?></div>
                    <div class="col-md-3"><strong>Number of Visits:</strong><br><?= (int) $amcContract['no_of_visits'] ?></div>
                    <?php if ($canSeeAddedBy): ?>
                    <div class="col-md-3"><strong>Added By:</strong><br><?= htmlspecialchars(amc_added_by_label($amcContract)) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Customer Details</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3"><strong>Customer Name:</strong><br><?= htmlspecialchars($amcContract['customer_name']) ?></div>
                    <div class="col-md-3"><strong>Mobile:</strong><br><?= htmlspecialchars($amcContract['telephone_number'] ?? '-') ?></div>
                    <div class="col-md-3"><strong>Email:</strong><br><?= htmlspecialchars($amcContract['email_id'] ?? '-') ?></div>
                    <div class="col-md-3"><strong>Pincode:</strong><br><?= htmlspecialchars($amcContract['post_code'] ?? '-') ?></div>
                    <div class="col-md-6"><strong>Address:</strong><br><?= htmlspecialchars(trim(($amcContract['address_line1'] ?? '') . ' ' . ($amcContract['address_line2'] ?? '')) ?: '-') ?></div>
                    <div class="col-md-2"><strong>City:</strong><br><?= htmlspecialchars($amcContract['city_name'] ?? '-') ?></div>
                    <div class="col-md-2"><strong>District:</strong><br><?= htmlspecialchars($amcContract['district_name'] ?? '-') ?></div>
                    <div class="col-md-2"><strong>State:</strong><br><?= htmlspecialchars($amcContract['state_name'] ?? '-') ?></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><strong>Visit Schedule</strong></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Visit Date</th>
                                <th>Status</th>
                                <th>Completed Date</th>
                                <?php if ($canEditAmc): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($amcVisits as $visit): ?>
                            <tr>
                                <td><?= (int) $visit['visit_number'] ?></td>
                                <td><?= htmlspecialchars($visit['visit_date']) ?></td>
                                <td><span class="status-badge border border-dark"><?= htmlspecialchars($visit['visit_status']) ?></span></td>
                                <td><?= htmlspecialchars($visit['completed_date'] ?? '-') ?></td>
                                <?php if ($canEditAmc): ?>
                                <td>
                                    <?php if ($visit['visit_status'] !== AMC_VISIT_COMPLETED): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="visit_id" value="<?= (int) $visit['id'] ?>">
                                        <button type="submit" name="mark_visit_status" value="Completed" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check-lg"></i> Mark Completed
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="visit_id" value="<?= (int) $visit['id'] ?>">
                                        <button type="submit" name="mark_visit_status" value="Pending" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reopen
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
