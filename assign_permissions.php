<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/role_helpers.php';
include 'includes/role_permission_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';
$roleOptions = role_get_all_active($obconn);
$createdBy = current_username();
$selectedRoleId = (int) ($_GET['role_id'] ?? ($_POST['role_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_role_permissions'])) {
    $selectedRoleId = (int) ($_POST['role_id'] ?? 0);
    $permissionIds = $_POST['permission_ids'] ?? [];

    if ($selectedRoleId <= 0) {
        $error_message = 'Please select a role.';
    } elseif (role_get_by_id($obconn, $selectedRoleId) === null) {
        $error_message = 'Selected role not found.';
    } else {
        try {
            role_permission_save($obconn, $selectedRoleId, (array) $permissionIds, $createdBy);
            $success_message = 'Permissions assigned successfully.';
        } catch (PDOException $e) {
            $error_message = 'Failed to save permission assignment.';
        }
    }
}

$permissionMatrix = $selectedRoleId > 0 ? role_permission_matrix($obconn, $selectedRoleId) : [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Permissions</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/rbac_assign.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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

            <div class="page-header">
                <div>
                    <div class="page-subtitle">Assign module permissions to roles.</div>
                </div>
            </div>

            <div class="booking-card mb-3">
                <div class="booking-header">
                    <div class="booking-title">Select Role</div>
                </div>
                <div class="p-3">
                    <form method="GET" id="roleSelectForm" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label" for="roleSelect">Role</label>
                            <select class="form-control" name="role_id" id="roleSelect">
                                <option value="">Select role</option>
                                <?php foreach ($roleOptions as $role) { ?>
                                <option value="<?php echo (int) $role['id']; ?>"
                                    <?php echo $selectedRoleId === (int) $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-dark">
                                <i class="bi bi-search"></i> Load Permissions
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if ($selectedRoleId > 0) { ?>
            <form method="POST" id="assignPermissionForm">
                <input type="hidden" name="role_id" value="<?php echo $selectedRoleId; ?>">
                <input type="hidden" name="submit_role_permissions" value="1">

                <div class="booking-card">
                    <div class="booking-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="booking-title">Permission Assignment</div>
                        <button type="submit" class="submit-btn btn-complaint-primary">
                            <i class="bi bi-check-lg"></i> Save Permission Assignment
                        </button>
                    </div>

                    <div class="p-3" id="permissionMatrixContainer">
                        <?php if (empty($permissionMatrix)) { ?>
                        <div class="alert alert-info mb-0">
                            No active modules or permissions found. Please create modules and permissions first.
                        </div>
                        <?php } else { ?>
                        <div class="rbac-select-all-bar">
                            <label class="rbac-permission-item rbac-select-all-label">
                                <input type="checkbox" id="checkAllPermissions">
                                <span><strong>Select All Permissions</strong></span>
                            </label>
                            <button type="button" class="btn btn-sm btn-outline-dark" id="clearAllPermissions">
                                Clear All
                            </button>
                        </div>
                        <?php foreach ($permissionMatrix as $module) { ?>
                        <div class="rbac-module-block">
                            <div class="rbac-module-title">
                                <label class="rbac-module-check-all">
                                    <input type="checkbox" class="module-check-all">
                                    <i class="bi bi-folder2-open"></i>
                                    <span><?php echo htmlspecialchars($module['module_name']); ?></span>
                                </label>
                            </div>
                            <?php if (empty($module['permissions'])) { ?>
                            <p class="text-muted mb-0 ps-4">No permissions defined for this module.</p>
                            <?php } else { ?>
                            <div class="rbac-permission-list">
                                <?php foreach ($module['permissions'] as $permission) { ?>
                                <label class="rbac-permission-item">
                                    <input type="checkbox"
                                        class="permission-checkbox"
                                        name="permission_ids[]"
                                        value="<?php echo (int) $permission['id']; ?>"
                                        <?php echo !empty($permission['assigned']) ? 'checked' : ''; ?>>
                                    <span><?php echo htmlspecialchars($permission['permission_name']); ?></span>
                                </label>
                                <?php } ?>
                            </div>
                            <?php } ?>
                        </div>
                        <?php } ?>
                        <?php } ?>
                    </div>

                    <?php if (!empty($permissionMatrix)) { ?>
                    <div class="complaint-form-actions border-top">
                        <button type="submit" class="submit-btn btn-complaint-primary">
                            <i class="bi bi-check-lg"></i> Save Permission Assignment
                        </button>
                    </div>
                    <?php } ?>
                </div>
            </form>
            <?php } ?>
        </div>
    </div>

    <script src="js/assign_permissions.js"></script>
</body>

</html>
