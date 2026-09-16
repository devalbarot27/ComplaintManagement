<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
admin_ensure_session_role($obconn);

$canSearch = rbac_has_permission($obconn, 'installed-base-capture', 'view')
    || rbac_has_permission($obconn, 'complaint-entry', 'view')
    || rbac_has_permission($obconn, 'complaint-entry', 'add')
    || rbac_has_permission($obconn, 'customer-master', 'view')
    || rbac_has_permission($obconn, 'customer-master', 'add')
    || rbac_has_permission($obconn, 'customer-master', 'edit')
    || rbac_has_permission($obconn, 'contact', 'view')
    || rbac_has_permission($obconn, 'contact', 'add')
    || rbac_has_permission($obconn, 'contact', 'edit')
    || rbac_has_permission($obconn, 'order-booking', 'create-order');

if (!$canSearch) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. You do not have permission for this action.']);
    exit;
}

customer_master_ensure_schema($obconn);

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

if ($id > 0) {
    $row = customer_master_get_by_id($obconn, $id);
    if ($row === null || !customer_master_user_can_access_record($obconn, $row)) {
        echo json_encode(['results' => []]);
        exit;
    }

    echo json_encode([
        'results' => [[
            'id' => (int) $row['id'],
            'text' => customer_master_select2_label($row),
            'customer_name' => trim((string) ($row['customer_name'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'mobile' => trim((string) ($row['mobile'] ?? '')),
            'street_1' => trim((string) ($row['street_1'] ?? '')),
            'street_2' => trim((string) ($row['street_2'] ?? '')),
            'pincode' => trim((string) ($row['pincode'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'district' => trim((string) ($row['district'] ?? '')),
            'state' => trim((string) ($row['state'] ?? '')),
        ]],
    ]);
    exit;
}

echo json_encode([
    'results' => customer_master_search_select2($obconn, $term, 50),
]);