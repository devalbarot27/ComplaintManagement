<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/spare_parts_helpers.php';
require_once 'includes/after_market_access_helpers.php';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid spare parts record.');
}

if (!after_market_user_can_access_record($obconn, 'spare_parts_consumption', $id)) {
    die('Spare parts record not found.');
}

$stmt = $obconn->prepare('
    SELECT sp.*, ib.customer_name, ib.machine_model, ib.order_id
    FROM spare_parts_consumption sp
    LEFT JOIN installed_base ib
        ON ib.id = sp.installed_base_id
       AND ib.deleted_at IS NULL
    WHERE sp.id = :id
      AND sp.deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die('Spare parts record not found.');
}

$installedBaseLink = base64_encode((string) $record['installed_base_id']);
$serviceLogLink = $record['service_log_id']
    ? base64_encode((string) $record['service_log_id'])
    : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spare Parts Details #<?php echo (int) $record['id']; ?></title>
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
                    <h5 class="mb-1">Spare Parts #<?php echo (int) $record['id']; ?></h5>
                    <span class="badge border border-dark text-dark">
                        <?php echo htmlspecialchars(spare_parts_display_value($record['reason'])); ?>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="spare_parts_consumption.php" class="btn btn-light border">Back to Spare Parts Consumption</a>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Machine & Service Link</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Machine (Installed Base):</strong>
                        <a href="installed_base_details.php?id=<?php echo htmlspecialchars($installedBaseLink); ?>">
                            #<?php echo (int) $record['installed_base_id']; ?>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <strong>Order ID:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['order_id'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Fab Number:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['fab_number'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Serial Number:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['serial_number'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Machine Model:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['machine_model'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Customer Name:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['customer_name'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Service Record:</strong>
                        <?php if ($serviceLogLink) { ?>
                        <a href="service_log_details.php?id=<?php echo htmlspecialchars($serviceLogLink); ?>">
                            #<?php echo (int) $record['service_log_id']; ?>
                        </a>
                        <?php } else { ?>
                        -
                        <?php } ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Consumption Details</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Consumption Date:</strong>
                        <?php echo spare_parts_format_date($record['consumption_date']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Warranty / Chargeable:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['warranty_chargeable'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Reason:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['reason'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Spare Kit Number:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['spare_kit_number'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Quantity:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['quantity'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Order Value:</strong>
                        <?php echo spare_parts_format_currency($record['order_value']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Running Hours:</strong>
                        <?php echo htmlspecialchars(spare_parts_display_value($record['running_hours'])); ?>
                    </div>
                    <div class="col-md-12">
                        <strong>Remarks:</strong>
                        <?php
                        $remarks = spare_parts_display_value($record['remarks']);
                        echo $remarks === '-' ? '-' : nl2br(htmlspecialchars($remarks));
                        ?>
                    </div>
                </div>
            </div>

            
        </div>
    </div>
</body>

</html>
