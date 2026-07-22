<?php
// Configure session cookie flags + expiration before session_start().
if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionLifetime = 8 * 60 * 60; // 8 hours

    ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
    ini_set('session.cookie_lifetime', (string) $sessionLifetime);
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => $sessionLifetime,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/module_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$row = module_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Module not found.']);
    exit;
}

$safeId = (int) ($row['id'] ?? 0);
$safeName = (string) ($row['module_name'] ?? '');
$safeSlug = (string) ($row['module_slug'] ?? '');
$safeDescription = (string) ($row['description'] ?? '');
$safeStatus = (string) ($row['status'] ?? '');
unset($row);

api_json_echo([
    'id' => $safeId,
    'module_name' => $safeName,
    'module_slug' => $safeSlug,
    'description' => $safeDescription,
    'status' => $safeStatus,
]);
