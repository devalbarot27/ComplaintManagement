<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Despatch Details</title>
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

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-items mb-0" id="LrTable">
                        <thead>
                            <tr>
                                <th>Product Group</th>
                                <th>AO Number</th>
                                <th>Invoice No</th>
                                <th>Despatch Date</th>
                                <th>Transporter</th>
                                <th>LR Number</th>
                                <th>LR Date</th>
                            </tr>
                        </thead>
                        <tbody>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>
<script>
    $(document).ready(function() {

        $('#LrTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            autoWidth: false,
            pageLength: 10,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                data: {
                    action: 'getLrDetails'
                }
            },

            columns: [
                {
                    data: 'dpst'
                },
                {
                    data: 'ordno'
                },
                {
                    data: 'invno'
                },
                {
                    data: 'invdt'
                },
                {
                    data: 'transporter'
                },
                {
                    data: 'lrno'
                },
                {
                    data: 'packing'
                },
                {
                    data: 'weight'
                },
            ]
        });

    });
</script>