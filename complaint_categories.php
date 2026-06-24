<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/complaint_category_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$createdByUserId = current_user_id($obconn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint_category'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = complaint_category_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = complaint_category_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (complaint_category_name_exists($obconn, $data['name'], $recordId)) {
        $error_message = 'Category name already exists. Please choose a different name.';
    } else {
        try {
            if ($isEdit) {
                if (!complaint_category_get_by_id($obconn, $recordId)) {
                    $error_message = 'Complaint category not found or already deleted.';
                } else {
                    complaint_category_update($obconn, $recordId, $data);
                    $success_message = 'Complaint category updated successfully.';
                }
            } else {
                complaint_category_insert($obconn, $data, $createdByUserId);
                $success_message = 'Complaint category saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update complaint category.' : 'Failed to save complaint category.';
        }
    }
}

$statusOptions = rbac_status_options();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Category</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
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
                    <div class="page-subtitle">Manage complaint category options for complaint entry.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openComplaintCategoryForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Complaint Category
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeComplaintCategoryForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="complaintCategoryFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-tags"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="complaintCategoryFormModeLabel">Add Complaint Category</h2>
                            <p class="complaint-form-header__subtitle">Enter category name and status.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="complaintCategoryForm" novalidate>
                    <input type="hidden" name="record_id" id="complaintCategoryRecordId" value="">
                    <input type="hidden" name="submit_complaint_category" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" maxlength="100"
                                        placeholder="e.g. Product Issue">
                                    <div class="text-danger validation-msg" data-field="name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="status">
                                        <?php foreach ($statusOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="status"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelComplaintCategoryForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitComplaintCategoryBtn">
                            <i class="bi bi-check-lg"></i> Save Complaint Category
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="booking-title">Complaint Category List</div>
                    <div class="d-flex flex-wrap gap-2 align-items-center">
                        <select class="form-control form-control-sm" id="complaintCategoryRecordFilter" style="width:auto; min-width:140px;">
                            <option value="active">Active Records</option>
                            <option value="deleted">Deleted Records</option>
                        </select>
                        <select class="form-control form-control-sm" id="complaintCategoryStatusFilter" style="width:auto; min-width:130px;">
                            <option value="">All Status</option>
                            <?php foreach ($statusOptions as $value => $label) { ?>
                            <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="complaintCategoriesTable">
                        <thead>
                            <tr>
                                <th width="10%">ID</th>
                                <th width="35%">Name</th>
                                <th width="15%">Status</th>
                                <th width="20%">Created At</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/complaint_categories.js"></script>
</body>

</html>
