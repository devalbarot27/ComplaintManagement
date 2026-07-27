<?php
/**
 * Export all rows from a whitelisted database table to an .xlsx file (data only).
 *
 * Usage: uploads/export_table.php?table=user_master
 *
 * Read-only: does not modify any database records.
 */

session_start();

require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/admin_access_helpers.php';

require_system_admin($obconn);

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
 * Convert 1-based column index to Excel column letters (1 => A, 27 => AA).
 */
function export_table_column_letter(int $index): string
{
    $letter = '';
    while ($index > 0) {
        $index--;
        $letter = chr(65 + ($index % 26)) . $letter;
        $index = intdiv($index, 26);
    }

    return $letter;
}

/**
 * Escape text for Excel inline string cells.
 */
function export_table_xml_text(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Build a minimal .xlsx (Office Open XML) file from headers + rows.
 *
 * @param list<string> $headers
 * @param list<list<string>> $rows
 */
function export_table_build_xlsx(array $headers, array $rows): string
{
    $sheet = [];
    $sheet[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheet[] = '<sheetData>';

    $colCount = count($headers);
    $headerCells = [];
    for ($c = 0; $c < $colCount; $c++) {
        $ref = export_table_column_letter($c + 1) . '1';
        $headerCells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>'
            . export_table_xml_text((string) $headers[$c])
            . '</t></is></c>';
    }
    $sheet[] = '<row r="1">' . implode('', $headerCells) . '</row>';

    $rowNum = 2;
    foreach ($rows as $row) {
        $cells = [];
        for ($c = 0; $c < $colCount; $c++) {
            $ref = export_table_column_letter($c + 1) . $rowNum;
            $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>'
                . export_table_xml_text((string) ($row[$c] ?? ''))
                . '</t></is></c>';
        }
        $sheet[] = '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
        $rowNum++;
    }

    $sheet[] = '</sheetData>';
    $sheet[] = '</worksheet>';
    $sheetXml = implode('', $sheet);

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Data" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx_');
    if ($tmp === false) {
        throw new RuntimeException('Unable to create temporary export file.');
    }

    $zipPath = $tmp . '.xlsx';
    @unlink($tmp);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Unable to create Excel archive.');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $binary = file_get_contents($zipPath);
    @unlink($zipPath);

    if ($binary === false) {
        throw new RuntimeException('Unable to read generated Excel file.');
    }

    return $binary;
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
    // Table name is whitelist-validated only; never bind as a value (identifiers can't be parameterized).
    $quotedTable = '"' . str_replace('"', '""', $table) . '"';

    $stmt = $obconn->query('SELECT * FROM ' . $quotedTable);
    if ($stmt === false) {
        throw new RuntimeException('Failed to query table.');
    }

    $headers = [];
    $columnCount = $stmt->columnCount();
    for ($i = 0; $i < $columnCount; $i++) {
        $meta = $stmt->getColumnMeta($i);
        $headers[] = (string) ($meta['name'] ?? ('column_' . ($i + 1)));
    }

    $rows = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $normalized = [];
        foreach ($row as $value) {
            if ($value === null) {
                $normalized[] = '';
            } elseif (is_bool($value)) {
                $normalized[] = $value ? '1' : '0';
            } else {
                $normalized[] = (string) $value;
            }
        }
        $rows[] = $normalized;
    }

    // Empty table: still export headers only.
    if ($headers === []) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to read table columns.';
        exit;
    }

    $xlsx = export_table_build_xlsx($headers, $rows);
    $filename = $table . '_export_' . date('Ymd_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($xlsx));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    echo $xlsx;
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export failed. Please try again or contact the administrator.';
    exit;
}
