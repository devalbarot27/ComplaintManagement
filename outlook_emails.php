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
                $success_message = 'Brochure email configuration updated successfully.';
            } else {
                outlook_email_insert($obconn, $data, $createdBy);
                $success_message = 'Brochure email configuration saved successfully.';
            }
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23505') {
                $error_message = 'One of these email addresses is already saved.';
            } else {
                $error_message = $isEdit
                    ? 'Failed to update brochure email configuration.'
                    : 'Failed to save brochure email configuration.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brochure Email Configuration</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <style>
        .brochure-email-field__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }

        .brochure-email-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .brochure-email-control {
            display: flex;
            align-items: center;
            min-height: 44px;
            background: #fff;
            border: 1px solid #dbe2ea;
            border-radius: 10px;
            overflow: hidden;
        }

        .brochure-email-control:focus-within {
            border-color: #F44611;
            box-shadow: 0 0 0 3px rgba(244, 70, 17, 0.12);
        }

        .brochure-email-control:has(.is-invalid) {
            border-color: #dc3545;
            box-shadow: none;
        }

        .brochure-email-control__icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            color: #6b7280;
            flex: 0 0 42px;
        }

        .brochure-email-control .brochure-email-input {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            min-height: 42px;
            padding-left: 0;
        }

        .brochure-email-control .brochure-email-input:focus {
            box-shadow: none;
        }

        .brochure-email-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            border: 0;
            border-left: 1px solid #dbe2ea;
            background: #f8fafc;
            color: #374151;
            flex: 0 0 42px;
        }

        .brochure-email-remove:hover:not(:disabled) {
            background: #fff1f2;
            color: #b91c1c;
        }

        .brochure-email-remove:disabled {
            color: #cbd5e1;
            cursor: not-allowed;
        }

        .brochure-email-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 34px;
            padding: 0 14px;
            border: 1px solid #F44611;
            border-radius: 8px;
            background: #fff;
            color: #F44611;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        .brochure-email-add:hover {
            background: #F44611;
            color: #fff;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
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
                    <div class="page-subtitle">Configure brochure email addresses by category.</div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openOutlookEmailForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Brochure Email
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
                            <h2 class="complaint-form-header__title" id="outlookEmailFormModeLabel">Add Brochure Email</h2>
                            <p class="complaint-form-header__subtitle">Choose a category and add one or more email addresses.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="outlookEmailForm" novalidate>
                    <input type="hidden" name="record_id" id="outlookEmailRecordId" value="">
                    <input type="hidden" name="submit_outlook_email" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label" for="brochureEmailCategory">Category <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="category" id="brochureEmailCategory" maxlength="100" placeholder="Enter category">
                                    <div class="text-danger validation-msg" data-field="category"></div>
                                </div>
                                <div class="col-12 form-group brochure-email-field">
                                    <div class="brochure-email-field__head">
                                        <label class="form-label mb-0">Email <span class="text-danger">*</span></label>
                                        <button type="button" class="brochure-email-add" id="addBrochureEmailBtn">
                                            <i class="bi bi-plus-lg"></i> Add More
                                        </button>
                                    </div>
                                    <div id="brochureEmailList" class="brochure-email-list">
                                        <div class="brochure-email-row">
                                            <div class="brochure-email-control">
                                                <span class="brochure-email-control__icon" aria-hidden="true"><i class="bi bi-envelope"></i></span>
                                                <input type="email" class="form-control brochure-email-input" name="emails[]" maxlength="255" placeholder="name@company.com">
                                                <button type="button" class="brochure-email-remove" title="Remove" disabled>
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </div>
                                            <div class="text-danger validation-msg" data-field="emails_0"></div>
                                        </div>
                                    </div>
                                    <div class="text-danger validation-msg" data-field="emails"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelOutlookEmailForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitOutlookEmailBtn">
                            <i class="bi bi-check-lg"></i> Save
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Brochure Email List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="outlookEmailsTable">
                        <thead>
                            <tr>
                                <th width="8%">ID</th>
                                <th width="24%">Category</th>
                                <th width="38%">Email</th>
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