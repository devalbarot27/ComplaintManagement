<?php

require_once __DIR__ . '/complaint_assignment_helpers.php';
require_once __DIR__ . '/complaint_status.php';
require_once __DIR__ . '/rbac_access_helpers.php';
require_once __DIR__ . '/service_log_helpers.php';
require_once __DIR__ . '/after_market_access_helpers.php';
require_once __DIR__ . '/service_log_draft_helpers.php';

function complaint_service_log_mapping_table(): string
{
    return 'complaint_service_logs';
}

function complaint_service_log_service_update_statuses(): array
{
    return [COMPLAINT_STATUS_IN_PROGRESS, COMPLAINT_STATUS_REOPEN];
}

function complaint_service_log_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $table = complaint_service_log_mapping_table();

    $stmt = $conn->query("
        SELECT 1
        FROM information_schema.tables
        WHERE table_name = '{$table}'
        LIMIT 1
    ");
    if (!$stmt->fetchColumn()) {
        $conn->exec('
            CREATE TABLE complaint_service_logs (
                id SERIAL PRIMARY KEY,
                complaint_id INTEGER NOT NULL,
                service_log_id INTEGER NOT NULL,
                complaint_status INTEGER NOT NULL,
                reopen_cycle_number INTEGER NULL,
                created_by INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $conn->exec('
            CREATE UNIQUE INDEX complaint_service_logs_service_log_id_unique
                ON complaint_service_logs (service_log_id)
        ');

        $conn->exec('
            CREATE UNIQUE INDEX complaint_service_logs_in_progress_unique
                ON complaint_service_logs (complaint_id)
                WHERE complaint_status = ' . COMPLAINT_STATUS_IN_PROGRESS . '
        ');

        $conn->exec('
            CREATE UNIQUE INDEX complaint_service_logs_reopen_cycle_unique
                ON complaint_service_logs (complaint_id, reopen_cycle_number)
                WHERE complaint_status = ' . COMPLAINT_STATUS_REOPEN . '
                  AND reopen_cycle_number IS NOT NULL
        ');
    }

    complaint_service_log_migrate_from_service_logs($conn);
    complaint_service_log_drop_service_log_complaint_columns($conn);

    $ensured = true;
}

function complaint_service_log_migrate_from_service_logs(PDO $conn): void
{
    $stmt = $conn->query("
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'service_logs'
          AND column_name = 'complaint_id'
        LIMIT 1
    ");
    if (!$stmt->fetchColumn()) {
        return;
    }

    $conn->exec('
        INSERT INTO complaint_service_logs (
            complaint_id,
            service_log_id,
            complaint_status,
            reopen_cycle_number,
            created_by,
            created_at
        )
        SELECT
            sl.complaint_id,
            sl.id,
            COALESCE(sl.complaint_status, ' . COMPLAINT_STATUS_IN_PROGRESS . '),
            CASE
                WHEN COALESCE(sl.complaint_status, ' . COMPLAINT_STATUS_IN_PROGRESS . ') = ' . COMPLAINT_STATUS_REOPEN . ' THEN GREATEST(
                    1,
                    (
                        SELECT COUNT(*)
                        FROM complaint_assignments ca
                        WHERE ca.complaint_id = sl.complaint_id
                          AND (
                              sl.complaint_assignment_id IS NULL
                              OR ca.id <= sl.complaint_assignment_id
                          )
                    ) - 1
                )
                ELSE NULL
            END,
            sl.created_by,
            COALESCE(sl.created_at, CURRENT_TIMESTAMP)
        FROM service_logs sl
        WHERE sl.complaint_id IS NOT NULL
          AND sl.deleted_at IS NULL
          AND NOT EXISTS (
              SELECT 1
              FROM complaint_service_logs csl
              WHERE csl.service_log_id = sl.id
          )
    ');
}

function complaint_service_log_drop_service_log_complaint_columns(PDO $conn): void
{
    $conn->exec('DROP INDEX IF EXISTS service_logs_complaint_in_progress_unique');
    $conn->exec('DROP INDEX IF EXISTS service_logs_complaint_reopen_unique');
    $conn->exec('DROP INDEX IF EXISTS service_logs_complaint_id_unique');

    foreach (['complaint_assignment_id', 'complaint_status', 'complaint_id'] as $column) {
        $stmt = $conn->prepare('
            SELECT 1
            FROM information_schema.columns
            WHERE table_name = \'service_logs\'
              AND column_name = :column_name
            LIMIT 1
        ');
        $stmt->bindValue(':column_name', $column);
        $stmt->execute();

        if ($stmt->fetchColumn()) {
            $conn->exec('ALTER TABLE service_logs DROP COLUMN ' . $column);
        }
    }
}

function complaint_service_log_get_installed_base_row(PDO $conn, int $installedBaseId): ?array
{
    if ($installedBaseId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, machine_model_code, running_hours
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_service_log_action_permissions(PDO $conn): array
{
    $serviceLogPermissions = service_log_action_permissions($conn);

    return [
        'add' => $serviceLogPermissions['add'],
        'edit' => $serviceLogPermissions['edit'],
        'view' => $serviceLogPermissions['view'],
    ];
}

function complaint_service_log_get_complaint(PDO $conn, int $complaintId): ?array
{
    if ($complaintId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, fab_number, customer_name, complaint_description, status
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_service_log_count_assignments(PDO $conn, int $complaintId): int
{
    if ($complaintId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function complaint_service_log_resolve_reopen_cycle_number(PDO $conn, int $complaintId): int
{
    $assignmentCount = complaint_service_log_count_assignments($conn, $complaintId);

    return max(1, $assignmentCount - 1);
}

function complaint_service_log_resolve_cycle_context(PDO $conn, int $complaintId): ?array
{
    $complaint = complaint_service_log_get_complaint($conn, $complaintId);
    if (!$complaint) {
        return null;
    }

    $status = (int) ($complaint['status'] ?? 0);
    if (!in_array($status, complaint_service_log_service_update_statuses(), true)) {
        return null;
    }

    if (complaint_service_log_count_assignments($conn, $complaintId) <= 0) {
        return null;
    }

    $reopenCycleNumber = null;
    if ($status === COMPLAINT_STATUS_REOPEN) {
        $reopenCycleNumber = complaint_service_log_resolve_reopen_cycle_number($conn, $complaintId);
    }

    return [
        'complaint_id' => $complaintId,
        'complaint_status' => $status,
        'complaint_status_label' => complaint_status_label($status),
        'reopen_cycle_number' => $reopenCycleNumber,
        'cycle' => $status === COMPLAINT_STATUS_REOPEN ? 'reopen' : 'in_progress',
    ];
}

function complaint_service_log_resolve_installed_base(PDO $conn, int $complaintId, string $username = ''): ?array
{
    unset($username);

    $complaint = complaint_service_log_get_complaint($conn, $complaintId);
    if (!$complaint) {
        return null;
    }

    $fabNumber = trim((string) ($complaint['fab_number'] ?? ''));
    if ($fabNumber === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, machine_model_code, running_hours
        FROM installed_base
        WHERE fab_number = :fab_number
          AND deleted_at IS NULL
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return null;
    }

    $installedBaseId = (int) ($row['id'] ?? 0);
    if ($installedBaseId <= 0) {
        return null;
    }

    return $row;
}

function complaint_service_log_find_mapping(
    PDO $conn,
    int $complaintId,
    int $complaintStatus,
    ?int $reopenCycleNumber = null
): ?array {
    complaint_service_log_ensure_schema($conn);

    if ($complaintId <= 0) {
        return null;
    }

    $sql = '
        SELECT csl.*
        FROM complaint_service_logs csl
        INNER JOIN service_logs sl ON sl.id = csl.service_log_id AND sl.deleted_at IS NULL
        WHERE csl.complaint_id = :complaint_id
          AND csl.complaint_status = :complaint_status
    ';

    if ($complaintStatus === COMPLAINT_STATUS_REOPEN) {
        $sql .= ' AND csl.reopen_cycle_number = :reopen_cycle_number';
    }

    $sql .= ' ORDER BY csl.id DESC LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':complaint_status', $complaintStatus, PDO::PARAM_INT);
    if ($complaintStatus === COMPLAINT_STATUS_REOPEN) {
        $stmt->bindValue(':reopen_cycle_number', (int) $reopenCycleNumber, PDO::PARAM_INT);
    }
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_service_log_find_service_log_for_mapping(PDO $conn, ?array $mapping): ?array
{
    if (!$mapping) {
        return null;
    }

    $serviceLogId = (int) ($mapping['service_log_id'] ?? 0);
    if ($serviceLogId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT sl.*
        FROM service_logs sl
        WHERE sl.id = :id
          AND sl.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_service_log_find_in_progress(PDO $conn, int $complaintId): ?array
{
    $mapping = complaint_service_log_find_mapping($conn, $complaintId, COMPLAINT_STATUS_IN_PROGRESS);

    return complaint_service_log_find_service_log_for_mapping($conn, $mapping);
}

function complaint_service_log_find_reopen_for_cycle(PDO $conn, int $complaintId, int $reopenCycleNumber): ?array
{
    if ($reopenCycleNumber <= 0) {
        return null;
    }

    $mapping = complaint_service_log_find_mapping(
        $conn,
        $complaintId,
        COMPLAINT_STATUS_REOPEN,
        $reopenCycleNumber
    );

    return complaint_service_log_find_service_log_for_mapping($conn, $mapping);
}

function complaint_service_log_find_current_cycle(PDO $conn, int $complaintId): ?array
{
    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return null;
    }

    if ((int) $context['complaint_status'] === COMPLAINT_STATUS_REOPEN) {
        return complaint_service_log_find_reopen_for_cycle(
            $conn,
            $complaintId,
            (int) $context['reopen_cycle_number']
        );
    }

    return complaint_service_log_find_in_progress($conn, $complaintId);
}

function complaint_service_log_find_by_complaint(PDO $conn, int $complaintId, string $username = ''): ?array
{
    unset($username);

    return complaint_service_log_find_current_cycle($conn, $complaintId);
}

function complaint_service_log_cycle_has_log(
    PDO $conn,
    int $complaintId,
    int $complaintStatus,
    ?int $reopenCycleNumber = null
): bool {
    complaint_service_log_ensure_schema($conn);

    if ($complaintId <= 0) {
        return false;
    }

    if ($complaintStatus === COMPLAINT_STATUS_IN_PROGRESS) {
        return complaint_service_log_find_in_progress($conn, $complaintId) !== null;
    }

    if ($complaintStatus === COMPLAINT_STATUS_REOPEN) {
        if ($reopenCycleNumber === null || $reopenCycleNumber <= 0) {
            return false;
        }

        return complaint_service_log_find_reopen_for_cycle($conn, $complaintId, $reopenCycleNumber) !== null;
    }

    return false;
}

function complaint_service_log_complaint_has_log(PDO $conn, int $complaintId): bool
{
    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return false;
    }

    return complaint_service_log_cycle_has_log(
        $conn,
        $complaintId,
        (int) $context['complaint_status'],
        $context['reopen_cycle_number'] !== null ? (int) $context['reopen_cycle_number'] : null
    );
}

function complaint_service_log_is_linked(PDO $conn, int $complaintId, int $serviceLogId): bool
{
    complaint_service_log_ensure_schema($conn);

    if ($complaintId <= 0 || $serviceLogId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM complaint_service_logs
        WHERE complaint_id = :complaint_id
          AND service_log_id = :service_log_id
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function complaint_service_log_find_raw_mapping(
    PDO $conn,
    int $complaintId,
    int $complaintStatus,
    ?int $reopenCycleNumber = null
): ?array {
    complaint_service_log_ensure_schema($conn);

    if ($complaintId <= 0) {
        return null;
    }

    $sql = '
        SELECT *
        FROM complaint_service_logs
        WHERE complaint_id = :complaint_id
          AND complaint_status = :complaint_status
    ';

    if ($complaintStatus === COMPLAINT_STATUS_REOPEN) {
        $sql .= ' AND reopen_cycle_number = :reopen_cycle_number';
    } else {
        $sql .= ' AND reopen_cycle_number IS NULL';
    }

    $sql .= ' ORDER BY id DESC LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':complaint_status', $complaintStatus, PDO::PARAM_INT);
    if ($complaintStatus === COMPLAINT_STATUS_REOPEN) {
        $stmt->bindValue(':reopen_cycle_number', (int) $reopenCycleNumber, PDO::PARAM_INT);
    }
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_service_log_insert_mapping(
    PDO $conn,
    int $complaintId,
    int $serviceLogId,
    int $complaintStatus,
    ?int $reopenCycleNumber,
    int $createdBy
): void {
    complaint_service_log_ensure_schema($conn);

    $existing = complaint_service_log_find_raw_mapping($conn, $complaintId, $complaintStatus, $reopenCycleNumber);
    if ($existing) {
        $update = $conn->prepare('
            UPDATE complaint_service_logs
            SET service_log_id = :service_log_id,
                created_by = :created_by,
                created_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ');
        $update->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
        $update->bindValue(':created_by', $createdBy > 0 ? $createdBy : null, $createdBy > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $update->bindValue(':id', (int) $existing['id'], PDO::PARAM_INT);
        $update->execute();

        return;
    }

    $insert = $conn->prepare('
        INSERT INTO complaint_service_logs (
            complaint_id,
            service_log_id,
            complaint_status,
            reopen_cycle_number,
            created_by
        ) VALUES (
            :complaint_id,
            :service_log_id,
            :complaint_status,
            :reopen_cycle_number,
            :created_by
        )
    ');

    $insert->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $insert->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $insert->bindValue(':complaint_status', $complaintStatus, PDO::PARAM_INT);
    if ($reopenCycleNumber !== null && $reopenCycleNumber > 0) {
        $insert->bindValue(':reopen_cycle_number', $reopenCycleNumber, PDO::PARAM_INT);
    } else {
        $insert->bindValue(':reopen_cycle_number', null, PDO::PARAM_NULL);
    }
    $insert->bindValue(':created_by', $createdBy > 0 ? $createdBy : null, $createdBy > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $insert->execute();
}

function complaint_service_log_machine_model_part_label(PDO $conn, array $serviceLog): string
{
    if (service_log_part_replaced_is_yes((string) ($serviceLog['part_replaced'] ?? ''))) {
        $entries = service_log_part_replacements_for_service_log($conn, (int) ($serviceLog['id'] ?? 0));
        $labels = [];

        foreach ($entries as $entry) {
            $code = trim((string) ($entry['machine_model_code'] ?? ''));
            $description = trim((string) ($entry['machine_model'] ?? ''));
            if ($code !== '' && $description !== '') {
                $labels[] = $code . ' - ' . $description;
            } elseif ($code !== '') {
                $labels[] = $code;
            } elseif ($description !== '') {
                $labels[] = $description;
            }
        }

        if ($labels !== []) {
            return implode(', ', $labels);
        }
    }

    return service_log_display_value($serviceLog['machine_model'] ?? null);
}

function complaint_service_log_summary_row(PDO $conn, array $serviceLog, ?array $mapping = null): array
{
    $serviceLogId = (int) ($serviceLog['id'] ?? 0);
    $serialNumber = service_log_format_serial_number_for_display($serviceLog['serial_number'] ?? null);
    $serviceLogNumber = $serialNumber !== '-' ? $serialNumber : ('#' . $serviceLogId);

    $row = [
        'id' => $serviceLogId,
        'is_draft' => service_log_is_draft_value($serviceLog['is_draft'] ?? 0),
        'service_log_number' => $serviceLogNumber,
        'complaint_date' => service_log_format_date($serviceLog['complaint_date'] ?? null),
        'machine_model_part' => complaint_service_log_machine_model_part_label($conn, $serviceLog),
        'running_hours' => service_log_display_value($serviceLog['running_hours'] ?? null),
        'engineer_name' => service_log_display_value($serviceLog['engineer_name'] ?? null),
    ];

    if ($mapping) {
        $row['complaint_status'] = (int) ($mapping['complaint_status'] ?? 0);
        $row['reopen_cycle_number'] = isset($mapping['reopen_cycle_number'])
            ? (int) $mapping['reopen_cycle_number']
            : null;
    }

    return $row;
}

function complaint_service_log_summary_row_for_cycle(
    PDO $conn,
    int $complaintId,
    int $complaintStatus,
    ?int $reopenCycleNumber = null
): ?array {
    $mapping = complaint_service_log_find_mapping($conn, $complaintId, $complaintStatus, $reopenCycleNumber);
    $serviceLog = complaint_service_log_find_service_log_for_mapping($conn, $mapping);
    if (!$serviceLog) {
        return null;
    }

    return complaint_service_log_summary_row($conn, $serviceLog, $mapping);
}

function complaint_service_log_summary_payload(PDO $conn, int $complaintId, string $username = ''): array
{
    unset($username);
    complaint_service_log_ensure_schema($conn);
    $permissions = complaint_service_log_action_permissions($conn);

    if (!complaint_user_can_access_assigned_complaint($conn, $complaintId)) {
        return [
            'success' => false,
            'error' => 'Access denied. You do not have permission to view this complaint.',
        ];
    }

    $complaint = complaint_service_log_get_complaint($conn, $complaintId);
    if (!$complaint) {
        return [
            'success' => false,
            'error' => 'Complaint not found.',
        ];
    }

    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return [
            'success' => false,
            'error' => 'Service log is only available for complaints in progress or re-open.',
        ];
    }

    $installedBase = complaint_service_log_resolve_installed_base($conn, $complaintId);
    if (!$installedBase) {
        $fabNumber = trim((string) ($complaint['fab_number'] ?? ''));
        $canAddInstalledBase = !empty(installed_base_action_permissions($conn)['add']);
        $addUrlParams = ['open_form' => '1'];
        if ($fabNumber !== '') {
            $addUrlParams['fab_number'] = $fabNumber;
        }

        return [
            'success' => true,
            'has_installed_base' => false,
            'has_service_log' => false,
            'permissions' => $permissions,
            'complaint_status' => (int) $context['complaint_status'],
            'complaint_status_label' => $context['complaint_status_label'],
            'current_cycle' => $context['cycle'],
            'fab_number' => $fabNumber,
            'can_add_installed_base' => $canAddInstalledBase,
            'installed_base_add_url' => 'installed_base.php?' . http_build_query($addUrlParams),
            'message' => 'No installed base record found for this complaint Fab Number.',
        ];
    }

    $installedBaseId = (int) $installedBase['id'];
    $installedBaseLabel = '#' . $installedBaseId
        . ' - ' . ($installedBase['order_id'] ?? '')
        . ' - ' . ($installedBase['fab_number'] ?? '')
        . ' - ' . ($installedBase['customer_name'] ?? '');

    $currentCycleSummary = complaint_service_log_summary_row_for_cycle(
        $conn,
        $complaintId,
        (int) $context['complaint_status'],
        $context['reopen_cycle_number'] !== null ? (int) $context['reopen_cycle_number'] : null
    );
    $hasCurrentCycleLog = $currentCycleSummary !== null;

    $currentCycleLabel = $context['complaint_status_label'];
    if ((int) $context['complaint_status'] === COMPLAINT_STATUS_REOPEN && $context['reopen_cycle_number'] !== null) {
        $currentCycleLabel .= ' (Cycle ' . (int) $context['reopen_cycle_number'] . ')';
    }

    $payload = [
        'success' => true,
        'has_installed_base' => true,
        'has_service_log' => $hasCurrentCycleLog,
        'installed_base_id' => $installedBaseId,
        'installed_base_label' => $installedBaseLabel,
        'permissions' => $permissions,
        'complaint_id' => $complaintId,
        'complaint_status' => (int) $context['complaint_status'],
        'complaint_status_label' => $context['complaint_status_label'],
        'current_cycle' => $context['cycle'],
        'current_cycle_label' => $currentCycleLabel,
        'reopen_cycle_number' => $context['reopen_cycle_number'],
    ];

    if ($hasCurrentCycleLog) {
        $payload['service_log'] = $currentCycleSummary;
        $payload['current_cycle_service_log'] = $currentCycleSummary;
    }

    return $payload;
}

function complaint_service_log_prefill_payload(PDO $conn, int $complaintId, string $username = ''): array
{
    unset($username);

    if (!complaint_user_can_access_assigned_complaint($conn, $complaintId)) {
        return [
            'success' => false,
            'error' => 'Access denied. You do not have permission to view this complaint.',
        ];
    }

    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return [
            'success' => false,
            'error' => 'Service log can only be added for complaints in progress or re-open.',
        ];
    }

    $installedBase = complaint_service_log_resolve_installed_base($conn, $complaintId);
    if (!$installedBase) {
        return [
            'success' => false,
            'error' => 'No installed base record found for this complaint Fab Number.',
        ];
    }

    if (complaint_service_log_cycle_has_log(
        $conn,
        $complaintId,
        (int) $context['complaint_status'],
        $context['reopen_cycle_number'] !== null ? (int) $context['reopen_cycle_number'] : null
    )) {
        $statusLabel = $context['complaint_status_label'];
        return [
            'success' => false,
            'error' => 'A service log already exists for the ' . $statusLabel . ' cycle. Please edit the existing record.',
        ];
    }

    $installedBaseId = (int) $installedBase['id'];
    $label = '#' . $installedBaseId
        . ' - ' . ($installedBase['order_id'] ?? '')
        . ' - ' . ($installedBase['fab_number'] ?? '')
        . ' - ' . ($installedBase['customer_name'] ?? '');

    return [
        'success' => true,
        'complaint_id' => $complaintId,
        'complaint_status' => (int) $context['complaint_status'],
        'reopen_cycle_number' => $context['reopen_cycle_number'],
        'current_cycle' => $context['cycle'],
        'installed_base_id' => $installedBaseId,
        'installed_base_label' => $label,
        'order_id' => $installedBase['order_id'] ?? '',
        'fab_number' => $installedBase['fab_number'] ?? '',
        'machine_model' => service_log_machine_model_from_installed_base($installedBase),
        'serial_number' => service_log_peek_next_serial_number_safe($conn),
        'complaint_description' => trim((string) (complaint_service_log_get_complaint($conn, $complaintId)['complaint_description'] ?? '')),
    ];
}

function complaint_service_log_validate_for_service_update(PDO $conn, int $complaintId): ?string
{
    complaint_service_log_ensure_schema($conn);

    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return 'Service update is only allowed for complaints in progress or re-open.';
    }

    $installedBase = complaint_service_log_resolve_installed_base($conn, $complaintId);
    if (!$installedBase) {
        return 'A matching installed base is required before a service log can be added.';
    }

    
    // TODO: Uncomment this when we have a way to add service logs to complaints that don't have them yet.
    // 09-07-2026
    $serviceLog = complaint_service_log_find_current_cycle($conn, $complaintId);
    if (!$serviceLog) {
        $statusLabel = $context['complaint_status_label'];
        return 'A service log is required for the ' . $statusLabel . ' cycle before submitting the service update.';
    }
    
    if (service_log_is_draft_value($serviceLog['is_draft'] ?? 0)) {
        return 'Please complete the service log before submitting the service update.';
    }

    return null;
}

function complaint_service_log_require_assigned_access(PDO $conn, int $complaintId): void
{
    if (empty($_SESSION['usr_name'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized.']);
        exit;
    }

    if (!rbac_user_can($conn, 'assigned-complaint-list', 'service-update')) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied. You do not have permission for this action.']);
        exit;
    }

    if ($complaintId <= 0 || !complaint_user_can_access_assigned_complaint($conn, $complaintId)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied. You do not have permission to update this complaint.']);
        exit;
    }
}

function complaint_service_log_context_for_create(PDO $conn, int $complaintId): array
{
    complaint_service_log_ensure_schema($conn);

    if ($complaintId <= 0) {
        return ['success' => false, 'message' => 'Complaint reference is required.'];
    }

    if (!complaint_user_can_access_assigned_complaint($conn, $complaintId)) {
        return ['success' => false, 'message' => 'Access denied. You do not have permission to update this complaint.'];
    }

    $context = complaint_service_log_resolve_cycle_context($conn, $complaintId);
    if (!$context) {
        return ['success' => false, 'message' => 'Service log can only be added for complaints in progress or re-open.'];
    }

    if (complaint_service_log_cycle_has_log(
        $conn,
        $complaintId,
        (int) $context['complaint_status'],
        $context['reopen_cycle_number'] !== null ? (int) $context['reopen_cycle_number'] : null
    )) {
        return [
            'success' => false,
            'message' => 'A service log already exists for the ' . $context['complaint_status_label'] . ' cycle. Please edit the existing record.',
        ];
    }

    return [
        'success' => true,
        'complaint_status' => (int) $context['complaint_status'],
        'reopen_cycle_number' => $context['reopen_cycle_number'] !== null
            ? (int) $context['reopen_cycle_number']
            : null,
    ];
}

function complaint_service_log_create_mapping_for_service_log(
    PDO $conn,
    int $complaintId,
    int $serviceLogId,
    array $createContext,
    int $createdBy
): void {
    complaint_service_log_insert_mapping(
        $conn,
        $complaintId,
        $serviceLogId,
        (int) $createContext['complaint_status'],
        isset($createContext['reopen_cycle_number']) && $createContext['reopen_cycle_number'] !== null
            ? (int) $createContext['reopen_cycle_number']
            : null,
        $createdBy
    );
}

function complaint_service_log_details_view_url_for_id(int $serviceLogId): string
{
    return 'service_log_details.php?id=' . rawurlencode(base64_encode((string) $serviceLogId));
}

function complaint_service_log_assignment_rank(PDO $conn, int $complaintId, int $assignmentId): int
{
    if ($complaintId <= 0 || $assignmentId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('
        SELECT COUNT(*)
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
          AND id <= :assignment_id
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->bindValue(':assignment_id', $assignmentId, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function complaint_service_log_service_log_id_for_assignment(PDO $conn, int $complaintId, int $assignmentId): ?int
{
    complaint_service_log_ensure_schema($conn);

    $rank = complaint_service_log_assignment_rank($conn, $complaintId, $assignmentId);
    if ($rank <= 0) {
        return null;
    }

    $serviceLog = $rank === 1
        ? complaint_service_log_find_in_progress($conn, $complaintId)
        : complaint_service_log_find_reopen_for_cycle($conn, $complaintId, $rank - 1);

    if (!$serviceLog) {
        return null;
    }

    $serviceLogId = (int) ($serviceLog['id'] ?? 0);

    return $serviceLogId > 0 ? $serviceLogId : null;
}

function complaint_service_log_view_url_for_assignment(PDO $conn, int $complaintId, int $assignmentId): ?string
{
    $serviceLogId = complaint_service_log_service_log_id_for_assignment($conn, $complaintId, $assignmentId);
    if ($serviceLogId === null) {
        return null;
    }

    return complaint_service_log_details_view_url_for_id($serviceLogId);
}

function complaint_service_log_attach_view_urls_to_service_updates(PDO $conn, int $complaintId, array $serviceUpdates): array
{
    foreach ($serviceUpdates as $index => $serviceUpdate) {
        $assignmentId = (int) ($serviceUpdate['assignment_id'] ?? 0);
        $serviceUpdates[$index]['service_log_view_url'] = $assignmentId > 0
            ? complaint_service_log_view_url_for_assignment($conn, $complaintId, $assignmentId)
            : null;
    }

    return $serviceUpdates;
}

/**
 * Resolve mapping/link row for a service log from complaint_service_logs
 * (complaint ? service log association table).
 */
function complaint_service_log_find_mapping_by_service_log(PDO $conn, int $serviceLogId): ?array
{
    complaint_service_log_ensure_schema($conn);

    if ($serviceLogId <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM complaint_service_logs
        WHERE service_log_id = :service_log_id
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * Latest assignment engineer label for a complaint.
 */
function complaint_service_log_latest_assigned_engineer(PDO $conn, int $complaintId): string
{
    if ($complaintId <= 0) {
        return '';
    }

    $stmt = $conn->prepare('
        SELECT assign_complaint
        FROM complaint_assignments
        WHERE complaint_id = :complaint_id
        ORDER BY assign_complaint_datetime DESC, id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    return trim((string) ($stmt->fetchColumn() ?: ''));
}

/**
 * Fetch complaint details linked to a service log for details-page display.
 *
 * @return array{
 *     associated: bool,
 *     mapping: ?array,
 *     complaint: ?array,
 *     assigned_engineer: string,
 *     machine_model: string,
 *     customer_number: string,
 *     closure_datetime: string,
 *     complaint_view_url: ?string
 * }
 */
function complaint_service_log_linked_complaint_context(PDO $conn, int $serviceLogId): array
{
    $empty = [
        'associated' => false,
        'mapping' => null,
        'complaint' => null,
        'assigned_engineer' => '',
        'machine_model' => '',
        'customer_number' => '',
        'closure_datetime' => '',
        'complaint_view_url' => null,
    ];

    $mapping = complaint_service_log_find_mapping_by_service_log($conn, $serviceLogId);
    if (!$mapping) {
        return $empty;
    }

    $complaintId = (int) ($mapping['complaint_id'] ?? 0);
    if ($complaintId <= 0) {
        return $empty;
    }

    $stmt = $conn->prepare("
        SELECT
            c.*,
            COALESCE(
                NULLIF(TRIM(um.name), ''),
                NULLIF(TRIM(um.username), ''),
                NULLIF(TRIM(c.username), ''),
                '-'
            ) AS added_by_name
        FROM complaints c
        LEFT JOIN user_master um
            ON um.id = c.added_by
           AND um.deleted_at IS NULL
        WHERE c.id = :id
          AND c.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$complaint) {
        return array_merge($empty, [
            'associated' => true,
            'mapping' => $mapping,
        ]);
    }

    $machineModel = '';
    $customerNumber = '';
    $fabNumber = trim((string) ($complaint['fab_number'] ?? ''));
    if ($fabNumber !== '') {
        $ib = complaint_service_log_resolve_installed_base($conn, $complaintId);
        if ($ib) {
            if (function_exists('installed_base_machine_model_label')) {
                $machineModel = trim((string) installed_base_machine_model_label($ib));
            } else {
                $machineModel = trim((string) ($ib['machine_model'] ?? ''));
            }
            $customerNumber = trim((string) ($ib['mobile'] ?? ''));
        }
    }

    $closureDatetime = '';
    $closureStmt = $conn->prepare('
        SELECT COALESCE(cc.closure_datetime, cc.created_at) AS closure_datetime
        FROM complaint_closures cc
        WHERE cc.complaint_id = :complaint_id
          AND cc.call_closure::text = \'Yes\'
        ORDER BY cc.created_at DESC, cc.id DESC
        LIMIT 1
    ');
    $closureStmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $closureStmt->execute();
    $closureDatetime = trim((string) ($closureStmt->fetchColumn() ?: ''));

    return [
        'associated' => true,
        'mapping' => $mapping,
        'complaint' => $complaint,
        'assigned_engineer' => complaint_service_log_latest_assigned_engineer($conn, $complaintId),
        'machine_model' => $machineModel,
        'customer_number' => $customerNumber,
        'closure_datetime' => $closureDatetime,
        'complaint_view_url' => 'complaint_details.php?id='
            . rawurlencode(base64_encode((string) $complaintId))
            . '&from=entry',
    ];
}