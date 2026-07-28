<?php
/**
 * SSO module bootstrap — loads classes and existing login helpers.
 *
 * Usage:
 *   require_once __DIR__ . '/includes/sso/bootstrap.php';
 *   $sso = sso_create_services($obconn);
 */

require_once dirname(__DIR__) . '/login_helpers.php';
require_once __DIR__ . '/SsoException.php';
require_once __DIR__ . '/SsoConfig.php';
require_once __DIR__ . '/SsoSessionManager.php';
require_once __DIR__ . '/SsoClient.php';
require_once __DIR__ . '/SsoUserAuthenticator.php';
require_once __DIR__ . '/SsoLoginService.php';
require_once __DIR__ . '/SsoCallbackHandler.php';
require_once __DIR__ . '/SsoLogoutService.php';

/**
 * Factory for SSO services wired to the complaint_management PDO connection.
 *
 * @return array{
 *   config: SsoConfig,
 *   session: SsoSessionManager,
 *   client: SsoClient,
 *   authenticator: SsoUserAuthenticator,
 *   login: SsoLoginService,
 *   callback: SsoCallbackHandler,
 *   logout: SsoLogoutService
 * }
 */
function sso_create_services(PDO $obconn): array
{
    $config = new SsoConfig();
    $session = new SsoSessionManager();
    $client = new SsoClient($config);
    $authenticator = new SsoUserAuthenticator($obconn);
    $login = new SsoLoginService($config, $client, $session);
    $callback = new SsoCallbackHandler($config, $client, $session, $authenticator);
    $logout = new SsoLogoutService($config, $client, $session);

    return [
        'config' => $config,
        'session' => $session,
        'client' => $client,
        'authenticator' => $authenticator,
        'login' => $login,
        'callback' => $callback,
        'logout' => $logout,
    ];
}

/**
 * Whether SSO is enabled and sufficiently configured for a login button.
 */
function sso_is_available(): bool
{
    try {
        $config = new SsoConfig();
        if (!$config->isEnabled()) {
            return false;
        }
        $config->assertReadyForLogin();
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Display name for the SSO login button.
 */
function sso_provider_display_name(): string
{
    try {
        return (new SsoConfig())->getProviderName();
    } catch (Throwable $e) {
        return 'SSO';
    }
}
