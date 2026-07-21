<?php

require_once __DIR__ . '/login_helpers.php';
require_once __DIR__ . '/password_history_helpers.php';

function password_reset_expiry_seconds(): int
{
    return 3600;
}

function password_reset_use_redirect(): bool
{
    return true;
}

function password_reset_rules_error(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one digit (0-9).';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter (A-Z).';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter (a-z).';
    }

    if (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?~`]/', $password)) {
        return 'Password must contain at least one special character (!@#$%^&* etc.).';
    }

    return null;
}

function password_reset_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

    return rtrim($scheme . '://' . $host . $scriptDir, '/');
}

function password_reset_build_url(string $token): string
{
    return password_reset_base_url() . '/reset_password.php?token=' . urlencode($token);
}

function password_reset_hash_token(string $token): string
{
    return hash('sha256', $token);
}

function password_reset_invalidate_user_tokens(PDO $conn, string $username): void
{
    $stmt = $conn->prepare("
        UPDATE password_reset_tokens
        SET used_at = CURRENT_TIMESTAMP
        WHERE TRIM(usr_name) = :usr_name
          AND used_at IS NULL
    ");
    $stmt->bindValue(':usr_name', trim($username));
    $stmt->execute();
}

function password_reset_create_token(PDO $conn, string $username): string
{
    password_reset_invalidate_user_tokens($conn, $username);

    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + password_reset_expiry_seconds());

    $stmt = $conn->prepare("
        INSERT INTO password_reset_tokens (usr_name, token_hash, expires_at)
        VALUES (:usr_name, :token_hash, :expires_at)
    ");
    $stmt->bindValue(':usr_name', trim($username));
    $stmt->bindValue(':token_hash', password_reset_hash_token($token));
    $stmt->bindValue(':expires_at', $expiresAt);
    $stmt->execute();

    return $token;
}

function password_reset_send_email(array $user, string $resetUrl): bool
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $displayName = login_display_name($user);
    $expiryMinutes = (int) (password_reset_expiry_seconds() / 60);

    $message = implode("\r\n", [
        'Hello ' . $displayName . ',',
        '',
        'We received a request to reset your Dealer Portal password.',
        '',
        'Click the link below to reset your password:',
        $resetUrl,
        '',
        'This link will expire in ' . $expiryMinutes . ' minutes and can be used only once.',
        '',
        'If you did not request a password reset, please ignore this email.',
    ]);

    $subject = 'Dealer Portal Password Reset';
    $fromAddress = 'noreply@dealerportal.local';
    $headers = 'From: Dealer Portal <' . $fromAddress . ">\r\n"
        . 'Reply-To: ' . $fromAddress . "\r\n"
        . 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
        . 'X-Mailer: PHP/' . phpversion();

    //return mail($email, $subject, $message, $headers);
    return true;
}

function password_reset_find_valid_token(PDO $conn, string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, usr_name, expires_at, used_at
        FROM password_reset_tokens
        WHERE token_hash = :token_hash
        LIMIT 1
    ");
    $stmt->bindValue(':token_hash', password_reset_hash_token($token));
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    if (!empty($row['used_at'])) {
        return null;
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        return null;
    }

    return $row;
}

function password_reset_mark_used(PDO $conn, int $tokenId): void
{
    $stmt = $conn->prepare("
        UPDATE password_reset_tokens
        SET used_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->bindValue(':id', $tokenId, PDO::PARAM_INT);
    $stmt->execute();
}

function password_reset_update_password(PDO $obconn, string $username, string $newPassword): bool
{
    $stmt = $obconn->prepare("
        UPDATE user_master
        SET password = :password,
            updated_at = CURRENT_TIMESTAMP
        WHERE TRIM(username) = :username
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':password', user_password_hash($newPassword));
    $stmt->bindValue(':username', trim($username));
    $stmt->execute();

    return $stmt->rowCount() > 0;
}

function password_reset_process_forgot(PDO $obconn, string $email): array
{
    $email = trim($email);

    if ($email === '') {
        return ['success' => false, 'error' => 'Email address is required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Please enter a valid email address.'];
    }

    $user = login_fetch_user_by_email($obconn, $email);
    if ($user === null) {
        return ['success' => false, 'error' => 'Email address does not exist in the system.'];
    }

    $username = trim((string) ($user['usr_name'] ?? ''));

    try {
        $token = password_reset_create_token($obconn, $username);
        $resetUrl = password_reset_build_url($token);

        password_reset_send_email($user, $resetUrl);

        if (password_reset_use_redirect()) {
            // Relative path only — absolute URLs are for email, not Location headers.
            return [
                'success' => true,
                'redirect' => 'reset_password.php?token=' . $token,
                'message' => 'Redirecting to reset password page.',
            ];
        }
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Failed to process password reset request. Please try again.'];
    }

    return [
        'success' => true,
        'message' => 'A password reset link has been sent to your registered email address.',
    ];
}

function password_reset_process_reset(
    PDO $obconn,
    string $token,
    string $newPassword,
    string $confirmPassword
): array {
    if ($newPassword === '' || $confirmPassword === '') {
        return ['success' => false, 'error' => 'All fields are required.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'Confirm Password must match New Password.'];
    }

    $passwordError = password_reset_rules_error($newPassword);
    if ($passwordError !== null) {
        return ['success' => false, 'error' => $passwordError];
    }

    $tokenRow = password_reset_find_valid_token($obconn, $token);
    if ($tokenRow === null) {
        return ['success' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'];
    }

    $username = trim((string) $tokenRow['usr_name']);
    $user = login_fetch_user_auth($obconn, $username);
    if ($user === null) {
        return ['success' => false, 'error' => 'Invalid or expired reset link. Please request a new one.'];
    }

    $currentHash = (string) ($user['password'] ?? '');
    if (password_history_is_reused($obconn, $username, $newPassword, $currentHash)) {
        return ['success' => false, 'error' => password_history_reuse_error()];
    }

    try {
        $obconn->beginTransaction();

        if (!password_reset_update_password($obconn, $username, $newPassword)) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }
            return ['success' => false, 'error' => 'Failed to reset password. Please try again.'];
        }

        password_history_record($obconn, $username, $currentHash);

        password_reset_mark_used($obconn, (int) $tokenRow['id']);
        password_reset_invalidate_user_tokens($obconn, $username);

        $obconn->commit();
    } catch (PDOException $e) {
        if ($obconn->inTransaction()) {
            $obconn->rollBack();
        }
        return ['success' => false, 'error' => 'Failed to reset password. Please try again.'];
    }

    return ['success' => true, 'message' => 'Password reset successfully. Please login with your new password.'];
}

function change_password_process(
    PDO $obconn,
    string $username,
    string $currentPassword,
    string $newPassword,
    string $confirmPassword
): array {
    $username = trim($username);

    if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
        return ['success' => false, 'error' => 'All fields are required.'];
    }

    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'Confirm Password must match New Password.'];
    }

    $passwordError = password_reset_rules_error($newPassword);
    if ($passwordError !== null) {
        return ['success' => false, 'error' => $passwordError];
    }

    $user = login_fetch_user_auth($obconn, $username);
    if ($user === null) {
        return ['success' => false, 'error' => 'User account not found.'];
    }

    if (!login_verify_password($user, $currentPassword)) {
        return ['success' => false, 'error' => 'Current password is incorrect.'];
    }

    $currentHash = (string) ($user['password'] ?? '');
    if (password_history_is_reused($obconn, $username, $newPassword, $currentHash)) {
        return ['success' => false, 'error' => password_history_reuse_error()];
    }

    try {
        $obconn->beginTransaction();

        if (!password_reset_update_password($obconn, $username, $newPassword)) {
            if ($obconn->inTransaction()) {
                $obconn->rollBack();
            }
            return ['success' => false, 'error' => 'Failed to change password. Please try again.'];
        }

        password_history_record($obconn, $username, $currentHash);
        $obconn->commit();
    } catch (PDOException $e) {
        if ($obconn->inTransaction()) {
            $obconn->rollBack();
        }
        return ['success' => false, 'error' => 'Failed to change password. Please try again.'];
    }

    return ['success' => true, 'message' => 'Password changed successfully'];
}