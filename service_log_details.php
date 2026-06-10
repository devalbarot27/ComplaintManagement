<?php
session_start();

include 'pdo_obconn.php';
include 'includes/service_log_helpers.php';

$active_menu = 'service_log';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid service log record.');
}

$stmt = $obconn->prepare('
    SELECT sl.*, ib.customer_name, ib.dealer_name
    FROM service_logs sl
    LEFT JOIN installed_base ib
        ON ib.id = sl.installed_base_id
       AND ib.deleted_at IS NULL
    WHERE sl.id = :id
      AND sl.deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die('Service log record not found.');
}

$installedBaseLink = base64_encode((string) $record['installed_base_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Log Details #<?php echo (int) $record['id']; ?></title>
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
                    <h5 class="mb-1">Service Log #<?php echo (int) $record['id']; ?></h5>
                    <span class="badge border border-dark text-dark">
                        <?php echo htmlspecialchars(service_log_display_value($record['warranty_chargeable'])); ?>
                    </span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="service_log.php" class="btn btn-light border">Back to Service Log Capture</a>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Machine & Order Details</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Installed Base:</strong>
                        <a href="installed_base_details.php?id=<?php echo htmlspecialchars($installedBaseLink); ?>">
                            #<?php echo (int) $record['installed_base_id']; ?>
                        </a>
                    </div>
                    <div class="col-md-4">
                        <strong>Order ID:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['order_id'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Serial Number:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['serial_number'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Machine Model:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['machine_model'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Customer Name:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['customer_name'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Dealer Name:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['dealer_name'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Warranty / Chargeable:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['warranty_chargeable'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Complaint Date:</strong>
                        <?php echo service_log_format_date($record['complaint_date']); ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Issue & Service Details</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-12">
                        <strong>Issue Description:</strong>
                        <?php echo nl2br(htmlspecialchars(service_log_display_value($record['issue_description']))); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Engineer Name:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['engineer_name'])); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Visit Date:</strong>
                        <?php echo service_log_format_date($record['visit_date']); ?>
                    </div>
                    <div class="col-md-4">
                        <strong>Closure Date:</strong>
                        <?php echo service_log_format_date($record['closure_date']); ?>
                    </div>
                    <div class="col-md-12">
                        <strong>Action Taken:</strong>
                        <?php echo nl2br(htmlspecialchars(service_log_display_value($record['action_taken']))); ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white"><strong>Usage & Feedback</strong></div>
                <div class="card-body row g-3">
                    <div class="col-md-3">
                        <strong>Part Replaced:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['part_replaced'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Running Hours:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['running_hours'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Loaded Hours:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['loaded_hours'])); ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Customer Feedback:</strong>
                        <?php echo htmlspecialchars(service_log_display_value($record['customer_feedback'])); ?>
                    </div>
                    <div class="col-md-12">
                        <strong>Remarks:</strong>
                        <?php
                        $remarks = service_log_display_value($record['remarks']);
                        echo $remarks === '-' ? '-' : nl2br(htmlspecialchars($remarks));
                        ?>
                    </div>
                </div>
            </div>

            
        </div>
    </div>
</body>

</html>
