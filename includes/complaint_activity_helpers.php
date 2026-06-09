<?php
function complaint_log_activity(
    PDO $conn,
    int $complaintId,
    string $activityType,
    string $description,
    int $userId = 1
): void {
    $log = $conn->prepare("
        INSERT INTO complaint_activity_logs
        (
            complaint_id,
            activity_type,
            activity_description,
            user_id
        )
        VALUES
        (
            :complaint_id,
            :activity_type,
            :activity_description,
            :user_id
        )
    ");
 
    $log->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $log->bindValue(':activity_type', $activityType);
    $log->bindValue(':activity_description', $description);
    $log->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $log->execute();
}
 
function complaint_activity_type_meta(string $activityType): array
{
    $map = [
        'Created' => [
            'label' => 'Complaint Created',
            'icon' => 'bi-plus-circle-fill',
            'modifier' => 'complaint-timeline-item--created',
        ],
        'Assignment' => [
            'label' => 'Assigned',
            'icon' => 'bi-person-plus-fill',
            'modifier' => 'complaint-timeline-item--assign',
        ],
        'Reassignment' => [
            'label' => 'Reassigned',
            'icon' => 'bi-arrow-counterclockwise',
            'modifier' => 'complaint-timeline-item--reassign',
        ],
        'Service Update' => [
            'label' => 'Service Update',
            'icon' => 'bi-tools',
            'modifier' => 'complaint-timeline-item--service',
        ],
        'Closure' => [
            'label' => 'Closure',
            'icon' => 'bi-check2-square',
            'modifier' => 'complaint-timeline-item--closure',
        ],
        'Status Change' => [
            'label' => 'Status Changed',
            'icon' => 'bi-arrow-repeat',
            'modifier' => 'complaint-timeline-item--status',
        ],
        'Deleted' => [
            'label' => 'Deleted',
            'icon' => 'bi-trash-fill',
            'modifier' => 'complaint-timeline-item--deleted',
        ],
    ];
 
    return $map[$activityType] ?? [
        'label' => $activityType,
        'icon' => 'bi-circle-fill',
        'modifier' => 'complaint-timeline-item--default',
    ];
}
 
function complaint_resolve_activity_meta(string $activityType, string $description = ''): array
{
    if (
        $activityType === 'Assignment'
        && stripos($description, 'reassigned') !== false
    ) {
        return complaint_activity_type_meta('Reassignment');
    }

    return complaint_activity_type_meta($activityType);
}

function complaint_fetch_activity_timeline(PDO $conn, int $complaintId, array $complaint): array
{
    $stmt = $conn->prepare("
        SELECT
            activity_type,
            activity_description,
            user_id,
            created_at
        FROM complaint_activity_logs
        WHERE complaint_id = :complaint_id
        ORDER BY created_at ASC
    ");
 
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
    $hasCreated = false;
    foreach ($activities as $activity) {
        if ($activity['activity_type'] === 'Created') {
            $hasCreated = true;
            break;
        }
    }
 
    if (!$hasCreated && !empty($complaint['created_at'])) {
        array_unshift($activities, [
            'activity_type' => 'Created',
            'activity_description' => 'Complaint registered for Fab Number '
                . ($complaint['fab_number'] ?? '')
                . ' � '
                . ($complaint['customer_name'] ?? ''),
            'user_id' => $complaint['added_by'] ?? 1,
            'created_at' => $complaint['created_at'],
        ]);
    }
 
    $timeline = [];
    foreach ($activities as $activity) {
        $meta = complaint_resolve_activity_meta(
            (string) $activity['activity_type'],
            (string) ($activity['activity_description'] ?? '')
        );
        $timeline[] = [
            'type' => $activity['activity_type'],
            'type_label' => $meta['label'],
            'icon' => $meta['icon'],
            'modifier' => $meta['modifier'],
            'description' => $activity['activity_description'],
            'user_id' => $activity['user_id'] ?? null,
            'created_at' => $activity['created_at'],
        ];
    }
 
    return $timeline;
}