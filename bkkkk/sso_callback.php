<?php
/**
 * SSO OIDC callback — validates the IdP response, authenticates the local user
 * by email, creates a secure session, and redirects to the dashboard (index.php).
 */

session_start();

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/sso/bootstrap.php';

$sso = sso_create_services($obconn);

try {
    $sso['callback']->handle($_GET);
} catch (SsoException $e) {
    $sso['session']->clearHandshake();
    $sso['session']->setFlashError($e->getMessage());
    header('Location: login.php');
    exit;
} catch (Throwable $e) {
    $sso['session']->clearHandshake();
    $sso['session']->setFlashError('SSO login failed. Please try again or use password login.');
    header('Location: login.php');
    exit;
}