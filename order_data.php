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
        body {
            background: #f5f7fa;
            font-size: 14px;
        }

        .main-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, .12);
            overflow: hidden;
            margin-top: 20px;
            width: max-content;
        }

        .page-header {
            background: linear-gradient(135deg, #000, #000);
            color: #fff;
            padding: 14px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .info-table td {
            padding: 10px;
            vertical-align: top;
        }

        .info-table td:first-child {
            width: 220px;
            font-weight: 600;
            background: #f8f9fa;
        }

        .section-title {
            background: #000;
            color: #fff;
            padding: 10px 15px;
            font-weight: 600;
        }

        .table-items thead th {
            background: #000;
            color: #fff;
            text-align: center;
            vertical-align: middle;
            font-size: 13px;
        }

        .table-items tbody td {
            vertical-align: middle;
            font-size: 13px;
        }

        .address {
            line-height: 1.6;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="main-wrapper" id="mainWrapper">

        <!-- SIDEBAR -->
        <?php include('sidebar.php'); ?>

        <!-- CONTENT -->
        <div class="content">
            <div class="order-form-card" id="orderFormCard">
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
                        ':delivery_code' => $delcode]);
                    if ($addrRow = $addrStmt->fetch(PDO::FETCH_ASSOC)) {
                        $delAdd1 = $addrRow['address1'];
                        $delAdd2 = $addrRow['address2'];
                        $delAdd3 = $addrRow['address3'];
                        $delAdd4 = $addrRow['address4'];
                        $delAdd5 = $addrRow['address5'];
                        $delAdd6 = $addrRow['address6'];
                    }
                }
                ?>


                <div class="page-header">ORDER ACKNOWLEDGEMENT VIEW - ELGI EQUIPMENTS LTD</div>
                <div class="p-3">
                    <table class="table table-bordered info-table">
                        <tr>
                            <td>Order Number</td>
                            <td><?= htmlspecialchars($ordno) ?></td>
                        </tr>
                        <tr>
                            <td>Order Date</td>
                            <td><?= htmlspecialchars($orddate) ?></td>
                        </tr>
                        <tr>
                            <td>Customer</td>
                            <td><?= htmlspecialchars($cuname) ?> [<?= htmlspecialchars($cuno) ?>]</td>
                        </tr>
                        <tr>
                            <td>PO Number</td>
                            <td><?= htmlspecialchars($purno) ?></td>
                        </tr>
                        <tr>
                            <td>Invoice Address</td>
                            <td class="address">
                                <?= htmlspecialchars($cuname) ?><br>
                                <?= htmlspecialchars($add1) ?><br>
                                <?= htmlspecialchars($add2) ?><br>
                                <?= htmlspecialchars($add3) ?><br>
                                <?= htmlspecialchars($add4) ?>
                            </td>
                        </tr>

                        <tr>
                            <td>Payment Terms</td>
                            <td><?= htmlspecialchars($payterms) ?></td>
                        </tr>

                        <tr>
                            <td>Delivery Terms</td>
                            <td><?= htmlspecialchars($delterms) ?></td>
                        </tr>

                        <tr>
                            <td>Currency</td>
                            <td><?= htmlspecialchars($currency) ?></td>
                        </tr>

                        <tr>
                            <td>Delivery Address</td>
                            <td class="address">
                                <?= htmlspecialchars($delAdd1) ?><br>
                                <?= htmlspecialchars($delAdd2) ?><br>
                                <?= htmlspecialchars($delAdd3) ?><br>
                                <?= htmlspecialchars($delAdd4) ?><br>
                                <?= htmlspecialchars($delAdd5) ?><br>
                                <?= htmlspecialchars($delAdd6) ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="section-title">
                    Order Line Details
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
                                <th rowspan="2">Discount (%)</th>
                                <th colspan="2">Duty & Taxes / Unit</th>
                                <th colspan="2">Planned Delivery Date</th>
                            </tr>

                            <tr>
                                <th>Excise Duty</th>
                                <th>Sales Tax</th>
                                <th>Earliest</th>
                                <th>Latest</th>
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
                                    <td class="text-center"><?= $row['posno'] ?></td>
                                    <td><?= htmlspecialchars($row['item_desc']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['uom']) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['qty'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['price'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['discount'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['excisedutyrs'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$row['salestax'], 2) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['earlierdate']) ?></td>
                                    <td class="text-center"><?= htmlspecialchars($row['latestdate']) ?></td>
                                </tr>

                            <?php } ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>