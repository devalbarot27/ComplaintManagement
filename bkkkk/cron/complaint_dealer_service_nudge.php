<?php

/**
 * Cron Job: Dealer User Service Update Nudge
 *
 * Reminds the assigned Dealer User at 24h / 48h / 72h when a complaint
 * remains In Progress or Re-Open without a service update.
 *
 * Recommended schedule: every 30 minutes (or every 1 hour).
 *
 * CLI (preferred):
 *   php cron/complaint_dealer_service_nudge.php
 *
 * Windows Task Scheduler:
 *   Program: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\ComplaintManagementDev\cron\complaint_dealer_service_nudge.php
 *
 * Optional HTTP trigger:
 *   http://localhost/ComplaintManagementDev/cron/complaint_dealer_service_nudge.php?key=YOUR_SECRET
 */

declare(strict_types=1);

const COMPLAINT_DEALER_SERVICE_NUDGE_CRON_SECRET = 'BjNX718biT6cF5RE';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server');

if (!$isCli) {
    $providedKey = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if (
        COMPLAINT_DEALER_SERVICE_NUDGE_CRON_SECRET === ''
        || !hash_equals(COMPLAINT_DEALER_SERVICE_NUDGE_CRON_SECRET, $providedKey)
    ) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once __DIR__ . '/complaint_dealer_service_nudge_helpers.php';

$startedAt = date('Y-m-d H:i:s');

try {
    if (!isset($obconn) || !($obconn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $summary = complaint_dealer_service_nudge_run($obconn);
    $summary['ok'] = true;
    $summary['started_at'] = $startedAt;
    $summary['finished_at'] = date('Y-m-d H:i:s');

    if ($isCli) {
        echo '[' . $summary['finished_at'] . '] Dealer Service Update Nudge' . PHP_EOL;
        echo '  Processed assignments : ' . $summary['processed'] . PHP_EOL;
        echo '  Reminders sent        : ' . $summary['reminders_sent'] . PHP_EOL;
        echo '  Notifications created : ' . $summary['notifications_created'] . PHP_EOL;
        echo '  Emails sent           : ' . $summary['emails_sent'] . PHP_EOL;
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
        fwrite(STDERR, '[ERROR] Dealer Service Update Nudge failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}