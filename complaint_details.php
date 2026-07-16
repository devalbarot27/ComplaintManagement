<?php
session_start();
include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
include 'includes/complaint_activity_helpers.php';
require_once 'includes/complaint_status.php';
include 'includes/service_report_helpers.php';
include 'includes/complaint_address_helpers.php';
include 'includes/complaint_category_helpers.php';
require_once 'includes/complaint_closure_helpers.php';
require_once 'includes/complaint_service_log_helpers.php';

$id = (int)base64_decode($_GET['id'] ?? '', true);
 
if ($id <= 0) {
    die('Invalid complaint');
}
 
$stmt = $obconn->prepare("
    SELECT
        c.*,
        COALESCE(
            NULLIF(TRIM(um.name), ''),
            NULLIF(TRIM(um.username), ''),
            NULLIF(TRIM(c.username), ''),
            '-'
        ) AS added_by_name
    FROM complaints c
    LEFT JOIN user_master um
        ON um.id = c.added_by
       AND um.deleted_at IS NULL
    WHERE c.id = :id
      AND c.deleted_at IS NULL
");
 
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
 
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);
 
if (!$complaint) {
    die('Complaint not found');
}

$from = $_GET['from'] ?? 'entry';
if ($from === 'list') {
    require_once 'includes/complaint_assignment_helpers.php';
    if (!rbac_user_can($obconn, 'assigned-complaint-list', 'view')) {
        rbac_access_denied_redirect();
    }
    if (!complaint_user_can_access_assigned_complaint($obconn, $id)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to view this complaint.';
        header('Location: dse_lse_complaint_list.php');
        exit;
    }
    $active_menu = 'complaint_list';
    $back_url = 'dse_lse_complaint_list.php';
    $back_label = 'Back to Assigned List';
} else {
    if (!complaint_user_can_access_entry_complaint($obconn, $id)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission to view this complaint.';
        header('Location: new_complaint.php');
        exit;
    }
    $active_menu = 'complaint_entry';
    $back_url = 'new_complaint.php';
    $back_label = 'Back to Complaint Entry';
}
 
$statusMap = complaint_status_map();
 
$assignmentStmt = $obconn->prepare("
    SELECT
        ca.id,
        ca.assign_complaint,
        ca.assign_complaint_datetime,
        ca.assigned_by,
        ca.remarks,
        COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(um.username), ''), '-') AS assigned_by_name
    FROM complaint_assignments ca
    LEFT JOIN user_master um
        ON um.id = ca.assigned_by
       AND um.deleted_at IS NULL
    WHERE ca.complaint_id = :complaint_id
    ORDER BY ca.assign_complaint_datetime DESC
");
 
$assignmentStmt->bindValue(':complaint_id', $complaint['id'], PDO::PARAM_INT);
$assignmentStmt->execute();
$assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);
 
$serviceStmt = $obconn->prepare("
    SELECT
        id,
        assignment_id,
        customer_visit_date,
        complaint_action_taken,
        part_replaced,
        service_report,
        created_by,
        created_at
    FROM complaint_service_updates
    WHERE complaint_id = :complaint_id
    ORDER BY created_at DESC, id DESC
");
 
$serviceStmt->bindValue(':complaint_id', $complaint['id'], PDO::PARAM_INT);
$serviceStmt->execute();
$serviceUpdates = complaint_service_log_attach_view_urls_to_service_updates(
    $obconn,
    (int) $complaint['id'],
    $serviceStmt->fetchAll(PDO::FETCH_ASSOC)
);
 
$closureStmt = $obconn->prepare("
    SELECT
        cc.call_closure,
        cc.closure_remarks,
        cc.reassignment_details,
        cc.closure_datetime,
        cc.customer_feedback,
        cc.closed_by,
        cc.created_at,
        COALESCE(
            NULLIF(TRIM(um.name), ''),
            NULLIF(TRIM(um.username), ''),
            '-'
        ) AS closed_by_name
    FROM complaint_closures cc
    LEFT JOIN user_master um
        ON um.id = cc.closed_by
       AND um.deleted_at IS NULL
    WHERE cc.complaint_id = :complaint_id
    ORDER BY cc.created_at DESC, cc.id DESC
");
 
$closureStmt->bindValue(':complaint_id', $complaint['id'], PDO::PARAM_INT);
$closureStmt->execute();
$closures = $closureStmt->fetchAll(PDO::FETCH_ASSOC);
 

$timelineActivities = complaint_fetch_activity_timeline($obconn, (int) $complaint['id'], $complaint);

$statusUiClass = [
    COMPLAINT_STATUS_OPEN => 'complaint-details-status--open',
    COMPLAINT_STATUS_IN_PROGRESS => 'complaint-details-status--progress',
    COMPLAINT_STATUS_PENDING_HO => 'complaint-details-status--pending',
    COMPLAINT_STATUS_REOPEN => 'complaint-details-status--reopen',
    COMPLAINT_STATUS_RESOLVED => 'complaint-details-status--resolved',
];

$complaintStatusUiClass = $statusUiClass[(int) $complaint['status']] ?? 'complaint-details-status--default';
$assignmentCount = count($assignments);
$serviceUpdateCount = count($serviceUpdates);
$closureCount = count($closures);
?>
 
<!DOCTYPE html>
<html lang="en">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details #<?php echo (int) $complaint['id']; ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/complaint_activity_timeline.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
 
<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>
 
        <div class="content">

            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-3">
                <div>
                    <h5 class="mb-2">Complaint #<?php echo (int) $complaint['id']; ?></h5>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="complaint-details-status <?php echo htmlspecialchars($complaintStatusUiClass); ?>">
                            <?php echo htmlspecialchars($statusMap[$complaint['status']] ?? 'Unknown'); ?>
                        </span>
                        <?php if (!empty($complaint['fab_number'])) { ?>
                        <span class="badge border border-secondary text-secondary complaint-details-meta-badge">
                            <i class="bi bi-upc-scan"></i>
                            <?php echo htmlspecialchars($complaint['fab_number']); ?>
                        </span>
                        <?php } ?>
                        <?php if ($assignmentCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary complaint-details-meta-badge">
                            <?php echo (int) $assignmentCount; ?>
                            assignment<?php echo $assignmentCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                        <?php if ($serviceUpdateCount > 0) { ?>
                        <span class="badge border border-secondary text-secondary complaint-details-meta-badge">
                            <?php echo (int) $serviceUpdateCount; ?>
                            service update<?php echo $serviceUpdateCount === 1 ? '' : 's'; ?>
                        </span>
                        <?php } ?>
                    </div>
                </div>

                <div class="d-flex gap-2 flex-wrap">
                    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-light border">
                        <i class="bi bi-arrow-left"></i>
                        <?php echo htmlspecialchars($back_label); ?>
                    </a>
                </div>
            </div>

            <?php include __DIR__ . '/includes/complaint_record_details_section.php'; ?>

            <div class="card border-1 shadow-sm mb-3 complaint-details-history-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-person-check text-secondary"></i>
                        <strong>Assignment History</strong>
                    </div>
                    <?php if ($assignmentCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $assignmentCount; ?>
                        record<?php echo $assignmentCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>

                <div class="card-body complaint-form-body px-3 pt-3 pb-3">
                    <?php if ($assignments === []) { ?>
                    <div class="complaint-details-empty">
                        <i class="bi bi-person-x"></i>
                        No assignment history found.
                    </div>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle complaint-details-table">
                            <thead>
                                <tr>
                                    <th>Assigned To</th>
                                    <th>Assigned Date</th>
                                    <th>Assigned By</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $key => $assignment) {
                                    $isLatest = $key === 0;
                                    $assignmentTag = $isLatest ? 'Assigned' : 'Reassigned';
                                    $tagClass = $isLatest ? 'complaint-details-tag--current' : '';
                                    ?>
                                <tr>
                                    <td data-label="Assigned To">
                                        <?php echo htmlspecialchars($assignment['assign_complaint']); ?>
                                        <span class="complaint-details-tag <?php echo $tagClass; ?>">
                                            <?php echo $assignmentTag; ?>
                                        </span>
                                    </td>
                                    <td data-label="Assigned Date">
                                        <?php echo date('d M Y h:i A', strtotime($assignment['assign_complaint_datetime'])); ?>
                                    </td>
                                    <td data-label="Assigned By">
                                        <?php echo htmlspecialchars($assignment['assigned_by_name']); ?>
                                    </td>
                                    <td data-label="Remarks">
                                        <?php echo nl2br(htmlspecialchars($assignment['remarks'] ?? '-')); ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 complaint-details-history-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-tools text-secondary"></i>
                        <strong>Service Updates</strong>
                    </div>
                    <?php if ($serviceUpdateCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $serviceUpdateCount; ?>
                        record<?php echo $serviceUpdateCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>

                <div class="card-body complaint-form-body px-3 pt-3 pb-3">
                    <?php if ($serviceUpdates === []) { ?>
                    <div class="complaint-details-empty">
                        <i class="bi bi-clipboard-x"></i>
                        No service updates found.
                    </div>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle complaint-details-table">
                            <thead>
                                <tr>
                                    <th>Visit Date</th>
                                    <th>Action Taken</th>
                                    <th>Part Replaced</th>
                                    <th>Service Report</th>
                                    <th>Service Log</th>   
                                    <th>Updated On</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($serviceUpdates as $service) { ?>
                                <tr>
                                    <td data-label="Visit Date">
                                        <?php echo date('d M Y', strtotime($service['customer_visit_date'])); ?>
                                    </td>
                                    <td data-label="Action Taken">
                                        <?php echo nl2br(htmlspecialchars($service['complaint_action_taken'])); ?>
                                    </td>
                                    <td data-label="Part Replaced">
                                        <?php echo htmlspecialchars($service['part_replaced'] ?: '-'); ?>
                                    </td>
                                    <td data-label="Service Report">
                                        <?php
                                        $serviceReports = service_report_parse_filenames($service['service_report'] ?? null);
                                        if ($serviceReports !== []) {
                                            foreach ($serviceReports as $reportIndex => $reportFile) {
                                                if ($reportIndex > 0) {
                                                    echo ' ';
                                                }
                                                ?>
                                        <a href="uploads/service_reports/<?php echo rawurlencode($reportFile); ?>"
                                            target="_blank" rel="noopener noreferrer"
                                            class="btn btn-sm btn-outline-dark complaint-details-report-btn">
                                            <i class="bi bi-file-earmark-text"></i>
                                            View<?php echo count($serviceReports) > 1 ? ' ' . ($reportIndex + 1) : ''; ?>
                                        </a>
                                                <?php
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td data-label="Service Log">
                                        <?php if (!empty($service['service_log_view_url'])) { ?>
                                        <a href="<?php echo htmlspecialchars($service['service_log_view_url'], ENT_QUOTES, 'UTF-8'); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-sm btn-outline-dark complaint-details-report-btn">
                                            <i class="bi bi-journal-text"></i>
                                            View
                                        </a>
                                        <?php } else { ?>
                                        -
                                        <?php } ?>
                                    </td>
                                    <td data-label="Updated On">
                                        <?php echo date('d M Y h:i A', strtotime($service['created_at'])); ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="card border-1 shadow-sm mb-3 complaint-details-history-card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-check2-circle text-secondary"></i>
                        <strong>Closure History</strong>
                    </div>
                    <?php if ($closureCount > 0) { ?>
                    <span class="badge border border-secondary text-secondary">
                        <?php echo (int) $closureCount; ?>
                        record<?php echo $closureCount === 1 ? '' : 's'; ?>
                    </span>
                    <?php } ?>
                </div>

                <div class="card-body complaint-form-body px-3 pt-3 pb-3">
                    <?php if ($closures === []) { ?>
                    <div class="complaint-details-empty">
                        <i class="bi bi-archive"></i>
                        No closure history found.
                    </div>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle complaint-details-table">
                            <thead>
                                <tr>
                                    <th>Call Closure</th>
                                    <th>Closure Remarks</th>
                                    <th>Customer Feedback</th>
                                    <th>Remarks</th>
                                    <th>Closed By</th>
                                    <th>Closure Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($closures as $closure) { ?>
                                <tr>
                                    <td data-label="Call Closure">
                                        <?php echo htmlspecialchars($closure['call_closure']); ?>
                                    </td>
                                    <td data-label="Closure Remarks">
                                        <?php echo nl2br(htmlspecialchars($closure['closure_remarks'] ?? '-')); ?>
                                    </td>
                                    <td data-label="Customer Feedback">
                                        <?php echo complaint_closure_display_customer_feedback($closure['customer_feedback'] ?? null); ?>
                                    </td>
                                    <td data-label="Remarks">
                                        <?php echo nl2br(htmlspecialchars($closure['reassignment_details'] ?? '-')); ?>
                                    </td>
                                    <td data-label="Closed By">
                                        <?php echo htmlspecialchars($closure['closed_by_name'] ?? '-'); ?>
                                    </td>
                                    <td data-label="Closure Date">
                                        <?php
                                        $closureDate = $closure['closure_datetime'] ?? $closure['created_at'] ?? null;
                                        echo $closureDate
                                            ? date('d M Y h:i A', strtotime($closureDate))
                                            : '-';
                                        ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <?php include 'includes/complaint_activity_timeline.php'; ?>

        </div>

    </div>
</body>

</html>
 
 