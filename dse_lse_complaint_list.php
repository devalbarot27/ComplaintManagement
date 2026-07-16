<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/admin_access_helpers.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/complaint_datatable_helpers.php';
require_once 'includes/complaint_service_log_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

admin_refresh_session_role($obconn);
complaint_assigned_require_page_access($obconn);

$active_menu = 'complaint_list';
$assignedComplaintPermissions = complaint_assigned_action_permissions($obconn);
$canViewAssignedComplaintList = $assignedComplaintPermissions['view'];
$canServiceUpdateAssignedComplaint = $assignedComplaintPermissions['service_update'];
$complaintServiceLogPermissions = complaint_service_log_action_permissions($obconn);
$canAddComplaintServiceLog = $complaintServiceLogPermissions['add'];
$canEditComplaintServiceLog = $complaintServiceLogPermissions['edit'];
$canShowComplaintServiceLogModal = $canServiceUpdateAssignedComplaint && ($canAddComplaintServiceLog || $canEditComplaintServiceLog);
$serviceLogWarrantyTypes = service_log_warranty_types($obconn);
$partReplacedOptions = service_log_part_replaced_options($obconn);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dealer - Complaint</title>

    <?php include('header_css.php'); ?>

    <link href="css/dse_lse_complaint.css" rel="stylesheet" />
    <link href="css/complaint_status_cards.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <!-- DataTables -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link href="css/datatable_custom.css" rel="stylesheet" />
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>

<!-- validate.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>

</head>

<body>


    <div class="main-wrapper" id="mainWrapper">

        <?php include('sidebar.php'); ?>

        <div class="content">

            <!-- PAGE HEADER -->

            <?php  if(!empty($success_message)){ ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php  } ?>

            <?php if(isset($_SESSION['success_message'])) { ?>

            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <?php
                unset($_SESSION['success_message']);
                }
                ?>

            <?php if (isset($_SESSION['error_message'])) { ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php
                unset($_SESSION['error_message']);
                }
                ?>

            <div class="booking-card">

                <div class="booking-header">

                    <div class="booking-title">
                        Assigned Complaint List
                    </div>

                </div>

                <div class="table-responsive">

                    <table class="table table-hover booking-table w-100" id="dscComplaintTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Fab Number</th>
                                <th width="10%">Customer Name</th>
                                <th width="10%">Complaint Category</th>
                                <th width="10%">Assigned To</th>
                                <th width="15%">Assigned Date</th>
                                <th>Remarks</th>
                                <th width="10%">Status</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>

                </div>

            </div>






        </div>
    </div>





    <?php if ($canServiceUpdateAssignedComplaint) { ?>
    <div class="modal fade" id="serviceUpdateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content complaint-form-modal">
                <form method="post" action="service_update_complaint.php" id="serviceUpdateForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="complaint_id" id="serviceComplaintId">
                    <div class="complaint-form-header">
                        <div class="complaint-form-header__main">
                            <div class="complaint-form-header__icon">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <h2 class="complaint-form-header__title">Service Update</h2>
                                <p class="complaint-form-header__subtitle">Record visit details and submit for Head Office approval.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="complaint-form-body">
                        <div class="complaint-form-notice">
                            <i class="bi bi-info-circle"></i>
                            <div>
                                <strong>HO Approval Required:</strong> This service update will be submitted for Head Office approval before further action can be taken.
                            </div>
                        </div>
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Visit Details</h3>
                                    <p class="complaint-form-section__hint">Customer visit date and service action taken</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-calendar-event"></i>
                                        Customer Visit Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" name="customer_visit_date" id="customer_visit_date" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                    <div class="text-danger validation-msg" data-field="customer_visit_date"></div>
                                </div>
                                <div class="col-md-8 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-gear"></i>
                                        Part Replaced
                                    </label>
                                    <input type="text" class="form-control" name="part_replaced" placeholder="Enter part replaced (if any)">
                                    <div class="text-danger validation-msg" data-field="part_replaced"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-chat-left-text"></i>
                                        Complaint Action Taken <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" name="complaint_action_taken" rows="3" placeholder="Describe the action taken during the visit"></textarea>
                                    <div class="text-danger validation-msg" data-field="complaint_action_taken"></div>
                                </div>
                            </div>
                        </section>
                        <?php if ($canServiceUpdateAssignedComplaint) { ?>
                        <section class="complaint-form-section" id="serviceUpdateServiceLogSection">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 w-100">
                                    <div>
                                        <h3 class="complaint-form-section__title">Service Log <span class="text-danger">*</span></h3>
                                        <p class="complaint-form-section__hint">Only the service log for the current complaint cycle is shown and required before submit</p>
                                    </div>
                                </div>
                            </div>
                            <div class="alert alert-warning py-2 px-3 mb-3 d-none" id="serviceUpdateServiceLogNotice"></div>
                            <div class="text-muted small mb-3" id="serviceUpdateServiceLogEmpty">
                                Loading service log details...
                            </div>
                            <div id="serviceUpdateServiceLogDetails" class="d-none"></div>
                            <div class="text-danger validation-msg" data-field="service_log" id="serviceUpdateServiceLogValidation"></div>
                        </section>
                        <?php } ?>
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">3</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Service Report</h3>
                                    <p class="complaint-form-section__hint">Upload supporting service documentation</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-paperclip"></i>
                                        Service Report <span class="text-danger">*</span> 
                                    </label>
                                    <small class="text-muted d-block mb-2" style="font-size:12px">
                                        Multiple files allowed.
                                        <br>Allowed file types: PDF, JPG, JPEG, PNG, DOC, DOCX.
                                        <br>Maximum file size: 2 MB per file.                                    
                                    </small>
                                    <input type="file" class="form-control" name="service_report[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                    <div class="text-danger validation-msg" data-field="service_report"></div>
                                </div>
                            </div>
                        </section>
                    
                    </div>
                    <div class="complaint-form-actions">
                        <?php include __DIR__ . '/includes/complaint_service_update_save_actions.php'; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <?php if ($canShowComplaintServiceLogModal) {
        include 'includes/complaint_service_log_modal.php';
    } ?>

<?php if ($canShowComplaintServiceLogModal) { ?>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="js/static_select2.js"></script>
<script src="js/customer_feedback_rating.js"></script>
<script src="js/complaint_service_log_modal.js"></script>
<script src="js/complaint_service_log_draft.js"></script>
<?php } ?>

<?php if ($canServiceUpdateAssignedComplaint) { ?>
<script src="js/complaint_service_update_service_log.js"></script>
<script src="js/complaint_service_update_save.js"></script>
<?php } ?>

<script>

function initAssignedComplaintDatatable() {
    const $table = $('#dscComplaintTable');
    if (!$table.length) {
        return null;
    }

    const params = new URLSearchParams(window.location.search);
    const rawComplaintId = String(params.get('complaint_id') || '').trim();
    const complaintId = rawComplaintId !== '' && /^\d+$/.test(rawComplaintId)
        ? String(parseInt(rawComplaintId, 10))
        : '';

    const table = $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/assigned_complaints_datatable.php',
            type: 'POST',
            data: function (d) {
                if (complaintId !== '') {
                    d.complaint_id = complaintId;
                }
            }
        },
        order: [[5, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'c_id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'complaint_category' },
            { data: 'assign_complaint' },
            { data: 'assign_complaint_datetime' },
            { data: 'remarks' },
            { data: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No assigned complaints found.',
            zeroRecords: 'No matching assigned complaints found.'
        }
    });

    return table;
}

let serviceUpdateSubmitting = false;

function initServiceUpdateValidation() {
    const form = document.getElementById('serviceUpdateForm');

    if (!form) {
        return;
    }

    const allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const maxFileSize = 2 * 1024 * 1024;

    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });

        if (typeof clearServiceUpdateServiceLogValidation === 'function') {
            clearServiceUpdateServiceLogValidation();
        }
    }

    function setFieldError(fieldName, message) {
        if (fieldName === 'service_log') {
            if (typeof showServiceUpdateServiceLogValidation === 'function') {
                showServiceUpdateServiceLogValidation(message);
            }
            return;
        }

        const input = fieldName === 'service_report'
            ? form.querySelector('input[name="service_report[]"]')
            : form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');

        if (input) {
            input.classList.add('is-invalid');
        }

        if (msg) {
            msg.textContent = message;
        }
    }

    function validateVisitDate(value) {
        const maxDate = getCurrentDateLocal();

        if (!value) {
            return 'Customer visit date is required';
        }

        if (value > maxDate) {
            return 'Customer visit date cannot be in the future';
        }

        return null;
    }

    function validateActionTaken(value) {
        if (!value || !value.trim()) {
            return 'Complaint action taken is required';
        }

        return null;
    }

    function validateServiceReport() {
        const fileInput = form.querySelector('input[name="service_report[]"]');

        if (!fileInput || !fileInput.files.length) {
            return 'At least one service report file is required';
        }

        for (let i = 0; i < fileInput.files.length; i++) {
            const file = fileInput.files[i];
            const extension = file.name.split('.').pop().toLowerCase();

            if (!allowedExtensions.includes(extension)) {
                return 'Invalid file type for "' + file.name + '". Allowed: PDF, JPG, PNG, DOC, DOCX';
            }

            if (file.size > maxFileSize) {
                return 'File "' + file.name + '" must be 2 MB or smaller';
            }
        }

        return null;
    }

    function validateServiceLog() {
        const section = document.getElementById('serviceUpdateServiceLogSection');
        if (!section) {
            return null;
        }

        if (typeof validateServiceUpdateServiceLogRequired !== 'function') {
            return 'Service log validation is unavailable. Please refresh the page.';
        }

        return validateServiceUpdateServiceLogRequired();
    }

    function validateServiceUpdateForm() {
        const errors = {};
        const visitDateError = validateVisitDate(
            (form.querySelector('[name="customer_visit_date"]') || {}).value || ''
        );
        const actionTakenError = validateActionTaken(
            (form.querySelector('[name="complaint_action_taken"]') || {}).value || ''
        );
        const fileError = validateServiceReport();
        const serviceLogError = validateServiceLog();

        if (visitDateError) {
            errors.customer_visit_date = [visitDateError];
        }

        if (actionTakenError) {
            errors.complaint_action_taken = [actionTakenError];
        }

        if (fileError) {
            errors.service_report = [fileError];
        }

        if (serviceLogError) {
            errors.service_log = [serviceLogError];
        }

        return Object.keys(errors).length ? errors : null;
    }

    function showErrors(errors) {
        clearValidationState();

        if (!errors) {
            return;
        }

        Object.keys(errors).forEach(function (field) {
            if (errors[field] && errors[field].length) {
                setFieldError(field, errors[field][0]);
            }
        });
    }

    function showFieldValidation(fieldName, message) {
        const input = fieldName === 'service_report'
            ? form.querySelector('input[name="service_report[]"]')
            : form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');

        if (input) {
            input.classList.toggle('is-invalid', !!message);
        }

        if (msg) {
            msg.textContent = message || '';
        }
    }

    const visitDateInput = form.querySelector('[name="customer_visit_date"]');
    if (visitDateInput) {
        setCurrentDateInput(visitDateInput);

        visitDateInput.addEventListener('change', function () {
            const maxDate = getCurrentDateLocal();
            this.max = maxDate;
            if (this.value && this.value > maxDate) {
                this.value = maxDate;
            }
            showFieldValidation('customer_visit_date', validateVisitDate(this.value));
        });
    }

    const actionTakenInput = form.querySelector('[name="complaint_action_taken"]');
    if (actionTakenInput) {
        actionTakenInput.addEventListener('input', function () {
            showFieldValidation('complaint_action_taken', validateActionTaken(this.value));
        });
    }

    const serviceReportInput = form.querySelector('input[name="service_report[]"]');
    if (serviceReportInput) {
        serviceReportInput.addEventListener('change', function () {
            showFieldValidation('service_report', validateServiceReport());
        });
    }

    form.addEventListener('submit', function (e) {
        if (serviceUpdateSubmitting) {
            e.preventDefault();
            return;
        }

        const errors = validateServiceUpdateForm();
        showErrors(errors);

        if (errors) {
            e.preventDefault();

            if (errors.service_log && errors.service_log.length) {
                const section = document.getElementById('serviceUpdateServiceLogSection');
                if (section) {
                    section.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }

            return;
        }

        serviceUpdateSubmitting = true;

        const submitButton = form.querySelector('[name="save_update"]');
        if (submitButton) {
            submitButton.classList.add('disabled_btn');
        }
    });

    form.addEventListener('reset', function () {
        clearValidationState();
        serviceUpdateSubmitting = false;
    });
}
 
function resetServiceUpdateForm(complaintId) {
    const form = document.getElementById('serviceUpdateForm');

    if (!form) {
        return;
    }

    serviceUpdateSubmitting = false;

    form.reset();
    document.getElementById('serviceComplaintId').value = complaintId;
    setCurrentDateInput(form.querySelector('[name="customer_visit_date"]'));

    if (typeof resetServiceUpdateServiceLogState === 'function') {
        resetServiceUpdateServiceLogState();
    }
    if (typeof clearServiceUpdateServiceLogValidation === 'function') {
        clearServiceUpdateServiceLogValidation();
    }

    const submitButton = form.querySelector('[name="save_update"]');
    if (submitButton) {
        submitButton.classList.remove('disabled_btn');
    }

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}


$(document).ready(function() {
    initAssignedComplaintDatatable();
<?php if ($canServiceUpdateAssignedComplaint) { ?>
    initServiceUpdateValidation();
    initComplaintServiceUpdateSave();
<?php } ?>

    setTimeout(function() {
        $('.alert-success, .alert-danger').fadeOut();
    }, 3000);
});

<?php if ($canServiceUpdateAssignedComplaint) { ?>
$(document).on('click', '.service-update-btn', function() {
    resetServiceUpdateForm($(this).data('id'));
});
<?php } ?>

function getCurrentDateLocal() {
    const now = new Date();
    const pad = function (n) {
        return String(n).padStart(2, '0');
    };
    return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
}
function getCurrentDateTimeLocal() {
    const now = new Date();
    const pad = function (n) {
        return String(n).padStart(2, '0');
    };
    return getCurrentDateLocal() + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
}
function setCurrentDateInput(input) {
    if (input) {
        const today = getCurrentDateLocal();
        input.max = today;
        input.value = today;
    }
}
function setCurrentDateTimeInput(input) {
    if (input) {
        input.value = getCurrentDateTimeLocal();
    }
}
 


</script>

<?php if ($canServiceUpdateAssignedComplaint) { ?>
<script>
$(document).ready(function () {
    initComplaintServiceUpdateServiceLog();
<?php if ($canShowComplaintServiceLogModal) { ?>
    initInstalledBaseServiceLogModal();
    initComplaintServiceLogDraftSave();
<?php } ?>
});
</script>
<?php } ?>

</body>

</html>