<?php

require_once __DIR__ . '/complaint_service_log_helpers.php';

function complaint_service_update_missing_service_log_message(): string
{
    return 'Please add a Service Log to proceed further.';
}

function complaint_service_update_draft_blocked_message(): string
{
    return 'Service Log is in Draft. It will not be sent for HO Approval. Please submit the Service Log before updating the complaint.';
}

function complaint_service_update_ensure_schema(PDO $conn): void
{
    $conn->exec('
        ALTER TABLE complaint_service_updates
        ADD COLUMN IF NOT EXISTS distance_travelled NUMERIC(10, 2) NULL
    ');
}

function complaint_service_update_normalize_distance(?string $distance): ?string
{
    $distance = trim((string) $distance);
    if ($distance === '' || !is_numeric($distance)) {
        return null;
    }

    $value = round((float) $distance, 2);
    if ($value < 0) {
        return null;
    }

    return number_format($value, 2, '.', '');
}

function complaint_service_update_validate_service_log(PDO $conn, int $complaintId): ?string
{
    complaint_service_log_ensure_schema($conn);
    complaint_service_update_ensure_schema($conn);

    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return 'Service update is only allowed for complaints in progress or re-open.';
    }

    $installedBase = complaint_service_log_resolve_installed_base($conn, $complaintId);
    if (!$installedBase) {
        return 'A matching installed base is required before a service log can be added.';
    }

    $serviceLog = complaint_service_log_find_current_cycle($conn, $complaintId);
    if (!$serviceLog) {
        return complaint_service_update_missing_service_log_message();
    }

    if (service_log_is_draft_value($serviceLog['is_draft'] ?? 0)) {
        return complaint_service_update_draft_blocked_message();
    }

    return null;
}