<?php

/**
 * Dealer User Service Update Nudge helpers.
 *
 * Recipients: Complaint Assigned User + CCS Admin users.
 * Channels: In-App Notification + Email.
 *
 * Only the highest applicable milestone is sent based on current age:
 * - > 24h and ≤ 48h → 24h only
 * - > 48h and ≤ 72h → 48h only
 * - > 72h → 72h only
 * Logged for audit on each cron run (repeat sends allowed).
 */

require_once dirname(__DIR__) . '/includes/complaint_status.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';
require_once __DIR__ . '/complaint_nudge_log_helpers.php';

function complaint_dealer_service_nudge_hours(): array
{
    return [24, 48, 72];
}

/**
 * @return array<int, array<string, mixed>>
 */
function complaint_dealer_service_nudge_fetch_eligible(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            c.id AS complaint_id,
            c.status,
            ca.id AS assignment_id,
            ca.assigned_to,
            ca.assign_complaint,
            ca.assign_complaint_datetime,
            ca.is_service_updated,
            EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - ca.assign_complaint_datetime)) / 3600.0 AS age_hours
        FROM complaints c
        INNER JOIN LATERAL (
            SELECT
                ca_inner.id,
                ca_inner.assigned_to,
                ca_inner.assign_complaint,
                ca_inner.assign_complaint_datetime,
                ca_inner.is_service_updated
            FROM complaint_assignments ca_inner
            WHERE ca_inner.complaint_id = c.id
            ORDER BY ca_inner.assign_complaint_datetime DESC, ca_inner.id DESC
            LIMIT 1
        ) ca ON TRUE
        INNER JOIN user_master um
            ON um.id = ca.assigned_to
           AND um.deleted_at IS NULL
           AND um.role = :dealer_role
        WHERE c.deleted_at IS NULL
          AND c.status IN (:status_in_progress, :status_reopen)
          AND ca.is_service_updated = 0
          AND ca.assigned_to IS NOT NULL
          AND ca.assign_complaint_datetime IS NOT NULL
        ORDER BY ca.assign_complaint_datetime ASC
    ');

    $stmt->bindValue(':dealer_role', DEALER_USER_ROLE, PDO::PARAM_INT);
    $stmt->bindValue(':status_in_progress', COMPLAINT_STATUS_IN_PROGRESS, PDO::PARAM_INT);
    $stmt->bindValue(':status_reopen', COMPLAINT_STATUS_REOPEN, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function complaint_dealer_service_nudge_still_eligible(PDO $conn, int $complaintId, int $assignmentId): bool
{
    $stmt = $conn->prepare('
        SELECT 1
        FROM complaints c
        INNER JOIN complaint_assignments ca
            ON ca.id = :assignment_id
           AND ca.complaint_id = c.id
        WHERE c.id = :complaint_id
          AND c.deleted_at IS NULL
          AND c.status IN (:status_in_progress, :status_reopen)
          AND ca.is_service_updated = 0
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
    $stmt->bindValue(':status_in_progress', COMPLAINT_STATUS_IN_PROGRESS, PDO::PARAM_INT);
    $stmt->bindValue(':status_reopen', COMPLAINT_STATUS_REOPEN, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function complaint_dealer_service_nudge_status_label(int $status): string
{
    if ($status === COMPLAINT_STATUS_REOPEN) {
        return 'Re-Open';
    }

    return 'In Progress';
}

/**
 * Assigned user + CCS Admins (unique by user id).
 *
 * @return array<int, array<string, mixed>>
 */
function complaint_dealer_service_nudge_resolve_recipients(PDO $conn, int $assignedTo): array
{
    $recipients = [];

    if ($assignedTo > 0) {
        $assignee = user_get_by_id($conn, $assignedTo);
        if ($assignee !== null) {
            $recipients[] = $assignee;
        }
    }

    foreach (complaint_nudge_fetch_ccs_admins($conn) as $admin) {
        $recipients[] = $admin;
    }

    return complaint_nudge_unique_users($recipients);
}

function complaint_dealer_service_nudge_in_app_message(int $complaintId, string $statusLabel, int $hours): string
{
    return 'Complaint #' . $complaintId
        . ' is still in ' . $statusLabel
        . ' status and has not received a service update for '
        . $hours . ' hours.';
}

function complaint_dealer_service_nudge_email_body(
    string $userName,
    int $complaintId,
    string $statusLabel,
    int $hours
): string {
    $name = trim($userName) !== '' ? trim($userName) : 'User';

    return implode("\r\n", [
        'Dear ' . $name . ',',
        '',
        'Complaint #' . $complaintId
            . ' is still in ' . $statusLabel
            . ' status and has not received a service update for '
            . $hours . ' hours.',
        '',
        'Please update the service details or contact the concerned team if required.',
        '',
        'Regards,',
        'Support Team',
    ]);
}

/**
 * @param array<string, mixed> $row
 * @return array{
 *   sent: bool,
 *   notifications_created: int,
 *   emails_sent: int,
 *   skipped: bool,
 *   reason: string,
 *   recipients: array<int, array<string, mixed>>
 * }
 */
function complaint_dealer_service_nudge_send_reminder(PDO $conn, array $row, int $hours): array
{
    $complaintId = (int) ($row['complaint_id'] ?? 0);
    $assignmentId = (int) ($row['assignment_id'] ?? 0);
    $assignedTo = (int) ($row['assigned_to'] ?? 0);
    $status = (int) ($row['status'] ?? 0);

    $result = [
        'sent' => false,
        'notifications_created' => 0,
        'emails_sent' => 0,
        'skipped' => true,
        'reason' => '',
        'recipients' => [],
    ];

    if ($complaintId <= 0 || $assignmentId <= 0) {
        $result['reason'] = 'invalid_row';
        return $result;
    }

    if (!complaint_dealer_service_nudge_still_eligible($conn, $complaintId, $assignmentId)) {
        $result['reason'] = 'not_eligible';
        return $result;
    }

    $recipients = complaint_dealer_service_nudge_resolve_recipients($conn, $assignedTo);
    if ($recipients === []) {
        $result['reason'] = 'no_recipients';
        return $result;
    }

    $statusLabel = complaint_dealer_service_nudge_status_label($status);
    $title = 'Service Update Pending';
    $message = complaint_dealer_service_nudge_in_app_message($complaintId, $statusLabel, $hours);
    $anySent = false;

    foreach ($recipients as $user) {
        if (!complaint_dealer_service_nudge_still_eligible($conn, $complaintId, $assignmentId)) {
            $result['reason'] = 'not_eligible_mid_send';
            break;
        }

        $send = complaint_nudge_notify_user(
            $conn,
            $complaintId,
            COMPLAINT_NUDGE_TYPE_DEALER_SERVICE,
            $user,
            $hours,
            $title,
            $message,
            'Reminder: Service Update Pending',
            complaint_dealer_service_nudge_email_body(
                complaint_nudge_user_display_name($user),
                $complaintId,
                $statusLabel,
                $hours
            ),
            'assigned-complaint',
            $assignmentId
        );

        $result['recipients'][] = [
            'user_id' => (int) ($user['id'] ?? 0),
            'result' => $send,
        ];

        if (!$send['sent']) {
            continue;
        }

        $anySent = true;
        if (!empty($send['notification_id'])) {
            $result['notifications_created']++;
        }
        if (!empty($send['email_sent'])) {
            $result['emails_sent']++;
        }
    }

    if (!$anySent) {
        $result['reason'] = $result['reason'] !== '' ? $result['reason'] : 'already_sent';
        return $result;
    }

    $result['sent'] = true;
    $result['skipped'] = false;
    $result['reason'] = 'ok';

    return $result;
}

/**
 * @return array{
 *   processed: int,
 *   reminders_sent: int,
 *   emails_sent: int,
 *   notifications_created: int,
 *   details: array<int, array<string, mixed>>
 * }
 */
function complaint_dealer_service_nudge_run(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $summary = [
        'processed' => 0,
        'reminders_sent' => 0,
        'emails_sent' => 0,
        'notifications_created' => 0,
        'details' => [],
    ];

    $rows = complaint_dealer_service_nudge_fetch_eligible($conn);

    foreach ($rows as $row) {
        $summary['processed']++;
        $complaintId = (int) $row['complaint_id'];
        $assignmentId = (int) $row['assignment_id'];
        $ageHours = (float) ($row['age_hours'] ?? 0);
        $hours = complaint_nudge_applicable_hours($ageHours);

        if ($hours === null) {
            continue;
        }

        $sendResult = complaint_dealer_service_nudge_send_reminder($conn, $row, $hours);

        $summary['details'][] = [
            'complaint_id' => $complaintId,
            'assignment_id' => $assignmentId,
            'hours' => $hours,
            'age_hours' => round($ageHours, 2),
            'result' => $sendResult,
        ];

        if ($sendResult['sent']) {
            $summary['reminders_sent']++;
            $summary['emails_sent'] += (int) $sendResult['emails_sent'];
            $summary['notifications_created'] += (int) $sendResult['notifications_created'];
        }
    }

    return $summary;
}
