<?php

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';
include 'includes/product_helpers.php';
require_once 'includes/record_details_layout.php';

require_system_admin($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = product_get_by_id($obconn, $id);

if (!$record) {
    die('Product not found.');
}

$tplCode = product_display_value($record['tplcode'] ?? '');
$tplDesc = product_display_value($record['tpldesc'] ?? '');
$pageTitle = $tplCode !== '-' ? $tplCode : ('Product #' . (int) $record['id']);
$validBadge = product_yn_badge((string) ($record['valid'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/record_details.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php
            record_details_page_header(
                'Product Details',
                $pageTitle,
                'products.php',
                'Back to List',
                'bi-box-seam',
                [
                    record_details_id_chip((int) $record['id']),
                    $validBadge,
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Product Identity', 'Core identification and classification');
            record_details_field('DPST', product_display_value($record['dpst'] ?? ''), 'col-md-3');
            record_details_field('Product Group', product_display_value($record['product_group'] ?? ''), 'col-md-3');
            record_details_field('TPL Code', $tplCode, 'col-md-3');
            record_details_field('TPL Description', $tplDesc, 'col-md-3');
            record_details_field('Valid', $validBadge, 'col-md-3', false, true);
            record_details_section_end();

            record_details_section_start(2, 'Pricing & Commercial Flags', 'Price and commercial indicators');
            record_details_field('Dealer Price', product_display_value($record['dealer_price'] ?? ''), 'col-md-3');
            record_details_field('COS (Price)', product_format_cos($record['cos'] ?? ''), 'col-md-3');
            record_details_field('TOD Flag', product_yn_badge((string) ($record['tod_flag'] ?? '')), 'col-md-3', false, true);
            record_details_field('Excisable', product_yn_badge((string) ($record['excisable'] ?? '')), 'col-md-3', false, true);
            record_details_section_end();

            record_details_section_start(3, 'Cost Components', 'Material, variable, and fixed cost values');
            record_details_field('MC', product_display_value($record['mc'] ?? ''), 'col-md-4');
            record_details_field('VC', product_display_value($record['vc'] ?? ''), 'col-md-4');
            record_details_field('FC', product_display_value($record['fc'] ?? ''), 'col-md-4');
            record_details_section_end();

            record_details_section_start(4, 'Organization', 'Company, warehouse, and payment details');
            record_details_field('Company', product_display_value($record['company'] ?? ''), 'col-md-4');
            record_details_field('Warehouse', product_display_value($record['warehouse'] ?? ''), 'col-md-4');
            record_details_field('Payment Term', product_display_value($record['payment_term'] ?? ''), 'col-md-4');
            record_details_section_end();

            record_details_section_start(5, 'Audit Trail', 'Creation and last update history', true);
            record_details_field(
                'Created By',
                product_user_label(
                    $record['created_by_name'] ?? null,
                    $record['created_by_username'] ?? null,
                    $record['created_by'] ?? null
                ),
                'col-md-3'
            );
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), 'col-md-3');
            record_details_field(
                'Updated By',
                product_user_label(
                    $record['updated_by_name'] ?? null,
                    $record['updated_by_username'] ?? null,
                    $record['updated_by'] ?? null
                ),
                'col-md-3'
            );
            record_details_field(
                'Updated At',
                rbac_format_datetime($record['updated_at'] ?? ($record['updated_at'] ?? null)),
                'col-md-3'
            );
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>
