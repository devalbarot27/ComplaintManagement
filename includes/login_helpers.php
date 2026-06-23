<?php

function login_remember_cookie_name(): string
{
    return 'dp_remember';
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
        'password' => (string) ($row['password'] ?? ''),
        'display_name' => $name !== '' ? $name : $username,
        'name' => $name,
        'email' => trim((string) ($row['email'] ?? '')),
        'mobile' => trim((string) ($row['mobile_number'] ?? '')),
        'role' => (int) ($row['role'] ?? 0),
    ];
}

function login_fetch_user_master(PDO $conn, string $username): ?array
{
    $sql = "
        SELECT username, name, email, password, mobile_number, role
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

    $sql = "
        SELECT username, name, email, password, mobile_number, role
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
    session_regenerate_id(true);
    $_SESSION['usr_name'] = trim((string) $user['usr_name']);
    $_SESSION['display_name'] = login_display_name($user);
    $_SESSION['role'] = (int) ($user['role'] ?? 0);
    unset($_SESSION['rbac_permissions']);

    if ($remember) {
        login_set_remember_cookie($_SESSION['usr_name']);
    } else {
        login_clear_remember_cookie();
    }
}

function login_set_remember_cookie(string $usrName): void
{
    $payload = [
        'usr_name' => trim($usrName),
        'exp' => time() + (30 * 24 * 60 * 60),
    ];
    $data = base64_encode(json_encode($payload));
    $signature = hash_hmac('sha256', $data, login_remember_secret());

    setcookie(
        login_remember_cookie_name(),
        $data . '.' . $signature,
        [
            'expires' => $payload['exp'],
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

function login_clear_remember_cookie(): void
{
    setcookie(
        login_remember_cookie_name(),
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
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

    login_start_session($user, true);
    return true;
}

function login_verify_password(array $user, string $password): bool
{
    $storedPassword = strtolower(trim((string) ($user['password'] ?? '')));
    $enteredPasswordHash = md5($password);

    return $storedPassword !== '' && hash_equals($storedPassword, $enteredPasswordHash);
}

function login_generate_otp(): string
{
    //return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    return '123456'; // Temp
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

    $fromAddress = 'noreply@dealerportal.local';
    $headers = 'From: Dealer Portal <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();

    //return mail($email, $subject, $message, $headers);
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
