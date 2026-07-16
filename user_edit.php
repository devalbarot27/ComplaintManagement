<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_helpers.php';

require_system_admin($obconn);

$encodedId = trim((string) ($_GET['id'] ?? ''));
$recordId = (int) base64_decode($encodedId, true);

if ($recordId <= 0) {
    die('Invalid user record.');
}

$record = user_get_by_id($obconn, $recordId);

if (!$record) {
    die('User not found.');
}

$success_message = '';
$error_message = '';
$roleOptions = user_role_options($obconn);
$formRecord = user_form_record_from_row($record);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_user'])) {
    $data = user_from_post($_POST);
    $formRecord = user_form_record_from_post($data, $recordId);
    $validationError = user_validate($data, true, $obconn);

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
            user_update($obconn, $recordId, $data);
            $_SESSION['success_message'] = 'User updated successfully.';
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Failed to update user.';
        }
    }
}

$salesCoordinatorOptions = user_sales_coordinator_options_for_form(
    $obconn,
    (int) ($formRecord['sales_coordinator_id'] ?? 0)
);

$displayName = user_display_value($formRecord['name']);
$pageTitle = $displayName !== '-' ? $displayName : user_display_value($formRecord['username']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User #<?php echo (int) $recordId; ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/validate.js/0.13.1/validate.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if (!empty($error_message)) { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">Update user account details and Sales Coordinator assignment.</div>
                </div>
                <div class="header-btn-group">
                    <a href="user_details.php?id=<?php echo htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8'); ?>"
                        class="cancel-btn">
                        <i class="bi bi-arrow-left"></i> Back to Details
                    </a>
                </div>
            </div>

            <div class="complaint-form-card show" id="userFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-pencil-square"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="userFormModeLabel">Edit User</h2>
                            <p class="complaint-form-header__subtitle"><?php echo htmlspecialchars($pageTitle); ?></p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="userForm" novalidate>
                    <input type="hidden" name="record_id" id="userRecordId" value="<?php echo (int) $recordId; ?>">
                    <input type="hidden" name="submit_user" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="complaint-form-section__head">
                                <span class="complaint-form-section__badge">1</span>
                                <div>
                                    <h3 class="complaint-form-section__title">User Details</h3>
                                    <p class="complaint-form-section__hint">Role, identity, contact, and Sales Coordinator</p>
                                </div>
                            </div>
                            <?php include 'includes/user_form_fields.php'; ?>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelUserForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitUserBtn">
                            <i class="bi bi-check-lg"></i> Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    window.USER_FORM_PAGE = 'edit';
    window.USER_ROLES_REQUIRING_SALES_COORDINATOR = <?php echo json_encode(user_roles_requiring_sales_coordinator()); ?>;
    window.USER_FORM_CANCEL_URL = <?php echo json_encode('users.php'); ?>;
    </script>
    <script src="js/users.js"></script>
</body>

</html>