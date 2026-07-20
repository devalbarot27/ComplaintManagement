<?php
session_start();

include __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_page_guard.php';
require_once __DIR__ . '/orderClass.php';

if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

admin_refresh_session_role($obconn);

$refno = trim((string) ($_GET['refno'] ?? $_GET['order'] ?? ''));
$orderService = new orderClass($obconn, $dpconn);
$details = $orderService->getRecentOrderDetails($refno);

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$header = $details['header'] ?? [];
$lines = $details['lines'] ?? [];
$totals = $details['totals'] ?? [
    'subtotal' => 0,
    'tax' => 0,
    'freight' => 0,
    'grand_total' => 0,
];

$customerLabel = trim((string) ($header['cuname'] ?? ''));
$cuno = trim((string) ($header['cuno'] ?? ''));
if ($customerLabel !== '' && $cuno !== '') {
    $customerLabel .= ' [' . $cuno . ']';
} elseif ($cuno !== '') {
    $customerLabel = $cuno;
} elseif ($customerLabel === '') {
    $customerLabel = '-';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dealer - Recent Order Details</title>
    <?php include __DIR__ . '/header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/recent_order_details.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>

<body class="oad-page">

    <div class="main-wrapper" id="mainWrapper">
        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="content">
            <a href="recent_orders.php" class="oad-back">
                <i class="bi bi-arrow-left"></i> Back to Recent Orders
            </a>

            <?php if (empty($details['success'])) { ?>
                <div class="oad-error-card">
                    <h2 class="mb-2">Unable to load order</h2>
                    <p class="text-muted mb-3">
                        <?php echo $h($details['error'] ?? 'Invalid or missing order reference.'); ?>
                    </p>
                    <a href="recent_orders.php" class="btn btn-dark btn-sm">Return to Recent Orders</a>
                </div>
            <?php } else { ?>
                <div class="order-form-card oad-shell" id="orderFormCard">
                    <div class="oad-hero">
                        <div class="oad-hero__left">
                            <div class="oad-hero__icon" aria-hidden="true">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <div>
                                <span class="oad-hero__eyebrow">ELGI Equipments Ltd</span>
                                <h1 class="oad-hero__title">Recent Order Details</h1>
                            </div>
                        </div>
                        <div class="oad-hero__badges">
                            <div class="oad-hero__badge oad-hero__badge--accent">
                                <span>Ref No.</span>
                                <?php echo $h($header['refno'] ?? '-'); ?>
                            </div>
                            <div class="oad-hero__badge">
                                <span>Date</span>
                                <?php echo $h($header['order_date'] ?? '-'); ?>
                            </div>
                            <div class="oad-hero__badge">
                                <span>Status</span>
                                <?php echo $h($header['order_status'] ?? '-'); ?>
                            </div>
                        </div>
                    </div>

                    <div class="oad-spotlight">
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-hash"></i></div>
                            <div>
                                <span class="oad-spotlight__label">AO Number</span>
                                <div class="oad-spotlight__value"><?php echo $h($header['order_number'] ?? '-'); ?></div>
                            </div>
                        </div>
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-person"></i></div>
                            <div>
                                <span class="oad-spotlight__label">Customer</span>
                                <div class="oad-spotlight__value"><?php echo $h($customerLabel); ?></div>
                            </div>
                        </div>
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-receipt"></i></div>
                            <div>
                                <span class="oad-spotlight__label">PO Number</span>
                                <div class="oad-spotlight__value"><?php echo $h($header['po_number'] ?? '-'); ?></div>
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
                                    <span class="oad-meta-label">Ref Number</span>
                                    <div class="oad-meta-value"><?php echo $h($header['refno'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">AO Number</span>
                                    <div class="oad-meta-value"><?php echo $h($header['order_number'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Order Date</span>
                                    <div class="oad-meta-value"><?php echo $h($header['order_date'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Customer</span>
                                    <div class="oad-meta-value"><?php echo $h($customerLabel); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">PO Number</span>
                                    <div class="oad-meta-value"><?php echo $h($header['po_number'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Category</span>
                                    <div class="oad-meta-value"><?php echo $h($header['category'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Payment Terms</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $h($header['payment_term'] ?? '-'); ?></span></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Delivery Terms</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $h($header['delivery_term'] ?? '-'); ?></span></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Transporter</span>
                                    <div class="oad-meta-value"><?php echo $h($header['transporter'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Currency</span>
                                    <div class="oad-meta-value"><?php echo $h($header['currency'] ?? 'INR'); ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Order Status</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $h($header['order_status'] ?? '-'); ?></span></div>
                                </div>
                                <?php if (!empty($header['email'])) { ?>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Email</span>
                                    <div class="oad-meta-value"><?php echo $h($header['email']); ?></div>
                                </div>
                                <?php } ?>
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
                                    <div class="oad-address-card__body"><?php echo $h($header['invoice_address'] ?? '-'); ?></div>
                                </div>
                                <div class="oad-address-card">
                                    <div class="oad-address-card__head">
                                        <div class="oad-address-card__icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></div>
                                        <h3 class="oad-address-card__title">Delivery Address</h3>
                                    </div>
                                    <div class="oad-address-card__body"><?php echo $h($header['delivery_address'] ?? '-'); ?></div>
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
                                                <th>Position No</th>
                                                <th>Item Code</th>
                                                <th>Item Description</th>
                                                <th>UOM</th>
                                                <th>Qty</th>
                                                <th>Price / Unit</th>
                                                <th>Line Total</th>
                                                <th>Tax</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($lines === []) { ?>
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-4">No line items found.</td>
                                                </tr>
                                            <?php } else { ?>
                                                <?php foreach ($lines as $line) { ?>
                                                    <tr>
                                                        <td class="text-center"><span class="oad-pos"><?php echo $h($line['posno']); ?></span></td>
                                                        <td><?php echo $h($line['item_code'] !== '' ? $line['item_code'] : '-'); ?></td>
                                                        <td><span class="oad-item-desc"><?php echo $h($line['item_desc'] !== '' ? $line['item_desc'] : '-'); ?></span></td>
                                                        <td class="text-center"><span class="oad-uom"><?php echo $h($line['uom']); ?></span></td>
                                                        <td class="text-end"><?php echo number_format((float) $line['qty'], 2); ?></td>
                                                        <td class="text-end"><?php echo number_format((float) $line['price'], 2); ?></td>
                                                        <td class="text-end"><?php echo number_format((float) $line['line_total'], 2); ?></td>
                                                        <td class="text-end"><?php echo number_format((float) $line['tax_amount'], 2); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="oad-totals">
                                <div class="oad-totals__box">
                                    <div class="oad-totals__row">
                                        <span>Subtotal</span>
                                        <strong><?php echo number_format((float) $totals['subtotal'], 2); ?></strong>
                                    </div>
                                    <div class="oad-totals__row">
                                        <span>Tax</span>
                                        <strong><?php echo number_format((float) $totals['tax'], 2); ?></strong>
                                    </div>
                                    <div class="oad-totals__row">
                                        <span>Freight</span>
                                        <strong><?php echo number_format((float) $totals['freight'], 2); ?></strong>
                                    </div>
                                    <div class="oad-totals__row oad-totals__row--grand">
                                        <span>Grand Total</span>
                                        <span><?php echo number_format((float) $totals['grand_total'], 2); ?></span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</body>

</html>
<?php include __DIR__ . '/script_js.php'; ?>
