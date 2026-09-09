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

$sparePartsRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sparePartsRecord) {
    die('Spare parts record not found.');
}

$sparePartsItems = spare_parts_items_for_consumption($obconn, $id);
$sparePartsHideRecordHeader = true;
$itemTotals = spare_parts_items_totals($sparePartsItems);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spare Parts Details #<?php echo (int) $sparePartsRecord['id']; ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">Spare Parts Consumption #<?php echo (int) $sparePartsRecord['id']; ?></h5>
                    
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="spare_parts_consumption.php" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Back to Spare Parts Consumption
                    </a>
                </div>
            </div>

            <?php include __DIR__ . '/includes/spare_parts_record_details_section.php'; ?>
        </div>
    </div>
</body>

</html>
