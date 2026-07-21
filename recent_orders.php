<?php
session_start();
include('pdo_obconn.php');
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

admin_refresh_session_role($obconn);

$roModule = 'recent-orders';
$canListRecentOrders = rbac_user_can($obconn, $roModule, 'list');
$canExportRecentOrders = rbac_user_can($obconn, $roModule, 'export-excel');
$canViewRecentOrders = rbac_user_can($obconn, $roModule, 'view');
$showAddedByColumn = is_system_admin() || is_management_user() || is_ccs_admin_user();

if (!$canListRecentOrders) {
    header('Location: access_denied.php');
    exit;
}

$refNo = trim((string) ($_GET['order_no'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - Recent Orders</title>

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
                                        class="add-item-btn btn-sm<?php echo $canExportRecentOrders ? '' : ' d-none'; ?>"
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
                                            <th width="12%">AO Number</th>
                                            <th width="12%">Category</th>
                                            <th>Delivery Term</th>
                                            <th width="12%">PO Number</th>
                                            <th width="12%">Payment Term</th>
                                            <th width="15%">Transporter</th>
                                            <?php if ($showAddedByColumn) { ?>
                                            <th width="12%">Added By</th>
                                            <?php } ?>
                                            <th width="10%">Order Status</th>
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
    <!-- MODAL -->
    <div class="modal fade" id="lineModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">

                    <h5 class="page-subtitle mb-0" id="lineModalLabel">
                        Recent Order List
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary add-item-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>
<script>
    const canViewRecentOrders = <?php echo $canViewRecentOrders ? 'true' : 'false'; ?>;
    const recentOrderRefNo = <?php echo json_encode($refNo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

    $(document).ready(function() {

        var table = $('#orderTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            autoWidth: false,
            pageLength: 10,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                data: {
                    action: 'getRecentOrders'
                }
            },

            columns: [{
                    data: 'ref_no'
                },
                {
                    data: 'order_no'
                },
                {
                    data: 'category'
                },
                {
                    data: 'delivery_term'
                },
                {
                    data: 'po_number'
                },
                {
                    data: 'payment_term'
                },
                {
                    data: 'transporter'
                },
                <?php if ($showAddedByColumn) { ?>
                {
                    data: 'added_by'
                },
                <?php } ?>
                {
                    data: 'order_status'
                },
                {
                    data: 'lines',
                    orderable: false
                },
            ],
            drawCallback: function() {
                if (!canViewRecentOrders) {
                    $('#orderTable tbody button[onclick*="openLineItems"]').remove();
                    $('#orderTable tbody a[href*="recent_order_details.php"]').remove();
                }
            }
        });

        if (recentOrderRefNo !== '') {
            table.search(recentOrderRefNo).draw();
        }
    });

    function openLineItems(orderNo) {
        if (!canViewRecentOrders) {
            return;
        }

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                orderNo: orderNo,
                action: "getRecentOrderLine"
            },
            dataType: "HTML",
            success: function(res) {
                $("#lineModal").modal('toggle');
                $(".modal-body").html(res);
            }
        })
    }
</script>