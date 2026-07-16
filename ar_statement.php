<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Dealer - AR Statement</title>

    <?php include('header_css.php'); ?>

    <link href="css/order_acknowledge_style.css" rel="stylesheet" />

    <link href="css/ar_statement.css" rel="stylesheet" />

</head>

<body>


    <div class="main-wrapper" id="mainWrapper">
        <!-- TOPBAR -->

        <div class="topbar">

            <div class="topbar-left">
                <i class="bi bi-list toggle-btn" id="menuToggle"></i>
                AR Statement
            </div>
            <?php include('topbar.php'); ?>
        </div>
        <!-- SIDEBAR -->

        <?php include('sidebar.php'); ?>
        <div class="content">

            <!-- PAGE HEADER -->

            <div class="page-header">

                <div class="page-title-section">

                    <div class="page-title">
                        AR Statement
                    </div>

                </div>

            </div>

            <!-- STATS -->

            <div class="stats-grid ar-grid">

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Outstanding Balance
                            </div>

                            <div class="card-value">
                                ₹42.3L
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-credit-card"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Credit Limit
                            </div>

                            <div class="card-value">
                                ₹62.0L
                            </div>

                            <div class="card-sub gray">
                                90-day credit terms
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
                                Overdue Amount
                            </div>

                            <div class="card-value">
                                ₹4.5L
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-exclamation-triangle"></i>

                        </div>

                    </div>

                </div>

                <!-- CARD -->

                <div class="stat-card">

                    <div class="card-top">

                        <div>

                            <div class="card-title">
                                Avg. Payment Days
                            </div>

                            <div class="card-value">
                                72
                            </div>

                            <div class="card-sub green-text">
                                ↑ 3 days faster
                            </div>

                        </div>

                        <div class="icon-box">

                            <i class="bi bi-clock-history"></i>

                        </div>

                    </div>

                </div>

            </div>

            <!-- CREDIT UTILIZATION -->

            <div class="utilization-card">

                <div class="utilization-title">
                    Credit Utilization
                </div>

                <div class="utilization-top">

                    <div class="utilization-text">

                        Used: ₹42.3L of ₹62.0L

                    </div>

                    <div class="utilization-percent">
                        68%
                    </div>

                </div>

                <div class="utilization-bar">

                    <div class="utilization-fill"></div>

                </div>

                <div class="utilization-bottom">

                    <div class="available-credit">
                        Available credit ₹19.7L
                    </div>

                    <div class="moderate-text">
                        Moderate utilization
                    </div>

                </div>

            </div>

            <!-- ANALYSIS -->

            <div class="analysis-grid">

                <!-- CHART -->

                <div class="analysis-card">

                    <div class="analysis-title">

                        <i class="bi bi-graph-up-arrow"></i>

                        Aging Analysis (₹ in thousands)

                    </div>

                    <div class="chart-wrapper">

                        <canvas id="agingChart"></canvas>

                    </div>

                </div>

                <!-- SUMMARY -->

                <div class="analysis-card">

                    <div class="analysis-title">
                        Payment Summary
                    </div>

                    <!-- BOX -->

                    <div class="summary-box">

                        <div>

                            <div class="summary-label">
                                Total Invoiced (This Quarter)
                            </div>

                            <div class="summary-value">
                                ₹7.50L
                            </div>

                        </div>

                        <span class="summary-badge">
                            Q1 2024
                        </span>

                    </div>

                    <!-- BOX -->

                    <div class="summary-box">

                        <div>

                            <div class="summary-label">
                                Total Payments Received
                            </div>

                            <div class="summary-value">
                                ₹4.65L
                            </div>

                        </div>

                        <span class="summary-badge blue-badge">
                            62%
                        </span>

                    </div>

                    <!-- BOX -->

                    <div class="summary-box danger-box">

                        <div>

                            <div class="summary-label">
                                Overdue Invoices
                            </div>

                            <div class="summary-value red-text">
                                ₹4.50L
                            </div>

                        </div>

                        <span class="summary-badge red-badge">
                            3 invoices
                        </span>

                    </div>

                </div>

            </div>

            <!-- LEDGER -->

            <div class="booking-card">

                <!-- HEADER -->

                <div class="booking-header">

                    <div class="booking-title">
                        Account Ledger
                    </div>

                    <div class="booking-actions">

                        <!-- SEARCH -->

                        <div class="search-box">

                            <i class="bi bi-search"></i>

                            <input type="text"
                                placeholder="Search transactions...">

                        </div>

                        <!-- FILTER -->

                        <select class="filter-select">

                            <option>All Types</option>

                            <option>Invoice</option>

                            <option>Payment</option>

                            <option>Credit Note</option>

                        </select>

                        <!-- DOWNLOAD -->

                        <button class="download-btn">

                            <i class="bi bi-download"></i>

                            Download Statement

                        </button>

                    </div>

                </div>

                <!-- TABLE -->

                <div class="table-responsive">

                    <table class="booking-table">

                        <thead>

                            <tr>

                                <th>Ref</th>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Balance</th>
                                <th>Due Date</th>

                            </tr>

                        </thead>

                        <tbody>

                            <tr>

                                <td class="fw-semibold">
                                    INV-8821
                                </td>

                                <td>01 Mar 2024</td>

                                <td>Invoice - ORD-2024-1750</td>

                                <td>

                                    <span class="ledger-badge">
                                        Invoice
                                    </span>

                                </td>

                                <td class="debit-text">
                                    ₹2,50,000
                                </td>

                                <td>-</td>

                                <td class="fw-semibold">
                                    ₹42,30,000
                                </td>

                                <td class="debit-text">
                                    30 May 2024
                                </td>

                            </tr>

                            <tr>

                                <td class="fw-semibold">
                                    PMT-3321
                                </td>

                                <td>05 Mar 2024</td>

                                <td>Payment Received - NEFT</td>

                                <td>

                                    <span class="ledger-badge">
                                        Payment
                                    </span>

                                </td>

                                <td>-</td>

                                <td class="credit-text">
                                    ₹1,80,000
                                </td>

                                <td class="fw-semibold">
                                    ₹40,50,000
                                </td>

                                <td>—</td>

                            </tr>

                            <tr>

                                <td class="fw-semibold">
                                    INV-8835
                                </td>

                                <td>10 Mar 2024</td>

                                <td>Invoice - ORD-2024-1780</td>

                                <td>

                                    <span class="ledger-badge">
                                        Invoice
                                    </span>

                                </td>

                                <td class="debit-text">
                                    ₹1,45,000
                                </td>

                                <td>-</td>

                                <td class="fw-semibold">
                                    ₹41,95,000
                                </td>

                                <td class="debit-text">
                                    08 Jun 2024
                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

        <script>
            const ctx = document.getElementById('agingChart');

            new Chart(ctx, {

                type: 'bar',

                data: {

                    labels: [
                        'Current',
                        '1-30 days',
                        '31-60 days',
                        '61-90 days',
                        '90+ days'
                    ],

                    datasets: [{

                        data: [320, 150, 250, 90, 50],

                        backgroundColor: '#1565d8',

                        borderRadius: 4

                    }]

                },

                options: {

                    responsive: true,

                    maintainAspectRatio: false,

                    plugins: {
                        legend: {
                            display: false
                        }
                    },

                    scales: {

                        x: {
                            grid: {
                                color: '#e2e8f0',
                                borderDash: [3, 3]
                            }
                        },

                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0',
                                borderDash: [3, 3]
                            }
                        }

                    }

                }

            });
        </script>
    </div>
</body>

</html>