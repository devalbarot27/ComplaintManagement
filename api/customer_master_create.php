<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    api_json_echo(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);

$canCreate = rbac_has_permission($obconn, 'customer-master', 'add');

if (!$canCreate) {
    http_response_code(403);
    api_json_echo(['success' => false, 'error' => 'Access denied. You do not have permission to add customers.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    api_json_echo(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$data = customer_master_from_post($_POST);
$data = customer_master_apply_dealer_rules($obconn, $data);
$validationError = customer_master_validate($obconn, $data);

if ($validationError !== null) {
    http_response_code(422);
    api_json_echo([
        'success' => false,
        'error' => $validationError,
    ]);
    exit;
}

if (customer_master_email_exists($obconn, $data['email'])) {
    http_response_code(422);
    api_json_echo([
        'success' => false,
        'error' => 'Email already exists. Please choose a different email.',
        'field_errors' => ['email' => ['Email already exists']],
    ]);
    exit;
}

if (customer_master_mobile_exists($obconn, $data['mobile'])) {
    http_response_code(422);
    api_json_echo([
        'success' => false,
        'error' => 'Mobile already exists. Please choose a different mobile number.',
        'field_errors' => ['mobile' => ['Mobile already exists']],
    ]);
    exit;
}

try {
    $newId = customer_master_insert($obconn, $data, current_username());
    if ($newId <= 0) {
        http_response_code(500);
        api_json_echo(['success' => false, 'error' => 'Failed to save customer.']);
        exit;
    }

    $row = customer_master_get_by_id($obconn, $newId);
    $label = $row ? customer_master_select2_label($row) : ($data['customer_name'] ?? '');

    api_json_echo([
        'success' => true,
        'id' => $newId,
        'text' => $label,
        'customer_name' => trim((string) ($data['customer_name'] ?? '')),
        'email' => trim((string) ($data['email'] ?? '')),
        'mobile' => trim((string) ($data['mobile'] ?? '')),
        'street_1' => trim((string) ($data['street_1'] ?? '')),
        'street_2' => trim((string) ($data['street_2'] ?? '')),
        'pincode' => trim((string) ($data['pincode'] ?? '')),
        'city' => trim((string) ($data['city'] ?? '')),
        'district' => trim((string) ($data['district'] ?? '')),
        'state' => trim((string) ($data['state'] ?? '')),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    api_json_echo(['success' => false, 'error' => 'Failed to save customer.']);
}
