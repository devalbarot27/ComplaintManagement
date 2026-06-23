<?php
session_start();
include('pdo_obconn.php');
$username = $_SESSION['usr_name'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Order Booking</title>
    <?php include('header_css.php'); ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/base/jquery-ui.css">
    <style>
        .select2-selection__rendered {
            font-size: 14px !important;
            color: #94a3b8;
        }

        .addressInp {
            display: none;
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
                <div class="order-form-grid">
                    <div class="form-group">
                        <label>Dpst</label>
                        <select class="form-control" id="dpst">
                            <?php
                            $getDpst = $obconn->prepare("SELECT dpst FROM tbl_vayu_dpst_master WHERE status=1");
                            $getDpst->execute();
                            if ($getDpst->rowCount() > 0) {
                                while ($rowDpst = $getDpst->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                    <option value="<?php echo $rowDpst['dpst'];?>"><?php echo $rowDpst['dpst'];?></option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Order Category</label>
                        <select class="form-control" id="orderCategory">
                            <option value="">Select</option>
                            <?php
                            $getCateList = $obconn->prepare("SELECT id,order_category FROM tbl_vayu_order_category WHERE status=1");
                            $getCateList->execute();
                            while ($getList = $getCateList->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <option value="<?php echo $getList['id']; ?>"><?php echo $getList['order_category']; ?></option>
                            <?php

                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Area</label>
                        <?php
                        $rs = $obconn->prepare("SELECT DISTINCT a.area, b.area_desc FROM user_area_dpst_mapping_view a INNER JOIN area b ON a.area = b.area_code");
                        // $rs->bindParam(':uname', $username, PDO::PARAM_STR);
                        $rs->execute();
                        ?>
                        <select class="form-control" name="area" id="area">
                            <option value="">Select Area</option>
                            <?php while ($rowArea = $rs->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo htmlspecialchars($rowArea['area']); ?>">
                                    <?php echo htmlspecialchars($rowArea['area_desc']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PO Number</label>
                        <input type="text" class="form-control" id="pono" placeholder="PO Number" />
                    </div>
                    <div class="form-group">
                        <label>Delivery Date</label>
                        <input type="text" class="form-control" id="dDate" placeholder="Delivery Date" />
                    </div>
                    <div class="form-group">
                        <label>Delivery Term</label>
                        <select class="form-control" id="deliveryTerm">
                            <option value="">Select</option>
                            <?php
                            $getDeliveryList = $obconn->prepare("select del_code,del_desc From delterm_master where dealers ='E'");
                            $getDeliveryList->execute();
                            while ($getDList = $getDeliveryList->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <option value="<?php echo $getDList['del_code']; ?>"><?php echo $getDList['del_desc']; ?></option>
                            <?php

                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Term</label>
                        <select class="form-control" id="paymentTerm">
                            <option value="">Select</option>
                            <?php
                            $getDeliveryList = $obconn->prepare("select distinct pay_code,pay_desc from spp_payterm_master where dpst='90033' and valid='Y' order by pay_desc");
                            $getDeliveryList->execute();
                            while ($getDList = $getDeliveryList->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <option value="<?php echo $getDList['pay_code']; ?>"><?php echo $getDList['pay_desc']; ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Transporter</label>
                        <select class="form-control" id="transporter">
                            <option value="">Select</option>
                            <?php
                            $rs = $obconn->prepare("select distinct a.trans_code,b.trans_name from dealercode_and_transportercode a,transporter_master b where a.trans_code=b.trans_code order by trans_name");
                            $rs->execute();
                            while ($qryExe = $rs->fetch(PDO::FETCH_ASSOC)) {
                                $tcode = $qryExe['trans_code'];
                                $tname = $qryExe['trans_name'];
                            ?>
                                <option value='<?php echo $tcode; ?>'><?php echo  ucwords(strtolower($tname));; ?></option>";
                            <?php
                            }
                            ?>
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Freight Amount</label>
                        <input type="text" placeholder="Freigh Amount" id="fAmount" class="form-control" />
                    </div>

                    <div class="form-group">
                        <label>Delivery Address</label>
                        <select class="form-control" onchange="changeAddressType(this.value)">
                            <option value=1>Dealer</option>
                            <option value=2>End Customer</option>
                        </select>
                    </div>

                    <div id="dealerAddressDiv">
                        <div class="form-group">
                            <label>Dealer Address</label>
                            <select class="form-control" id="customer_master">
                                <option value="">Select</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group addressInp">
                        <label>Street Address</label>
                        <textarea class="form-control"></textarea>
                    </div>



                    <div class="form-group addressInp">
                        <label>City</label>
                        <select class="form-control"></select>
                    </div>



                    <div class="form-group addressInp">
                        <label>State</label>
                        <select class="form-control"></select>
                    </div>

                    <div class="form-group addressInp">
                        <label>Pincode</label>
                        <input type="text" class="form-control">
                    </div>
                </div>
            </div>
            <div class="order-form-card" id="orderFormCard">
                <div class="order-form-grid">
                    <div class="form-group">
                        <label>Product</label>
                        <select class="custom-input" id="item" onchange="enableBtn()">
                            <option>Select a product</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" class="custom-input" value="1" id="qty">
                    </div>

                    <div class="form-group">
                        <label>Unit Price (₹)</label>
                        <input type="text" class="custom-input" id="price" readonly>
                    </div>
                </div>
                <div class="add-btn-wrapper">
                    <button class="add-item-btn" disabled onclick="addItemToCart()">
                        <i class="bi bi-plus-lg"></i>
                        Add Item
                    </button>
                </div>
            </div>
            <!-- CART ITEM -->
            <div class="booking-card" id="divCartHeader">
                <div class="booking-header">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="booking-title">
                                Cart Item(s)
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="divCart mt-2"></div>
                        </div>
                        <div class="col-md-12" style="display: none;" id="divbtnUpload" onclick="submitCart()">
                            <button style="float:right;" class="add-item-btn"><i class="bi bi-cloud-arrow-up"></i> Submit</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>
<script src="https://code.jquery.com/ui/1.14.2/jquery-ui.js"></script>
<script>
    $(document).ready(function() {
        $("#dDate").datepicker({
            dateFormat: "dd.mm.yy"
        });
        setTimeout(function() {
            $(".alert-info").hide();
        }, 4000);
        getItems();
        itemLoads();
        $('#item').select2({
            placeholder: 'Search Item',
            allowClear: true,
            width: '100%',
            // minimumInputLength: 2,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'searchItems',
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });

        $("#price").on("input", function() {
            let value = $(this).val();

            // Allow only numbers and one decimal point
            value = value.replace(/[^0-9.]/g, '');

            // Prevent multiple decimal points
            let parts = value.split('.');
            if (parts.length > 2) {
                value = parts[0] + '.' + parts.slice(1).join('');
            }

            $(this).val(value);
        });
        $("#price").on("blur", function() {
            let value = parseFloat($(this).val());

            if (!isNaN(value)) {
                $(this).val(value.toFixed(2));
            }
        });

        $('#customer_master').select2({
            placeholder: 'Search Customer',
            allowClear: true,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'customer_master',
                        search: params.term
                    };
                },
                processResults: function(data) {
                    return {
                        results: data
                    };
                },
                cache: true
            }
        });
    });

    function enableBtn() {
        $(".add-item-btn").prop("disabled", false);
        var item = $("#item").val().trim();
        var type = "getPrice";
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                item: item,
                action: type
            },
            dataType: "JSON",
            success: function(res) {
                $("#price").val(res.price);
            },
            error: function(xhr, status, error) {
                alert('Request failed');
            }
        });
    }

    function addItemToCart() {
        var item = $("#item").val().trim();
        var qty = $("#qty").val().trim();
        var price = $("#price").val().trim();


        const fields = [{
                value: item,
                message: "Please select an item"
            },
            {
                value: qty,
                message: "Please enter a quantity"
            },
            {
                value: price,
                message: "Please enter a price"
            }
        ];

        for (const field of fields) {
            if (!field.value) {
                alert(field.message);
                return;
            }
        }

        if (qty == 0) {
            alert("Please check the quantity");
            return;
        }

        data = {
            item: item,
            qty: qty,
            price: price,
            action: "addItem"
        };
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: data,
            dataType: "JSON",
            success: function(res) {
                if (res == '1') {
                    alert('Item Added Successfully');
                    getItems();
                    $("#price").val("");
                    $("#item").val("").trigger("change");

                } else if (res == '0') {
                    alert('Unable to Add Item');

                } else {
                    alert(res);
                    console.error(res);
                }
            },
            error: function(xhr, status, error) {

                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);

                alert('AJAX request failed');
            }
        });
    }

    function getItems() {
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                action: "getCartItems"
            },
            dataType: "HTML",
            success: function(response) {
                $(".divCart").html(response);
                var tCount = $("#cartTable > tbody > tr").length;
                (tCount > 0) ? $("#divbtnUpload").show(): $("#divbtnUpload").hide();
                (tCount > 0) ? $("#divCartHeader").show(): $("#divCartHeader").hide();
            }
        });
    }


    function itemLoads() {
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',

            data: {
                action: "itemSync"
            },
            async: true,
            success: function(response) {
                console.log('Background sync started');
            }
        });
    }

    function deleteCartItem(id) {
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                action: "deleteItem",
                id: id
            },
            dataType: "json",
            success: function() {
                getItems();
            }
        });
    }

    function updatePrice(id) {
        var qty = $("#idQty" + id).val();
        var price = $("#idPrice" + id).val().trim();
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                action: "updatePrice",
                id: id,
                qty: qty,
                price: price
            },
            dataType: "json",
            success: function(res) {
                $("#idTotal" + id).text(res.total_amount);
            }
        });
    }

    function submitCart() {
        var dpst = $("#dpst").val().trim();
        var orderCategory = $("#orderCategory").val().trim();
        var addressCode = $("#customer_master").val().trim();
        var deliveryTerm = $("#deliveryTerm").val().trim();
        var paymentTerm = $("#paymentTerm").val().trim();
        var transporter = $("#transporter").val().trim();
        var fAmount = $("#fAmount").val().trim();
        var area = $("#area").val().trim();
        var ddate = $("#dDate").val().trim();
        var pono = $("#pono").val().trim();
        const fields = [{
                value: dpst,
                message: "Please enter a dpst"
            },
            {
                value: orderCategory,
                message: "Please select a order category"
            },
            {
                value: addressCode,
                message: "Please select a address"
            },
            {
                value: deliveryTerm,
                message: "Please select a delivery term"
            },
            {
                value: paymentTerm,
                message: "Please select a payment term"
            },
            {
                value: transporter,
                message: "Please select a transporter"
            },
            {
                value: area,
                message: "Please select area"
            },
            {
                value: ddate,
                message: "Please select delivery date"
            },
            {
                value: pono,
                message: "Please enter pono"
            },
        ];

        for (const field of fields) {
            if (!field.value) {
                alert(field.message);
                return;
            }
        }
        data = {
            dpst: dpst,
            orderCategory: orderCategory,
            addressCode: addressCode,
            deliveryTerm: deliveryTerm,
            paymentTerm: paymentTerm,
            transporter: transporter,
            freightAmount: fAmount,
            area: area,
            pono: pono,
            ddate: ddate,
            action: "submitCart"
        };
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: data,
            dataType: "json",
            success: function(res) {
                if (res.status == "success") {
                    alert(`Order successfully placed : - Ref. No - ${res.order_no}`);
                    // getItems();
                    // $("#orderCategory").val("").trigger("change");
                    // $("#customer_master").val("").trigger("change");
                    // $("#deliveryTerm").val("").trigger("change");
                    // $("#paymentTerm").val("").trigger("change");
                    // $("#transporter").val("").trigger("change");
                } else {
                    alert("Error in placing order ! Please contact IT");
                }
            }
        });
    }

    function changeAddressType(type) {
        if (type == 1) {
            $("#dealerAddressDiv").show();
            $(".addressInp").hide();
        } else {
            $("#dealerAddressDiv").hide();
            $(".addressInp").show();
        }
    }
</script>