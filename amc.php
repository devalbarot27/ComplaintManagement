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

$userName = current_username();
$createdBy = current_user_id($obconn);
$dealerName = current_assignee_name();

$formData = [];
$reopenAmcForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_amc'])) {
    if (!$canAddAmc) {
        $error_message = 'Access denied. You do not have permission to add AMC contracts.';
    } else {
        $data = amc_from_post($_POST);
        $validationError = amc_validate($data);

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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/pincode_select2.js"></script>
    <script src="js/fabno_select2.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">

    <?php include 'sidebar.php'; ?>

    <div class="content">

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
                    Register and track Annual Maintenance Contracts (AMC) for customer machines.
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
                            Capture product, customer and contract details to register a new AMC.
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
                                <h3 class="complaint-form-section__title">Product Details</h3>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3 form-group">
                                <label class="form-label">Product Group <span class="text-danger">*</span></label>
                                <select class="form-control" name="product_group" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_PRODUCT_GROUP_OPTIONS as $pg): ?>
                                    <option value="<?= htmlspecialchars($pg) ?>" <?= (($formData['product_group'] ?? '') === $pg) ? 'selected' : '' ?>><?= htmlspecialchars($pg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Equipment Model</label>
                                <select class="form-control" name="product_model" id="machineModelSelect"
                                    data-placeholder="Search or select machine model">
                                    <option value=""></option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Fab No</label>
                                <select class="form-control" name="fab_number" id="fabNumberSelect"
                                    data-placeholder="Search or select fab number">
                                    <option value=""></option>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Obligation <span class="text-danger">*</span></label>
                                <select class="form-control" name="obligation" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_OBLIGATION_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= (($formData['obligation'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Customer Details</h3>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="customer_name" maxlength="150" required value="<?= htmlspecialchars($formData['customer_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" maxlength="150" value="<?= htmlspecialchars($formData['contact_person'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Telephone</label>
                                <input type="text" class="form-control" name="telephone_number" maxlength="50" value="<?= htmlspecialchars($formData['telephone_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email_id" maxlength="150" value="<?= htmlspecialchars($formData['email_id'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" name="address_line1" maxlength="255" value="<?= htmlspecialchars($formData['address_line1'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" class="form-control" name="address_line2" maxlength="255" value="<?= htmlspecialchars($formData['address_line2'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label" for="amcPincodeSelect">Pin Code</label>
                                <select class="form-control" name="post_code" id="amcPincodeSelect"
                                    data-placeholder="Search or select pincode">
                                    <option value=""></option>
                                </select>
                                <div class="text-danger validation-msg" data-field="pincode"></div>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control address-auto-field" name="city"
                                    maxlength="100" placeholder="Auto-filled from pincode" readonly value="<?= htmlspecialchars($formData['city_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">District</label>
                                <input type="text" class="form-control address-auto-field" name="district"
                                    maxlength="100" placeholder="Auto-filled from pincode" readonly value="<?= htmlspecialchars($formData['district_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control address-auto-field" name="state"
                                    maxlength="100" placeholder="Auto-filled from pincode" readonly value="<?= htmlspecialchars($formData['state_name'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Customer Group</label>
                                <select class="form-control" name="customer_group">
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_CUSTOMER_GROUP_OPTIONS as $cg): ?>
                                    <option value="<?= htmlspecialchars($cg) ?>" <?= (($formData['customer_group'] ?? '') === $cg) ? 'selected' : '' ?>><?= htmlspecialchars($cg) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Business Line</label>
                                <select class="form-control" name="business_line">
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_BUSINESS_LINE_OPTIONS as $bl): ?>
                                    <option value="<?= htmlspecialchars($bl) ?>" <?= (($formData['business_line'] ?? '') === $bl) ? 'selected' : '' ?>><?= htmlspecialchars($bl) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">AMC Details</h3>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-3 form-group">
                                <label class="form-label">Environment</label>
                                <select class="form-control" name="environment">
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_ENVIRONMENT_OPTIONS as $env): ?>
                                    <option value="<?= htmlspecialchars($env) ?>" <?= (($formData['environment'] ?? '') === $env) ? 'selected' : '' ?>><?= htmlspecialchars($env) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">AMC Type <span class="text-danger">*</span></label>
                                <select class="form-control" name="amc_type" id="amcType" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_TYPE_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= (($formData['amc_type'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Mode of Call</label>
                                <select class="form-control" name="mode_of_call">
                                    <option value="">-- Select --</option>
                                    <?php foreach (AMC_MODE_OF_CALL_OPTIONS as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>" <?= (($formData['mode_of_call'] ?? '') === $val) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">AMC Value <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0.01" class="form-control" name="amc_value" required value="<?= htmlspecialchars($formData['amc_value'] ?? '') ?>">
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="form-label">AMC Type Remarks</label>
                                <textarea class="form-control" name="amc_type_remarks" rows="2" maxlength="500"><?= htmlspecialchars($formData['amc_type_remarks'] ?? '') ?></textarea>
                                <small class="text-muted">Required (min 5 characters) when AMC Type is Standard.</small>
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">AMC Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_start_date" required value="<?= htmlspecialchars($formData['amc_start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">AMC End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="amc_end_date" required value="<?= htmlspecialchars($formData['amc_end_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">Visit Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="visit_start_date" required value="<?= htmlspecialchars($formData['visit_start_date'] ?? '') ?>">
                            </div>
                            <div class="col-md-3 form-group">
                                <label class="form-label">No. of Visits <span class="text-danger">*</span></label>
                                <input type="number" min="1" max="52" class="form-control" name="no_of_visits" required value="<?= htmlspecialchars($formData['no_of_visits'] ?? '') ?>">
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

        <div class="card mt-3" id="amcTableCard" style="<?= $reopenAmcForm ? 'display:none;' : '' ?>">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-list-ul"></i>
                <strong>AMC Contracts</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="padding:10px;">
                    <table id="amcContractsTable" class="table table-hover mb-0 datatable-standard">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Contract No.</th>
                                <th>Customer</th>
                                <th>Product Model</th>
                                <th>Fab No</th>
                                <th>AMC Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Visits</th>
                                <th>Value</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($amcContracts as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= htmlspecialchars($row['contract_number']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['product_model'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['fab_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(AMC_TYPE_OPTIONS[$row['amc_type']] ?? $row['amc_type']) ?></td>
                                <td><?= htmlspecialchars($row['amc_start_date']) ?></td>
                                <td><?= htmlspecialchars($row['amc_end_date']) ?></td>
                                <td><?= (int) $row['no_of_visits'] ?></td>
                                <td><?= htmlspecialchars(number_format((float) $row['amc_value'], 2)) ?></td>
                                <td><span class="badge <?= amc_status_badge_class($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                <td class="text-nowrap">
                                    <div class="d-flex gap-1">
                                        <a href="amc_details.php?id=<?= rawurlencode(base64_encode((string) $row['id'])) ?>"
                                            class="btn btn-sm btn-outline-dark">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <?php if ($canDeleteAmc): ?>
                                        <a href="delete_amc.php?id=<?= rawurlencode(base64_encode((string) $row['id'])) ?>"
                                            class="btn btn-sm btn-outline-dark"
                                            onclick="return confirm('Delete this AMC contract?');">
                                            <i class="bi bi-trash"></i> Delete
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
(function () {
    const openBtn = document.getElementById('openAmcForm');
    const closeBtn = document.getElementById('closeAmcForm');
    const cancelBtn = document.getElementById('cancelAmcForm');
    const formCard = document.getElementById('amcFormCard');
    const tableCard = document.getElementById('amcTableCard');

    function showForm() {
        if (!formCard) return;
        formCard.style.display = 'block';
        tableCard.style.display = 'none';
        if (openBtn) openBtn.style.display = 'none';
        if (closeBtn) closeBtn.style.display = '';
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) return;
        formCard.style.display = 'none';
        tableCard.style.display = 'block';
        if (openBtn) openBtn.style.display = '';
        if (closeBtn) closeBtn.style.display = 'none';
    }

    if (openBtn) openBtn.addEventListener('click', showForm);
    if (closeBtn) closeBtn.addEventListener('click', hideForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

    $(document).ready(function () {
        $('#amcContractsTable').DataTable({
            order: [[0, 'desc']]
        });

        const $machineModelSelect = $('#machineModelSelect');
        $machineModelSelect.select2({
            width: '100%',
            placeholder: $machineModelSelect.data('placeholder') || 'Search or select machine model',
            allowClear: true,
            minimumInputLength: 0,
            ajax: {
                url: 'api/machine_model_search.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term || '' };
                },
                processResults: function (data) {
                    return data;
                },
                cache: true
            },
            language: {
                noResults: function () { return 'No machine model found'; },
                searching: function () { return 'Searching...'; }
            }
        });

        function setAmcMachineModel(code, description) {
            $machineModelSelect.val(null).trigger('change');

            if (!code) {
                return;
            }

            const label = description ? code + ' - ' + description : code;
            const option = new Option(label, code, true, true);
            $machineModelSelect.append(option).trigger('change');
        }

        initFabnoSelect2('amcForm', 'fabNumberSelect', {
            onSelect: function (data) {
                setAmcMachineModel(data.machine_model_code || '', data.machine_model || '');
            },
            onClear: function () {
                setAmcMachineModel('', '');
            }
        });

        initPincodeSelect2('amcForm', 'amcPincodeSelect');

        <?php if ($reopenAmcForm): ?>
        setAmcMachineModel(<?= json_encode($formData['product_model'] ?? '') ?>, '');
        setFabNumberSelect2('fabNumberSelect', 'amcForm', <?= json_encode($formData['fab_number'] ?? '') ?>);
        <?php if (($formData['post_code'] ?? '') !== ''): ?>
        setPincodeSelect2($('#amcForm')[0], 'amcPincodeSelect', {
            pincode: <?= json_encode($formData['post_code']) ?>,
            city: <?= json_encode($formData['city_name'] ?? '') ?>,
            district: <?= json_encode($formData['district_name'] ?? '') ?>,
            state: <?= json_encode($formData['state_name'] ?? '') ?>
        });
        <?php endif; ?>
        <?php endif; ?>
    });
})();
</script>
</body>
</html>
