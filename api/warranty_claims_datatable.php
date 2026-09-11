<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';
require_once dirname(__DIR__) . '/includes/amc_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    installed_base_ensure_schema($obconn);

    $allowedOrderColumns = [
        'id',
        'fab_number',
        'customer_name',
        'commissioning_date',
    ];

    $req = dt_parse_request($allowedOrderColumns, 'id');
    $listScope = after_market_list_scope($obconn);
    $baseWhere = after_market_scope_where_for_alias($listScope['where'], 'ib');
    $filterParams = $listScope['params'];
    $customerJoin = installed_base_customer_join_sql('ib', 'cm');

    $recordsTotalStmt = $obconn->prepare("
        SELECT COUNT(*) AS total
        FROM installed_base ib
        WHERE {$baseWhere}
    ");
    foreach ($filterParams as $key => $value) {
        $recordsTotalStmt->bindValue($key, $value);
    }
    $recordsTotalStmt->execute();
    $recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $filterWhere = $baseWhere;

    if ($req['searchValue'] !== '') {
        $warrantyStatusSql = installed_base_warranty_status_sql('ib.commissioning_date');
        $filterWhere .= ' AND (
            ib.fab_number ILIKE :search
            OR ib.dealer_name ILIKE :search
            OR ib.machine_model ILIKE :search
            OR ib.machine_model_code ILIKE :search
            OR cm.customer_name ILIKE :search
            OR CAST(ib.id AS TEXT) ILIKE :search
            OR CAST(ib.commissioning_date AS TEXT) ILIKE :search
            OR TO_CHAR(ib.commissioning_date, \'DD Mon YYYY\') ILIKE :search
            OR TO_CHAR(ib.commissioning_date, \'DD/MM/YYYY\') ILIKE :search
            OR TO_CHAR(ib.commissioning_date, \'DD.MM.YYYY\') ILIKE :search
            OR TO_CHAR(ib.commissioning_date, \'YYYY-MM-DD\') ILIKE :search
            OR (' . $warrantyStatusSql . ') ILIKE :search
        )';
        $filterParams[':search'] = '%' . $req['searchValue'] . '%';
    }

    $countFilteredStmt = $obconn->prepare("
        SELECT COUNT(*) AS total
        FROM installed_base ib
        {$customerJoin}
        WHERE {$filterWhere}
    ");
    foreach ($filterParams as $key => $value) {
        $countFilteredStmt->bindValue($key, $value);
    }
    $countFilteredStmt->execute();
    $recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

    $orderColumn = $req['orderColumn'];
    if ($orderColumn === 'customer_name') {
        $orderColumnSql = 'cm.customer_name';
    } elseif (in_array($orderColumn, ['id', 'fab_number', 'commissioning_date'], true)) {
        $orderColumnSql = 'ib.' . $orderColumn;
    } else {
        $orderColumnSql = 'ib.id';
    }

    $dataQuery = "
        SELECT
            ib.id,
            ib.fab_number,
            cm.customer_name,
            ib.machine_model,
            ib.machine_model_code,
            ib.commissioning_date
        FROM installed_base ib
        {$customerJoin}
        WHERE {$filterWhere}
        ORDER BY {$orderColumnSql} {$req['orderDir']}
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $obconn->prepare($dataQuery);
    foreach ($filterParams as $key => $value) {
        $dataStmt->bindValue($key, $value);
    }
    $dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
    $dataStmt->execute();

    $data = [];
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
    $amcLookup = amc_coverage_lookup(
        $obconn,
        array_map(static fn ($row) => (int) ($row['id'] ?? 0), $rows),
        array_map(static fn ($row) => (string) ($row['fab_number'] ?? ''), $rows)
    );
    foreach ($rows as $row) {
        $warranty = installed_base_warranty_status($row['commissioning_date'] ?? null);
        $customerName = trim((string) ($row['customer_name'] ?? ''));
        $fabNumber = trim((string) ($row['fab_number'] ?? ''));
        $commissioned = installed_base_format_date($row['commissioning_date'] ?? null);
        $installedBaseId = (int) ($row['id'] ?? 0);
        $fabHref = $installedBaseId > 0
            ? 'installed_base_details.php?id=' . rawurlencode(base64_encode((string) $installedBaseId))
            : '';
        $coverage = amc_coverage_resolve($amcLookup, $installedBaseId, $fabNumber);

        $fabHtml = ($fabNumber !== '' && $fabHref !== '')
            ? '<a href="' . htmlspecialchars($fabHref, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">'
                . htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8')
                . '</a>'
            : ($fabNumber !== ''
                ? '<span class="warranty-grid-fab">' . htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="text-muted">-</span>');

        $data[] = [
            'id' => '<span class="warranty-grid-idd">#' . $installedBaseId . '</span>',
            'fab_number' => amc_with_coverage_html($fabHtml, $coverage, $row['commissioning_date'] ?? null),
            'customer_name' => $customerName !== ''
                ? htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8')
                : '<span class="text-muted">-</span>',
            'machine_model' => '<span class="warranty-grid-model">'
                . htmlspecialchars(installed_base_machine_model_label($row), ENT_QUOTES, 'UTF-8')
                . '</span>',
            'commissioning_date' => $commissioned !== '' && $commissioned !== '-'
                ? '<span class="warranty-grid-date">' . htmlspecialchars($commissioned, ENT_QUOTES, 'UTF-8') . '</span>'
                : '<span class="text-muted">-</span>',
            'warranty_status' => installed_base_warranty_status_badge_html($warranty),
        ];
    }

    dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'draw' => (int) ($_POST['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'Failed to load warranty claims.',
    ]);
}