<?php
session_start();
 
include('pdo_obconn.php');
require_once 'includes/rbac_page_guard.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/complaint_assignment_mail_helpers.php';
include('includes/complaint_address_helpers.php');
include('includes/complaint_category_helpers.php');
include('includes/complaint_status.php');
include('includes/ln_invoice_helpers.php');
 
$success_message = '';
$error_message = '';
$userName = current_username();
$complaintAssignees = complaint_fetch_elgi_engineer_assignees($obconn);
$complaintAssigneeOptionsHtml = complaint_render_assignee_options($complaintAssignees);
$complaintCategoryOptions = complaint_category_get_active_options($obconn);
$complaintCategoryOptionsHtml = complaint_category_render_options($complaintCategoryOptions);

if(isset($_POST['submit_complaint']))
{
    $fab_number = trim($_POST['fab_number']);
    $customer_name = trim($_POST['customer_name']);
    $address = complaint_address_from_post($_POST);
    $complaint_description = trim($_POST['complaint_description']);
    $complaint_category_id = (int) ($_POST['complaint_category_id'] ?? 0);
    $assign_complaint = trim($_POST['assign_complaint'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    $addressError = complaint_validate_address_fields($address);
    $complaintCategory = complaint_category_resolve_for_complaint($obconn, $complaint_category_id);

    if ($addressError !== null) {
        $error_message = $addressError;
    } elseif ($fab_number === '') {
        $error_message = 'Fab Number is required.';
    } elseif (!ln_invoice_fabno_exists($dpconn, $fab_number)) {
        $error_message = 'Selected Fab Number was not found in invoice details.';
    } elseif ($complaintCategory === null) {
        $error_message = 'Complaint Category is required.';
    } elseif (strlen($remarks) > 500) {
        $error_message = 'Remarks cannot exceed 500 characters.';
    } elseif ($assign_complaint !== '' && ($assigneeError = complaint_validate_elgi_engineer_assignee($obconn, $assign_complaint)) !== null) {
        $error_message = $assigneeError;
    } else {
        try {
            $obconn->beginTransaction();

            $assigned_by = 1;
            $hasAssignee = $assign_complaint !== '';
            $complaintStatus = $hasAssignee ? COMPLAINT_STATUS_IN_PROGRESS : COMPLAINT_STATUS_OPEN;
            $assign_complaint_datetime = date('Y-m-d H:i:s');

            $insert = $obconn->prepare("
                INSERT INTO complaints
                (
                    fab_number,
                    customer_name,
                    street_1,
                    street_2,
                    pincode,
                    city,
                    district,
                    state,
                    complaint_description,
                    complaint_category_id,
                    complaint_category_name,
                    status,
                    added_by,
                    username
                )
                VALUES
                (
                    :fab_number,
                    :customer_name,
                    :street_1,
                    :street_2,
                    :pincode,
                    :city,
                    :district,
                    :state,
                    :complaint_description,
                    :complaint_category_id,
                    :complaint_category_name,
                    :status,
                    :added_by,
                    :username
                )
            ");

            $insert->bindValue(':fab_number', $fab_number);
            $insert->bindValue(':customer_name', $customer_name);
            $insert->bindValue(':street_1', $address['street_1']);
            $insert->bindValue(':street_2', $address['street_2'] !== '' ? $address['street_2'] : null);
            $insert->bindValue(':pincode', $address['pincode']);
            $insert->bindValue(':city', $address['city']);
            $insert->bindValue(':district', $address['district']);
            $insert->bindValue(':state', $address['state']);
            $insert->bindValue(':complaint_description', $complaint_description);
            $insert->bindValue(':complaint_category_id', (int) $complaintCategory['id'], PDO::PARAM_INT);
            $insert->bindValue(':complaint_category_name', $complaintCategory['name']);
            $insert->bindValue(':status', $complaintStatus, PDO::PARAM_INT);
            $insert->bindValue(':added_by', $assigned_by, PDO::PARAM_INT);
            $insert->bindValue(':username', $userName);
            $insert->execute();

            $complaintId = (int) $obconn->lastInsertId();

            complaint_log_activity(
                $obconn,
                $complaintId,
                'Created',
                'Complaint registered for Fab Number ' . $fab_number . ' - ' . $customer_name,
                $assigned_by
            );

            if ($hasAssignee) {
                $assigned_to = 1;

                $assignmentInsert = $obconn->prepare("
                    INSERT INTO complaint_assignments
                    (
                        complaint_id,
                        assign_complaint,
                        assigned_to,
                        assign_complaint_datetime,
                        remarks,
                        assigned_by,
                        username
                    )
                    VALUES
                    (
                        :complaint_id,
                        :assign_complaint,
                        :assigned_to,
                        :assign_complaint_datetime,
                        :remarks,
                        :assigned_by,
                        :username
                    )
                ");

                $assignmentInsert->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
                $assignmentInsert->bindValue(':assign_complaint', $assign_complaint);
                $assignmentInsert->bindValue(':assigned_to', $assigned_to, PDO::PARAM_INT);
                $assignmentInsert->bindValue(':assign_complaint_datetime', $assign_complaint_datetime);
                $assignmentInsert->bindValue(':remarks', $remarks !== '' ? $remarks : null);
                $assignmentInsert->bindValue(':assigned_by', $assigned_by, PDO::PARAM_INT);
                $assignmentInsert->bindValue(':username', $userName);
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
            }

            $obconn->commit();

            if ($hasAssignee) {
                complaint_assignment_notify_email(
                    $obconn,
                    $complaintId,
                    $assign_complaint,
                    $assign_complaint_datetime,
                    $remarks
                );
            }

            $success_message = $hasAssignee
                ? 'Complaint submitted and assigned successfully.'
                : 'Complaint submitted successfully.';
        } catch (PDOException $e) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }

            $error_message = 'Failed to submit complaint.';
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="en">
 
<head>
 
    <meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <title>Dealer - Complaint</title>
 
    <?php include('header_css.php'); ?>

    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" /> 
    <link href="css/complaint_status_cards.css" rel="stylesheet" />
    <link href="css/complaint_botton.css" rel="stylesheet" />
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="js/pincode_select2.js"></script>
<script src="js/fabno_select2.js"></script>
<script src="js/assign_to_select2.js"></script>
<script src="js/static_select2.js"></script>

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
                                    <label class="form-label" for="complaintFabNumberSelect">
                                        <i class="bi bi-upc-scan"></i>
                                        Fab Number <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="fab_number" id="complaintFabNumberSelect"
                                        data-placeholder="Search fab number">
                                        <option value=""></option>
                                    </select>
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
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-signpost"></i>
                                        Street 1 <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control" name="street_1" maxlength="255"
                                        placeholder="House / building / street">
                                    <div class="text-danger validation-msg" data-field="street_1"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-signpost-2"></i>
                                        Street 2
                                    </label>
                                    <input type="text" class="form-control" name="street_2" maxlength="255"
                                        placeholder="Area / landmark (optional)">
                                    <div class="text-danger validation-msg" data-field="street_2"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label" for="pincodeSelect">
                                        <i class="bi bi-mailbox"></i>
                                        Pincode <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="pincode" id="pincodeSelect"
                                        data-placeholder="Search pincode">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="pincode"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-building"></i>
                                        City <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="city"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="city"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-geo"></i>
                                        District <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="district"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="district"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-map"></i>
                                        State <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" class="form-control address-auto-field" name="state"
                                        maxlength="100" placeholder="Auto-filled from pincode" readonly>
                                    <div class="text-danger validation-msg" data-field="state"></div>
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
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="complaintCategorySelect">
                                        <i class="bi bi-tags"></i>
                                        Complaint Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="complaint_category_id" id="complaintCategorySelect"
                                        data-placeholder="Select complaint category">
                                        <?php echo $complaintCategoryOptionsHtml; ?>
                                    </select>
                                    <input type="hidden" name="complaint_category_name" id="complaintCategoryName" value="">
                                    <div class="text-danger validation-msg" data-field="complaint_category_id"></div>
                                </div>
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
                                    <p class="complaint-form-section__hint">Optional: assign complaint to a team member during registration</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="complaintAssignToSelect">
                                        <i class="bi bi-person-check"></i>
                                        Assign To
                                    </label>
                                    <select class="form-control" name="assign_complaint" id="complaintAssignToSelect"
                                        data-placeholder="Search assignee">
                                        <?php echo $complaintAssigneeOptionsHtml; ?>
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
 

<?php include 'includes/complaint_status_cards.php'; ?>

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
                                <th width="12%">Customer Name</th>
                                <th width="12%">Complaint Category</th>
                                <th>Customer Address</th>
                                <th width="15%">Status</th>
                                <th width="12%">Created At</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
 
                </div>
 
            </div>
 
        </div>
</div>
 
 
 
<div class="modal fade" id="assignModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content complaint-form-modal">
                <form method="post" action="assign_complaint.php" id="assignComplaintForm" novalidate>
                    <input type="hidden" name="complaint_id" id="assignComplaintId">
                    <div class="complaint-form-header">
                        <div class="complaint-form-header__main">
                            <div class="complaint-form-header__icon">
                                <i class="bi bi-person-plus"></i>
                            </div>
                            <div>
                                <h2 class="complaint-form-header__title">Assign Complaint</h2>
                                <p class="complaint-form-header__subtitle">Assign an open complaint to a team member.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Assignment Details</h3>
                                    <p class="complaint-form-section__hint">Select assignee for this complaint</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="assignModalAssignToSelect">
                                        <i class="bi bi-person-check"></i>
                                        Assign To <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="assign_complaint" id="assignModalAssignToSelect"
                                        data-placeholder="Search assignee">
                                        <?php echo $complaintAssigneeOptionsHtml; ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="assign_complaint"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-card-text"></i>
                                        Remarks
                                    </label>
                                    <textarea class="form-control" name="remarks" rows="2" placeholder="Optional remarks for assignment"></textarea>
                                    <div class="text-danger validation-msg" data-field="remarks"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="submit-btn btn-complaint-primary" name="assign_user" id="assign_user">
                            <i class="bi bi-person-check"></i>
                            Assign
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<div class="modal fade" id="closureModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content complaint-form-modal">
                <form method="post" action="closure_complaint.php" id="closureForm" novalidate>
                    <input type="hidden" name="complaint_id" id="closureComplaintId">
                    <div class="complaint-form-header">
                        <div class="complaint-form-header__main">
                            <div class="complaint-form-header__icon">
                                <i class="bi bi-check2-square"></i>
                            </div>
                            <div>
                                <h2 class="complaint-form-header__title">Complaint Closure</h2>
                                <p class="complaint-form-header__subtitle">Mark call closure and resolve or reassign the complaint.</p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Closure Decision</h3>
                                    <p class="complaint-form-section__hint">Confirm whether the complaint call is closed</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-telephone"></i>
                                    Call Closure? <span class="text-danger">*</span>
                                </label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="call_closure" id="closureYes" value="Yes">
                                        <label class="form-check-label" for="closureYes">Yes</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="call_closure" id="closureNo" value="No">
                                        <label class="form-check-label" for="closureNo">No</label>
                                    </div>
                                </div>
                                <div class="text-danger validation-msg" data-field="call_closure"></div>
                            </div>
                        </section>
                        <section class="complaint-form-section d-none" id="closureRemarksWrap">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Closure Remarks</h3>
                                    <p class="complaint-form-section__hint">Add remarks before resolving the complaint</p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="bi bi-card-text"></i>
                                    Closure Remarks <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" name="closure_remarks" rows="3" placeholder="Enter closure remarks"></textarea>
                                <div class="text-danger validation-msg" data-field="closure_remarks"></div>
                            </div>
                        </section>
                        <section class="complaint-form-section d-none" id="reassignmentDetailsWrap">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">2</span>
                                <div>
                                    <h3 class="complaint-form-section__title">Reassignment</h3>
                                    <p class="complaint-form-section__hint">Reassign complaint when call closure is marked No</p>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="closureReassignToSelect">
                                        <i class="bi bi-person-check"></i>
                                        Reassign to <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="reassign_complaint" id="closureReassignToSelect"
                                        data-placeholder="Search assignee">
                                        <?php echo $complaintAssigneeOptionsHtml; ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="reassign_complaint"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">
                                        <i class="bi bi-card-text"></i>
                                        Remarks
                                    </label>
                                    <textarea class="form-control" name="reassign_remarks" rows="2" placeholder="Optional remarks for reassignment"></textarea>
                                    <div class="text-danger validation-msg" data-field="reassign_remarks"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="submit-btn btn-complaint-primary" name="save_closure" id="save_closure">
                            <i class="bi bi-check-lg"></i>
                            Save Closure
                        </button>
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
        street_1: {
            presence: {
                allowEmpty: false,
                message: '^Street 1 is required'
            }
        },
        street_2: {
            length: {
                maximum: 255,
                message: '^Street 2 cannot exceed 255 characters'
            }
        },
        pincode: {
            presence: {
                allowEmpty: false,
                message: '^Pincode is required'
            },
            format: {
                pattern: /^\d{6}$/,
                message: '^Pincode must be a 6-digit number'
            }
        },
        city: {
            presence: {
                allowEmpty: false,
                message: '^City is required'
            }
        },
        district: {
            presence: {
                allowEmpty: false,
                message: '^District is required'
            }
        },
        state: {
            presence: {
                allowEmpty: false,
                message: '^State is required'
            }
        },
        complaint_description: {
            presence: {
                allowEmpty: false,
                message: '^Complaint Description is required'
            }
        },
        complaint_category_id: {
            presence: {
                allowEmpty: false,
                message: '^Complaint Category is required'
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

            if (field === 'fab_number') {
                $('#complaintFabNumberSelect').addClass('is-invalid');
            }

            if (field === 'pincode') {
                $('#pincodeSelect').addClass('is-invalid');
            }

            if (field === 'assign_complaint') {
                $('#complaintAssignToSelect').addClass('is-invalid');
            }

            if (field === 'complaint_category_id') {
                $('#complaintCategorySelect').addClass('is-invalid');
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

    	syncComplaintCategoryName();
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
        resetPincodeSelect2(form);
        resetFabNumberSelect2ById('complaintFabNumberSelect');
        resetAssignToSelect2('complaintAssignToSelect');
        resetStaticSelect2('complaintCategorySelect');
        document.getElementById('complaintCategoryName').value = '';
    });
}

function syncComplaintCategoryName() {
    const $select = $('#complaintCategorySelect');
    const nameInput = document.getElementById('complaintCategoryName');

    if (!$select.length || !nameInput) {
        return;
    }

    const selected = $select.find(':selected');
    nameInput.value = selected.data('name') || '';
}

function initComplaintCategorySelect2() {
    initStaticSelect2('complaintForm', 'complaintCategorySelect', {
        validationField: 'complaint_category_id',
        allowClear: false,
        noResultsText: 'No complaint category found'
    });

    $('#complaintCategorySelect').on('select2:select select2:clear', syncComplaintCategoryName);
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
            { data: 'complaint_category' },
            { data: 'customer_address' },
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

function initAssignValidation() {
    const form = document.getElementById('assignComplaintForm');

    if (!form || typeof validate === 'undefined') {
        return;
    }

    const constraints = {
        assign_complaint: {
            presence: {
                allowEmpty: false,
                message: '^Please select an ELGi Engineer'
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

            if (field === 'assign_complaint') {
                $('#assignModalAssignToSelect').addClass('is-invalid');
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

            if (input.name === 'assign_complaint') {
                $('#assignModalAssignToSelect').toggleClass('is-invalid', !!fieldErrors);
            }

            if (msg) {
                msg.textContent = fieldErrors ? fieldErrors[0] : '';
            }
        });
    });

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
        isSubmitting = false;
    });
}

function resetAssignForm(complaintId) {
    const form = document.getElementById('assignComplaintForm');

    if (!form) {
        return;
    }

    form.reset();
    document.getElementById('assignComplaintId').value = complaintId;
    resetAssignToSelect2('assignModalAssignToSelect');

    form.querySelectorAll('.is-invalid').forEach(function (el) {
        el.classList.remove('is-invalid');
    });
    form.querySelectorAll('.validation-msg').forEach(function (el) {
        el.textContent = '';
    });

    const submitButton = form.querySelector('[name="assign_user"]');
    if (submitButton) {
        submitButton.classList.remove('disabled_btn');
    }
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
        const assignToField = form.querySelector('[name="reassign_complaint"]');
        const reassignRemarksField = form.querySelector('[name="reassign_remarks"]');
 
        if (remarksField) {
            remarksField.value = isYes ? remarksField.value : '';
        }
 
        if (assignToField && !isNo) {
            resetAssignToSelect2('closureReassignToSelect');
        }

        if (reassignRemarksField && !isNo) {
            reassignRemarksField.value = '';
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

        if (fieldName === 'reassign_complaint') {
            $('#closureReassignToSelect').addClass('is-invalid');
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
            const assignTo = form.querySelector('[name="reassign_complaint"]').value.trim();
            if (!assignTo) {
                errors.reassign_complaint = ['Reassign to is required'];
            }

            const reassignRemarks = form.querySelector('[name="reassign_remarks"]').value.trim();
            if (reassignRemarks && reassignRemarks.length > 500) {
                errors.reassign_remarks = ['Remarks cannot exceed 500 characters'];
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




 
function resetClosureForm(complaintId) {
    const form = document.getElementById('closureForm');
 
    if (!form) {
        return;
    }
 
    form.reset();
    document.getElementById('closureComplaintId').value = complaintId;
    resetAssignToSelect2('closureReassignToSelect');

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
    initPincodeSelect2();
    initFabnoSelect2('complaintForm', 'complaintFabNumberSelect');
    initComplaintCategorySelect2();
    initAssignToSelect2('complaintForm', 'complaintAssignToSelect');
    initAssignToSelect2('assignComplaintForm', 'assignModalAssignToSelect', {
        dropdownParent: $('#assignModal')
    });
    initAssignToSelect2('closureForm', 'closureReassignToSelect', {
        dropdownParent: $('#closureModal'),
        validationField: 'reassign_complaint'
    });
    initAssignValidation();
    initClosureValidation();
 
    setTimeout(function() {
        $('.alert-success').fadeOut();
    }, 3000);
 
});
 
$(document).on('click', '.manual-assign-btn', function() {
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
        resetPincodeSelect2(complaintForm);
        resetFabNumberSelect2ById('complaintFabNumberSelect');
        resetStaticSelect2('complaintCategorySelect');
        document.getElementById('complaintCategoryName').value = '';
        resetAssignToSelect2('complaintAssignToSelect');
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
</body>
 
</html>