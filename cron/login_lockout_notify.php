<?php

/**
 * Cron Job: Login lockout auto-unlock + unlock notification emails
 *
 * - Unlocks accounts whose 15-minute lock has expired
 * - Sends unlock notification email 30 minutes after unlock
 *
 * CLI (preferred):
 *   php cron/login_lockout_notify.php
 *
 * Optional HTTP trigger:
 *   .../cron/login_lockout_notify.php?key=YOUR_SECRET
 */

declare(strict_types=1);

const LOGIN_LOCKOUT_CRON_SECRET = 'BjNX718biT6cF5RE';

$isCli = (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server');

if (!$isCli) {
    $providedKey = (string) ($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');
    if (LOGIN_LOCKOUT_CRON_SECRET === '' || !hash_equals(LOGIN_LOCKOUT_CRON_SECRET, $providedKey)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Forbidden.']);
        exit;
    }
}

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/login_lockout_helpers.php';

$startedAt = date('Y-m-d H:i:s');

try {
    if (!isset($obconn) || !($obconn instanceof PDO)) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $summary = login_lockout_cron_run($obconn);
    $summary['ok'] = true;
    $summary['started_at'] = $startedAt;
    $summary['finished_at'] = date('Y-m-d H:i:s');

    if ($isCli) {
        echo '[' . $summary['finished_at'] . '] Login Lockout Cron' . PHP_EOL;
        echo '  Accounts unlocked : ' . $summary['accounts_unlocked'] . PHP_EOL;
        echo '  Emails processed  : ' . $summary['emails_processed'] . PHP_EOL;
        echo '  Emails sent       : ' . $summary['emails_sent'] . PHP_EOL;
        echo '  Emails skipped    : ' . $summary['emails_skipped'] . PHP_EOL;
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
        fwrite(STDERR, '[ERROR] Login lockout cron failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
}
