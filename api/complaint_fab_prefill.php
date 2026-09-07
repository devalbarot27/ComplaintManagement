<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_status.php';
require_once dirname(__DIR__) . '/includes/complaint_address_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

rbac_require_api_access($obconn);
complaint_ensure_schema($obconn);

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

// Prefer installed base customer for this FAB when available.
$ibRow = installed_base_latest_record_by_fab($obconn, $fabNumber);
if ($ibRow !== null && (int) ($ibRow['customer_id'] ?? 0) > 0) {
    echo json_encode([
        'found' => true,
        'customer_id' => (int) $ibRow['customer_id'],
        'customer_label' => (string) ($ibRow['customer_label'] ?? ''),
        'customer_name' => (string) ($ibRow['customer_name'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (is_system_admin() || is_management_user() || is_ccs_admin_user()) {
    $sql = '
        SELECT
            c.customer_id,
            cm.customer_name,
            cm.mobile,
            cm.city
        FROM complaints c
        ' . complaint_customer_join_sql('c', 'cm') . '
        WHERE c.fab_number = :fab_number
          AND c.deleted_at IS NULL
          AND c.customer_id IS NOT NULL
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT 1
    ';
    $params = [':fab_number' => $fabNumber];
} else {
    $scope = complaint_entry_list_scope($obconn);
    $scopeWhere = complaint_scope_where_for_alias($scope['where'], 'c');
    $sql = '
        SELECT
            c.customer_id,
            cm.customer_name,
            cm.mobile,
            cm.city
        FROM complaints c
        ' . complaint_customer_join_sql('c', 'cm') . '
        WHERE c.fab_number = :fab_number
          AND ' . $scopeWhere . '
          AND c.customer_id IS NOT NULL
        ORDER BY c.created_at DESC, c.id DESC
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

if (!$row || (int) ($row['customer_id'] ?? 0) <= 0) {
    echo json_encode(['found' => false]);
    exit;
}

echo json_encode([
    'found' => true,
    'customer_id' => (int) $row['customer_id'],
    'customer_label' => customer_master_select2_label($row),
    'customer_name' => (string) ($row['customer_name'] ?? ''),
], JSON_UNESCAPED_UNICODE);
