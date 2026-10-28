<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/help_support_helpers.php';
require_once 'includes/record_details_layout.php';

help_support_ensure_schema($obconn);
help_support_require_page_access($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = help_support_get_by_id($obconn, $id);

if (!$record) {
    die('Help & Support item not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Help &amp; Support Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/record_details.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php
            record_details_page_header(
                'Help & Support',
                (string) ($record['title'] ?? ''),
                'help_support.php',
                'Back to List',
                'bi-question-circle',
                [
                    record_details_id_chip((int) $record['id']),
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Item Details', 'Category, description, quantity, and resource');
            record_details_field('Category', help_support_category_label((string) ($record['category'] ?? '')), 'col-md-4');
            record_details_field('Title', rbac_display_value($record['title'] ?? ''), 'col-md-5');
            record_details_field('Qty', (string) (int) ($record['qty'] ?? 0), 'col-md-3');
            record_details_field('Status', help_support_status_label((string) ($record['approval_status'] ?? 'pending')), 'col-md-4');
            $approvalRemarks = trim((string) ($record['approval_remarks'] ?? ''));
            if ($approvalRemarks !== '') {
                $remarksLabel = (string) ($record['approval_status'] ?? '') === 'approved'
                    ? 'Approval Remarks'
                    : 'Reject Remarks';
                record_details_field($remarksLabel, $approvalRemarks, 'col-12', true);
            }
            if (help_support_can_view_all()) {
                record_details_field('Added By', help_support_added_by_label($record), 'col-md-4');
            }
            record_details_field('Description', (string) ($record['description'] ?? ''), 'col-12', true);
            record_details_field('Attachment / URL', help_support_resource_html($record), 'col-12', false, true);
            record_details_section_end();

            record_details_section_start(2, 'Audit Trail', 'Creation and update history', true);
            record_details_field('Created By', help_support_added_by_label($record), 'col-md-6');
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), 'col-md-6');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>
