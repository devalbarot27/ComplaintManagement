<?php

require_once __DIR__ . '/installed_base_helpers.php';

function service_log_warranty_types(): array
{
    return ['Warranty', 'Chargeable'];
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

function service_log_part_replaced_options(): array
{
    return ['Yes', 'No'];
}

function service_log_customer_feedback_options(): array
{
    return [
        'Excellent',
        'Good',
        'Average',
        'Poor',
    ];
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

    return $data;
}

function service_log_get_installed_base(PDO $conn, int $installedBaseId, string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, machine_model_code, running_hours
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
          AND TRIM(username) = :username
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->bindValue(':username', $username);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function service_log_machine_model_from_installed_base(array $installedBase): string
{
    $label = installed_base_machine_model_label($installedBase);

    return $label === '-' ? '' : $label;
}

function service_log_validate(array $data): ?string
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

    if (!in_array($data['warranty_chargeable'], service_log_warranty_types(), true)) {
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

    if (!in_array($data['part_replaced'], service_log_part_replaced_options(), true)) {
        return 'Invalid Part Replaced selection.';
    }

    if ($data['running_hours'] === '') {
        return 'Running Hours is required.';
    }

    if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] < 0) {
        return 'Running Hours must be a valid non-negative number.';
    }

    if ($data['loaded_hours'] === '') {
        return 'Loaded Hours is required.';
    }

    if (!is_numeric($data['loaded_hours']) || (float) $data['loaded_hours'] < 0) {
        return 'Loaded Hours must be a valid non-negative number.';
    }

    if ($data['customer_feedback'] !== ''
        && !in_array($data['customer_feedback'], service_log_customer_feedback_options(), true)) {
        return 'Invalid Customer Feedback selection.';
    }

    if (strtotime($data['visit_date']) < strtotime($data['complaint_date'])) {
        return 'Visit Date cannot be earlier than Complaint Date.';
    }

    if (strtotime($data['closure_date']) < strtotime($data['visit_date'])) {
        return 'Closure Date cannot be earlier than Visit Date.';
    }

    if (strlen($data['remarks']) > 1000) {
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

function service_log_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="service_log_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-service-log-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_service_log.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this service log?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}
