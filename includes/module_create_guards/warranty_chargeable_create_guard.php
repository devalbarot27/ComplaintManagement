<?php

/**
 * Create-request guards for warranty_chargeable only.
 * Max 1 record/request, throttle, rate limit, and abuse monitoring.
 */

function warranty_chargeable_max_records_per_request(): int
{
    return 1;
}

function warranty_chargeable_rate_limit(): int
{
    return 20;
}

function warranty_chargeable_rate_window_seconds(): int
{
    return 15 * 60;
}

function warranty_chargeable_min_interval_seconds(): int
{
    return 3;
}

function warranty_chargeable_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/request_abuse/warranty_chargeable';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function warranty_chargeable_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function warranty_chargeable_actor_key(): string
{
    $username = trim((string) ($_SESSION['usr_name'] ?? ''));
    if ($username !== '') {
        return 'user:' . strtolower($username);
    }
    return 'ip:' . warranty_chargeable_client_ip();
}

function warranty_chargeable_count_records_in_payload(array $post): int
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

function warranty_chargeable_log_event(string $event, array $context = []): void
{
    $entry = [
        'ts' => date('c'),
        'event' => $event,
        'resource' => 'warranty_chargeable',
        'actor' => warranty_chargeable_actor_key(),
        'ip' => warranty_chargeable_client_ip(),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'context' => $context,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    file_put_contents(warranty_chargeable_storage_dir() . '/abuse_monitor.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    error_log('[warranty_chargeable_create_guard] ' . $line);
}

function warranty_chargeable_throttle_state_path(): string
{
    $safeActor = preg_replace('/[^a-z0-9_\-:]+/i', '_', warranty_chargeable_actor_key()) ?: 'actor';
    return warranty_chargeable_storage_dir() . '/throttle_' . sha1($safeActor) . '.json';
}

function warranty_chargeable_read_throttle_state(): array
{
    $path = warranty_chargeable_throttle_state_path();
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

function warranty_chargeable_write_throttle_state(array $state): void
{
    file_put_contents(warranty_chargeable_throttle_state_path(), json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * @return string|null Error message when rejected; null when allowed
 */
function warranty_chargeable_enforce_create_request(array $post): ?string
{
    $recordCount = warranty_chargeable_count_records_in_payload($post);
    $maxRecords = warranty_chargeable_max_records_per_request();
    if ($recordCount > $maxRecords) {
        warranty_chargeable_log_event('create_payload_rejected', [
            'reason' => 'max_records_exceeded',
            'record_count' => $recordCount,
            'max_allowed' => $maxRecords,
        ]);
        return 'Too many records in a single request. A maximum of '
            . $maxRecords . ' record(s) can be created per request.';
    }

    $now = time();
    $window = warranty_chargeable_rate_window_seconds();
    $limit = warranty_chargeable_rate_limit();
    $minInterval = warranty_chargeable_min_interval_seconds();
    $state = warranty_chargeable_read_throttle_state();

    $recent = [];
    foreach ($state['timestamps'] as $ts) {
        if (($now - $ts) < $window) {
            $recent[] = $ts;
        }
    }

    $lastAt = (int) ($state['last_at'] ?? 0);
    if ($lastAt > 0 && ($now - $lastAt) < $minInterval) {
        warranty_chargeable_log_event('create_throttled', [
            'reason' => 'min_interval',
            'retry_after_seconds' => $minInterval - ($now - $lastAt),
            'recent_count' => count($recent),
        ]);
        return 'Please wait a few seconds before creating another record.';
    }

    if (count($recent) >= $limit) {
        $oldest = min($recent);
        $retryAfter = max(1, $window - ($now - $oldest));
        warranty_chargeable_log_event('create_rate_limited', [
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
    warranty_chargeable_write_throttle_state([
        'timestamps' => $recent,
        'last_at' => $now,
    ]);

    warranty_chargeable_log_event('create_allowed', [
        'record_count' => $recordCount,
        'recent_count' => count($recent),
        'limit' => $limit,
    ]);

    return null;
}