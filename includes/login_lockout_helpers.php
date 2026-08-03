<?php

/**
 * Account lockout after consecutive failed login attempts.
 *
 * Rules:
 * - 5 consecutive failures → lock for 15 minutes
 * - After 15 minutes → automatically unlock
 * - 30 minutes after unlock → email user that the account was unlocked
 */

require_once __DIR__ . '/login_helpers.php';

function login_lockout_max_attempts(): int
{
    return 5;
}

function login_lockout_duration_seconds(): int
{
    return 15 * 60;
}

function login_unlock_email_delay_seconds(): int
{
    return 30 * 60;
}

function login_ensure_lockout_columns(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS failed_login_attempts INTEGER NOT NULL DEFAULT 0
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS account_unlocked_at TIMESTAMP NULL
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS unlock_email_due_at TIMESTAMP NULL
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS unlock_email_sent_at TIMESTAMP NULL
    ');

    $ensured = true;
}

/**
 * Unlock accounts whose lock window has expired.
 * Schedules unlock notification email for 30 minutes later.
 *
 * @return int Number of accounts unlocked
 */
function login_process_expired_lockouts(PDO $conn): int
{
    login_ensure_lockout_columns($conn);

    $delaySeconds = login_unlock_email_delay_seconds();

    $stmt = $conn->prepare('
        UPDATE user_master
        SET failed_login_attempts = 0,
            locked_until = NULL,
            account_unlocked_at = CURRENT_TIMESTAMP,
            unlock_email_due_at = CURRENT_TIMESTAMP + make_interval(secs => :delay_seconds),
            unlock_email_sent_at = NULL
        WHERE locked_until IS NOT NULL
          AND locked_until <= CURRENT_TIMESTAMP
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':delay_seconds', $delaySeconds, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->rowCount();
}

/**
 * @return array{locked:bool,message:?string,minutes_remaining:int}|null null when user not found
 */
function login_get_lockout_status(PDO $conn, string $username): ?array
{
    login_ensure_lockout_columns($conn);
    login_process_expired_lockouts($conn);

    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, locked_until,
               CASE
                   WHEN locked_until IS NULL THEN 0
                   ELSE CEIL(EXTRACT(EPOCH FROM (locked_until - CURRENT_TIMESTAMP)) / 60.0)
               END AS minutes_remaining
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $minutesRemaining = max(0, (int) ($row['minutes_remaining'] ?? 0));
    $lockedUntil = $row['locked_until'] ?? null;
    $locked = $lockedUntil !== null && $minutesRemaining > 0;

    if (!$locked) {
        return [
            'locked' => false,
            'message' => null,
            'minutes_remaining' => 0,
        ];
    }

    $minutesLabel = $minutesRemaining === 1 ? '1 minute' : $minutesRemaining . ' minutes';

    return [
        'locked' => true,
        'message' => 'Your account is locked due to too many failed login attempts. Please try again after '
            . $minutesLabel . '.',
        'minutes_remaining' => $minutesRemaining,
    ];
}

function login_is_account_locked(PDO $conn, string $username): bool
{
    $status = login_get_lockout_status($conn, $username);
    return $status !== null && !empty($status['locked']);
}

/**
 * Record a failed login for an existing user. Locks after max attempts.
 *
 * @return array{locked:bool,attempts:int,message:?string}
 */
function login_register_failed_attempt(PDO $conn, string $username): array
{
    login_ensure_lockout_columns($conn);
    login_process_expired_lockouts($conn);

    $username = trim($username);
    if ($username === '') {
        return ['locked' => false, 'attempts' => 0, 'message' => null];
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('
            SELECT id, failed_login_attempts, locked_until
            FROM user_master
            WHERE TRIM(username) = :username
              AND deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
        ');
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $conn->commit();
            return ['locked' => false, 'attempts' => 0, 'message' => null];
        }

        if (!empty($row['locked_until'])) {
            $lockedUntilTs = strtotime((string) $row['locked_until']);
            if ($lockedUntilTs !== false && $lockedUntilTs > time()) {
                $conn->commit();
                $status = login_get_lockout_status($conn, $username);
                return [
                    'locked' => true,
                    'attempts' => (int) ($row['failed_login_attempts'] ?? 0),
                    'message' => $status['message'] ?? 'Your account is locked. Please try again later.',
                ];
            }
        }

        $attempts = (int) ($row['failed_login_attempts'] ?? 0) + 1;
        $maxAttempts = login_lockout_max_attempts();
        $userId = (int) $row['id'];

        if ($attempts >= $maxAttempts) {
            $lockSeconds = login_lockout_duration_seconds();
            $update = $conn->prepare('
                UPDATE user_master
                SET failed_login_attempts = :attempts,
                    locked_until = CURRENT_TIMESTAMP + make_interval(secs => :lock_seconds),
                    account_unlocked_at = NULL,
                    unlock_email_due_at = NULL,
                    unlock_email_sent_at = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $update->bindValue(':attempts', $attempts, PDO::PARAM_INT);
            $update->bindValue(':lock_seconds', $lockSeconds, PDO::PARAM_INT);
            $update->bindValue(':id', $userId, PDO::PARAM_INT);
            $update->execute();
            $conn->commit();

            $lockMinutes = (int) ($lockSeconds / 60);
            return [
                'locked' => true,
                'attempts' => $attempts,
                'message' => 'Your account has been locked for ' . $lockMinutes
                    . ' minutes due to too many failed login attempts.',
            ];
        }

        $update = $conn->prepare('
            UPDATE user_master
            SET failed_login_attempts = :attempts,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $update->bindValue(':attempts', $attempts, PDO::PARAM_INT);
        $update->bindValue(':id', $userId, PDO::PARAM_INT);
        $update->execute();
        $conn->commit();

        return [
            'locked' => false,
            'attempts' => $attempts,
            'message' => null,
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

/**
 * Clear failure counters after a successful authentication.
 */
function login_clear_failed_attempts(PDO $conn, string $username): void
{
    login_ensure_lockout_columns($conn);

    $username = trim($username);
    if ($username === '') {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE user_master
        SET failed_login_attempts = 0,
            locked_until = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
}

function login_send_unlock_notification_email(array $user): bool
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $displayName = login_display_name($user);
    $subject = 'Dealer Portal Account Unlocked';
    $message = implode("\r\n", [
        'Hello ' . $displayName . ',',
        '',
        'Your Dealer Portal account was unlocked after a temporary lock caused by multiple failed login attempts.',
        '',
        'If you did not attempt to sign in, please change your password immediately and contact your administrator.',
        '',
        'Thank you,',
        'Dealer Portal',
    ]);

    $fromAddress = 'noreply@vayudealerportal.com';
    $headers = 'From: Dealer Portal <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();

    return (bool) mail($email, $subject, $message, $headers);
}

/**
 * Send unlock notification emails that are due (30 minutes after unlock).
 *
 * @return array{processed:int,emails_sent:int,skipped:int}
 */
function login_send_due_unlock_notification_emails(PDO $conn): array
{
    login_ensure_lockout_columns($conn);

    $stmt = $conn->query('
        SELECT id, username, name, email
        FROM user_master
        WHERE unlock_email_due_at IS NOT NULL
          AND unlock_email_due_at <= CURRENT_TIMESTAMP
          AND unlock_email_sent_at IS NULL
          AND deleted_at IS NULL
        ORDER BY unlock_email_due_at ASC
        LIMIT 100
    ');

    $processed = 0;
    $emailsSent = 0;
    $skipped = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $processed++;
        $userId = (int) $row['id'];
        $user = [
            'usr_name' => (string) ($row['username'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'display_name' => trim((string) ($row['name'] ?? '')),
            'email' => (string) ($row['email'] ?? ''),
        ];

        $sent = login_send_unlock_notification_email($user);
        if ($sent) {
            $emailsSent++;
            $mark = $conn->prepare('
                UPDATE user_master
                SET unlock_email_sent_at = CURRENT_TIMESTAMP,
                    unlock_email_due_at = NULL
                WHERE id = :id
            ');
            $mark->bindValue(':id', $userId, PDO::PARAM_INT);
            $mark->execute();
        } else {
            $skipped++;
            // Avoid retrying forever when email is missing/invalid.
            $mark = $conn->prepare('
                UPDATE user_master
                SET unlock_email_sent_at = CURRENT_TIMESTAMP,
                    unlock_email_due_at = NULL
                WHERE id = :id
            ');
            $mark->bindValue(':id', $userId, PDO::PARAM_INT);
            $mark->execute();
        }
    }

    return [
        'processed' => $processed,
        'emails_sent' => $emailsSent,
        'skipped' => $skipped,
    ];
}

/**
 * Cron entry: unlock expired accounts, then send due unlock emails.
 *
 * @return array<string,mixed>
 */
function login_lockout_cron_run(PDO $conn): array
{
    $unlocked = login_process_expired_lockouts($conn);
    $emailSummary = login_send_due_unlock_notification_emails($conn);

    return [
        'accounts_unlocked' => $unlocked,
        'emails_processed' => $emailSummary['processed'],
        'emails_sent' => $emailSummary['emails_sent'],
        'emails_skipped' => $emailSummary['skipped'],
    ];
}
