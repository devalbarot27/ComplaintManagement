<?php
/**
 * Stream DealerPortal table data to CSV (Excel-compatible).
 * Designed for large tables (100k–1M+ rows) via batched cursor reads.
 *
 * Usage:
 * uploads/export_dealerportal_table.php?table=customer_address
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
 * Allowed DealerPortal tables (exact names only).
 *
 * @return list<string>
 */
function dealerportal_export_whitelist(): array
{
    return [
        'customer_address',
        'pendingordersnew',
        'maintdealer',
        'dispatch',
    ];
}

/**
 * Validate and normalize input table name.
 */
function dealerportal_export_resolve_table(string $raw): ?string
{
    $table = strtolower(trim($raw));
    if ($table === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $table)) {
        return null;
    }

    $allowed = dealerportal_export_whitelist();
    $lookup = array_fill_keys($allowed, true);

    return isset($lookup[$table]) ? $table : null;
}

/**
 * @return list<string>
 */
function dealerportal_export_headers(PDO $conn, string $table): array
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
function dealerportal_export_cell($value): string
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
$table = dealerportal_export_resolve_table($tableParam);

if ($table === null) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid or unauthorized table name.';
    exit;
}

try {
    if (!isset($dpconn) || !$dpconn instanceof PDO) {
        throw new RuntimeException('DealerPortal connection not available.');
    }

    $headers = dealerportal_export_headers($dpconn, $table);
    if ($headers === []) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Table not found or has no columns.';
        exit;
    }

    // Whitelist-validated identifier only (never interpolate raw GET into SQL).
    $quotedTable = '"' . str_replace('"', '""', $table) . '"';
    $cursorName = 'dp_export_cur_' . preg_replace('/[^a-z0-9_]/', '', $table);
    $batchSize = 5000;
    $filename = $table . '_dealerportal_export_' . date('Ymd_His') . '.csv';

    // Clear any prior output so the download is clean.
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

    $dpconn->beginTransaction();
    try {
        $dpconn->exec('DECLARE ' . $cursorName . ' NO SCROLL CURSOR FOR SELECT * FROM ' . $quotedTable);

        while (true) {
            $batchStmt = $dpconn->query('FETCH FORWARD ' . (int) $batchSize . ' FROM ' . $cursorName);
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
                    $normalized[] = dealerportal_export_cell($value);
                }
                fputcsv($out, $normalized);
            }

            unset($batch, $normalized);
            if (function_exists('flush')) {
                flush();
            }
        }

        $dpconn->exec('CLOSE ' . $cursorName);
        $dpconn->commit();
    } catch (Throwable $inner) {
        if ($dpconn->inTransaction()) {
            $dpconn->rollBack();
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