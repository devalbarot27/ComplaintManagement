<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/cdoc_helpers.php';
require_once 'includes/record_details_layout.php';

cdoc_ensure_schema($obconn);

$id = cdoc_decoded_id($_GET['id'] ?? '');
if ($id <= 0) {
    die('Invalid document.');
}

$document = cdoc_find_by_id($obconn, $id);
$cdocPermissions = cdoc_action_permissions($obconn);

if (!$document || !cdoc_user_can_access_document($document, $cdocPermissions)) {
    die('Document not found or is not available.');
}

$canEditDocument = !empty($cdocPermissions['edit']);
$encodedId = cdoc_encoded_id($id);
$success_message = '';
$error_message = '';
$formData = $document;
$showEditForm = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_documentation_update'])) {
    if (!$canEditDocument) {
        $error_message = 'Access denied. You do not have permission to update documents.';
    } else {
        $data = cdoc_apply_product_master($obconn, cdoc_from_post($_POST, $document));
        $fileField = $_FILES['document_file'] ?? null;
        $hasNewFile = $fileField !== null && isset($fileField['error']) && (int) $fileField['error'] !== UPLOAD_ERR_NO_FILE;
        $validationError = cdoc_validate($data, false, $hasNewFile ? $fileField : null, $obconn);

        if ($validationError !== null) {
            $error_message = $validationError;
            $formData = array_merge($document, $data);
            $showEditForm = true;
        } else {
            try {
                $previousStored = (string) ($document['stored_filename'] ?? '');
                $fileMeta = null;
                if ($hasNewFile) {
                    $fileMeta = cdoc_store_upload($fileField);
                }

                if (cdoc_update_record($obconn, $id, $data, $fileMeta, current_assignee_name())) {
                    if ($fileMeta !== null && $previousStored !== '' && $previousStored !== $fileMeta['stored_filename']) {
                        cdoc_delete_stored_file($previousStored);
                    }
                    $_SESSION['success_message'] = 'Document updated successfully.';
                    header('Location: documentation_details.php?id=' . $encodedId);
                    exit;
                }

                $error_message = 'Failed to update document.';
                $formData = array_merge($document, $data);
                $showEditForm = true;
            } catch (Throwable $e) {
                $error_message = 'Failed to update document. Please try again.';
                $formData = array_merge($document, $data);
                $showEditForm = true;
            }
        }
    }
}

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$document = cdoc_find_by_id($obconn, $id) ?? $document;
$previewUrl = 'documentation_download.php?id=' . $encodedId . '&mode=inline';
$downloadUrl = 'documentation_download.php?id=' . $encodedId . '&mode=download';
$canPreview = cdoc_is_previewable($document);
$ext = strtolower((string) ($document['file_extension'] ?? ''));
$productGroups = $canEditDocument ? cdoc_product_groups($obconn) : [];
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
    <title><?= htmlspecialchars((string) $document['title']) ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/record_details.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/documentation.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="css/select2_change.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="js/cdoc_product_select2.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">
    <?php include 'sidebar.php'; ?>

    <div class="content">
        <?php if ($success_message !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($success_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if ($error_message !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php
        record_details_page_header(
            'Documentation',
            (string) $document['title'],
            'documentation.php',
            'Back to List',
            'bi-file-earmark-text',
            [
                record_details_id_chip((int) $document['id']),
            ]
        );
        ?>

        <div class="d-flex flex-wrap gap-2 mb-3">
            <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn btn-complaint-primary">
                <i class="bi bi-download"></i> Download
            </a>
            <?php if ($canPreview): ?>
            <a href="<?= htmlspecialchars($previewUrl) ?>" class="btn btn-outline-dark" target="_blank" rel="noopener">
                <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
            </a>
            <?php endif; ?>
            <?php if ($canEditDocument): ?>
            <button type="button" class="btn btn-outline-dark" id="toggleDocumentEdit">
                <i class="bi bi-pencil"></i> <?= $showEditForm ? 'Hide Edit' : 'Edit Details' ?>
            </button>
            <?php endif; ?>
        </div>

        <?php
        record_details_card_start();
        record_details_section_start(1, 'Document Information', 'Approved product document published from CDOC');
        record_details_field('CDOC Document Number', cdoc_display_value($document['document_number'] ?? ''), 'col-md-4');
        record_details_field('Document Type', cdoc_display_value($document['document_type'] ?? ''), 'col-md-4');
        record_details_field('Product Group', cdoc_display_value($document['product_group'] ?? ''), 'col-md-4');
        record_details_field('Product Name', cdoc_display_value($document['product_name'] ?? ''), 'col-md-4');
        record_details_field('Published', cdoc_format_date($document['published_at'] ?? $document['created_at'] ?? null), 'col-md-4');
        record_details_field('File Name', cdoc_display_value($document['original_filename'] ?? ''), 'col-md-4');
        record_details_field('File Size', cdoc_format_file_size($document['file_size'] ?? 0), 'col-md-4');
        record_details_field('File Type', strtoupper(cdoc_display_value($document['file_extension'] ?? '')), 'col-md-4');
        record_details_field('Description', cdoc_display_value($document['description'] ?? ''), 'col-12', true);
        record_details_section_end();
        record_details_card_end();
        ?>

        <div class="card mb-3">
            <div class="card-header"><strong>Document Preview</strong></div>
            <div class="card-body">
                <?php if ($ext === 'pdf'): ?>
                <iframe class="cdoc-preview-frame" src="<?= htmlspecialchars($previewUrl) ?>" title="Document preview"></iframe>
                <?php elseif (in_array($ext, ['jpg', 'jpeg', 'png'], true)): ?>
                <div class="cdoc-preview-image-wrap">
                    <img src="<?= htmlspecialchars($previewUrl) ?>" alt="<?= htmlspecialchars((string) $document['title']) ?>">
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">Preview is not available for this file type. Use Download to open the document.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($canEditDocument): ?>
        <div class="complaint-form-card" id="documentEditCard" style="<?= $showEditForm ? 'display:block;' : 'display:none;' ?>">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-pencil"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Update Published Document</h2>
                        <p class="complaint-form-header__subtitle">Replace the published copy if CDOC has a new approved version.</p>
                    </div>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data" novalidate>
                <div class="complaint-form-body">
                    <section class="complaint-form-section mb-0">
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label">CDOC Document Number</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($document['document_number'] ?? '')) ?>" readonly>
                            </div>
                            <div class="col-md-8 form-group">
                                <label class="form-label">Document Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" maxlength="200" required
                                    value="<?= htmlspecialchars((string) ($formData['title'] ?? '')) ?>">
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
                                <label class="form-label">Replace File</label>
                                <input type="file" class="form-control" name="document_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                                <div class="form-text">Leave empty to keep the current file. Maximum 10 MB.</div>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="2" maxlength="1000"><?= htmlspecialchars((string) ($formData['description'] ?? '')) ?></textarea>
                            </div>
                        </div>
                    </section>
                </div>
                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="submit" name="submit_documentation_update" class="btn btn-complaint-primary">
                        <i class="bi bi-check-lg"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
        <script>
        (function () {
            const toggleBtn = document.getElementById('toggleDocumentEdit');
            const editCard = document.getElementById('documentEditCard');
            if (!toggleBtn || !editCard) return;
            toggleBtn.addEventListener('click', function () {
                const isHidden = editCard.style.display === 'none' || editCard.style.display === '';
                editCard.style.display = isHidden ? 'block' : 'none';
                toggleBtn.innerHTML = isHidden
                    ? '<i class="bi bi-pencil"></i> Hide Edit'
                    : '<i class="bi bi-pencil"></i> Edit Details';
                if (isHidden) {
                    editCard.scrollIntoView({ behavior: 'smooth' });
                }
            });

            if (typeof $ !== 'undefined') {
                $(document).ready(function () {
                    initCdocProductSelect2('productNameSelect', 'productGroupSelect', {
                        code: <?= json_encode($prefillProductCode) ?>,
                        label: <?= json_encode($prefillProductLabel) ?>
                    });
                });
            }
        })();
        </script>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
