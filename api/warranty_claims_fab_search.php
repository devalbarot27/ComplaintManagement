<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

header('Content-Type: application/json; charset=utf-8');

installed_base_ensure_schema($obconn);

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

$listScope = after_market_list_scope($obconn);
$where = after_market_scope_where_for_alias($listScope['where'], 'ib');
$params = $listScope['params'];

if ($term !== '') {
    $where .= ' AND (ib.fab_number ILIKE :term OR cm.customer_name ILIKE :term OR CAST(ib.id AS TEXT) ILIKE :term)';
    $params[':term'] = '%' . $term . '%';
}

$stmt = $obconn->prepare("
    SELECT ib.id, ib.fab_number, cm.customer_name, ib.commissioning_date
    FROM installed_base ib
    " . installed_base_customer_join_sql('ib', 'cm') . "
    WHERE {$where}
    ORDER BY ib.id DESC
    LIMIT 20
");
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();

$results = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $label = '#' . (int) $row['id'] . ' - ' . trim((string) $row['fab_number']);
    if (trim((string) $row['customer_name']) !== '') {
        $label .= ' (' . trim((string) $row['customer_name']) . ')';
    }

    $results[] = [
        'id' => (int) $row['id'],
        'text' => $label,
        'fab_number' => (string) $row['fab_number'],
        'commissioning_date' => installed_base_format_date_for_input($row['commissioning_date']),
    ];
}

echo json_encode(['results' => $results]);
