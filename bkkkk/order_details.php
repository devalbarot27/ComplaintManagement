<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Order Details</title>

    <?php include('header_css.php'); ?>

    <link href="css/order_details.css" rel="stylesheet" />
    <link href="css/table_style.css" rel="stylesheet" />

</head>

<body>

    <!-- SIDEBAR -->

    <?php include('sidebar.php'); ?>

    <div class="main-wrapper"
        id="mainWrapper">

        <!-- TOPBAR -->

        <div class="topbar">

            <div class="topbar-left">

                <i class="bi bi-list toggle-btn"
                    id="menuToggle"></i>

                Order Details

            </div>

            <?php include('topbar.php'); ?>

        </div>

        <!-- CONTENT -->

        <div class="content">

            <!-- PAGE HEADER -->

            <div class="details-header">

                <!-- LEFT -->

                <div class="details-left">

                    <a href="order_acknowledgement.php"
                        class="back-btn">

                        <i class="bi bi-arrow-left"></i>

                    </a>

                    <div>

                        <div class="order-main-title">
                            ORD-2024-1842
                        </div>

                        <div class="order-subtitle">
                            LN Ref: LN-88421
                        </div>

                    </div>

                </div>

                <!-- RIGHT -->

                <div class="details-right">

                    <span class="status-badge approved">
                        Approved
                    </span>

                    <span class="type-badge">
                        Special Price
                    </span>

                </div>

            </div>

            <!-- LIFECYCLE -->

            <div class="details-card">

                <div class="section-title">
                    Order Lifecycle
                </div>

                <div class="lifecycle-wrapper">

                    <!-- STEP -->

                    <div class="lifecycle-step active">

                        <div class="circle">

                            <i class="bi bi-check-lg"></i>

                        </div>

                        <div class="step-title">
                            Created
                        </div>

                        <div class="step-time">
                            11 Apr 2024, 09:15 AM
                        </div>

                    </div>

                    <div class="line active-line"></div>

                    <!-- STEP -->

                    <div class="lifecycle-step active">

                        <div class="circle">

                            <i class="bi bi-check-lg"></i>

                        </div>

                        <div class="step-title">
                            Approved
                        </div>

                        <div class="step-time">
                            11 Apr 2024, 03:45 PM
                        </div>

                    </div>

                    <div class="line active-line"></div>

                    <!-- STEP -->

                    <div class="lifecycle-step">

                        <div class="circle"></div>

                        <div class="step-title inactive">
                            Acknowledged
                        </div>

                    </div>

                    <div class="line"></div>

                    <!-- STEP -->

                    <div class="lifecycle-step">

                        <div class="circle"></div>

                        <div class="step-title inactive">
                            Pending
                        </div>

                    </div>

                    <div class="line"></div>

                    <!-- STEP -->

                    <div class="lifecycle-step">

                        <div class="circle"></div>

                        <div class="step-title inactive">
                            Dispatched
                        </div>

                    </div>

                </div>

            </div>

            <!-- INFO GRID -->

            <div class="info-grid">

                <!-- ORDER INFO -->

                <div class="details-card">

                    <div class="section-title">

                        <i class="bi bi-box-seam"></i>

                        Order Info

                    </div>

                    <div class="info-list">

                        <div class="info-row">

                            <span>Customer</span>

                            <strong>Patel Industries</strong>

                        </div>

                        <div class="info-row">

                            <span>Date</span>

                            <strong>11 Apr 2024</strong>

                        </div>

                        <div class="info-row">

                            <span>Type</span>

                            <strong>Special Price</strong>

                        </div>

                        <div class="info-row">

                            <span>Total Value</span>

                            <strong>₹13,20,000</strong>

                        </div>

                    </div>

                    <div class="address-box">

                        <div class="address-title">
                            Delivery Address
                        </div>

                        <div class="address-text">

                            Sector 12, GIDC, Ahmedabad,
                            Gujarat - 382445

                        </div>

                    </div>

                </div>

                <!-- DISPATCH -->

                <div class="details-card">

                    <div class="section-title">

                        <i class="bi bi-truck"></i>

                        Dispatch Info

                    </div>

                    <div class="empty-box">

                        <i class="bi bi-clock-history"></i>

                        <div>
                            Not yet dispatched
                        </div>

                    </div>

                </div>

                <!-- LR -->

                <div class="details-card">

                    <div class="section-title">

                        <i class="bi bi-file-earmark-text"></i>

                        LR Details

                    </div>

                    <div class="empty-box">

                        <i class="bi bi-file-earmark"></i>

                        <div>
                            No LR generated yet
                        </div>

                    </div>

                </div>

            </div>

            <!-- LINE ITEMS -->

            <div class="details-card">

                <div class="section-title">
                    Line Items
                </div>

                <div class="table-responsive">

                    <table class="booking-table">

                        <thead>

                            <tr>

                                <th>Product Code</th>

                                <th>Product Name</th>

                                <th>Qty</th>

                                <th>Unit Price</th>

                                <th>Total</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td>PRD-002</td>

                                <td>Steel Rod 16mm</td>

                                <td>200</td>

                                <td>₹4,800</td>

                                <td class="fw-semibold">
                                    ₹9,60,000
                                </td>

                            </tr>

                            <tr>

                                <td>PRD-006</td>

                                <td>MS Channel 75mm</td>

                                <td>50</td>

                                <td>₹7,200</td>

                                <td class="fw-semibold">
                                    ₹3,60,000
                                </td>

                            </tr>

                            <!-- TOTAL -->

                            <tr>

                                <td colspan="4"
                                    class="text-end fw-semibold">

                                    Order Total

                                </td>

                                <td class="grand-total">

                                    ₹13,20,000

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</body>

</html>