<?php

function service_report_allowed_extensions(): array
{
    return ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
}

function service_report_max_file_size(): int
{
    return 2 * 1024 * 1024;
}

function service_report_allowed_extensions_label(): string
{
    return 'PDF, JPG, PNG, DOC, DOCX';
}

/**
 * @return array<int, array{name: string, tmp_name: string, error: int, size: int}>
 */
function service_report_normalize_uploads(?array $fileField): array
{
    if ($fileField === null || !isset($fileField['name'])) {
        return [];
    }

    $files = [];

    if (!is_array($fileField['name'])) {
        if ((int) $fileField['error'] === UPLOAD_ERR_OK) {
            $files[] = [
                'name' => (string) $fileField['name'],
                'tmp_name' => (string) $fileField['tmp_name'],
                'error' => (int) $fileField['error'],
                'size' => (int) $fileField['size'],
            ];
        }

        return $files;
    }

    foreach ($fileField['name'] as $index => $name) {
        if ((int) ($fileField['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $files[] = [
            'name' => (string) $name,
            'tmp_name' => (string) $fileField['tmp_name'][$index],
            'error' => (int) $fileField['error'][$index],
            'size' => (int) $fileField['size'][$index],
        ];
    }

    return $files;
}

function service_report_validate_uploads(?array $fileField): ?string
{
    $files = service_report_normalize_uploads($fileField);

    if (empty($files)) {
        return 'At least one service report file is required';
    }

    $allowedExtensions = service_report_allowed_extensions();
    $maxFileSize = service_report_max_file_size();

    foreach ($files as $file) {
        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExtensions, true)) {
            return 'Invalid file type for "' . $file['name'] . '". Allowed: '
                . service_report_allowed_extensions_label() . '.';
        }

        if ($file['size'] > $maxFileSize) {
            return 'File "' . $file['name'] . '" must be 2 MB or smaller.';
        }
    }

    return null;
}

/**
 * @return array{stored: string, paths: string[]}
 */
function service_report_store_uploads(array $files, string $uploadDir): array
{
    $storedNames = [];
    $storedPaths = [];

    foreach ($files as $file) {
        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        $storedName = uniqid('service_report_', true) . '.' . $extension;
        $targetPath = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        $storedNames[] = $storedName;
        $storedPaths[] = $targetPath;
    }

    return [
        'stored' => json_encode($storedNames, JSON_UNESCAPED_UNICODE),
        'paths' => $storedPaths,
    ];
}

function service_report_delete_files(array $paths): void
{
    foreach ($paths as $path) {
        if (is_string($path) && is_file($path)) {
            unlink($path);
        }
    }
}

/**
 * @return string[]
 */
function service_report_parse_filenames(?string $stored): array
{
    if ($stored === null || trim($stored) === '') {
        return [];
    }

    $decoded = json_decode($stored, true);

    if (is_array($decoded)) {
        return array_values(array_filter(array_map('strval', $decoded)));
    }

    return [trim($stored)];
}