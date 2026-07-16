<?php
session_start();

require_once __DIR__ . '/pdo_obconn.php';
require_once __DIR__ . '/includes/pending_order_invoice_helpers.php';

$ordno = trim($_GET['ordno'] ?? '501003371');
$invoice = pending_invoice_fetch($dpconn, $ordno);
$errorMessage = $invoice === null ? 'Order not found in pending orders.' : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Order Invoice</title>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <?php include 'header_css.php'; ?>
    <style>
        .invoice-page-card {
            max-width: 760px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }

        .invoice-page-header {
            margin-bottom: 8px;
        }

        .invoice-page-logo {
            width: 72px;
            height: auto;
            display: block;
            margin-bottom: 12px;
        }

        .invoice-page-title {
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .invoice-page-brand-line {
            font-size: 15px;
            font-weight: 500;
            color: #64748b;
        }

        .invoice-page-subtitle {
            color: #64748b;
            margin-bottom: 24px;
        }

        .invoice-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }

        .invoice-summary-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
        }

        .invoice-summary-label {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .invoice-summary-value {
            font-size: 15px;
            font-weight: 600;
            color: #0f172a;
        }

        .invoice-download-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            height: 42px;
            padding: 0 18px;
            border: none;
            border-radius: 8px;
            background: #F44611;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .invoice-download-btn:hover {
            background: #F44611;
            color: #fff;
        }

        .invoice-download-btn.disabled {
            pointer-events: none;
            opacity: 0.6;
        }
    </style>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <div class="invoice-page-card">
                <div class="invoice-page-header">
                    <img src="uploads/vayu.png" alt="Vayu Logo" class="invoice-page-logo">
                    <div>
                        <div class="invoice-page-title">VAYU COMPRESSORS</div>
                        <div class="invoice-page-brand-line">Pending Order Invoice</div>
                    </div>
                </div>
                <div class="invoice-page-subtitle">
                    Generate a PDF invoice for a single pending order using order and customer master data.
                </div>

                <?php if ($errorMessage !== '') { ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                <?php } else { ?>
                    <div class="invoice-summary-grid">
                        <div class="invoice-summary-item">
                            <div class="invoice-summary-label">Order No</div>
                            <div class="invoice-summary-value"><?php echo htmlspecialchars($invoice['ordno']); ?></div>
                        </div>
                        <div class="invoice-summary-item">
                            <div class="invoice-summary-label">Order Date</div>
                            <div class="invoice-summary-value"><?php echo htmlspecialchars($invoice['orddt']); ?></div>
                        </div>
                        <div class="invoice-summary-item">
                            <div class="invoice-summary-label">Customer</div>
                            <div class="invoice-summary-value"><?php echo htmlspecialchars($invoice['customer']['name']); ?></div>
                        </div>
                        <div class="invoice-summary-item">
                            <div class="invoice-summary-label">Grand Total</div>
                            <div class="invoice-summary-value"><?php echo pending_invoice_format_money_html($invoice['grand_total']); ?></div>
                        </div>
                    </div>

                    <a
                        href="generate_pending_order_invoice.php?ordno=<?php echo urlencode($invoice['ordno']); ?>"
                        class="invoice-download-btn">
                        <i class="bi bi-file-earmark-pdf"></i>
                        Download Invoice PDF
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</body>

</html>