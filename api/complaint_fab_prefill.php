<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_status.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$fabNumber = trim((string) ($_GET['fab_number'] ?? ''));

if ($fabNumber === '') {
    echo json_encode(['found' => false]);
    exit;
}

$username = current_username();
$userId = current_user_id($obconn);

if ($username === '' && ($userId === null || $userId <= 0)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

if (!isset($_SESSION['role'])) {
    admin_refresh_session_role($obconn);
}

if (is_system_admin() || is_management_user() || is_ccs_admin_user()) {
    $sql = '
        SELECT
            customer_name,
            street_1,
            street_2,
            pincode,
            city,
            district,
            state
        FROM complaints
        WHERE fab_number = :fab_number
          AND deleted_at IS NULL
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ';
    $params = [':fab_number' => $fabNumber];
} else {
    $scope = complaint_entry_list_scope($obconn);
    $sql = '
        SELECT
            customer_name,
            street_1,
            street_2,
            pincode,
            city,
            district,
            state
        FROM complaints
        WHERE fab_number = :fab_number
          AND ' . $scope['where'] . '
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ';
    $params = array_merge([':fab_number' => $fabNumber], $scope['params']);
}

$stmt = $obconn->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue(
        $key,
        $value,
        is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}

$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['found' => false]);
    exit;
}

echo json_encode([
    'found' => true,
    'customer_name' => (string) ($row['customer_name'] ?? ''),
    'street_1' => (string) ($row['street_1'] ?? ''),
    'street_2' => (string) ($row['street_2'] ?? ''),
    'pincode' => (string) ($row['pincode'] ?? ''),
    'city' => (string) ($row['city'] ?? ''),
    'district' => (string) ($row['district'] ?? ''),
    'state' => (string) ($row['state'] ?? ''),
], JSON_UNESCAPED_UNICODE);