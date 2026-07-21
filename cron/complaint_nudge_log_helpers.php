<?php

/**
 * Common complaint nudge log helpers.
 * Single table for Open / Dealer Service / CCS Closure nudge history.
 */

require_once dirname(__DIR__) . '/includes/notification_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_mail_helpers.php';

const COMPLAINT_NUDGE_TYPE_OPEN_STATUS = 'open_status';
const COMPLAINT_NUDGE_TYPE_DEALER_SERVICE = 'dealer_service';
const COMPLAINT_NUDGE_TYPE_CCS_CLOSURE = 'ccs_closure';

function complaint_nudge_log_table(): string
{
    return 'complaint_nudge_logs';
}

/**
 * Highest applicable milestone for current pending age.
 * >24h & ≤48h → 24 | >48h & ≤72h → 48 | >72h → 72
 */
function complaint_nudge_applicable_hours(float $ageHours): ?int
{
    if ($ageHours > 72) {
        return 72;
    }
    if ($ageHours > 48) {
        return 48;
    }
    if ($ageHours > 24) {
        return 24;
    }

    return null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function complaint_nudge_fetch_ccs_admins(PDO $conn): array
{
    $stmt = $conn->prepare('
        SELECT id, name, username, email, role
        FROM user_master
        WHERE role = :role
          AND deleted_at IS NULL
        ORDER BY id ASC
    ');
    $stmt->bindValue(':role', CCS_ADMIN_ROLE, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Deduplicate user rows by id (keeps first occurrence).
 *
 * @param array<int, array<string, mixed>> $users
 * @return array<int, array<string, mixed>>
 */
function complaint_nudge_unique_users(array $users): array
{
    $unique = [];
    foreach ($users as $user) {
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0 || isset($unique[$userId])) {
            continue;
        }
        $unique[$userId] = $user;
    }

    return array_values($unique);
}

function complaint_nudge_user_display_name(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    $username = trim((string) ($user['username'] ?? ''));

    return $username !== '' ? $username : 'User';
}

/**
 * Send in-app + email to one recipient and log it.
 * Each cron execution may send again for eligible complaints (no duplicate block).
 *
 * @return array{sent: bool, notification_id: ?int, email_sent: bool, reason: string}
 */
function complaint_nudge_notify_user(
    PDO $conn,
    int $complaintId,
    string $nudgeType,
    array $user,
    int $hours,
    string $title,
    string $message,
    string $emailSubject,
    string $emailBody,
    string $notificationModule,
    ?int $referenceId = null
): array {
    $result = [
        'sent' => false,
        'notification_id' => null,
        'email_sent' => false,
        'reason' => '',
    ];

    $userId = (int) ($user['id'] ?? 0);
    if ($complaintId <= 0 || $userId <= 0 || $hours <= 0) {
        $result['reason'] = 'invalid_recipient';
        return $result;
    }

    $notificationId = notification_create(
        $conn,
        $userId,
        $title,
        $message,
        $notificationModule,
        $complaintId
    );

    $email = trim((string) ($user['email'] ?? ''));
    $emailSent = false;
    if ($email !== '') {
        $emailSent = complaint_mail_send($email, $emailSubject, $emailBody);
    }

    // Log every send for audit; logging failure does not block the notification.
    complaint_nudge_log_record(
        $conn,
        $complaintId,
        $nudgeType,
        $userId,
        $hours,
        $referenceId,
        $notificationId,
        $emailSent
    );

    $result['sent'] = true;
    $result['notification_id'] = $notificationId;
    $result['email_sent'] = $emailSent;
    $result['reason'] = 'ok';

    return $result;
}

function complaint_nudge_log_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    notification_ensure_schema($conn);

    $table = complaint_nudge_log_table();

    $stmt = $conn->prepare('
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = \'public\'
          AND table_name = :table_name
        LIMIT 1
    ');
    $stmt->bindValue(':table_name', $table);
    $stmt->execute();

    if (!$stmt->fetchColumn()) {
        $conn->exec('
            CREATE TABLE complaint_nudge_logs (
                id SERIAL PRIMARY KEY,
                complaint_id INTEGER NOT NULL,
                nudge_type VARCHAR(50) NOT NULL,
                recipient_user_id INTEGER NOT NULL,
                reminder_hours INTEGER NOT NULL,
                reference_id INTEGER NULL,
                notification_id INTEGER NULL,
                email_sent SMALLINT NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // History index only — allow multiple sends per complaint / user / milestone.
        $conn->exec('
            CREATE INDEX IF NOT EXISTS complaint_nudge_logs_lookup_idx
                ON complaint_nudge_logs (complaint_id, nudge_type, recipient_user_id, reminder_hours)
        ');

        $conn->exec('
            CREATE INDEX IF NOT EXISTS complaint_nudge_logs_complaint_id_idx
                ON complaint_nudge_logs (complaint_id)
        ');

        $conn->exec('
            CREATE INDEX IF NOT EXISTS complaint_nudge_logs_type_recipient_idx
                ON complaint_nudge_logs (nudge_type, recipient_user_id)
        ');
    } else {
        // Remove legacy unique index so repeated cron runs can log each send.
        $conn->exec('DROP INDEX IF EXISTS complaint_nudge_logs_unique');
        $conn->exec('
            CREATE INDEX IF NOT EXISTS complaint_nudge_logs_lookup_idx
                ON complaint_nudge_logs (complaint_id, nudge_type, recipient_user_id, reminder_hours)
        ');
    }

    // Drop legacy per-nudge tables if they still exist
    foreach ([
        'complaint_open_nudge_logs',
        'complaint_dealer_service_nudge_logs',
        'complaint_ccs_closure_nudge_logs',
    ] as $legacyTable) {
        $conn->exec('DROP TABLE IF EXISTS ' . $legacyTable);
    }

    $ensured = true;
}

/**
 * @return array<int, bool> reminder_hours => true
 */
function complaint_nudge_log_sent_map(
    PDO $conn,
    int $complaintId,
    string $nudgeType,
    int $recipientUserId
): array {
    complaint_nudge_log_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT reminder_hours
        FROM complaint_nudge_logs
        WHERE complaint_id = :complaint_id
          AND nudge_type = :nudge_type
          AND recipient_user_id = :recipient_user_id
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':nudge_type', $nudgeType);
    $stmt->bindValue(':recipient_user_id', $recipientUserId, PDO::PARAM_INT);
    $stmt->execute();

    $map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $map[(int) $row['reminder_hours']] = true;
    }

    return $map;
}

function complaint_nudge_log_already_sent(
    PDO $conn,
    int $complaintId,
    string $nudgeType,
    int $recipientUserId,
    int $reminderHours
): bool {
    complaint_nudge_log_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT 1
        FROM complaint_nudge_logs
        WHERE complaint_id = :complaint_id
          AND nudge_type = :nudge_type
          AND recipient_user_id = :recipient_user_id
          AND reminder_hours = :reminder_hours
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':nudge_type', $nudgeType);
    $stmt->bindValue(':recipient_user_id', $recipientUserId, PDO::PARAM_INT);
    $stmt->bindValue(':reminder_hours', $reminderHours, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * Record a nudge send for audit/history.
 *
 * @param int|null $referenceId Optional cycle context (assignment_id / service_update_id)
 */
function complaint_nudge_log_record(
    PDO $conn,
    int $complaintId,
    string $nudgeType,
    int $recipientUserId,
    int $reminderHours,
    ?int $referenceId = null,
    ?int $notificationId = null,
    bool $emailSent = false
): bool {
    if ($complaintId <= 0 || $recipientUserId <= 0 || $reminderHours <= 0 || trim($nudgeType) === '') {
        return false;
    }

    complaint_nudge_log_ensure_schema($conn);

    try {
        $stmt = $conn->prepare('
            INSERT INTO complaint_nudge_logs (
                complaint_id,
                nudge_type,
                recipient_user_id,
                reminder_hours,
                reference_id,
                notification_id,
                email_sent,
                created_at
            ) VALUES (
                :complaint_id,
                :nudge_type,
                :recipient_user_id,
                :reminder_hours,
                :reference_id,
                :notification_id,
                :email_sent,
                CURRENT_TIMESTAMP
            )
        ');
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->bindValue(':nudge_type', trim($nudgeType));
        $stmt->bindValue(':recipient_user_id', $recipientUserId, PDO::PARAM_INT);
        $stmt->bindValue(':reminder_hours', $reminderHours, PDO::PARAM_INT);
        $stmt->bindValue(
            ':reference_id',
            $referenceId !== null && $referenceId > 0 ? $referenceId : null,
            $referenceId !== null && $referenceId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL
        );
        $stmt->bindValue(
            ':notification_id',
            $notificationId !== null && $notificationId > 0 ? $notificationId : null,
            $notificationId !== null && $notificationId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL
        );
        $stmt->bindValue(':email_sent', $emailSent ? 1 : 0, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    } catch (PDOException $e) {
        return false;
    }
}
