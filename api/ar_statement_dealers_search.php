<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/../includes/ar_statement_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if (!ar_statement_user_can_filter_dealers($obconn)) {
    echo json_encode(['results' => []]);
    exit;
}

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? $_GET['search'] ?? ''));
$code = trim((string) ($_GET['code'] ?? $_GET['id'] ?? ''));

try {
    if ($code !== '') {
        if (!ar_statement_is_allowed_cuno($obconn, $code)) {
            echo json_encode(['results' => []]);
            exit;
        }

        $dealer = ar_statement_dealer_get($dpconn, $obconn, $code);
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
        'results' => ar_statement_search_dealers($dpconn, $obconn, $term, 50),
    ]);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
}