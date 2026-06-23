<?php

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

function dashboard_fetch_pending_orders_count(PDO $conn, string $userName, string $period): int
{
    $dateFilter = dashboard_period_date_sql('p.orddt', $period);

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM pendingordersnew p, user_area_dpst_mapping u
            WHERE u.usr_name = :uname
              AND trim(p.dpst) = ANY(u.dpst)
              AND trim(p.area) = u.area
              AND u.valid = 'Y'
              AND $dateFilter
        ");
        $stmt->bindValue(':uname', $userName);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_fetch_pending_over_10_days_count(PDO $conn, string $userName): int
{
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM pendingordersnew p, user_area_dpst_mapping u
            WHERE u.usr_name = :uname
              AND trim(p.dpst) = ANY(u.dpst)
              AND trim(p.area) = u.area
              AND u.valid = 'Y'
              AND p.orddt <= (CURRENT_DATE - INTERVAL '10 days')::date
        ");
        $stmt->bindValue(':uname', $userName);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_format_pending_over_10_days_alert(int $count): string
{
    $orderLabel = $count === 1 ? 'order' : 'orders';

    return $count . ' ' . $orderLabel . ' pending for more than 10 days';
}

function dashboard_fetch_acknowledgement_count(PDO $conn, string $userName, string $period): int
{
    $dateFilter = dashboard_period_date_sql('m.ord_date', $period);

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM maintdealer m
            JOIN user_area_dpst_mapping u ON m.dpst = ANY(u.dpst) AND m.area = u.area
            WHERE u.usr_name = :uname
              AND u.valid = 'Y'
              AND $dateFilter
        ");
        $stmt->bindValue(':uname', $userName);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_fetch_total_orders_count(PDO $conn, string $userName, string $period): int
{
    // complaint_management DB: tbl_vayu_orders_header
    $dateFilter = dashboard_period_date_sql('(o.created_at::date)', $period);

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM tbl_vayu_orders_header o
            WHERE o.created_by = :uname
              AND $dateFilter
        ");
        $stmt->bindValue(':uname', $userName);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

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

function dashboard_fetch_monthly_acknowledgement_counts(PDO $conn, string $userName, string $startDate): array
{
    $counts = [];

    try {
        $stmt = $conn->prepare("
            SELECT to_char(date_trunc('month', m.ord_date), 'YYYY-MM') AS month_key,
                   COUNT(*) AS cnt
            FROM maintdealer m
            JOIN user_area_dpst_mapping u ON m.dpst = ANY(u.dpst) AND m.area = u.area
            WHERE u.usr_name = :uname
              AND u.valid = 'Y'
              AND m.ord_date >= :start_date
              AND m.ord_date <= CURRENT_DATE
            GROUP BY 1
            ORDER BY 1
        ");
        $stmt->bindValue(':uname', $userName);
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

function dashboard_fetch_monthly_pending_counts(PDO $conn, string $userName, string $startDate): array
{
    $counts = [];

    try {
        $stmt = $conn->prepare("
            SELECT to_char(date_trunc('month', p.orddt), 'YYYY-MM') AS month_key,
                   COUNT(*) AS cnt
            FROM pendingordersnew p, user_area_dpst_mapping u
            WHERE u.usr_name = :uname
              AND trim(p.dpst) = ANY(u.dpst)
              AND trim(p.area) = u.area
              AND u.valid = 'Y'
              AND p.orddt >= :start_date
              AND p.orddt <= CURRENT_DATE
            GROUP BY 1
            ORDER BY 1
        ");
        $stmt->bindValue(':uname', $userName);
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

function dashboard_fetch_monthly_chart_data(PDO $conn, string $userName, int $months = 6): array
{
    $monthSeries = dashboard_build_month_series($months);
    $monthKeys = array_keys($monthSeries);
    $startDate = $monthKeys[0] . '-01';

    $acknowledgementCounts = dashboard_fetch_monthly_acknowledgement_counts($conn, $userName, $startDate);
    $pendingCounts = dashboard_fetch_monthly_pending_counts($conn, $userName, $startDate);

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

function dashboard_format_dispatches_delivered_this_week_alert(int $count): string
{
    $label = $count === 1 ? 'dispatch' : 'dispatches';

    return $count . ' ' . $label . ' delivered this week';
}

function dashboard_fetch_dispatched_count(PDO $conn, string $userName, string $period): int
{
    $dateFilter = dashboard_period_date_sql('a.invdate', $period);

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt
            FROM (
                SELECT DISTINCT ON (a.invdate, a.cmp, a.ordno, a.invref, a.invno) a.invno
                FROM despatch a
                LEFT JOIN lr_details b
                    ON a.invref = b.invref AND a.invno = b.invno AND a.cmp = b.company
                LEFT JOIN dpst_master d ON d.dpst_code::text = a.dpst
                INNER JOIN user_area_dpst_mapping u
                    ON trim(a.dpst) = ANY(u.dpst) AND u.valid = 'Y' AND u.usr_name = :uname
                WHERE a.cmp != 600
                  AND a.dpst NOT IN ('SLS500', 'SLS01', 'SO0600', 'SAL01')
                  AND $dateFilter
                ORDER BY a.invdate DESC, a.ordno, a.invref, a.invno
            ) x
        ");
        $stmt->bindValue(':uname', $userName);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_fetch_stats(PDO $dpconn, PDO $obconn, string $userName, ?string $period = null): array
{
    $selectedPeriod = dashboard_resolve_period($period);
    $periodOptions = dashboard_period_options();

    return [
        'selected_period' => $selectedPeriod,
        'selected_period_label' => $periodOptions[$selectedPeriod],
        'period_options' => $periodOptions,
        'pending_orders_count' => dashboard_fetch_pending_orders_count($dpconn, $userName, $selectedPeriod),
        'acknowledgement_count' => dashboard_fetch_acknowledgement_count($dpconn, $userName, $selectedPeriod),
        'total_orders_count' => dashboard_fetch_total_orders_count($obconn, $userName, $selectedPeriod),
        'pending_over_10_days_count' => dashboard_fetch_pending_over_10_days_count($dpconn, $userName),
        'dispatched_orders_count' => dashboard_fetch_dispatched_count($dpconn, $userName, $selectedPeriod),
        'dispatches_delivered_this_week_count' => dashboard_fetch_dispatched_count($dpconn, $userName, 'this_week'),
        'monthly_chart' => dashboard_fetch_monthly_chart_data($dpconn, $userName),
    ];
}