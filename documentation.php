<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/cdoc_helpers.php';
cdoc_ensure_schema($obconn);
if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$cdocPermissions = cdoc_action_permissions($obconn);
$canAddDocument = !empty($cdocPermissions['add']);
$canDeleteDocument = !empty($cdocPermissions['delete']);
$canManageDocuments = cdoc_user_can_manage($cdocPermissions);

$userName = current_assignee_name();
$createdBy = current_user_id($obconn);

$formData = [];
$reopenForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_documentation'])) {
    if (!$canAddDocument) {
        $error_message = 'Access denied. You do not have permission to publish documents.';
    } else {
        $data = cdoc_apply_product_master($obconn, cdoc_from_post($_POST));
        $fileField = $_FILES['document_file'] ?? null;
        $validationError = cdoc_validate($data, true, $fileField, $obconn);

        if ($validationError !== null) {
            $error_message = $validationError;
            $formData = $data;
            $reopenForm = true;
        } elseif ($createdBy === null || $createdBy <= 0) {
            $error_message = 'Unable to resolve logged-in user.';
            $formData = $data;
            $reopenForm = true;
        } else {
            try {
                $fileMeta = cdoc_store_upload($fileField);
                cdoc_insert_record($obconn, $data, $fileMeta, (int) $createdBy, $userName);
                $_SESSION['success_message'] = 'Document published to the dealer portal successfully.';
                header('Location: documentation.php');
                exit;
            } catch (Throwable $e) {
                $error_message = 'Failed to publish document. Please try again.';
                $formData = $data;
                $reopenForm = true;
            }
        }
    }
}

$documents = cdoc_list($obconn, $canManageDocuments);
$nextDocumentNumber = $canAddDocument ? cdoc_next_document_number($obconn) : '';
$productGroups = $canAddDocument ? cdoc_product_groups($obconn) : [];
$prefillProductCode = cdoc_product_code_from_stored((string) ($formData['product_name'] ?? ''));
$prefillProductLabel = (string) ($formData['product_name'] ?? '');
if ($prefillProductCode !== '' && strpos($prefillProductLabel, ' - ') === false) {
    $prefillProduct = cdoc_product_by_code($obconn, $prefillProductCode);
    if ($prefillProduct !== null) {
        $prefillProductLabel = cdoc_product_display_label($prefillProduct);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet">
    <link href="css/complaint_buttons.css" rel="stylesheet">
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_form.css" rel="stylesheet">
    <link href="css/documentation.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="css/select2_change.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/cdoc_product_select2.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">

    <?php include 'sidebar.php'; ?>

    <div class="content">

        <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($success_message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="page-header">
            <div>
                <div class="page-subtitle">
                    View and download publicly approved product documents. Authoring and version control remain in CDOC.
                </div>
            </div>
            <?php if ($canAddDocument): ?>
            <div class="header-btn-group">
                <button class="new-order-btn btn-complaint-primary" id="openDocumentForm" type="button" style="<?= $reopenForm ? 'display:none;' : '' ?>">
                    <i class="bi bi-plus-lg"></i> Publish Document
                </button>
                <button class="close-form-btn cancel-btn" id="closeDocumentForm" type="button" style="<?= $reopenForm ? '' : 'display:none;' ?>">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($canAddDocument): ?>
        <div class="complaint-form-card" id="documentFormCard" style="<?= $reopenForm ? 'display:block;' : 'display:none;' ?>">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-file-earmark-text"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Publish Approved Document</h2>
                        <p class="complaint-form-header__subtitle">
                            Publish a copy of an already approved CDOC document. Only Public + Approved documents are visible to dealers.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" id="documentForm" enctype="multipart/form-data" novalidate>
                <div class="complaint-form-body">
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h2 class="complaint-form-section__title">Document Details</h2>
                                <p class="complaint-form-section__hint">The CDOC document number is generated automatically.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label">CDOC Document Number</label>
                                <input type="text" class="form-control" name="document_number" value="<?= htmlspecialchars($nextDocumentNumber) ?>" readonly>
                            </div>
                            <div class="col-md-8 form-group">
                                <label class="form-label">Document Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" maxlength="200" required
                                    value="<?= htmlspecialchars($formData['title'] ?? '') ?>" placeholder="e.g. EG Series Operation Manual">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Product Group</label>
                                <select class="form-control" name="product_group" id="productGroupSelect">
                                    <option value="">Select</option>
                                    <?php
                                    $selectedGroup = (string) ($formData['product_group'] ?? '');
                                    if ($selectedGroup !== '' && !isset($productGroups[$selectedGroup])) {
                                        $productGroups[$selectedGroup] = $selectedGroup;
                                    }
                                    foreach ($productGroups as $value => $label):
                                    ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= ($selectedGroup === $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="productNameSelect">Product Name <span class="text-danger">*</span></label>
                                <select class="form-control" name="product_name" id="productNameSelect" data-placeholder="Select a product" required>
                                    <option value=""></option>
                                </select>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label">Document Type</label>
                                <select class="form-control" name="document_type">
                                    <option value="">Select</option>
                                    <?php foreach (cdoc_document_type_options() as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= (($formData['document_type'] ?? '') === $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8 form-group">
                                <label class="form-label">Document File <span class="text-danger">*</span></label>
                                <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" required>
                                <div class="form-text">PDF, DOC, DOCX, XLS, XLSX, JPG, PNG. Maximum 10 MB.</div>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" maxlength="1000"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-outline-secondary" id="cancelDocumentForm">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" name="submit_documentation" class="btn btn-complaint-primary">
                        <i class="bi bi-upload"></i> Publish Document
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <div class="complaint-form-card show" id="documentTableCard" style="<?= $reopenForm ? 'display:none;' : '' ?>">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title"><?= $canManageDocuments ? 'Published Documents' : 'Available Documents' ?></h2>
                        <p class="complaint-form-header__subtitle">
                            Search, view, or download approved product documents.
                        </p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                    <table id="documentationTable" class="table table-hover booking-table w-100">
                        <thead>
                            <tr>
                                <th width="6%">#</th>
                                <th width="14%">CDOC No.</th>
                                <th width="24%">Title</th>
                                <th width="16%">Product</th>
                                <th width="14%">Type</th>
                                <th width="12%">Published</th>
                                <th width="14%">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $row): ?>
                            <?php
                                $docId = (int) $row['id'];
                                $encodedId = cdoc_encoded_id($docId);
                                $title = trim((string) ($row['title'] ?? ''));
                                $productLabel = trim(($row['product_name'] ?? '') . ' ' . ($row['product_group'] ?? ''));
                                if ($productLabel === '') {
                                    $productLabel = trim((string) ($row['product_group'] ?? ''));
                                }
                                $docType = trim((string) ($row['document_type'] ?? ''));
                            ?>
                            <tr>
                                <td><?= $docId ?></td>
                                <td><?= htmlspecialchars(cdoc_display_value($row['document_number'] ?? '')) ?></td>
                                <td>
                                    <a href="documentation_details.php?id=<?= $encodedId ?>" class="text-primary fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($title !== '' ? $title : 'Untitled document') ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($productLabel !== '' ? $productLabel : '-') ?></td>
                                <td>
                                    <span class="status-badge border border-dark">
                                        <?= htmlspecialchars($docType !== '' ? $docType : '-') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(cdoc_format_date($row['published_at'] ?? $row['created_at'] ?? null)) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="documentation_details.php?id=<?= $encodedId ?>" class="btn btn-sm btn-outline-dark" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="documentation_download.php?id=<?= $encodedId ?>&mode=download" class="btn btn-sm btn-outline-dark" title="Download">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <?php if ($canDeleteDocument): ?>
                                        <a href="delete_documentation.php?id=<?= $encodedId ?>"
                                            class="btn btn-sm btn-outline-dark"
                                            onclick="return confirm('Remove this document from the dealer portal?');" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    const openBtn = document.getElementById('openDocumentForm');
    const closeBtn = document.getElementById('closeDocumentForm');
    const cancelBtn = document.getElementById('cancelDocumentForm');
    const formCard = document.getElementById('documentFormCard');
    const tableCard = document.getElementById('documentTableCard');

    function showForm() {
        if (!formCard) return;
        formCard.style.display = 'block';
        if (tableCard) tableCard.style.display = 'none';
        if (openBtn) openBtn.style.display = 'none';
        if (closeBtn) closeBtn.style.display = '';
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) return;
        formCard.style.display = 'none';
        if (tableCard) tableCard.style.display = 'block';
        if (openBtn) openBtn.style.display = '';
        if (closeBtn) closeBtn.style.display = 'none';
    }

    if (openBtn) openBtn.addEventListener('click', showForm);
    if (closeBtn) closeBtn.addEventListener('click', hideForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

    $(document).ready(function () {
        $('#documentationTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: { emptyTable: 'No documents have been published yet.' }
        });

        initCdocProductSelect2('productNameSelect', 'productGroupSelect', {
            code: <?= json_encode($prefillProductCode) ?>,
            label: <?= json_encode($prefillProductLabel) ?>
        });
    });
})();
</script>
</body>
</html>
