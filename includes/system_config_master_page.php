<?php

if (!isset($scmType) || $scmType === '') {
    die('System configuration page type is not defined.');
}

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/system_config_master_helpers.php';

require_system_admin($obconn);

$config = scm_config($scmType);
$success_message = '';
$error_message = '';
$createdBy = current_username();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST[$config['submit_key']])) {
    $recordId = (int) ($_POST['record_id'] ?? 0);
    $data = scm_from_post($_POST);
    $isEdit = $recordId > 0;
    $validationError = scm_validate($data, $config['label']);

    if ($validationError !== null) {
        $error_message = $validationError;
    } elseif (scm_name_exists($obconn, $scmType, $data['name'], $recordId)) {
        $error_message = $config['label'] . ' name already exists. Please choose a different name.';
    } else {
        try {
            if ($isEdit) {
                if (!scm_get_by_id($obconn, $scmType, $recordId)) {
                    $error_message = $config['label'] . ' not found or already deleted.';
                } else {
                    scm_update($obconn, $scmType, $recordId, $data);
                    $success_message = $config['label'] . ' updated successfully.';
                }
            } else {
                scm_insert($obconn, $scmType, $data, $createdBy);
                $success_message = $config['label'] . ' saved successfully.';
            }
        } catch (PDOException $e) {
            $error_message = $isEdit ? 'Failed to update ' . strtolower($config['label']) . '.' : 'Failed to save ' . strtolower($config['label']) . '.';
        }
    }
}

$scmJsConfig = scm_page_js_config($scmType);
$statusOptions = rbac_status_options();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['label_plural']); ?></title>
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
                    <div class="page-subtitle"><?php echo htmlspecialchars($config['subtitle']); ?></div>
                </div>
                <div class="header-btn-group">
                    <button class="new-order-btn btn-complaint-primary" id="openScmForm" type="button">
                        <i class="bi bi-plus-lg"></i> Add <?php echo htmlspecialchars($config['label']); ?>
                    </button>
                    <button class="close-form-btn cancel-btn" id="closeScmForm" type="button">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                </div>
            </div>

            <div class="complaint-form-card" id="scmFormCard">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi <?php echo htmlspecialchars($config['icon']); ?>"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title" id="scmFormModeLabel">Add <?php echo htmlspecialchars($config['label']); ?></h2>
                            <p class="complaint-form-header__subtitle">Enter name and status.</p>
                        </div>
                    </div>
                </div>

                <form method="POST" id="scmForm" novalidate>
                    <input type="hidden" name="record_id" id="scmRecordId" value="">
                    <input type="hidden" name="<?php echo htmlspecialchars($config['submit_key']); ?>" value="1">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="name" maxlength="100"
                                        placeholder="<?php echo htmlspecialchars($config['name_placeholder']); ?>">
                                    <div class="text-danger validation-msg" data-field="name"></div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-control" name="status">
                                        <?php foreach ($statusOptions as $value => $label) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($label); ?></option>
                                        <?php } ?>
                                    </select>
                                    <div class="text-danger validation-msg" data-field="status"></div>
                                </div>
                            </div>
                        </section>
                    </div>
                    <div class="complaint-form-actions">
                        <button type="button" class="cancel-btn" id="cancelScmForm">Cancel</button>
                        <button class="submit-btn btn-complaint-primary" type="submit" id="submitScmBtn">
                            <i class="bi bi-check-lg"></i> Save <?php echo htmlspecialchars($config['label']); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title"><?php echo htmlspecialchars($config['label_plural']); ?> List</div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover booking-table w-100" id="scmTable">
                        <thead>
                            <tr>
                                <th width="10%">ID</th>
                                <th width="35%">Name</th>
                                <th width="15%">Status</th>
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

    <script>
        window.SCM_PAGE_CONFIG = <?php echo json_encode($scmJsConfig, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
    </script>
    <script src="js/system_config_master.js"></script>
</body>

</html>
