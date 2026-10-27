<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/help_support_helpers.php';
require_once __DIR__ . '/includes/current_username_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

admin_ensure_session_role($obconn);
help_support_ensure_schema($obconn);
help_support_require_page_access($obconn);

$helpSupportPermissions = help_support_action_permissions($obconn);
$canAdd = $helpSupportPermissions['add'];
$canEdit = $helpSupportPermissions['edit'];

$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_help_support'])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = help_support_from_post($_POST);
    $isEdit = $recordId > 0;
    $existing = $isEdit ? help_support_get_by_id($obconn, $recordId) : null;

    if ($isEdit && !$canEdit) {
        $error_message = 'Access denied. You do not have permission to edit Help & Support items.';
    } elseif (!$isEdit && !$canAdd) {
        $error_message = 'Access denied. You do not have permission to add Help & Support items.';
    } else {
        $isUrl = $data['resource_type'] === 'url';
        $upload = $_FILES['attachment'] ?? null;
        $uploadError = $isUrl ? null : help_support_upload_error($upload);
        $hasNewFile = !$isUrl && help_support_has_upload($upload);
        $hasExistingFile = !$isUrl
            && $existing !== null
            && trim((string) ($existing['attachment_path'] ?? '')) !== ''
            && !$hasNewFile;
        if ($isUrl) {
            $data['resource_url'] = trim((string) $data['resource_url']);
            $data['remove_attachment'] = true;
        } else {
            $data['resource_url'] = '';
        }

        if ($isEdit && $existing === null) {
            $error_message = 'Record not found or already deleted.';
        } elseif ($uploadError !== null) {
            $error_message = $uploadError;
        } else {
            $validationError = help_support_validate($data, $hasNewFile, $hasExistingFile);
            if ($validationError !== null) {
                $error_message = $validationError;
            }
        }

        if ($error_message === '') {
            $attachmentPath = $hasExistingFile ? (string) $existing['attachment_path'] : '';
            $attachmentName = $hasExistingFile ? (string) $existing['attachment_name'] : '';
            $stored = null;

            try {
                if ($hasNewFile) {
                    $stored = help_support_store_upload($upload);
                    $attachmentPath = $stored['attachment_path'];
                    $attachmentName = $stored['attachment_name'];
                }

                $record = [
                    'category' => $data['category'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'qty' => $data['qty'],
                    'attachment_path' => $attachmentPath,
                    'attachment_name' => $attachmentName,
                    'resource_url' => $data['resource_url'],
                ];

                if ($isEdit) {
                    help_support_update($obconn, $recordId, $record);
                    if ($hasNewFile || $data['remove_attachment']) {
                        $oldPath = (string) ($existing['attachment_path'] ?? '');
                        if ($oldPath !== '' && $oldPath !== $attachmentPath) {
                            help_support_delete_stored_file($oldPath);
                        }
                    }
                    $success_message = 'Help & Support item updated successfully.';
                } else {
                    help_support_insert($obconn, $record, $createdBy);
                    $success_message = 'Help & Support item saved successfully.';
                }
            } catch (Throwable $e) {
                if ($stored !== null) {
                    help_support_delete_stored_file($stored['attachment_path']);
                }
                $error_message = $isEdit
                    ? 'Failed to update Help & Support item.'
                    : 'Failed to save Help & Support item.';
            }
        }
    }
}

$categoryOptions = help_support_category_options();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help &amp; Support</title>
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
                    <div class="page-subtitle">Manage brochures, manuals, FAQs, videos, and contact support.</div>
                </div>
                <div class="header-btn-group">
                    <?php if ($canAdd) { ?>
                    <button class="new-order-btn btn-complaint-primary" id="openHelpSupportForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add Help &amp; Support
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeHelpSupportForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <?php } ?>
                </div>
            </div>

            <?php if ($canAdd || $canEdit) { ?>
            <div class="complaint-form-card" id="helpSupportFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-question-circle"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="helpSupportFormModeLabel">Add Help &amp; Support</h2>
                            <p class="complaint-form-header__subtitle">Choose a category, then add an attachment or a URL.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="helpSupportForm" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="record_id" id="helpSupportRecordId" value="">
                    <input type="hidden" name="has_attachment" id="helpSupportHasAttachment" value="0">
                    <input type="hidden" name="submit_help_support" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-4 form-group">
                                    <label class="form-label" for="helpSupportCategory">Category <span class="text-danger">*</span></label>
                                    <select class="form-control" name="category" id="helpSupportCategory">
                                        <option value="">Select category</option>
                                        <?php foreach ($categoryOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="category"></div>
                                </div>
                                <div class="col-md-5 form-group">
                                    <label class="form-label" for="helpSupportTitle">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" id="helpSupportTitle" maxlength="255" placeholder="Enter title">
                                    <div class="text-danger validation-msg" data-field="title"></div>
                                </div>
                                <div class="col-md-3 form-group">
                                    <label class="form-label" for="helpSupportQty">Qty <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="qty" id="helpSupportQty" min="0" max="999999" step="1" placeholder="0">
                                    <div class="text-danger validation-msg" data-field="qty"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label" for="helpSupportDescription">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control" name="description" id="helpSupportDescription" rows="4" maxlength="4000" placeholder="Enter description"></textarea>
                                    <div class="text-danger validation-msg" data-field="description"></div>
                                </div>
                                <div class="col-12 form-group">
                                    <label class="form-label">Attachment / URL <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-wrap gap-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="resource_type" id="helpSupportTypeAttachment" value="attachment" checked>
                                            <label class="form-check-label" for="helpSupportTypeAttachment">Attachment</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="resource_type" id="helpSupportTypeUrl" value="url">
                                            <label class="form-check-label" for="helpSupportTypeUrl">URL</label>
                                        </div>
                                    </div>
                                    <div class="text-danger validation-msg" data-field="resource_type"></div>
                                </div>
                                <div class="col-md-6 form-group" id="helpSupportAttachmentGroup">
                                    <label class="form-label" for="helpSupportAttachment">Attachment <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" name="attachment" id="helpSupportAttachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.mp4,.webm">
                                    <div class="form-text">PDF, JPG, PNG, DOC, DOCX, XLS, XLSX, MP4, or WEBM. Maximum 10 MB.</div>
                                    <div id="helpSupportCurrentFile" class="small mt-2" style="display:none;"></div>
                                    <div class="text-danger validation-msg" data-field="attachment"></div>
                                </div>
                                <div class="col-md-6 form-group" id="helpSupportUrlGroup" style="display:none;">
                                    <label class="form-label" for="helpSupportUrl">URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" name="resource_url" id="helpSupportUrl" maxlength="500" placeholder="https://" disabled>
                                    <div class="text-danger validation-msg" data-field="resource_url"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelHelpSupportForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitHelpSupportBtn">
                            <i class="bi bi-check-lg"></i> Save Help &amp; Support
                        </button>
                    </div>
                </form>
            </div>
            <?php } ?>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">Help &amp; Support List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="helpSupportTable">
                        <thead>
                            <tr>
                                <th width="6%">ID</th>
                                <th width="14%">Category</th>
                                <th width="20%">Title</th>
                                <th width="8%">Qty</th>
                                <?php if (help_support_can_view_all()) { ?>
                                <th width="16%">Added By</th>
                                <?php } ?>
                                <th width="14%">Created At</th>
                                <th width="24%">Attachment / URL</th>
                                <th width="14%">Action</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        window.helpSupportShowAddedBy = <?php echo help_support_can_view_all() ? 'true' : 'false'; ?>;
    </script>
    <script src="js/help_support.js"></script>
</body>

</html>
