<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/outlook_email_helpers.php';
require_once 'includes/record_details_layout.php';

require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = outlook_email_get_by_id($obconn, $id);

if (!$record) {
    die('Outlook email not found.');
}

$userLabel = trim((string) ($record['user_name'] ?? ''));
if ($userLabel === '') {
    $userLabel = trim((string) ($record['username'] ?? ''));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Outlook Email Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
                'Outlook Email',
                (string) ($record['outlook_email'] ?? ''),
                'outlook_emails.php',
                'Back to List',
                'bi-envelope',
                [
                    record_details_id_chip((int) $record['id']),
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Email Details', 'User and Outlook email address');
            record_details_field('User', $userLabel, 'col-md-4');
            record_details_field('Username', rbac_display_value($record['user_name'] ?? ''), 'col-md-4');
            record_details_field('Outlook Email', rbac_display_value($record['outlook_email'] ?? ''), 'col-md-4');
            record_details_section_end();

            record_details_section_start(2, 'Audit Trail', 'Creation and update history', true);
            record_details_field('Created By', rbac_display_value($record['created_by_name'] ?? ''), 'col-md-6');
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), 'col-md-6');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>
