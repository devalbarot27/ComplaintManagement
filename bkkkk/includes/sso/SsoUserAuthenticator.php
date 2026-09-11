<?php
/**
 * Authenticates SSO identities against the local user_master table.
 *
 * Reuses existing login helpers so session keys, idle timeout, and RBAC stay consistent.
 */
class SsoUserAuthenticator
{
    private PDO $conn;

    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Look up an active local user by email and establish a secure app session.
     *
     * @return array Normalized user row from login_normalize_user_master_row()
     * @throws SsoException
     */
    public function authenticateByEmail(string $email): array
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new SsoException('A valid email is required for SSO authentication.', 'invalid_email');
        }

        $user = login_fetch_user_by_email($this->conn, $email);
        if ($user === null) {
            throw new SsoException(
                'No local account is linked to this SSO email. Please contact your administrator.',
                'user_not_found'
            );
        }

        $username = trim((string) ($user['usr_name'] ?? ''));
        if ($username === '') {
            throw new SsoException('Local user record is incomplete.', 'user_incomplete');
        }

        require_once dirname(__DIR__) . '/login_lockout_helpers.php';
        $lockout = login_get_lockout_status($this->conn, $username);
        if ($lockout !== null && !empty($lockout['locked'])) {
            throw new SsoException(
                (string) ($lockout['message'] ?? 'Your account is locked. Please try again later.'),
                'account_locked'
            );
        }

        login_clear_failed_attempts($this->conn, $username);
        login_update_last_login_at($this->conn, $username);
        login_start_session($user, false);

        return $user;
    }
}