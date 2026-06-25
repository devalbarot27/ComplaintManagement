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

    return $data;
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
        && !scm_option_exists($conn, 'customer_feedback', $data['customer_feedback'])) {
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

function service_log_create_record(PDO $conn, array $post, string $username, int $createdBy = 1): array
{
    $data = service_log_from_post($post);
    $installedBaseId = (int) $data['installed_base_id'];
    $installedBase = $installedBaseId > 0
        ? service_log_get_installed_base($conn, $installedBaseId, $username)
        : null;

    if ($installedBase) {
        $data['machine_model'] = service_log_machine_model_from_installed_base($installedBase);
    }

    $validationError = service_log_validate($conn, $data);

    if ($validationError !== null) {
        return ['success' => false, 'message' => $validationError];
    }

    if (!$installedBase) {
        return ['success' => false, 'message' => 'Selected installed base record was not found or is not assigned to your account.'];
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
        $insert->bindValue(':running_hours', $data['running_hours']);
        $insert->bindValue(':loaded_hours', $data['loaded_hours']);
        $insert->bindValue(':customer_feedback', $data['customer_feedback'] !== '' ? $data['customer_feedback'] : null);
        $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
        service_log_bind_remaining_consumables($insert, $data);
        $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $insert->bindValue(':username', $username);
        $insert->execute();

        return ['success' => true, 'message' => 'Service log saved successfully.'];
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Failed to save service log.'];
    }
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
