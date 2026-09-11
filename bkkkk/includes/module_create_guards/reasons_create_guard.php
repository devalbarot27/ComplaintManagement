<?php

/**
 * Create-request guards for reasons only.
 * Max 1 record/request, throttle, rate limit, and abuse monitoring.
 */

function reasons_max_records_per_request(): int
{
    return 1;
}

function reasons_rate_limit(): int
{
    return 20;
}

function reasons_rate_window_seconds(): int
{
    return 15 * 60;
}

function reasons_min_interval_seconds(): int
{
    return 3;
}

function reasons_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/request_abuse/reasons';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function reasons_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function reasons_actor_key(): string
{
    $username = trim((string) ($_SESSION['usr_name'] ?? ''));
    if ($username !== '') {
        return 'user:' . strtolower($username);
    }
    return 'ip:' . reasons_client_ip();
}

function reasons_count_records_in_payload(array $post): int
{
    foreach (['items', 'records', 'rows', 'batch', 'data'] as $bulkKey) {
        if (isset($post[$bulkKey]) && is_array($post[$bulkKey])) {
            $isList = array_keys($post[$bulkKey]) === range(0, count($post[$bulkKey]) - 1);
            return $isList ? count($post[$bulkKey]) : 1;
        }
    }
    foreach (['name', 'tplcode', 'id'] as $field) {
        if (isset($post[$field]) && is_array($post[$field])) {
            return count($post[$field]);
        }
    }
    return 1;
}

function reasons_log_event(string $event, array $context = []): void
{
    $entry = [
        'ts' => date('c'),
        'event' => $event,
        'resource' => 'reasons',
        'actor' => reasons_actor_key(),
        'ip' => reasons_client_ip(),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'context' => $context,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    file_put_contents(reasons_storage_dir() . '/abuse_monitor.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    error_log('[reasons_create_guard] ' . $line);
}

function reasons_throttle_state_path(): string
{
    $safeActor = preg_replace('/[^a-z0-9_\-:]+/i', '_', reasons_actor_key()) ?: 'actor';
    return reasons_storage_dir() . '/throttle_' . sha1($safeActor) . '.json';
}

function reasons_read_throttle_state(): array
{
    $path = reasons_throttle_state_path();
    if (!is_file($path)) {
        return ['timestamps' => [], 'last_at' => 0];
    }
    $raw = file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        return ['timestamps' => [], 'last_at' => 0];
    }
    $timestamps = [];
    foreach (($data['timestamps'] ?? []) as $ts) {
        $timestamps[] = (int) $ts;
    }
    return [
        'timestamps' => $timestamps,
        'last_at' => (int) ($data['last_at'] ?? 0),
    ];
}

function reasons_write_throttle_state(array $state): void
{
    file_put_contents(reasons_throttle_state_path(), json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * @return string|null Error message when rejected; null when allowed
 */
function reasons_enforce_create_request(array $post): ?string
{
    $recordCount = reasons_count_records_in_payload($post);
    $maxRecords = reasons_max_records_per_request();
    if ($recordCount > $maxRecords) {
        reasons_log_event('create_payload_rejected', [
            'reason' => 'max_records_exceeded',
            'record_count' => $recordCount,
            'max_allowed' => $maxRecords,
        ]);
        return 'Too many records in a single request. A maximum of '
            . $maxRecords . ' record(s) can be created per request.';
    }

    $now = time();
    $window = reasons_rate_window_seconds();
    $limit = reasons_rate_limit();
    $minInterval = reasons_min_interval_seconds();
    $state = reasons_read_throttle_state();

    $recent = [];
    foreach ($state['timestamps'] as $ts) {
        if (($now - $ts) < $window) {
            $recent[] = $ts;
        }
    }

    $lastAt = (int) ($state['last_at'] ?? 0);
    if ($lastAt > 0 && ($now - $lastAt) < $minInterval) {
        reasons_log_event('create_throttled', [
            'reason' => 'min_interval',
            'retry_after_seconds' => $minInterval - ($now - $lastAt),
            'recent_count' => count($recent),
        ]);
        return 'Please wait a few seconds before creating another record.';
    }

    if (count($recent) >= $limit) {
        $oldest = min($recent);
        $retryAfter = max(1, $window - ($now - $oldest));
        reasons_log_event('create_rate_limited', [
            'reason' => 'rate_window_exceeded',
            'limit' => $limit,
            'window_seconds' => $window,
            'recent_count' => count($recent),
            'retry_after_seconds' => $retryAfter,
        ]);
        return 'Create rate limit exceeded. You may create up to '
            . $limit . ' records every ' . (int) ($window / 60)
            . ' minutes. Please try again later.';
    }

    $recent[] = $now;
    reasons_write_throttle_state([
        'timestamps' => $recent,
        'last_at' => $now,
    ]);

    reasons_log_event('create_allowed', [
        'record_count' => $recordCount,
        'recent_count' => count($recent),
        'limit' => $limit,
    ]);

    return null;
}