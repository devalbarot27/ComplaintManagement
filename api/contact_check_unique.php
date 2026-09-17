<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/contact_helpers.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'errors' => ['email' => ['Unauthorized.']],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
admin_ensure_session_role($obconn);
contact_ensure_rbac($obconn);

$permissions = contact_action_permissions($obconn);
if (!$permissions['add'] && !$permissions['edit']) {
    http_response_code(403);
    echo json_encode([
        'valid' => false,
        'errors' => [
            'email' => ['Access denied.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

contact_ensure_schema($obconn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'valid' => false,
        'errors' => [
            'email' => ['Method not allowed. Use POST.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'valid' => true,
    'errors' => [],
], JSON_UNESCAPED_UNICODE);