<?php

/**
 * Common complaint nudge log helpers.
 * Single table for Open / Dealer Service / CCS Closure nudge history.
 */

require_once dirname(__DIR__) . '/includes/notification_helpers.php';

const COMPLAINT_NUDGE_TYPE_OPEN_STATUS = 'open_status';
const COMPLAINT_NUDGE_TYPE_DEALER_SERVICE = 'dealer_service';
const COMPLAINT_NUDGE_TYPE_CCS_CLOSURE = 'ccs_closure';

function complaint_nudge_log_table(): string
{
    return 'complaint_nudge_logs';
}

function complaint_nudge_log_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    notification_ensure_schema($conn);

    $table = complaint_nudge_log_table();

    $stmt = $conn->query("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = '{$table}'
        LIMIT 1
    ");

    if (!$stmt->fetchColumn()) {
        $conn->exec("
            CREATE TABLE {$table} (
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
        ");

        // Duplicate prevention: same complaint + type + recipient + nudge level
        $conn->exec("
            CREATE UNIQUE INDEX complaint_nudge_logs_unique
                ON {$table} (complaint_id, nudge_type, recipient_user_id, reminder_hours)
        ");

        $conn->exec("
            CREATE INDEX complaint_nudge_logs_complaint_id_idx
                ON {$table} (complaint_id)
        ");

        $conn->exec("
            CREATE INDEX complaint_nudge_logs_type_recipient_idx
                ON {$table} (nudge_type, recipient_user_id)
        ");
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
 * Record a nudge send. Returns false on duplicate (unique violation).
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
        if (
            str_contains($e->getMessage(), 'complaint_nudge_logs_unique')
            || str_contains($e->getMessage(), 'duplicate key')
            || (string) $e->getCode() === '23505'
        ) {
            return false;
        }
        throw $e;
    }
}