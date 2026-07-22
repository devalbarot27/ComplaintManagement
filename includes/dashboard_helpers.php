<?php

require_once __DIR__ . '/dashboard_scope_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

/**
 * @return array{created: bool, acknowledged: bool, pending: bool, dispatched: bool}
 */
function dashboard_order_module_permissions(PDO $conn): array
{
    return [
        'created' => rbac_user_can($conn, 'order-booking', 'create-order'),
        'recent' => rbac_user_can($conn, 'recent-orders', 'list'),
        'acknowledged' => rbac_user_can($conn, 'order-acknowledgement', 'list'),
        'pending' => rbac_user_can($conn, 'pending-order', 'list'),
        'dispatched' => rbac_user_can($conn, 'despatch-details', 'list'),
        'complaint-view' => rbac_user_can($conn, 'complaint-entry', 'view'),
    ];
}

function dashboard_period_options(): array
{
    return [
        'today' => 'Today',
        'this_week' => 'This Week',
        'this_month' => 'This Month',
        'last_3_months' => 'Last 3 Month',
        'last_6_months' => 'Last 6 Month',
        'this_year' => 'This Year',
        'last_year' => 'Last Year',
    ];
}

function dashboard_resolve_period(?string $period): string
{
    $options = dashboard_period_options();
    $period = $period ?? 'this_month';

    return isset($options[$period]) ? $period : 'this_month';
}

function dashboard_period_date_sql(string $dateColumn, string $period): string
{
    switch ($period) {
        case 'today':
            return "$dateColumn = CURRENT_DATE";
        case 'this_week':
            return "$dateColumn >= DATE_TRUNC('week', CURRENT_DATE)::date AND $dateColumn <= CURRENT_DATE";
        case 'this_month':
            return "DATE_TRUNC('month', $dateColumn)::date = DATE_TRUNC('month', CURRENT_DATE)::date";
        case 'last_3_months':
            return "$dateColumn >= (CURRENT_DATE - INTERVAL '3 months')::date AND $dateColumn <= CURRENT_DATE";
        case 'last_6_months':
            return "$dateColumn >= (CURRENT_DATE - INTERVAL '6 months')::date AND $dateColumn <= CURRENT_DATE";
        case 'this_year':
            return "DATE_TRUNC('year', $dateColumn)::date = DATE_TRUNC('year', CURRENT_DATE)::date";
        case 'last_year':
            return "DATE_TRUNC('year', $dateColumn)::date = DATE_TRUNC('year', (CURRENT_DATE - INTERVAL '1 year'))::date";
        default:
            return "DATE_TRUNC('month', $dateColumn)::date = DATE_TRUNC('month', CURRENT_DATE)::date";
    }
}

// Dashboard Stats
function dashboard_fetch_stats(PDO $dpconn, PDO $obconn, ?string $period = null): array
{
    $selectedPeriod = dashboard_resolve_period($period);
    $periodOptions = dashboard_period_options();
    $scope = dashboard_resolve_view_scope($obconn);
    $recentOrderStats = dashboard_fetch_recent_order_pipeline_stats($obconn, $dpconn, $selectedPeriod);

    return [
        'selected_period' => $selectedPeriod,
        'selected_period_label' => $periodOptions[$selectedPeriod],
        'period_options' => $periodOptions,
        'view_scope' => $scope,
        'total_recent_orders_count' => $recentOrderStats['total'],
        'created_orders_count' => $recentOrderStats['created'],
        'acknowledgement_count' => $recentOrderStats['acknowledged'],
        'pending_orders_count' => $recentOrderStats['pending'],
        'dispatched_orders_count' => $recentOrderStats['dispatched'],
        'pending_over_10_days_count' => dashboard_fetch_pending_over_10_days_count($dpconn, $scope, $obconn),
        'dispatches_delivered_this_week_count' => dashboard_fetch_dispatched_count($dpconn, $scope, 'this_week'),
        'monthly_chart' => dashboard_fetch_monthly_chart_data($dpconn, $obconn, $scope),
    ];
}

/**
 * Dashboard pipeline stats derived from the Recent Orders query (plexecom_customer_units).
 *
 * Total/Created = distinct Recent Orders
 * Acknowledged  = orders with AO Number (order_number)
 * Pending       = orders without AO Number
 * Dispatched    = orders with invoice details in despatch
 *
 * @return array{total:int,created:int,acknowledged:int,pending:int,dispatched:int}
 */
function dashboard_fetch_recent_order_pipeline_stats(PDO $obconn, PDO $dpconn, string $period): array
{
    $empty = [
        'total' => 0,
        'created' => 0,
        'acknowledged' => 0,
        'pending' => 0,
        'dispatched' => 0,
    ];

    try {
        admin_ensure_session_role($obconn);

        // Match getRecentOrders() visibility rules.
        $seeAll = is_system_admin() || is_management_user();
        $customerCode = dashboard_resolve_customer_code();
        if (!$seeAll && $customerCode === '') {
            return $empty;
        }

        $userWhere = $seeAll ? '1=1' : 'a.cuno = :customer_code';
        $dateFilter = dashboard_period_date_sql('a.indent_date', $period);

        $sql = "
            SELECT
                a.refno,
                a.cuno,
                TRIM(COALESCE(a.order_number, '')) AS order_number
            FROM (
                SELECT DISTINCT ON (a.refno)
                    a.refno,
                    a.cuno,
                    a.order_number,
                    a.indent_date
                FROM plexecom_customer_units AS a
                WHERE {$userWhere}
                  AND {$dateFilter}
                ORDER BY a.refno DESC, a.indent_date DESC
            ) AS a
        ";

        $stmt = $obconn->prepare($sql);
        if (!$seeAll) {
            $stmt->bindValue(':customer_code', $customerCode, PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = count($rows);
        $acknowledged = 0;
        $pending = 0;
        $aoByCuno = [];

        foreach ($rows as $row) {
            $orderNumber = trim((string) ($row['order_number'] ?? ''));
            $cuno = trim((string) ($row['cuno'] ?? ''));

            if ($orderNumber === '') {
                $pending++;
                continue;
            }

            $acknowledged++;
            if ($cuno !== '') {
                $aoByCuno[$cuno][$orderNumber] = true;
            }
        }

        $dispatched = dashboard_count_recent_orders_with_invoice($dpconn, $aoByCuno);

        return [
            'total' => $total,
            'created' => $total,
            'acknowledged' => $acknowledged,
            'pending' => $pending,
            'dispatched' => $dispatched,
        ];
    } catch (Throwable $e) {
        return $empty;
    }
}

/**
 * Count Recent Orders that have invoice/despatch details.
 *
 * @param array<string, array<string, bool>> $aoByCuno
 */
function dashboard_count_recent_orders_with_invoice(PDO $dpconn, array $aoByCuno): int
{
    if ($aoByCuno === []) {
        return 0;
    }

    $dispatched = 0;

    try {
        foreach ($aoByCuno as $cuno => $orderMap) {
            $orderNumbers = array_keys($orderMap);
            if ($orderNumbers === []) {
                continue;
            }

            // Chunk large IN lists to keep query size manageable.
            foreach (array_chunk($orderNumbers, 500) as $chunk) {
                $placeholders = [];
                $params = [':cuno' => $cuno];

                foreach ($chunk as $i => $ordno) {
                    $key = ':ord' . $i;
                    $placeholders[] = $key;
                    $params[$key] = $ordno;
                }

                $sql = '
                    SELECT COUNT(DISTINCT TRIM(ordno)) AS cnt
                    FROM despatch
                    WHERE cuno = :cuno
                      AND cmp != 600
                      AND TRIM(ordno) IN (' . implode(', ', $placeholders) . ')
                ';

                $stmt = $dpconn->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value, PDO::PARAM_STR);
                }
                $stmt->execute();
                $dispatched += (int) $stmt->fetchColumn();
            }
        }
    } catch (Throwable $e) {
        return 0;
    }

    return $dispatched;
}

// Orders pending for more than 10 days — same Pending definition as Order Pipeline:
// Recent Orders (plexecom_customer_units) without AO Number, indent_date older than 10 days.
function dashboard_fetch_pending_over_10_days_count(PDO $dpconn, array $scope, PDO $obconn): int
{
    try {
        admin_ensure_session_role($obconn);

        $seeAll = is_system_admin() || is_management_user();
        $customerCode = dashboard_resolve_customer_code();
        if (!$seeAll && $customerCode === '') {
            return 0;
        }

        $userWhere = $seeAll ? '1=1' : 'a.cuno = :customer_code';

        $sql = "
            SELECT COUNT(*) FROM (
                SELECT DISTINCT ON (a.refno)
                    a.refno,
                    a.order_number,
                    a.indent_date
                FROM plexecom_customer_units AS a
                WHERE {$userWhere}
                ORDER BY a.refno DESC, a.indent_date DESC
            ) AS a
            WHERE TRIM(COALESCE(a.order_number, '')) = ''
              AND a.indent_date IS NOT NULL
              AND a.indent_date::date < (CURRENT_DATE - INTERVAL '10 days')::date
        ";

        $stmt = $obconn->prepare($sql);
        if (!$seeAll) {
            $stmt->bindValue(':customer_code', $customerCode, PDO::PARAM_STR);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_format_pending_over_10_days_alert(int $count): string
{
    $count = max(0, (int) $count);
    $orderLabel = $count === 1 ? 'order' : 'orders';

    return $count . ' ' . $orderLabel . ' pending for more than 10 days';
}

// Pending Orders — mirrors getPendingOrderListNew() recordsTotal logic
function dashboard_fetch_pending_orders_count(PDO $conn, array $scope, string $period): int
{
    $customerCode = dashboard_resolve_customer_code();

    if ($customerCode === '') {
        return 0;
    }

    $sql = "
        SELECT COUNT(*) FROM (
            SELECT p.ordno
            FROM pendingordersnew p
            WHERE p.company != 600 AND p.cuno = :uname
            GROUP BY p.ordno
        ) x
    ";

    try {
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':uname', $customerCode, PDO::PARAM_STR);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// Acknowledgement
function dashboard_fetch_acknowledgement_count(PDO $conn, array $scope, string $period): int
{
    $cunoFilter = dashboard_scope_maintdealer_cuno_sql($scope, 'm.cuno');
    $dateFilter = trim($period) !== ''
        ? dashboard_period_date_sql('m.ord_date', dashboard_resolve_period($period))
        : '';

    $sql = '
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT m.cuno, m.ordno, m.ord_date, m.purno, m.dpst, d.dpst_desc
            FROM maintdealer m
            LEFT OUTER JOIN dpst_master d ON TRIM(m.dpst) = d.dpst_code::text
            WHERE m.company != 600
    ';

    if ($cunoFilter !== '') {
        $sql .= "\n              {$cunoFilter}";
    }

    if ($dateFilter !== '') {
        $sql .= "\n              AND {$dateFilter}";
    }

    $sql .= '
        ) acknowledged_orders
    ';

    try {
        $stmt = $conn->prepare($sql);
        dashboard_bind_maintdealer_scope_params($stmt, $scope);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}


// Total Recent Orders
function dashboard_fetch_total_recent_orders_count(PDO $conn, array $scope, string $period): int
{
    $dateFilter = dashboard_period_date_sql('indent_date', $period);
    $cunoFilter = dashboard_scope_plexecom_cuno_sql($scope);

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT refno) AS cnt
            FROM plexecom_customer_units
            WHERE 1 = 1
              $cunoFilter
              AND $dateFilter
        ");
        dashboard_bind_plexecom_scope_params($stmt, $scope);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

// Monthly Chart
function dashboard_build_month_series(int $months = 6): array
{
    $series = [];

    for ($i = $months - 1; $i >= 0; $i--) {
        $monthStart = strtotime("-$i months", strtotime(date('Y-m-01')));
        $key = date('Y-m', $monthStart);
        $series[$key] = [
            'label' => date('M', $monthStart),
            'acknowledged' => 0,
            'pending' => 0,
        ];
    }

    return $series;
}

// Monthly Acknowledgement
function dashboard_fetch_monthly_acknowledgement_counts(PDO $conn, array $scope, string $startDate): array
{
    $counts = [];
    $cunoFilter = dashboard_scope_maintdealer_cuno_sql($scope, 'm.cuno');

    $sql = '
        SELECT month_key, COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT
                m.cuno,
                m.ordno,
                m.ord_date,
                m.purno,
                m.dpst,
                d.dpst_desc,
                to_char(date_trunc(\'month\', m.ord_date), \'YYYY-MM\') AS month_key
            FROM maintdealer m
            LEFT OUTER JOIN dpst_master d ON TRIM(m.dpst) = d.dpst_code::text
            WHERE m.company != 600
              AND m.ord_date >= :start_date
              AND m.ord_date <= CURRENT_DATE
    ';

    if ($cunoFilter !== '') {
        $sql .= "\n              {$cunoFilter}";
    }

    $sql .= '
        ) acknowledged_orders
        GROUP BY month_key
        ORDER BY month_key
    ';

    try {
        $stmt = $conn->prepare($sql);
        dashboard_bind_maintdealer_scope_params($stmt, $scope);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['month_key']] = (int) $row['cnt'];
        }
    } catch (Throwable $e) {
        return [];
    }

    return $counts;
}

// Monthly Pending — same definition as Order Pipeline Pending:
// Recent Orders (plexecom_customer_units) without AO Number, by indent_date month.
function dashboard_fetch_monthly_pending_counts(PDO $obconn, string $startDate): array
{
    $counts = [];

    try {
        admin_ensure_session_role($obconn);

        $seeAll = is_system_admin() || is_management_user();
        $customerCode = dashboard_resolve_customer_code();
        if (!$seeAll && $customerCode === '') {
            return [];
        }

        $userWhere = $seeAll ? '1=1' : 'a.cuno = :customer_code';

        $sql = "
            SELECT
                to_char(date_trunc('month', a.indent_date), 'YYYY-MM') AS month_key,
                COUNT(*) AS cnt
            FROM (
                SELECT DISTINCT ON (a.refno)
                    a.refno,
                    a.order_number,
                    a.indent_date,
                    a.cuno
                FROM plexecom_customer_units AS a
                WHERE {$userWhere}
                  AND a.indent_date >= :start_date
                  AND a.indent_date <= CURRENT_DATE
                ORDER BY a.refno DESC, a.indent_date DESC
            ) AS a
            WHERE TRIM(COALESCE(a.order_number, '')) = ''
            GROUP BY month_key
            ORDER BY month_key
        ";

        $stmt = $obconn->prepare($sql);
        if (!$seeAll) {
            $stmt->bindValue(':customer_code', $customerCode, PDO::PARAM_STR);
        }
        $stmt->bindValue(':start_date', $startDate);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['month_key']] = (int) $row['cnt'];
        }
    } catch (Throwable $e) {
        return [];
    }

    return $counts;
}

// Monthly Chart
function dashboard_fetch_monthly_chart_data(PDO $dpconn, PDO $obconn, array $scope, int $months = 6): array
{
    $monthSeries = dashboard_build_month_series($months);
    $monthKeys = array_keys($monthSeries);
    $startDate = $monthKeys[0] . '-01';

    $acknowledgementCounts = dashboard_fetch_monthly_acknowledgement_counts($dpconn, $scope, $startDate);
    $pendingCounts = dashboard_fetch_monthly_pending_counts($obconn, $startDate);

    foreach ($monthKeys as $monthKey) {
        $monthSeries[$monthKey]['acknowledged'] = $acknowledgementCounts[$monthKey] ?? 0;
        $monthSeries[$monthKey]['pending'] = $pendingCounts[$monthKey] ?? 0;
    }

    $values = array_values($monthSeries);

    return [
        'labels' => array_column($values, 'label'),
        'acknowledged' => array_column($values, 'acknowledged'),
        'pending' => array_column($values, 'pending'),
    ];
}

// Dispatches Delivered This Week
function dashboard_format_dispatches_delivered_this_week_alert(int $count): string
{
    $label = $count === 1 ? 'dispatch' : 'dispatches';

    return $count . ' ' . $label . ' delivered this week';
}

// Dispatches Delivered
function dashboard_fetch_dispatched_count(PDO $conn, array $scope, string $period): int
{
    $cunoFilter = dashboard_scope_cuno_sql($scope, 'a.cuno');
    $dateFilter = trim($period) !== ''
        ? dashboard_period_date_sql('a.invdate', dashboard_resolve_period($period))
        : '';

    $sql = '
        SELECT COUNT(*) AS cnt
        FROM (
            SELECT DISTINCT ON (a.invdate, a.cmp, a.ordno, a.invref, a.invno) a.invno
            FROM despatch a
            LEFT JOIN lr_details b ON a.invref = b.invref AND a.invno = b.invno AND a.cmp = b.company
            LEFT JOIN dpst_master d ON d.dpst_code::text = a.dpst
            WHERE a.cmp != 600
              AND a.dpst NOT IN (\'SLS500\', \'SLS01\', \'SO0600\', \'SAL01\')
    ';

    if ($cunoFilter !== '') {
        $sql .= "\n              {$cunoFilter}";
    }

    if ($dateFilter !== '') {
        $sql .= "\n              AND {$dateFilter}";
    }

    $sql .= '
        ) dispatched_orders
    ';

    try {
        $stmt = $conn->prepare($sql);
        dashboard_bind_scope_params($stmt, $scope);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}