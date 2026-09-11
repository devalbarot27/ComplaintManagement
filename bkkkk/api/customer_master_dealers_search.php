<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    echo json_encode(['results' => []]);
    exit;
}

login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);
admin_ensure_session_role($obconn);

$canSearch = rbac_has_permission($obconn, 'customer-master', 'view')
    || rbac_has_permission($obconn, 'customer-master', 'add')
    || rbac_has_permission($obconn, 'customer-master', 'edit')
    || rbac_has_permission($obconn, 'installed-base-capture', 'add')
    || rbac_has_permission($obconn, 'complaint-entry', 'add')
    || rbac_has_permission($obconn, 'order-booking', 'create-order');

if (!$canSearch) {
    http_response_code(403);
    echo json_encode(['results' => []]);
    exit;
}

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));
$code = trim((string) ($_GET['code'] ?? $_GET['id'] ?? ''));

try {
    if ($code !== '') {
        $dealer = customer_master_dealer_get($obconn, $code);
        if ($dealer === null) {
            echo json_encode(['results' => []]);
            exit;
        }

        echo json_encode([
            'results' => [[
                'id' => $dealer['code'],
                'text' => $dealer['text'],
                'name' => $dealer['name'],
            ]],
        ]);
        exit;
    }

    echo json_encode([
        'results' => customer_master_dealer_search($obconn, $term, 50),
    ]);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
}
