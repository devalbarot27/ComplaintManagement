<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/installed_base_helpers.php';
require_once 'includes/service_log_helpers.php';
require_once 'includes/service_log_draft_helpers.php';
require_once 'includes/spare_parts_helpers.php';
require_once 'includes/after_market_access_helpers.php';
require_once 'includes/complaint_service_log_helpers.php';
require_once 'includes/complaint_category_helpers.php';
require_once 'includes/complaint_address_helpers.php';

$active_menu = 'installed_base';

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid installed base record.');
}

if (!after_market_user_can_access_record($obconn, 'installed_base', $id)) {
    die('Installed base record not found.');
}

$stmt = $obconn->prepare('
    SELECT
        ib.*,
        COALESCE(
            NULLIF(TRIM(um.name), \'\'),
            NULLIF(TRIM(um.username), \'\'),
            NULLIF(TRIM(ib.username), \'\'),
            \'-\'
        ) AS added_by_name
    FROM installed_base ib
    LEFT JOIN user_master um
        ON um.id = ib.created_by
       AND um.deleted_at IS NULL
    WHERE ib.id = :id
      AND ib.deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$installedBaseRecord = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$installedBaseRecord) {
    die('Installed base record not found.');
}

$serviceLogs = service_log_list_for_installed_base($obconn, $id);
$sparePartsRecords = spare_parts_list_for_installed_base($obconn, $id);
$serviceLogPermissions = service_log_action_permissions($obconn);
$sparePartsPermissions = spare_parts_action_permissions($obconn);
$canViewServiceLogDetails = $serviceLogPermissions['view'];
$canEditServiceLog = $serviceLogPermissions['edit'];
$canViewSparePartsDetails = $sparePartsPermissions['view'];
$installedBaseHideRecordHeader = true;
$serviceLogCount = count($serviceLogs);
$serviceLogDraftCount = 0;
foreach ($serviceLogs as $serviceLogRow) {
    if (service_log_is_draft_value($serviceLogRow['is_draft'] ?? 0)) {
        $serviceLogDraftCount++;
    }
}
$sparePartsCount = count($sparePartsRecords);
$success_message = '';

if (isset($_GET['service_log_draft_updated']) && (string) $_GET['service_log_draft_updated'] === '1') {
    $success_message = 'Service log draft updated successfully.';
}

if (isset($_GET['service_log_updated']) && (string) $_GET['service_log_updated'] === '1') {
    $success_message = 'Service log updated successfully.';
}

if (isset($_GET['service_log_added']) && (string) $_GET['service_log_added'] === '1') {
    $success_message = 'Service Log Capture added successfully.';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installed Base Details #<?php echo htmlspecialchars((string) (int) $installedBaseRecord['id'], ENT_QUOTES, 'UTF-8'); ?></title>

    <?php include 'header_css.php'; ?>

    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if ($success_message !== '') { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>

            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                <div>
                    <h5 class="mb-2">Installed Base #<?php echo htmlspecialchars((string) (int) $installedBaseRecord['id'], ENT_QUOTES, 'UTF-8'); ?></h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        
                        <?php if ($serviceLogCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo htmlspecialchars((string) (int) $serviceLogCount, ENT_QUOTES, 'UTF-8'); ?>
                            service log<?php echo ((int) $serviceLogCount === 1) ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($serviceLogDraftCount > 0) { ?>
                        <span class="badge service-log-draft-badge">
                            <?php echo htmlspecialchars((string) (int) $serviceLogDraftCount, ENT_QUOTES, 'UTF-8'); ?>
                            draft<?php echo ((int) $serviceLogDraftCount === 1) ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($sparePartsCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary">
                            <?php echo htmlspecialchars((string) (int) $sparePartsCount, ENT_QUOTES, 'UTF-8'); ?>
                            spare parts record<?php echo ((int) $sparePartsCount === 1) ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="installed_base.php" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i> Back to Installed Base Capture
                    </a>
                </div>
            </div>

            <?php include __DIR__ . '/includes/installed_base_record_details_section.php'; ?>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white d-flex  flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-clipboard-pulse text-secondary"></i>
                        <strong>Service Log Capture Records</strong>
                    </div>
                    <div class="d-flex justify-content-end align-items-center gap-2 flex-grow-1">
                    <?php if ($serviceLogDraftCount > 0) { ?>
                    <span class="badge service-log-draft-badge">
                        <?php echo htmlspecialchars((string) (int) $serviceLogDraftCount, ENT_QUOTES, 'UTF-8'); ?>
                        draft<?php echo ((int) $serviceLogDraftCount === 1) ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                    <?php if ($serviceLogCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo htmlspecialchars((string) (int) $serviceLogCount, ENT_QUOTES, 'UTF-8'); ?>
                        record<?php echo ((int) $serviceLogCount === 1) ? '' : 's'; ?>
                    </span>
                 
                    <?php } ?>
                    </div>
                </div>

                <div class="card-body complaint-form-body px-3 pt-3 pb-3">
                    <?php if ($serviceLogs === []) { ?>
                    <div class="border rounded p-4 bg-white text-center text-muted">
                        <i class="bi bi-clipboard-x fs-4 d-block mb-2"></i>
                        No Service Log Capture records linked to this installed base yet.
                    </div>
                    <?php } else { ?>
                        <?php
                        $serviceLogRecordTotal = count($serviceLogs);
                        $serviceLogRecordNumber = 0;
                        foreach ($serviceLogs as $serviceLogRecord) {
                            $serviceLogRecordNumber++;
                            $partReplacements = service_log_part_replacements_for_service_log(
                                $obconn,
                                (int) $serviceLogRecord['id']
                            );
                            $serviceLogEmbeddedInInstalledBase = true;
                            $isLastServiceLogRecord = $serviceLogRecordNumber === $serviceLogRecordTotal;
                            include __DIR__ . '/includes/service_log_record_details_section.php';
                        }
                        ?>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-gear text-secondary"></i>
                        <strong>Spare Parts Consumption Records</strong>
                    </div>
                    <?php if ($sparePartsCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo htmlspecialchars((string) (int) $sparePartsCount, ENT_QUOTES, 'UTF-8'); ?>
                        record<?php echo ((int) $sparePartsCount === 1) ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>

                <div class="card-body complaint-form-body px-3 pt-3 pb-3">
                    <?php if ($sparePartsRecords === []) { ?>
                    <div class="border rounded p-4 bg-white text-center text-muted">
                        <i class="bi bi-inbox fs-4 d-block mb-2"></i>
                        No Spare Parts Consumption records linked to this installed base yet.
                    </div>
                    <?php } else { ?>
                        <?php
                        $sparePartsRecordTotal = count($sparePartsRecords);
                        $sparePartsRecordNumber = 0;
                        foreach ($sparePartsRecords as $sparePartsRecord) {
                            $sparePartsRecordNumber++;
                            $sparePartsItems = spare_parts_items_for_consumption(
                                $obconn,
                                (int) $sparePartsRecord['id']
                            );
                            $sparePartsEmbeddedInInstalledBase = true;
                            $sparePartsHideRecordHeader = false;
                            $isLastSparePartsRecord = $sparePartsRecordNumber === $sparePartsRecordTotal;
                            include __DIR__ . '/includes/spare_parts_record_details_section.php';
                        }
                        ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            document.querySelectorAll('.alert-success').forEach(function (el) {
                el.classList.remove('show');
                setTimeout(function () { el.remove(); }, 150);
            });
        }, 3000);
    });
    </script>
</body>

</html>