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

$header = $details['header'] ?? [];
$lines = $details['lines'] ?? [];
$loadOk = !empty($details['success']);
$errorText = (string) ($details['error'] ?? 'Invalid or missing order reference.');

$customerLabel = trim((string) ($header['cuname'] ?? ''));
$cuno = trim((string) ($header['cuno'] ?? ''));
if ($customerLabel !== '' && $cuno !== '') {
    $customerLabel .= ' [' . $cuno . ']';
} elseif ($cuno !== '') {
    $customerLabel = $cuno;
} elseif ($customerLabel === '') {
    $customerLabel = '-';
}

$deliveryIsDealer = (($header['delivery_address_type'] ?? '') === 'dealer');
$deliveryAddressLabel = $deliveryIsDealer ? 'Dealer' : 'End Customer';
$dealerDeliveryAddress = (string) ($header['dealer_delivery_address'] ?? $header['delivery_address'] ?? '-');

// Escape into locals, then drop tainted source arrays (scanner-friendly XSS pattern).
$safe = [
    'error' => htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8'),
    'refno' => htmlspecialchars((string) ($header['refno'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'order_date' => htmlspecialchars((string) ($header['order_date'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'order_status' => htmlspecialchars((string) ($header['order_status'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'order_number' => htmlspecialchars((string) ($header['order_number'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'customer' => htmlspecialchars($customerLabel, ENT_QUOTES, 'UTF-8'),
    'po_number' => htmlspecialchars((string) ($header['po_number'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'category' => htmlspecialchars((string) ($header['category'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'area' => htmlspecialchars((string) ($header['area'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'delivery_date' => htmlspecialchars((string) ($header['delivery_date'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'payment_term' => htmlspecialchars((string) ($header['payment_term'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'delivery_term' => htmlspecialchars((string) ($header['delivery_term'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'transporter' => htmlspecialchars((string) ($header['transporter'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'currency' => htmlspecialchars((string) ($header['currency'] ?? 'INR'), ENT_QUOTES, 'UTF-8'),
    'email' => htmlspecialchars((string) ($header['email'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'invoice_address' => htmlspecialchars((string) ($header['invoice_address'] ?? '-'), ENT_QUOTES, 'UTF-8'),
    'delivery_address_label' => htmlspecialchars($deliveryAddressLabel, ENT_QUOTES, 'UTF-8'),
    'dealer_delivery_address' => htmlspecialchars($dealerDeliveryAddress, ENT_QUOTES, 'UTF-8'),
    'end_customer_name' => htmlspecialchars((string) ($header['end_customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_email' => htmlspecialchars((string) ($header['end_customer_email'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_street1' => htmlspecialchars((string) ($header['end_customer_street1'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_street2' => htmlspecialchars((string) ($header['end_customer_street2'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_pincode' => htmlspecialchars((string) ($header['end_customer_pincode'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_city' => htmlspecialchars((string) ($header['end_customer_city'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_district' => htmlspecialchars((string) ($header['end_customer_district'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'end_customer_state' => htmlspecialchars((string) ($header['end_customer_state'] ?? ''), ENT_QUOTES, 'UTF-8'),
];

$hasEmail = trim((string) ($header['email'] ?? '')) !== '';
$hasEndName = trim((string) ($header['end_customer_name'] ?? '')) !== '';
$hasEndEmail = trim((string) ($header['end_customer_email'] ?? '')) !== '';
$hasEndStreet1 = trim((string) ($header['end_customer_street1'] ?? '')) !== '';
$hasEndStreet2 = trim((string) ($header['end_customer_street2'] ?? '')) !== '';
$hasEndPincode = trim((string) ($header['end_customer_pincode'] ?? '')) !== '';
$hasEndCity = trim((string) ($header['end_customer_city'] ?? '')) !== '';
$hasEndDistrict = trim((string) ($header['end_customer_district'] ?? '')) !== '';
$hasEndState = trim((string) ($header['end_customer_state'] ?? '')) !== '';
$hasAnyEndField = $hasEndName || $hasEndEmail || $hasEndStreet1 || $hasEndPincode
    || $hasEndDistrict || $hasEndState || $hasEndCity || $hasEndStreet2;

$safeLines = [];
foreach ($lines as $line) {
    $safeLines[] = [
        'posno' => htmlspecialchars((string) ($line['posno'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'item_code' => htmlspecialchars(
            ((string) ($line['item_code'] ?? '') !== '') ? (string) $line['item_code'] : '-',
            ENT_QUOTES,
            'UTF-8'
        ),
        'item_desc' => htmlspecialchars(
            ((string) ($line['item_desc'] ?? '') !== '') ? (string) $line['item_desc'] : '-',
            ENT_QUOTES,
            'UTF-8'
        ),
        'qty' => htmlspecialchars(number_format((float) ($line['qty'] ?? 0), 2), ENT_QUOTES, 'UTF-8'),
        'price' => htmlspecialchars(number_format((float) ($line['price'] ?? 0), 2), ENT_QUOTES, 'UTF-8'),
    ];
}

unset($details, $header, $lines, $customerLabel, $cuno, $errorText, $dealerDeliveryAddress, $deliveryAddressLabel);
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

            <?php if (!$loadOk) { ?>
                <div class="oad-error-card">
                    <h2 class="mb-2">Unable to load order</h2>
                    <p class="text-muted mb-3">
                        <?php echo $safe['error']; ?>
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
                                <?php echo $safe['refno']; ?>
                            </div>
                            <div class="oad-hero__badge">
                                <span>Date</span>
                                <?php echo $safe['order_date']; ?>
                            </div>
                            <div class="oad-hero__badge">
                                <span>Status</span>
                                <?php echo $safe['order_status']; ?>
                            </div>
                        </div>
                    </div>

                    <div class="oad-spotlight">
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-hash"></i></div>
                            <div>
                                <span class="oad-spotlight__label">AO Number</span>
                                <div class="oad-spotlight__value"><?php echo $safe['order_number']; ?></div>
                            </div>
                        </div>
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-person"></i></div>
                            <div>
                                <span class="oad-spotlight__label">Customer</span>
                                <div class="oad-spotlight__value"><?php echo $safe['customer']; ?></div>
                            </div>
                        </div>
                        <div class="oad-spotlight__item">
                            <div class="oad-spotlight__icon" aria-hidden="true"><i class="bi bi-receipt"></i></div>
                            <div>
                                <span class="oad-spotlight__label">PO Number</span>
                                <div class="oad-spotlight__value"><?php echo $safe['po_number']; ?></div>
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
                                    <div class="oad-meta-value"><?php echo $safe['refno']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">AO Number</span>
                                    <div class="oad-meta-value"><?php echo $safe['order_number']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Order Date</span>
                                    <div class="oad-meta-value"><?php echo $safe['order_date']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Customer</span>
                                    <div class="oad-meta-value"><?php echo $safe['customer']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">PO Number</span>
                                    <div class="oad-meta-value"><?php echo $safe['po_number']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Order Category</span>
                                    <div class="oad-meta-value"><?php echo $safe['category']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Area</span>
                                    <div class="oad-meta-value"><?php echo $safe['area']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Delivery Date</span>
                                    <div class="oad-meta-value"><?php echo $safe['delivery_date']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Payment Term</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $safe['payment_term']; ?></span></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Delivery Term</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $safe['delivery_term']; ?></span></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Transporter</span>
                                    <div class="oad-meta-value"><?php echo $safe['transporter']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Currency</span>
                                    <div class="oad-meta-value"><?php echo $safe['currency']; ?></div>
                                </div>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Order Status</span>
                                    <div class="oad-meta-value"><span class="oad-chip"><?php echo $safe['order_status']; ?></span></div>
                                </div>
                                <?php if ($hasEmail) { ?>
                                <div class="oad-meta-card">
                                    <span class="oad-meta-label">Email</span>
                                    <div class="oad-meta-value"><?php echo $safe['email']; ?></div>
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
                                    <div class="oad-address-card__body"><?php echo $safe['invoice_address']; ?></div>
                                </div>
                                <div class="oad-address-card">
                                    <div class="oad-address-card__head">
                                        <div class="oad-address-card__icon" aria-hidden="true"><i class="bi bi-geo-alt"></i></div>
                                        <h3 class="oad-address-card__title">
                                            Delivery Address
                                            <small class="text-muted">(<?php echo $safe['delivery_address_label']; ?>)</small>
                                        </h3>
                                    </div>
                                    <div class="oad-address-card__body">
                                        <?php if ($deliveryIsDealer) { ?>
                                            <?php echo $safe['dealer_delivery_address']; ?>
                                        <?php } else { ?>
                                            <table class="table table-sm table-borderless mb-0" style="font-size: 0.92rem;">
                                                <?php if ($hasEndName) { ?>
                                                <tr><td class="text-muted" style="width:120px;">Name</td><td><?php echo $safe['end_customer_name']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndEmail) { ?>
                                                <tr><td class="text-muted">Email</td><td><?php echo $safe['end_customer_email']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndStreet1) { ?>
                                                <tr><td class="text-muted">Street 1</td><td><?php echo $safe['end_customer_street1']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndStreet2) { ?>
                                                <tr><td class="text-muted">Street 2</td><td><?php echo $safe['end_customer_street2']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndPincode) { ?>
                                                <tr><td class="text-muted">Pincode</td><td><?php echo $safe['end_customer_pincode']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndCity) { ?>
                                                <tr><td class="text-muted">City</td><td><?php echo $safe['end_customer_city']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndDistrict) { ?>
                                                <tr><td class="text-muted">District</td><td><?php echo $safe['end_customer_district']; ?></td></tr>
                                                <?php } ?>
                                                <?php if ($hasEndState) { ?>
                                                <tr><td class="text-muted">State</td><td><?php echo $safe['end_customer_state']; ?></td></tr>
                                                <?php } ?>
                                            </table>
                                            <?php if (!$hasAnyEndField) { ?>
                                                <span class="text-muted">-</span>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
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
                                                <th>Qty</th>
                                                <th>Price / Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($safeLines === []) { ?>
                                                <tr>
                                                    <td colspan="5" class="text-center text-muted py-4">No line items found.</td>
                                                </tr>
                                            <?php } else { ?>
                                                <?php foreach ($safeLines as $line) { ?>
                                                    <tr>
                                                        <td class="text-center"><span class="oad-pos"><?php echo $line['posno']; ?></span></td>
                                                        <td><?php echo $line['item_code']; ?></td>
                                                        <td><span class="oad-item-desc"><?php echo $line['item_desc']; ?></span></td>
                                                        <td class="text-end"><?php echo $line['qty']; ?></td>
                                                        <td class="text-end"><?php echo $line['price']; ?></td>
                                                    </tr>
                                                <?php } ?>
                                            <?php } ?>
                                        </tbody>
                                    </table>
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
