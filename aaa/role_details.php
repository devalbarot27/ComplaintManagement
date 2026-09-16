<?php
session_start();
include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/role_helpers.php';
require_once 'includes/record_details_layout.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid role record.');
}

$record = role_get_by_id($obconn, $id);

if (!$record) {
    die('Role not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
                'Role Details',
                (string) $record['role_name'],
                'roles.php',
                'Back to Role List',
                'bi-shield-lock',
                [
                    record_details_id_chip((int) $record['id']),
                    rbac_status_badge((string) ($record['status'] ?? '')),
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Role Information', 'Name, description, and status');
            record_details_field('Role Name', (string) $record['role_name'], 'col-md-6');
            record_details_field('Status', rbac_status_badge((string) ($record['status'] ?? '')), 'col-md-6', false, true);
            record_details_field('Description', rbac_display_value($record['description']), 'col-12', true);
            record_details_section_end();

            record_details_section_start(2, 'Audit Trail', 'Creation and update history', true);
            record_details_field('Created By', rbac_display_value($record['created_by']), 'col-md-4');
            record_details_field('Created At', rbac_format_datetime($record['created_at']), 'col-md-4');
            record_details_field('Updated At', rbac_format_datetime($record['updated_at']), 'col-md-4');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>
