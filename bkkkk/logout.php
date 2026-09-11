<?php
/**
 * Application logout.
 * If the user signed in via SSO, optionally end the IdP session as well.
 * Password / OTP sessions continue to redirect to login.php only.
 */

require_once __DIR__ . '/includes/login_helpers.php';
login_start_php_session();
require_once __DIR__ . '/includes/sso/bootstrap.php';

$ssoSession = new SsoSessionManager();

if ($ssoSession->isAuthenticatedViaSso()) {
    try {
        require_once __DIR__ . '/pdo_obconn.php';
        $sso = sso_create_services($obconn);
        $sso['logout']->logout();
        // logout() always exits
    } catch (Throwable $e) {
        // Fall through to local logout if SSO logout cannot run
    }
}

login_destroy_session();

header('Location: login.php');
exit;