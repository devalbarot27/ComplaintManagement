<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
include 'includes/spare_parts_helpers.php';
require_once 'includes/after_market_access_helpers.php';

$success_message = '';
$error_message = '';
$sparePartsPermissions = spare_parts_action_permissions($obconn);
$canAddSpareParts = $sparePartsPermissions['add'];
$canEditSpareParts = $sparePartsPermissions['edit'];
$warrantyTypes = spare_parts_warranty_types($obconn);
$reasons = spare_parts_reasons($obconn);
$createdBy = current_user_id($obconn);
$userName = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_spare_parts'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = spare_parts_from_post($_POST);

    if ($recordId > 0) {
        if (!$canEditSpareParts) {
            $error_message = 'Access denied. You do not have permission to edit spare parts records.';
        } elseif (!after_market_user_can_access_record($obconn, 'spare_parts_consumption', $recordId)) {
            $error_message = 'Access denied. You do not have permission to edit this record.';
        }
    } elseif (!$canAddSpareParts) {
        $error_message = 'Access denied. You do not have permission to add spare parts records.';
    }

    if ($error_message === '') {
    $validationError = spare_parts_validate($obconn, $data);
    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? spare_parts_get_installed_base($obconn, $installedBaseId)
        : null;
    $serviceLogId = $data['service_log_id'] !== '' ? (int) $data['service_log_id'] : 0;
    $serviceLog = $serviceLogId > 0
        ? spare_parts_get_service_log($obconn, $serviceLogId)
        : null;

    if ($createdBy === null || $createdBy <= 0) {
        $error_message = 'Unable to resolve logged-in user.';
    } elseif ($validationError !== null) {
        $error_message = $validationError;
    } elseif (!$installedBase) {
        $error_message = 'Selected machine was not found in installed base records.';
    } elseif ($serviceLogId > 0 && (!$serviceLog || (int) $serviceLog['installed_base_id'] !== $installedBaseId)) {
        $error_message = 'Selected service record does not belong to the selected machine.';
    } elseif (trim((string) ($installedBase['fab_number'] ?? '')) !== $data['fab_number']) {
        $error_message = 'Fab Number does not match the selected machine.';
    } else {
        try {
            $serviceLogId = $data['service_log_id'] !== '' ? (int) $data['service_log_id'] : 0;

            if ($recordId > 0) {
                if (!after_market_user_can_access_record($obconn, 'spare_parts_consumption', $recordId)) {
                    $error_message = 'Record not found or already deleted.';
                } elseif (!spare_parts_update_consumption(
                    $obconn,
                    $recordId,
                    $data,
                    $installedBaseId,
                    $serviceLogId
                )) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    $success_message = 'Spare parts record updated successfully.';
                }
            } else {
                spare_parts_save_consumption(
                    $obconn,
                    $data,
                    $installedBaseId,
                    $serviceLogId,
                    $createdBy,
                    $userName
                );
                $success_message = 'Spare parts record saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = 'Failed to save spare parts record.';
        }
    }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spare Parts Consumption</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if (!empty($success_message)) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if (!empty($error_message)) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if (isset($_SESSION['success_message'])) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); } ?>
            <?php if (isset($_SESSION['error_message'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); } ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">Track spare parts usage linked to machines and service records.</div>
                </div>
                <div class="header-btn-group">
<?php if ($canAddSpareParts) { ?>
                    <button class="new-order-btn btn-complaint-primary" id="openSparePartsForm" type="button">
                        <i class="bi bi-plus-lg"></i> New Record
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeSparePartsForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
<?php } ?>
                </div>
            </div>

            <div class="complaint-form-card" id="sparePartsFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-gear"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="formModeLabel">New Spare Parts Consumption</h2>
                            <p class="complaint-form-header__subtitle">Link to a machine; service record is optional.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="sparePartsForm" novalidate>
                    <input type="hidden" name="record_id" id="sparePartsId" value="">
                    <input type="hidden" name="spare_parts_multi" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Machine & Service Link</h3>
                                    <p class="complaint-form-section__hint">Spare parts must be linked to a machine</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="sparePartsMachineSelect">
                                        <i class="bi bi-hdd-stack"></i> Machine <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="installed_base_id" id="sparePartsMachineSelect"
                                        data-placeholder="Search installed base / serial number">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="sparePartsServiceLogSelect">
                                        <i class="bi bi-clipboard-pulse"></i> Service Record
                                    </label>
                                    <select class="form-control" name="service_log_id" id="sparePartsServiceLogSelect"
                                        data-placeholder="Link service record (optional)" disabled>
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="service_log_id"></div>
                                </div>
                                <div class="col-md-4 form-group d-none">
                                    <label class="form-label"><i class="bi bi-receipt"></i> Order ID</label>
                                    <input type="hidden" class="form-control address-auto-field" name="order_id" value="">
                                    <div class="text-danger validation-msg" data-field="order_id"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-upc-scan"></i> Fab Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control address-auto-field" name="fab_number" readonly>
                                    <div class="text-danger validation-msg" data-field="fab_number"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-upc"></i> Serial Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="serial_number" maxlength="50"
                                        placeholder="Enter serial number">
                                    <div class="text-danger validation-msg" data-field="serial_number"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-clock-history"></i> Running Hours <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="running_hours" min="0.01" step="0.01" placeholder="Machine usage">
                                    <div class="text-danger validation-msg" data-field="running_hours"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Spare Parts Details</h3>
                                    <p class="complaint-form-section__hint">Kit reference, quantity and cost</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-calendar-event"></i> Consumption Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="consumption_date">
                                    <div class="text-danger validation-msg" data-field="consumption_date"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-shield-check"></i> Warranty / Chargeable <span class="text-danger">*</span></label>
                                    <select class="form-control" name="warranty_chargeable" id="sparePartsWarrantySelect"
                                        data-placeholder="Search warranty type">
                                        <option value=""></option>
                                        <?php foreach ($warrantyTypes as $type) { ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="warranty_chargeable"></div>
                                </div>
                            </div>

                            <div class="mt-3 mb-3" id="sparePartsAddItemWrapper">
                                <button type="button" class="btn btn-sm btn-outline-dark" id="sparePartsAddItemBtn">
                                    <i class="bi bi-plus-lg"></i> Add Spare Part
                                </button>
                            </div>

                            <div id="sparePartsItemEntries"></div>
                            <div class="text-danger validation-msg mb-3" data-field="spare_parts_items"></div>

                            <div class="row g-3">
                                <div class="col-12 form-group">
                                    <label class="form-label"><i class="bi bi-card-text"></i> Remarks</label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="Additional notes (optional)"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelSparePartsForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" name="submit_spare_parts" id="submitSparePartsBtn">
                            <i class="bi bi-check-lg"></i> Save Record
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Spare Parts Consumption History</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="sparePartsTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Serial No.</th>
                                <th width="10%">Date</th>
                                <th width="10%">Type</th>
                                <th width="12%">Kit Number</th>
                                <th width="8%">Qty</th>
                                <th width="10%">Order Value (₹)</th>
                                <th width="8%">Reason</th>
                                <th width="10%">Service Log</th>
                                <th width="10%">Created At</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="sparePartsReasonOptionsJson"><?php echo json_encode(array_values($reasons), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
    <script src="js/static_select2.js"></script>
    <script src="js/spare_parts_items.js"></script>
    <script src="js/spare_parts_select2.js"></script>
    <script src="js/spare_parts_validation.js"></script>
    <script src="js/spare_parts.js"></script>
    <script>
    $(document).ready(function () {
        initSparePartsPage();
        document.getElementById('cancelSparePartsForm').addEventListener('click', closeSparePartsFormPanel);
        setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
    });
    </script>
</body>

</html>
