<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/ar_statement_helpers.php';

$canViewArStatement = rbac_can_access_menu($obconn, 'ar_statement.php');
if (!$canViewArStatement) {
    header('Location: access_denied.php');
    exit;
}

$active_menu = 'ar_statement';

$canFilterDealers = ar_statement_user_can_filter_dealers($obconn);
$canViewAllDealers = ar_statement_user_can_view_all_dealers();
$cuno = ar_statement_resolve_cuno($obconn);
$requiresDealerSelection = $canFilterDealers && !$canViewAllDealers;
$selectedDealer = $cuno !== '' ? ar_statement_dealer_get($dpconn, $obconn, $cuno) : null;
$dealerFilterOptions = $canFilterDealers
    ? ar_statement_dealer_options_like_installed_base($obconn)
    : [];

$ledgerBaseConditions = [];
$ledgerBaseParams = [];
if ($cuno !== '') {
    $ledgerBaseConditions[] = 'TRIM(cuno) = TRIM(:cuno)';
    $ledgerBaseParams[':cuno'] = $cuno;
} elseif ($requiresDealerSelection) {
    $ledgerBaseConditions[] = '1 = 0';
}
$ledgerBaseWhere = $ledgerBaseConditions ? ' WHERE ' . implode(' AND ', $ledgerBaseConditions) : '';
$ledgerDataRequest = isset($_POST['ledger_datatable']);
$ledgerDownloadRequest = ($_GET['download'] ?? '') === 'csv';

if ($ledgerDataRequest || $ledgerDownloadRequest) {
    $draw = max(0, (int)($_POST['draw'] ?? 0));
    $filteredConditions = $ledgerBaseConditions;
    $filteredParams = $ledgerBaseParams;
    $statusFilter = (string)($_POST['status'] ?? $_GET['status'] ?? '');
    if ($statusFilter === 'outstanding') {
        $filteredConditions[] = 'COALESCE(amtout, 0) > 0';
    } elseif ($statusFilter === 'settled') {
        $filteredConditions[] = 'COALESCE(amtout, 0) <= 0';
    }

    $searchValue = trim((string)($_POST['search']['value'] ?? $_GET['search'] ?? ''));
    if ($searchValue !== '') {
        $searchColumns = [
            "COALESCE(CAST(dpst AS TEXT), '')",
            "COALESCE(CAST(docdt AS TEXT), '')",
            "COALESCE(CAST(invpre AS TEXT), '') || '-' || COALESCE(CAST(invno AS TEXT), '')",
            "COALESCE(CAST(currency AS TEXT), '')",
            "COALESCE(CAST(invamt AS TEXT), '')",
            "COALESCE(CAST(recvamt AS TEXT), '')",
            "COALESCE(CAST(amtout AS TEXT), '')",
            "COALESCE(CAST(less30 AS TEXT), '')",
            "COALESCE(CAST(less40 AS TEXT), '')",
            "COALESCE(CAST(less45 AS TEXT), '')",
            "COALESCE(CAST(less50 AS TEXT), '')",
            "COALESCE(CAST(less60 AS TEXT), '')",
            "COALESCE(CAST(less90 AS TEXT), '')",
            "COALESCE(CAST(more90 AS TEXT), '')",
            "COALESCE(CAST(duedate AS TEXT), '')",
        ];
        $searchConditions = [];
        foreach ($searchColumns as $index => $column) {
            $parameter = ':ledgerSearch' . $index;
            $searchConditions[] = $column . ' ILIKE ' . $parameter;
            $filteredParams[$parameter] = '%' . $searchValue . '%';
        }
        $filteredConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
    }
    $filteredWhere = $filteredConditions ? ' WHERE ' . implode(' AND ', $filteredConditions) : '';

    $orderColumns = [
        0 => 'dpst',
        1 => 'docdt',
        2 => 'invno',
        3 => 'currency',
        4 => 'invamt',
        5 => 'recvamt',
        6 => 'amtout',
        7 => 'less30',
        8 => 'less40',
        9 => 'less45',
        10 => 'less50',
        11 => 'less60',
        12 => 'less90',
        13 => 'more90',
        14 => 'duedate',
    ];
    $orderIndex = (int)($_POST['order'][0]['column'] ?? $_GET['order_column'] ?? 1);
    $orderColumn = $orderColumns[$orderIndex] ?? 'docdt';
    $orderDirection = strtoupper((string)($_POST['order'][0]['dir'] ?? $_GET['order_dir'] ?? 'DESC')) === 'ASC'
        ? 'ASC'
        : 'DESC';

    try {
        $totalStmt = $dpconn->prepare('SELECT COUNT(*) FROM arst_new' . $ledgerBaseWhere);
        $totalStmt->execute($ledgerBaseParams);
        $recordsTotal = (int)$totalStmt->fetchColumn();

        $filteredCountStmt = $dpconn->prepare('SELECT COUNT(*) FROM arst_new' . $filteredWhere);
        $filteredCountStmt->execute($filteredParams);
        $recordsFiltered = (int)$filteredCountStmt->fetchColumn();

        $rowsSql = '
            SELECT dpst, docdt, invpre, invno, currency, invamt, recvamt, amtout,
                   less30, less40, less45, less50, less60, less90, more90, duedate
            FROM arst_new' . $filteredWhere . '
            ORDER BY ' . $orderColumn . ' ' . $orderDirection . ', invpre, invno';

        if ($ledgerDataRequest) {
            $pageStart = max(0, (int)($_POST['start'] ?? 0));
            $pageLength = min(100, max(1, (int)($_POST['length'] ?? 10)));
            $rowsSql .= ' LIMIT :ledgerLimit OFFSET :ledgerOffset';
        }

        $rowsStmt = $dpconn->prepare($rowsSql);
        foreach ($filteredParams as $parameter => $value) {
            $rowsStmt->bindValue($parameter, $value, PDO::PARAM_STR);
        }
        if ($ledgerDataRequest) {
            $rowsStmt->bindValue(':ledgerLimit', $pageLength, PDO::PARAM_INT);
            $rowsStmt->bindValue(':ledgerOffset', $pageStart, PDO::PARAM_INT);
        }
        $rowsStmt->execute();

        if ($ledgerDownloadRequest) {
            $csvTotalsStmt = $dpconn->prepare('
                SELECT COALESCE(SUM(invamt), 0) AS invamt,
                       COALESCE(SUM(recvamt), 0) AS recvamt,
                       COALESCE(SUM(amtout), 0) AS amtout,
                       COALESCE(SUM(less30), 0) AS less30,
                       COALESCE(SUM(less40), 0) AS less40,
                       COALESCE(SUM(less45), 0) AS less45,
                       COALESCE(SUM(less50), 0) AS less50,
                       COALESCE(SUM(less60), 0) AS less60,
                       COALESCE(SUM(less90), 0) AS less90,
                       COALESCE(SUM(more90), 0) AS more90
                FROM arst_new' . $filteredWhere
            );
            $csvTotalsStmt->execute($filteredParams);
            $csvTotals = $csvTotalsStmt->fetch(PDO::FETCH_ASSOC);

            $safeDealer = preg_replace('/[^A-Za-z0-9._-]/', '_', $cuno);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="AR_Statement' . ($safeDealer !== '' ? '_' . $safeDealer : '') . '.csv"');
            $csv = fopen('php://output', 'w');
            fputcsv($csv, [
                'DPST', 'Document Date', 'Invoice No', 'Currency', 'Invoice Amt',
                'Received Amt', 'Outstanding Amt', 'Less than 30 days', '31-40 days',
                '41-45 days', '46-50 days', '51-60 days', '61-90 days', 'Above 90 days', 'Due Date',
            ]);
            while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($csv, [
                    $row['dpst'], $row['docdt'], trim((string)$row['invpre']) . '-' . trim((string)$row['invno']),
                    trim((string)$row['currency']) !== '' ? $row['currency'] : 'INR',
                    round((float)$row['invamt']), round((float)$row['recvamt']), round((float)$row['amtout']),
                    round((float)$row['less30']), round((float)$row['less40']), round((float)$row['less45']),
                    round((float)$row['less50']), round((float)$row['less60']), round((float)$row['less90']),
                    round((float)$row['more90']), $row['duedate'],
                ]);
            }
            fputcsv($csv, [
                'Total Amount', '', '', '', round((float)$csvTotals['invamt']), round((float)$csvTotals['recvamt']),
                round((float)$csvTotals['amtout']), round((float)$csvTotals['less30']), round((float)$csvTotals['less40']),
                round((float)$csvTotals['less45']), round((float)$csvTotals['less50']), round((float)$csvTotals['less60']),
                round((float)$csvTotals['less90']), round((float)$csvTotals['more90']), '',
            ]);
            fclose($csv);
            exit;
        }

        $formatDate = static function ($value): string {
            $timestamp = $value !== null && trim((string)$value) !== '' ? strtotime((string)$value) : false;
            return $timestamp ? date('d M Y', $timestamp) : '';
        };
        $formatAmount = static function ($value): float {
            return round((float)$value);
        };
        $data = [];
        while ($row = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                'dpst' => (string)$row['dpst'],
                'docdt' => $formatDate($row['docdt']),
                'invoiceNo' => trim((string)$row['invpre']) . '-' . trim((string)$row['invno']),
                'currency' => trim((string)$row['currency']) !== '' ? (string)$row['currency'] : 'INR',
                'invamt' => $formatAmount($row['invamt']),
                'recvamt' => $formatAmount($row['recvamt']),
                'amtout' => $formatAmount($row['amtout']),
                'less30' => $formatAmount($row['less30']),
                'less40' => $formatAmount($row['less40']),
                'less45' => $formatAmount($row['less45']),
                'less50' => $formatAmount($row['less50']),
                'less60' => $formatAmount($row['less60']),
                'less90' => $formatAmount($row['less90']),
                'more90' => $formatAmount($row['more90']),
                'duedate' => $formatDate($row['duedate']),
            ];
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $data,
        ], JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $error) {
        error_log('[Vayu Power] AR DataTables request failed: ' . $error->getMessage());
        http_response_code(500);
        if ($ledgerDownloadRequest) {
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Unable to generate the AR statement.';
        } else {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Unable to load the AR ledger.',
            ]);
        }
    }
    exit;
}

$summary = [
    'invamt' => 0.0,
    'recvamt' => 0.0,
    'amtout' => 0.0,
    'less30' => 0.0,
    'less40' => 0.0,
    'less45' => 0.0,
    'less50' => 0.0,
    'less60' => 0.0,
    'less90' => 0.0,
    'more90' => 0.0,
];
$asOfDate = '';

if ($cuno !== '' || $requiresDealerSelection) {
    $summaryStmt = $dpconn->prepare('
        SELECT
            COALESCE(SUM(invamt), 0) AS invamt,
            COALESCE(SUM(recvamt), 0) AS recvamt,
            COALESCE(SUM(amtout), 0) AS amtout,
            COALESCE(SUM(less30), 0) AS less30,
            COALESCE(SUM(less40), 0) AS less40,
            COALESCE(SUM(less45), 0) AS less45,
            COALESCE(SUM(less50), 0) AS less50,
            COALESCE(SUM(less60), 0) AS less60,
            COALESCE(SUM(less90), 0) AS less90,
            COALESCE(SUM(more90), 0) AS more90,
            MAX(docdt) AS max_docdt
        FROM arst_new
        WHERE TRIM(cuno) = TRIM(:cuno)
    ');
    $summaryStmt->bindValue(':cuno', $cuno);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    if ($summaryRow) {
        foreach ($summary as $key => $default) {
            $summary[$key] = (float) $summaryRow[$key];
        }
        $asOfDate = (string) ($summaryRow['max_docdt'] ?? '');
    }

} else {
    $summaryStmt = $dpconn->prepare('
        SELECT
            COALESCE(SUM(invamt), 0) AS invamt,
            COALESCE(SUM(recvamt), 0) AS recvamt,
            COALESCE(SUM(amtout), 0) AS amtout,
            COALESCE(SUM(less30), 0) AS less30,
            COALESCE(SUM(less40), 0) AS less40,
            COALESCE(SUM(less45), 0) AS less45,
            COALESCE(SUM(less50), 0) AS less50,
            COALESCE(SUM(less60), 0) AS less60,
            COALESCE(SUM(less90), 0) AS less90,
            COALESCE(SUM(more90), 0) AS more90,
            MAX(docdt) AS max_docdt
        FROM arst_new
    ');
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC);

    if ($summaryRow) {
        foreach ($summary as $key => $default) {
            $summary[$key] = (float) $summaryRow[$key];
        }
        $asOfDate = (string) ($summaryRow['max_docdt'] ?? '');
    }

}

$invoicedTotal    = $summary['invamt'];
$receivedTotal    = $summary['recvamt'];
$outstandingTotal = $summary['amtout'];
$above90Total     = $summary['more90'];

$above90Where = $cuno !== ''
    ? ' WHERE TRIM(cuno) = TRIM(:cuno)'
    : ($requiresDealerSelection ? ' WHERE 1 = 0' : '');
$above90Stmt = $dpconn->prepare(
    'SELECT COUNT(*) FROM arst_new' . $above90Where
    . ($above90Where !== '' ? ' AND ' : ' WHERE ')
    . 'COALESCE(more90, 0) > 0'
);
if ($cuno !== '') {
    $above90Stmt->bindValue(':cuno', $cuno);
}
$above90Stmt->execute();
$above90Count = (int)$above90Stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - AR Statement</title>

    <?php include('header_css.php'); ?>

    <link href="css/order_acknowledge_style.css" rel="stylesheet" />
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/ar_statement.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet" />
    <?php if ($canFilterDealers): ?>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
        <link href="css/select2_change.css" rel="stylesheet" />
    <?php endif; ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <?php if ($canFilterDealers): ?>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php endif; ?>

</head>

<body>


    <div class="main-wrapper" id="mainWrapper">

        <?php include('sidebar.php'); ?>
        <div class="content">

            <!-- STATS -->

            <div class="stats-grid ar-grid">

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Outstanding Balance
                            </div>

                            <div class="card-value">
                                &#8377;<?= number_format(round($outstandingTotal)) ?>
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-credit-card"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Total Invoiced
                            </div>

                            <div class="card-value">
                                &#8377;<?= number_format(round($invoicedTotal)) ?>
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-check2-circle"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Total Received
                            </div>

                            <div class="card-value">
                                &#8377;<?= number_format(round($receivedTotal)) ?>
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-exclamation-triangle"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Above 90 Days
                            </div>

                            <div class="card-value">
                                &#8377;<?= number_format(round($above90Total)) ?>
                            </div>

                            <div class="card-sub red-text" style="color:#dc2626;">
                                <?= $above90Count ?> overdue invoice<?= $above90Count === 1 ? '' : 's' ?>
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-clock-history"></i>

                        </div>

                    </div>

                </div>

            </div>

            <!-- LEDGER -->

            <div class="booking-card">

                <!-- HEADER -->

                <div class="booking-header">

                    <div class="booking-title">
                        Account Ledger
                        <?php if ($selectedDealer !== null): ?>
                            <div class="booking-subtitle">
                                <?= htmlspecialchars((string) $selectedDealer['text']) ?>
                            </div>
                        <?php elseif ($canFilterDealers): ?>
                            <div class="booking-subtitle">
                                Select a dealer to view the AR statement
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="booking-actions">

                        <?php if ($canFilterDealers): ?>
                            <select class="filter-select ar-dealer-filter" id="arDealerFilter"
                                data-placeholder="Search dealer"
                                aria-label="Dealer Name">
                                <option value=""></option>
                                <?php foreach ($dealerFilterOptions as $dealerOption): ?>
                                    <option value="<?= htmlspecialchars((string) $dealerOption['id']) ?>"
                                        <?= $cuno === $dealerOption['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string) $dealerOption['text']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <select class="filter-select" id="ledgerStatusFilter">
                            <option value="">All Status</option>
                            <option value="outstanding">Outstanding</option>
                            <option value="settled">Settled</option>
                        </select>

                        <button class="download-btn" id="downloadStatementBtn" type="button">
                            <i class="bi bi-download"></i>
                            Download Statement
                        </button>

                    </div>

                </div>

                <!-- TABLE -->

                <div class="table-responsive">

                    <table class="table table-hover booking-table w-100" id="ledgerTable">

                        <thead>
                            <tr>
                                <th>DPST</th>
                                <th>Document Date</th>
                                <th>Invoice No</th>
                                <th>Currency</th>
                                <th>Invoice Amt</th>
                                <th>Received Amt</th>
                                <th>Outstanding Amt</th>
                                <th>Less than<br>30 days</th>
                                <th>31-40<br>days</th>
                                <th>41-45<br>days</th>
                                <th>46-50<br>days</th>
                                <th>51-60<br>days</th>
                                <th>61-90<br>days</th>
                                <th>Above 90<br>days</th>
                                <th>Due Date</th>
                            </tr>
                        </thead>

                        <tbody></tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td>Total Amount</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="text-end">&#8377;<?= number_format(round($summary['invamt'])) ?></td>
                                <td class="text-end">&#8377;<?= number_format(round($summary['recvamt'])) ?></td>
                                <td class="text-end">&#8377;<?= number_format(round($summary['amtout'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less30'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less40'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less45'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less50'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less60'])) ?></td>
                                <td class="text-end">&#8377;<?= number_format(round($summary['less90'])) ?></td>
                                <td class="text-end">&#8377;<?= number_format(round($summary['more90'])) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>

                    </table>

                </div>

            </div>

        </div>

        <script src="js/ar_statement.js?v=<?= (int) @filemtime(__DIR__ . '/js/ar_statement.js') ?>"></script>
    </div>
</body>

</html>