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

$canUpdate = rbac_has_permission($obconn, 'customer-master', 'edit')
    || rbac_has_permission($obconn, 'order-booking', 'create-order');

if (!$canUpdate) {
    http_response_code(403);
    api_json_echo(['success' => false, 'error' => 'Access denied. You do not have permission to update customers.']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    api_json_echo(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

$id = (int) ($_POST['customer_id'] ?? 0);
if ($id <= 0) {
    http_response_code(422);
    api_json_echo(['success' => false, 'error' => 'Select a customer to update.']);
    exit;
}

$existing = customer_master_get_by_id($obconn, $id);
if ($existing === null || !customer_master_user_can_access_record($obconn, $existing)) {
    http_response_code(404);
    api_json_echo(['success' => false, 'error' => 'Customer not found.']);
    exit;
}

$data = customer_master_from_post($_POST);
$data = customer_master_apply_dealer_rules($obconn, $data);
$gstRequired = isset($_POST['require_gst']) && (string) $_POST['require_gst'] === '1';
$validationError = customer_master_validate($obconn, $data, $gstRequired);

if ($validationError !== null) {
    http_response_code(422);
    api_json_echo([
        'success' => false,
        'error' => $validationError,
    ]);
    exit;
}

try {
    customer_master_update($obconn, $id, $data, current_username());
    $row = customer_master_get_by_id($obconn, $id);
    $label = $row ? customer_master_select2_label($row) : ($data['customer_name'] ?? '');

    api_json_echo([
        'success' => true,
        'id' => $id,
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
        'dealer_code' => trim((string) ($data['dealer_code'] ?? '')),
        'dealer_name' => trim((string) ($data['dealer_name'] ?? '')),
        'gst_number' => trim((string) ($data['gst_number'] ?? '')),
        'pan_number' => trim((string) ($data['pan_number'] ?? '')),
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    api_json_echo(['success' => false, 'error' => 'Failed to update customer.']);
}