<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/outlook_email_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';

require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_outlook_email'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = outlook_email_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = outlook_email_validate($obconn, $data, $recordId);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif ($isEdit && !outlook_email_get_by_id($obconn, $recordId)) {
        $error_message = 'Record not found or already deleted.';
    } else {
        try {
            if ($isEdit) {
                outlook_email_update($obconn, $recordId, $data);
                $success_message = 'Outlook email updated successfully.';
            } else {
                outlook_email_insert($obconn, $data, $createdBy);
                $success_message = 'Outlook email saved successfully.';
            }
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23505') {
                $error_message = 'This user or Outlook email address is already saved.';
            } else {
                $error_message = $isEdit
                    ? 'Failed to update Outlook email.'
                    : 'Failed to save Outlook email.';
            }
        }
    }
}

$userOptions = outlook_email_user_options($obconn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlook Email Management</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
                    <div class="page-subtitle">Assign an Outlook email address to a user.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openOutlookEmailForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Outlook Email
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeOutlookEmailForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="outlookEmailFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-envelope"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="outlookEmailFormModeLabel">Add Outlook Email</h2>
                            <p class="complaint-form-header__subtitle">Select a user and enter their Outlook email address. Each user can have one address.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="outlookEmailForm" novalidate>
                    <input type="hidden" name="record_id" id="outlookEmailRecordId" value="">
                    <input type="hidden" name="submit_outlook_email" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md form-group">
                                    <label class="form-label" for="outlookEmailUserId">User <span class="text-danger">*</span></label>
                                    <select class="form-control" name="user_id" id="outlookEmailUserId" style="width:100%;">
                                        <option value="">Select user</option>
                                        <?php foreach ($userOptions as $user) { ?>
                                        <option value="<?php echo (int) $user['id']; ?>"><?php echo htmlspecialchars(outlook_email_user_label($user)); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="user_id"></div>
                                </div>
                                <div class="col-md form-group">
                                    <label class="form-label" for="outlookEmailAddress">Outlook Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="outlook_email" id="outlookEmailAddress" maxlength="255" placeholder="name@company.com">
                                    <div class="text-danger validation-msg" data-field="outlook_email"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelOutlookEmailForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitOutlookEmailBtn">
                            <i class="bi bi-check-lg"></i> Save Outlook Email
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Outlook Email List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="outlookEmailsTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="28%">User</th>
                                <th width="34%">Outlook Email</th>
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

    <script src="js/outlook_emails.js"></script>
</body>

</html>
