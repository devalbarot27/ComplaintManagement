<?php
/**
 * SSO configuration (OIDC / OAuth 2.0 Authorization Code + PKCE).
 *
 * Edit the $defaults array below for local/XAMPP setups.
 * Environment variables (SSO_*) override these values when set — preferred in production.
 *
 * Register this redirect URI with your Identity Provider:
 *   https://your-host/.../sso_callback.php
 *
 * Required when enabled:
 *   client_id, authorize_url, token_url
 * Recommended:
 *   client_secret, issuer, jwks_url, userinfo_url, logout_url
 */

$defaults = [
    // Set to true after filling IdP settings below
    'enabled' => false,

    // Label on the login page button
    'provider_name' => 'Company SSO',

    'client_id' => '',
    'client_secret' => '',

    // Example (Azure AD):
    // authorize_url => https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize
    // token_url     => https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token
    // userinfo_url  => https://graph.microsoft.com/oidc/userinfo
    // logout_url    => https://login.microsoftonline.com/{tenant}/oauth2/v2.0/logout
    // jwks_url      => https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys
    // issuer        => https://login.microsoftonline.com/{tenant}/v2.0
    'authorize_url' => '',
    'token_url' => '',
    'userinfo_url' => '',
    'logout_url' => '',
    'jwks_url' => '',
    'issuer' => '',

    'scopes' => 'openid profile email',

    // Leave empty to auto-detect from the current request
    'redirect_uri' => '',

    // Claim matched against user_master.email
    'email_claim' => 'email',
    'fallback_email_claim' => 'preferred_username',

    'dashboard_path' => 'index.php',
    'login_path' => 'login.php',

    // Also end the IdP session on logout when logout_url is set
    'logout_at_idp' => true,

    'http_timeout' => 20,
];

/**
 * Read an env var when present; otherwise keep the file default.
 *
 * @param mixed $default
 * @return mixed
 */
$envOr = static function (string $name, $default) {
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }
    return $value;
};

return [
    'enabled' => filter_var(
        $envOr('SSO_ENABLED', $defaults['enabled'] ? '1' : '0'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'provider_name' => (string) $envOr('SSO_PROVIDER_NAME', $defaults['provider_name']),
    'client_id' => (string) $envOr('SSO_CLIENT_ID', $defaults['client_id']),
    'client_secret' => (string) $envOr('SSO_CLIENT_SECRET', $defaults['client_secret']),
    'authorize_url' => (string) $envOr('SSO_AUTHORIZE_URL', $defaults['authorize_url']),
    'token_url' => (string) $envOr('SSO_TOKEN_URL', $defaults['token_url']),
    'userinfo_url' => (string) $envOr('SSO_USERINFO_URL', $defaults['userinfo_url']),
    'logout_url' => (string) $envOr('SSO_LOGOUT_URL', $defaults['logout_url']),
    'jwks_url' => (string) $envOr('SSO_JWKS_URL', $defaults['jwks_url']),
    'scopes' => (string) $envOr('SSO_SCOPES', $defaults['scopes']),
    'redirect_uri' => (string) $envOr('SSO_REDIRECT_URI', $defaults['redirect_uri']),
    'email_claim' => (string) $envOr('SSO_EMAIL_CLAIM', $defaults['email_claim']),
    'fallback_email_claim' => (string) $envOr('SSO_FALLBACK_EMAIL_CLAIM', $defaults['fallback_email_claim']),
    'issuer' => (string) $envOr('SSO_ISSUER', $defaults['issuer']),
    'dashboard_path' => $defaults['dashboard_path'],
    'login_path' => $defaults['login_path'],
    'logout_at_idp' => filter_var(
        $envOr('SSO_LOGOUT_AT_IDP', $defaults['logout_at_idp'] ? '1' : '0'),
        FILTER_VALIDATE_BOOLEAN
    ),
    'http_timeout' => (int) $envOr('SSO_HTTP_TIMEOUT', $defaults['http_timeout']),
];
