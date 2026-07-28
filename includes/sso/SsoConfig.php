<?php
/**
 * Loads and validates SSO configuration from includes/sso_config.php.
 */
class SsoConfig
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed>|null $config Optional override (mainly for tests).
     */
    public function __construct(?array $config = null)
    {
        if ($config !== null) {
            $this->config = $config;
            return;
        }

        $path = dirname(__DIR__) . '/sso_config.php';
        if (!is_readable($path)) {
            throw new SsoException('SSO configuration file is missing.', 'config_missing');
        }

        $loaded = require $path;
        if (!is_array($loaded)) {
            throw new SsoException('SSO configuration must return an array.', 'config_invalid');
        }

        $this->config = $loaded;
    }

    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    public function getProviderName(): string
    {
        $name = trim((string) ($this->config['provider_name'] ?? 'SSO'));
        return $name !== '' ? $name : 'SSO';
    }

    public function getClientId(): string
    {
        return trim((string) ($this->config['client_id'] ?? ''));
    }

    public function getClientSecret(): string
    {
        return (string) ($this->config['client_secret'] ?? '');
    }

    public function getAuthorizeUrl(): string
    {
        return trim((string) ($this->config['authorize_url'] ?? ''));
    }

    public function getTokenUrl(): string
    {
        return trim((string) ($this->config['token_url'] ?? ''));
    }

    public function getUserinfoUrl(): string
    {
        return trim((string) ($this->config['userinfo_url'] ?? ''));
    }

    public function getLogoutUrl(): string
    {
        return trim((string) ($this->config['logout_url'] ?? ''));
    }

    public function getJwksUrl(): string
    {
        return trim((string) ($this->config['jwks_url'] ?? ''));
    }

    public function getScopes(): string
    {
        return trim((string) ($this->config['scopes'] ?? 'openid profile email'));
    }

    public function getEmailClaim(): string
    {
        $claim = trim((string) ($this->config['email_claim'] ?? 'email'));
        return $claim !== '' ? $claim : 'email';
    }

    public function getFallbackEmailClaim(): string
    {
        return trim((string) ($this->config['fallback_email_claim'] ?? ''));
    }

    public function getIssuer(): string
    {
        return trim((string) ($this->config['issuer'] ?? ''));
    }

    public function getDashboardPath(): string
    {
        $path = trim((string) ($this->config['dashboard_path'] ?? 'index.php'));
        return $path !== '' ? $path : 'index.php';
    }

    public function getLoginPath(): string
    {
        $path = trim((string) ($this->config['login_path'] ?? 'login.php'));
        return $path !== '' ? $path : 'login.php';
    }

    public function shouldLogoutAtIdp(): bool
    {
        return !empty($this->config['logout_at_idp']);
    }

    public function getHttpTimeout(): int
    {
        $timeout = (int) ($this->config['http_timeout'] ?? 20);
        return $timeout > 0 ? $timeout : 20;
    }

    /**
     * Absolute redirect URI registered with the Identity Provider.
     */
    public function getRedirectUri(): string
    {
        $configured = trim((string) ($this->config['redirect_uri'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }

        return $this->buildAbsoluteUrl('sso_callback.php');
    }

    /**
     * Absolute post-logout redirect back to the local login page.
     */
    public function getPostLogoutRedirectUri(): string
    {
        return $this->buildAbsoluteUrl($this->getLoginPath());
    }

    /**
     * Ensure the minimum settings required to start an SSO login are present.
     *
     * @throws SsoException
     */
    public function assertReadyForLogin(): void
    {
        if (!$this->isEnabled()) {
            throw new SsoException('Single Sign-On is not enabled.', 'sso_disabled');
        }

        $missing = [];
        if ($this->getClientId() === '') {
            $missing[] = 'client_id';
        }
        if ($this->getAuthorizeUrl() === '') {
            $missing[] = 'authorize_url';
        }
        if ($this->getTokenUrl() === '') {
            $missing[] = 'token_url';
        }

        if ($missing !== []) {
            throw new SsoException(
                'SSO is not fully configured. Missing: ' . implode(', ', $missing) . '.',
                'config_incomplete'
            );
        }
    }

    private function buildAbsoluteUrl(string $relativePath): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = rtrim($scheme . '://' . $host . $scriptDir, '/');

        return $base . '/' . ltrim($relativePath, '/');
    }
}
