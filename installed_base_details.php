<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/installed_base_helpers.php';
require_once 'includes/service_log_helpers.php';
require_once 'includes/after_market_access_helpers.php';

$active_menu = 'installed_base';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid installed base record.');
}

if (!after_market_user_can_access_record($obconn, 'installed_base', $id)) {
    die('Installed base record not found.');
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

$serviceLogs = service_log_list_for_installed_base($obconn, $id);
$serviceLogPermissions = service_log_action_permissions($obconn);
$canViewServiceLogDetails = $serviceLogPermissions['view'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installed Base Details #<?php echo (int) $record['id']; ?></title>

    <?php include 'header_css.php'; ?>

    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
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
                        <?php echo htmlspecialchars(installed_base_display_value($record['machine_model_code'] ?? null)); ?> - <?php echo htmlspecialchars(installed_base_display_value($record['machine_model'])); ?>
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

                    <div class="col-md-6">
                        <strong>Street 1:</strong>
                        <?php echo nl2br(htmlspecialchars(installed_base_address_display_value($record, 'street_1'))); ?>
                    </div>

                    <div class="col-md-6">
                        <strong>Street 2:</strong>
                        <?php echo htmlspecialchars(installed_base_address_display_value($record, 'street_2')); ?>
                    </div>

                    <div class="col-md-3">
                        <strong>Pincode:</strong>
                        <?php echo htmlspecialchars(installed_base_address_display_value($record, 'pincode')); ?>
                    </div>

                    <div class="col-md-3">
                        <strong>City:</strong>
                        <?php echo htmlspecialchars(installed_base_address_display_value($record, 'city')); ?>
                    </div>

                    <div class="col-md-3">
                        <strong>District:</strong>
                        <?php echo htmlspecialchars(installed_base_address_display_value($record, 'district')); ?>
                    </div>

                    <div class="col-md-3">
                        <strong>State:</strong>
                        <?php echo htmlspecialchars(installed_base_address_display_value($record, 'state')); ?>
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

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Service Log Capture Records</strong>
                </div>
                <div class="card-body p-0">
                    <?php if ($serviceLogs === []) { ?>
                    <p class="text-muted mb-0 p-3">No Service Log Capture records linked to this installed base yet.</p>
                    <?php } else { ?>
                        <div class="p-3">
                        <?php foreach ($serviceLogs as $serviceLogRecord) {
                            $partReplacements = service_log_part_replacements_for_service_log(
                                $obconn,
                                (int) $serviceLogRecord['id']
                            );
                            $serviceLogEmbeddedInInstalledBase = true;
                            $installedBaseRecord = $record;
                            include __DIR__ . '/includes/service_log_record_details_section.php';
                        } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>

            
        </div>
    </div>
</body>

</html>
