<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_approval_configuration_helpers.php';
require_once 'includes/record_details_layout.php';

require_system_admin($obconn);
user_approval_config_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = user_approval_config_get_by_id($obconn, $id);

if (!$record) {
    die('User approval configuration not found.');
}

$userLabel = user_approval_config_user_label($record);
$moduleLabel = user_approval_config_module_label((string) ($record['module_slug'] ?? ''));
$level1 = user_approval_config_bool_from_value($record['level_1_approval'] ?? false);
$level2 = user_approval_config_bool_from_value($record['level_2_approval'] ?? false);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approval Configuration #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
                'User Approval Configuration',
                $userLabel,
                'user_approval_configurations.php',
                'Back to List',
                'bi-person-check',
                [
                    record_details_id_chip((int) $record['id']),
                    '<span class="record-details-chip">' . record_details_escape($moduleLabel) . '</span>',
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Configuration', 'User, module, and approval levels');
            record_details_field('User', $userLabel, 'col-md-6');
            record_details_field('Module', $moduleLabel, 'col-md-6');
            record_details_field('Level 1 Approval', user_approval_config_yes_no($level1), 'col-md-6');
            record_details_field(
                'Level 2 Approval',
                ((string) ($record['module_slug'] ?? '') === user_approval_config_module_service())
                    ? '-'
                    : user_approval_config_yes_no($level2),
                'col-md-6'
            );
            record_details_section_end();

            record_details_section_start(2, 'Audit Trail', 'Creation and update history', true);
            record_details_field('Created By', rbac_display_value($record['created_by'] ?? ''), 'col-md-6');
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), 'col-md-6');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>
