<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_helpers.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = customer_get_by_id($obconn, $id);

if (!$record) {
    die('Customer not found.');
}

$addrLabel = customer_address_label($dpconn, (string) ($record['cust_addr'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Customer #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
                <div>
                    <a href="customers.php" class="btn btn-light border">Back to List</a>
                </div>
            </div>

            <div class="booking-card">
                
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Customer Code:</strong><br><?php echo htmlspecialchars((string) $record['cust_code']); ?></div>
                        <div class="col-md-6"><strong>Customer Name:</strong><br><?php echo htmlspecialchars((string) $record['cust_name']); ?></div>
                        <div class="col-md-12"><strong>Customer Address:</strong><br><?php echo htmlspecialchars($addrLabel); ?></div>
                        <div class="col-md-6"><strong>Created By:</strong><br><?php echo htmlspecialchars(rbac_display_value($record['created_by'] ?? null)); ?></div>
                        <div class="col-md-6"><strong>Updated By:</strong><br><?php echo htmlspecialchars(rbac_display_value($record['updated_by'] ?? null)); ?></div>
                        <div class="col-md-6"><strong>Created At:</strong><br><?php echo htmlspecialchars(rbac_format_datetime($record['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-6"><strong>Updated At:</strong><br><?php echo htmlspecialchars(rbac_format_datetime($record['updated_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
