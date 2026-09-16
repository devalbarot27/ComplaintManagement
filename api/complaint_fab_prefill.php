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
    echo json_encode(['found' => false, 'lock_customer' => false]);
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

$stmt = $obconn->prepare('
    SELECT
        c.customer_id,
        cm.customer_name,
        cm.mobile,
        cm.city
    FROM complaints c
    ' . complaint_customer_join_sql('c', 'cm') . '
    WHERE TRIM(c.fab_number) = TRIM(:fab_number)
      AND c.deleted_at IS NULL
      AND c.customer_id IS NOT NULL
    ORDER BY c.created_at DESC, c.id DESC
    LIMIT 1
');
$stmt->bindValue(':fab_number', $fabNumber);
$stmt->execute();
$complaintRow = $stmt->fetch(PDO::FETCH_ASSOC);

if ($complaintRow && (int) ($complaintRow['customer_id'] ?? 0) > 0) {
    echo json_encode([
        'found' => true,
        'lock_customer' => true,
        'from_existing_complaint' => true,
        'customer_id' => (int) $complaintRow['customer_id'],
        'customer_label' => customer_master_select2_label($complaintRow),
        'customer_name' => (string) ($complaintRow['customer_name'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ibRow = installed_base_latest_record_by_fab($obconn, $fabNumber);
if ($ibRow !== null && (int) ($ibRow['customer_id'] ?? 0) > 0) {
    echo json_encode([
        'found' => true,
        'lock_customer' => false,
        'from_existing_complaint' => false,
        'customer_id' => (int) $ibRow['customer_id'],
        'customer_label' => (string) ($ibRow['customer_label'] ?? ''),
        'customer_name' => (string) ($ibRow['customer_name'] ?? ''),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['found' => false, 'lock_customer' => false]);