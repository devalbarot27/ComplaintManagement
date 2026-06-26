<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/machine_model_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

try {
    $rows = machine_model_search($obconn, $term);
} catch (Throwable $e) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

foreach ($rows as $row) {
    $results[] = machine_model_to_select2_result($row);
}

echo json_encode(['results' => $results]);
