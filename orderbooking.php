<?php
session_start();
$username = $_SESSION['usr_name'];
// Check assigned permission
include('pdo_obconn.php');
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['role'])) {
    admin_refresh_session_role($obconn);
}

if (!rbac_user_can($obconn, 'order-booking', 'create-order')) {
    header('Location: access_denied.php');
    exit;
}
//end
$freightPercentage = 4;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Order Booking</title>
    <?php include('header_css.php'); ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/success_modal.css" rel="stylesheet" />
    <link href="css/select2_change.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.14.2/themes/base/jquery-ui.css">
    <style>
        .select2-selection__rendered {
            font-size: 14px !important;
            color: #94a3b8;
        }

        #dealerAddressDiv,
        #endCustomerAddressDiv {
            display: none;
        }

        #dealerAddressDiv.is-visible,
        #endCustomerAddressDiv.is-visible {
            display: contents;
        }

        .delivery-date-group {
            position: relative;
        }

        .delivery-date-note {
            display: none;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            padding: 6px 10px;
            width: fit-content;
            max-width: 100%;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.3;
            color: #721c24;
            background: #f8d7da;
            border: 1px solid #f8d7da;
            border-radius: 8px;
        }

        .delivery-date-note.is-visible {
            display: inline-flex;
        }

        .delivery-date-note .bi {
            font-size: 13px;
            color: #721c24;
            flex-shrink: 0;
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
                <div class="order-form-grid" id="orderBookingForm">
                    <div class="form-group">
                        <label>Dpst <span class="text-danger">*</span></label>
                        <select class="form-control" id="dpst">
                            <?php
                            $getDpst = $obconn->prepare("SELECT dpst FROM tbl_vayu_dpst_master WHERE status=1");
                            $getDpst->execute();
                            if ($getDpst->rowCount() > 0) {
                                while ($rowDpst = $getDpst->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                    <option value="<?php echo $rowDpst['dpst']; ?>"><?php echo $rowDpst['dpst']; ?></option>
                            <?php
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Order Category <span class="text-danger">*</span></label>
                        <select class="form-control" id="orderCategory" readonly style="cursor: not-allowed;pointer-events: none;background-color: #ededed;">
                            <option value="1">Standard</option>
                            <?php /* Temp comment
 				<option value="">Select</option>
                            <?php
                            $getCateList = $obconn->prepare("SELECT id,order_category FROM tbl_vayu_order_category WHERE status=1");
                            $getCateList->execute();
                            while ($getList = $getCateList->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <option value="<?php echo $getList['id']; ?>"><?php echo $getList['order_category']; ?></option>
                            <?php

                            } */ ?>

                        </select>
                    </div>
                    <div class="form-group">
                        <label>Area <span class="text-danger">*</span></label>
                        <?php
                        $rs = $obconn->prepare("SELECT * FROM area WHERE area_code IN('011','012','013','014','021','022','023','024','031','032','033','034','035','036','041','042','043','045','051','052','053','054','058')");
                        $rs->execute();
                        ?>
                        <select class="form-control" name="area" id="areaCode">
                            <option value="">Select Area</option>
                            <?php while ($rowArea = $rs->fetch(PDO::FETCH_ASSOC)) { ?>
                                <option value="<?php echo htmlspecialchars(trim($rowArea['area_code'])); ?>">
                                    <?php echo htmlspecialchars(trim($rowArea['area_desc'])); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>PO Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="pono" placeholder="PO Number" />
                    </div>
                    <div class="form-group delivery-date-group">
                        <label>Delivery Date <span class="text-danger">*</span> <span id="deliveryDateNote" class="delivery-date-note" aria-live="polite" hidden>
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            Subject to availability
                        </span></label>
                        <input type="text" class="form-control" id="dDate" placeholder="Delivery Date" autocomplete="false" />
                        
                    </div>
                    <div class="form-group">
                        <label>Delivery Term <span class="text-danger">*</span></label>
                        <select class="form-control" id="deliveryTerm">
                            <option value="">Select</option>
                            <option value='003'>FREIGHT PAID - D/D AGST. C/C</option>
                            <option value='508'>TO PAY-D/D AGA CONSIGNEE COPY</option>
                            <option value='509'>TOPAY-DOOR DELIVERY CC ATTACHED</option>
                            <option value='581'>TOPAY - GODOWN DELIVERY</option>
                            <option value='545'>TO-PAY DOOR DELIVERY (FTL)</option>
                            <option value='540'>TOPAY - DOOR DELIVERY ( LCV)</option>
                            <option value='011'>PAID-DOOR DELY REIM CC ATTACH</option>
                            <option value='013'>PAID-DD AGST CC REIM-PART LOAD</option>
                            <option value='579'>TOPAY-DOOR DELY AGNST C/C(FTL)</option>
                            <option value='580'>PAID-D/D AGNST C/C (FTL)</option>
                            <option value="546">PAID - GODOWN DELIVERY</option>
                            <option value='004'>PAID - DOOR DELY CC ATTACHED</option>
                            <option value='010'>PAID-DOOR DELIVERY REIM-FTL</option>
                            <option value='541'>PAID - DOOR DELIVERY (FTL)</option>
                            <option value='122'>PAID DOOR DELIVERY WITHOUT CC</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Payment Term <span class="text-danger">*</span></label>
                        <select class="form-control" id="paymentTerm" readonly style="cursor: not-allowed;pointer-events: none;background-color: #ededed;">
                            <option value="1">100% Advance</option>
                            <?php /*
                            <option value="">Select</option>
                            <?php
                            $getDeliveryList = $obconn->prepare("select distinct pay_code,pay_desc from spp_payterm_master where dpst='90092' and valid='Y' order by pay_desc");
                            $getDeliveryList->execute();
                            while ($getDList = $getDeliveryList->fetch(PDO::FETCH_ASSOC)) {
                            ?>
                                <option value="<?php echo $getDList['pay_code']; ?>"><?php echo $getDList['pay_desc']; ?></option>
                            <?php
                            } */
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Transporter <span class="text-danger">*</span></label>
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

                    <div class="form-group d-none">
                        <label>Freight Amount</label>
                        <input type="text" placeholder="Freight Amount" id="fAmount" class="form-control" inputmode="decimal" />
                    </div>
                    <div class="form-group">
                        <label>Delivery Address <span class="text-danger">*</span></label>
                        <select class="form-control" id="deliveryAddressType" onchange="changeAddressType(this.value)">
                            <option value="1">Dealer</option>
                            <option value="2">End Customer</option>
                        </select>
                    </div>
                    <?php
                    if (in_array($_SESSION['role'], [3, 2], true)) {
                    ?>
                        <div class="form-group">
                            <input type="hidden" id="deliveryAddressType" />

                            <label>Dealer <span class="text-danger">*</span></label>
                            <div class="dealerList">
                                <select class="form-control" id="dealerlist">

                                </select>
                            </div>
                        </div>
                        <div id="dealerAddressDiv" class="is-visible">
                            <div class="form-group">
                                <label>Dealer Address <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_master">
                                    <option value="">Select</option>
                                </select>
                            </div>
                        </div>
                    <?php
                    } else {
                    ?>

                        <div id="dealerAddressDiv" class="is-visible">
                            <div class="form-group">
                                <label>Dealer Address <span class="text-danger">*</span></label>
                                <select class="form-control" id="customer_master">
                                    <option value="">Select</option>
                                </select>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                    <div id="endCustomerAddressDiv">
                        <div class="form-group">
                            <label>End Customer Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="endCustomerName" name="end_customer_name"
                                placeholder="Name" autocomplete="email">
                        </div>
                        <div class="form-group">
                            <label>Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="endCustomerEmail" name="end_customer_email"
                                placeholder="Email" autocomplete="email">
                        </div>

                        <div class="form-group">
                            <label>Street 1 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="endCustomerStreet1" name="street_1"
                                placeholder="Street 1" maxlength="255">
                        </div>

                        <div class="form-group">
                            <label>Street 2</label>
                            <input type="text" class="form-control" id="endCustomerStreet2" name="street_2"
                                placeholder="Street 2" maxlength="255">
                        </div>

                        <div class="form-group">
                            <label>Pincode <span class="text-danger">*</span></label>
                            <select class="form-control" name="pincode" id="orderBookingPincodeSelect"
                                data-placeholder="Search pincode">
                                <option value=""></option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="city" id="endCustomerCity"
                                placeholder="Auto-filled from pincode" maxlength="100" readonly>
                        </div>

                        <div class="form-group">
                            <label>District <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="district" id="endCustomerDistrict"
                                placeholder="Auto-filled from pincode" maxlength="100" readonly>
                        </div>

                        <div class="form-group">
                            <label>State <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="state" id="endCustomerState"
                                placeholder="Auto-filled from pincode" maxlength="100" readonly>
                            <input type="hidden" id="state_code" name="state_code" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="order-form-card" id="orderFormCard">
                <div class="order-form-grid">
                    <div class="form-group">
                        <label>Product <span class="text-danger">*</span></label>
                        <select class="custom-input" id="item" onchange="enableBtn()">
                            <option>Select a product</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Quantity <span class="text-danger">*</span></label>
                        <input type="number" class="custom-input" value="1" id="qty">
                    </div>

                    <div class="form-group">
                        <label>Unit Price (&#8377;)</label>
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
            <div class="booking-card cart-section" id="divCartHeader">
                <div class="cart-section__header">
                    <div class="cart-section__title-wrap">
                        <div class="cart-section__title">
                            <i class="bi bi-cart3"></i>
                            <span>Cart Item(s)</span>
                        </div>
                        <span class="cart-section__badge" id="cartItemCountBadge">0</span>
                    </div>
                    <p class="cart-section__subtitle">Review quantities, gst, and totals before submitting your order.</p>
                </div>

                <div class="cart-section__body divCart"></div>

                <div class="cart-section__footer">
                    <div class="cart-section__loader" id="loader" style="display:none">
                        <img src="images/loader.gif" alt="Submitting order">
                        <span>Submitting order...</span>
                    </div>
                    <div class="cart-section__actions">
                        <button type="button" class="add-item-btn cart-submit-btn cart-submit-btn " id="divbtnUpload1" style="display:none" onclick="submitCartApi()">
                            <i class="bi bi-cloud-arrow-up"></i> Submit Order Api
                        </button>
                        <button type="button" class="add-item-btn cart-submit-btn d-none" id="divbtnUpload" style="display:none" onclick="submitCart()">
                            <i class="bi bi-check2-circle"></i> Submit Order
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <div class="modal fade" id="priceBreakupModal" tabindex="-1" aria-labelledby="priceBreakupModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable price-breakup-modal-dialog">
            <div class="modal-content price-breakup-modal">
                <div class="price-breakup-modal__header">
                    <div class="price-breakup-modal__title-wrap">
                        <span class="price-breakup-modal__icon" aria-hidden="true">
                            <i class="bi bi-receipt-cutoff"></i>
                        </span>
                        <div>
                            <h5 class="price-breakup-modal__title" id="priceBreakupModalLabel">Price Breakup</h5>
                            <p class="price-breakup-modal__subtitle" id="priceBreakupItemLabel">Loading price breakup...</p>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="price-breakup-modal__body">
                    <div class="price-breakup-card">
                        <div class="price-breakup-section">
                            <div class="price-breakup-section__head">
                                <i class="bi bi-tag"></i>
                                <span>Base Price</span>
                            </div>
                            <div class="price-breakup-row">
                                <span class="price-breakup-row__label">Total Price (Before GST)</span>
                                <span class="price-breakup-row__value" id="priceBreakupBeforeGst">N/A</span>
                            </div>
                        </div>

                        <div class="price-breakup-section">
                            <div class="price-breakup-section__head">
                                <i class="bi bi-percent"></i>
                                <span>GST Details</span>
                                <small>18% (9% CGST + 9% SGST)</small>
                            </div>
                            <div class="price-breakup-row">
                                <span class="price-breakup-row__label" id="priceBreakupCgstLabel">CGST (N/A)</span>
                                <span class="price-breakup-row__value" id="priceBreakupCgstAmount">N/A</span>
                            </div>
                            <div class="price-breakup-row">
                                <span class="price-breakup-row__label" id="priceBreakupSgstLabel">SGST (N/A)</span>
                                <span class="price-breakup-row__value" id="priceBreakupSgstAmount">N/A</span>
                            </div>
                        </div>

                        <div class="price-breakup-section price-breakup-section--freight">
                            <div class="price-breakup-section__head">
                                <i class="bi bi-truck"></i>
                                <span>Freight</span>
                                <small><?php echo $freightPercentage . '%' ?> of Total Price</small>
                            </div>
                            <div class="price-breakup-row">
                                <span class="price-breakup-row__label">Freight (<?php echo $freightPercentage . '%' ?>)</span>
                                <span class="price-breakup-row__value" id="priceBreakupFreightAmount">N/A</span>
                            </div>
                        </div>

                        <div class="price-breakup-total-box">
                            <div class="price-breakup-total-box__label">Grand Total</div>
                            <div class="price-breakup-total-box__value" id="priceBreakupTotal">N/A</div>
                        </div>
                    </div>
                </div>
                <div class="price-breakup-modal__footer d-none">
                    <button type="button" class="btn price-breakup-modal__close-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
<?php include('script_js.php'); ?>
<script src="https://code.jquery.com/ui/1.14.2/jquery-ui.js"></script>
<script src="js/pincode_select2.js"></script>
<script src="js/success_modal.js"></script>
<script>
    const endCustomerFieldIds = [
        'endCustomerEmail',
        'endCustomerStreet1',
        'endCustomerStreet2',
        'orderBookingPincodeSelect',
        'endCustomerCity',
        'endCustomerDistrict',
        'endCustomerState'
    ];

    function isValidEmailAddress(email) {
        const value = String(email || '').trim();
        if (!value) {
            return false;
        }
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    }

    function setEndCustomerRequired(isRequired) {
        endCustomerFieldIds.forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (!field) {
                return;
            }

            if (isRequired) {
                field.setAttribute('required', 'required');
            } else {
                field.removeAttribute('required');
            }
        });
    }

    function changeAddressType(type) {
        type = String(type);
        if (type === '1') {
            $('#dealerAddressDiv').addClass('is-visible');
            $('#endCustomerAddressDiv').removeClass('is-visible');
            setEndCustomerRequired(false);
            return;
        }
        $('#dealerAddressDiv').removeClass('is-visible');
        $('#endCustomerAddressDiv').addClass('is-visible');
        setEndCustomerRequired(true);
    }

    function toggleDeliveryDateNote() {
        var hasDate = ($("#dDate").val() || '').trim() !== '';
        $("#deliveryDateNote")
            .toggleClass('is-visible', hasDate)
            .prop('hidden', !hasDate);
    }

    $(document).ready(function() {
        $("#dDate").datepicker({
            dateFormat: "dd.mm.yy",
            minDate: 0,
            onSelect: function() {
                toggleDeliveryDateNote();
            }
        }).on('change input', function() {
            toggleDeliveryDateNote();
        });
        toggleDeliveryDateNote();
        setTimeout(function() {
            $(".alert-info").hide();
        }, 4000);
        getItems();
        itemLoads();
        $("#deliveryTerm").select2({});
        $("#paymentTerm_temp").select2({}); // Temp comment
        $("#transporter").select2({});
        $("#areaCode").select2({});
        $("#orderCategory_temp").select2({}); // Temp comment
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
                        dealer: $("#dealerlist").val(),
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

        initPincodeSelect2('orderBookingForm', 'orderBookingPincodeSelect');
        changeAddressType($('#deliveryAddressType').val() || '1');

        // Manual Freight Amount field � separate from per-line 4% freight calculation
        $('#fAmount').on('input', function() {
            sanitizeFreightInput(this);
        });
        getDealerList();
    });

    function sanitizeFreightInput(input) {
        if (!input) {
            return;
        }

        let value = String(input.value || '').replace(/[^0-9.]/g, '');
        const dotIndex = value.indexOf('.');

        if (dotIndex !== -1) {
            value = value.slice(0, dotIndex + 1) + value.slice(dotIndex + 1).replace(/\./g, '');
        }

        input.value = value;
    }

    function enableBtn() {
        $(".add-item-btn").prop("disabled", false);
        var item = $("#item").val().trim();
        var dpst = $("#dpst").val().trim();
        var type = "getPrice";
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                item: item,
                dpst: dpst,
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

    function getDealerList() {
        $('#dealerlist').select2({
            placeholder: 'Search Dealer',
            allowClear: true,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        action: 'search_dealer',
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

    }

    function getItems() {
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                action: "getCartItems",
                orderCategory: ($('#orderCategory').val() || '').trim()
            },
            dataType: "HTML",
            success: function(response) {
                $(".divCart").html(response);
                var tCount = $("#cartTable > tbody > tr.cart-item-row").length;
                var badge = document.getElementById('cartItemCountBadge');
                if (badge) {
                    badge.textContent = String(tCount);
                    badge.classList.toggle('is-empty', tCount === 0);
                }
                (tCount > 0) ? $("#divbtnUpload").show(): $("#divbtnUpload").hide();
                (tCount > 0) ? $("#divbtnUpload1").show(): $("#divbtnUpload1").hide();
                $("#divCartHeader").show();
                recalculateCartSummaryFromDom();
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

    let priceBreakupModalInstance = null;
    let orderPriceBreakupCache = null;
    let priceBreakupMode = null;

    function getPriceBreakupModal() {
        const modalEl = document.getElementById('priceBreakupModal');
        if (!modalEl) {
            return null;
        }

        if (!priceBreakupModalInstance) {
            priceBreakupModalInstance = new bootstrap.Modal(modalEl);
        }

        return priceBreakupModalInstance;
    }

    function setPriceBreakupValue(elementId, value) {
        const element = document.getElementById(elementId);
        if (element) {
            element.textContent = value || 'N/A';
        }
    }

    function parseBreakupAmount(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }

        const parsed = parseFloat(String(value).replace(/[^0-9.-]/g, ''));
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function formatCartAmount(amount) {
        const value = Number.isFinite(amount) ? amount : 0;
        return value.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatCartRupee(amount) {
        return formatCartAmount(amount);
    }
    const freightPercentage = 4;

    function computeCartLineAmounts(unitPrice, qty) {
        const totalPrice = Math.round(unitPrice * qty * 100) / 100;
        const freight = Math.round((totalPrice * freightPercentage) / 100 * 100) / 100;
        const cgst = Math.round(totalPrice * 0.09 * 100) / 100;
        const sgst = Math.round(totalPrice * 0.09 * 100) / 100;
        const gst = Math.round((cgst + sgst) * 100) / 100;
        const totalAmount = Math.round((totalPrice + gst + freight) * 100) / 100;

        return {
            totalPrice: totalPrice,
            freight: freight,
            cgst: cgst,
            sgst: sgst,
            gst: gst,
            totalAmount: totalAmount
        };
    }

    function renderCartGstBreakdown(cartId, cgst, sgst, totalGst) {
        const gstEl = document.getElementById('idGst' + cartId);
        if (!gstEl) {
            return;
        }

        const gstTotal = Number.isFinite(totalGst) ?
            totalGst :
            Math.round((cgst + sgst) * 100) / 100;

        gstEl.innerHTML = '' +
            '<div class="cart-gst-breakdown">' +
            '<span class="cart-gst-line cart-gst-line--total"> ' + formatCartAmount(gstTotal) + '</span>' +
            '</div>';
    }

    function setCartSummaryValue(elementId, amount, dataAttribute) {
        const element = document.getElementById(elementId);
        if (!element) {
            return;
        }

        element.textContent = formatCartAmount(amount);
        if (dataAttribute) {
            element.setAttribute(dataAttribute, String(amount));
        }
    }

    function getCartSummaryAmount(elementId, dataAttribute) {
        const element = document.getElementById(elementId);
        if (!element) {
            return 0;
        }

        const raw = element.getAttribute(dataAttribute);
        const parsed = parseFloat(raw);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function getCartTotalPriceSum() {
        return getCartSummaryAmount('cartTotalPrice', 'data-cart-total-price');
    }

    function getCartTotalGstSum() {
        return getCartSummaryAmount('cartTotalGst', 'data-cart-total-gst');
    }

    function getCartTotalAmountSum() {
        return getCartSummaryAmount('cartTotalAmount', 'data-cart-total-amount');
    }

    function recalculateCartSummaryFromDom() {
        let totalPrice = 0;
        let totalGst = 0;
        let totalFreight = 0;
        let totalAmount = 0;

        document.querySelectorAll('.cart-item-row').forEach(function(row) {
            const cartId = row.getAttribute('data-cart-id');
            const unitPrice = parseFloat(row.getAttribute('data-unit-price') || '0');
            const qtyInput = document.getElementById('idQty' + cartId);
            const qty = parseFloat((qtyInput && qtyInput.value) || '0');

            if (!Number.isFinite(unitPrice) || !Number.isFinite(qty) || qty <= 0) {
                return;
            }

            const amounts = computeCartLineAmounts(unitPrice, qty);
            totalPrice += amounts.totalPrice;
            totalGst += amounts.gst;
            totalFreight += amounts.freight;
            totalAmount += amounts.totalAmount;
        });

        setCartSummaryValue('cartTotalPrice', Math.round(totalPrice * 100) / 100, 'data-cart-total-price');
        setCartSummaryValue('cartTotalGst', Math.round(totalGst * 100) / 100, 'data-cart-total-gst');
        setCartSummaryValue('cartTotalFreight', Math.round(totalFreight * 100) / 100, 'data-cart-total-freight');
        setCartSummaryValue('cartTotalAmount', Math.round(totalAmount * 100) / 100, 'data-cart-total-amount');
    }

    function recalculateCartLine(cartId) {
        const row = document.querySelector('.cart-item-row[data-cart-id="' + cartId + '"]');
        if (!row) {
            return;
        }

        const unitPrice = parseFloat(row.getAttribute('data-unit-price') || '0');
        const qtyInput = document.getElementById('idQty' + cartId);
        const qty = parseFloat((qtyInput && qtyInput.value) || '0');

        if (!Number.isFinite(unitPrice) || !Number.isFinite(qty) || qty <= 0) {
            return;
        }

        const amounts = computeCartLineAmounts(unitPrice, qty);
        const lineTotalEl = document.getElementById('idLineTotalPrice' + cartId);
        const freightEl = document.getElementById('idFreight' + cartId);
        const totalEl = document.getElementById('idTotal' + cartId);

        if (lineTotalEl) {
            lineTotalEl.textContent = formatCartAmount(amounts.totalPrice);
        }

        renderCartGstBreakdown(cartId, amounts.cgst, amounts.sgst, amounts.gst);

        if (freightEl) {
            freightEl.textContent = formatCartAmount(amounts.freight);
        }

        if (totalEl) {
            totalEl.textContent = formatCartAmount(amounts.totalAmount);
        }

        recalculateCartSummaryFromDom();
    }

    function getCartItemsTotal() {
        return getCartTotalAmountSum();
    }

    function formatRupeeAmount(amount) {
        const value = Number.isFinite(amount) ? amount : 0;
        return value.toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function setPriceBreakupLoading(isLoading) {
        const modal = document.querySelector('.price-breakup-modal');
        if (modal) {
            modal.classList.toggle('is-loading', !!isLoading);
        }
    }

    function applyOrderPriceBreakupDisplay(res) {
        if (!res) {
            return;
        }

        const itemCount = parseInt(res.item_count, 10) || 0;
        const itemLabel = itemCount > 0 ?
            'Order total (' + itemCount + ' cart item' + (itemCount === 1 ? '' : 's') + ')' :
            'Order total (all cart items)';

        setPriceBreakupValue('priceBreakupItemLabel', itemLabel);
        setPriceBreakupValue('priceBreakupBeforeGst', res.price_before_gst);
        setPriceBreakupValue('priceBreakupCgstLabel', 'CGST (' + (res.cgst_percent || 'N/A') + ')');
        setPriceBreakupValue('priceBreakupCgstAmount', res.cgst_amount);
        setPriceBreakupValue('priceBreakupSgstLabel', 'SGST (' + (res.sgst_percent || 'N/A') + ')');
        setPriceBreakupValue('priceBreakupSgstAmount', res.sgst_amount);
        setPriceBreakupValue('priceBreakupFreightAmount', res.freight_amount);
        setPriceBreakupValue('priceBreakupTotal', res.total_price);
    }

    function refreshOrderPriceBreakupIfOpen() {
        const modalEl = document.getElementById('priceBreakupModal');
        if (!modalEl || !modalEl.classList.contains('show')) {
            return;
        }

        if (priceBreakupMode !== 'order' || !orderPriceBreakupCache) {
            return;
        }

        applyOrderPriceBreakupDisplay(orderPriceBreakupCache);
    }

    function openPriceBreakup(cartId) {
        priceBreakupMode = 'item';
        orderPriceBreakupCache = null;
        loadPriceBreakupModal({
            action: 'getCartPriceBreakup',
            id: cartId,
            itemLabel: 'Loading price breakup...'
        });
    }

    function openOrderPriceBreakup() {
        priceBreakupMode = 'order';
        loadPriceBreakupModal({
            action: 'getCartOrderPriceBreakup',
            itemLabel: 'Loading order price breakup...'
        });
    }

    function loadPriceBreakupModal(options) {
        const modal = getPriceBreakupModal();
        if (!modal) {
            return;
        }

        setPriceBreakupValue('priceBreakupItemLabel', options.itemLabel || 'Loading price breakup...');
        setPriceBreakupValue('priceBreakupBeforeGst', 'N/A');
        setPriceBreakupValue('priceBreakupCgstLabel', 'CGST (N/A)');
        setPriceBreakupValue('priceBreakupCgstAmount', 'N/A');
        setPriceBreakupValue('priceBreakupSgstLabel', 'SGST (N/A)');
        setPriceBreakupValue('priceBreakupSgstAmount', 'N/A');
        setPriceBreakupValue('priceBreakupFreightAmount', 'N/A');
        setPriceBreakupValue('priceBreakupTotal', 'N/A');
        setPriceBreakupLoading(true);
        modal.show();

        const requestData = {
            action: options.action,
            orderCategory: ($('#orderCategory').val() || '').trim()
        };

        if (options.id) {
            requestData.id = options.id;
        }

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: requestData,
            dataType: 'json',
            success: function(res) {
                setPriceBreakupLoading(false);

                if (!res || !res.status) {
                    alert((res && res.message) ? res.message : 'Unable to fetch price breakup.');
                    modal.hide();
                    return;
                }

                if (options.action === 'getCartOrderPriceBreakup') {
                    orderPriceBreakupCache = res;
                    applyOrderPriceBreakupDisplay(res);
                    return;
                }

                orderPriceBreakupCache = null;
                const itemLabel = [res.item_code, res.item_name].filter(Boolean).join(' - ') || 'Cart item';
                setPriceBreakupValue('priceBreakupItemLabel', itemLabel);
                setPriceBreakupValue('priceBreakupBeforeGst', res.price_before_gst);
                setPriceBreakupValue('priceBreakupCgstLabel', 'CGST (' + (res.cgst_percent || 'N/A') + ')');
                setPriceBreakupValue('priceBreakupCgstAmount', res.cgst_amount);
                setPriceBreakupValue('priceBreakupSgstLabel', 'SGST (' + (res.sgst_percent || 'N/A') + ')');
                setPriceBreakupValue('priceBreakupSgstAmount', res.sgst_amount);
                setPriceBreakupValue('priceBreakupFreightAmount', res.freight_amount);
                setPriceBreakupValue('priceBreakupTotal', res.total_price);
            },
            error: function() {
                setPriceBreakupLoading(false);
                alert('Unable to fetch price breakup.');
                modal.hide();
            }
        });
    }

    function updatePrice(id) {
        var qty = $("#idQty" + id).val();
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                action: "updatePrice",
                id: id,
                qty: qty
            },
            dataType: "json",
            success: function() {
                getItems();
            }
        });
    }

    function submitCart() {
        $("#loader").show();
        $("#divbtnUpload").hide();
        $("#divbtnUpload1").hide();
        var dpst = $("#dpst").val().trim();
        var orderCategory = $("#orderCategory").val().trim();
        var addressCode = $("#customer_master").val().trim();
        var deliveryTerm = $("#deliveryTerm").val().trim();
        var paymentTerm = $("#paymentTerm").val().trim();
        var transporter = $("#transporter").val().trim();
        var fAmount = $("#fAmount").val().trim();
        var area = ($("#areaCode").val() || "").trim();
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
                message: "Please enter PO - Number"
            },
        ];

        // Added validation 01-07-26
        const deliveryAddressType = $("#deliveryAddressType").val();

        // Added validation 01-07-26
        if (deliveryAddressType == "1") {
            fields.push({
                value: addressCode,
                message: "Please select an address"
            });
        } else {
            fields.push({
                value: $("#endCustomerName").val().trim(),
                message: "Please enter name"
            }, {
                value: $("#endCustomerEmail").val().trim(),
                message: "Please enter email"
            }, {
                value: $("#endCustomerStreet1").val(),
                message: "Please enter street 1"
            }, {
                value: $("#orderBookingPincodeSelect").val(),
                message: "Please select pincode"
            }, {
                value: $("#endCustomerCity").val(),
                message: "Please enter city"
            }, {
                value: $("#endCustomerDistrict").val(),
                message: "Please enter district"
            }, {
                value: $("#endCustomerState").val(),
                message: "Please enter state"
            });
        }
        // END ADDRESS VALIDATION 01-07-26  

        for (const field of fields) {
            if (!field.value) {
                alert(field.message);
                $("#loader").hide();
                $("#divbtnUpload").show();
                $("#divbtnUpload1").show();
                return;
            }
        }

        if (deliveryAddressType == "2") {
            const endCustomerEmail = $("#endCustomerEmail").val().trim();
            if (!isValidEmailAddress(endCustomerEmail)) {
                alert("Please enter a valid email address");
                $("#endCustomerEmail").focus();
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
            deliveryAddressType: deliveryAddressType,
            action: "submitCart"
        };

        if (deliveryAddressType == "2") {
            data.end_customer_name = $("#endCustomerName").val().trim();
            data.end_customer_email = $("#endCustomerEmail").val().trim();
            data.street_1 = $("#endCustomerStreet1").val().trim();
            data.street_2 = $("#endCustomerStreet2").val().trim();
            data.pincode = ($("#orderBookingPincodeSelect").val() || "").trim();
            data.city = $("#endCustomerCity").val().trim();
            data.district = $("#endCustomerDistrict").val().trim();
            data.state = $("#endCustomerState").val().trim();
        }
        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: data,
            dataType: "json",
            success: function(res) {
                if (res.status == "success") {
                    $("#loader").hide();
                    $("#divbtnUpload").show();
                    $("#divbtnUpload1").show();
                    getItems();
                    $("#orderCategory").val("").trigger("change");
                    $("#customer_master").val("").trigger("change");
                    $("#deliveryTerm").val("").trigger("change");
                    $("#paymentTerm").val("").trigger("change");
                    $("#transporter").val("").trigger("change");
                    $("#fAmount").val("");
                    // Modern success popup (reusable); redirect after the user closes it
                    SuccessModal.show({
                        title: 'Order Created Successfully',
                        message: 'Your order has been created successfully.',
                        status: res.order_no,
                        onClose: function() {
                            window.location.href = "recent_orders.php?order_no=" + res.order_no;
                        }
                    });
                } else {
                    alert("Error in placing order ! Please contact IT");
                }
            }
        });
    }

    function submitCartApi() {
        $("#loader").show();
        $("#divbtnUpload").hide();
        $("#divbtnUpload1").hide();
        var dpst = $("#dpst").val().trim();
        var orderCategory = $("#orderCategory").val().trim();
        var addressCode = $("#customer_master").val().trim();
        var deliveryTerm = $("#deliveryTerm").val().trim();
        var paymentTerm = $("#paymentTerm").val().trim();
        var transporter = $("#transporter").val().trim();
        var fAmount = $("#fAmount").val().trim();
        var area = ($("#areaCode").val() || "").trim();

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
                message: "Please enter PO Number"
            },
        ];

        const deliveryAddressType = $("#deliveryAddressType").val();

        // Added validation 01-07-26
        if (deliveryAddressType == "1") {
            fields.push({
                value: addressCode,
                message: "Please select an address"
            });
        } else {
            fields.push({
                value: $("#endCustomerName").val().trim(),
                message: "Please enter name"
            }, {
                value: $("#endCustomerEmail").val().trim(),
                message: "Please enter email"
            }, {
                value: $("#endCustomerStreet1").val(),
                message: "Please enter street 1"
            }, {
                value: $("#orderBookingPincodeSelect").val(),
                message: "Please select pincode"
            }, {
                value: $("#endCustomerCity").val(),
                message: "Please enter city"
            }, {
                value: $("#endCustomerDistrict").val(),
                message: "Please enter district"
            }, {
                value: $("#endCustomerState").val(),
                message: "Please enter state"
            });
        }

        for (const field of fields) {
            if (!field.value) {
                alert(field.message);
                $("#loader").hide();
                $("#divbtnUpload").show();
                $("#divbtnUpload1").show();
                return;
            }
        }
        if (deliveryAddressType == "2") {
            const endCustomerEmail = $("#endCustomerEmail").val().trim();
            if (!isValidEmailAddress(endCustomerEmail)) {
                alert("Please enter a valid email address");
                $("#endCustomerEmail").focus();
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
            deliveryAddressType: deliveryAddressType,
            area: area,
            pono: pono,
            ddate: ddate,
            action: "submitCartApi"
        };


        if (deliveryAddressType == "2") {
            data.end_customer_name = $("#endCustomerName").val().trim();
            data.end_customer_email = $("#endCustomerEmail").val().trim();
            data.street_1 = $("#endCustomerStreet1").val().trim();
            data.street_2 = $("#endCustomerStreet2").val().trim();
            data.pincode = ($("#orderBookingPincodeSelect").val() || "").trim();
            data.city = $("#endCustomerCity").val().trim();
            data.district = $("#endCustomerDistrict").val().trim();
            data.state = $("#endCustomerState").val().trim();
            data.state_code = $("#state_code").val().trim();
        }

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: data,
            dataType: "json",
            success: function(res) {
                if (res.status) {
                    setTimeout(function() {
                        $("#divbtnUpload").show();
                        $("#divbtnUpload1").show();
                        getItems();
                        $("#orderCategory").val("").trigger("change");
                        $("#customer_master").val("").trigger("change");
                        $("#deliveryTerm").val("").trigger("change");
                        $("#paymentTerm").val("").trigger("change");
                        $("#transporter").val("").trigger("change");
                        $("#fAmount").val("");
                        $("#loader").hide();
                        // Modern success popup (reusable); redirect after the user closes it
                        SuccessModal.show({
                            title: 'Order Created Successfully',
                            message: 'Your order has been created successfully.',
                            status: res.status,
                            onClose: function() {
                                window.location.href = "recent_orders.php?order_no=" + res.status;
                            }
                        });
                    }, 4000);
                } else {
                    $("#loader").hide();
                    alert("Error in placing order ! Please contact IT");
                }
            }
        });
    }
</script>