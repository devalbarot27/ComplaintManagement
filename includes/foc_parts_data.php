<?php
require_once __DIR__ . '/includes/login_helpers.php';
login_start_php_session();

include 'pdo_obconn.php';
require_once 'includes/rbac_access_helpers.php';
require_once 'includes/warranty_claims_helpers.php';

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
admin_ensure_session_role($obconn);

header('Content-Type: application/json; charset=utf-8');

if (!rbac_user_can($obconn, 'foc-parts', 'view')) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'search_items':
        $search = trim((string) ($_GET['search'] ?? $_POST['search'] ?? ''));
        $rows = warranty_claims_search_spare_items($obconn, $search);
        $results = array_map(static function (array $row): array {
            return [
                'id' => $row['tplcode'],
                'text' => $row['tplcode'] . ' - ' . $row['tpldesc'],
                'part_number' => $row['tplcode'],
                'part_description' => $row['tpldesc'],
            ];
        }, $rows);
        echo json_encode(['results' => $results]);
        break;

    case 'complaint_items':
        $complaintId = (int) ($_GET['complaint_id'] ?? $_POST['complaint_id'] ?? 0);
        $complaint = warranty_claims_find_complaint($obconn, $complaintId);
        if ($complaint === null) {
            echo json_encode(['items' => []]);
            break;
        }
        echo json_encode(['items' => warranty_claims_existing_items_for_complaint($obconn, $complaintId)]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action.']);
        break;
}
