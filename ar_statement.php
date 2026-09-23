<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'api/ar_statement_helpers.php';

$canViewArStatement = rbac_can_access_menu($obconn, 'ar_statement.php');
 if (!$canViewArStatement) {
    header('Location: access_denied.php');
    exit;
 }  

$active_menu = 'ar_statement';

$canFilterDealers = ar_statement_user_can_filter_dealers($obconn);
$canViewAllDealers = ar_statement_user_can_view_all_dealers();
$cuno = ar_statement_resolve_cuno($obconn);
$selectedDealer = $cuno !== '' ? ar_statement_dealer_get($dpconn, $obconn, $cuno) : null;
$assignedDealerOptions = [];
if ($canFilterDealers && !$canViewAllDealers) {
    $assignedDealerOptions = ar_statement_dealers_for_codes(
        $dpconn,
        $obconn,
        ar_statement_assigned_dealer_codes($obconn)
    );
    if ($assignedDealerOptions === []) {
        $assignedDealerOptions = ar_statement_search_all_dealers($dpconn, $obconn, '', 50);
    }
}

$summary = [
    'invamt' => 0.0, 'recvamt' => 0.0, 'amtout' => 0.0,
    'less30' => 0.0, 'less40' => 0.0, 'less45' => 0.0,
    'less50' => 0.0, 'less60' => 0.0, 'less90' => 0.0, 'more90' => 0.0,
];
$ledgerRows = [];
$asOfDate = '';

if ($cuno !== '') {
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

    $rowsStmt = $dpconn->prepare('
        SELECT dpst, docdt, invpre, invno, currency, invamt, recvamt, amtout,
               less30, less40, less45, less50, less60, less90, more90, duedate
        FROM arst_new
        WHERE TRIM(cuno) = TRIM(:cuno)
        ORDER BY docdt DESC
    ');
    $rowsStmt->bindValue(':cuno', $cuno);
    $rowsStmt->execute();
    $ledgerRows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$invoicedTotal    = $summary['invamt'];
$receivedTotal    = $summary['recvamt'];
$outstandingTotal = $summary['amtout'];
$above90Total     = $summary['more90'];

$above90Count = 0;
foreach ($ledgerRows as $row) {
    if ((float) $row['more90'] > 0) {
        $above90Count++;
    }
}
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

                        <?php if ($canFilterDealers):
                            $dealerFilterOptions = $canViewAllDealers
                                ? ($selectedDealer !== null ? [[
                                    'id' => $selectedDealer['code'],
                                    'text' => $selectedDealer['text'],
                                ]] : [])
                                : $assignedDealerOptions;
                            $dealerFilterOptionsJson = htmlspecialchars(
                                json_encode(array_values($dealerFilterOptions), JSON_UNESCAPED_UNICODE),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                        ?>
                        <select class="filter-select ar-dealer-filter" id="arDealerFilter"
                            data-placeholder="Dealer Name"
                            data-ajax="1"
                            data-options="<?= $dealerFilterOptionsJson ?>"
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

                        <tbody>
<?php foreach ($ledgerRows as $row):
    $rowOutstanding = (float) $row['amtout'];
    $docDateRaw = trim((string) ($row['docdt'] ?? ''));
    $dueDateRaw = trim((string) ($row['duedate'] ?? ''));
    $docDateTs = $docDateRaw !== '' ? strtotime($docDateRaw) : false;
    $dueDateTs = $dueDateRaw !== '' ? strtotime($dueDateRaw) : false;
?>
                            <tr data-status="<?= $rowOutstanding > 0 ? 'outstanding' : 'settled' ?>">
                                <td class="fw-semibold"><?= htmlspecialchars((string) $row['dpst']) ?></td>
                                <td data-order="<?= htmlspecialchars($docDateTs ? date('Y-m-d', $docDateTs) : '') ?>">
                                    <?= htmlspecialchars($docDateTs ? date('d M Y', $docDateTs) : '') ?>
                                </td>
                                <td><?= htmlspecialchars(trim((string) $row['invpre']) . '-' . trim((string) $row['invno'])) ?></td>
                                <td><?= htmlspecialchars(trim((string) $row['currency']) !== '' ? $row['currency'] : 'INR') ?></td>
                                <td class="debit-text text-end" data-order="<?= (int) round((float) $row['invamt']) ?>">
                                    &#8377;<?= number_format(round((float) $row['invamt'])) ?>
                                </td>
                                <td class="credit-text text-end" data-order="<?= (int) round((float) $row['recvamt']) ?>">
                                    &#8377;<?= number_format(round((float) $row['recvamt'])) ?>
                                </td>
                                <td class="fw-semibold text-end" data-order="<?= (int) round($rowOutstanding) ?>">
                                    &#8377;<?= number_format(round($rowOutstanding)) ?>
                                </td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less30']) ?>"><?= number_format(round((float) $row['less30'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less40']) ?>"><?= number_format(round((float) $row['less40'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less45']) ?>"><?= number_format(round((float) $row['less45'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less50']) ?>"><?= number_format(round((float) $row['less50'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less60']) ?>"><?= number_format(round((float) $row['less60'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['less90']) ?>"><?= number_format(round((float) $row['less90'])) ?></td>
                                <td class="text-end" data-order="<?= (int) round((float) $row['more90']) ?>">&#8377;<?= number_format(round((float) $row['more90'])) ?></td>
                                <td class="<?= $rowOutstanding > 0 ? 'debit-text' : '' ?>" data-order="<?= htmlspecialchars($dueDateTs ? date('Y-m-d', $dueDateTs) : '') ?>">
                                    <?= htmlspecialchars($dueDateTs ? date('d M Y', $dueDateTs) : ' ') ?>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>

<?php if (!empty($ledgerRows)): ?>
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
<?php endif; ?>

                    </table>

                </div>

            </div>

        </div>

        <script src="js/ar_statement.js?v=<?= (int) @filemtime(__DIR__ . '/js/ar_statement.js') ?>"></script>
    </div>
</body>

</html>