<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/module_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_module'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = module_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = module_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (module_slug_exists($obconn, $data['module_slug'], $recordId)) {
        $error_message = 'Module slug already exists. Please choose a different slug.';
    } else {
        try {
            if ($isEdit) {
                if (!module_get_by_id($obconn, $recordId)) {
                    $error_message = 'Module not found or already deleted.';
                } else {
                    module_update($obconn, $recordId, $data);
                    $success_message = 'Module updated successfully.';
                }
            } else {
                module_insert($obconn, $data, $createdBy);
                $success_message = 'Module saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update module.' : 'Failed to save module.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Management</title>
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
                    <div class="page-subtitle">Manage system modules for permission grouping.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openModuleForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Module
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeModuleForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="moduleFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-grid-3x3-gap"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="moduleFormModeLabel">Add Module</h2>
                            <p class="complaint-form-header__subtitle">Define module name and slug.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="moduleForm" novalidate>
                    <input type="hidden" name="record_id" id="moduleRecordId" value="">
                    <input type="hidden" name="submit_module" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Module Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="module_name" maxlength="100" placeholder="e.g. User Management">
                                    <div class="text-danger validation-msg" data-field="module_name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Module Slug <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="module_slug" maxlength="100" placeholder="e.g. user-management">
                                    <div class="text-danger validation-msg" data-field="module_slug"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Ordering</label>
                                    <input type="number" class="form-control" name="ordering" min="0" step="1" value="0" placeholder="e.g. 10">
                                    <div class="text-danger validation-msg" data-field="ordering"></div>
                                </div>
                                <div class="col-md-6 form-group" id="defaultPermissionsWrap">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="create_default_permissions" value="1" id="createDefaultPermissions" checked>
                                        <label class="form-check-label" for="createDefaultPermissions">
                                            Create default permissions (View, Add, Edit, Delete)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Module description"></textarea>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelModuleForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitModuleBtn">
                            <i class="bi bi-check-lg"></i> Save Module
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Module List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="modulesTable">
                        <thead>
                            <tr>
                                <th width="7%">ID</th>
                                <th width="8%">Ordering</th>
                                <th width="18%">Module Name</th>
                                <th width="16%">Module Slug</th>
                                <th width="32%">Description</th>
                                <th width="13%">Created At</th>
                                <th width="8%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="js/modules.js"></script>
</body>

</html>