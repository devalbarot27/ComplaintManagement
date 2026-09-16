<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_master_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);
customer_master_ensure_schema($obconn);

$success_message = '';
$error_message = '';
$actorUsername = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_customer_master'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = customer_master_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = customer_master_validate($obconn, $data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (customer_master_email_exists($obconn, $data['email'], $recordId)) {
        $error_message = 'Email already exists. Please choose a different email.';
    } elseif (customer_master_mobile_exists($obconn, $data['mobile'], $recordId)) {
        $error_message = 'Mobile already exists. Please choose a different mobile number.';
    } else {
        try {
            if ($isEdit) {
                if (!customer_master_get_by_id($obconn, $recordId)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    customer_master_update($obconn, $recordId, $data, $actorUsername);
                    $success_message = 'Customer updated successfully.';
                }
            } else {
                customer_master_insert($obconn, $data, $actorUsername);
                $success_message = 'Customer saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update customer.' : 'Failed to save customer.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Master</title>
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
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
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
                    <div class="page-subtitle">Manage customer contact details and address information.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="opencustomerMasterForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Customer
                    </button>
                    <button class="close-form-btn cancel-btn" id="closecustomerMasterForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="customerMasterFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-person-vcard"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="customerMasterFormModeLabel">Add Customer</h2>
                            <p class="complaint-form-header__subtitle">Enter customer name, contact, and address details.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="customerMasterForm" novalidate>
                    <input type="hidden" name="record_id" id="customerMasterRecordId" value="">
                    <input type="hidden" name="submit_customer_master" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="customer_name" maxlength="150"
                                        placeholder="Enter customer name">
                                    <div class="text-danger validation-msg" data-field="customer_name"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" maxlength="150"
                                        placeholder="Enter email">
                                    <div class="text-danger validation-msg" data-field="email"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="mobile" maxlength="10"
                                        placeholder="10-digit mobile number" inputmode="numeric">
                                    <div class="text-danger validation-msg" data-field="mobile"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Street 1 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="street_1" maxlength="255"
                                        placeholder="House / building / street">
                                    <div class="text-danger validation-msg" data-field="street_1"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Street 2 <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="street_2" maxlength="255"
                                        placeholder="Area / landmark">
                                    <div class="text-danger validation-msg" data-field="street_2"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label" for="customerMasterPincodeSelect">
                                        Pincode <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="pincode" id="customerMasterPincodeSelect"
                                        data-placeholder="Search pincode" style="width:100%;">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="pincode"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">City <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="city" maxlength="100" readonly
                                        style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                                    <div class="text-danger validation-msg" data-field="city"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">District <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="district" maxlength="100" readonly
                                        style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                                    <div class="text-danger validation-msg" data-field="district"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">State <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="state" maxlength="100" readonly
                                        style="background-color: #f8f9fa;" placeholder="Auto-filled from pincode">
                                    <div class="text-danger validation-msg" data-field="state"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelcustomerMasterForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitcustomerMasterBtn">
                            <i class="bi bi-check-lg"></i> Save Customer
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Customer Master List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="customerMasterTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="16%">Customer Name</th>
                                <th width="16%">Email</th>
                                <th width="12%">Mobile</th>
                                <th width="12%">City</th>
                                <th width="12%">State</th>
                                <th width="12%">Created At</th>
                                <th width="12%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/pincode_select2.js"></script>
    <script src="js/customer_master.js"></script>
</body>

</html>
