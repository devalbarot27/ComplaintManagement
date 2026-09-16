<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';

$active_menu = 'ar_statement';

$cuno = trim((string) ($_SESSION['customer_number_vayu'] ?? ''));

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
        WHERE cuno = :cuno
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
        WHERE cuno = :cuno
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

    <link href="css/ar_statement.css" rel="stylesheet" />

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
                                ₹<?= number_format(round($outstandingTotal)) ?>
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
                                ₹<?= number_format(round($invoicedTotal)) ?>
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
                                ₹<?= number_format(round($receivedTotal)) ?>
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
                                ₹<?= number_format(round($above90Total)) ?>
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
                    </div>

                    <div class="booking-actions">

                        <!-- SEARCH -->

                        <div class="search-box">

                            <i class="bi bi-search"></i>

                            <input type="text" id="ledgerSearchInput"
                                placeholder="Search invoice no...">

                        </div>

                        <!-- FILTER -->

                        <select class="filter-select" id="ledgerStatusFilter">

                            <option value="">All Status</option>

                            <option value="outstanding">Outstanding</option>

                            <option value="settled">Settled</option>

                        </select>

                        <!-- DOWNLOAD -->

                        <button class="download-btn" id="downloadStatementBtn" type="button">

                            <i class="bi bi-download"></i>

                            Download Statement

                        </button>

                    </div>

                </div>

                <!-- TABLE -->

                <div class="table-responsive">

                    <table class="booking-table" id="ledgerTable">

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

<?php if (empty($ledgerRows)): ?>
                            <tr>
                                <td colspan="15" class="text-center text-muted">No AR records found.</td>
                            </tr>
<?php else: foreach ($ledgerRows as $row): $rowOutstanding = (float) $row['amtout']; ?>
                            <tr data-status="<?= $rowOutstanding > 0 ? 'outstanding' : 'settled' ?>">

                                <td class="fw-semibold">
                                    <?= htmlspecialchars((string) $row['dpst']) ?>
                                </td>

                                <td><?= htmlspecialchars(!empty($row['docdt']) ? date('d M Y', strtotime((string) $row['docdt'])) : '') ?></td>

                                <td><?= htmlspecialchars(trim((string) $row['invpre']) . '-' . trim((string) $row['invno'])) ?></td>

                                <td><?= htmlspecialchars(trim((string) $row['currency']) !== '' ? $row['currency'] : 'INR') ?></td>

                                <td class="debit-text text-end">
                                    ₹<?= number_format(round((float) $row['invamt'])) ?>
                                </td>

                                <td class="credit-text text-end">
                                    ₹<?= number_format(round((float) $row['recvamt'])) ?>
                                </td>

                                <td class="fw-semibold text-end">
                                    ₹<?= number_format(round($rowOutstanding)) ?>
                                </td>

                                <td class="text-end"><?= number_format(round((float) $row['less30'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['less40'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['less45'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['less50'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['less60'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['less90'])) ?></td>
                                <td class="text-end"><?= number_format(round((float) $row['more90'])) ?></td>

                                <td class="<?= $rowOutstanding > 0 ? 'debit-text' : '' ?>">
                                    <?= htmlspecialchars(!empty($row['duedate']) ? date('d M Y', strtotime((string) $row['duedate'])) : '—') ?>
                                </td>

                            </tr>
<?php endforeach; endif; ?>

                        </tbody>

<?php if (!empty($ledgerRows)): ?>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="4">Total Amount</td>
                                <td class="text-end">₹<?= number_format(round($summary['invamt'])) ?></td>
                                <td class="text-end">₹<?= number_format(round($summary['recvamt'])) ?></td>
                                <td class="text-end">₹<?= number_format(round($summary['amtout'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less30'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less40'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less45'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less50'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less60'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['less90'])) ?></td>
                                <td class="text-end"><?= number_format(round($summary['more90'])) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
<?php endif; ?>

                    </table>

                </div>

            </div>

        </div>

        <script>
            const ledgerTable = document.getElementById('ledgerTable');
            const searchInput = document.getElementById('ledgerSearchInput');
            const statusFilter = document.getElementById('ledgerStatusFilter');

            function applyLedgerFilters() {
                if (!ledgerTable) {
                    return;
                }
                const search = (searchInput?.value || '').trim().toLowerCase();
                const status = statusFilter?.value || '';
                ledgerTable.querySelectorAll('tbody tr[data-status]').forEach((tr) => {
                    const matchesSearch = search === '' || tr.textContent.toLowerCase().includes(search);
                    const matchesStatus = status === '' || tr.dataset.status === status;
                    tr.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
                });
            }

            searchInput?.addEventListener('input', applyLedgerFilters);
            statusFilter?.addEventListener('change', applyLedgerFilters);

            document.getElementById('downloadStatementBtn')?.addEventListener('click', () => {
                if (!ledgerTable) {
                    return;
                }
                const rows = [...ledgerTable.querySelectorAll('tr')].filter((tr) => tr.style.display !== 'none');
                const csv = rows.map((tr) =>
                    [...tr.querySelectorAll('th,td')]
                        .map((cell) => '"' + cell.textContent.trim().replace(/"/g, '""').replace(/\s+/g, ' ') + '"')
                        .join(',')
                ).join('\n');
                const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'AR_Statement.csv';
                link.click();
                URL.revokeObjectURL(link.href);
            });
        </script>
    </div>
</body>

</html>