<?php
session_start();
include 'pdo_obconn.php';
include 'includes/complaint_activity_helpers.php';
include 'includes/complaint_status.php';

$id = (int)base64_decode($_GET['id'] ?? '', true);
 
if ($id <= 0) {
    die('Invalid complaint');
}
 
$stmt = $obconn->prepare("
    SELECT *
    FROM complaints
    WHERE id = :id
    AND deleted_at IS NULL
");
 
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
 
$complaint = $stmt->fetch(PDO::FETCH_ASSOC);
 
if (!$complaint) {
    die('Complaint not found');
}
 
$statusMap = complaint_status_map();

$statusClass = [
    COMPLAINT_STATUS_OPEN => 'border border-dark badge text-dark',
    COMPLAINT_STATUS_IN_PROGRESS => 'border border-dark badge text-dark',
    COMPLAINT_STATUS_PENDING_HO => 'border border-dark badge text-dark',
    COMPLAINT_STATUS_REOPEN => 'border border-dark badge text-dark',
    COMPLAINT_STATUS_RESOLVED => 'border border-dark badge text-dark',
];
 
$from = $_GET['from'] ?? 'entry';
if ($from === 'list') {
    $active_menu = 'complaint_list';
    $back_url = 'dse_lse_complaint_list.php';
    $back_label = 'Back to Assigned List';
} else {
    $active_menu = 'complaint_entry';
    $back_url = 'new_complaint.php';
    $back_label = 'Back to Complaint Entry';
}
 
$assignmentStmt = $obconn->prepare("
    SELECT
        id,
        assign_complaint,
        assign_complaint_datetime,
        assigned_by,
        remarks
    FROM complaint_assignments
    WHERE complaint_id = :complaint_id
    ORDER BY assign_complaint_datetime DESC
");
 
$assignmentStmt->bindValue(':complaint_id', $complaint['id'], PDO::PARAM_INT);
$assignmentStmt->execute();
$assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);
 
$serviceStmt = $obconn->prepare("
    SELECT
        id,
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
$serviceUpdates = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
 
$closureStmt = $obconn->prepare("
    SELECT
        call_closure,
        closure_remarks,
        reassignment_details,
        closed_by,
        created_at
    FROM complaint_closures
    WHERE complaint_id = :complaint_id
    ORDER BY created_at DESC, id DESC
");
 
$closureStmt->bindValue(':complaint_id', $complaint['id'], PDO::PARAM_INT);
$closureStmt->execute();
$closures = $closureStmt->fetchAll(PDO::FETCH_ASSOC);
 

$timelineActivities = complaint_fetch_activity_timeline($obconn, (int) $complaint['id'], $complaint);
?>
 
<!DOCTYPE html>
<html lang="en">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaint Details #<?php echo (int) $complaint['id']; ?></title>
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
 
                    <h5 class="mb-1">
                        Complaint #<?php echo (int) $complaint['id']; ?>
                    </h5>
 
                    <span class="<?php echo $statusClass[$complaint['status']] ?? 'badge bg-secondary'; ?>">
                        <?php echo $statusMap[$complaint['status']] ?? 'Unknown'; ?>
                    </span>
 
                </div>
 
                <div class="d-flex gap-2 flex-wrap">
 
                    <a href="<?php echo htmlspecialchars($back_url); ?>" class="btn btn-light border">
                        <?php echo htmlspecialchars($back_label); ?>
                    </a>
 
                </div>
 
            </div>
 
            <div class="card border-1 shadow-sm mb-3">
 
                <div class="card-header bg-white">
                    <strong>Complaint Information</strong>
                </div>
 
                <div class="card-body row g-3">
 
                    <div class="col-md-4">
                        <strong>Fab Number:</strong>
                        <?php echo htmlspecialchars($complaint['fab_number']); ?>
                    </div>
 
                    <div class="col-md-4">
                        <strong>Customer Name:</strong>
                        <?php echo htmlspecialchars($complaint['customer_name']); ?>
                    </div>
 
                    <div class="col-md-4">
                        <strong>Status:</strong>
                        <?php echo $statusMap[$complaint['status']] ?? 'Unknown'; ?>
                    </div>
 
                    <div class="col-md-12">
                        <strong>Address:</strong>
                        <?php echo nl2br(htmlspecialchars($complaint['customer_address'])); ?>
                    </div>
 
                    <div class="col-md-12">
                        <strong>Complaint Description:</strong>
                        <?php echo nl2br(htmlspecialchars($complaint['complaint_description'])); ?>
                    </div>
 
                    <div class="col-md-4">
                        <strong>Created At:</strong>
                        <?php echo date('d M Y h:i A', strtotime($complaint['created_at'])); ?>
                    </div>
 
                </div>
 
            </div>
 
            <div class="card border-1 shadow-sm mb-3">
 
                <div class="card-header bg-white">
                    <strong>Assignment History</strong>
                </div>
 
                <div class="card-body table-responsive">
 
                    <table class="table table-sm table-bordered align-middle">
 
                        <thead>
                            <tr>
                                <th>Assigned To</th>
                                <th>Assigned Date</th>
                                <th>Assigned By</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
 
                        <tbody>
 
                            <?php if (!empty($assignments)) { ?>
 
                            <?php foreach ($assignments as $key=>$assignment) { ?>
 
                            <tr>
 
                                <td>
                                    <?php echo htmlspecialchars($assignment['assign_complaint']);  if ( $key < count($assignments) - 1) {
            echo ' (Reassigned)';
        }else{
            echo ' (Assigned)';
        } ?>
                                </td>
 
                                <td>
                                    <?php echo date(
                                        'd M Y h:i A',
                                        strtotime($assignment['assign_complaint_datetime'])
                                    ); ?>
                                </td>
 
                                <td>
                                    User <?php echo htmlspecialchars($assignment['assigned_by']); ?>
                                </td>
 
                                <td>
                                    <?php echo nl2br(htmlspecialchars($assignment['remarks'] ?? '')); ?>
                                </td>
 
                            </tr>
 
                            <?php } ?>
 
                            <?php } else { ?>
 
                            <tr>
                                <td colspan="4" class="text-center">
                                    No assignment history found.
                                </td>
                            </tr>
 
                            <?php } ?>
 
                        </tbody>
 
                    </table>
 
                </div>
 
            </div>
 
            <div class="card border-1 shadow-sm mb-3">
 
                <div class="card-header bg-white">
                    <strong>Service Updates</strong>
                </div>
 
                <div class="card-body table-responsive">
 
                    <table class="table table-sm table-bordered align-middle">
 
                        <thead>
                            <tr>
                                <th>Visit Date</th>
                                <th>Action Taken</th>
                                <th>Part Replaced</th>
                                <th>Service Report</th>
                                <th>Updated On</th>
                            </tr>
                        </thead>
 
                        <tbody>
 
                            <?php if (!empty($serviceUpdates)) { ?>
 
                            <?php foreach ($serviceUpdates as $service) { ?>
 
                            <tr>
 
                                <td>
                                    <?php echo date('d M Y', strtotime($service['customer_visit_date'])); ?>
                                </td>
 
                                <td>
                                    <?php echo nl2br(htmlspecialchars($service['complaint_action_taken'])); ?>
                                </td>
 
                                <td>
                                    <?php echo htmlspecialchars($service['part_replaced'] ?: '-'); ?>
                                </td>
 
                                <td>
                                    <?php if (!empty($service['service_report'])) { ?>
                                    <a href="uploads/service_reports/<?php echo rawurlencode($service['service_report']); ?>"
                                        target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                    <?php } else { ?>
                                    -
                                    <?php } ?>
                                </td>
 
                                <td>
                                    <?php echo date('d M Y h:i A', strtotime($service['created_at'])); ?>
                                </td>
 
                            </tr>
 
                            <?php } ?>
 
                            <?php } else { ?>
 
                            <tr>
                                <td colspan="5" class="text-center">
                                    No service updates found.
                                </td>
                            </tr>
 
                            <?php } ?>
 
                        </tbody>
 
                    </table>
 
                </div>
 
            </div>
 
            <div class="card border-1 shadow-sm mb-3">
 
                <div class="card-header bg-white">
                    <strong>Closure History</strong>
                </div>
 
                <div class="card-body table-responsive">
 
                    <table class="table table-sm table-bordered align-middle">
 
                        <thead>
                            <tr>
                                <th>Call Closure</th>
                                <th>Closure Remarks</th>
                                <th>Remarks</th>
                                <th>Closed By</th>
                                <th>Date</th>
                            </tr>
                        </thead>
 
                        <tbody>
 
                            <?php if (!empty($closures)) { ?>
 
                            <?php foreach ($closures as $closure) { ?>
 
                            <tr>
 
                                <td><?php echo htmlspecialchars($closure['call_closure']); ?></td>
 
                                <td><?php echo nl2br(htmlspecialchars($closure['closure_remarks'] ?? '-')); ?></td>
 
                                <td><?php echo nl2br(htmlspecialchars($closure['reassignment_details'] ?? '-')); ?></td>
 
                                <td>User <?php echo htmlspecialchars($closure['closed_by']); ?></td>
 
                                <td>
                                    <?php echo date('d M Y h:i A', strtotime($closure['created_at'])); ?>
                                </td>
 
                            </tr>
 
                            <?php } ?>
 
                            <?php } else { ?>
 
                            <tr>
                                <td colspan="5" class="text-center">No closure history found.</td>
                            </tr>
 
                            <?php } ?>
 
                        </tbody>
 
                    </table>
 
                </div>
 
            </div>
 
            <div class="card border-0 shadow-sm mb-3 d-none">
 
                <div class="card-header bg-white">
                    <strong>Activity Timeline</strong>
                </div>
 
                <div class="card-body">
 
                    <ul class="timeline-list mb-0">
 
                        <?php if (!empty($activities)) { ?>
 
                        <?php foreach ($activities as $activity) { ?>
 
                        <li>
 
                            <div class="fw-semibold">
                                <?php echo htmlspecialchars($activity['activity_description']); ?>
                            </div>
 
                            <div class="text-muted small">
                                <?php echo date(
                                    'd M Y h:i A',
                                    strtotime($activity['created_at'])
                                ); ?>
                            </div>
 
                        </li>
 
                        <?php } ?>
 
                        <?php } else { ?>
 
                        <li>
                            <div class="fw-semibold">
                                Complaint Created
                            </div>
 
                            <div class="text-muted small">
                                <?php echo date(
                                    'd M Y h:i A',
                                    strtotime($complaint['created_at'])
                                ); ?>
                            </div>
                        </li>
 
                        <?php } ?>
 
                    </ul>
 
                </div>
 
            </div>
 
 <?php  include 'includes/complaint_activity_timeline.php'; ?>

        </div>
 
    </div>
 
    <style>
    .badge-open {
        background-color: #dc3545;
    }
 
    .badge-progress {
        background-color: #fd7e14;
    }
 
    .badge-resolved {
        background-color: #198754;
    }
 
    .timeline-list {
        list-style: none;
        padding-left: 0;
    }
 
    .timeline-list li {
        position: relative;
        padding: 0 0 16px 24px;
        border-left: 2px solid #dee2e6;
        margin-left: 8px;
    }
 
    .timeline-list li::before {
        content: "";
        position: absolute;
        left: -7px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #0d6efd;
    }
 
    .timeline-list li:last-child {
        border-left: none;
        padding-bottom: 0;
    }


/*activity*/
.complaint-timeline {
    position: relative;
    padding-left: 4px;
}
 
.complaint-timeline-item {
    position: relative;
    display: flex;
    gap: 14px;
    padding: 0 0 22px 0;
}
 
.complaint-timeline-item:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 17px;
    top: 36px;
    bottom: 0;
    width: 2px;
    background: #e2e8f0;
}
 
.complaint-timeline-marker {
    position: relative;
    z-index: 1;
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f8fafc;
    border: 2px solid #cbd5e1;
    color: #334155;
    font-size: 15px;
}
 
.complaint-timeline-content {
    flex: 1;
    min-width: 0;
    padding-top: 2px;
}
 
.complaint-timeline-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 4px;
}
 
.complaint-timeline-type {
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
}
 
.complaint-timeline-time {
    font-size: 12px;
    color: #64748b;
    white-space: nowrap;
}
 
.complaint-timeline-desc {
    font-size: 14px;
    color: #334155;
    line-height: 1.5;
}
 
.complaint-timeline-item--created .complaint-timeline-marker {
    background: #ecfdf5;
    border-color: #10b981;
    color: #059669;
}
 
.complaint-timeline-item--assign .complaint-timeline-marker {
    background: #fff7ed;
    border-color: #f59e0b;
    color: #d97706;
}

.complaint-timeline-item--reassign .complaint-timeline-marker {
    background: #fff7ed;
    border-color: #f59e0b;
    color: #d97706;
}
 
.complaint-timeline-item--service .complaint-timeline-marker {
    background: #eff6ff;
    border-color: #3b82f6;
    color: #2563eb;
}
 
.complaint-timeline-item--closure .complaint-timeline-marker {
    background: #f5f3ff;
    border-color: #8b5cf6;
    color: #7c3aed;
}
 
.complaint-timeline-item--status .complaint-timeline-marker {
    background: #ecfeff;
    border-color: #06b6d4;
    color: #0891b2;
}
 
.complaint-timeline-item--deleted .complaint-timeline-marker {
    background: #fef2f2;
    border-color: #ef4444;
    color: #dc2626;
}
 
.complaint-timeline-item:last-child {
    padding-bottom: 0;
}
 
@media (max-width: 576px) {
    .complaint-timeline-head {
        flex-direction: column;
        align-items: flex-start;
    }
}
 
 
    </style>
</body>
 
</html>
 
 