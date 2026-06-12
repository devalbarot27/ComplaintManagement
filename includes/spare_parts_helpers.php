<?php

function spare_parts_warranty_types(): array
{
    return ['Warranty', 'Chargeable'];
}

function spare_parts_reasons(): array
{
    return ['PM', 'Breakdown'];
}

function spare_parts_from_post(array $post): array
{
    return [
        'installed_base_id' => trim((string) ($post['installed_base_id'] ?? '')),
        'service_log_id' => trim((string) ($post['service_log_id'] ?? '')),
        'serial_number' => trim((string) ($post['serial_number'] ?? '')),
        'consumption_date' => trim((string) ($post['consumption_date'] ?? '')),
        'warranty_chargeable' => trim((string) ($post['warranty_chargeable'] ?? '')),
        'spare_kit_number' => trim((string) ($post['spare_kit_number'] ?? '')),
        'quantity' => trim((string) ($post['quantity'] ?? '')),
        'order_value' => trim((string) ($post['order_value'] ?? '')),
        'reason' => trim((string) ($post['reason'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ];
}

function spare_parts_get_installed_base(PDO $conn, int $installedBaseId): ?array
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

function spare_parts_get_service_log(PDO $conn, int $serviceLogId): ?array
{
    $stmt = $conn->prepare('
        SELECT id, installed_base_id, order_id, serial_number
        FROM service_logs
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $serviceLogId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function spare_parts_validate(array $data): ?string
{
    if ($data['installed_base_id'] === '' || (int) $data['installed_base_id'] <= 0) {
        return 'Machine (installed base) is required.';
    }

    if ($data['serial_number'] === '') {
        return 'Serial Number is required.';
    }

    if ($data['consumption_date'] === '') {
        return 'Consumption Date is required.';
    }

    if ($data['warranty_chargeable'] === '') {
        return 'Warranty / Chargeable is required.';
    }

    if (!in_array($data['warranty_chargeable'], spare_parts_warranty_types(), true)) {
        return 'Invalid Warranty / Chargeable selection.';
    }

    if ($data['spare_kit_number'] === '') {
        return 'Spare Kit Number is required.';
    }

    if ($data['quantity'] === '') {
        return 'Quantity is required.';
    }

    if (!is_numeric($data['quantity']) || (float) $data['quantity'] <= 0) {
        return 'Quantity must be greater than zero.';
    }

    if ($data['order_value'] === '') {
        return 'Order Value is required.';
    }

    if (!is_numeric($data['order_value']) || (float) $data['order_value'] < 0) {
        return 'Order Value must be a valid non-negative number.';
    }

    if ($data['reason'] === '') {
        return 'Reason is required.';
    }

    if (!in_array($data['reason'], spare_parts_reasons(), true)) {
        return 'Invalid Reason selected.';
    }

    if ($data['running_hours'] === '') {
        return 'Running Hours is required.';
    }

    if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] < 0) {
        return 'Running Hours must be a valid non-negative number.';
    }

    if (strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    return null;
}

function spare_parts_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function spare_parts_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function spare_parts_format_currency($value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return number_format((float) $value, 2);
}

function spare_parts_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function spare_parts_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="spare_parts_consumption_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-spare-parts-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_spare_parts_consumption.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this spare parts record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}
