<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_approval_configuration_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);
user_approval_config_ensure_schema($obconn);

$success_message = '';
$error_message = '';
$createdBy = current_username();
$userOptions = user_approval_config_user_options($obconn);
$moduleOptions = user_approval_config_module_options();
$formRecord = [
    'user_id' => 0,
    'module_slug' => '',
    'level_1_approval' => false,
    'level_2_approval' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_user_approval_config'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = user_approval_config_from_post($_POST);
    $formRecord = $data;
    $isEdit = $recordId > 0;
    $validationError = user_approval_config_validate($obconn, $data);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (user_approval_config_exists($obconn, (int) $data['user_id'], (string) $data['module_slug'], $recordId)) {
        $error_message = 'Approval configuration already exists for this user and module.';
    } else {
        try {
            if ($isEdit) {
                if (!user_approval_config_get_by_id($obconn, $recordId)) {
                    $error_message = 'Record not found or already deleted.';
                } else {
                    user_approval_config_update($obconn, $recordId, $data);
                    $success_message = 'User approval configuration updated successfully.';
                    $formRecord = [
                        'user_id' => 0,
                        'module_slug' => '',
                        'level_1_approval' => false,
                        'level_2_approval' => false,
                    ];
                }
            } else {
                user_approval_config_insert($obconn, $data, $createdBy);
                $success_message = 'User approval configuration saved successfully.';
                $formRecord = [
                    'user_id' => 0,
                    'module_slug' => '',
                    'level_1_approval' => false,
                    'level_2_approval' => false,
                ];
            }
        } catch (PDOException $e) {
            $error_message = $isEdit
                ? 'Failed to update user approval configuration.'
                : 'Failed to save user approval configuration.';
        }
    }
}

$keepFormOpen = $error_message !== '';
$editingRecordId = $keepFormOpen ? (int) ($_POST['record_id'] ?? 0) : 0;
$selectedUserId = (int) ($formRecord['user_id'] ?? 0);
$selectedModule = (string) ($formRecord['module_slug'] ?? '');
$showLevel2 = $selectedModule !== user_approval_config_module_service();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approval Configuration</title>
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
                    <div class="page-subtitle">Assign FOC Parts and Service Claims approval levels to users.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openUserApprovalConfigForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Configuration
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeUserApprovalConfigForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card<?php echo $keepFormOpen ? ' show' : ''; ?>" id="userApprovalConfigFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-person-check"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="userApprovalConfigFormModeLabel"><?php echo $editingRecordId > 0 ? 'Edit User Approval Configuration' : 'Add User Approval Configuration'; ?></h2>
                            <p class="complaint-form-header__subtitle">Select a user, module, and approval levels.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="userApprovalConfigForm" novalidate>
                    <input type="hidden" name="record_id" id="userApprovalConfigRecordId" value="<?php echo $editingRecordId > 0 ? $editingRecordId : ''; ?>">
                    <input type="hidden" name="submit_user_approval_config" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label" for="userApprovalConfigUserId">
                                        User <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="user_id" id="userApprovalConfigUserId" style="width:100%;">
                                        <option value="">Select User</option>
                                        <?php foreach ($userOptions as $user) { ?>
                                        <option value="<?php echo (int) $user['id']; ?>"<?php echo $selectedUserId === (int) $user['id'] ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars(user_approval_config_user_label($user)); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="user_id"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label" for="userApprovalConfigModule">
                                        Module <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" name="module_slug" id="userApprovalConfigModule">
                                        <option value="">Select Module</option>
                                        <?php foreach ($moduleOptions as $slug => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($slug); ?>"<?php echo $selectedModule === $slug ? ' selected' : ''; ?>>
                                            <?php echo htmlspecialchars($label); ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="module_slug"></div>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label class="form-label d-block">
                                        Approval Levels <span class="text-danger">*</span>
                                    </label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="level_1_approval" value="1"
                                            id="userApprovalConfigLevel1"
                                            <?php echo !empty($formRecord['level_1_approval']) ? ' checked' : ''; ?>>
                                        <label class="form-check-label" for="userApprovalConfigLevel1">Level 1 Approval</label>
                                    </div>
                                    <div class="form-check" id="userApprovalConfigLevel2Wrap"<?php echo $showLevel2 ? '' : ' style="display: none;"'; ?>>
                                        <input class="form-check-input" type="checkbox" name="level_2_approval" value="1"
                                            id="userApprovalConfigLevel2"
                                            <?php echo !empty($formRecord['level_2_approval']) ? ' checked' : ''; ?>>
                                        <label class="form-check-label" for="userApprovalConfigLevel2">Level 2 Approval</label>
                                    </div>
                                    <div class="text-danger validation-msg" data-field="approval_levels"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelUserApprovalConfigForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitUserApprovalConfigBtn">
                            <i class="bi bi-check-lg"></i> <?php echo $editingRecordId > 0 ? 'Update' : 'Save'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">User Approval Configuration List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="userApprovalConfigTable">
                        <thead>
                            <tr>
                                <th width="6%">ID</th>
                                <th width="22%">User</th>
                                <th width="16%">Module</th>
                                <th width="14%">Level 1 Approval</th>
                                <th width="14%">Level 2 Approval</th>
                                <th width="14%">Created At</th>
                                <th width="10%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    window.USER_APPROVAL_CONFIG_FOC_MODULE = <?php echo json_encode(user_approval_config_module_foc()); ?>;
    window.USER_APPROVAL_CONFIG_SERVICE_MODULE = <?php echo json_encode(user_approval_config_module_service()); ?>;
    window.USER_APPROVAL_CONFIG_KEEP_FORM_OPEN = <?php echo $keepFormOpen ? 'true' : 'false'; ?>;
    </script>
    <script src="js/user_approval_configurations.js"></script>
</body>

</html>
