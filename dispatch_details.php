<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - Dispatch Details</title>

    <?php include('header_css.php'); ?>

    <link href="css/order_acknowledge_style.css" rel="stylesheet" />
    
    <link href="css/dispatch_details.css" rel="stylesheet" />

</head>

<body>


    <div class="main-wrapper" id="mainWrapper">
        <!-- TOPBAR -->

        <div class="topbar">

            <div class="topbar-left">
                <i class="bi bi-list toggle-btn" id="menuToggle"></i>
                Dispatch Details
            </div>
            <?php include('topbar.php'); ?>
        </div>
        <!-- SIDEBAR -->

        <?php include('sidebar.php'); ?>
        <div class="content">

            <!-- STATS -->

            <div class="stats-grid dispatch-grid">

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Dispatched This Month
                            </div>

                            <div class="card-value">
                                7
                            </div>

                            <div class="card-sub green-text">
                                ↑ 8% vs last month
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-truck"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                In Transit
                            </div>

                            <div class="card-value">
                                2
                            </div>

                            <div class="card-sub gray">
                                Currently moving
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-geo-alt"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Delivered
                            </div>

                            <div class="card-value">
                                5
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-check2-circle"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Avg. Delivery Time
                            </div>

                            <div class="card-value">
                                3.2 days
                            </div>

                            <div class="card-sub green-text">
                                ↑ 0.5 days faster
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-clock-history"></i>

                        </div>

                    </div>

                </div>

            </div>

            <!-- TABLE -->

            <div class="booking-card">

                <!-- HEADER -->

                <div class="booking-header">

                    <div class="booking-title">
                        Dispatch Records
                    </div>

                    <div class="booking-actions">

                        <!-- SEARCH -->

                        <div class="search-box">

                            <i class="bi bi-search"></i>

                            <input type="text"
                                placeholder="Search dispatch/order...">

                        </div>

                        <!-- STATUS -->

                        <select class="filter-select">

                            <option>All Status</option>
                            <option>In Transit</option>
                            <option>Delivered</option>

                        </select>

                        <!-- FILTER -->

                        <select class="filter-select small-filter">

                            <option>All...</option>

                        </select>

                        <!-- EXPORT -->

                        <button class="export-btn">

                            <i class="bi bi-download"></i>

                            Export

                        </button>

                    </div>

                </div>

                <!-- TABLE -->

                <div class="table-responsive">

                    <table class="booking-table">

                        <thead>

                            <tr>

                                <th>Dispatch ID</th>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Value</th>
                                <th>Warehouse</th>
                                <th>Destination</th>
                                <th>Transporter</th>
                                <th>ETA</th>
                                <th>Status</th>
                                <th></th>

                            </tr>

                        </thead>

                        <tbody>

                            <!-- ROW -->

                            <tr>

                                <td class="fw-semibold">
                                    DSP-4521
                                </td>

                                <td>

                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1838

                                    </a>

                                </td>

                                <td>13 Apr 2024</td>

                                <td>8</td>

                                <td class="fw-semibold">
                                    ₹78,500
                                </td>

                                <td>Mumbai</td>

                                <td>Pune</td>

                                <td class="transport">
                                    Blue Dart
                                </td>

                                <td>16 Apr 2024</td>

                                <td>

                                    <span class="dispatch-badge transit-badge">

                                        <i class="bi bi-truck"></i>

                                        In Transit

                                    </span>

                                </td>

                                <td>

                                    <button class="view-btn">

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </td>

                            </tr>

                            <!-- ROW -->

                            <tr>

                                <td class="fw-semibold">
                                    DSP-4518
                                </td>

                                <td>

                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1832

                                    </a>

                                </td>

                                <td>12 Apr 2024</td>

                                <td>3</td>

                                <td class="fw-semibold">
                                    ₹1,45,000
                                </td>

                                <td>Delhi</td>

                                <td>Jaipur</td>

                                <td class="transport">
                                    Delhivery
                                </td>

                                <td>17 Apr 2024</td>

                                <td>

                                    <span class="dispatch-badge transit-badge">

                                        <i class="bi bi-truck"></i>

                                        In Transit

                                    </span>

                                </td>

                                <td>

                                    <button class="view-btn">

                                        <i class="bi bi-eye"></i>

                                    </button>

                                </td>

                            </tr>

                            <!-- ROW -->

                            <tr>

                                <td class="fw-semibold">
                                    DSP-4515
                                </td>

                                <td>

                                    <a href="#"
                                        class="order-link">

                                        ORD-2024-1826

                                    </a>

                                </td>

                                <td>11 Apr 2024</td>

                                <td>5</td>

                                <td class="fw-semibold">
                                    ₹2,30,000
                                </td>

                                <td>Chennai</td>

                                <td>Bangalore</td>

                                <td class="transport">
                                    Gati
                                </td>

                                <td>15 Apr 2024</td>

                                <td>

                                    <span class="dispatch-badge delivered-badge">

                                        Delivered

                                    </span>

                                </td>

                                <td>

                                    <button class="view-btn">

                                        <i class="bi bi-eye"></i>

                                    </button>

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