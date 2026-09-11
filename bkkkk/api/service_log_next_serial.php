<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';

after_market_require_service_log_add_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'serial_number' => service_log_peek_next_serial_number_safe($obconn),
]);