<?php
session_start();

include 'pdo_obconn.php';
include 'includes/installed_base_helpers.php';

$active_menu = 'installed_base';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid installed base record.');
}

$stmt = $obconn->prepare('
    SELECT *
    FROM installed_base
    WHERE id = :id
      AND deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die('Installed base record not found.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installed Base Details #<?php echo (int) $record['id']; ?></title>

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
                    <h5 class="mb-1">Installed Base #<?php echo (int) $record['id']; ?></h5>
                    
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="installed_base.php" class="btn btn-light border">
                        Back to Installed Base Capture
                    </a>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Order & Machine Details</strong>
                </div>

                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <strong>Order ID:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['order_id'])); ?>
                    </div>

                    <div class="col-md-4">
                        <strong>Fab Number:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['fab_number'])); ?>
                    </div>

                    <div class="col-md-4">
                        <strong>Machine Model:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['machine_model'])); ?>
                    </div>

                    <div class="col-md-4">
                        <strong>Invoice Date:</strong>
                        <?php echo installed_base_format_date($record['invoice_date']); ?>
                    </div>

                    <div class="col-md-4">
                        <strong>Commissioning Date:</strong>
                        <?php echo installed_base_format_date($record['commissioning_date']); ?>
                    </div>

                    <div class="col-md-4">
                        <strong>Running Hours:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['running_hours'])); ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Customer Details</strong>
                </div>

                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <strong>Customer Name:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['customer_name'])); ?>
                    </div>

                    <div class="col-md-6">
                        <strong>Dealer Name:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['dealer_name'])); ?>
                    </div>

                    <div class="col-md-12">
                        <strong>Address:</strong>
                        <?php echo nl2br(htmlspecialchars(installed_base_display_value($record['address']))); ?>
                    </div>

                    <div class="col-md-6">
                        <strong>Mobile:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['mobile'])); ?>
                    </div>

                    <div class="col-md-6">
                        <strong>Email:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['email'])); ?>
                    </div>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Business Details</strong>
                </div>

                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <strong>Industry Segment:</strong>
                        <?php echo htmlspecialchars(installed_base_display_value($record['industry_segment'])); ?>
                    </div>

                    <div class="col-md-12">
                        <strong>Remarks:</strong>
                        <?php
                        $remarks = installed_base_display_value($record['remarks']);
                        echo $remarks === '-'
                            ? '-'
                            : nl2br(htmlspecialchars($remarks));
                        ?>
                    </div>
                </div>
            </div>

            
        </div>
    </div>
</body>

</html>
