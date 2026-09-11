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

$freightPercentage = 4;
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
                                            <th>Order Number</th>
                                            <th>AO Number</th>
                                            <th>Order Date</th>
                                            <th>Customer</th>
                                            <th>PO Number</th>
                                            <th>Invoice Address</th>
                                            <th>Payment Terms</th>
                                            <th>Delivery Terms</th>
                                            <th>Delivery Address</th>
                                            <th>Action</th>
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
                                <small><?php echo $freightPercentage; ?>% of Total Price</small>
                            </div>
                            <div class="price-breakup-row">
                                <span class="price-breakup-row__label">Freight (<?php echo $freightPercentage; ?>%)</span>
                                <span class="price-breakup-row__value" id="priceBreakupFreightAmount">N/A</span>
                            </div>
                        </div>

                        <div class="price-breakup-total-box">
                            <div class="price-breakup-total-box__label">Grand Total</div>
                            <div class="price-breakup-total-box__value" id="priceBreakupTotal">N/A</div>
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

    function getPriceBreakupModal() {
        const el = document.getElementById('priceBreakupModal');
        if (!el || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }
        return bootstrap.Modal.getOrCreateInstance(el);
    }

    function setPriceBreakupValue(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = (value === null || value === undefined || value === '') ? 'N/A' : value;
        }
    }

    function setPriceBreakupLoading(isLoading) {
        const modal = document.querySelector('.price-breakup-modal');
        if (modal) {
            modal.classList.toggle('is-loading', !!isLoading);
        }
    }

    function openPendingOrderPriceBreakup(ordno) {
        const modal = getPriceBreakupModal();
        if (!modal || !ordno) {
            return;
        }

        setPriceBreakupValue('priceBreakupItemLabel', 'Loading price breakup for AO ' + ordno + '...');
        setPriceBreakupValue('priceBreakupBeforeGst', 'N/A');
        setPriceBreakupValue('priceBreakupCgstLabel', 'CGST (N/A)');
        setPriceBreakupValue('priceBreakupCgstAmount', 'N/A');
        setPriceBreakupValue('priceBreakupSgstLabel', 'SGST (N/A)');
        setPriceBreakupValue('priceBreakupSgstAmount', 'N/A');
        setPriceBreakupValue('priceBreakupFreightAmount', 'N/A');
        setPriceBreakupValue('priceBreakupTotal', 'N/A');
        setPriceBreakupLoading(true);
        modal.show();

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'getPendingOrderPriceBreakup',
                ordno: ordno
            },
            success: function(res) {
                setPriceBreakupLoading(false);
                if (!res || !res.status) {
                    alert((res && res.message) ? res.message : 'Unable to fetch price breakup.');
                    return;
                }

                const itemCount = parseInt(res.item_count, 10) || 0;
                const itemLabel = itemCount > 0
                    ? 'AO ' + ordno + ' (' + itemCount + ' line' + (itemCount === 1 ? '' : 's') + ')'
                    : 'AO ' + ordno;

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
            }
        });
    }

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
                    action: 'getPendingOrderListNew'
                }
            },

            columns: [{
                    data: 'order_number'
                },
                {
                    data: 'ao_number'
                },
                {
                    data: 'order_date'
                },
                {
                    data: 'customer'
                },
                {
                    data: 'po_number'
                },
                {
                    data: 'invoice_address',
                    orderable: false
                },
                {
                    data: 'payment_terms',
                    orderable: false
                },
                {
                    data: 'delivery_terms',
                    orderable: false
                },
                {
                    data: 'delivery_address',
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