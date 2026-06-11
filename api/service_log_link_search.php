<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';

header('Content-Type: application/json; charset=utf-8');

$installedBaseId = (int) ($_GET['installed_base_id'] ?? 0);
$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

if ($installedBaseId <= 0) {
    echo json_encode(['results' => []]);
    exit;
}

$sql = "
    SELECT id, order_id, serial_number, engineer_name, visit_date, closure_date
    FROM service_logs
    WHERE deleted_at IS NULL
      AND installed_base_id = :installed_base_id
";
$params = [':installed_base_id' => $installedBaseId];

if ($term !== '') {
    $sql .= "
      AND (
            CAST(id AS TEXT) ILIKE :term
         OR order_id ILIKE :term
         OR engineer_name ILIKE :term
      )
    ";
    $params[':term'] = '%' . $term . '%';
}

$sql .= ' ORDER BY id DESC LIMIT 25';

$stmt = $obconn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

$results = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $label = '#' . (int) $row['id'] . ' — ' . $row['order_id'] . ' — Visit: ' . $row['visit_date'];

    $results[] = [
        'id' => (int) $row['id'],
        'text' => $label,
        'service_log_id' => (int) $row['id'],
    ];
}

echo json_encode(['results' => $results]);
