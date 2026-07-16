<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_helpers.php';

require_system_admin($obconn);

$success_message = '';
$error_message = '';


$roleOptions = user_role_options($obconn);
$salesCoordinatorOptions = user_sales_coordinator_options_for_form($obconn);
$formRecord = [
    'id' => 0,
    'role' => 0,
    'username' => '',
    'name' => '',
    'email' => '',
    'mobile_number' => '',
    'sales_coordinator_id' => 0,
];
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_user'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = user_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = user_validate($data, $isEdit, $obconn);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (user_username_exists($obconn, $data['username'], $recordId)) {
        $error_message = 'Username already exists. Please choose a different username.';
    } elseif (user_email_exists($obconn, $data['email'], $recordId)) {
        $error_message = 'Email address already exists';
    } elseif (user_mobile_exists($obconn, $data['mobile_number'], $recordId)) {
        $error_message = 'Mobile number already exists';
    } else {
        try {
            if ($isEdit) {
                if (!user_get_by_id($obconn, $recordId)) {
                    $error_message = 'User not found or already deleted.';
                } else {
                    user_update($obconn, $recordId, $data);
                    $success_message = 'User updated successfully.';
                }
            } else {
                user_insert($obconn, $data, $createdBy);
                $success_message = 'User saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update user.' : 'Failed to save user.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
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
                    <div class="page-subtitle">Manage application users, roles, and credentials.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openUserForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add User
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeUserForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="userFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-person-plus"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="userFormModeLabel">Add User</h2>
                            <p class="complaint-form-header__subtitle">Create or update user account details.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="userForm" novalidate>
                    <input type="hidden" name="record_id" id="userRecordId" value="">
                    <input type="hidden" name="submit_user" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">User Details</h3>
                                    <p class="complaint-form-section__hint">Role, identity, and contact information</p>
                                </div>
                            </div>
                            <?php include 'includes/user_form_fields.php'; ?>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelUserForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitUserBtn">
                            <i class="bi bi-check-lg"></i> Save User
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">User List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="usersTable">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="12%">Role</th>
                                <th width="12%">Username</th>
                                <th width="14%">Name</th>
                                <th width="16%">Email</th>
                                <th width="10%">Mobile</th>
                                <th width="12%">Last Login</th>
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

    <script src="js/users.js"></script>
    <script>
    window.USER_ROLES_REQUIRING_SALES_COORDINATOR = <?php echo json_encode(user_roles_requiring_sales_coordinator()); ?>;
    $(document).ready(function () {
        document.getElementById('cancelUserForm').addEventListener('click', closeUserFormPanel);
        document.getElementById('closeUserForm').addEventListener('click', closeUserFormPanel);
        document.getElementById('openUserForm').addEventListener('click', function () {
            resetUserForm();
            openUserFormPanel();
        });
        setTimeout(function () { $('.alert-success').fadeOut(); }, 3000);
    });
    </script>
</body>

</html>