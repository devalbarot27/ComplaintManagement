<?php
session_start();
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];

foreach (installed_base_search_orders($term) as $row) {
    $orderId = trim((string) $row['order_id']);
    $customerName = trim((string) ($row['customer_name'] ?? ''));

    $results[] = [
        'id' => $orderId,
        'text' => $orderId . ($customerName !== '' ? ' — ' . $customerName : ''),
        'fab_number' => trim((string) ($row['fab_number'] ?? '')),
        'customer_name' => $customerName,
        'invoice_date' => $row['invoice_date'] ?? '',
        'dealer_name' => trim((string) ($row['dealer_name'] ?? '')),
        'machine_model' => trim((string) ($row['machine_model'] ?? '')),
    ];
}

echo json_encode(['results' => $results]);
