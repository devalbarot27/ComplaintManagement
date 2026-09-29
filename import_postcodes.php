<?php

const POSTCODE_IMPORT_BATCH_SIZE = 500;
const POSTCODE_IMPORT_MIN_BYTES = 20 * 1024 * 1024;

/**
 * @return array{imported:int, skipped:int, samples:array<int, string>, error:?string}
 */
function postcode_import_from_path(PDO $conn, string $path): array
{
    $empty = ['imported' => 0, 'skipped' => 0, 'samples' => [], 'error' => null];

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return array_merge($empty, ['error' => 'The CSV file could not be opened.']);
    }

    $delimiter = postcode_import_delimiter($handle);
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header === false) {
        fclose($handle);
        return array_merge($empty, ['error' => 'The CSV file is empty.']);
    }

    $columnMap = postcode_import_column_map($header);
    $missing = array_diff(['postcode', 'city', 'district', 'state'], array_keys($columnMap));
    if ($missing !== []) {
        fclose($handle);
        return array_merge($empty, [
            'error' => 'The CSV header must include pincode, CITY name, District, and Statename.',
        ]);
    }

    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    $ownTransaction = !$conn->inTransaction();
    if ($ownTransaction) {
        $conn->beginTransaction();
    }

    $imported = 0;
    $skipped = 0;
    $samples = [];
    $seen = [];
    $batch = [];
    $rowNumber = 1;

    try {
        $conn->exec('DROP TABLE IF EXISTS postcodes_import_stage');
        $conn->exec('
            CREATE TEMP TABLE postcodes_import_stage (
                postcode VARCHAR(20) NOT NULL,
                city VARCHAR(100) NULL,
                district VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                country VARCHAR(100) NULL,
                state_code VARCHAR(10) NULL
            )
        ');

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;
            if (postcode_import_row_is_blank($cells)) {
                continue;
            }

            $parsed = postcode_import_parse_row($cells, $columnMap, $seen);
            if ($parsed['error'] !== null) {
                $skipped++;
                if (count($samples) < 8) {
                    $samples[] = 'Row ' . $rowNumber . ': ' . $parsed['error'];
                }
                continue;
            }

            $seen[$parsed['row']['postcode']] = true;
            $batch[] = $parsed['row'];
            $imported++;

            if (count($batch) >= POSTCODE_IMPORT_BATCH_SIZE) {
                postcode_import_insert_batch($conn, $batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            postcode_import_insert_batch($conn, $batch);
        }

        if ($imported < 1) {
            if ($ownTransaction && $conn->inTransaction()) {
                $conn->rollBack();
            }
            fclose($handle);
            return [
                'imported' => 0,
                'skipped' => $skipped,
                'samples' => $samples,
                'error' => 'No valid postcode rows were found. Existing postcode data was not changed.',
            ];
        }

        $conn->exec('DELETE FROM postcodes');
        $conn->exec('
            INSERT INTO postcodes (postcode, city, district, state, country, state_code, created_at, updated_at)
            SELECT postcode, city, district, state, COALESCE(NULLIF(country, \'\'), \'India\'), state_code,
                   CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            FROM postcodes_import_stage
        ');
        $conn->exec('DROP TABLE IF EXISTS postcodes_import_stage');

        if ($ownTransaction && $conn->inTransaction()) {
            $conn->commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        fclose($handle);
        error_log('postcode import: ' . $e->getMessage());
        return array_merge($empty, ['error' => 'Failed to import postcodes. Existing postcode data was not changed.']);
    }

    fclose($handle);

    return [
        'imported' => $imported,
        'skipped' => $skipped,
        'samples' => $samples,
        'error' => null,
    ];
}

function postcode_import_delimiter($handle): string
{
    $line = fgets($handle, 65536);
    rewind($handle);
    if (!is_string($line) || $line === '') {
        return ',';
    }

    $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
    $counts = [
        ',' => substr_count($line, ','),
        ';' => substr_count($line, ';'),
        "\t" => substr_count($line, "\t"),
    ];
    arsort($counts);
    $delimiter = (string) array_key_first($counts);

    return ($counts[$delimiter] ?? 0) > 0 ? $delimiter : ',';
}

/**
 * @param array<int, string|null> $header
 * @return array<string, int>
 */
function postcode_import_column_map(array $header): array
{
    $normalized = [];
    foreach ($header as $index => $label) {
        $normalized[$index] = postcode_import_header_key((string) $label);
    }

    $aliases = [
        'postcode' => ['postcode', 'pincode', 'pin', 'zipcode', 'zip'],
        'city' => ['cityname', 'city', 'officename', 'taluk'],
        'district' => ['district', 'districtname'],
        'state' => ['state', 'statename'],
        'state_code' => ['statecode'],
        'country' => ['country'],
    ];

    $map = [];
    foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
            $index = array_search($name, $normalized, true);
            if ($index !== false) {
                $map[$field] = (int) $index;
                break;
            }
        }
    }

    return $map;
}

function postcode_import_header_key(string $value): string
{
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;

    return $value;
}

/**
 * @param array<int, string|null> $cells
 */
function postcode_import_row_is_blank(array $cells): bool
{
    foreach ($cells as $cell) {
        if (trim((string) $cell) !== '') {
            return false;
        }
    }

    return true;
}

/**
 * @param array<int, string|null> $cells
 * @param array<string, int> $columnMap
 * @param array<string, bool> $seen
 * @return array{row:?array{postcode:string, city:string, district:string, state:string, country:?string, state_code:?string}, error:?string}
 */
function postcode_import_parse_row(array $cells, array $columnMap, array $seen): array
{
    $postcode = postcode_import_cell($cells, $columnMap, 'postcode');
    if (preg_match('/^\d+\.0$/', $postcode)) {
        $postcode = substr($postcode, 0, -2);
    }
    if (!preg_match('/^\d{6}$/', $postcode)) {
        return ['row' => null, 'error' => 'Postcode must be a 6-digit number.'];
    }
    if (isset($seen[$postcode])) {
        return ['row' => null, 'error' => 'Duplicate postcode ' . $postcode . '.'];
    }

    $city = postcode_import_cell($cells, $columnMap, 'city');
    $district = postcode_import_cell($cells, $columnMap, 'district');
    $state = postcode_import_cell($cells, $columnMap, 'state');
    $country = postcode_import_cell($cells, $columnMap, 'country');
    $stateCode = strtoupper(postcode_import_cell($cells, $columnMap, 'state_code'));

    if ($city === '' || $district === '' || $state === '') {
        return ['row' => null, 'error' => 'City, District, and State are required.'];
    }
    if (mb_strlen($city) > 100 || mb_strlen($district) > 100 || mb_strlen($state) > 100) {
        return ['row' => null, 'error' => 'City, District, or State is longer than 100 characters.'];
    }
    if ($country !== '' && mb_strlen($country) > 100) {
        return ['row' => null, 'error' => 'Country is longer than 100 characters.'];
    }
    if ($stateCode !== '' && !preg_match('/^[A-Z0-9]{1,10}$/', $stateCode)) {
        return ['row' => null, 'error' => 'State Code must be 1 to 10 letters or numbers.'];
    }
    foreach ([$postcode, $city, $district, $state, $country, $stateCode] as $value) {
        if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
            return ['row' => null, 'error' => 'A value is not valid text.'];
        }
    }

    return [
        'row' => [
            'postcode' => $postcode,
            'city' => $city,
            'district' => $district,
            'state' => $state,
            'country' => $country !== '' ? $country : null,
            'state_code' => $stateCode !== '' ? $stateCode : null,
        ],
        'error' => null,
    ];
}

/**
 * @param array<int, string|null> $cells
 * @param array<string, int> $columnMap
 */
function postcode_import_cell(array $cells, array $columnMap, string $field): string
{
    if (!isset($columnMap[$field])) {
        return '';
    }

    $value = trim((string) ($cells[$columnMap[$field]] ?? ''));
    if ($value !== '' && !mb_check_encoding($value, 'UTF-8')) {
        $converted = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (is_string($converted)) {
            $value = trim($converted);
        }
    }

    return $value;
}

/**
 * @param array<int, array{postcode:string, city:string, district:string, state:string, country:?string, state_code:?string}> $batch
 */
function postcode_import_insert_batch(PDO $conn, array $batch): void
{
    if ($batch === []) {
        return;
    }

    $values = [];
    $params = [];
    foreach ($batch as $index => $row) {
        $values[] = "(:postcode_{$index}, :city_{$index}, :district_{$index}, :state_{$index}, :country_{$index}, :state_code_{$index})";
        $params[":postcode_{$index}"] = $row['postcode'];
        $params[":city_{$index}"] = $row['city'];
        $params[":district_{$index}"] = $row['district'];
        $params[":state_{$index}"] = $row['state'];
        $params[":country_{$index}"] = $row['country'];
        $params[":state_code_{$index}"] = $row['state_code'];
    }

    $stmt = $conn->prepare('
        INSERT INTO postcodes_import_stage (postcode, city, district, state, country, state_code)
        VALUES ' . implode(', ', $values)
    );
    foreach ($params as $name => $value) {
        if ($value === null) {
            $stmt->bindValue($name, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($name, $value);
        }
    }
    $stmt->execute();
}

function postcode_import_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }

    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    if ($unit === 'g') {
        return (int) ($number * 1073741824);
    }
    if ($unit === 'm') {
        return (int) ($number * 1048576);
    }
    if ($unit === 'k') {
        return (int) ($number * 1024);
    }

    return (int) $number;
}

function postcode_import_upload_error(array $file): ?string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_OK) {
        return null;
    }
    if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
        return 'The CSV file is larger than the server upload limit. Raise upload_max_filesize and post_max_size to at least 20M.';
    }
    if ($error === UPLOAD_ERR_NO_FILE) {
        return 'Choose a CSV file to import.';
    }

    return 'The CSV file could not be uploaded.';
}

session_start();

include 'pdo_obconn.php';
include 'includes/admin_access_helpers.php';

require_system_admin($obconn);

$successMessage = '';
$errorMessage = '';
$importResult = null;
$existingCount = (int) $obconn->query('SELECT COUNT(*) FROM postcodes')->fetchColumn();
$uploadLimit = min(
    postcode_import_ini_bytes((string) ini_get('upload_max_filesize')),
    postcode_import_ini_bytes((string) ini_get('post_max_size'))
);
$uploadLimitReady = $uploadLimit >= POSTCODE_IMPORT_MIN_BYTES;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_postcodes'])) {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 0 && empty($_FILES) && $contentLength > postcode_import_ini_bytes((string) ini_get('post_max_size'))) {
        $errorMessage = 'The CSV file is larger than the server upload limit. Raise upload_max_filesize and post_max_size to at least 20M.';
    } else {
        $file = $_FILES['postcode_csv'] ?? [];
        $uploadError = postcode_import_upload_error(is_array($file) ? $file : []);
        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($uploadError !== null) {
            $errorMessage = $uploadError;
        } elseif ($extension !== 'csv' || !is_uploaded_file($tmpPath)) {
            $errorMessage = 'Upload a .csv file.';
        } else {
            $signature = file_get_contents($tmpPath, false, null, 0, 2);
            if ($signature === 'PK') {
                $errorMessage = 'This file is an Excel workbook. Save it as CSV and upload that file.';
            } else {
                $importResult = postcode_import_from_path($obconn, $tmpPath);
                if ($importResult['error'] !== null) {
                    $errorMessage = $importResult['error'];
                } else {
                    $existingCount = (int) $obconn->query('SELECT COUNT(*) FROM postcodes')->fetchColumn();
                    $successMessage = 'Postcodes imported. Imported: ' . number_format($importResult['imported'])
                        . '. Skipped: ' . number_format($importResult['skipped']) . '.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Postcodes</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet" />
    <link href="css/complaint_buttons.css" rel="stylesheet" />
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php if ($successMessage !== '') { ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($successMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>
            <?php if ($errorMessage !== '') { ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php } ?>

            <div class="page-header">
                <div>
                    <div class="page-subtitle">Replace postcode records from a CSV file.</div>
                </div>
            </div>

            <div class="complaint-form-cardd">
                <div class="complaint-form-header">
                    <div class="complaint-form-header__main">
                        <div class="complaint-form-header__icon"><i class="bi bi-upload"></i></div>
                        <div>
                            <h2 class="complaint-form-header__title">Import Postcodes</h2>
                            <p class="complaint-form-header__subtitle">
                                Current records: <?php echo number_format($existingCount); ?>.
                                The import deletes existing postcodes, then loads the CSV in batches.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data" id="postcodeImportForm">
                    <input type="hidden" name="import_postcodes" value="1">
                    <input type="hidden" name="MAX_FILE_SIZE" value="33554432">
                    <div class="complaint-form-body">
                        <section class="complaint-form-section">
                            <?php if (!$uploadLimitReady) { ?>
                            <div class="alert alert-warning">
                                The server upload limit is below 20 MB. Set upload_max_filesize and post_max_size to at least 20M before importing a 14–15 MB file.
                            </div>
                            <?php } ?>
                            <div class="row g-3">
                                <div class="col-md-8 form-group">
                                    <label class="form-label" for="postcodeCsv">CSV file <span class="text-danger">*</span></label>
                                    <input type="file" class="form-control" id="postcodeCsv" name="postcode_csv" accept=".csv,text/csv" required>
                                    <div class="form-text">
                                        Required columns: pincode, CITY name, District, Statename. Optional: State Code, Country.
                                    </div>
                                </div>
                            </div>
                        </section>
                        <?php if (is_array($importResult) && $importResult['samples'] !== []) { ?>
                        <section class="complaint-form-section">
                            <p class="mb-2">Skipped rows (first <?php echo count($importResult['samples']); ?>):</p>
                            <ul class="mb-0">
                                <?php foreach ($importResult['samples'] as $sample) { ?>
                                <li><?php echo htmlspecialchars($sample); ?></li>
                                <?php } ?>
                            </ul>
                        </section>
                        <?php } ?>
                    </div>
                    <div class="complaint-form-actions">
                        <button class="submit-btn btn-complaint-primary" type="submit">
                            <i class="bi bi-upload"></i> Import Postcodes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>