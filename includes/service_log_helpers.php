<?php
require_once __DIR__ . '/installed_base_helpers.php';
require_once __DIR__ . '/system_config_master_helpers.php';

function service_log_warranty_types(PDO $conn): array
{
    return scm_get_active_names($conn, 'warranty_chargeable');
}

function service_log_engineers(): array
{
    return [
        'Rajesh Kumar',
        'Amit Sharma',
        'Suresh Patel',
        'Vikram Singh',
        'Anil Mehta',
    ];
}

function service_log_part_replaced_options(PDO $conn): array
{
    return scm_get_active_names($conn, 'part_replaced');
}

function service_log_customer_feedback_options(PDO $conn): array
{
    return scm_get_active_names($conn, 'customer_feedback');
}

function service_log_remaining_consumable_fields(): array
{
    return [
        ['key' => 'separator', 'label' => 'Separator'],
        ['key' => 'air_filter', 'label' => 'Air Filter'],
        ['key' => 'oil_filter', 'label' => 'Oil Filter'],
        ['key' => 'oil', 'label' => 'Oil'],
        ['key' => 'valve_kit', 'label' => 'Valve Kit'],
        ['key' => 'grease', 'label' => 'Grease'],
    ];
}

function service_log_remaining_consumable_column_names(): array
{
    $columns = [];

    foreach (service_log_remaining_consumable_fields() as $item) {
        $columns[] = $item['key'] . '_remaining_date';
        $columns[] = $item['key'] . '_remaining_hours';
    }

    return $columns;
}

function service_log_from_post(array $post): array
{
    $data = [
        'installed_base_id' => trim((string) ($post['installed_base_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'serial_number' => trim((string) ($post['serial_number'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
        'warranty_chargeable' => trim((string) ($post['warranty_chargeable'] ?? '')),
        'complaint_date' => trim((string) ($post['complaint_date'] ?? '')),
        'issue_description' => trim((string) ($post['issue_description'] ?? '')),
        'engineer_name' => trim((string) ($post['engineer_name'] ?? '')),
        'visit_date' => trim((string) ($post['visit_date'] ?? '')),
        'action_taken' => trim((string) ($post['action_taken'] ?? '')),
        'closure_date' => trim((string) ($post['closure_date'] ?? '')),
        'part_replaced' => trim((string) ($post['part_replaced'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'loaded_hours' => trim((string) ($post['loaded_hours'] ?? '')),
        'customer_feedback' => trim((string) ($post['customer_feedback'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ];

    foreach (service_log_remaining_consumable_column_names() as $field) {
        $data[$field] = trim((string) ($post[$field] ?? ''));
    }

    if (!empty($post['part_replacement_multi'])) {
        $data['part_replacement_multi'] = true;
        $data['part_replacement_entries'] = service_log_part_replacements_from_post($post);
    }

    return $data;
}

function service_log_part_replaced_is_yes(string $value): bool
{
    return strcasecmp(trim($value), 'Yes') === 0;
}

function service_log_part_replacements_from_post(array $post): array
{
    $raw = $post['part_replacement_entries'] ?? [];
    if (!is_array($raw)) {
        return [];
    }

    $entries = [];
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }

        $entries[] = [
            'id' => trim((string) ($row['id'] ?? '')),
            'machine_model_code' => trim((string) ($row['machine_model_code'] ?? '')),
            'machine_model' => trim((string) ($row['machine_model'] ?? '')),
            'running_hours' => trim((string) ($row['running_hours'] ?? '')),
            'loaded_hours' => trim((string) ($row['loaded_hours'] ?? '')),
        ];
    }

    return $entries;
}

function service_log_validate_part_replacement_entries(array $entries): ?string
{
    if ($entries === []) {
        return 'At least one Machine Model / Part entry is required when Part Replaced is Yes.';
    }

    foreach ($entries as $index => $entry) {
        $label = 'Part replacement entry ' . ($index + 1);

        if ($entry['machine_model_code'] === '' || $entry['machine_model'] === '') {
            return $label . ': Machine Model / Part is required.';
        }

        if ($entry['running_hours'] === '') {
            return $label . ': Running Hours is required.';
        }

        if (!is_numeric($entry['running_hours']) || (float) $entry['running_hours'] <= 0) {
            return $label . ': Running Hours must be greater than 0.';
        }

        if ($entry['loaded_hours'] === '') {
            return $label . ': Loaded Hours is required.';
        }

        if (!is_numeric($entry['loaded_hours']) || (float) $entry['loaded_hours'] < 0) {
            return $label . ': Loaded Hours must be a valid non-negative number.';
        }
    }

    return null;
}

function service_log_apply_part_replacement_fields_for_save(array &$data): void
{
    if (empty($data['part_replacement_multi'])) {
        return;
    }

    if (!service_log_part_replaced_is_yes($data['part_replaced'])) {
        $data['part_replacement_entries'] = [];
        $data['running_hours'] = '';
        $data['loaded_hours'] = '';
        $data['customer_feedback'] = '';
        $data['remarks'] = '';
        return;
    }

    $first = $data['part_replacement_entries'][0] ?? null;
    if (!$first) {
        $data['running_hours'] = '';
        $data['loaded_hours'] = '';
        return;
    }

    $data['running_hours'] = $first['running_hours'];
    $data['loaded_hours'] = $first['loaded_hours'];
}

function service_log_insert_part_replacements(PDO $conn, int $serviceLogId, array $entries): void
{
    if ($serviceLogId <= 0 || $entries === []) {
        return;
    }

    $stmt = $conn->prepare('
        INSERT INTO service_log_part_replacements (
            service_log_id, machine_model_code, machine_model, running_hours, loaded_hours, sort_order
        ) VALUES (
            :service_log_id, :machine_model_code, :machine_model, :running_hours, :loaded_hours, :sort_order
        )
    ');

    foreach ($entries as $sortOrder => $entry) {
        $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
        $stmt->bindValue(':machine_model_code', $entry['machine_model_code']);
        $stmt->bindValue(':machine_model', $entry['machine_model']);
        $stmt->bindValue(':running_hours', $entry['running_hours']);
        $stmt->bindValue(':loaded_hours', $entry['loaded_hours']);
        $stmt->bindValue(':sort_order', (int) $sortOrder, PDO::PARAM_INT);
        $stmt->execute();
    }
}

function service_log_soft_delete_part_replacements(PDO $conn, int $serviceLogId): void
{
    if ($serviceLogId <= 0) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE service_log_part_replacements
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE service_log_id = :service_log_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();
}

function service_log_sync_part_replacements(PDO $conn, int $serviceLogId, array $data): void
{
    if ($serviceLogId <= 0 || empty($data['part_replacement_multi'])) {
        return;
    }

    if (!service_log_part_replaced_is_yes($data['part_replaced'])) {
        service_log_soft_delete_part_replacements($conn, $serviceLogId);
        return;
    }

    $entries = $data['part_replacement_entries'] ?? [];
    if ($entries === []) {
        service_log_soft_delete_part_replacements($conn, $serviceLogId);
        return;
    }

    $keptIds = [];

    $updateStmt = $conn->prepare('
        UPDATE service_log_part_replacements
        SET
            machine_model_code = :machine_model_code,
            machine_model = :machine_model,
            running_hours = :running_hours,
            loaded_hours = :loaded_hours,
            sort_order = :sort_order,
            deleted_at = NULL
        WHERE id = :id
          AND service_log_id = :service_log_id
          AND deleted_at IS NULL
    ');

    $insertStmt = $conn->prepare('
        INSERT INTO service_log_part_replacements (
            service_log_id, machine_model_code, machine_model, running_hours, loaded_hours, sort_order
        ) VALUES (
            :service_log_id, :machine_model_code, :machine_model, :running_hours, :loaded_hours, :sort_order
        )
    ');

    foreach ($entries as $sortOrder => $entry) {
        $entryId = (int) ($entry['id'] ?? 0);

        if ($entryId > 0) {
            $updateStmt->bindValue(':machine_model_code', $entry['machine_model_code']);
            $updateStmt->bindValue(':machine_model', $entry['machine_model']);
            $updateStmt->bindValue(':running_hours', $entry['running_hours']);
            $updateStmt->bindValue(':loaded_hours', $entry['loaded_hours']);
            $updateStmt->bindValue(':sort_order', (int) $sortOrder, PDO::PARAM_INT);
            $updateStmt->bindValue(':id', $entryId, PDO::PARAM_INT);
            $updateStmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
            $updateStmt->execute();

            if ($updateStmt->rowCount() > 0) {
                $keptIds[] = $entryId;
                continue;
            }
        }

        $insertStmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
        $insertStmt->bindValue(':machine_model_code', $entry['machine_model_code']);
        $insertStmt->bindValue(':machine_model', $entry['machine_model']);
        $insertStmt->bindValue(':running_hours', $entry['running_hours']);
        $insertStmt->bindValue(':loaded_hours', $entry['loaded_hours']);
        $insertStmt->bindValue(':sort_order', (int) $sortOrder, PDO::PARAM_INT);
        $insertStmt->execute();
        $keptIds[] = (int) $conn->lastInsertId();
    }

    if ($keptIds === []) {
        service_log_soft_delete_part_replacements($conn, $serviceLogId);
        return;
    }

    $placeholders = [];
    $params = [':service_log_id' => $serviceLogId];
    foreach ($keptIds as $index => $keptId) {
        $paramKey = ':kept_id_' . $index;
        $placeholders[] = $paramKey;
        $params[$paramKey] = $keptId;
    }

    $deleteStmt = $conn->prepare('
        UPDATE service_log_part_replacements
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE service_log_id = :service_log_id
          AND deleted_at IS NULL
          AND id NOT IN (' . implode(', ', $placeholders) . ')
    ');
    foreach ($params as $paramKey => $value) {
        $deleteStmt->bindValue(
            $paramKey,
            $value,
            $paramKey === ':service_log_id' ? PDO::PARAM_INT : PDO::PARAM_INT
        );
    }
    $deleteStmt->execute();
}

function service_log_get_installed_base(PDO $conn, int $installedBaseId, string $username = ''): ?array
{
    require_once __DIR__ . '/after_market_access_helpers.php';

    if (!after_market_user_can_access_record($conn, 'installed_base', $installedBaseId)) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, machine_model_code, running_hours
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function service_log_machine_model_from_installed_base(array $installedBase): string
{
    $label = installed_base_machine_model_label($installedBase);

    return $label === '-' ? '' : $label;
}

function service_log_validate(PDO $conn, array $data): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Installed base record is required.';
    }

    if ($data['order_id'] === '') {
        return 'Order ID is required.';
    }

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['serial_number'] === '') {
        return 'Serial Number is required.';
    }

    if ($data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    if ($data['warranty_chargeable'] === '') {
        return 'Warranty / Chargeable is required.';
    }

    if (!scm_option_exists($conn, 'warranty_chargeable', $data['warranty_chargeable'])) {
        return 'Invalid Warranty / Chargeable selection.';
    }

    if ($data['complaint_date'] === '') {
        return 'Complaint Date is required.';
    }

    if ($data['issue_description'] === '') {
        return 'Issue Description is required.';
    }

    if ($data['engineer_name'] === '') {
        return 'Engineer Name is required.';
    }

    if (strlen($data['engineer_name']) > 150) {
        return 'Engineer Name cannot exceed 150 characters.';
    }

    if ($data['visit_date'] === '') {
        return 'Visit Date is required.';
    }

    if ($data['action_taken'] === '') {
        return 'Action Taken is required.';
    }

    if ($data['closure_date'] === '') {
        return 'Closure Date is required to complete the service log.';
    }

    if ($data['part_replaced'] === '') {
        return 'Part Replaced is required.';
    }

    if (!scm_option_exists($conn, 'part_replaced', $data['part_replaced'])) {
        return 'Invalid Part Replaced selection.';
    }

    if (!empty($data['part_replacement_multi'])) {
        if (service_log_part_replaced_is_yes($data['part_replaced'])) {
            $entryError = service_log_validate_part_replacement_entries($data['part_replacement_entries'] ?? []);
            if ($entryError !== null) {
                return $entryError;
            }

            if ($data['customer_feedback'] === '') {
                return 'Customer Feedback is required.';
            }

            if (!scm_option_exists($conn, 'customer_feedback', $data['customer_feedback'])) {
                return 'Invalid Customer Feedback selection.';
            }

            if (strlen($data['remarks']) > 1000) {
                return 'Remarks cannot exceed 1000 characters.';
            }
        }
    } else {
        if ($data['running_hours'] === '') {
            return 'Running Hours is required.';
        }

        if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] <= 0) {
            return 'Running Hours must be greater than 0.';
        }

        if ($data['loaded_hours'] === '') {
            return 'Loaded Hours is required.';
        }

        if (!is_numeric($data['loaded_hours']) || (float) $data['loaded_hours'] < 0) {
            return 'Loaded Hours must be a valid non-negative number.';
        }

        if ($data['customer_feedback'] !== ''
            && !scm_option_exists($conn, 'customer_feedback', $data['customer_feedback'])) {
            return 'Invalid Customer Feedback selection.';
        }

        if (strlen($data['remarks']) > 1000) {
            return 'Remarks cannot exceed 1000 characters.';
        }
    }

    if (strtotime($data['visit_date']) < strtotime($data['complaint_date'])) {
        return 'Visit Date cannot be earlier than Complaint Date.';
    }

    if (strtotime($data['closure_date']) < strtotime($data['visit_date'])) {
        return 'Closure Date cannot be earlier than Visit Date.';
    }

    if (empty($data['part_replacement_multi']) && strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    foreach (service_log_remaining_consumable_fields() as $item) {
        $hoursKey = $item['key'] . '_remaining_hours';

        if ($data[$hoursKey] !== ''
            && (!is_numeric($data[$hoursKey]) || (float) $data[$hoursKey] < 0)) {
            return $item['label'] . ' Remaining Hours must be a valid non-negative number.';
        }
    }

    return null;
}

function service_log_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function service_log_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function service_log_format_input_date(?string $value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '';
    }

    return date('Y-m-d', strtotime($value));
}

function service_log_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function service_log_bind_remaining_consumables(PDOStatement $stmt, array $data): void
{
    foreach (service_log_remaining_consumable_column_names() as $field) {
        $value = $data[$field] ?? '';

        if (substr($field, -15) === '_remaining_date') {
            $stmt->bindValue(':' . $field, $value !== '' ? $value : null);
            continue;
        }

        $stmt->bindValue(':' . $field, $value !== '' ? $value : null);
    }
}

function service_log_remaining_consumable_set_clause(): string
{
    $parts = [];

    foreach (service_log_remaining_consumable_column_names() as $field) {
        $parts[] = $field . ' = :' . $field;
    }

    return implode(",\n                            ", $parts);
}

function service_log_remaining_consumable_insert_columns(): string
{
    return implode(', ', service_log_remaining_consumable_column_names());
}

function service_log_remaining_consumable_insert_placeholders(): string
{
    return implode(', ', array_map(
        static fn (string $field): string => ':' . $field,
        service_log_remaining_consumable_column_names()
    ));
}

function service_log_create_record(PDO $conn, array $post, string $username, int $createdBy = 1): array
{
    if (!empty($post['from_installed_base_modal'])) {
        $existingServiceLogId = (int) ($post['record_id'] ?? 0);
        if ($existingServiceLogId > 0) {
            return [
                'success' => false,
                'message' => 'Service Log Capture from Installed Base must create a new record.',
            ];
        }
    }

    $data = service_log_from_post($post);
    $installedBaseId = (int) $data['installed_base_id'];

    if ($installedBaseId <= 0) {
        return ['success' => false, 'message' => 'Installed base record is required.'];
    }

    $installedBase = service_log_get_installed_base($conn, $installedBaseId, $username);

    if (!$installedBase) {
        return ['success' => false, 'message' => 'Selected installed base record was not found or is not assigned to your account.'];
    }

    $existsStmt = $conn->prepare('
        SELECT id
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $existsStmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $existsStmt->execute();

    if (!$existsStmt->fetch(PDO::FETCH_ASSOC)) {
        return ['success' => false, 'message' => 'Installed base record does not exist. Service log cannot be created without an existing installed base.'];
    }

    $data['machine_model'] = service_log_machine_model_from_installed_base($installedBase);

    service_log_apply_part_replacement_fields_for_save($data);

    $validationError = service_log_validate($conn, $data);

    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if ($installedBase['order_id'] !== $data['order_id']) {
        return ['success' => false, 'message' => 'Order ID does not match the selected installed base record.'];
    }

    if (trim((string) ($installedBase['fab_number'] ?? '')) !== $data['fab_number']) {
        return ['success' => false, 'message' => 'Fab Number does not match the selected installed base record.'];
    }

    try {
        $insert = $conn->prepare('
            INSERT INTO service_logs (
                installed_base_id, order_ref_id, order_id, fab_number, serial_number, machine_model,
                warranty_chargeable, complaint_date, issue_description, engineer_name,
                visit_date, action_taken, closure_date, part_replaced,
                running_hours, loaded_hours, customer_feedback, remarks,
                ' . service_log_remaining_consumable_insert_columns() . ', created_by, username
            ) VALUES (
                :installed_base_id, :order_ref_id, :order_id, :fab_number, :serial_number, :machine_model,
                :warranty_chargeable, :complaint_date, :issue_description, :engineer_name,
                :visit_date, :action_taken, :closure_date, :part_replaced,
                :running_hours, :loaded_hours, :customer_feedback, :remarks,
                ' . service_log_remaining_consumable_insert_placeholders() . ', :created_by, :username
            )
        ');

        $orderRefId = (int) ($installedBase['order_ref_id'] ?? 0);
        $insert->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
        if ($orderRefId > 0) {
            $insert->bindValue(':order_ref_id', $orderRefId, PDO::PARAM_INT);
        } else {
            $insert->bindValue(':order_ref_id', null, PDO::PARAM_NULL);
        }
        $insert->bindValue(':order_id', $installedBase['order_id']);
        $insert->bindValue(':fab_number', $data['fab_number']);
        $insert->bindValue(':serial_number', $data['serial_number']);
        $insert->bindValue(':machine_model', $data['machine_model']);
        $insert->bindValue(':warranty_chargeable', $data['warranty_chargeable']);
        $insert->bindValue(':complaint_date', $data['complaint_date']);
        $insert->bindValue(':issue_description', $data['issue_description']);
        $insert->bindValue(':engineer_name', $data['engineer_name']);
        $insert->bindValue(':visit_date', $data['visit_date']);
        $insert->bindValue(':action_taken', $data['action_taken']);
        $insert->bindValue(':closure_date', $data['closure_date']);
        $insert->bindValue(':part_replaced', $data['part_replaced']);
        $insert->bindValue(':running_hours', $data['running_hours'] !== '' ? $data['running_hours'] : null);
        $insert->bindValue(':loaded_hours', $data['loaded_hours'] !== '' ? $data['loaded_hours'] : null);
        $insert->bindValue(':customer_feedback', $data['customer_feedback'] !== '' ? $data['customer_feedback'] : null);
        $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
        service_log_bind_remaining_consumables($insert, $data);
        $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $insert->bindValue(':username', $username);
        $insert->execute();

        $serviceLogId = (int) $conn->lastInsertId();
        if (!empty($data['part_replacement_multi'])
            && service_log_part_replaced_is_yes($data['part_replaced'])
            && !empty($data['part_replacement_entries'])) {
            service_log_insert_part_replacements($conn, $serviceLogId, $data['part_replacement_entries']);
        }

        return [
            'success' => true,
            'message' => !empty($post['from_installed_base_modal'])
                ? 'Service Log Capture added successfully.'
                : 'Service log saved successfully.',
            'service_log_id' => $serviceLogId,
            'installed_base_id' => $installedBaseId,
        ];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to save service log.'];
    }
}

function service_log_list_for_installed_base(PDO $conn, int $installedBaseId): array
{
    if ($installedBaseId <= 0) {
        return [];
    }

    $parentExistsStmt = $conn->prepare('
        SELECT id
        FROM installed_base
        WHERE id = :installed_base_id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $parentExistsStmt->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
    $parentExistsStmt->execute();

    if (!$parentExistsStmt->fetch(PDO::FETCH_ASSOC)) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM service_logs
        WHERE installed_base_id = :installed_base_id
          AND deleted_at IS NULL
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    $unique = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $unique[(int) $row['id']] = $row;
    }

    return array_values($unique);
}

function service_log_linked_installed_base_display_fields(?array $installedBaseRecord, array $serviceLogRecord): array
{
    if (!empty($installedBaseRecord) && is_array($installedBaseRecord)) {
        $machineModelLabel = installed_base_machine_model_label($installedBaseRecord);
        if ($machineModelLabel === '-') {
            $machineModelLabel = installed_base_display_value($installedBaseRecord['machine_model'] ?? null);
        }

        return [
            'order_id' => installed_base_display_value($installedBaseRecord['order_id'] ?? null),
            'fab_number' => installed_base_display_value($installedBaseRecord['fab_number'] ?? null),
            'machine_model' => $machineModelLabel,
        ];
    }

    $machineModelLabel = installed_base_machine_model_label([
        'machine_model_code' => $serviceLogRecord['machine_model_code'] ?? '',
        'machine_model' => $serviceLogRecord['machine_model'] ?? '',
    ]);
    if ($machineModelLabel === '-') {
        $machineModelLabel = service_log_display_value($serviceLogRecord['machine_model'] ?? null);
    }

    return [
        'order_id' => service_log_display_value($serviceLogRecord['order_id'] ?? null),
        'fab_number' => service_log_display_value($serviceLogRecord['fab_number'] ?? null),
        'machine_model' => $machineModelLabel,
    ];
}

function service_log_part_replacements_for_service_log(PDO $conn, int $serviceLogId): array
{
    if ($serviceLogId <= 0) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT id, machine_model_code, machine_model, running_hours, loaded_hours, sort_order
        FROM service_log_part_replacements
        WHERE service_log_id = :service_log_id
          AND deleted_at IS NULL
        ORDER BY sort_order ASC, id ASC
    ');
    $stmt->bindValue(':service_log_id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    $unique = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rowKey = (int) ($row['id'] ?? 0);
        if ($rowKey > 0) {
            $unique[$rowKey] = $row;
        } else {
            $unique[] = $row;
        }
    }

    return array_values($unique);
}

function service_log_part_model_label(array $row): string
{
    $code = trim((string) ($row['machine_model_code'] ?? ''));
    $description = trim((string) ($row['machine_model'] ?? ''));

    if ($code === '' && $description === '') {
        return '-';
    }

    if ($code !== '' && $description !== '') {
        return $code . ' - ' . $description;
    }

    return $code !== '' ? $code : $description;
}

function service_log_entry_actions(int $id, array $permissions = []): string
{
    $permissions = array_merge([
        'view' => false,
        'edit' => false,
        'delete' => false,
        'spare_parts_add' => false,
    ], $permissions);
    $encodedId = base64_encode((string) $id);

    $html = '<div class="d-flex gap-1">';

    if ($permissions['view']) {
        $html .= '
            <a href="service_log_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>';
    }

    if ($permissions['edit']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark edit-service-log-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';
    }

    if ($permissions['spare_parts_add']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark add-spare-parts-btn"
                data-id="' . $id . '" data-prefill="service_log" title="Add Spare Parts Consumption">
                <i class="bi bi-gear"></i>
            </button>';
    }

    if ($permissions['delete']) {
        $html .= '
            <a href="delete_service_log.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this service log?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>';
    }

    $html .= '</div>';

    return $html;
}
