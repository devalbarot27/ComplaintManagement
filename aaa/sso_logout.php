<?php
/**
 * Dedicated SSO logout endpoint.
 * Prefer logout.php for the UI — it delegates here when the session was via SSO.
 */

session_start();

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/sso/bootstrap.php';

$sso = sso_create_services($obconn);
$sso['logout']->logout();