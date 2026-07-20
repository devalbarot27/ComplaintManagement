<?php

/**
 * CCS Admin Call Closure Nudge helpers.
 *
 * Sends 24h / 48h / 72h in-app + email reminders to CCS Admins
 * while a complaint remains in Pending With HO ("Call Closure")
 * without a closure action (Yes/No).
 * If age is already past 72h when evaluated, only the 72h reminder is sent
 * (24h / 48h are skipped). Each milestone is logged once per complaint.
 */

require_once dirname(__DIR__) . '/includes/complaint_status.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_mail_helpers.php';
require_once dirname(__DIR__) . '/includes/notification_helpers.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';
require_once __DIR__ . '/complaint_nudge_log_helpers.php';

function complaint_ccs_closure_nudge_hours(): array
{
    return [24, 48, 72];
}

/**
 * @return array<int, array<string, mixed>>
 */
function complaint_ccs_closure_nudge_fetch_eligible(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            c.id AS complaint_id,
            c.status,
            su.id AS service_update_id,
            su.created_at AS pending_ho_since,
            EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - su.created_at)) / 3600.0 AS age_hours
        FROM complaints c
        INNER JOIN LATERAL (
            SELECT
                csu.id,
                csu.created_at
            FROM complaint_service_updates csu
            WHERE csu.complaint_id = c.id
            ORDER BY csu.created_at DESC, csu.id DESC
            LIMIT 1
        ) su ON TRUE
        WHERE c.deleted_at IS NULL
          AND c.status = :pending_ho_status
          AND NOT EXISTS (
              SELECT 1
              FROM complaint_closures cc
              WHERE cc.complaint_id = c.id
                AND cc.created_at >= su.created_at
          )
        ORDER BY su.created_at ASC
    ');
    $stmt->bindValue(':pending_ho_status', COMPLAINT_STATUS_PENDING_HO, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function complaint_ccs_closure_nudge_fetch_ccs_admins(PDO $conn): array
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

function complaint_ccs_closure_nudge_still_eligible(
    PDO $conn,
    int $complaintId,
    int $serviceUpdateId
): bool {
    $stmt = $conn->prepare('
        SELECT 1
        FROM complaints c
        INNER JOIN complaint_service_updates su
            ON su.id = :service_update_id
           AND su.complaint_id = c.id
        WHERE c.id = :complaint_id
          AND c.deleted_at IS NULL
          AND c.status = :pending_ho_status
          AND NOT EXISTS (
              SELECT 1
              FROM complaint_closures cc
              WHERE cc.complaint_id = c.id
                AND cc.created_at >= su.created_at
          )
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':service_update_id', $serviceUpdateId, PDO::PARAM_INT);
    $stmt->bindValue(':pending_ho_status', COMPLAINT_STATUS_PENDING_HO, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function complaint_ccs_closure_nudge_in_app_message(int $complaintId, int $hours): string
{
    return 'Complaint #' . $complaintId
        . ' is pending Call Closure and has not received any CCS Admin action for '
        . $hours . ' hours.';
}

function complaint_ccs_closure_nudge_email_body(string $userName, int $complaintId, int $hours): string
{
    $name = trim($userName) !== '' ? trim($userName) : 'User';

    return implode("\r\n", [
        'Dear ' . $name . ',',
        '',
        'Complaint #' . $complaintId
            . ' is pending Call Closure (Pending With HO) and has not received any action for '
            . $hours . ' hours.',
        '',
        'Please review the complaint and complete Call Closure (Yes / No) if required.',
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
 *   reason: string
 * }
 */
function complaint_ccs_closure_nudge_send_reminder(PDO $conn, array $row, int $hours): array
{
    $complaintId = (int) ($row['complaint_id'] ?? 0);
    $serviceUpdateId = (int) ($row['service_update_id'] ?? 0);

    $result = [
        'sent' => false,
        'notifications_created' => 0,
        'emails_sent' => 0,
        'skipped' => true,
        'reason' => '',
    ];

    if ($complaintId <= 0 || $serviceUpdateId <= 0) {
        $result['reason'] = 'invalid_row';
        return $result;
    }

    if (!complaint_ccs_closure_nudge_still_eligible($conn, $complaintId, $serviceUpdateId)) {
        $result['reason'] = 'not_eligible';
        return $result;
    }

    $admins = complaint_ccs_closure_nudge_fetch_ccs_admins($conn);
    if ($admins === []) {
        $result['reason'] = 'no_ccs_admin';
        return $result;
    }

    $title = 'Call Closure Pending';
    $message = complaint_ccs_closure_nudge_in_app_message($complaintId, $hours);
    $notificationsCreated = 0;
    $emailsSent = 0;
    $anySent = false;

    foreach ($admins as $admin) {
        if (!complaint_ccs_closure_nudge_still_eligible($conn, $complaintId, $serviceUpdateId)) {
            $result['reason'] = 'not_eligible_mid_send';
            break;
        }

        $userId = (int) ($admin['id'] ?? 0);
        if ($userId <= 0) {
            continue;
        }

        if (complaint_nudge_log_already_sent(
            $conn,
            $complaintId,
            COMPLAINT_NUDGE_TYPE_CCS_CLOSURE,
            $userId,
            $hours
        )) {
            continue;
        }

        $userName = trim((string) ($admin['name'] ?? ''));
        if ($userName === '') {
            $userName = trim((string) ($admin['username'] ?? ''));
        }
        $email = trim((string) ($admin['email'] ?? ''));

        $notificationId = notification_create(
            $conn,
            $userId,
            $title,
            $message,
            'complaint-closure',
            $complaintId
        );
        if ($notificationId !== null) {
            $notificationsCreated++;
        }

        $emailSent = false;
        if ($email !== '') {
            $emailSent = complaint_mail_send(
                $email,
                'Reminder: Call Closure Pending',
                complaint_ccs_closure_nudge_email_body($userName, $complaintId, $hours)
            );
            if ($emailSent) {
                $emailsSent++;
            }
        }

        $recorded = complaint_nudge_log_record(
            $conn,
            $complaintId,
            COMPLAINT_NUDGE_TYPE_CCS_CLOSURE,
            $userId,
            $hours,
            $serviceUpdateId,
            $notificationId,
            $emailSent
        );

        if ($recorded) {
            $anySent = true;
        }
    }

    if (!$anySent) {
        $result['reason'] = $result['reason'] !== '' ? $result['reason'] : 'already_sent';
        return $result;
    }

    $result['sent'] = true;
    $result['skipped'] = false;
    $result['notifications_created'] = $notificationsCreated;
    $result['emails_sent'] = $emailsSent;
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
function complaint_ccs_closure_nudge_run(PDO $conn): array
{
    complaint_nudge_log_ensure_schema($conn);

    $summary = [
        'processed' => 0,
        'reminders_sent' => 0,
        'emails_sent' => 0,
        'notifications_created' => 0,
        'details' => [],
    ];

    $rows = complaint_ccs_closure_nudge_fetch_eligible($conn);
    $milestones = complaint_ccs_closure_nudge_hours();

    foreach ($rows as $row) {
        $summary['processed']++;
        $complaintId = (int) $row['complaint_id'];
        $serviceUpdateId = (int) $row['service_update_id'];
        $ageHours = (float) ($row['age_hours'] ?? 0);

        foreach ($milestones as $hours) {
            if ($ageHours < $hours) {
                continue;
            }

            // Past 72h: send only the 72h reminder (skip missed 24h / 48h).
            if ($ageHours >= 72 && $hours < 72) {
                continue;
            }

            $sendResult = complaint_ccs_closure_nudge_send_reminder($conn, $row, $hours);

            $summary['details'][] = [
                'complaint_id' => $complaintId,
                'service_update_id' => $serviceUpdateId,
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
    }

    return $summary;
}