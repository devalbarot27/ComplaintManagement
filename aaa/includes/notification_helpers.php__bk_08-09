<?php

/**
 * Centralized in-app notification helpers.
 * Used by Complaint Management, Open Status Nudge, and future modules.
 *
 * Redirect URLs are resolved in code from module + reference_id + title (not stored).
 */

require_once __DIR__ . '/complaint_status.php';

function notification_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $stmt = $conn->query("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'notifications'
        LIMIT 1
    ");

    if (!$stmt->fetchColumn()) {
        $conn->exec("
            CREATE TABLE notifications (
                id SERIAL PRIMARY KEY,
                user_id INTEGER NOT NULL,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                module VARCHAR(100) NULL,
                reference_id INTEGER NULL,
                is_read SMALLINT NOT NULL DEFAULT 0,
                read_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $conn->exec('
            CREATE INDEX notifications_user_id_created_at_idx
                ON notifications (user_id, created_at DESC)
        ');

        $conn->exec('
            CREATE INDEX notifications_user_id_is_read_idx
                ON notifications (user_id, is_read)
        ');
    } else {
        $colStmt = $conn->query("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = 'notifications'
              AND column_name = 'redirect_url'
            LIMIT 1
        ");
        if ($colStmt->fetchColumn()) {
            $conn->exec('ALTER TABLE notifications DROP COLUMN redirect_url');
        }
    }

    $ensured = true;
}

/**
 * @return int|null Inserted notification ID
 */
function notification_create(
    PDO $conn,
    int $userId,
    string $title,
    string $message,
    ?string $module = null,
    ?int $referenceId = null
): ?int {
    if ($userId <= 0) {
        return null;
    }

    notification_ensure_schema($conn);

    $title = trim($title);
    $message = trim($message);
    if ($title === '' || $message === '') {
        return null;
    }

    $stmt = $conn->prepare('
        INSERT INTO notifications (
            user_id,
            title,
            message,
            module,
            reference_id,
            is_read,
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :title,
            :message,
            :module,
            :reference_id,
            0,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        RETURNING id
    ');

    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':title', $title);
    $stmt->bindValue(':message', $message);
    $stmt->bindValue(':module', $module !== null && trim($module) !== '' ? trim($module) : null);
    $stmt->bindValue(
        ':reference_id',
        $referenceId !== null && $referenceId > 0 ? $referenceId : null,
        $referenceId !== null && $referenceId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL
    );
    $stmt->execute();

    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function notification_unread_count(PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    notification_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
          AND is_read = 0
    ');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * @return array<int, array<string, mixed>>
 */
function notification_list_for_user(
    PDO $conn,
    int $userId,
    int $limit = 5,
    int $offset = 0,
    ?bool $unreadOnly = null
): array {
    if ($userId <= 0) {
        return [];
    }

    notification_ensure_schema($conn);

    $limit = max(1, min(100, $limit));
    $offset = max(0, $offset);

    $where = 'user_id = :user_id';
    if ($unreadOnly === true) {
        $where .= ' AND is_read = 0';
    } elseif ($unreadOnly === false) {
        $where .= ' AND is_read = 1';
    }

    $stmt = $conn->prepare("
        SELECT
            id,
            user_id,
            title,
            message,
            module,
            reference_id,
            is_read,
            read_at,
            created_at,
            updated_at
        FROM notifications
        WHERE {$where}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map(static function (array $row) use ($conn): array {
        return notification_format_row($row, $conn);
    }, $rows);
}

function notification_count_for_user(PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    notification_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
    ');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function notification_get_for_user(PDO $conn, int $userId, int $notificationId): ?array
{
    if ($userId <= 0 || $notificationId <= 0) {
        return null;
    }

    notification_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            id,
            user_id,
            title,
            message,
            module,
            reference_id,
            is_read,
            read_at,
            created_at,
            updated_at
        FROM notifications
        WHERE id = :id
          AND user_id = :user_id
        LIMIT 1
    ');
    $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? notification_format_row($row, $conn) : null;
}

function notification_mark_read(PDO $conn, int $userId, int $notificationId): bool
{
    if ($userId <= 0 || $notificationId <= 0) {
        return false;
    }

    notification_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE notifications
        SET is_read = 1,
            read_at = COALESCE(read_at, CURRENT_TIMESTAMP),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND user_id = :user_id
          AND is_read = 0
    ');
    $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

function notification_mark_all_read(PDO $conn, int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    notification_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE notifications
        SET is_read = 1,
            read_at = COALESCE(read_at, CURRENT_TIMESTAMP),
            updated_at = CURRENT_TIMESTAMP
        WHERE user_id = :user_id
          AND is_read = 0
    ');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

function notification_fetch_complaint_status(PDO $conn, int $complaintId): ?int
{
    if ($complaintId <= 0) {
        return null;
    }

    try {
        $stmt = $conn->prepare('
            SELECT status
            FROM complaints
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();
        $status = $stmt->fetchColumn();

        if ($status === false || $status === null) {
            return null;
        }

        return (int) $status;
    } catch (Throwable $e) {
        return null;
    }
}

function notification_is_service_update_pending(?string $module, ?string $title): bool
{
    $moduleKey = strtolower(trim((string) $module));
    $titleKey = strtolower(trim((string) $title));

    if (str_contains($titleKey, 'service update pending')) {
        return true;
    }

    return in_array($moduleKey, ['assigned-complaint', 'assigned-complaint-list', 'dealer_service'], true);
}

function notification_resolve_redirect_url(
    ?string $module,
    ?int $referenceId,
    ?string $title = null,
    ?PDO $conn = null
): ?string {
    $moduleKey = strtolower(trim((string) $module));
    $titleKey = strtolower(trim((string) $title));

    // Open-status nudge ? Complaint Entry filtered to Open
    if (
        in_array($moduleKey, ['complaint-open', 'open-complaint', 'open_status'], true)
        || ($moduleKey === 'complaint' && str_contains($titleKey, 'complaint pending'))
    ) {
        //return 'new_complaint.php?status_filter=' . COMPLAINT_STATUS_OPEN;
        return 'new_complaint.php?complaint_id='.(int)$referenceId;
    }

    // Call Closure nudge ? Complaint Entry filtered to Pending With HO
    if (
        in_array($moduleKey, ['complaint-closure', 'call-closure', 'ccs-closure', 'ccs_closure'], true)
        || ($moduleKey === 'complaint' && str_contains($titleKey, 'call closure'))
    ) {
        //return 'new_complaint.php?status_filter=' . COMPLAINT_STATUS_PENDING_HO;
        return 'new_complaint.php?complaint_id='.(int)$referenceId;
    }

    // Service Update Pending ? live status from DB
    if (notification_is_service_update_pending($module, $title)) {
        if ($referenceId === null || $referenceId <= 0 || $conn === null) {
            return null;
        }

        $status = notification_fetch_complaint_status($conn, $referenceId);
        if ($status === null) {
            return null;
        }

        if ($status === COMPLAINT_STATUS_IN_PROGRESS || $status === COMPLAINT_STATUS_REOPEN) {
            return 'dse_lse_complaint_list.php?complaint_id=' . (int) $referenceId;
        }

        $encodedId = rawurlencode(base64_encode((string) $referenceId));

        return 'complaint_details.php?id=' . $encodedId . '&from=entry';
    }

    if ($referenceId === null || $referenceId <= 0) {
        return null;
    }

    $encodedId = rawurlencode(base64_encode((string) $referenceId));

    switch ($moduleKey) {
        case 'complaint':
        case 'complaint-entry':
        case 'complaint-management':
            return 'complaint_details.php?id=' . $encodedId . '&from=entry';

        case 'service-log':
        case 'service_log':
        case 'service-log-capture':
            return 'service_log_details.php?id=' . $encodedId;

        case 'order':
        case 'orders':
        case 'order-booking':
            return 'order_details.php?id=' . $encodedId;

        default:
            return null;
    }
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function notification_format_row(array $row, ?PDO $conn = null): array
{
    $createdAt = (string) ($row['created_at'] ?? '');
    $module = $row['module'] !== null ? (string) $row['module'] : null;
    $title = (string) ($row['title'] ?? '');
    $referenceId = isset($row['reference_id']) && $row['reference_id'] !== null
        ? (int) $row['reference_id']
        : null;

    return [
        'id' => (int) ($row['id'] ?? 0),
        'user_id' => (int) ($row['user_id'] ?? 0),
        'title' => $title,
        'message' => (string) ($row['message'] ?? ''),
        'module' => $module,
        'reference_id' => $referenceId,
        'redirect_url' => notification_resolve_redirect_url($module, $referenceId, $title, $conn),
        'is_read' => (int) ($row['is_read'] ?? 0) === 1,
        'read_at' => $row['read_at'] !== null ? (string) $row['read_at'] : null,
        'created_at' => $createdAt,
        'updated_at' => (string) ($row['updated_at'] ?? ''),
        'relative_time' => notification_relative_time($createdAt),
    ];
}

function notification_relative_time(?string $datetime): string
{
   $tz = new DateTimeZone('Asia/Kolkata');
 
try {
    $created = new DateTimeImmutable($datetime, $tz);
} catch (Exception $e) {
    return '';
}
    $now = new DateTimeImmutable('now', $tz);
    $diff = $now->getTimestamp() - $created->getTimestamp();
 
    // Guard against clock/timezone skew showing future times as "Just Now"
    if ($diff < 0) {
        $diff = 0;
    }
 
    if ($diff < 60) {
        return 'Just Now';
    }
 
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes === 1 ? '1 Minute Ago' : $minutes . ' Minutes Ago';
    }
 
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours === 1 ? '1 Hour Ago' : $hours . ' Hours Ago';
    }
 
    if ($diff < 172800) {
        return 'Yesterday';
    }
 
    $days = (int) floor($diff / 86400);
    if ($days < 7) {
        return $days . ' Days Ago';
    }
 
    return $created->format('d M Y');
}