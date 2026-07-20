<?php

/**
 * Complaint Open Status Nudge helpers.
 *
 * Recipients: Complaint Created User + CCS Admin users.
 * Channels: In-App Notification + Email.
 *
 * Only the highest applicable milestone is sent based on current age:
 * - > 24h and ≤ 48h → 24h only
 * - > 48h and ≤ 72h → 48h only
 * - > 72h → 72h only
 * Logged once per complaint + user + nudge type + hours.
 */

require_once dirname(__DIR__) . '/includes/complaint_status.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';
require_once __DIR__ . '/complaint_nudge_log_helpers.php';

function complaint_open_nudge_hours(): array
{
    return [24, 48, 72];
}

/**
 * @return array<int, array<string, mixed>>
 */
function complaint_open_nudge_fetch_eligible(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            c.id,
            c.added_by,
            c.username,
            c.created_at,
            c.status,
            EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - c.created_at)) / 3600.0 AS age_hours
        FROM complaints c
        WHERE c.status = :open_status
          AND c.deleted_at IS NULL
        ORDER BY c.created_at ASC
    ');
    $stmt->bindValue(':open_status', COMPLAINT_STATUS_OPEN, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function complaint_open_nudge_resolve_creator(PDO $conn, array $complaint): ?array
{
    $addedBy = (int) ($complaint['added_by'] ?? 0);
    if ($addedBy > 0) {
        $user = user_get_by_id($conn, $addedBy);
        if ($user !== null) {
            return $user;
        }
    }

    $username = trim((string) ($complaint['username'] ?? ''));
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Creator + CCS Admins (unique by user id).
 *
 * @return array<int, array<string, mixed>>
 */
function complaint_open_nudge_resolve_recipients(PDO $conn, array $complaint): array
{
    $recipients = [];

    $creator = complaint_open_nudge_resolve_creator($conn, $complaint);
    if ($creator !== null) {
        $recipients[] = $creator;
    }

    foreach (complaint_nudge_fetch_ccs_admins($conn) as $admin) {
        $recipients[] = $admin;
    }

    return complaint_nudge_unique_users($recipients);
}

function complaint_open_nudge_in_app_message(int $complaintId, int $hours): string
{
    return 'Complaint #' . $complaintId
        . ' is still in Open status and has not received any action for '
        . $hours . ' hours.';
}

function complaint_open_nudge_email_body(string $userName, int $complaintId, int $hours): string
{
    $name = trim($userName) !== '' ? trim($userName) : 'User';

    return implode("\r\n", [
        'Dear ' . $name . ',',
        '',
        'Complaint #' . $complaintId
            . ' is still in Open status and has not received any action for '
            . $hours . ' hours.',
        '',
        'Please review the complaint or contact the concerned team if required.',
        '',
        'Regards,',
        'Support Team',
    ]);
}

/**
 * @return array{
 *   sent: bool,
 *   notifications_created: int,
 *   emails_sent: int,
 *   skipped: bool,
 *   reason: string,
 *   recipients: array<int, array<string, mixed>>
 * }
 */
function complaint_open_nudge_send_reminder(PDO $conn, array $complaint, int $hours): array
{
    $complaintId = (int) ($complaint['id'] ?? 0);
    $result = [
        'sent' => false,
        'notifications_created' => 0,
        'emails_sent' => 0,
        'skipped' => true,
        'reason' => '',
        'recipients' => [],
    ];

    if ($complaintId <= 0) {
        $result['reason'] = 'invalid_complaint';
        return $result;
    }

    $statusStmt = $conn->prepare('
        SELECT status
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $statusStmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $statusStmt->execute();
    $currentStatus = $statusStmt->fetchColumn();

    if ($currentStatus === false || (int) $currentStatus !== COMPLAINT_STATUS_OPEN) {
        $result['reason'] = 'not_open';
        return $result;
    }

    $recipients = complaint_open_nudge_resolve_recipients($conn, $complaint);
    if ($recipients === []) {
        $result['reason'] = 'no_recipients';
        return $result;
    }

    $title = 'Complaint Pending';
    $message = complaint_open_nudge_in_app_message($complaintId, $hours);
    $anySent = false;

    foreach ($recipients as $user) {
        $send = complaint_nudge_notify_user(
            $conn,
            $complaintId,
            COMPLAINT_NUDGE_TYPE_OPEN_STATUS,
            $user,
            $hours,
            $title,
            $message,
            'Reminder: Complaint Still Open',
            complaint_open_nudge_email_body(
                complaint_nudge_user_display_name($user),
                $complaintId,
                $hours
            ),
            'complaint-open'
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
        $result['reason'] = 'already_sent';
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
function complaint_open_nudge_run(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $summary = [
        'processed' => 0,
        'reminders_sent' => 0,
        'emails_sent' => 0,
        'notifications_created' => 0,
        'details' => [],
    ];

    $complaints = complaint_open_nudge_fetch_eligible($conn);

    foreach ($complaints as $complaint) {
        $summary['processed']++;
        $complaintId = (int) $complaint['id'];
        $ageHours = (float) ($complaint['age_hours'] ?? 0);
        $hours = complaint_nudge_applicable_hours($ageHours);

        if ($hours === null) {
            continue;
        }

        $sendResult = complaint_open_nudge_send_reminder($conn, $complaint, $hours);

        $summary['details'][] = [
            'complaint_id' => $complaintId,
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
