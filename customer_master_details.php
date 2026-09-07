<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_master_helpers.php';

require_system_admin($obconn);
customer_master_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = customer_master_get_by_id($obconn, $id);

if (!$record) {
    die('Customer not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Master Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
                    <a href="customer_master.php" class="btn btn-light border">Back to List</a>
                </div>
            </div>

            <div class="booking-card">
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Customer Name:</strong><br><?php echo htmlspecialchars((string) ($record['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Email:</strong><br><?php echo htmlspecialchars((string) ($record['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Mobile:</strong><br><?php echo htmlspecialchars((string) ($record['mobile'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Pincode:</strong><br><?php echo htmlspecialchars((string) ($record['pincode'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Street 1:</strong><br><?php echo htmlspecialchars((string) ($record['street_1'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Street 2:</strong><br><?php echo htmlspecialchars((string) ($record['street_2'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>City:</strong><br><?php echo htmlspecialchars((string) ($record['city'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>District:</strong><br><?php echo htmlspecialchars((string) ($record['district'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>State:</strong><br><?php echo htmlspecialchars((string) ($record['state'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Created By:</strong><br><?php echo htmlspecialchars(customer_master_created_by_label($record), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col-md-4"><strong>Created At:</strong><br><?php echo htmlspecialchars(rbac_format_datetime($record['created_at'] ?? null), ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
