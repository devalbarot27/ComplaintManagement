<?php

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

function service_log_from_post(array $post): array
{
    return [
        'installed_base_id' => trim((string) ($post['installed_base_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
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
}

function service_log_get_installed_base(PDO $conn, int $installedBaseId): ?array
{
    $stmt = $conn->prepare('
        SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, running_hours
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function service_log_validate(array $data): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Installed base record is required.';
    }

    if ($data['order_id'] === '') {
        return 'Order ID is required.';
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

    if (!in_array($data['engineer_name'], service_log_engineers(), true)) {
        return 'Invalid Engineer Name selected.';
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

function service_log_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
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
