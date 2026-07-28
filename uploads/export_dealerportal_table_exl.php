<?php
/**
 * Export DealerPortal table data to .xlsx (data only).
 *
 * Usage:
 * uploads/export_dealerportal_table.php?table=customer_address
 */

session_start();

require_once __DIR__ . '/../pdo_obconn.php';
require_once __DIR__ . '/../includes/admin_access_helpers.php';

require_system_admin($obconn);

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
 * Convert 1-based index to Excel letter (1 => A, 27 => AA).
 */
function dealerportal_export_column_letter(int $index): string
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
 * XML-escape cell text.
 */
function dealerportal_export_xml_text(string $value): string
{
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';

    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Build a minimal .xlsx payload from headers and rows.
 *
 * @param list<string> $headers
 * @param list<list<string>> $rows
 */
function dealerportal_export_build_xlsx(array $headers, array $rows): string
{
    $sheet = [];
    $sheet[] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $sheet[] = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $sheet[] = '<sheetData>';

    $colCount = count($headers);
    $headerCells = [];
    for ($c = 0; $c < $colCount; $c++) {
        $ref = dealerportal_export_column_letter($c + 1) . '1';
        $headerCells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>'
            . dealerportal_export_xml_text((string) $headers[$c])
            . '</t></is></c>';
    }
    $sheet[] = '<row r="1">' . implode('', $headerCells) . '</row>';

    $rowNum = 2;
    foreach ($rows as $row) {
        $cells = [];
        for ($c = 0; $c < $colCount; $c++) {
            $ref = dealerportal_export_column_letter($c + 1) . $rowNum;
            $cells[] = '<c r="' . $ref . '" t="inlineStr"><is><t>'
                . dealerportal_export_xml_text((string) ($row[$c] ?? ''))
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

    // Whitelist-validated identifier only.
    $quotedTable = '"' . str_replace('"', '""', $table) . '"';
    $stmt = $dpconn->query('SELECT * FROM ' . $quotedTable);
    if ($stmt === false) {
        throw new RuntimeException('Failed to query table.');
    }

    $headers = [];
    $columnCount = $stmt->columnCount();
    for ($i = 0; $i < $columnCount; $i++) {
        $meta = $stmt->getColumnMeta($i);
        $headers[] = (string) ($meta['name'] ?? ('column_' . ($i + 1)));
    }

    if ($headers === []) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Unable to read table columns.';
        exit;
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

    $xlsx = dealerportal_export_build_xlsx($headers, $rows);
    $filename = $table . '_dealerportal_export_' . date('Ymd_His') . '.xlsx';

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