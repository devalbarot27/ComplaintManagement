<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/amc_helpers.php';

amc_ensure_schema($obconn);

$active_menu = 'amc';
$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$amcPermissions = amc_action_permissions($obconn);
$canAddAmc = $amcPermissions['add'];
$canDeleteAmc = $amcPermissions['delete'];
$canSeeAddedBy = amc_can_view_added_by($obconn);

$userName = current_username();
$createdBy = current_user_id($obconn);
$dealerName = current_assignee_name();

$formData = [];
$reopenAmcForm = false;
$installedBaseSnapshot = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_amc'])) {
    if (!$canAddAmc) {
        $error_message = 'Access denied. You do not have permission to add AMC contracts.';
    } else {
        $data = amc_from_post($_POST);
        $validationError = amc_validate($data);

        if ((int) ($data['installed_base_id'] ?? 0) > 0) {
            $installedBaseSnapshot = amc_installed_base_snapshot($obconn, (int) $data['installed_base_id']);
        }

        if ($validationError === null) {
            if ($installedBaseSnapshot === null) {
                $validationError = 'Please search for and select a valid Installed Base machine.';
            } elseif (trim((string) ($installedBaseSnapshot['fab_number'] ?? '')) === '') {
                $validationError = 'The selected Installed Base record does not have a FAB number.';
            } elseif (trim((string) ($installedBaseSnapshot['customer_name'] ?? '')) === '') {
                $validationError = 'Customer details are not available for the selected Installed Base record.';
            } else {
                $data = amc_merge_installed_base_snapshot($data, $installedBaseSnapshot);
            }
        }

        if ($validationError !== null) {
            $error_message = $validationError;
            $formData = $data;
            $reopenAmcForm = true;
        } elseif ($createdBy === null || $createdBy <= 0) {
            $error_message = 'Unable to resolve logged-in user.';
            $formData = $data;
            $reopenAmcForm = true;
        } else {
            try {
                $newId = amc_insert_record($obconn, $data, (int) $createdBy, $userName, $dealerName);
                $_SESSION['success_message'] = 'AMC contract registered successfully.';
                header('Location: amc.php');
                exit;
            } catch (PDOException $e) {
                $error_message = 'Failed to save AMC contract. Please try again.';
                $formData = $data;
                $reopenAmcForm = true;
            }
        }
    }
}

$amcContracts = amc_list($obconn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMC Registration</title>
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
    <style>
        .amc-page .warranty-status-badge {
            border: 1px solid transparent;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            line-height: 1.2;
            display: inline-block;
        }
        .amc-page .warranty-status--standard {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }
        .amc-page .warranty-status--uptime {
            background: #e0f2fe;
            color: #075985;
            border-color: #7dd3fc;
        }
        .amc-page .warranty-status--out {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        .amc-page .warranty-status--unknown {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }
        .amc-page #amcContractsTable tbody td {
            vertical-align: middle;
        }
        .amc-page #amcContractsTable tbody td:last-child {
            white-space: nowrap;
        }
        #amcWarrantyBadge{
            display: none !important;
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

        <div class="content amc-page">

        <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($success_message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <div class="page-subtitle">
                    Register AMC contracts against an Installed Base machine. FAB number, model, warranty and customer details are filled from the selected record.
                </div>
            </div>
            <?php if ($canAddAmc): ?>
            <div class="header-btn-group">
                <button class="new-order-btn btn-complaint-primary" id="openAmcForm" type="button" style="<?= $reopenAmcForm ? 'display:none;' : '' ?>">
                    <i class="bi bi-plus-lg"></i> New AMC Contract
                </button>
                <button class="close-form-btn cancel-btn" id="closeAmcForm" type="button" style="<?= $reopenAmcForm ? '' : 'display:none;' ?>">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canAddAmc): ?>
        <div class="complaint-form-card" id="amcFormCard" style="<?= $reopenAmcForm ? 'display:block;' : 'display:none;' ?>">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">New AMC Registration</h2>
                        <p class="complaint-form-header__subtitle">
                            Select an Installed Base machine, then enter AMC contract details.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" id="amcForm" novalidate>
                <div class="complaint-form-body">

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Installed Base</h3>
                                <p class="complaint-form-section__hint">Search and select a machine. FAB number, model and warranty status are filled automatically.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6 form-group">
                                <label class="form-label" for="amcInstalledBaseSelect">
                                    Installed Base Search <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="installed_base_id" id="amcInstalledBaseSelect"
                                    data-placeholder="Search by FAB number, customer or model" required>
                                    <option value=""></option>
                                </select>
                                <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">FAB Number</label>
                                <input type="text" class="form-control address-auto-field" id="amcFabNumber"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['fab_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Equipment Model</label>
                                <input type="text" class="form-control address-auto-field" id="amcEquipmentModel"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['product_model'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Warranty Status</label>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <input type="text" class="form-control address-auto-field" id="amcWarrantyStatus"
                                        placeholder="Auto-filled from the selected machine" readonly
                                        value="<?= htmlspecialchars($installedBaseSnapshot['warranty_status'] ?? '') ?>">
                                    <span id="amcWarrantyBadge" class="warranty-status-badge <?= htmlspecialchars($installedBaseSnapshot['warranty_badge_class'] ?? 'warranty-status--unknown') ?>"
                                        style="<?= empty($installedBaseSnapshot['warranty_status']) ? 'display:none;' : '' ?>">
                                        <?= htmlspecialchars($installedBaseSnapshot['warranty_status'] ?? '') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4 form-group d-none">
                                <label class="form-label">Under AMC</label>
                                <input type="text" class="form-control address-auto-field" id="amcUnderAmc"
                                    placeholder="Auto-filled from the selected machine" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['under_amc'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group d-none">
                                <label class="form-label">AMC End Date</label>
                                <input type="text" class="form-control address-auto-field" id="amcExistingEndDate"
                                    placeholder="Shown when the machine is under AMC" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['amc_end_date_label'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Customer Details</h3>
                                <p class="complaint-form-section__hint">Populated from the customer linked to the selected Installed Base record.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label">Customer Name</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerName"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['customer_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Mobile</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerMobile"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['telephone_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Email</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerEmail"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['email_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Street 1</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerStreet1"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['address_line1'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="form-label">Street 2</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerStreet2"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['address_line2'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Pincode</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerPincode"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['post_code'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerCity"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['city_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerDistrict"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['district_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control address-auto-field" id="amcCustomerState"
                                    placeholder="Auto-filled from Installed Base" readonly
                                    value="<?= htmlspecialchars($installedBaseSnapshot['state_name'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">AMC Details</h3>
                                <p class="complaint-form-section__hint">Enter the contract type, value, dates and visit plan.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="amcType">
                                    <i class="bi bi-tags"></i> AMC Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" name="amc_type" id="amcType"
                                    data-placeholder="Select AMC type" required>
                                    <option value=""></option>
                                    <?php foreach (AMC_TYPE_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= (($formData['amc_type'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="amc_type"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC Value <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amc_value" required
                                    value="<?= htmlspecialchars($formData['amc_value'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="amc_value"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Number of Visits <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="52" step="1" class="form-control" name="no_of_visits" required
                                    value="<?= htmlspecialchars($formData['no_of_visits'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="no_of_visits"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_start_date" required
                                    value="<?= htmlspecialchars($formData['amc_start_date'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="amc_start_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">AMC End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_end_date" required
                                    value="<?= htmlspecialchars($formData['amc_end_date'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="amc_end_date"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Visit Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="visit_start_date" required
                                    value="<?= htmlspecialchars($formData['visit_start_date'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="visit_start_date"></div>
                            </div>
                        </div>
                    </section>

                </div>

                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-outline-secondary" id="cancelAmcForm">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" name="submit_amc" class="btn btn-complaint-primary">
                        <i class="bi bi-send"></i> Register AMC
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="complaint-form-card show" id="amcTableCard" style="<?= $reopenAmcForm ? 'display:none;' : '' ?>">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">AMC Contracts</h2>
                        <p class="complaint-form-header__subtitle">
                            Track registered AMC contracts, warranty status and visit coverage.
                        </p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table id="amcContractsTable" class="table table-hover booking-table w-100">
                        <thead>
                            <tr>
                                <th width="6%">#</th>
                                <th width="12%">Contract No.</th>
                                <th width="14%">Customer</th>
                                <th width="14%">Equipment Model</th>
                                <th width="10%">FAB Number</th>
                                <th width="12%">Warranty</th>
                                <th width="10%">AMC Type</th>
                                <th width="10%">Start Date</th>
                                <th width="10%">End Date</th>
                                <th width="6%">Visits</th>
                                <th width="8%">Value</th>
                                <th width="8%">Status</th>
                                <?php if ($canSeeAddedBy): ?>
                                <th width="10%">Added By</th>
                                <?php endif; ?>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $amcCommissioningLookup = installed_base_commissioning_lookup(
                                $obconn,
                                array_map(static fn ($contractRow) => (int) ($contractRow['installed_base_id'] ?? 0), $amcContracts),
                                array_map(static fn ($contractRow) => (string) ($contractRow['fab_number'] ?? ''), $amcContracts)
                            );
                            foreach ($amcContracts as $row): ?>
                            <?php
                                $amcId = (int) $row['id'];
                                $encodedAmcId = rawurlencode(base64_encode((string) $amcId));
                                $installedBaseId = (int) ($row['installed_base_id'] ?? 0);
                                $fabNumber = trim((string) ($row['fab_number'] ?? ''));
                                $amcRowWarranty = installed_base_warranty_details(
                                    installed_base_commissioning_resolve($amcCommissioningLookup, $installedBaseId, $fabNumber)
                                );
                            ?>
                            <tr>
                                <td><?= $amcId ?></td>
                                <td>
                                    <a href="amc_details.php?id=<?= htmlspecialchars($encodedAmcId, ENT_QUOTES, 'UTF-8') ?>"
                                        class="text-primary fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($row['contract_number']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['customer_name'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['product_model'] ?? '-')) ?></td>
                                <td>
                                    <?php if ($installedBaseId > 0 && $fabNumber !== ''): ?>
                                    <a href="installed_base_details.php?id=<?= htmlspecialchars(rawurlencode(base64_encode((string) $installedBaseId)), ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank" rel="noopener"
                                        class="text-primary fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($fabNumber) ?>
                                    </a>
                                    <?php else: ?>
                                    <?= htmlspecialchars($fabNumber !== '' ? $fabNumber : '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark"><?= htmlspecialchars((string) $amcRowWarranty['status']) ?></span>
                                    <?php if ($amcRowWarranty['end_date_label'] !== '' && $amcRowWarranty['end_date_label'] !== '-') { ?>
                                    <div class="amc-coverage-meta">
                                        <?= htmlspecialchars((string) $amcRowWarranty['end_date_heading']) ?>:
                                        <?= htmlspecialchars((string) $amcRowWarranty['end_date_label']) ?>
                                    </div>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars(AMC_TYPE_OPTIONS[$row['amc_type']] ?? ($row['amc_type'] ?: '-')) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars((string) ($row['amc_start_date'] ?? '-')) ?></td>
                                <td><?= htmlspecialchars((string) ($row['amc_end_date'] ?? '-')) ?></td>
                                <td><?= (int) $row['no_of_visits'] ?></td>
                                <td><?= htmlspecialchars(number_format((float) $row['amc_value'], 2)) ?></td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars(amc_display_status($row)) ?>
                                    </span>
                                </td>
                                <?php if ($canSeeAddedBy): ?>
                                <td><?= htmlspecialchars(amc_added_by_label($row)) ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="amc_details.php?id=<?= htmlspecialchars($encodedAmcId, ENT_QUOTES, 'UTF-8') ?>"
                                            class="btn btn-sm btn-outline-dark" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($canDeleteAmc): ?>
                                        <a href="delete_amc.php?id=<?= htmlspecialchars($encodedAmcId, ENT_QUOTES, 'UTF-8') ?>"
                                            class="btn btn-sm btn-outline-dark" title="Delete"
                                            onclick="return confirm('Delete this AMC contract?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
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

<script>
window.amcPreselectedInstalledBase = <?= json_encode($installedBaseSnapshot ?: null, JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="js/amc.js"></script>
</body>
</html>