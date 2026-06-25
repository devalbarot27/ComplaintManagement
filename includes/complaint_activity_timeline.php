<?php
 
if (empty($timelineActivities)) {
    $timelineActivities = [];
}

?>
<div class="card border-1 shadow-sm mb-3">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <strong>Activity Timeline</strong>
        <span class="badge text-bg-light border">
            <?php echo count($timelineActivities); ?> events
        </span>
    </div>
 
    <div class="card-body">
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
        <p class="text-muted mb-0">No activity recorded yet.</p>
        <?php } ?>
    </div>
</div>
 
 