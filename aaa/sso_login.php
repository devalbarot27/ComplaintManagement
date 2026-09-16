<?php
/**
 * SSO login entry point — redirects the browser to the Identity Provider.
 * Existing password / OTP login flows are unchanged.
 */

session_start();

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/sso/bootstrap.php';

$sso = sso_create_services($obconn);

try {
    $sso['login']->redirectToIdentityProvider();
} catch (SsoException $e) {
    $sso['session']->setFlashError($e->getMessage());
    header('Location: login.php');
    exit;
} catch (Throwable $e) {
    $sso['session']->setFlashError('Unable to start SSO login. Please try again or use password login.');
    header('Location: login.php');
    exit;
}