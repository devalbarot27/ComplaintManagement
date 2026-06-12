<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($term === '') {
    echo json_encode(['results' => []]);
    exit;
}

$stmt = $obconn->prepare("
    SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, running_hours
    FROM installed_base
    WHERE deleted_at IS NULL
      AND (
            order_id ILIKE :term
         OR fab_number ILIKE :term
         OR customer_name ILIKE :term
         OR machine_model ILIKE :term
      )
    ORDER BY id DESC
    LIMIT 25
");
$stmt->bindValue(':term', '%' . $term . '%');
$stmt->execute();

$results = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $label = '#' . (int) $row['id'] . ' — ' . $row['order_id'] . ' — ' . $row['customer_name'];

    $results[] = [
        'id' => (int) $row['id'],
        'text' => $label,
        'installed_base_id' => (int) $row['id'],
        'order_id' => $row['order_id'],
        'order_ref_id' => (int) ($row['order_ref_id'] ?? 0),
        'serial_number' => $row['fab_number'],
        'machine_model' => $row['machine_model'],
        'running_hours' => $row['running_hours'],
    ];
}

echo json_encode(['results' => $results]);
