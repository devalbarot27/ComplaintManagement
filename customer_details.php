<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/customer_helpers.php';

require_system_admin($obconn);

$cuno = trim((string) base64_decode($_GET['id'] ?? '', true));

if ($cuno === '') {
    die('Invalid record.');
}

$record = customer_get_by_cuno($obconn, $cuno);

if (!$record) {
    die('Customer not found.');
}

$addrLabel = customer_address_label($obconn, trim((string) ($record['adr_code'] ?? '')));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details - <?php echo htmlspecialchars(trim((string) $record['cuno']), ENT_QUOTES, 'UTF-8'); ?></title>
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
                    <h5 class="mb-1">Customer - <?php echo htmlspecialchars(trim((string) $record['cuno']), ENT_QUOTES, 'UTF-8'); ?></h5>
                </div>
                <div>
                    <a href="customers.php" class="btn btn-light border">Back to List</a>
                </div>
            </div>

            <div class="booking-card">
                <div class="p-3">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Customer Code:</strong><br><?php echo htmlspecialchars(trim((string) $record['cuno'])); ?></div>
                        <div class="col-md-6"><strong>Customer Name:</strong><br><?php echo htmlspecialchars(trim((string) $record['cuname'])); ?></div>
                        <div class="col-md-12"><strong>Customer Address:</strong><br><?php echo htmlspecialchars($addrLabel); ?></div>
                        <div class="col-md-6"><strong>City:</strong><br><?php echo htmlspecialchars(rbac_display_value(trim((string) ($record['city'] ?? '')))); ?></div>
                        <div class="col-md-6"><strong>State:</strong><br><?php echo htmlspecialchars(rbac_display_value(trim((string) ($record['state'] ?? '')))); ?></div>
                        <div class="col-md-6"><strong>Country:</strong><br><?php echo htmlspecialchars(rbac_display_value(trim((string) ($record['country'] ?? '')))); ?></div>
                        <div class="col-md-6"><strong>Status:</strong><br><?php echo htmlspecialchars((string) ($record['status'] ?? '-')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
