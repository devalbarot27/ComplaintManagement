<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/contact_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

admin_ensure_session_role($obconn);
contact_require_page_access($obconn);
contact_ensure_schema($obconn);

$success_message = '';
$error_message = '';
$actorUsername = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = contact_from_post($_POST);
    $isEdit = $recordId > 0;

    $validationError = contact_validate($obconn, $data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (contact_email_exists($obconn, $data['email'], $recordId)) {
        $error_message = 'Email already exists. Please choose a different email.';
    } elseif (contact_mobile_exists($obconn, $data['mobile'], $recordId)) {
        $error_message = 'Mobile already exists. Please choose a different mobile number.';
    } else {
        try {
            if ($isEdit) {
                if (!contact_get_by_id($obconn, $recordId)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    contact_update($obconn, $recordId, $data, $actorUsername);
                    $success_message = 'Contact updated successfully.';
                }
            } else {
                contact_insert($obconn, $data, $actorUsername);
                $success_message = 'Contact saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update contact.' : 'Failed to save contact.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact</title>
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
                    <div class="page-subtitle">Manage contacts linked to Customer Master.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openContactForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Contact
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeContactForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="contactFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-person-lines-fill"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="contactFormModeLabel">Add Contact</h2>
                            <p class="complaint-form-header__subtitle">Enter contact person details for a customer.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="contactForm" novalidate>
                    <input type="hidden" name="record_id" id="contactRecordId" value="">
                    <input type="hidden" name="submit_contact" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="contactCustomerSelect">
                                        Customer <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="customer_id" id="contactCustomerSelect"
                                        data-placeholder="Search customer" style="width:100%;">
                                        <option value=""></option>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="customer_id"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="first_name" maxlength="100"
                                        placeholder="Enter first name">
                                    <div class="text-danger validation-msg" data-field="first_name"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="last_name" maxlength="100"
                                        placeholder="Enter last name">
                                    <div class="text-danger validation-msg" data-field="last_name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" maxlength="150"
                                        placeholder="Enter email">
                                    <div class="text-danger validation-msg" data-field="email"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Mobile <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="mobile" maxlength="10"
                                        placeholder="10-digit mobile number" inputmode="numeric">
                                    <div class="text-danger validation-msg" data-field="mobile"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelContactForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitContactBtn">
                            <i class="bi bi-check-lg"></i> Save Contact
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Contact List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="contactTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="18%">Customer</th>
                                <th width="14%">First Name</th>
                                <th width="14%">Last Name</th>
                                <th width="16%">Email</th>
                                <th width="12%">Mobile</th>
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

    <script src="js/contact.js"></script>
</body>

</html>
