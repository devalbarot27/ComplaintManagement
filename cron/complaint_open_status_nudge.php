<?php

/**
 * Cron Job: Complaint Open Status Nudge
 *
 * Evaluates Open complaints and sends 24h / 48h / 72h reminders
 * via In-App Notification and Email.
 *
 * CLI (preferred):
 *   php cron/complaint_open_status_nudge.php
 *
 * Optional HTTP trigger:
 *   http://localhost/ComplaintManagementDev/cron/complaint_open_status_nudge.php?key=YOUR_SECRET
 */

declare(strict_types=1);

const COMPLAINT_OPEN_NUDGE_CRON_SECRET = 'AsDfGhJkL';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server');

if (!$isCli) {
    $providedKey = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if (COMPLAINT_OPEN_NUDGE_CRON_SECRET === '' || !hash_equals(COMPLAINT_OPEN_NUDGE_CRON_SECRET, $providedKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once __DIR__ . '/complaint_open_nudge_helpers.php';

$startedAt = date('Y-m-d H:i:s');

try {
    if (!isset($obconn) || !($obconn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $summary = complaint_open_nudge_run($obconn);
    $summary['ok'] = true;
    $summary['started_at'] = $startedAt;
    $summary['finished_at'] = date('Y-m-d H:i:s');

    if ($isCli) {
        echo '[' . $summary['finished_at'] . '] Complaint Open Status Nudge' . PHP_EOL;
        echo '  Processed complaints : ' . $summary['processed'] . PHP_EOL;
        echo '  Reminders sent       : ' . $summary['reminders_sent'] . PHP_EOL;
        echo '  Notifications created: ' . $summary['notifications_created'] . PHP_EOL;
        echo '  Emails sent          : ' . $summary['emails_sent'] . PHP_EOL;
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
        fwrite(STDERR, '[ERROR] Complaint Open Status Nudge failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}