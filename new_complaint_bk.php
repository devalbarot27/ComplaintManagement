<?php
session_start();
 
include('pdo_obconn.php');
include('includes/complaint_activity_helpers.php');
 
$success_message = '';
$error_message = '';

if(isset($_POST['submit_complaint']))
{
    $fab_number = trim($_POST['fab_number']);
    $customer_name = trim($_POST['customer_name']);
    $customer_address = trim($_POST['customer_address']);
    $complaint_description = trim($_POST['complaint_description']);
    $assign_complaint = trim($_POST['assign_complaint'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($assign_complaint === '') {
        $error_message = 'Assign To is required.';
    } elseif (strlen($remarks) > 500) {
        $error_message = 'Remarks cannot exceed 500 characters.';
    } else {
        try {
            $obconn->beginTransaction();

            $assigned_by = 1;
            $assigned_to = 1;
            $assign_complaint_datetime = date('Y-m-d H:i:s');

            $insert = $obconn->prepare("
                INSERT INTO complaints
                (
                    fab_number,
                    customer_name,
                    customer_address,
                    complaint_description,
                    status,
                    added_by
                )
                VALUES
                (
                    :fab_number,
                    :customer_name,
                    :customer_address,
                    :complaint_description,
                    :status,
                    :added_by
                )
            ");

            $insert->bindValue(':fab_number', $fab_number);
            $insert->bindValue(':customer_name', $customer_name);
            $insert->bindValue(':customer_address', $customer_address);
            $insert->bindValue(':complaint_description', $complaint_description);
            $insert->bindValue(':status', 2);
            $insert->bindValue(':added_by', $assigned_by, PDO::PARAM_INT);
            $insert->execute();

            $complaintId = (int) $obconn->lastInsertId();

            complaint_log_activity(
                $obconn,
                $complaintId,
                'Created',
                'Complaint registered for Fab Number ' . $fab_number . ' - ' . $customer_name,
                $assigned_by
            );

            $assignmentInsert = $obconn->prepare("
                INSERT INTO complaint_assignments
                (
                    complaint_id,
                    assign_complaint,
                    assigned_to,
                    assign_complaint_datetime,
                    remarks,
                    assigned_by
                )
                VALUES
                (
                    :complaint_id,
                    :assign_complaint,
                    :assigned_to,
                    :assign_complaint_datetime,
                    :remarks,
                    :assigned_by
                )
            ");

            $assignmentInsert->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
            $assignmentInsert->bindValue(':assign_complaint', $assign_complaint);
            $assignmentInsert->bindValue(':assigned_to', $assigned_to, PDO::PARAM_INT);
            $assignmentInsert->bindValue(':assign_complaint_datetime', $assign_complaint_datetime);
            $assignmentInsert->bindValue(':remarks', $remarks);
            $assignmentInsert->bindValue(':assigned_by', $assigned_by, PDO::PARAM_INT);
            $assignmentInsert->execute();

            $activityDescription = 'Complaint assigned to ' . $assign_complaint
                . ' on ' . date('d M Y, h:i A', strtotime($assign_complaint_datetime))
                . '. Status changed to In Progress.';

            if ($remarks !== '') {
                $activityDescription .= ' Remarks: ' . $remarks;
            }

            complaint_log_activity(
                $obconn,
                $complaintId,
                'Assignment',
                $activityDescription,
                $assigned_by
            );

            $obconn->commit();

            $success_message = 'Complaint submitted successfully.';
        } catch (PDOException $e) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }

            $error_message = 'Failed to submit complaint.';
        }
    }
}
 
 function complaint_status_counts(PDO $conn, bool $assignedOnly = false): array
{
    $counts = [
        'open' => 0,
        'in_progress' => 0,
        'resolved' => 0,
    ];
 
    if ($assignedOnly) {
        $sql = "
            SELECT c.status, COUNT(DISTINCT c.id) AS total
            FROM complaints c
            INNER JOIN complaint_assignments ca ON ca.complaint_id = c.id
            WHERE c.deleted_at IS NULL
            GROUP BY c.status
        ";
    } else {
        $sql = "
            SELECT status, COUNT(*) AS total
            FROM complaints
            WHERE deleted_at IS NULL
            GROUP BY status
        ";
    }
 
    $stmt = $conn->query($sql);
 
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        switch ((int) $row['status']) {
            case 1:
                $counts['open'] = (int) $row['total'];
                break;
            case 2:
                $counts['in_progress'] = (int) $row['total'];
                break;
            case 3:
                $counts['resolved'] = (int) $row['total'];
                break;
        }
    }
 
    return $counts;
}
?>
 
<!DOCTYPE html>
<html lang="en">
 
<head>
 
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <title>Dealer - Complaint</title>
 
    <?php include('header_css.php'); ?>
 
    <link href="css/orderbook_style.css" rel="stylesheet" /> 
    <link href="css/complaint_status_cards.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />


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
<?php if(!empty($error_message)){ ?>
<div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
<?php echo htmlspecialchars($error_message); ?>
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
 
            <?php if(isset($_SESSION['error_message'])) { ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
<?php echo htmlspecialchars($_SESSION['error_message']); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
 
            <?php
                unset($_SESSION['error_message']);
                }
                ?>
 
            <div class="page-header">
<div>
<div class="page-subtitle">
                        Log and track complaints related to orders and deliveries.
</div>
</div>
 
                <!-- RIGHT BUTTONS -->
<div class="header-btn-group">
<!-- NEW -->
<button class="new-order-btn btn-complaint-primary" id="openOrderForm">
<i class="bi bi-plus-lg"></i>
                        New Complaint
</button>
 
                    <!-- CLOSE -->
<button class="close-form-btn cancel-btn" id="closeOrderForm">
<i class="bi bi-x-lg"></i>
                        Cancel
</button>
</div>
</div>
 
            <!-- NEW COMPLAINT FORM -->
            <div class="complaint-form-card" id="orderFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon">
                            <i class="bi bi-clipboard-plus"></i>
                        </div>
                        <div>
                            <h2 class="complaint-form-header__title">New Complaint</h2>
                            <p class="complaint-form-header__subtitle">Register complaint details and assign for resolution.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="complaintForm" novalidate>
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Customer Information</h3>
                                    <p class="complaint-form-section__hint">Fabric and customer contact details</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-upc-scan"></i>
                                        Fab Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="fab_number" inputmode="numeric"
                                        pattern="[0-9]*" autocomplete="off" maxlength="10" placeholder="Enter fab number">
                                    <div class="text-danger validation-msg" data-field="fab_number"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-person"></i>
                                        Customer Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="customer_name" maxlength="200"
                                        placeholder="Enter customer name">
                                    <div class="text-danger validation-msg" data-field="customer_name"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-geo-alt"></i>
                                        Customer Address <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" name="customer_address" rows="2"
                                        placeholder="Enter full address"></textarea>
                                    <div class="text-danger validation-msg" data-field="customer_address"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Complaint Details</h3>
                                    <p class="complaint-form-section__hint">Describe the issue reported by the customer</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-chat-left-text"></i>
                                        Complaint Description <span class="text-danger">*</span>
                                    </label>
                                    <textarea class="form-control" name="complaint_description" rows="3"
                                        placeholder="Enter complaint description"></textarea>
                                    <div class="text-danger validation-msg" data-field="complaint_description"></div>
                                </div>
                            </div>
                        </section>

                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">3</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Assignment</h3>
                                    <p class="complaint-form-section__hint">Assign complaint to the responsible team member</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-person-check"></i>
                                        Assign To <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" name="assign_complaint">
                                        <option value="">Select option</option>
                                        <option value="Option 1">Option 1</option>
                                        <option value="Option 2">Option 2</option>
                                        <option value="Option 3">Option 3</option>
                                        <option value="Option 4">Option 4</option>
                                        <option value="Option 5">Option 5</option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="assign_complaint"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-card-text"></i>
                                        Remarks
                                    </label>
                                    <textarea class="form-control" name="remarks" rows="2"
                                        placeholder="Optional remarks for assignment"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="complaint-form-actions">
                        <button type="reset" class="cancel-btn">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" name="submit_complaint">
                            <i class="bi bi-send"></i>
                            Submit Complaint
                        </button>
                    </div>
                </form>
            </div>
 

<?php
if (!isset($statusCounts)) {
    $assignedOnly = $assignedOnly ?? false;
    $statusCounts = complaint_status_counts($obconn, $assignedOnly);
}
 
$openCount = (int) ($statusCounts['open'] ?? 0);
$inProgressCount = (int) ($statusCounts['in_progress'] ?? 0);
$resolvedCount = (int) ($statusCounts['resolved'] ?? 0);
?>
 
<div class="complaint-status-grid">
    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Open Complaints</div>
            <div class="complaint-status-value"><?php echo $openCount; ?></div>
            <?php if ($openCount > 0) { ?>
            <div class="complaint-status-hint">Needs attention</div>
            <?php } ?>
        </div>
        <div class="complaint-status-icon complaint-status-icon--open">
            <i class="bi bi-exclamation-lg"></i>
        </div>
    </div>
 
    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">In Progress</div>
            <div class="complaint-status-value"><?php echo $inProgressCount; ?></div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--progress">
            <i class="bi bi-clock"></i>
        </div>
    </div>
 
    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Resolved</div>
            <div class="complaint-status-value"><?php echo $resolvedCount; ?></div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--resolved">
            <i class="bi bi-check-lg"></i>
        </div>
    </div>
</div>
 
            <!-- TABLE CARD -->
<div class="booking-card">
 
                <div class="booking-header">
 
                    <div class="booking-title">
                        Complaint History
</div>
 
                </div>
 
                <div class="table-responsive">
 
                    <table class="table table-hover booking-table w-100" id="complaintTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Fab Number</th>
                                <th width="15%">Customer Name</th>
                                <th width="15%">Customer Address</th>
                                <th>Complaint Description</th>
                                <th width="10%">Status</th>
                                <th width="15%">Created At</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
 
                </div>
 
            </div>
 
        </div>
</div>
 
 
 
    <div class="modal fade" id="assignModal" tabindex="-1">
 
        <div class="modal-dialog">
 
            <div class="modal-content">
 
                <form method="post" action="assign_complaint.php" id="assignComplaintForm" novalidate>
<input type="hidden" name="action" value="assign">
<input type="hidden" name="complaint_id" id="assignComplaintId" value="1">
<div class="modal-header">
<h5 class="modal-title">Assign Complaint</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label class="form-label">Assign To <span class="text-danger">*</span></label>
<select class="form-select" name="assign_complaint">
<option value="">Select option</option>
<option value="Option 1">Option 1</option>
<option value="Option 2">Option 2</option>
<option value="Option 3">Option 3</option>
<option value="Option 4">Option 4</option>
<option value="Option 5">Option 5</option>
</select>
<div class="text-danger validation-msg" data-field="assign_complaint"></div>
</div>
<div class="mb-3">
<label class="form-label">Assign Date Time <span class="text-danger">*</span></label>
<input type="datetime-local" class="form-control" name="assign_complaint_datetime" id="assign_complaint_datetime" value="<?php echo date('Y-m-d\TH:i'); ?>">
<div class="text-danger validation-msg" data-field="assign_complaint_datetime"></div>
</div>
<div class="mb-3">
<label class="form-label">Remarks</label>
<textarea class="form-control" name="remarks" rows="2"></textarea>
<div class="text-danger validation-msg" data-field="remarks"></div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary" name="assign_user" id="assign_user">Assign</button>
</div>
</form>
</div>
</div>
</div>
 


<div class="modal fade" id="closureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="closure_complaint.php" id="closureForm" novalidate>
                    <input type="hidden" name="complaint_id" id="closureComplaintId">
                    <div class="modal-header">
                        <h5 class="modal-title">Complaint Closure</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label d-block">Call Closure? <span
                                    class="text-danger">*</span></label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="call_closure" id="closureYes"
                                    value="Yes">
                                <label class="form-check-label" for="closureYes">Yes</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="call_closure" id="closureNo"
                                    value="No">
                                <label class="form-check-label" for="closureNo">No</label>
                            </div>
                            <div class="text-danger validation-msg" data-field="call_closure"></div>
                        </div>
                        <div class="mb-3 d-none" id="closureRemarksWrap">
                            <label class="form-label">Closure Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="closure_remarks" rows="3"
                                placeholder="Enter remarks"></textarea>
                            <div class="text-danger validation-msg" data-field="closure_remarks"></div>
                        </div>
                        <div class="mb-3 d-none" id="reassignmentDetailsWrap">
                            <label class="form-label">Reassignment Details <span class="text-danger">*</span></label>
                            <textarea class="form-control" name="reassignment_details" rows="3"
                                placeholder="Enter reason for reassignment"></textarea>
                            <div class="text-danger validation-msg" data-field="reassignment_details"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="save_closure" id="save_closure">Save Closure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
 
 
</body>
 
 
 
<script>
function initComplaintFormValidation() {
    const form = document.getElementById('complaintForm');
 
    if (!form || typeof validate === 'undefined') {
        return;
    }
 
    const constraints = {
        fab_number: {
            presence: {
                allowEmpty: false,
                message: '^Fab Number is required'
            }
        },
        customer_name: {
            presence: {
                allowEmpty: false,
                message: '^Customer Name is required'
            }
        },
        customer_address: {
            presence: {
                allowEmpty: false,
                message: '^Customer Address is required'
            }
        },
        complaint_description: {
            presence: {
                allowEmpty: false,
                message: '^Complaint Description is required'
            }
        },
        assign_complaint: {
            presence: {
                allowEmpty: false,
                message: '^Please select Assign To option'
            }
        },
        remarks: {
            length: {
                maximum: 500,
                message: '^Remarks cannot exceed 500 characters'
            }
        }
    };
 
    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
    }
 
    function showErrors(errors) {
        clearValidationState();
 
        if (!errors) {
            return;
        }
 
        Object.keys(errors).forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
 
            if (input) {
                input.classList.add('is-invalid');
            }
 
            if (msg && errors[field] && errors[field].length) {
                msg.textContent = errors[field][0];
            }
        });
    }
 
    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }

        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';

        input.addEventListener(eventName, function () {
            if (input.name === 'fab_number') {
                input.value = input.value.replace(/\D/g, '');
            }

            const fieldErrors = validate.single(input.value, constraints[input.name]);
            const msg = form.querySelector('.validation-msg[data-field="' + input.name + '"]');

            input.classList.toggle('is-invalid', !!fieldErrors);

            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }
        });
    });


/*
form.addEventListener('submit', function (e) {

        const errors = validate(form, constraints);
        showErrors(errors);
        if (errors) {
            e.preventDefault();
        }
});
*/

    let isSubmitting = false; 
    form.addEventListener('submit', function (e) {
	if (isSubmitting) {
        	e.preventDefault();
        	return;
    	}

    	const errors = validate(form, constraints);
    	showErrors(errors);
    	if (errors && Object.keys(errors).length > 0) {
        	e.preventDefault();
        	return;
    	}

    	isSubmitting = true;
    	const submitButton = form.querySelector('[name="submit_complaint"]');
    	if (submitButton) {
        	submitButton.classList.add('disabled_btn');
    	}
    });
 
    form.addEventListener('reset', function () {
        clearValidationState();
    });
}


function initComplaintEntryDatatable() {
    const $table = $('#complaintTable');
    if (!$table.length) {
        return null;
    }
 
    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/complaints_datatable.php',
            type: 'POST'
        },
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'customer_address' },
            { data: 'complaint_description' },
            { data: 'status', orderable: false },
            { data: 'created_at' },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No complaints found.',
            zeroRecords: 'No matching complaints found.'
        }
    });
}
 
function initAssignedComplaintDatatable() {
    const $table = $('#dscComplaintTable');
    if (!$table.length) {
        return null;
    }
 
    return $table.DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api/assigned_complaints_datatable.php',
            type: 'POST'
        },
        order: [[5, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        columns: [
            { data: 'id' },
            { data: 'fab_number' },
            { data: 'customer_name' },
            { data: 'complaint_description' },
            { data: 'assign_complaint' },
            { data: 'assign_complaint_datetime' },
            { data: 'status', orderable: false },
            { data: 'actions', orderable: false, searchable: false }
        ],
        language: {
            emptyTable: 'No assigned complaints found.',
            zeroRecords: 'No matching assigned complaints found.'
        }
    });
}

function initClosureValidation() {
    const form = document.getElementById('closureForm');
 
    if (!form || typeof validate === 'undefined') {
        return;
    }
 
    const remarksWrap = document.getElementById('closureRemarksWrap');
    const reassignmentWrap = document.getElementById('reassignmentDetailsWrap');
 
    function getCallClosure() {
        const checked = form.querySelector('input[name="call_closure"]:checked');
        return checked ? checked.value : '';
    }
 
    function toggleClosureFields() {
        const value = getCallClosure();
        const isYes = value === 'Yes';
        const isNo = value === 'No';
 
        if (remarksWrap) {
            remarksWrap.classList.toggle('d-none', !isYes);
        }
 
        if (reassignmentWrap) {
            reassignmentWrap.classList.toggle('d-none', !isNo);
        }
 
        const remarksField = form.querySelector('[name="closure_remarks"]');
        const reassignField = form.querySelector('[name="reassignment_details"]');
 
        if (remarksField) {
            remarksField.value = isYes ? remarksField.value : '';
        }
 
        if (reassignField && !isNo) {
            reassignField.value = '';
        }
    }
 
    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
    }
 
    function setFieldError(fieldName, message) {
        const input = form.querySelector('[name="' + fieldName + '"]');
        const msg = form.querySelector('.validation-msg[data-field="' + fieldName + '"]');
 
        if (input) {
            input.classList.add('is-invalid');
        }
 
        if (msg) {
            msg.textContent = message;
        }
    }
 
    function validateClosureForm() {
        const errors = {};
        const callClosure = getCallClosure();
 
        if (!callClosure) {
            errors.call_closure = ['Please select Call Closure Yes or No'];
        }
 
        if (callClosure === 'Yes') {
            const remarks = form.querySelector('[name="closure_remarks"]').value.trim();
            if (!remarks) {
                errors.closure_remarks = ['Closure remarks are required'];
            }
        }
 
        if (callClosure === 'No') {
            const details = form.querySelector('[name="reassignment_details"]').value.trim();
            if (!details) {
                errors.reassignment_details = ['Reassignment details are required'];
            }
        }
 
        return Object.keys(errors).length ? errors : null;
    }
 
    function showErrors(errors) {
        clearValidationState();
 
        if (!errors) {
            return;
        }
 
        Object.keys(errors).forEach(function (field) {
            setFieldError(field, errors[field][0]);
        });
    }
 
    form.querySelectorAll('input[name="call_closure"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            toggleClosureFields();
            clearValidationState();
        });
    });
 
/*
    form.addEventListener('submit', function (e) {
        const errors = validateClosureForm();
        showErrors(errors);
 
        if (errors) {
            e.preventDefault();
        }
    });
*/

    let isSubmitting = false; 
    form.addEventListener('submit', function (e) {
	if (isSubmitting) {
        	e.preventDefault();
        	return;
    	}

    	const errors = validateClosureForm();
    	showErrors(errors);
    	if (errors && Object.keys(errors).length > 0) {
        	e.preventDefault();
        	return;
    	}

    	isSubmitting = true;
    	const submitButton = form.querySelector('[name="save_closure"]');
    	if (submitButton) {
        	submitButton.classList.add('disabled_btn');
    	}
    });

 
    form.addEventListener('reset', function () {
        clearValidationState();
        toggleClosureFields();
    });
 
    toggleClosureFields();
}




function initAssignValidation() {
    const form = document.getElementById('assignComplaintForm');
 
    if (!form || typeof validate === 'undefined') {
        return;
    }
 
    const constraints = {
        assign_complaint: {
            presence: {
                allowEmpty: false,
                message: '^Please select Assign To option'
            }
        },
        assign_complaint_datetime: {
            presence: {
                allowEmpty: false,
                message: '^Please select Assign Date Time'
            }
        },
        remarks: {
            length: {
                maximum: 500,
                message: '^Remarks cannot exceed 500 characters'
            }
        }
    };
 
    function clearValidationState() {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
    }
 
    function showErrors(errors) {
        clearValidationState();
 
        if (!errors) {
            return;
        }
 
        Object.keys(errors).forEach(function (field) {
            const input = form.querySelector('[name="' + field + '"]');
            const msg = form.querySelector('.validation-msg[data-field="' + field + '"]');
 
            if (input) {
                input.classList.add('is-invalid');
            }
 
            if (msg && errors[field] && errors[field].length) {
                msg.textContent = errors[field][0];
            }
        });
    }
 
    form.querySelectorAll('input, textarea, select').forEach(function (input) {
        if (!constraints[input.name]) {
            return;
        }
 
        const eventName = input.tagName === 'SELECT' ? 'change' : 'input';
 
        input.addEventListener(eventName, function () {
            const fieldErrors = validate.single(input.value, constraints[input.name]);
            const msg = form.querySelector('.validation-msg[data-field="' + input.name + '"]');
 
            input.classList.toggle('is-invalid', !!fieldErrors);
 
            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }
        });
    });
 
/*
    form.addEventListener('submit', function (e) {
        const errors = validate(form, constraints);
        showErrors(errors);
 
        if (errors) {
            e.preventDefault();
        }
    });
*/

let isSubmitting = false; 
    form.addEventListener('submit', function (e) {
	if (isSubmitting) {
        	e.preventDefault();
        	return;
    	}

    	const errors = validate(form, constraints);
    	showErrors(errors);
    	if (errors && Object.keys(errors).length > 0) {
        	e.preventDefault();
        	return;
    	}

    	isSubmitting = true;
    	const submitButton = form.querySelector('[name="assign_user"]');
    	if (submitButton) {
        	submitButton.classList.add('disabled_btn');
    	}
    });

 
    form.addEventListener('reset', function () {
        clearValidationState();
    });
}
 
function resetAssignForm(complaintId) {
    const form = document.getElementById('assignComplaintForm');
 
    if (!form) {
        return;
    }
 
    form.reset();
    document.getElementById('assignComplaintId').value = complaintId;
    setCurrentDateTimeInput(form.querySelector('[name="assign_complaint_datetime"]'));
 
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
}
 
 


 
function resetClosureForm(complaintId) {
    const form = document.getElementById('closureForm');
 
    if (!form) {
        return;
    }
 
    form.reset();
    document.getElementById('closureComplaintId').value = complaintId;
 
    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });
 
    const event = new Event('reset');
    form.dispatchEvent(event);
}
 

$(document).ready(function() {

    initComplaintEntryDatatable();
    initComplaintFormValidation();
    initAssignValidation();
    initClosureValidation();

    $('input[name="fab_number"]').on('keypress keyup blur', function(event) {
        // Replace any non-numeric character with an empty string
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
        
        // Block the actual keypress if it's not a number
        if ((event.which < 48 || event.which > 57) && event.which !== 0 && event.which !== 8) {
            event.preventDefault();
        }
    });
 
    setTimeout(function() {
        $('.alert-success').fadeOut();
    }, 3000);
 
});
 
$(document).on('click', '.assign-btn', function() {
 resetAssignForm($(this).data('id'));
});

$(document).on('click', '.closure-btn', function() {
    resetClosureForm($(this).data('id'));
});


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
        input.value = getCurrentDateLocal();
    }
}
function setCurrentDateTimeInput(input) {
    if (input) {
        input.value = getCurrentDateTimeLocal();
    }
}
</script>
 
</html>
 
 
<style>
/* PAGE */
 
.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 22px;
}
 
.page-subtitle {
    font-size: 15px;
    color: #64748b;
    font-weight: 500;
}
 
/* BUTTON */
 
.new-order-btn {
    height: 40px;
    padding: 0 18px;
    border: none;
    border-radius: 10px;
    background: #1565d8;
    color: white;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}
 
/* CARD */
 
.booking-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
}
 
/* HEADER */
 
.booking-header {
    padding: 22px 24px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
 
.booking-title {
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}
 
.booking-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
 
/* SEARCH */
 
.search-box {
    width: 200px;
    height: 40px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    display: flex;
    align-items: center;
    padding: 0 14px;
    gap: 10px;
}
 
.search-box i {
    color: #64748b;
    font-size: 14px;
}
 
.search-box input {
    border: none;
    outline: none;
    width: 100%;
    font-size: 14px;
}
 
/* SELECT */
 
.filter-select {
    height: 40px;
    min-width: 145px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    padding: 0 14px;
    background: white;
    font-size: 14px;
    color: #334155;
}
 
.active-filter {
    border: 2px solid #d93d0f;
}
 
/* TABLE */
 
.booking-table {
    width: 100%;
    border-collapse: collapse;
}
 
.booking-table thead th {
    font-size: 13px;
    font-weight: 500;
    color: #64748b;
    padding: 14px 24px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
}
 
.booking-table tbody td {
    padding: 18px 24px;
    font-size: 14px;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
}
 
.booking-table tbody tr:last-child td {
    border-bottom: none;
}
 
/* LINK */
 
.order-link {
    color: #0f62fe;
    text-decoration: none;
    font-weight: 600;
}
 
/* BADGE */
 
.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}
 
.created {
    background: #dbeafe;
    color: #d93d0f;
}
 
.dispatched {
    background: #dcfce7;
    color: #16a34a;
}
 
.pending {
    background: #fef3c7;
    color: #d97706;
}
 
/* MOBILE */
 
@media(max-width:992px) {
 
    .booking-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 16px;
    }
 
    .booking-actions {
        width: 100%;
        flex-wrap: wrap;
    }
 
}
 
@media(max-width:768px) {
 
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 14px;
    }
 
    .new-order-btn {
        width: 100%;
        justify-content: center;
    }
 
    .search-box {
        width: 100%;
    }
 
    .filter-select {
        flex: 1;
    }
 
    .booking-table {
        min-width: 800px;
    }
 
}
 
/* GRID */
 
.complaint-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
 
/* FULL WIDTH */
 
.full-width {
    grid-column: 1 / -1;
}
 
/* LABEL */
 
.form-group label {
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 10px;
    display: block;
}
 
/* REQUIRED */
 
.required {
    color: #ef4444;
}
 
/* INPUT */
 
.custom-input {
    width: 100%;
    height: 42px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    padding: 0 14px;
    font-size: 14px;
    color: #0f172a;
    outline: none;
    background: #fff;
}
 
.custom-input:focus {
    border-color: #1565d8;
}
 
/* TEXTAREA */
 
.custom-textarea {
    width: 100%;
    min-height: 140px;
    border: 1px solid #dbe2ea;
    border-radius: 10px;
    padding: 14px;
    font-size: 14px;
    color: #0f172a;
    outline: none;
    resize: vertical;
    background: #fff;
}
 
.custom-textarea:focus {
    border-color: #1565d8;
}
 
/* BUTTON WRAPPER */
 
.complaint-btn-wrapper {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 18px;
}
 
/* CANCEL */
 
.cancel-btn {
    height: 40px;
    padding: 0 20px;
    border: 1px solid #dbe2ea;
    background: #fff;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a;
}
 
/* SUBMIT */
 
.submit-btn {
    height: 40px;
    padding: 0 20px;
    border: none;
    background: #1565d8;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #fff;
}
 
/* MOBILE */
 
@media(max-width:768px) {
 
    .complaint-grid {
        grid-template-columns: 1fr;
    }
 
    .complaint-btn-wrapper {
        flex-direction: column;
    }
 
    .cancel-btn,
    .submit-btn {
        width: 100%;
    }
 
}




/* Temp */
.complaint-action-btn--closure {
    background: #7c3aed;
}
 
.complaint-action-btn--closure:hover {
    background: #6d28d9;
    color: #fff;
}
.complaint-status-grid {

    display: grid;

    grid-template-columns: repeat(3, 1fr);

    gap: 16px;

    margin-bottom: 20px;

}

 

.complaint-status-card {

    display: flex;

    align-items: center;

    justify-content: space-between;

    background: #fff;

    border: 1px solid #e2e8f0;

    border-radius: 14px;

    padding: 20px 22px;

    min-height: 110px;

}

 

.complaint-status-body {

    flex: 1;

    min-width: 0;

}

 

.complaint-status-label {

    font-size: 14px;

    font-weight: 500;

    color: #64748b;

    margin-bottom: 8px;

}

 

.complaint-status-value {

    font-size: 32px;

    font-weight: 700;

    color: #0f172a;

    line-height: 1.1;

    letter-spacing: -0.5px;

}

 

.complaint-status-hint {

    margin-top: 6px;

    font-size: 13px;

    font-weight: 500;

    color: #ef4444;

}

 

.complaint-status-icon {

    flex-shrink: 0;

    width: 48px;

    height: 48px;

    border-radius: 50%;

    background: #eff6ff;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-left: 16px;

}

 

.complaint-status-icon i {

    font-size: 22px;

    color: #d93d0f;

    font-weight: 700;

}

 

@media (max-width: 992px) {

    .complaint-status-grid {

        grid-template-columns: 1fr;

    }

}

 

@media (max-width: 576px) {

    .complaint-status-card {

        padding: 16px 18px;

    }

 

    .complaint-status-value {

        font-size: 28px;

    }

 

    .complaint-status-icon {

        width: 42px;

        height: 42px;

    }

 

    .complaint-status-icon i {

        font-size: 18px;

    }

}





/* Primary action buttons (replaces blue) */
.btn-primary{
 background-color: #F44611 !important;
    border-color: #F44611 !important;
    color: #fff !important;

}

.btn-primary:hover,
.btn-primary:focus,
.content .btn-primary:hover,
.content .btn-primary:focus,
.modal .btn-primary:hover,
.modal .btn-primary:focus {
    background-color: #d93d0f !important;
    border-color: #d93d0f !important;
    color: #fff !important;
}

.btn-secondary,.cancel-btn{
    height: 40px;
    padding: 0 20px;
    border: 1px solid #dbe2ea !important;
    background: #fff !important;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a !important;
}
.btn-secondary:hover,.btn-secondary:focus,.btn-secondary:active
{
    height: 40px;
    padding: 0 20px;
    border: 1px solid #dbe2ea !important;
    background: #dbe2ea !important;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #0f172a !important;
}


.btn-complaint-primary,
.content .btn-complaint-primary,
.modal .btn-complaint-primary {
    background-color: #F44611 !important;
    border-color: #F44611 !important;
    color: #fff !important;
}
 
.btn-complaint-primary:hover,
.btn-complaint-primary:focus,
.content .btn-complaint-primary:hover,
.content .btn-complaint-primary:focus,
.modal .btn-complaint-primary:hover,
.modal .btn-complaint-primary:focus {
    background-color: #d93d0f !important;
    border-color: #d93d0f !important;
    color: #fff !important;
}
 
.btn-complaint-primary:active,
.content .btn-complaint-primary:active,
.modal .btn-complaint-primary:active {
    background-color: #c4350e !important;
    border-color: #c4350e !important;
    color: #fff !important;
}
 
/* Grid / table action icon buttons */
.complaint-action-cell {
    display: inline-flex;
    align-items: center;
  line-height: 1;
    text-decoration: none;
    cursor: pointer;
    flex-shrink: 0;
    box-shadow: none;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
 
.btn-complaint-action i {
    font-size: 14px;
    pointer-events: none;
    color: inherit;
}
 
.btn-complaint-action:hover,
.btn-complaint-action:focus {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
    text-decoration: none;
}
 
.btn-complaint-action:hover i,
.btn-complaint-action:focus i {
    color: #fff !important;
}
 
.btn-complaint-action:active {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}



.complaint-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    padding: 0;
    border: 1px solid #000 !important;
    border-radius: 8px;
    background: transparent !important;
    color: #000 !important;
    font-size: 14px;
    line-height: 1;
    text-decoration: none;
    cursor: pointer;
    flex-shrink: 0;
    box-shadow: none;
    transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
 
.complaint-action-btn:hover {
    background: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}
 
.disabled_btn {
    pointer-events: none;
    opacity: 0.6;
    cursor: not-allowed;
}
 
.dataTables_wrapper .dataTables_paginate .paginate_button{
     padding: 0px !important;
}
 
.form-select-sm {
       border-color: #dbe2ea !important;
}
.page-item.active .page-link {
    background-color: #000 !important;
    border-color: #000 !important;
    color: #fff !important;
}
 
.page-link {
    color: #000 !important;
    background-color: #fff !important;
    border: 1px solid #000 !important;
}


 
.page-link:hover {
    background-color: #000 !important;
    color: #fff !important;
    border-color: #000 !important;
}
 
.page-item.disabled .page-link {
    color: #6c757d !important;
    background-color: #fff !important;
    border-color: #dee2e6 !important;
}
 

.page-link:focus,
.page-link:active,
.page-link:focus-visible {
    box-shadow: none !important;
    outline: none !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:focus,
.dataTables_wrapper .dataTables_paginate .paginate_button:active {
    outline: none !important;
    box-shadow: none !important;
}
 

</style>
</body>
 
</html>
 
<script>
// OPEN FORM
 
const openOrderForm = document.getElementById('openOrderForm');
 
const closeOrderForm = document.getElementById('closeOrderForm');
 
const orderFormCard = document.getElementById('orderFormCard');
 
// OPEN
 
openOrderForm.addEventListener('click', function() {
 
    orderFormCard.classList.add('show');
 
    openOrderForm.style.display = 'none';
 
    closeOrderForm.classList.add('show');
 
});
 
// CLOSE
 
closeOrderForm.addEventListener('click', function() {

    orderFormCard.classList.remove('show');

    closeOrderForm.classList.remove('show');

    openOrderForm.style.display = 'flex';

    const complaintForm = document.getElementById('complaintForm');

    if (complaintForm) {
        complaintForm.reset();
        complaintForm.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        complaintForm.querySelectorAll('.validation-msg').forEach(function (el) {
            el.textContent = '';
        });
        const submitButton = complaintForm.querySelector('[name="submit_complaint"]');
        if (submitButton) {
            submitButton.classList.remove('disabled_btn');
        }
    }

});
</script>