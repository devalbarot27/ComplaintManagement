<?php

/**
 * Weekly reminder emails for pending FOC Parts approvals.
 *
 * A pending claim is reminded once every 7 days, starting 7 days after it
 * was assigned to the current approver. Level 2 starts its own 7-day clock
 * when Level 1 approves, or when the claim is submitted straight to Level 2.
 * Reminders stop when the claim is approved, rejected, or deleted.
 */

require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';

function foc_parts_reminder_interval_days(): int
{
    return 7;
}

function foc_parts_reminder_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    warranty_claims_ensure_schema($conn);

    $conn->exec('
        CREATE TABLE IF NOT EXISTS foc_parts_reminder_logs (
            id SERIAL PRIMARY KEY,
            claim_id INTEGER NOT NULL,
            recipient_user_id INTEGER NOT NULL,
            approval_level VARCHAR(20) NOT NULL,
            email_sent SMALLINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $conn->exec('
        CREATE INDEX IF NOT EXISTS foc_parts_reminder_logs_lookup_idx
            ON foc_parts_reminder_logs (claim_id, recipient_user_id, approval_level, created_at)
    ');

    $ensured = true;
}

/**
 * Pending FOC claims whose current approver has been waiting at least 7 days
 * and has not already been reminded for this level in the last 7 days.
 *
 * @return array<int, array<string, mixed>>
 */
function foc_parts_reminder_fetch_due(PDO $conn): array
{
    foc_parts_reminder_ensure_schema($conn);

    $days = max(1, foc_parts_reminder_interval_days());
    $interval = (int) $days . ' days';
    $pending = $conn->quote(FOC_STAGE_PENDING);
    $approved = $conn->quote(FOC_STAGE_APPROVED);
    $notRequired = $conn->quote(FOC_STAGE_NOT_REQUIRED);

    $stmt = $conn->query('
        SELECT
            due.id,
            due.approver_user_id,
            due.approval_level,
            due.pending_since,
            due.items_summary
        FROM (
            SELECT
                fc.id,
                CASE
                    WHEN fc.l1_status = ' . $pending . ' THEN fc.l1_approver_user_id
                    ELSE fc.l2_approver_user_id
                END AS approver_user_id,
                CASE
                    WHEN fc.l1_status = ' . $pending . ' THEN \'l1\'
                    ELSE \'l2\'
                END AS approval_level,
                CASE
                    WHEN fc.l1_status = ' . $pending . ' THEN fc.updated_at
                    ELSE COALESCE(fc.l1_at, fc.updated_at)
                END AS pending_since,
                (
                    SELECT STRING_AGG(fci.part_number || \' x\' || fci.qty, \', \' ORDER BY fci.id)
                    FROM foc_claim_items fci
                    WHERE fci.foc_claim_id = fc.id
                ) AS items_summary
            FROM foc_claims fc
            WHERE fc.deleted_at IS NULL
              AND (
                    fc.l1_status = ' . $pending . '
                    OR (
                        fc.l1_status IN (' . $approved . ', ' . $notRequired . ')
                        AND fc.l2_status = ' . $pending . '
                    )
              )
        ) due
        WHERE COALESCE(due.approver_user_id, 0) > 0
          AND due.pending_since <= CURRENT_TIMESTAMP - INTERVAL \'' . $interval . '\'
          AND NOT EXISTS (
              SELECT 1
              FROM foc_parts_reminder_logs l
              WHERE l.claim_id = due.id
                AND l.recipient_user_id = due.approver_user_id
                AND l.approval_level = due.approval_level
                AND l.created_at > CURRENT_TIMESTAMP - INTERVAL \'' . $interval . '\'
          )
        ORDER BY due.pending_since ASC, due.id ASC
    ');
    if ($stmt === false) {
        return [];
    }

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function foc_parts_reminder_log(
    PDO $conn,
    int $claimId,
    int $recipientUserId,
    string $level,
    bool $emailSent
): void {
    if ($claimId <= 0 || $recipientUserId <= 0 || ($level !== 'l1' && $level !== 'l2')) {
        return;
    }

    foc_parts_reminder_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO foc_parts_reminder_logs (
            claim_id,
            recipient_user_id,
            approval_level,
            email_sent,
            created_at
        ) VALUES (
            :claim_id,
            :recipient_user_id,
            :approval_level,
            :email_sent,
            CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':claim_id', $claimId, PDO::PARAM_INT);
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
function foc_parts_reminder_run(PDO $conn): array
{
    $summary = [
        'processed' => 0,
        'reminders_sent' => 0,
        'emails_sent' => 0,
        'notifications_created' => 0,
        'skipped' => 0,
        'failed' => 0,
    ];

    foreach (foc_parts_reminder_fetch_due($conn) as $row) {
        $summary['processed']++;

        $claimId = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['approver_user_id'] ?? 0);
        $level = (string) ($row['approval_level'] ?? '');
        if ($level !== 'l2') {
            $level = 'l1';
        }
        $detail = trim((string) ($row['items_summary'] ?? ''));

        if ($claimId <= 0 || $userId <= 0) {
            $summary['skipped']++;
            continue;
        }

        $levelLabel = $level === 'l2' ? 'Level 2 Approval' : 'Level 1 Approval';
        $emailSent = false;
        if (warranty_claims_approver_mail_recipient($conn, $userId) !== null) {
            $emailSent = foc_parts_send_reminder_email($conn, $userId, $claimId, $level, $detail);
        }

        $notificationId = notification_create(
            $conn,
            $userId,
            $levelLabel . ' reminder',
            'FOC Parts claim #' . $claimId . ' is still waiting for ' . $levelLabel . '.',
            'foc-parts',
            $claimId
        );
        $notificationCreated = $notificationId !== null && $notificationId > 0;

        if (!$emailSent && !$notificationCreated) {
            $summary['failed']++;
            continue;
        }

        foc_parts_reminder_log($conn, $claimId, $userId, $level, $emailSent);
        if ($notificationCreated) {
            $summary['notifications_created']++;
        }
        if ($emailSent) {
            $summary['emails_sent']++;
        }
        $summary['reminders_sent']++;
    }

    return $summary;
}