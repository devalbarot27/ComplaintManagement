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

$poModule = 'pending-order';
$canListPendingOrder = rbac_user_can($obconn, $poModule, 'list');
$canExportPendingOrder = rbac_user_can($obconn, $poModule, 'export-excel');
$canViewPendingOrder = rbac_user_can($obconn, $poModule, 'view');

if (!$canListPendingOrder) {
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
    <title>Dealer - Pending Orders</title>
    <?php include('header_css.php'); ?>
    <link href="css/order_acknowledge_style.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <!-- SIDEBAR -->
        <?php include('sidebar.php'); ?>
        <!-- CONTENT -->
        <div class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="page-header mb-3">
                                <div class="header-flex">
                                    <!-- <button id="btnExcel"
                                        class="add-item-btn btn-sm<?php echo $canExportPendingOrder ? '' : ' d-none'; ?>"
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
                                            <th width="12%">Delivery Date</th>
                                            <th width="10%">Status</th>
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
    const canViewPendingOrder = <?php echo $canViewPendingOrder ? 'true' : 'false'; ?>;

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
                    action: 'getPendingOrderList'
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
                    data: 'delivery_date'
                },
                {
                    data: 'order_status',
                    orderable: false
                },
                {
                    data: 'action',
                    orderable: false,
                    searchable: false
                },
            ],
            drawCallback: function() {
                if (!canViewPendingOrder) {
                    $('#orderTable tbody a[href*="order_data.php"]').remove();
                }
            }
        });

    });
</script>