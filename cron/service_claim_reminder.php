<?php

/**
 * Cron Job: Service Claim Approval weekly reminder
 *
 * Emails the assigned approver for each Service Claim that is still pending
 * approval. The first reminder is sent 7 days after the current approver
 * was assigned. Further reminders are sent once every 7 days until the
 * claim is approved or rejected.
 *
 * Recommended schedule: once per day.
 *
 * CLI (preferred):
 *   php cron/service_claim_reminder.php
 *
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\ComplaintManagement\cron\service_claim_reminder.php
 *
 * Optional HTTP trigger:
 *   http://localhost/ComplaintManagement/cron/service_claim_reminder.php?key=YOUR_SECRET
 */

declare(strict_types=1);

const SERVICE_CLAIM_REMINDER_CRON_SECRET = 'BjNX718biT6cF5RE';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server');

if (!$isCli) {
    $providedKey = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if (SERVICE_CLAIM_REMINDER_CRON_SECRET === '' || !hash_equals(SERVICE_CLAIM_REMINDER_CRON_SECRET, $providedKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once __DIR__ . '/service_claim_reminder_helpers.php';

$startedAt = date('Y-m-d H:i:s');

try {
    if (!isset($obconn) || !($obconn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $summary = service_claim_reminder_run($obconn);
    $summary['ok'] = true;
    $summary['started_at'] = $startedAt;
    $summary['finished_at'] = date('Y-m-d H:i:s');

    if ($isCli) {
        echo '[' . $summary['finished_at'] . '] Service Claim Approval Reminder' . PHP_EOL;
        echo '  Processed claims     : ' . $summary['processed'] . PHP_EOL;
        echo '  Reminders sent       : ' . $summary['reminders_sent'] . PHP_EOL;
        echo '  Emails sent          : ' . $summary['emails_sent'] . PHP_EOL;
        echo '  Notifications created: ' . $summary['notifications_created'] . PHP_EOL;
        echo '  Skipped              : ' . $summary['skipped'] . PHP_EOL;
        echo '  Failed               : ' . $summary['failed'] . PHP_EOL;
        exit(0);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($summary, JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    $payload = [
        'ok' => false,
        'error' => $e->getMessage(),
        'started_at' => $startedAt,
        'finished_at' => date('Y-m-d H:i:s'),
    ];

    if ($isCli) {
        fwrite(STDERR, '[ERROR] Service Claim Approval Reminder failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}