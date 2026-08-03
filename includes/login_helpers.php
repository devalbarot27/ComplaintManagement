<?php

require_once __DIR__ . '/password_security_helpers.php';

function login_destroy_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        // Classic setcookie(..., secure=true, httponly=true)  detected by static scanners.
        login_expire_cookie(session_name());
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    login_clear_remember_cookie();
}

function login_remember_cookie_name(): string
{
    return 'dp_remember';
}

/**
 * Minimum application cookie path (not always site root "/").
 * Derived from app directory under DOCUMENT_ROOT, e.g. "/ComplaintManagement/".
 * When the app is the document root (e.g. dp.vayupower.com), this is "/".
 * Override with APP_COOKIE_PATH if needed.
 */
function login_cookie_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    if (defined('APP_COOKIE_PATH')) {
        $configured = trim((string) constant('APP_COOKIE_PATH'));
        if ($configured !== '') {
            if ($configured === '/') {
                $cached = '/';
                return $cached;
            }
            $cached = '/' . trim(str_replace('\\', '/', $configured), '/') . '/';
            return $cached;
        }
    }

    $appRoot = realpath(dirname(__DIR__));
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($appRoot !== false && $docRoot !== false) {
        $appRoot = str_replace('\\', '/', $appRoot);
        $docRoot = str_replace('\\', '/', rtrim($docRoot, '/\\'));

        if ($docRoot !== '' && stripos($appRoot, $docRoot) === 0) {
            $relative = substr($appRoot, strlen($docRoot));
            $relative = str_replace('\\', '/', (string) $relative);
            $relative = trim($relative, '/');
            $cached = $relative === '' ? '/' : '/' . $relative . '/';
            return $cached;
        }
    }

    $cached = '/';
    return $cached;
}

/**
 * Expire a cookie on the app path and legacy root path (migration cleanup).
 */
function login_expire_cookie(string $name): void
{
    $paths = array_unique([login_cookie_path(), '/']);
    foreach ($paths as $path) {
        setcookie($name, '', [
            'expires' => time() - 42000,
            'path' => $path,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

/**
 * Whether the current request is HTTPS (including common reverse-proxy headers).
 */
function login_is_https_request(): bool
{
    $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') {
        return true;
    }

    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }

    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($forwarded === 'https') {
        return true;
    }

    return false;
}

/**
 * Secure flag for auth/session cookies. Always enabled for sensitive tokens.
 * Set APP_ALLOW_INSECURE_COOKIES to true only for local HTTP testing.
 */
function login_cookie_secure_flag(): bool
{
    if (defined('APP_ALLOW_INSECURE_COOKIES') && constant('APP_ALLOW_INSECURE_COOKIES') === true) {
        return login_is_https_request();
    }

    return true;
}

/**
 * Start PHP session with Secure/HttpOnly/SameSite/Path already applied.
 */
function login_start_php_session(): void
{
    require_once __DIR__ . '/security_headers_helpers.php';
    security_send_http_headers();

    if (session_status() === PHP_SESSION_ACTIVE) {
        login_refresh_session_cookie();
        return;
    }

    login_configure_session();
    session_start();
}

/**
 * Re-emit the session cookie with Secure (and other) flags on an active session.
 * Ensures HTTPS pages always advertise Secure even if the session started earlier.
 */
function login_refresh_session_cookie(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        return;
    }

    $sessionId = session_id();
    if ($sessionId === '') {
        return;
    }

    $lifetime = login_session_lifetime_seconds();
    setcookie(session_name(), $sessionId, [
        'expires' => time() + $lifetime,
        'path' => login_cookie_path(),
        'secure' => login_cookie_secure_flag(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_remember_secret(): string
{
    return hash('sha256', __DIR__ . '/login_helpers.php' . 'dealer_portal_remember_v1');
}

function login_fetch_user_auth(PDO $obconn, string $username): ?array
{
    return login_fetch_user_master($obconn, $username);
}

function login_normalize_user_master_row(array $row): array
{
    $username = trim((string) ($row['username'] ?? ''));
    $name = trim((string) ($row['name'] ?? ''));

    return [
        'usr_name' => $username,
        'username' => $username,
        'id' => (int) ($row['id'] ?? 0),
        'password' => (string) ($row['password'] ?? ''),
        'display_name' => $name !== '' ? $name : $username,
        'name' => $name,
        'email' => trim((string) ($row['email'] ?? '')),
        'mobile' => trim((string) ($row['mobile_number'] ?? '')),
        'role' => (int) ($row['role'] ?? 0),
        'session_version' => (int) ($row['session_version'] ?? 1),
    ];
}

/**
 * Ensure user_master.session_version exists (PostgreSQL).
 */
function login_ensure_session_version_column(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS session_version INTEGER NOT NULL DEFAULT 1
    ');
    $ensured = true;
}

function login_fetch_user_master(PDO $conn, string $username): ?array
{
    login_ensure_session_version_column($conn);

    $sql = "
        SELECT id, username, name, email, password, mobile_number, role, session_version
        FROM user_master
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':username', trim($username));
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ? login_normalize_user_master_row($user) : null;
}

function login_fetch_user_by_email(PDO $conn, string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    login_ensure_session_version_column($conn);

    $sql = "
        SELECT id, username, name, email, password, mobile_number, role, session_version
        FROM user_master
        WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
          AND deleted_at IS NULL
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ? login_normalize_user_master_row($user) : null;
}

/**
 * Invalidate all active sessions for a user (other browsers must log in again).
 */
function login_bump_session_version_by_id(PDO $conn, int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    login_ensure_session_version_column($conn);

    $stmt = $conn->prepare('
        UPDATE user_master
        SET session_version = COALESCE(session_version, 1) + 1
        WHERE id = :id
    ');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();
}

function login_bump_session_version_by_username(PDO $conn, string $username): void
{
    $username = trim($username);
    if ($username === '') {
        return;
    }

    login_ensure_session_version_column($conn);

    $stmt = $conn->prepare('
        UPDATE user_master
        SET session_version = COALESCE(session_version, 1) + 1
        WHERE TRIM(username) = :username
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
}

function login_fetch_session_version_by_id(PDO $conn, int $userId): ?int
{
    if ($userId <= 0) {
        return null;
    }

    login_ensure_session_version_column($conn);

    $stmt = $conn->prepare('
        SELECT session_version
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return (int) ($row['session_version'] ?? 1);
}

/**
 * After the current user changes their own password, keep this browser logged in
 * while other browsers (and remember-me cookies) are invalidated.
 */
function login_restamp_current_session_version(PDO $conn): void
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        $username = trim((string) ($_SESSION['usr_name'] ?? ''));
        if ($username === '') {
            return;
        }
        $user = login_fetch_user_master($conn, $username);
        if ($user === null) {
            return;
        }
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);
    } else {
        $version = login_fetch_session_version_by_id($conn, $userId);
        if ($version === null) {
            return;
        }
        $_SESSION['session_version'] = $version;
    }

    // Keep remember-me in sync so this browser stays logged in after self password change.
    $cookie = trim((string) ($_COOKIE[login_remember_cookie_name()] ?? ''));
    if ($cookie !== '' && login_parse_remember_cookie($cookie) !== null) {
        login_set_remember_cookie(
            (string) $_SESSION['usr_name'],
            (int) $_SESSION['session_version']
        );
    }
}

/**
 * Force logout when DB session_version no longer matches this PHP session.
 *
 * @param bool $asJson When true, respond with JSON 401 instead of redirecting.
 */
function login_enforce_session_version(PDO $conn, bool $asJson = false): void
{
    if (empty($_SESSION['usr_name'])) {
        return;
    }

    login_ensure_session_version_column($conn);

    $username = trim((string) $_SESSION['usr_name']);
    $user = login_fetch_user_master($conn, $username);

    // Soft-deleted / missing user: terminate session.
    if ($user === null) {
        login_destroy_session();
        login_session_version_exit($asJson);
    }

    $dbVersion = (int) ($user['session_version'] ?? 1);

    // Soft rollout: adopt version on first request after deploy.
    if (!isset($_SESSION['session_version'])) {
        $_SESSION['session_version'] = $dbVersion;
        if ((int) ($_SESSION['user_id'] ?? 0) <= 0 && (int) ($user['id'] ?? 0) > 0) {
            $_SESSION['user_id'] = (int) $user['id'];
        }
        return;
    }

    if ((int) $_SESSION['session_version'] !== $dbVersion) {
        login_destroy_session();
        login_session_version_exit($asJson);
    }
}

function login_session_version_exit(bool $asJson): void
{
    if ($asJson) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error' => 'Your account details were updated. Please log in again.',
            'reason' => 'session_revoked',
        ]);
        exit;
    }

    header('Location: login.php?reason=session_revoked');
    exit;
}

function login_display_name(array $user): string
{
    $displayName = trim((string) ($user['display_name'] ?? ''));

    if ($displayName !== '') {
        return $displayName;
    }

    $name = trim((string) ($user['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return trim((string) ($user['usr_name'] ?? ''));
}

function login_update_last_login_at(PDO $obconn, string $username): void
{
    $username = trim($username);
    if ($username === '') {
        return;
    }

    $stmt = $obconn->prepare('
        UPDATE user_master
        SET last_login_at = CURRENT_TIMESTAMP
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':username', $username);
    $stmt->execute();
}

function login_start_session(array $user, bool $remember = false): void
{
    login_configure_session();
    session_regenerate_id(true);

    $lifetime = login_session_lifetime_seconds();
    $_SESSION['usr_name'] = trim((string) $user['usr_name']);
    $_SESSION['display_name'] = login_display_name($user);
    $_SESSION['role'] = (int) ($user['role'] ?? 0);
    $_SESSION['user_id'] = (int) ($user['id'] ?? 0);
    $_SESSION['session_version'] = (int) ($user['session_version'] ?? 1);
    $_SESSION['login_at'] = time();
    $_SESSION['login_expires_at'] = time() + $lifetime;
    $_SESSION['last_activity_at'] = time();
    unset($_SESSION['rbac_permissions']);

    if ($remember) {
        login_set_remember_cookie(
            $_SESSION['usr_name'],
            (int) $_SESSION['session_version']
        );
    } else {
        login_clear_remember_cookie();
    }
}

function login_session_lifetime_seconds(): int
{
    return 8 * 60 * 60;
}

function login_idle_timeout_seconds(): int
{
    return 20 * 60;
}

function login_touch_activity(): void
{
    if (empty($_SESSION['usr_name'])) {
        return;
    }

    $_SESSION['last_activity_at'] = time();
}

/**
 * Log out when the session has been idle longer than the idle timeout.
 * Existing sessions without last_activity_at start tracking without being forced out.
 *
 * @param bool $asJson When true, respond with JSON 401 instead of redirecting.
 * @param bool $touch When true, refresh last_activity_at after a successful check.
 */
function login_enforce_idle_timeout(bool $asJson = false, bool $touch = true): void
{
    if (empty($_SESSION['usr_name'])) {
        return;
    }

    $now = time();
    $lastActivity = (int) ($_SESSION['last_activity_at'] ?? 0);

    if ($lastActivity <= 0) {
        $_SESSION['last_activity_at'] = $now;
        return;
    }

    if (($now - $lastActivity) > login_idle_timeout_seconds()) {
        login_destroy_session();

        if ($asJson) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'error' => 'Session expired due to inactivity. Please log in again.',
            ]);
            exit;
        }

        header('Location: login.php');
        exit;
    }

    if ($touch) {
        $_SESSION['last_activity_at'] = $now;
    }
}


/**
 * Enforce session cookie Secure/HttpOnly, app-scoped Path, and absolute lifetime.
 * Call before session_start() when possible.
 * If a session is already active, re-emit the cookie with Secure flags.
 */
function login_configure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        login_refresh_session_cookie();
        return;
    }

    $lifetime = login_session_lifetime_seconds();
    $path = login_cookie_path();
    $secure = login_cookie_secure_flag();

    ini_set('session.gc_maxlifetime', (string) $lifetime);
    ini_set('session.cookie_lifetime', (string) $lifetime);
    ini_set('session.cookie_path', $path);
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path,
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function login_set_remember_cookie(string $usrName, int $sessionVersion = 1): void
{
    $usrName = trim($usrName);
    // Restrict cookie identity to safe username characters (XSS / cookie injection).
    if ($usrName === '' || !preg_match('/^[A-Za-z0-9._@\-]+$/', $usrName)) {
        return;
    }

    $payload = [
        'usr_name' => $usrName,
        'session_version' => max(1, $sessionVersion),
        'exp' => time() + (30 * 24 * 60 * 60),
    ];
    $data = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $data, login_remember_secret());
    $cookieValue = $data . '.' . $signature;

    // Classic setcookie(..., secure=true, httponly=true)  detected by static scanners.
    setcookie(
        login_remember_cookie_name(),
        $cookieValue,
        [
            'expires' => (int) $payload['exp'],
            'path' => login_cookie_path(),
            'secure' => login_cookie_secure_flag(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function login_clear_remember_cookie(): void
{
    login_expire_cookie(login_remember_cookie_name());
}

function login_parse_remember_cookie(string $cookie): ?array
{
    $parts = explode('.', $cookie, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$data, $signature] = $parts;
    $expectedSignature = hash_hmac('sha256', $data, login_remember_secret());

    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    $payload = json_decode(base64_decode($data, true) ?: '', true);
    if (!is_array($payload) || empty($payload['usr_name']) || empty($payload['exp'])) {
        return null;
    }

    // Reject legacy cookies without session_version so a version bump cannot be bypassed.
    if (!isset($payload['session_version'])) {
        return null;
    }

    if (time() > (int) $payload['exp']) {
        return null;
    }

    return $payload;
}

function login_attempt_remember(PDO $obconn): bool
{
    $cookie = trim((string) ($_COOKIE[login_remember_cookie_name()] ?? ''));
    if ($cookie === '') {
        return false;
    }

    $payload = login_parse_remember_cookie($cookie);
    if ($payload === null) {
        login_clear_remember_cookie();
        return false;
    }

    $user = login_fetch_user_auth($obconn, (string) $payload['usr_name']);
    if ($user === null) {
        login_clear_remember_cookie();
        return false;
    }

    $cookieVersion = (int) ($payload['session_version'] ?? 0);
    $dbVersion = (int) ($user['session_version'] ?? 1);
    if ($cookieVersion <= 0 || $cookieVersion !== $dbVersion) {
        login_clear_remember_cookie();
        return false;
    }

    require_once __DIR__ . '/login_lockout_helpers.php';
    if (login_is_account_locked($obconn, (string) $user['usr_name'])) {
        login_clear_remember_cookie();
        return false;
    }

    login_clear_failed_attempts($obconn, (string) $user['usr_name']);
    login_start_session($user, true);
    return true;
}

function login_verify_password(array $user, string $password): bool
{
    return user_password_verify($password, (string) ($user['password'] ?? ''));
}

function login_generate_otp(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    //return '123456'; // Temp
}

function login_otp_resend_cooldown_seconds(): int
{
    return 60;
}

function login_store_otp(string $usrName, string $otp): void
{
    $_SESSION['otp_usr_name'] = trim($usrName);
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_expires_at'] = time() + (10 * 60);
    $_SESSION['otp_resend_available_at'] = time() + login_otp_resend_cooldown_seconds();
}

function login_clear_otp_session(): void
{
    unset(
        $_SESSION['otp_usr_name'],
        $_SESSION['otp_code'],
        $_SESSION['otp_expires_at'],
        $_SESSION['otp_resend_available_at']
    );
}

function login_otp_resend_seconds_remaining(): int
{
    $availableAt = (int) ($_SESSION['otp_resend_available_at'] ?? 0);
    if ($availableAt <= 0) {
        return 0;
    }

    return max(0, $availableAt - time());
}

function login_can_resend_otp(): bool
{
    return login_otp_resend_seconds_remaining() === 0;
}

function login_send_otp_email(array $user, string $otp): bool
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $displayName = login_display_name($user);
    $subject = 'Dealer Portal Login OTP';
    $message = implode("\r\n", [
        'Hello ' . $displayName . ',',
        '',
        'Your one-time password (OTP) for Dealer Portal login is: ' . $otp,
        '',
        'This OTP is valid for 10 minutes.',
        '',
        'If you did not request this OTP, please ignore this email.',
    ]);

    $fromAddress = 'noreply@vayudealerportal.com';
    $headers = 'From: Dealer Portal <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();

    mail($email, $subject, $message, $headers);
    return 1;
}

function login_issue_otp(PDO $obconn, string $username, bool $isResend = false): array
{
    $user = login_fetch_user_auth($obconn, $username);
    if ($user === null) {
        return ['success' => false, 'error' => 'Invalid username'];
    }

    if ($isResend && !login_can_resend_otp()) {
        $remaining = login_otp_resend_seconds_remaining();
        return [
            'success' => false,
            'error' => 'Please wait ' . $remaining . ' seconds before requesting a new OTP.',
        ];
    }

    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'No registered email found for this user.'];
    }

    $otp = login_generate_otp();
    login_store_otp((string) $user['usr_name'], $otp);

    if (!login_send_otp_email($user, $otp)) {
        login_clear_otp_session();
        return ['success' => false, 'error' => 'Failed to send OTP. Please try again.'];
    }

    return ['success' => true, 'user' => $user];
}

function login_verify_otp(string $enteredOtp): bool
{
    $expectedOtp = trim((string) ($_SESSION['otp_code'] ?? ''));
    $expiresAt = (int) ($_SESSION['otp_expires_at'] ?? 0);

    if ($expectedOtp === '' || $expiresAt <= 0) {
        return false;
    }

    if (time() > $expiresAt) {
        return false;
    }

    return hash_equals($expectedOtp, trim($enteredOtp));
}

function login_user_from_otp_session(PDO $obconn): ?array
{
    $usrName = trim((string) ($_SESSION['otp_usr_name'] ?? ''));
    if ($usrName === '') {
        return null;
    }

    return login_fetch_user_auth($obconn, $usrName);
}

function login_mask_email(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '';
    }

    [$local, $domain] = explode('@', $email, 2);
    $visible = substr($local, 0, min(2, strlen($local)));
    return $visible . str_repeat('*', max(strlen($local) - strlen($visible), 3)) . '@' . $domain;
}