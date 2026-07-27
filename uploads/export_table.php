<?php
/**
 * Stream whitelisted complaint_management table data to CSV (Excel-compatible).
 * Designed for large tables via batched cursor reads.
 *
 * Usage: uploads/export_table.php?table=user_master
 *
 * Read-only: does not modify any database records.
 */

session_start();

require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/admin_access_helpers.php';

require_system_admin($obconn);

@set_time_limit(0);
@ini_set('memory_limit', '512M');
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', '0');
ignore_user_abort(true);

/**
 * Allowed export tables (exact names only). Prevents SQL injection and unauthorized access.
 *
 * @return list<string>
 */
function export_table_whitelist(): array
{
    return [
        'area',
        'complaint_activity_logs',
        'complaint_assignments',
        'complaint_categories',
        'complaint_closures',
        'complaint_nudge_logs',
        'complaint_service_logs',
        'complaint_service_updates',
        'complaints',
        'customer_address',
        'customer_feedback_options',
        'customer_master',
        'dealercode_and_transportercode',
        'dpst_master',
        'elgi_item_master',
        'gst_hsn',
        'industry_segments',
        'installed_base',
        'module_routes',
        'modules',
        'notifications',
        'orders',
        'part_replaced_masters',
        'password_history',
        'password_reset_tokens',
        'permissions',
        'plexecom_customer_units',
        'postcodes',
        'product_master',
        'product_master_vayu',
        'reason_masters',
        'role_permissions',
        'roles',
        'sales_orders',
        'service_log_part_replacements',
        'service_logs',
        'spare_parts_consumption',
        'spare_parts_consumption_items',
        'spp_payterm_master',
        'tbl_vayu_cartitems',
        'tbl_vayu_delivery_term',
        'tbl_vayu_dpst_master',
        'tbl_vayu_item_master',
        'tbl_vayu_order_category',
        'tbl_vayu_orders_header',
        'tbl_vayu_orders_line',
        'transporter_master',
        'user_master',
        'warranty_chargeable_types',
    ];
}

/**
 * Validate table name against format rules and the whitelist.
 */
function export_table_resolve_name(string $raw): ?string
{
    $table = trim($raw);
    if ($table === '' || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $table)) {
        return null;
    }

    $allowed = export_table_whitelist();
    $lookup = array_fill_keys($allowed, true);

    return isset($lookup[$table]) ? $table : null;
}

/**
 * @return list<string>
 */
function export_table_headers(PDO $conn, string $table): array
{
    $stmt = $conn->prepare('
        SELECT column_name
        FROM information_schema.columns
        WHERE table_schema = \'public\'
          AND table_name = :table_name
        ORDER BY ordinal_position
    ');
    $stmt->bindValue(':table_name', $table);
    $stmt->execute();

    $headers = [];
    while ($name = $stmt->fetchColumn()) {
        $headers[] = (string) $name;
    }

    return $headers;
}

/**
 * Normalize a DB cell value for CSV output.
 */
function export_table_cell($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    return (string) $value;
}

$tableParam = (string) ($_GET['table'] ?? '');
$table = export_table_resolve_name($tableParam);

if ($table === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or unauthorized table name. Pass a whitelisted table via ?table=table_name';
    exit;
}

try {
    if (!isset($obconn) || !$obconn instanceof PDO) {
        throw new RuntimeException('Database connection not available.');
    }

    $headers = export_table_headers($obconn, $table);
    if ($headers === []) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Table not found or has no columns.';
        exit;
    }

    // Whitelist-validated identifier only (never interpolate raw GET into SQL).
    $quotedTable = '"' . str_replace('"', '""', $table) . '"';
    $cursorName = 'cm_export_cur_' . preg_replace('/[^a-z0-9_]/', '', strtolower($table));
    $batchSize = 5000;
    $filename = $table . '_export_' . date('Ymd_His') . '.csv';

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Accel-Buffering: no');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        throw new RuntimeException('Unable to open output stream.');
    }

    // UTF-8 BOM so Excel opens special characters correctly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $headers);

    $obconn->beginTransaction();
    try {
        $obconn->exec('DECLARE ' . $cursorName . ' NO SCROLL CURSOR FOR SELECT * FROM ' . $quotedTable);

        while (true) {
            $batchStmt = $obconn->query('FETCH FORWARD ' . (int) $batchSize . ' FROM ' . $cursorName);
            if ($batchStmt === false) {
                throw new RuntimeException('Failed to fetch export batch.');
            }

            $batch = $batchStmt->fetchAll(PDO::FETCH_NUM);
            $batchStmt->closeCursor();

            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $normalized = [];
                foreach ($row as $value) {
                    $normalized[] = export_table_cell($value);
                }
                fputcsv($out, $normalized);
            }

            unset($batch, $normalized);
            if (function_exists('flush')) {
                flush();
            }
        }

        $obconn->exec('CLOSE ' . $cursorName);
        $obconn->commit();
    } catch (Throwable $inner) {
        if ($obconn->inTransaction()) {
            $obconn->rollBack();
        }
        throw $inner;
    }

    fclose($out);
    exit;
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Export failed. Please try again or contact the administrator.';
    exit;
}
