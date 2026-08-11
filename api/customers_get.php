<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$cuno = trim((string) ($_GET['cuno'] ?? ''));

if ($cuno === '') {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid customer code.']);
    exit;
}

$row = customer_get_by_cuno($obconn, $cuno);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

$addrCode = trim((string) ($row['adr_code'] ?? ''));
$addrLabel = customer_address_label($obconn, $addrCode);

api_json_echo([
    'cuno' => trim((string) ($row['cuno'] ?? '')),
    'cuname' => trim((string) ($row['cuname'] ?? '')),
    'adr_code' => $addrCode,
    'adr_code_text' => $addrLabel,
]);
