<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/installed_base_helpers.php';
require_once 'includes/after_market_access_helpers.php';

$active_menu = 'warranty_claims';

$installedBasePermissions = installed_base_action_permissions($obconn);
$canUpdateCommissioningDate = $installedBasePermissions['edit'];

if (isset($_GET['commission_updated']) && (string) $_GET['commission_updated'] === '1') {
    $_SESSION['success_message'] = 'Commissioning date updated successfully.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_commissioning_date'])) {
    if (!$canUpdateCommissioningDate) {
        header('Location: access_denied.php');
        exit;
    }

    $recordId = (int) ($_POST['record_id'] ?? 0);
    $commissioningDate = trim((string) ($_POST['commissioning_date'] ?? ''));

    if ($recordId <= 0 || !installed_base_current_user_can_use_record($obconn, $recordId)) {
        $_SESSION['error_message'] = 'Selected fab number was not found or is not accessible.';
    } elseif ($commissioningDate === '' || installed_base_format_date_for_input($commissioningDate) === '') {
        $_SESSION['error_message'] = 'Please select a valid commissioning date.';
    } else {
        installed_base_update_commissioning_date($obconn, $recordId, $commissioningDate);
        header('Location: warranty_claims.php?commission_updated=1');
        exit;
    }

    header('Location: warranty_claims.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Claims</title>

    <?php include 'header_css.php'; ?>

    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <style>
        .warranty-claims-page .warranty-grid-id {
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 0.01em;
        }

        .warranty-claims-page .warranty-grid-fab {
            font-weight: 700;
            color: #1565d8;
            font-variant-numeric: tabular-nums;
        }

        .warranty-claims-page .warranty-grid-model {
            color: #334155;
            display: inline-block;
            max-width: 280px;
            white-space: normal;
            line-height: 1.35;
        }

        .warranty-claims-page .warranty-grid-date {
            font-variant-numeric: tabular-nums;
            color: #475569;
            white-space: nowrap;
        }

        .warranty-claims-page .warranty-status-badge {
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
        }

        .warranty-claims-page .warranty-status--standard {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .warranty-claims-page .warranty-status--uptime {
            background: #e0f2fe;
            color: #075985;
            border-color: #7dd3fc;
        }

        .warranty-claims-page .warranty-status--out {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .warranty-claims-page .warranty-status--unknown {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        .warranty-claims-page #warrantyClaimsTable tbody td {
            vertical-align: middle;
        }

        .warranty-claims-page #warrantyClaimsTable thead th:nth-child(6),
        .warranty-claims-page #warrantyClaimsTable tbody td:nth-child(6) {
            text-align: center;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content warranty-claims-page">
            <?php if (!empty($_SESSION['success_message'])) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); } ?>

            <?php if (!empty($_SESSION['error_message'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); } ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">
                        Warranty status of installed machines, derived from the commissioning date.
                    </div>
                </div>

                <div class="header-btn-group">
                    <?php if ($canUpdateCommissioningDate) { ?>
                    <button class="new-order-btn btn-complaint-primary" type="button"
                        data-bs-toggle="modal" data-bs-target="#updateCommissionModal">
                        <i class="bi bi-calendar-check"></i>
                        Update Commissioning Date
                    </button>
                    <?php } ?>
                </div>
            </div>

            <div class="complaint-form-card show" id="warrantyTableCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <div>
                            <h2 class="complaint-form-header__title">Installed Base Warranty Status</h2>
                            <p class="complaint-form-header__subtitle">
                                Standard (0–12 months) · Uptime (13–36 months) · Out of Warranty (after 36 months)
                            </p>
                        </div>
                    </div>
                </div>
                <div class="complaint-form-body">
                    <div class="table-responsive">
                        <table class="table table-hover booking-table w-100" id="warrantyClaimsTable">
                            <thead>
                                <tr>
                                    <th width="8%">ID</th>
                                    <th width="14%">Fab Number</th>
                                    <th width="22%">Customer</th>
                                    <th width="24%">Machine Model</th>
                                    <th width="14%">Commissioned</th>
                                    <th width="18%">Warranty Status</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php if ($canUpdateCommissioningDate) { ?>
    <div class="modal fade" id="updateCommissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content complaint-form-modal">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-calendar-check"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title">Update Commissioning Date</h2>
                            <p class="complaint-form-header__subtitle">Pick a fab number, then set its commissioning date.</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="updateCommissionForm" class="complaint-form-body p-4">
                    <input type="hidden" name="record_id" id="commissionRecordId" value="">
                    <div class="form-group mb-3">
                        <label class="form-label" for="commissionFabSelect">
                            Fab Number <span class="text-danger">*</span>
                        </label>
                        <select class="form-control" id="commissionFabSelect" data-placeholder="Search by ID or fab number">
                            <option value=""></option>
                        </select>
                        <div class="text-danger validation-msg" data-field="record_id"></div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="commissionDateInput">
                            Commissioning Date <span class="text-danger">*</span>
                        </label>
                        <input type="date" class="form-control" name="commissioning_date" id="commissionDateInput">
                        <div class="text-danger validation-msg" data-field="commissioning_date"></div>
                    </div>
                    <div class="complaint-form-actions pb-0">
                        <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="submit-btn btn-complaint-primary" name="update_commissioning_date" value="1" id="submitCommissionUpdateBtn">
                            <i class="bi bi-check-lg"></i> Submit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

    <script src="js/warranty_claims.js"></script>
</body>

</html>
