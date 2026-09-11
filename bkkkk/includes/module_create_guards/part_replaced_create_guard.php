<?php

/**
 * Create-request guards for part_replaced only.
 * Max 1 record/request, throttle, rate limit, and abuse monitoring.
 */

function part_replaced_max_records_per_request(): int
{
    return 1;
}

function part_replaced_rate_limit(): int
{
    return 20;
}

function part_replaced_rate_window_seconds(): int
{
    return 15 * 60;
}

function part_replaced_min_interval_seconds(): int
{
    return 3;
}

function part_replaced_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/request_abuse/part_replaced';
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $htaccess = $dir . '/.htaccess';
    if (!is_file($htaccess)) {
        file_put_contents($htaccess, "Require all denied\nDeny from all\n");
    }
    return $dir;
}

function part_replaced_client_ip(): string
{
    $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    return $ip !== '' ? $ip : 'unknown';
}

function part_replaced_actor_key(): string
{
    $username = trim((string) ($_SESSION['usr_name'] ?? ''));
    if ($username !== '') {
        return 'user:' . strtolower($username);
    }
    return 'ip:' . part_replaced_client_ip();
}

function part_replaced_count_records_in_payload(array $post): int
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

function part_replaced_log_event(string $event, array $context = []): void
{
    $entry = [
        'ts' => date('c'),
        'event' => $event,
        'resource' => 'part_replaced',
        'actor' => part_replaced_actor_key(),
        'ip' => part_replaced_client_ip(),
        'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'context' => $context,
    ];
    $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($line === false) {
        return;
    }
    file_put_contents(part_replaced_storage_dir() . '/abuse_monitor.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    error_log('[part_replaced_create_guard] ' . $line);
}

function part_replaced_throttle_state_path(): string
{
    $safeActor = preg_replace('/[^a-z0-9_\-:]+/i', '_', part_replaced_actor_key()) ?: 'actor';
    return part_replaced_storage_dir() . '/throttle_' . sha1($safeActor) . '.json';
}

function part_replaced_read_throttle_state(): array
{
    $path = part_replaced_throttle_state_path();
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

function part_replaced_write_throttle_state(array $state): void
{
    file_put_contents(part_replaced_throttle_state_path(), json_encode($state, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/**
 * @return string|null Error message when rejected; null when allowed
 */
function part_replaced_enforce_create_request(array $post): ?string
{
    $recordCount = part_replaced_count_records_in_payload($post);
    $maxRecords = part_replaced_max_records_per_request();
    if ($recordCount > $maxRecords) {
        part_replaced_log_event('create_payload_rejected', [
            'reason' => 'max_records_exceeded',
            'record_count' => $recordCount,
            'max_allowed' => $maxRecords,
        ]);
        return 'Too many records in a single request. A maximum of '
            . $maxRecords . ' record(s) can be created per request.';
    }

    $now = time();
    $window = part_replaced_rate_window_seconds();
    $limit = part_replaced_rate_limit();
    $minInterval = part_replaced_min_interval_seconds();
    $state = part_replaced_read_throttle_state();

    $recent = [];
    foreach ($state['timestamps'] as $ts) {
        if (($now - $ts) < $window) {
            $recent[] = $ts;
        }
    }

    $lastAt = (int) ($state['last_at'] ?? 0);
    if ($lastAt > 0 && ($now - $lastAt) < $minInterval) {
        part_replaced_log_event('create_throttled', [
            'reason' => 'min_interval',
            'retry_after_seconds' => $minInterval - ($now - $lastAt),
            'recent_count' => count($recent),
        ]);
        return 'Please wait a few seconds before creating another record.';
    }

    if (count($recent) >= $limit) {
        $oldest = min($recent);
        $retryAfter = max(1, $window - ($now - $oldest));
        part_replaced_log_event('create_rate_limited', [
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
    part_replaced_write_throttle_state([
        'timestamps' => $recent,
        'last_at' => $now,
    ]);

    part_replaced_log_event('create_allowed', [
        'record_count' => $recordCount,
        'recent_count' => count($recent),
        'limit' => $limit,
    ]);

    return null;
}