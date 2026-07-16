<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Check assigned permission
include('pdo_obconn.php');
require_once __DIR__ . '/includes/admin_access_helpers.php';
require_once __DIR__ . '/includes/rbac_access_helpers.php';
require_once __DIR__ . '/includes/dashboard_helpers.php';
$dashboardModule = 'dashboard';
$canViewDashboard = rbac_user_can($obconn, $dashboardModule, 'view');
if (empty($_SESSION['usr_name'])) {
    header('Location: login.php');
    exit;
}

admin_ensure_session_role($obconn);

if (!rbac_user_can($obconn, $dashboardModule, 'view')) {
    header('Location: access_denied.php');
    exit;
}
//end



$userName = $_SESSION['usr_name'] ?? '0';
$dashboardStats = dashboard_fetch_stats($dpconn, $obconn, $_GET['period'] ?? null);

$selectedPeriod = $dashboardStats['selected_period'];
$selectedPeriodLabel = $dashboardStats['selected_period_label'];
$periodOptions = $dashboardStats['period_options'];


$totalRecentOrdersCount = $dashboardStats['total_recent_orders_count'];
$pendingOrdersCount = $dashboardStats['pending_orders_count'];
$acknowledgementCount = $dashboardStats['acknowledgement_count'];
$pendingOver10DaysCount = $dashboardStats['pending_over_10_days_count'];
$pendingOver10DaysAlert = dashboard_format_pending_over_10_days_alert($pendingOver10DaysCount);
$dispatched_orders_count = $dashboardStats['dispatched_orders_count'];
$dispatchesDeliveredThisWeekAlert = dashboard_format_dispatches_delivered_this_week_alert(
    $dashboardStats['dispatches_delivered_this_week_count']
);

$orderModulePermissions = dashboard_order_module_permissions($obconn);
$canViewCreatedOrders = $orderModulePermissions['created'];
$canViewRecentOrders = $orderModulePermissions['recent'];
$canViewAcknowledgedOrders = $orderModulePermissions['acknowledged'];
$canViewPendingOrders = $orderModulePermissions['pending'];
$canViewDispatchedOrders = $orderModulePermissions['dispatched'];
$canViewComplaintView = $orderModulePermissions['complaint-view'];
$canViewAnyOrderCards = $canViewCreatedOrders
|| $canViewRecentOrders
|| $canViewAcknowledgedOrders
    || $canViewPendingOrders
    || $canViewDispatchedOrders;

$totalOrdersCount = 0;
if ($canViewRecentOrders) {
    $totalOrdersCount += $totalRecentOrdersCount;
}
if ($canViewAcknowledgedOrders) {
    $totalOrdersCount += $acknowledgementCount;
}
if ($canViewPendingOrders) {
    $totalOrdersCount += $pendingOrdersCount;
}
if ($canViewDispatchedOrders) {
    $totalOrdersCount += $dispatched_orders_count;
}

$monthlyChartData = $dashboardStats['monthly_chart'];
$monthlyChartMaxValues = [];
if ($canViewAcknowledgedOrders) {
    $monthlyChartMaxValues = array_merge($monthlyChartMaxValues, $monthlyChartData['acknowledged']);
}
if ($canViewPendingOrders) {
    $monthlyChartMaxValues = array_merge($monthlyChartMaxValues, $monthlyChartData['pending']);
}
$monthlyChartMax = max(4, (int) (ceil((max($monthlyChartMaxValues ?: [0]) / 4)) * 4));

$statusChartData = [];
$statusChartColors = [];
if ($canViewRecentOrders) {
    $statusChartData[] = (int) $totalRecentOrdersCount;
    $statusChartColors[] = '#2563eb';
}
if ($canViewAcknowledgedOrders) {
    $statusChartData[] = (int) $acknowledgementCount;
    $statusChartColors[] = '#7c3aed';
}
if ($canViewPendingOrders) {
    $statusChartData[] = (int) $pendingOrdersCount;
    $statusChartColors[] = '#f59e0b';
}
if ($canViewDispatchedOrders) {
    $statusChartData[] = (int) $dispatched_orders_count;
    $statusChartColors[] = '#16a34a';
}

$monthlyChartDatasets = [];
if ($canViewAcknowledgedOrders) {
    $monthlyChartDatasets[] = [
        'label' => 'Acknowledged',
        'data' => $monthlyChartData['acknowledged'],
        'backgroundColor' => '#7c3aed',
        'borderRadius' => 3,
        'borderSkipped' => false,
        'categoryPercentage' => 0.72,
        'barPercentage' => 0.88,
    ];
}
if ($canViewPendingOrders) {
    $monthlyChartDatasets[] = [
        'label' => 'Pending',
        'data' => $monthlyChartData['pending'],
        'backgroundColor' => '#f59e0b',
        'borderRadius' => 3,
        'borderSkipped' => false,
        'categoryPercentage' => 0.72,
        'barPercentage' => 0.88,
    ];
}

$showPendingOver10DaysAlert = $canViewPendingOrders && $pendingOver10DaysCount > 0;
$showDispatchesDeliveredAlert = $canViewDispatchedOrders;
$showAlertGrid = $showPendingOver10DaysAlert || $showDispatchesDeliveredAlert;


?>

<!-- CONTENT -->
<div class="content">

    <!-- FILTER -->

    <div class="filter-row">
    <?php if ($canViewAnyOrderCards): ?>
        <div class="period-filter" id="dashboardPeriodFilter">

            <button
                type="button"
                class="period-filter__trigger"
                id="dashboardPeriodTrigger"
                aria-haspopup="listbox"
                aria-expanded="false">

                <span class="period-filter__trigger-main">

                    <span class="period-filter__icon">
                        <i class="bi bi-calendar2"></i>
                    </span>

                    <span class="period-filter__label" id="dashboardPeriodLabel">
                        <?php echo htmlspecialchars($selectedPeriodLabel); ?>
                    </span>

                </span>

                <i class="bi bi-chevron-down period-filter__chevron"></i>

            </button>

            <div class="period-filter__menu" id="dashboardPeriodMenu" role="listbox">
                <?php foreach ($periodOptions as $periodValue => $periodLabel): ?>
                    <button
                        type="button"
                        class="period-filter__option<?php echo $periodValue === $selectedPeriod ? ' is-active' : ''; ?>"
                        role="option"
                        data-period="<?php echo htmlspecialchars($periodValue); ?>"
                        aria-selected="<?php echo $periodValue === $selectedPeriod ? 'true' : 'false'; ?>">
                        <span><?php echo htmlspecialchars($periodLabel); ?></span>
                        <i class="bi bi-check2 period-filter__check"></i>
                    </button>
                <?php endforeach; ?>
            </div>

        </div>
      

        <div class="action-group">

            <!-- <button class="action-btn" type="button">
                <i class="bi bi-download"></i>
                Export
            </button> -->

            <button class="action-btn" type="button" id="dashboardRefresh">

                <i class="bi bi-arrow-clockwise"></i>

                Refresh

            </button>

        </div>
        <?php endif; ?>

    </div>

    <!-- ALERTS -->

    <?php if ($showAlertGrid): ?>
    <div class="alert-grid">

        <?php if ($showPendingOver10DaysAlert): ?>
        <div class="custom-alert alert-orange">
            <?php echo htmlspecialchars($pendingOver10DaysAlert); ?>
        </div>
        <?php endif; ?>

        <div class="custom-alert alert-blue d-none">
            Credit utilization at 68%   ?19.7L available
        </div>

        <?php if ($showDispatchesDeliveredAlert): ?>
        <div class="custom-alert alert-green">
            <?php echo htmlspecialchars($dispatchesDeliveredThisWeekAlert); ?>
        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>

    <!-- STATS -->

    <?php if ($canViewAnyOrderCards): ?>
    <div class="stats-grid">

        <div class="stat-card">

            <div class="card-top">

                <div>

                    <div class="card-title">
                        Total Orders
                    </div>

                    <div class="card-value">
                    <?php echo htmlspecialchars((string) $totalOrdersCount); ?>
                    </div>

                </div>

                <div class="icon-box">
                    <i class="bi bi-cart"></i>
                </div>

            </div>

        </div>

        <?php if ($canViewPendingOrders): ?>
        <div class="stat-card">

            <div class="card-top">

                <div>

                    <div class="card-title">
                        Pending Orders
                    </div>

                    <div class="card-value">
                        <?php echo htmlspecialchars((string) $pendingOrdersCount); ?>
                    </div>

                </div>

                <div class="icon-box">
                    <i class="bi bi-clock-history"></i>
                </div>

            </div>

        </div>
        <?php endif; ?>

        <?php if ($canViewDispatchedOrders): ?>
        <div class="stat-card">

            <div class="card-top">

                <div>

                    <div class="card-title">
                        Dispatched This Month
                    </div>

                    <div class="card-value">
                        <?php echo htmlspecialchars((string) $dispatched_orders_count); ?>
                    </div>

                </div>

                <div class="icon-box">
                    <i class="bi bi-truck"></i>
                </div>

            </div>

        </div>
        <?php endif; ?>

        <div class="stat-card d-none">

            <div class="card-top">

                <div>

                    <div class="card-title">
                        Outstanding Balance
                    </div>

                    <div class="card-value">
                        ?42.3L
                    </div>

                    <div class="card-sub gray">
                        Credit: 90 days
                    </div>

                </div>

                <div class="icon-box">
                    <i class="bi bi-credit-card"></i>
                </div>

            </div>

        </div>

    </div>
    <?php endif; ?>

    <!-- PIPELINE -->

    <?php if ($canViewAnyOrderCards): ?>
    <div class="pipeline-card">

        <div class="pipeline-header">

            <div class="pipeline-title">
                Order Pipeline
            </div>

            <div class="pipeline-total">
                Total: <?php echo htmlspecialchars((string) $totalOrdersCount); ?> orders
            </div>

        </div>

        <div class="pipeline-wrapper">

            <?php if ($canViewRecentOrders): ?>
            <div class="pipeline-step">
                <div class="pipeline-count blue"><?php echo htmlspecialchars((string) $totalRecentOrdersCount); ?></div>
                <div class="pipeline-label">Created</div>
            </div>
            <?php endif; ?>

            <?php if ($canViewAcknowledgedOrders): ?>
            <div class="pipeline-step">
                <div class="pipeline-count purple"><?php echo htmlspecialchars((string) $acknowledgementCount); ?></div>
                <div class="pipeline-label">Acknowledged</div>
            </div>
            <?php endif; ?>

            <?php if ($canViewPendingOrders): ?>
            <div class="pipeline-step">
                <div class="pipeline-count orange"><?php echo htmlspecialchars((string) $pendingOrdersCount); ?></div>
                <div class="pipeline-label">Pending</div>
            </div>
            <?php endif; ?>

            <?php if ($canViewDispatchedOrders): ?>
            <div class="pipeline-step">
                <div class="pipeline-count green"><?php echo htmlspecialchars((string) $dispatched_orders_count); ?></div>
                <div class="pipeline-label">Dispatched</div>
            </div>
            <?php endif; ?>

        </div>

    </div>
    <?php endif; ?>

    <!-- CHARTS -->

    <?php if (!empty($monthlyChartDatasets) || !empty($statusChartData)): ?>
    <div class="chart-grid">

        <?php if (!empty($monthlyChartDatasets)): ?>
        <div class="chart-card">

            <div class="chart-title">

                <i class="bi bi-graph-up-arrow"></i>

                Monthly Orders

            </div>

            <div class="chart-wrapper">

                <canvas id="monthlyChart"></canvas>

            </div>

        </div>
        <?php endif; ?>

        <?php if (!empty($statusChartData)): ?>
        <div class="chart-card">

            <div class="chart-title">
                Status Distribution
            </div>

            <div class="d-flex justify-content-center">

                <canvas id="statusChart"></canvas>

            </div>

            <div class="status-legend">

                <?php if ($canViewRecentOrders): ?>
                <div class="legend-item">
                    <div class="legend-dot"
                        style="background:#2563eb"></div>
                    Created: <?php echo htmlspecialchars((string) $totalRecentOrdersCount); ?>
                </div>
                <?php endif; ?>

                <?php if ($canViewAcknowledgedOrders): ?>
                <div class="legend-item">
                    <div class="legend-dot"
                        style="background:#7c3aed"></div>
                    Acknowledged: <?php echo htmlspecialchars((string) $acknowledgementCount); ?>
                </div>
                <?php endif; ?>

                <?php if ($canViewPendingOrders): ?>
                <div class="legend-item">
                    <div class="legend-dot"
                        style="background:#f59e0b"></div>
                    Pending: <?php echo htmlspecialchars((string) $pendingOrdersCount); ?>
                </div>
                <?php endif; ?>

                <?php if ($canViewDispatchedOrders): ?>
                <div class="legend-item">
                    <div class="legend-dot"
                        style="background:#16a34a"></div>
                    Dispatched: <?php echo htmlspecialchars((string) $dispatched_orders_count); ?>
                </div>
                <?php endif; ?>

            </div>

        </div>
        <?php endif; ?>

    </div>
    <?php endif; ?>





    <!-- RECENT ORDERS -->

    <?php if ($canViewRecentOrders): ?>
    <div class="recent-card">

        <div class="recent-header">

            <div class="recent-title">
                Recent Orders
            </div>

            <a href="recent_orders.php"
                class="view-all">

                View All

                <i class="bi bi-arrow-right"></i>

            </a>

        </div>

          <div class="table-responsive mb-3">

 <table id="orderTable" class="table table-hover align-middle w-100 recent-table">
                                    <thead>
                                        <tr>
                                            <th>Ref No</th>
                                            <th>AO Number</th>
                                            <th>Category</th>
                                            <th>Delivery Term</th>
                                            <th>PO Number</th>
                                            <th>Payment Term</th>
                                            <th>Transporter</th>
                                            <th>Order Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                </table>


            

        </div>

    </div>
    <?php endif; ?>

    <!-- QUICK ACTIONS -->

    <div class="quick-card">

        <div class="quick-title">
            Quick Actions
        </div>

        <div class="quick-grid">

        <?php if ($canViewCreatedOrders): ?>
            <a href="orderbooking.php" style="text-decoration: none;">
                <div class="quick-item">    
                    <div class="quick-icon blue-icon">
                    <i class="bi bi-cart"></i>
                    </div>
                    <div class="quick-text">
                        New Order
                    </div>              
                </div>
            </a>
            <?php endif; ?>
            <?php if ($canViewPendingOrders): ?>
            <a href="pending_order.php" style="text-decoration: none;">
            <div class="quick-item">

                <div class="quick-icon orange-icon">
                    <i class="bi bi-clock-history"></i>
                </div>

                <div class="quick-text">
                    Pending Orders
                </div>

            </div>
            </a>
            <?php endif; ?>
            <?php if ($canViewComplaintView): ?>
            <a href="new_complaint.php" style="text-decoration: none;">
            <div class="quick-item">

                <div class="quick-icon red-icon">
                    <i class="bi bi-credit-card"></i>
                </div>

                <div class="quick-text">
                    File Complaint
                </div>

            </div>
            </a>    
            <?php endif; ?>
            <?php /*
            <a href="ar_statement.php" style="text-decoration: none;">
            <div class="quick-item">

                <div class="quick-icon green-icon">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>

                <div class="quick-text">
                    AR Statement
                </div>

            </div>
            </a>
            */ ?>

        </div>

    </div>
</div>



 <!-- MODAL -->
    <div class="modal fade" id="lineModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">

                    <h5 class="page-subtitle mb-0" id="lineModalLabel">
                        Recent Order List
                    </h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary add-item-btn" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>




<style>
    .period-filter {
        position: relative;
        display: inline-block;
    }

    .period-filter__trigger {
        height: 38px;
        min-width: 210px;
        border: 1px solid #d8dee8;
        background: #fff;
        border-radius: 10px;
        padding: 0 12px 0 14px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 13px;
        color: #0f172a;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .period-filter__trigger:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
    }

    .period-filter.is-open .period-filter__trigger {
        border-color: #93c5fd;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .period-filter__trigger-main {
        display: flex;
        align-items: center;
        gap: 10px;
        min-width: 0;
    }

    .period-filter__icon {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: #eff6ff;
        color: #2563eb;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        flex-shrink: 0;
    }

    .period-filter__label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .period-filter__chevron {
        color: #64748b;
        font-size: 12px;
        transition: transform 0.2s ease;
        flex-shrink: 0;
    }

    .period-filter.is-open .period-filter__chevron {
        transform: rotate(180deg);
    }

    .period-filter__menu {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        z-index: 30;
        min-width: 220px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 6px;
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        display: none;
    }

    .period-filter.is-open .period-filter__menu {
        display: block;
    }

    .period-filter__option {
        width: 100%;
        border: none;
        background: transparent;
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #334155;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .period-filter__option:hover {
        background: #f8fafc;
        color: #0f172a;
    }

    .period-filter__option.is-active {
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
    }

    .period-filter__check {
        color: #2563eb;
        font-size: 14px;
        opacity: 0;
    }

    .period-filter__option.is-active .period-filter__check {
        opacity: 1;
    }

    .filter-row .action-btn {
        cursor: pointer;
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
    }

    .filter-row .action-btn:hover {
        border-color: #cbd5e1;
        background: #f8fafc;
    }

    @media (max-width: 768px) {
        .period-filter,
        .period-filter__trigger {
            width: 100%;
        }

        .period-filter__menu {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .period-filter__trigger,
        .filter-row .action-btn {
            height: 36px;
            font-size: 12px;
        }
    }
.filter-row .action-btn {
    width: 100%;
}
</style>
<script>
    <?php if (!empty($monthlyChartDatasets)): ?>
    // BAR CHART
    const ctx = document.getElementById('monthlyChart').getContext('2d');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthlyChartData['labels']); ?>,
            datasets: <?php echo json_encode($monthlyChartDatasets); ?>
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: true,
                    backgroundColor: '#111827',
                    titleFont: {
                        size: 12,
                        weight: '600'
                    },
                    bodyFont: {
                        size: 12
                    },
                    padding: 10
                }
            },
            scales: {
                x: {
                    grid: {
                        color: '#e5e7eb',
                        borderDash: [3, 3],
                        drawBorder: false
                    },
                    ticks: {
                        color: '#64748b',
                        font: {
                            size: 12,
                            weight: '500',
                            family: 'Inter'
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    max: <?php echo (int) $monthlyChartMax; ?>,
                    ticks: {
                        stepSize: <?php echo max(1, (int) ($monthlyChartMax / 4)); ?>,
                        color: '#64748b',
                        font: {
                            size: 12,
                            weight: '500',
                            family: 'Inter'
                        }
                    },
                    grid: {
                        color: '#e5e7eb',
                        borderDash: [3, 3],
                        drawBorder: false
                    }
                }
            }
        }
    });
    <?php endif; ?>

    <?php if (!empty($statusChartData)): ?>
    // DONUT CHART
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            datasets: [{
                data: <?php echo json_encode($statusChartData); ?>,
                backgroundColor: <?php echo json_encode($statusChartColors); ?>,
                borderWidth: 0
            }]
        },
        options: {
            cutout: '68%',
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    <?php endif; ?>

    const dashboardPeriodFilter = document.getElementById('dashboardPeriodFilter');
    const dashboardPeriodTrigger = document.getElementById('dashboardPeriodTrigger');
    const dashboardPeriodMenu = document.getElementById('dashboardPeriodMenu');
    const dashboardRefresh = document.getElementById('dashboardRefresh');

    function closeDashboardPeriodMenu() {
        if (!dashboardPeriodFilter || !dashboardPeriodTrigger) {
            return;
        }

        dashboardPeriodFilter.classList.remove('is-open');
        dashboardPeriodTrigger.setAttribute('aria-expanded', 'false');
    }

    function openDashboardPeriodMenu() {
        if (!dashboardPeriodFilter || !dashboardPeriodTrigger) {
            return;
        }

        dashboardPeriodFilter.classList.add('is-open');
        dashboardPeriodTrigger.setAttribute('aria-expanded', 'true');
    }

    if (dashboardPeriodTrigger && dashboardPeriodMenu) {
        dashboardPeriodTrigger.addEventListener('click', function(event) {
            event.stopPropagation();

            if (dashboardPeriodFilter.classList.contains('is-open')) {
                closeDashboardPeriodMenu();
            } else {
                openDashboardPeriodMenu();
            }
        });

        dashboardPeriodMenu.querySelectorAll('[data-period]').forEach(function(option) {
            option.addEventListener('click', function() {
                const url = new URL(window.location.href);
                url.searchParams.set('period', this.dataset.period);
                url.searchParams.delete('month');
                window.location.href = url.toString();
            });
        });

        document.addEventListener('click', function(event) {
            if (!dashboardPeriodFilter.contains(event.target)) {
                closeDashboardPeriodMenu();
            }
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDashboardPeriodMenu();
            }
        });
    }

    if (dashboardRefresh) {
        dashboardRefresh.addEventListener('click', function() {
            window.location.reload();
        });
    }
</script>

<?php include('script_js.php'); ?>
<script>
    $(document).ready(function() {

        $('#orderTable').DataTable({
            processing: true,
            serverSide: true,
            scrollX: true,
            autoWidth: false,
            pageLength: 10,
            paging: false,
            searching: false,
            info: false,
            ordering: false,
            ajax: {
                url: 'orderRequest.php',
                type: 'POST',
                data: function(d) {
                    d.action = 'getRecentOrders';
                    d.start = 0;
                    d.length = 10;
                }
            },
            columns: [
                { data: 'ref_no' },
                { data: 'order_no' },
                { data: 'category' },
                { data: 'delivery_term' },
                { data: 'po_number' },
                { data: 'payment_term' },
                { data: 'transporter' },
                { data: 'order_status' },
                {
                    data: 'lines',
                    orderable: false
                }
            ]
        });

    });

    function openLineItems(orderNo) {

        $.ajax({
            url: 'orderRequest.php',
            type: 'POST',
            data: {
                orderNo: orderNo,
                action: "getRecentOrderLine"
            },
            dataType: "HTML",
            success: function(res) {
                $("#lineModal").modal('toggle');
                $(".modal-body").html(res);
            }
        })
    }
</script>