<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/role_helpers.php';



require_system_admin($obconn);

$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_role'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = role_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = role_validate($data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (role_name_exists($obconn, $data['role_name'], $recordId)) {
        $error_message = 'Role name already exists. Please choose a different name.';
    } else {
        try {
            if ($isEdit) {
                if (!role_get_by_id($obconn, $recordId)) {
                    $error_message = 'Role not found or already deleted.';
                } else {
                    role_update($obconn, $recordId, $data);
                    $success_message = 'Role updated successfully.';
                }
            } else {
                role_insert($obconn, $data, $createdBy);
                $success_message = 'Role saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update role.' : 'Failed to save role.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management</title>
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
                    <div class="page-subtitle">Create and manage application roles.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openRoleForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Role
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeRoleForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="roleFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-shield-lock"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="roleFormModeLabel">Add Role</h2>
                            <p class="complaint-form-header__subtitle">Define role name and description.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="roleForm" novalidate>
                    <input type="hidden" name="record_id" id="roleRecordId" value="">
                    <input type="hidden" name="submit_role" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Role Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="role_name" maxlength="100" placeholder="e.g. Admin">
                                    <div class="text-danger validation-msg" data-field="role_name"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="Role description"></textarea>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelRoleForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitRoleBtn">
                            <i class="bi bi-check-lg"></i> Save Role
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Role List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="rolesTable">
                        <thead>
                            <tr>
                                <th width="10%">ID</th>
                                <th width="25%">Role Name</th>
                                <th width="45%">Description</th>
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

    <script src="js/roles.js"></script>
</body>

</html>
