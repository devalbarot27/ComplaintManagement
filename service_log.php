<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/service_log_helpers.php';

$active_menu = 'service_log';
$success_message = '';
$error_message = '';
$warrantyTypes = service_log_warranty_types();
$partReplacedOptions = service_log_part_replaced_options();
$feedbackOptions = service_log_customer_feedback_options();
$createdBy = 1;
$userName = current_username();


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_log'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = service_log_from_post($_POST);
    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? service_log_get_installed_base($obconn, $installedBaseId, $userName)
        : null;

    if ($installedBase) {
        $data['machine_model'] = service_log_machine_model_from_installed_base($installedBase);
    }

    $validationError = service_log_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (!$installedBase) {
        $error_message = 'Selected installed base record was not found or is not assigned to your account.';
    } elseif ($installedBase['order_id'] !== $data['order_id']) {
        $error_message = 'Order ID does not match the selected installed base record.';
    } elseif (trim((string) ($installedBase['fab_number'] ?? '')) !== $data['fab_number']) {
        $error_message = 'Fab Number does not match the selected installed base record.';
    } else {
        try {
            $bindData = function ($stmt) use ($data, $installedBase) {
                $orderRefId = (int) ($installedBase['order_ref_id'] ?? 0);
                $stmt->bindValue(':installed_base_id', (int) $data['installed_base_id'], PDO::PARAM_INT);
                if ($orderRefId > 0) {
                    $stmt->bindValue(':order_ref_id', $orderRefId, PDO::PARAM_INT);
                } else {
                    $stmt->bindValue(':order_ref_id', null, PDO::PARAM_NULL);
                }
                $stmt->bindValue(':order_id', $installedBase['order_id']);
                $stmt->bindValue(':fab_number', $data['fab_number']);
                $stmt->bindValue(':serial_number', $data['serial_number']);
                $stmt->bindValue(':machine_model', $data['machine_model']);
                $stmt->bindValue(':warranty_chargeable', $data['warranty_chargeable']);
                $stmt->bindValue(':complaint_date', $data['complaint_date']);
                $stmt->bindValue(':issue_description', $data['issue_description']);
                $stmt->bindValue(':engineer_name', $data['engineer_name']);
                $stmt->bindValue(':visit_date', $data['visit_date']);
                $stmt->bindValue(':action_taken', $data['action_taken']);
                $stmt->bindValue(':closure_date', $data['closure_date']);
                $stmt->bindValue(':part_replaced', $data['part_replaced']);
                $stmt->bindValue(':running_hours', $data['running_hours']);
                $stmt->bindValue(':loaded_hours', $data['loaded_hours']);
                $stmt->bindValue(':customer_feedback', $data['customer_feedback'] !== '' ? $data['customer_feedback'] : null);
                $stmt->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
                service_log_bind_remaining_consumables($stmt, $data);
            };

            if ($recordId > 0) {
                $checkStmt = $obconn->prepare('
                    SELECT id FROM service_logs
                    WHERE id = :id
                      AND deleted_at IS NULL
                      AND TRIM(username) = :username
                ');
                $checkStmt->bindValue(':id', $recordId, PDO::PARAM_INT);
                $checkStmt->bindValue(':username', $userName);
                $checkStmt->execute();

                if (!$checkStmt->fetch(PDO::FETCH_ASSOC)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    $update = $obconn->prepare('
                        UPDATE service_logs SET
                            installed_base_id = :installed_base_id,
                            order_ref_id = :order_ref_id,
                            order_id = :order_id,
                            fab_number = :fab_number,
                            serial_number = :serial_number,
                            machine_model = :machine_model,
                            warranty_chargeable = :warranty_chargeable,
                            complaint_date = :complaint_date,
                            issue_description = :issue_description,
                            engineer_name = :engineer_name,
                            visit_date = :visit_date,
                            action_taken = :action_taken,
                            closure_date = :closure_date,
                            part_replaced = :part_replaced,
                            running_hours = :running_hours,
                            loaded_hours = :loaded_hours,
                            customer_feedback = :customer_feedback,
                            remarks = :remarks,
                            ' . service_log_remaining_consumable_set_clause() . ',
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = :id AND deleted_at IS NULL
                    ');
                    $bindData($update);
                    $update->bindValue(':id', $recordId, PDO::PARAM_INT);
                    $update->execute();
                    $success_message = 'Service log updated successfully.';
                }
            } else {
                $insert = $obconn->prepare('
                    INSERT INTO service_logs (
                        installed_base_id, order_ref_id, order_id, fab_number, serial_number, machine_model,
                        warranty_chargeable, complaint_date, issue_description, engineer_name,
                        visit_date, action_taken, closure_date, part_replaced,
                        running_hours, loaded_hours, customer_feedback, remarks,
                        ' . service_log_remaining_consumable_insert_columns() . ', created_by, username
                    ) VALUES (
                        :installed_base_id, :order_ref_id, :order_id, :fab_number, :serial_number, :machine_model,
                        :warranty_chargeable, :complaint_date, :issue_description, :engineer_name,
                        :visit_date, :action_taken, :closure_date, :part_replaced,
                        :running_hours, :loaded_hours, :customer_feedback, :remarks,
                        ' . service_log_remaining_consumable_insert_placeholders() . ', :created_by, :username
                    )
                ');
                $bindData($insert);
                $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
                $insert->bindValue(':username', $userName);
                $insert->execute();
                $success_message = 'Service log saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = 'Failed to save service log.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Log Capture</title>
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
                    <div class="page-subtitle">Capture service visit and resolution details linked to installed base.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openServiceLogForm" type="button">
                        <i class="bi bi-plus-lg"></i> New Service Log
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeServiceLogForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="serviceLogFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-clipboard-pulse"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="formModeLabel">New Service Log</h2>
                            <p class="complaint-form-header__subtitle">Closure date is mandatory to complete the service log.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="serviceLogForm" novalidate>
                    <input type="hidden" name="record_id" id="serviceLogId" value="">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Machine & Order</h3>
                                    <p class="complaint-form-section__hint">Linked to an installed base record</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="installedBaseLinkSelect">
                                        <i class="bi bi-link-45deg"></i> Installed Base <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="installed_base_id" id="installedBaseLinkSelect"
                                        data-placeholder="Search installed base record">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="installed_base_id"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-receipt"></i> Order ID <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control address-auto-field" name="order_id" readonly>
                                    <div class="text-danger validation-msg" data-field="order_id"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-upc-scan"></i> Fab Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control address-auto-field" name="fab_number" readonly>
                                    <div class="text-danger validation-msg" data-field="fab_number"></div>
                                </div>
                                  <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-cpu"></i> Machine Model <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control address-auto-field" name="machine_model" maxlength="150"
                                        placeholder="Auto-filled from installed base" readonly>
                                    <div class="text-danger validation-msg" data-field="machine_model"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-upc"></i> Serial Number <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="serial_number" maxlength="50"
                                        placeholder="Enter serial number">
                                    <div class="text-danger validation-msg" data-field="serial_number"></div>
                                </div>
                              
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-shield-check"></i> Warranty / Chargeable <span class="text-danger">*</span></label>
                                    <select class="form-control" name="warranty_chargeable" id="serviceLogWarrantySelect"
                                        data-placeholder="Search service type">
                                        <option value=""></option>
                                        <?php foreach ($warrantyTypes as $type) { ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="warranty_chargeable"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-calendar-x"></i> Complaint Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="complaint_date">
                                    <div class="text-danger validation-msg" data-field="complaint_date"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Issue & Service</h3>
                                    <p class="complaint-form-section__hint">Problem description and service visit details</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 form-group">
                                    <label class="form-label"><i class="bi bi-exclamation-circle"></i> Issue Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="issue_description" rows="2" placeholder="Problem description"></textarea>
                                    <div class="text-danger validation-msg" data-field="issue_description"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-person-gear"></i> Engineer Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="engineer_name" maxlength="150"
                                        placeholder="Enter engineer name">
                                    <div class="text-danger validation-msg" data-field="engineer_name"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-calendar-event"></i> Visit Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="visit_date">
                                    <div class="text-danger validation-msg" data-field="visit_date"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label"><i class="bi bi-calendar-check"></i> Closure Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="closure_date">
                                    <div class="text-danger validation-msg" data-field="closure_date"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label"><i class="bi bi-tools"></i> Action Taken <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="action_taken" rows="2" placeholder="Resolution details"></textarea>
                                    <div class="text-danger validation-msg" data-field="action_taken"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">3</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Usage & Feedback</h3>
                                    <p class="complaint-form-section__hint">Machine hours and customer feedback</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-gear-wide"></i> Part Replaced <span class="text-danger">*</span></label>
                                    <select class="form-control" name="part_replaced" id="serviceLogPartReplacedSelect"
                                        data-placeholder="Search part replaced">
                                        <option value=""></option>
                                        <?php foreach ($partReplacedOptions as $option) { ?>
                                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="part_replaced"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-clock-history"></i> Running Hours <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="running_hours" min="0" step="0.01" placeholder="Machine usage">
                                    <div class="text-danger validation-msg" data-field="running_hours"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-speedometer2"></i> Loaded Hours <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="loaded_hours" min="0" step="0.01" placeholder="Operational load">
                                    <div class="text-danger validation-msg" data-field="loaded_hours"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label"><i class="bi bi-chat-quote"></i> Customer Feedback</label>
                                    <select class="form-control" name="customer_feedback" id="serviceLogFeedbackSelect"
                                        data-placeholder="Search customer feedback">
                                        <option value=""></option>
                                        <?php foreach ($feedbackOptions as $feedback) { ?>
                                        <option value="<?php echo htmlspecialchars($feedback); ?>"><?php echo htmlspecialchars($feedback); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="customer_feedback"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label"><i class="bi bi-card-text"></i> Remarks</label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="Additional notes (optional)"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">4</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Remaining Consumables Details</h3>
                                    <p class="complaint-form-section__hint">Optional remaining life for consumable parts</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <?php foreach (service_log_remaining_consumable_fields() as $consumable) {
                                    $dateField = $consumable['key'] . '_remaining_date';
                                    $hoursField = $consumable['key'] . '_remaining_hours';
                                    ?>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-event"></i>
                                        <?php echo htmlspecialchars($consumable['label']); ?> Remaining Date
                                    </label>
                                    <input type="date" class="form-control" name="<?php echo htmlspecialchars($dateField); ?>">
                                    <div class="text-danger validation-msg" data-field="<?php echo htmlspecialchars($dateField); ?>"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-clock-history"></i>
                                        <?php echo htmlspecialchars($consumable['label']); ?> Remaining Hours
                                    </label>
                                    <input type="number" class="form-control" name="<?php echo htmlspecialchars($hoursField); ?>"
                                        min="0" step="0.01" placeholder="Optional hours">
                                    <div class="text-danger validation-msg" data-field="<?php echo htmlspecialchars($hoursField); ?>"></div>
                                </div>
                                <?php } ?>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelServiceLogForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" name="submit_service_log" id="submitServiceLogBtn">
                            <i class="bi bi-check-lg"></i> Save Service Log
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Service Log History</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="serviceLogTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Order ID</th>
                                <th width="10%">Serial No.</th>
                                <th width="12%">Machine Model</th>
                                <th width="10%">Service Type</th>
                                <th width="12%">Engineer</th>
                                <th width="10%">Visit Date</th>
                                <th width="10%">Closure Date</th>
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

    <script src="js/static_select2.js"></script>
    <script src="js/service_log_installed_base_select2.js"></script>
    <script src="js/service_log_validation.js"></script>
    <script src="js/service_log.js"></script>
    <script>
    $(document).ready(function () {
        initServiceLogPage();
        document.getElementById('cancelServiceLogForm').addEventListener('click', closeServiceLogFormPanel);
        setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
    });
    </script>
</body>

</html>
