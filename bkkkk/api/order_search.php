<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

try {
    foreach (installed_base_pending_order_search($dpconn, $term) as $row) {
        $results[] = installed_base_pending_order_to_select2_result($row);
    }
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
    exit;
}

echo json_encode(['results' => $results]);