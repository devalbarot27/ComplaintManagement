<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/module_helpers.php';
include 'includes/permission_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$moduleOptions = module_get_all_active($obconn);
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_permission'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = permission_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = permission_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (!module_get_by_id($obconn, (int) $data['module_id'])) {
        $error_message = 'Selected module not found.';
    } elseif (permission_slug_exists($obconn, (int) $data['module_id'], $data['permission_slug'], $recordId)) {
        $error_message = 'Permission slug already exists for this module.';
    } else {
        try {
            if ($isEdit) {
                if (!permission_get_by_id($obconn, $recordId)) {
                    $error_message = 'Permission not found or already deleted.';
                } else {
                    permission_update($obconn, $recordId, $data);
                    $success_message = 'Permission updated successfully.';
                }
            } else {
                permission_insert($obconn, $data, $createdBy);
                $success_message = 'Permission saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update permission.' : 'Failed to save permission.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permission Management</title>
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
                    <div class="page-subtitle">Manage permissions linked to system modules.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openPermissionForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Permission
                    </button>
                    <button class="close-form-btn cancel-btn" id="closePermissionForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="permissionFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-key"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="permissionFormModeLabel">Add Permission</h2>
                            <p class="complaint-form-header__subtitle">Define permission under a module.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="permissionForm" novalidate>
                    <input type="hidden" name="record_id" id="permissionRecordId" value="">
                    <input type="hidden" name="submit_permission" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Module <span class="text-danger">*</span></label>
                                    <select class="form-control" name="module_id">
                                        <option value="">Select module</option>
                                        <?php foreach ($moduleOptions as $module) { ?>
                                        <option value="<?php echo (int) $module['id']; ?>">
                                            <?php echo htmlspecialchars($module['module_name']); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="module_id"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Permission Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="permission_name" maxlength="100" placeholder="e.g. View">
                                    <div class="text-danger validation-msg" data-field="permission_name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Permission Slug <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="permission_slug" maxlength="100" placeholder="e.g. view">
                                    <div class="text-danger validation-msg" data-field="permission_slug"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Permission description"></textarea>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelPermissionForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitPermissionBtn">
                            <i class="bi bi-check-lg"></i> Save Permission
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Permission List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="permissionsTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="16%">Module</th>
                                <th width="16%">Permission Name</th>
                                <th width="16%">Permission Slug</th>
                                <th width="28%">Description</th>
                                <th width="14%">Created At</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/permissions.js"></script>
</body>

</html>
