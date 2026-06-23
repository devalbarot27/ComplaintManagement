<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/ln_invoice_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

foreach (ln_invoice_search_fabno($dpconn, $term) as $row) {
    $results[] = ln_invoice_fabno_to_select2_result($row);
}

echo json_encode(['results' => $results]);
