<?php
session_start();
include('pdo_obconn.php');
$username = $_SESSION['usr_name'];

$ordno = pg_escape_string($_GET['order']);
$cuno = pg_escape_string($_GET['cuno']);

$stmt = $dpconn->prepare("SELECT DISTINCT m.cuno, m.del_add, m.ord_date, m.currency, m.delterms, m.payterms, m.purno, c.cuname, c.st1, c.st2, c.city, c.state FROM maintdealer m INNER JOIN customer_master c ON m.cuno = c.cuno WHERE m.ordno = :ordno AND m.cuno = :cuno");

$stmt->bindParam(':ordno', $ordno, PDO::PARAM_STR);
$stmt->bindParam(':cuno', $cuno, PDO::PARAM_STR);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $cuno      = $row['cuno'];
    $delcode   = $row['del_add'];
    $orddate   = $row['ord_date'];
    $cuname    = $row['cuname'];
    $add1      = $row['st1'] ?? '';
    $add2      = $row['st2'] ?? '';
    $add3      = $row['city'] ?? '';
    $add4      = $row['state'] ?? '';
    $add5      = '';
    $currency  = $row['currency'];
    $payterms  = $row['payterms'];
    $delterms  = $row['delterms'];
    $purno     = $row['purno'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Order Acknowledgment Data</title>
    <?php include('header_css.php'); ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <style>
        .oad-page {
            --oad-ink: #0f172a;
            --oad-muted: #64748b;
            --oad-line: #e2e8f0;
            --oad-soft: #f8fafc;
            --oad-soft-2: #f1f5f9;
            --oad-brand: #000;
            --oad-accent: #F44611;
            --oad-accent-soft: #fff4f0;
            --oad-radius: 16px;
            --oad-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
            font-size: 14px;
            color: var(--oad-ink);
        }

        .oad-page .main-wrapper,
        .oad-page .content {
            background:
                radial-gradient(1200px 420px at 8% -10%, rgba(244, 70, 17, 0.08), transparent 55%),
                radial-gradient(900px 360px at 100% 0%, rgba(15, 23, 42, 0.05), transparent 50%),
                #f4f6f9;
        }

        .oad-page .content {
            padding: 24px;
        }

        .oad-page .order-form-card.oad-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0;
            background: #fff;
            border: 1px solid var(--oad-line);
            border-radius: var(--oad-radius);
            box-shadow: var(--oad-shadow);
            overflow: hidden;
            animation: oadRise 0.35s ease;
        }

        @keyframes oadRise {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .oad-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            flex-wrap: wrap;
            background:
                linear-gradient(135deg, #111 0%, #000 58%, #1a1a1a 100%);
            color: #fff;
            padding: 22px 26px;
            position: relative;
        }

        .oad-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--oad-accent), #ff8a65 55%, var(--oad-accent));
        }

        .oad-hero__left {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .oad-hero__icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(244, 70, 17, 0.18);
            border: 1px solid rgba(244, 70, 17, 0.35);
            color: #ffb199;
            font-size: 20px;
            flex-shrink: 0;
        }

        .oad-hero__eyebrow {
            display: block;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255, 255, 255, 0.62);
            margin-bottom: 4px;
        }

        .oad-hero__title {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.01em;
            line-height: 1.3;
        }

        .oad-hero__badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: flex-end;
        }

        .oad-hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            backdrop-filter: blur(4px);
        }

        .oad-hero__badge span {
            opacity: 0.7;
            font-weight: 500;
        }

        .oad-hero__badge--accent {
            background: rgba(244, 70, 17, 0.18);
            border-color: rgba(244, 70, 17, 0.4);
        }

        .oad-spotlight {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            padding: 18px 22px;
            background: linear-gradient(180deg, #fffaf8 0%, #fff 100%);
            border-bottom: 1px solid var(--oad-line);
        }

        .oad-spotlight__item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            background: #fff;
            border: 1px solid var(--oad-line);
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
            min-height: 76px;
        }

        .oad-spotlight__icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--oad-accent-soft);
            color: var(--oad-accent);
            font-size: 15px;
            flex-shrink: 0;
        }

        .oad-spotlight__label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--oad-muted);
            margin-bottom: 4px;
        }

        .oad-spotlight__value {
            font-size: 14px;
            font-weight: 700;
            color: var(--oad-ink);
            line-height: 1.4;
            word-break: break-word;
        }

        .oad-body {
            padding: 22px 22px 26px;
        }

        .oad-section {
            margin-bottom: 24px;
        }

        .oad-section:last-child {
            margin-bottom: 0;
        }

        .oad-section__head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 14px;
        }

        .oad-section__head-main {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .oad-section__head h2 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--oad-ink);
            letter-spacing: -0.01em;
        }

        .oad-section__head-main::before {
            content: "";
            width: 4px;
            height: 16px;
            border-radius: 4px;
            background: var(--oad-accent);
            flex-shrink: 0;
        }

        .oad-section__hint {
            font-size: 12px;
            color: var(--oad-muted);
            font-weight: 500;
        }

        .oad-meta-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .oad-meta-card {
            background: var(--oad-soft);
            border: 1px solid var(--oad-line);
            border-radius: 12px;
            padding: 15px 16px;
            min-height: 78px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, transform 0.15s ease;
        }

        .oad-meta-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            transform: translateY(-1px);
        }

        .oad-meta-label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: var(--oad-muted);
            margin-bottom: 7px;
        }

        .oad-meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--oad-ink);
            line-height: 1.5;
            word-break: break-word;
        }

        .oad-chip {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: 6px 10px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #dbe3ee;
            font-size: 13px;
            font-weight: 600;
            color: var(--oad-ink);
            line-height: 1.3;
            word-break: break-word;
        }

        .oad-address-pair {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .oad-address-card {
            background: #fff;
            border: 1px solid var(--oad-line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
            min-height: 100%;
        }

        .oad-address-card__head {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: var(--oad-soft-2);
            border-bottom: 1px solid var(--oad-line);
        }

        .oad-address-card__icon {
            width: 32px;
            height: 32px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 1px solid var(--oad-line);
            color: var(--oad-accent);
            font-size: 14px;
            flex-shrink: 0;
        }

        .oad-address-card__title {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: var(--oad-muted);
        }

        .oad-address-card__body {
            padding: 16px;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.7;
            color: #334155;
        }

        .oad-lines-wrap {
            border: 1px solid var(--oad-line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        }

        .oad-lines-titlebar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 16px;
            background: linear-gradient(135deg, #111, #000);
            color: #fff;
        }

        .oad-lines-titlebar h2 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .oad-lines-titlebar h2 i {
            color: #ffb199;
        }

        .oad-page .table-items {
            margin: 0;
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .oad-page .table-items thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #111;
            color: #fff;
            text-align: center;
            vertical-align: middle;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            padding: 13px 14px;
            border: none;
            border-bottom: 2px solid var(--oad-accent);
            white-space: nowrap;
        }

        .oad-page .table-items thead th:nth-child(2) {
            text-align: left;
        }

        .oad-page .table-items tbody td {
            vertical-align: middle;
            font-size: 13px;
            padding: 13px 14px;
            border-color: var(--oad-line);
            color: #334155;
            background: #fff;
        }

        .oad-page .table-items.table-bordered > :not(caption) > * > * {
            border-width: 0 0 1px 0;
        }

        .oad-page .table-items tbody tr:last-child td {
            border-bottom: none;
        }

        .oad-page .table-items tbody tr:nth-child(even) td {
            background: #fafbfc;
        }

        .oad-page .table-items tbody tr:hover td {
            background: var(--oad-accent-soft);
        }

        .oad-page .table-items td.text-center {
            text-align: center;
            font-weight: 600;
            color: var(--oad-ink);
        }

        .oad-page .table-items td.text-end {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 700;
            color: var(--oad-ink);
            white-space: nowrap;
        }

        .oad-pos {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 4px 8px;
            border-radius: 999px;
            background: var(--oad-soft-2);
            border: 1px solid #dbe3ee;
            font-size: 12px;
            font-weight: 700;
            color: var(--oad-ink);
        }

        .oad-item-desc {
            font-weight: 500;
            color: #1e293b;
            line-height: 1.45;
        }

        .oad-uom {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            padding: 3px 8px;
            border-radius: 8px;
            background: #fff;
            border: 1px solid #dbe3ee;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
        }

        .oad-page .table-responsive {
            margin: 0;
            max-height: min(56vh, 520px);
            overflow: auto;
        }

        @media (max-width: 992px) {
            .oad-meta-grid,
            .oad-spotlight {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 768px) {
            .oad-page .content {
                padding: 12px;
            }

            .oad-hero {
                padding: 16px;
            }

            .oad-hero__title {
                font-size: 15px;
            }

            .oad-hero__badges {
                width: 100%;
                justify-content: flex-start;
            }

            .oad-spotlight,
            .oad-meta-grid,
            .oad-address-pair {
                grid-template-columns: 1fr;
            }

            .oad-body {
                padding: 16px;
            }

            .oad-page .table-items thead th,
            .oad-page .table-items tbody td {
                padding: 10px 12px;
                font-size: 12px;
            }

            .oad-page .table-responsive {
                max-height: none;
            }
        }
    </style>
</head>

<body class="oad-page">

    <div class="main-wrapper" id="mainWrapper">

        <!-- SIDEBAR -->
        <?php include('sidebar.php'); ?>

        <!-- CONTENT -->
        <div class="content">
            <div class="order-form-card oad-shell" id="orderFormCard">
                <?php
                $delAdd1 = "";
                $delAdd2 = "";
                $delAdd3 = "";
                $delAdd4 = "";
                $delAdd5 = "";
                $delAdd6 = "";

                if (strlen(trim($delcode)) == 3) {
                    $addrStmt = $dpconn->prepare("SELECT address1, address2, address3, address4, address5, address6 FROM cust_delivery_address WHERE cuno = :cuno AND delivery_code = :delivery_code");
                    $addrStmt->execute([
                        ':cuno' => $cuno,
                        ':delivery_code' => $delcode
                    ]);
                    if ($addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC)) {
                        $delAdd1 = $addrRow['address1'];
                        $delAdd2 = $addrRow['address2'];
                        $delAdd3 = $addrRow['address3'];
                        $delAdd4 = $addrRow['address4'];
                        $delAdd5 = $addrRow['address5'];
                        $delAdd6 = $addrRow['address6'];
                    }
                } else {
                    $delAdd1      = htmlspecialchars($cuname).' [ '. htmlspecialchars($cuno) .']';
                    $delAdd2      = $row['st1'] ?? '';
                    $delAdd3      = $row['st2'] ?? '';
                    $delAdd4      = $row['city'] ?? '';
                    $delAdd5      = $row['state'] ?? '';  
                }
                ?>

                <div class="oad-hero">
                    <div class="oad-hero__left">
                        <div class="oad-hero__icon" aria-hidden="true">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <div>
                            <span class="oad-hero__eyebrow">ELGI Equipments Ltd</span>
                            <h1 class="oad-hero__title">Pending Order View</h1>
                        </div>
                    </div>
                    <div class="oad-hero__badges">
                        <div class="oad-hero__badge oad-hero__badge--accent">
                            <span>Order No.</span>
                            <?= htmlspecialchars($ordno) ?>
                        </div>
                        <div class="oad-hero__badge">
                            <span>Date</span>
                            <?= htmlspecialchars($orddate) ?>
                        </div>
                    </div>
                </div>

                <div class="oad-spotlight">
                    <div class="oad-spotlight__item">
                        <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-hash"></i></div>
                        <div>
                            <span class="oad-spotlight__label">Order Number</span>
                            <div class="oad-spotlight__value"><?= htmlspecialchars($ordno) ?></div>
                        </div>
                    </div>
                    <div class="oad-spotlight__item">
                        <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-person"></i></div>
                        <div>
                            <span class="oad-spotlight__label">Customer</span>
                            <div class="oad-spotlight__value"><?= htmlspecialchars($cuname) ?> [<?= htmlspecialchars($cuno) ?>]</div>
                        </div>
                    </div>
                    <div class="oad-spotlight__item">
                        <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-receipt"></i></div>
                        <div>
                            <span class="oad-spotlight__label">PO Number</span>
                            <div class="oad-spotlight__value"><?= htmlspecialchars($purno) ?></div>
                        </div>
                    </div>
                </div>

                <div class="oad-body">
                    <section class="oad-section">
                        <div class="oad-section__head">
                            <div class="oad-section__head-main">
                                <h2>Order Summary</h2>
                            </div>
                            <span class="oad-section__hint">Key order details</span>
                        </div>

                        <div class="oad-meta-grid">
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">Order Number</span>
                                <div class="oad-meta-value"><?= htmlspecialchars($ordno) ?></div>
                            </div>
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">Order Date</span>
                                <div class="oad-meta-value"><?= htmlspecialchars($orddate) ?></div>
                            </div>
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">Customer</span>
                                <div class="oad-meta-value"><?= htmlspecialchars($cuname) ?> [<?= htmlspecialchars($cuno) ?>]</div>
                            </div>
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">PO Number</span>
                                <div class="oad-meta-value"><?= htmlspecialchars($purno) ?></div>
                            </div>
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">Payment Terms</span>
                                <div class="oad-meta-value"><span class="oad-chip"><?= htmlspecialchars($payterms) ?></span></div>
                            </div>
                            <div class="oad-meta-card">
                                <span class="oad-meta-label">Delivery Terms</span>
                                <div class="oad-meta-value"><span class="oad-chip"><?= htmlspecialchars($delterms) ?></span></div>
                            </div>
                        </div>
                    </section>

                    <section class="oad-section">
                        <div class="oad-section__head">
                            <div class="oad-section__head-main">
                                <h2>Addresses</h2>
                            </div>
                            <span class="oad-section__hint">Invoice &amp; delivery locations</span>
                        </div>

                        <div class="oad-address-pair">
                            <div class="oad-address-card">
                                <div class="oad-address-card__head">
                                    <div class="oad-address-card__icon" aria-hidden="true"><i class="bi bi-building"></i></div>
                                    <h3 class="oad-address-card__title">Invoice Address</h3>
                                </div>
                                <div class="oad-address-card__body">
                                    <?= htmlspecialchars($cuname) ?><br>
                                    <?= htmlspecialchars($add1) ?><br>
                                    <?= htmlspecialchars($add2) ?><br>
                                    <?= htmlspecialchars($add3) ?><br>
                                    <?= htmlspecialchars($add4) ?>
                                </div>
                            </div>
                            <div class="oad-address-card">
                                <div class="oad-address-card__head">
                                    <div class="oad-address-card__icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></div>
                                    <h3 class="oad-address-card__title">Delivery Address</h3>
                                </div>
                                <div class="oad-address-card__body">
                                    <?= htmlspecialchars($delAdd1) ?><br>
                                    <?= htmlspecialchars($delAdd2) ?><br>
                                    <?= htmlspecialchars($delAdd3) ?><br>
                                    <?= htmlspecialchars($delAdd4) ?><br>
                                    <?= htmlspecialchars($delAdd5) ?><br>
                                    <?= htmlspecialchars($delAdd6) ?>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="oad-section">
                        
                        <div class="oad-lines-wrap">
                            <div class="oad-lines-titlebar">
                                <h2><i class="bi bi-list-ul" aria-hidden="true"></i> Order Line Details</h2>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered table-hover table-items mb-0">
                                    <thead>
                                        <tr>
                                            <th rowspan="2">Position No</th>
                                            <th rowspan="2">Item Description</th>
                                            <th rowspan="2">UOM</th>
                                            <th rowspan="2">Qty</th>
                                            <th rowspan="2">Price / Unit</th>
                                            <!-- <th colspan="2">Duty & Taxes / Unit</th> -->
                                        </tr>
                                       
                                    </thead>

                                    <tbody>
                                        <?php

                                        $itemStmt = $dpconn->prepare("SELECT DISTINCT  posno,item_desc,uom,qty,price,discount,excisedutyrs,salestax,earlierdate,latestdate FROM maintdealer WHERE ordno = :ordno AND cuno = :cuno ORDER BY posno");
                                        $itemStmt->execute([
                                            ':ordno' => $ordno,
                                            ':cuno' => $cuno
                                        ]);
                                        while ($row = $itemStmt->fetch(PDO::FETCH_ASSOC)) {
                                        ?>

                                            <tr>
                                                <td class="text-center"><span class="oad-pos"><?= $row['posno'] ?></span></td>
                                                <td><span class="oad-item-desc"><?= htmlspecialchars($row['item_desc']) ?></span></td>
                                                <td class="text-center"><span class="oad-uom"><?= htmlspecialchars($row['uom']) ?></span></td>
                                                <td class="text-end"><?= number_format((float)$row['qty'], 2) ?></td>
                                                <td class="text-end"><?= number_format((float)$row['price'], 2) ?></td>
                                                <!-- <td class="text-end"><?= number_format((float)$row['excisedutyrs'], 2) ?></td>
                                                <td class="text-end"><?= number_format((float)$row['salestax'], 2) ?></td> -->
                                            </tr>

                                        <?php } ?>

                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>