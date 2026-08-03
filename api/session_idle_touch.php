<?php

session_start();

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
login_touch_activity();

echo json_encode(['ok' => true]);