<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$actorUsername = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_customer'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = customer_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = customer_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (customer_address_get_by_code($dpconn, $data['cust_addr']) === null) {
        $error_message = 'Selected customer address is invalid.';
    } elseif (customer_code_exists($obconn, $data['cust_code'], $recordId)) {
        $error_message = 'Customer code already exists. Please choose a different code.';
    } elseif (customer_name_exists($obconn, $data['cust_name'], $recordId)) {
        $error_message = 'Customer name already exists. Please choose a different name.';
    } else {
        try {
            if ($isEdit) {
                if (!customer_get_by_id($obconn, $recordId)) {
                    $error_message = 'Customer not found or already deleted.';
                } else {
                    customer_update($obconn, $recordId, $data, $actorUsername);
                    $success_message = 'Customer updated successfully.';
                }
            } else {
                customer_insert($obconn, $data, $actorUsername);
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
    <title>Customers</title>
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
                    <div class="page-subtitle">Manage customer master records.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openCustomerForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Customer
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeCustomerForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="customerFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-person-badge"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="customerFormModeLabel">Add Customer</h2>
                            <p class="complaint-form-header__subtitle">Enter customer code, name, and address.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="customerForm" novalidate>
                    <input type="hidden" name="record_id" id="customerRecordId" value="">
                    <input type="hidden" name="submit_customer" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Customer Code <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="cust_code" maxlength="50"
                                        placeholder="e.g. CUST001" maxlength="20">
                                    <div class="text-danger validation-msg" data-field="cust_code"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="cust_name" maxlength="255"
                                        placeholder="e.g. Acme Industries">
                                    <div class="text-danger validation-msg" data-field="cust_name"></div>
                                </div>
                                <div class="col-md-12 form-group">
                                    <label class="form-label">Customer Address <span class="text-danger">*</span></label>
                                    <select class="form-control" name="cust_addr" id="customerAddrSelect" style="width:100%;">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="cust_addr"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelCustomerForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitCustomerBtn">
                            <i class="bi bi-check-lg"></i> Save Customer
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Customer List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="customersTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="15%">Code</th>
                                <th width="22%">Name</th>
                                <th width="30%">Address</th>
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

    <script src="js/customers.js"></script>
</body>

</html>
