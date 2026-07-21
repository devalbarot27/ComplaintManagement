<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$fabNumber = trim((string) ($_GET['fab_number'] ?? ''));
$complaintId = (int) ($_GET['complaint_id'] ?? 0);

if ($fabNumber === '' && $complaintId <= 0) {
    echo json_encode(['found' => false]);
    exit;
}

require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

$row = installed_base_fab_prefill_row(
    $obconn,
    $fabNumber,
    $complaintId > 0 ? $complaintId : null
);

if (!$row) {
    echo json_encode(['found' => false]);
    exit;
}

$payload = [
    'found' => true,
    'customer_name' => (string) ($row['customer_name'] ?? ''),
    'street_1' => (string) ($row['street_1'] ?? ''),
    'street_2' => (string) ($row['street_2'] ?? ''),
    'pincode' => (string) ($row['pincode'] ?? ''),
    'city' => (string) ($row['city'] ?? ''),
    'district' => (string) ($row['district'] ?? ''),
    'state' => (string) ($row['state'] ?? ''),
    'mobile' => (string) ($row['mobile'] ?? ''),
    'email' => (string) ($row['email'] ?? ''),
    'remarks' => (string) ($row['remarks'] ?? ''),
];
array_walk_recursive($payload, function (&$val) {
    if (is_string($val)) {
        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
});
echo json_encode($payload, JSON_UNESCAPED_UNICODE);
