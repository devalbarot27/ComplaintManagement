<?php
/**
 * Manages SSO-specific session state (CSRF state, PKCE, nonce, IdP tokens).
 *
 * Application login sessions still use login_start_session() / login_destroy_session()
 * from includes/login_helpers.php  this class only stores the OIDC handshake data.
 */
class SsoSessionManager
{
    private const KEY_STATE = 'sso_oauth_state';
    private const KEY_NONCE = 'sso_oauth_nonce';
    private const KEY_CODE_VERIFIER = 'sso_code_verifier';
    private const KEY_STARTED_AT = 'sso_oauth_started_at';
    private const KEY_ID_TOKEN = 'sso_id_token';
    private const KEY_AUTH_VIA_SSO = 'auth_via_sso';
    private const KEY_SSO_EMAIL = 'sso_email';
    private const KEY_FLASH_ERROR = 'sso_error_message';

    /** Handshake TTL in seconds (authorization code flow). */
    private const HANDSHAKE_TTL = 600;

    public function ensurePhpSession(): void
    {
        require_once dirname(__DIR__) . '/login_helpers.php';
        login_start_php_session();
    }

    /**
     * Create and store a new OIDC authorization handshake.
     *
     * @return array{state: string, nonce: string, code_verifier: string, code_challenge: string}
     */
    public function beginHandshake(): array
    {
        $this->ensurePhpSession();
        $this->clearHandshake();

        $state = $this->randomToken(32);
        $nonce = $this->randomToken(32);
        $codeVerifier = $this->randomToken(64);
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $_SESSION[self::KEY_STATE] = $state;
        $_SESSION[self::KEY_NONCE] = $nonce;
        $_SESSION[self::KEY_CODE_VERIFIER] = $codeVerifier;
        $_SESSION[self::KEY_STARTED_AT] = time();

        return [
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge,
        ];
    }

    /**
     * Validate returned state and return handshake secrets.
     *
     * @return array{nonce: string, code_verifier: string}
     * @throws SsoException
     */
    public function consumeHandshake(string $returnedState): array
    {
        $this->ensurePhpSession();

        $expectedState = (string) ($_SESSION[self::KEY_STATE] ?? '');
        $nonce = (string) ($_SESSION[self::KEY_NONCE] ?? '');
        $codeVerifier = (string) ($_SESSION[self::KEY_CODE_VERIFIER] ?? '');
        $startedAt = (int) ($_SESSION[self::KEY_STARTED_AT] ?? 0);

        $this->clearHandshake();

        if ($expectedState === '' || $returnedState === '' || !hash_equals($expectedState, $returnedState)) {
            throw new SsoException('Invalid SSO state. Please try signing in again.', 'invalid_state');
        }

        if ($startedAt <= 0 || (time() - $startedAt) > self::HANDSHAKE_TTL) {
            throw new SsoException('SSO login request expired. Please try again.', 'handshake_expired');
        }

        if ($nonce === '' || $codeVerifier === '') {
            throw new SsoException('SSO session data is incomplete. Please try again.', 'handshake_incomplete');
        }

        return [
            'nonce' => $nonce,
            'code_verifier' => $codeVerifier,
        ];
    }

    public function clearHandshake(): void
    {
        $this->ensurePhpSession();
        unset(
            $_SESSION[self::KEY_STATE],
            $_SESSION[self::KEY_NONCE],
            $_SESSION[self::KEY_CODE_VERIFIER],
            $_SESSION[self::KEY_STARTED_AT]
        );
    }

    /**
     * Mark the current app session as established via SSO.
     */
    public function markAuthenticatedViaSso(string $email, ?string $idToken = null): void
    {
        $this->ensurePhpSession();
        $_SESSION[self::KEY_AUTH_VIA_SSO] = true;
        $_SESSION[self::KEY_SSO_EMAIL] = trim($email);

        if ($idToken !== null && $idToken !== '') {
            $_SESSION[self::KEY_ID_TOKEN] = $idToken;
        }
    }

    public function isAuthenticatedViaSso(): bool
    {
        $this->ensurePhpSession();
        return !empty($_SESSION[self::KEY_AUTH_VIA_SSO]);
    }

    public function getIdToken(): string
    {
        $this->ensurePhpSession();
        return (string) ($_SESSION[self::KEY_ID_TOKEN] ?? '');
    }

    /**
     * Clear SSO markers only (does not destroy the PHP session).
     */
    public function clearSsoMarkers(): void
    {
        $this->ensurePhpSession();
        unset(
            $_SESSION[self::KEY_AUTH_VIA_SSO],
            $_SESSION[self::KEY_SSO_EMAIL],
            $_SESSION[self::KEY_ID_TOKEN]
        );
        $this->clearHandshake();
    }

    public function setFlashError(string $message): void
    {
        $this->ensurePhpSession();
        $_SESSION[self::KEY_FLASH_ERROR] = $message;
    }

    public function pullFlashError(): string
    {
        $this->ensurePhpSession();
        $message = (string) ($_SESSION[self::KEY_FLASH_ERROR] ?? '');
        unset($_SESSION[self::KEY_FLASH_ERROR]);
        return $message;
    }

    private function randomToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}