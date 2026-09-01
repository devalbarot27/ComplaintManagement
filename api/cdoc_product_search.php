<?php
session_start();

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/cdoc_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));
$productGroup = trim((string) ($_GET['product_group'] ?? ''));

try {
    $rows = cdoc_search_products($obconn, $term, $productGroup);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
foreach ($rows as $row) {
    $results[] = cdoc_product_to_select2_result($row);
}

echo json_encode(['results' => $results]);
