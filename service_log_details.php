<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/installed_base_helpers.php';
include 'includes/service_log_helpers.php';
require_once 'includes/service_log_draft_helpers.php';
require_once 'includes/after_market_access_helpers.php';
require_once 'includes/complaint_service_log_helpers.php';
require_once 'includes/complaint_category_helpers.php';
require_once 'includes/complaint_address_helpers.php';

$active_menu = 'service_log';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid service log record.');
}

if (!after_market_user_can_access_record($obconn, 'service_logs', $id)) {
    die('Service log record not found.');
}

$stmt = $obconn->prepare('
    SELECT sl.*
    FROM service_logs sl
    WHERE sl.id = :id
      AND sl.deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$record = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$record) {
    die('Service log record not found.');
}

$installedBaseRecord = null;
$installedBaseId = (int) ($record['installed_base_id'] ?? 0);

if ($installedBaseId > 0) {
    $ibStmt = $obconn->prepare('
        SELECT *
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $ibStmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $ibStmt->execute();
    $installedBaseRecord = $ibStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$partReplacements = service_log_part_replacements_for_service_log($obconn, $id);
$serviceLogRecord = $record;
$serviceLogHideRecordHeader = true;
$canViewServiceLogDetails = false;
$serviceLogEmbeddedInInstalledBase = false;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Log Details #<?php echo (int) $record['id']; ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                        <h5 class="mb-0">Service Log Capture #<?php echo (int) $record['id']; ?></h5>
                        <?php if (service_log_is_draft_value($record['is_draft'] ?? 0)) { ?>
                        <?php echo service_log_draft_badge_html(); ?>
                        <?php } ?>
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="service_log.php" class="btn btn-light border">Back to Service Log Capture</a>
                </div>
            </div>

            
            <?php include __DIR__ . '/includes/service_log_record_details_section.php'; ?>
          
        </div>
    </div>
</body>

</html>