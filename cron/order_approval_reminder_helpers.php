<?php

/**
 * Weekly reminder emails for pending order approvals.
 *
 * A pending order-level request is reminded once every 7 days, starting
 * 7 days after it was assigned to the current approver. Reminders stop
 * when the request is approved, rejected, or cancelled.
 */

require_once dirname(__DIR__) . '/includes/order_approval_helpers.php';

function order_approval_reminder_interval_days(): int
{
    return 7;
}

function order_approval_reminder_log_table(): string
{
    return 'order_approval_reminder_logs';
}

function order_approval_reminder_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    order_approval_ensure_schema($conn);

    $conn->exec('
        CREATE TABLE IF NOT EXISTS order_approval_reminder_logs (
            id SERIAL PRIMARY KEY,
            request_id INTEGER NOT NULL,
            recipient_user_id INTEGER NOT NULL,
            approval_level VARCHAR(20) NOT NULL,
            email_sent SMALLINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $conn->exec('
        CREATE INDEX IF NOT EXISTS order_approval_reminder_logs_lookup_idx
            ON order_approval_reminder_logs (request_id, recipient_user_id, approval_level, created_at)
    ');

    $ensured = true;
}

/**
 * Pending requests whose current approver has been waiting at least 7 days
 * and has not been emailed a reminder for this level in the last 7 days.
 *
 * @return array<int, array<string, mixed>>
 */
function order_approval_reminder_fetch_due(PDO $conn): array
{
    order_approval_reminder_ensure_schema($conn);

    $days = max(1, order_approval_reminder_interval_days());
    $interval = (int) $days . ' days';
    $stmt = $conn->query('
        SELECT
            pending.id,
            pending.order_refno,
            pending.assigned_to_user_id,
            pending.current_level,
            pending.requested_by,
            pending.requested_by_user_id,
            pending.created_at,
            pending.pending_since
        FROM (
            SELECT
                r.id,
                r.order_refno,
                r.assigned_to_user_id,
                COALESCE(NULLIF(TRIM(r.current_level), \'\'), NULLIF(TRIM(r.approval_level), \'\'), \'level_1\') AS current_level,
                r.requested_by,
                r.requested_by_user_id,
                r.created_at,
                CASE
                    WHEN COALESCE(NULLIF(TRIM(r.current_level), \'\'), NULLIF(TRIM(r.approval_level), \'\'), \'level_1\') = \'level_2\' THEN
                        COALESCE(
                            (
                                SELECT MAX(h.created_at)
                                FROM order_approval_history h
                                WHERE h.request_id = r.id
                                  AND h.action = \'approved\'
                            ),
                            r.created_at
                        )
                    ELSE r.created_at
                END AS pending_since
            FROM order_approval_requests r
            WHERE r.status = \'pending\'
              AND COALESCE(r.assigned_to_user_id, 0) > 0
              AND COALESCE(NULLIF(TRIM(r.order_refno), \'\'), \'\') <> \'\'
              AND COALESCE(r.cart_item_id, 0) = 0
        ) pending
        WHERE pending.pending_since <= CURRENT_TIMESTAMP - INTERVAL \'' . $interval . '\'
          AND NOT EXISTS (
              SELECT 1
              FROM order_approval_reminder_logs l
              WHERE l.request_id = pending.id
                AND l.recipient_user_id = pending.assigned_to_user_id
                AND l.approval_level = pending.current_level
                AND l.email_sent = 1
                AND l.created_at > CURRENT_TIMESTAMP - INTERVAL \'' . $interval . '\'
          )
        ORDER BY pending.pending_since ASC, pending.id ASC
    ');
    if ($stmt === false) {
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Same actionable checks as the Approvals queue.
 *
 * @param array<string, mixed> $row
 */
function order_approval_reminder_is_actionable(PDO $conn, array $row): bool
{
    $row = order_approval_hydrate_request($row);
    $refno = trim((string) ($row['refno'] ?? ''));
    if ($refno === '') {
        return false;
    }

    $lines = order_approval_order_lines($conn, $refno);
    if ($lines === [] || !order_approval_lines_need_approval($lines)) {
        return false;
    }

    $header = $lines[0];
    if (trim((string) ($header['order_number'] ?? '')) !== '') {
        return false;
    }

    $level = (string) ($row['current_level'] ?? 'level_1');
    if ($level === 'level_2' && empty($header['l1_approved_user_id'])) {
        $requesterId = order_approval_order_requester_user_id($conn, $header, $row);
        if (!order_approval_requester_skips_level1($conn, $requesterId)) {
            return false;
        }
    }

    return true;
}

function order_approval_reminder_log(
    PDO $conn,
    int $requestId,
    int $recipientUserId,
    string $level,
    bool $emailSent
): void {
    if ($requestId <= 0 || $recipientUserId <= 0 || ($level !== 'level_1' && $level !== 'level_2')) {
        return;
    }

    order_approval_reminder_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO order_approval_reminder_logs (
            request_id,
            recipient_user_id,
            approval_level,
            email_sent,
            created_at
        ) VALUES (
            :request_id,
            :recipient_user_id,
            :approval_level,
            :email_sent,
            CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':request_id', $requestId, PDO::PARAM_INT);
    $stmt->bindValue(':recipient_user_id', $recipientUserId, PDO::PARAM_INT);
    $stmt->bindValue(':approval_level', $level);
    $stmt->bindValue(':email_sent', $emailSent ? 1 : 0, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * @return array{
 *   processed: int,
 *   reminders_sent: int,
 *   emails_sent: int,
 *   notifications_created: int,
 *   skipped: int,
 *   failed: int
 * }
 */
function order_approval_reminder_run(PDO $conn): array
{
    $summary = [
        'processed' => 0,
        'reminders_sent' => 0,
        'emails_sent' => 0,
        'notifications_created' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    $due = order_approval_reminder_fetch_due($conn);
    foreach ($due as $row) {
        $summary['processed']++;

        $requestId = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['assigned_to_user_id'] ?? 0);
        $refno = trim((string) ($row['order_refno'] ?? ''));
        $level = (string) ($row['current_level'] ?? 'level_1');
        if ($level !== 'level_2') {
            $level = 'level_1';
        }

        if ($requestId <= 0 || $userId <= 0 || $refno === '' || !order_approval_reminder_is_actionable($conn, $row)) {
            $summary['skipped']++;
            continue;
        }

        if (order_approval_approver_mail_recipient($conn, $userId) === null) {
            $summary['skipped']++;
            continue;
        }

        $emailSent = order_approval_send_reminder_email($conn, $userId, $refno, $level);
        if (!$emailSent) {
            $summary['failed']++;
            continue;
        }

        order_approval_reminder_log($conn, $requestId, $userId, $level, true);

        $levelLabel = order_approval_level_label($level);
        $notificationId = notification_create(
            $conn,
            $userId,
            $levelLabel . ' reminder',
            'Order ' . $refno . ' is still waiting for ' . $levelLabel . '.',
            'order-approval',
            $requestId
        );
        if ($notificationId !== null && $notificationId > 0) {
            $summary['notifications_created']++;
        }

        $summary['reminders_sent']++;
        $summary['emails_sent']++;
    }

    return $summary;
}
