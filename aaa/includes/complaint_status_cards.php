<?php

require_once __DIR__ . '/complaint_status_counts.php';
require_once __DIR__ . '/current_username_helpers.php';

if (!isset($statusCounts)) {
    $assignedOnly = $assignedOnly ?? false;
    $statusCounts = complaint_status_counts($obconn, $assignedOnly, current_username());
}

$openCount = (int) ($statusCounts['open'] ?? 0);
$inProgressCount = (int) ($statusCounts['in_progress'] ?? 0);
$pendingHoCount = (int) ($statusCounts['pending_ho'] ?? 0);
$reopenCount = (int) ($statusCounts['reopen'] ?? 0);
$resolvedCount = (int) ($statusCounts['resolved'] ?? 0);
?>

<div class="complaint-status-grid">
    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Open</div>
            <div class="complaint-status-value"><?php echo $openCount; ?></div>
            <?php if ($openCount > 0) { ?>
            <div class="complaint-status-hint">Not assigned</div>
            <?php } ?>
        </div>
        <div class="complaint-status-icon complaint-status-icon--open">
            <i class="bi bi-exclamation-lg"></i>
        </div>
    </div>

    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">In Progress</div>
            <div class="complaint-status-value"><?php echo $inProgressCount; ?></div>
            <div class="complaint-status-hint">Assigned</div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--progress">
            <i class="bi bi-clock"></i>
        </div>
    </div>

    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Pending With HO</div>
            <div class="complaint-status-value"><?php echo $pendingHoCount; ?></div>
            <div class="complaint-status-hint">Service updated</div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--pending-ho">
            <i class="bi bi-hourglass-split"></i>
        </div>
    </div>

    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Re-Open</div>
            <div class="complaint-status-value"><?php echo $reopenCount; ?></div>
            <div class="complaint-status-hint">Closure No</div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--reopen">
            <i class="bi bi-arrow-counterclockwise"></i>
        </div>
    </div>

    <div class="complaint-status-card">
        <div class="complaint-status-body">
            <div class="complaint-status-label">Resolved</div>
            <div class="complaint-status-value"><?php echo $resolvedCount; ?></div>
            <div class="complaint-status-hint">Closure Yes</div>
        </div>
        <div class="complaint-status-icon complaint-status-icon--resolved">
            <i class="bi bi-check-lg"></i>
        </div>
    </div>
</div>
