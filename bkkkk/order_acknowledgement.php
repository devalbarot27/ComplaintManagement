<?php
session_start();
// Check assigned permission start
include('pdo_obconn.php');
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

admin_refresh_session_role($obconn);

$oaModule = 'order-acknowledgement';
$canListOrderAck = rbac_user_can($obconn, $oaModule, 'list');
$canExportOrderAck = rbac_user_can($obconn, $oaModule, 'export-excel');
$canViewOrderAck = rbac_user_can($obconn, $oaModule, 'view');

if (!$canListOrderAck) {
    header('Location: access_denied.php');
    exit;
}
//end
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - Order Acknowledgement</title>

    <?php include('header_css.php'); ?>

    <link href="css/order_acknowledge_style.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <style>

    </style>
</head>

<body>

    <div class="main-wrapper" id="mainWrapper">

        <!-- SIDEBAR -->
        <?php include('sidebar.php'); ?>
        <!-- CONTENT -->
        <div class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm" style="border:1px solid #dbe2ea !important;">
                        <div class="card-body">
                            <div class="page-header mb-3">
                                <div class="header-flex">
                                    <!-- <button id="btnExcel"
                                        class="add-item-btn btn-sm<?php echo $canExportOrderAck ? '' : ' d-none'; ?>"
                                        onclick="window.location.href='exportOrders.php'">
                                        <i class="fa fa-file-excel"></i>
                                        Export Excel
                                    </button> -->

                                </div>
                            </div>
                            <!-- TABLE -->
                            <div class="table-responsive">
                                <table id="orderTable" class="table table-hover align-middle w-100">
                                    <thead>
                                        <tr>
                                            <th width="15%">Ref No</th>
                                            <th width="12%">PO Number</th>
                                            <th width="12%">AO Number</th>
                                            <th width="12%">AO Date</th>
                                            <th width="5%">Action</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>
<script>
    const canViewOrderAck = <?php echo $canViewOrderAck ? 'true' : 'false'; ?>;

    $(document).ready(function() {

        $('#orderTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            autoWidth: false,
            pageLength: 10,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                data: {
                    action: 'getOrderAcknowledgeList'
                }
            },

            columns: [{
                    data: 'ref_no'
                },
                {
                    data: 'po_number'
                },
                {
                    data: 'ao_number'
                },
                {
                    data: 'ao_date'
                },
                {
                    data: 'lines',
                    orderable: false,
                    searchable: false
                },
            ],
            drawCallback: function() {
                if (!canViewOrderAck) {
                    $('#orderTable tbody a[href*="order_data.php"]').remove();
                }
            }
        });

    });
</script>