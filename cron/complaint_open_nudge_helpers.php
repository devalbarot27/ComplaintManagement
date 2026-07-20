<?php

/**
 * Complaint Open Status Nudge helpers.
 * Sends 24h / 48h / 72h in-app + email reminders while status remains Open.
 * If age is already past 72h when evaluated, only the 72h reminder is sent
 * (24h / 48h are skipped). Each milestone is logged once per complaint.
 */

require_once dirname(__DIR__) . '/includes/complaint_status.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_mail_helpers.php';
require_once dirname(__DIR__) . '/includes/notification_helpers.php';
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

function complaint_open_nudge_in_app_message(int $complaintId, int $hours): string
{
    return 'Your complaint #' . $complaintId
        . ' is still in Open status and has not received any action for '
        . $hours . ' hours.';
}

function complaint_open_nudge_email_body(string $userName, int $complaintId, int $hours): string
{
    $name = trim($userName) !== '' ? trim($userName) : 'User';

    return implode("\r\n", [
        'Dear ' . $name . ',',
        '',
        'Your complaint #' . $complaintId
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
 * @return array{sent: bool, notification_id: ?int, email_sent: bool, skipped: bool, reason: string}
 */
function complaint_open_nudge_send_reminder(PDO $conn, array $complaint, int $hours): array
{
    $complaintId = (int) ($complaint['id'] ?? 0);
    $result = [
        'sent' => false,
        'notification_id' => null,
        'email_sent' => false,
        'skipped' => true,
        'reason' => '',
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

    $creator = complaint_open_nudge_resolve_creator($conn, $complaint);
    if ($creator === null) {
        $result['reason'] = 'no_creator';
        return $result;
    }

    $userId = (int) ($creator['id'] ?? 0);
    if ($userId <= 0) {
        $result['reason'] = 'no_creator';
        return $result;
    }

    if (complaint_nudge_log_already_sent(
        $conn,
        $complaintId,
        COMPLAINT_NUDGE_TYPE_OPEN_STATUS,
        $userId,
        $hours
    )) {
        $result['reason'] = 'already_sent';
        return $result;
    }

    $userName = trim((string) ($creator['name'] ?? ''));
    if ($userName === '') {
        $userName = trim((string) ($creator['username'] ?? ''));
    }
    $email = trim((string) ($creator['email'] ?? ''));

    $title = 'Complaint Pending';
    $message = complaint_open_nudge_in_app_message($complaintId, $hours);

    $notificationId = notification_create(
        $conn,
        $userId,
        $title,
        $message,
        'complaint-open',
        $complaintId
    );

    $emailSent = false;
    if ($email !== '') {
        $emailSent = complaint_mail_send(
            $email,
            'Reminder: Complaint Still Open',
            complaint_open_nudge_email_body($userName, $complaintId, $hours)
        );
    }

    $recorded = complaint_nudge_log_record(
        $conn,
        $complaintId,
        COMPLAINT_NUDGE_TYPE_OPEN_STATUS,
        $userId,
        $hours,
        null,
        $notificationId,
        $emailSent
    );

    if (!$recorded) {
        $result['reason'] = 'duplicate_race';
        return $result;
    }

    $result['sent'] = true;
    $result['skipped'] = false;
    $result['notification_id'] = $notificationId;
    $result['email_sent'] = $emailSent;
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
    $milestones = complaint_open_nudge_hours();

    foreach ($complaints as $complaint) {
        $summary['processed']++;
        $complaintId = (int) $complaint['id'];
        $ageHours = (float) ($complaint['age_hours'] ?? 0);

        $creator = complaint_open_nudge_resolve_creator($conn, $complaint);
        $recipientUserId = (int) ($creator['id'] ?? 0);
        $sentMap = $recipientUserId > 0
            ? complaint_nudge_log_sent_map(
                $conn,
                $complaintId,
                COMPLAINT_NUDGE_TYPE_OPEN_STATUS,
                $recipientUserId
            )
            : [];

        foreach ($milestones as $hours) {
            if ($ageHours < $hours) {
                continue;
            }

            // Past 72h: send only the 72h reminder (skip missed 24h / 48h).
            if ($ageHours >= 72 && $hours < 72) {
                continue;
            }

            if (!empty($sentMap[$hours])) {
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
                if ($sendResult['email_sent']) {
                    $summary['emails_sent']++;
                }
                if (!empty($sendResult['notification_id'])) {
                    $summary['notifications_created']++;
                }
                $sentMap[$hours] = true;
            }
        }
    }

    return $summary;
}