<?php
session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/user_helpers.php';
require_once 'includes/record_details_layout.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid user record.');
}

$record = user_get_by_id($obconn, $id);

if (!$record) {
    die('User not found.');
}

$roleLabel = user_role_label($obconn, $record['role']);
$displayName = user_display_value($record['name']);
$pageTitle = $displayName !== '-' ? $displayName : user_display_value($record['username']);
$showSalesCoordinator = user_role_requires_sales_coordinator((int) $record['role'])
    || !empty($record['sales_coordinator_id']);
$salesCoordinatorLabel = user_sales_coordinator_display_name(
    $obconn,
    isset($record['sales_coordinator_id']) ? (int) $record['sales_coordinator_id'] : null
);
$encodedId = base64_encode((string) $record['id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
            <?php if (isset($_SESSION['success_message'])) { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); } ?>
            <?php
            record_details_page_header(
                'User Details',
                $pageTitle,
                'users.php',
                'Back to User List',
                'bi-person-badge',
                [
                    record_details_id_chip((int) $record['id']),
                    '<span class="record-details-chip">' . record_details_escape($roleLabel) . '</span>',
                ]
            );
            ?>
            
            <?php
            record_details_card_start();

            record_details_section_start(1, 'Account Information', 'Login credentials and profile details');
            record_details_field('Role', $roleLabel);
            record_details_field('Username', user_display_value($record['username']));
            record_details_field('Name', user_display_value($record['name']));
            record_details_field('Email', user_display_value($record['email']));
            record_details_field('Mobile Number', user_display_value($record['mobile_number']));
            if ($showSalesCoordinator) {
                record_details_field('Sales Coordinator', $salesCoordinatorLabel);
            }
            record_details_field('Created By', user_display_value($record['created_by']));
            record_details_section_end();

            record_details_section_start(2, 'Activity', 'Account lifecycle and login history', true);
            record_details_field('Created At', user_format_datetime($record['created_at']));
            record_details_field('Updated At', user_format_datetime($record['updated_at']));
            record_details_field('Last Login', user_format_datetime($record['last_login_at']));
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>