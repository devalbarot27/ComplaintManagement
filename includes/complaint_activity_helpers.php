<?php

require_once __DIR__ . '/current_username_helpers.php';

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
            user_id,
            username
        )
        VALUES
        (
            :complaint_id,
            :activity_type,
            :activity_description,
            :user_id,
            :username
        )
    ");

    $log->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $log->bindValue(':activity_type', $activityType);
    $log->bindValue(':activity_description', $description);
    $log->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $log->bindValue(':username', current_username());
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

/**
 * @param int[] $userIds
 * @return array<int, string>
 */
function complaint_activity_user_names_by_ids(PDO $conn, array $userIds): array
{
    $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static function ($id) {
        return $id > 0;
    })));

    if ($userIds === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    foreach ($userIds as $index => $userId) {
        $paramKey = ':activity_user_' . $index;
        $placeholders[] = $paramKey;
        $params[$paramKey] = $userId;
    }

    $stmt = $conn->prepare("
        SELECT
            id,
            COALESCE(
                NULLIF(TRIM(name), ''),
                NULLIF(TRIM(username), ''),
                NULL
            ) AS display_name
        FROM user_master
        WHERE id IN (" . implode(', ', $placeholders) . ")
    ");
    foreach ($params as $paramKey => $userId) {
        $stmt->bindValue($paramKey, $userId, PDO::PARAM_INT);
    }
    $stmt->execute();

    $names = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $displayName = trim((string) ($row['display_name'] ?? ''));
        if ($displayName !== '') {
            $names[(int) $row['id']] = $displayName;
        }
    }

    return $names;
}

function complaint_activity_resolve_user_name(array $activity, array $userNamesById): string
{
    $userId = (int) ($activity['user_id'] ?? 0);
    if ($userId > 0 && !empty($userNamesById[$userId])) {
        return $userNamesById[$userId];
    }

    $loggedUsername = trim((string) ($activity['username'] ?? ''));

    return $loggedUsername;
}

function complaint_fetch_activity_timeline(PDO $conn, int $complaintId, array $complaint): array
{
    $stmt = $conn->prepare("
        SELECT
            activity_type,
            activity_description,
            user_id,
            username,
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
            'username' => $complaint['username'] ?? '',
            'created_at' => $complaint['created_at'],
        ]);
    }

    $userIds = [];
    foreach ($activities as $activity) {
        $userId = (int) ($activity['user_id'] ?? 0);
        if ($userId > 0) {
            $userIds[] = $userId;
        }
    }
    $userNamesById = complaint_activity_user_names_by_ids($conn, $userIds);
 
    $timeline = [];
    foreach ($activities as $activity) {
        $meta = complaint_resolve_activity_meta(
            (string) $activity['activity_type'],
            (string) ($activity['activity_description'] ?? '')
        );
        $userName = complaint_activity_resolve_user_name($activity, $userNamesById);
        $timeline[] = [
            'type' => $activity['activity_type'],
            'type_label' => $meta['label'],
            'icon' => $meta['icon'],
            'modifier' => $meta['modifier'],
            'description' => $activity['activity_description'],
            'user_id' => $activity['user_id'] ?? null,
            'user_name' => $userName,
            'created_at' => $activity['created_at'],
        ];
    }
 
    return $timeline;
}