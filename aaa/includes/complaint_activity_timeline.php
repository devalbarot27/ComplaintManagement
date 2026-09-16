<?php
 
if (empty($timelineActivities)) {
    $timelineActivities = [];
}

?>
<div class="card border-1 shadow-sm mb-3 complaint-details-history-card">
    <div class="card-header bg-white d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock-history text-secondary"></i>
            <strong>Activity Timeline</strong>
        </div>
        <span class="badge border border-secondary text-secondary">
            <?php echo count($timelineActivities); ?> event<?php echo count($timelineActivities) === 1 ? '' : 's'; ?>
        </span>
    </div>

    <div class="card-body complaint-form-body px-3 pt-3 pb-3">
        <?php if (!empty($timelineActivities)) { ?>
        <div class="complaint-timeline">
            <?php foreach ($timelineActivities as $index => $event) { ?>
            <div class="complaint-timeline-item <?php echo htmlspecialchars($event['modifier']); ?>">
                <div class="complaint-timeline-marker" aria-hidden="true">
                    <i class="bi <?php echo htmlspecialchars($event['icon']); ?>"></i>
                </div>
 
                <div class="complaint-timeline-content">
                    <div class="complaint-timeline-head">
                        <span class="complaint-timeline-type">
                            <?php echo htmlspecialchars($event['type_label']); ?>
                        </span>
                        <span class="complaint-timeline-time">
                            <?php echo date('d M Y, h:i A', strtotime($event['created_at'])); ?>
                        </span>
                    </div>
 
                    <p class="complaint-timeline-desc mb-1">
                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                    </p>
 
                    <?php if (!empty($event['user_name'])) { ?>
                    <div class="complaint-timeline-user text-muted small">
                        By <?php echo htmlspecialchars($event['user_name']); ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
        <?php } else { ?>
        <div class="complaint-details-empty">
            <i class="bi bi-hourglass"></i>
            No activity recorded yet.
        </div>
        <?php } ?>
    </div>
</div>
 
 