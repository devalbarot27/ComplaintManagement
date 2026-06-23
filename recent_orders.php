<?php
session_start();
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
                                    <button id="btnExcel"
                                        class="add-item-btn btn-sm"
                                        onclick="window.location.href='exportOrders.php'">
                                        <i class="fa fa-file-excel"></i>
                                        Export Excel
                                    </button>

                                </div>
                            </div>
                            <!-- TABLE -->
                            <div class="table-responsive">
                                <table id="orderTable" class="table table-hover align-middle w-100">
                                    <thead>
                                        <tr>
                                            <th>Order No</th>
                                            <th>Order Category</th>
                                            <th>Address</th>
                                            <th>Delivery Term</th>
                                            <th>Payment Term</th>
                                            <th>Transporter</th>
                                            <th>Lines</th>
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
                    action: 'getRecentOrders'
                }
            },

            columns: [
                {
                    data: 'order_no'
                },
                {
                    data: 'order_category'
                },
                {
                    data: 'dealer_address'
                },
                {
                    data: 'delivery_term'
                },
                {
                    data: 'payment_term'
                },
                {
                    data: 'transporter'
                },
                {
                    data: 'lines'
                },
            ]
        });

    });

    function openLineItems(orderNo) {

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                orderNo: orderNo,
                action: "getAcknowledgeLine"
            },
            dataType: "HTML",
            success: function(res) {
                $("#lineModal").modal('toggle');
                $(".modal-body").html(res);
            }
        })
    }
</script>