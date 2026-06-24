<?php

require_once __DIR__ . '/order_helpers.php';
require_once __DIR__ . '/complaint_address_helpers.php';
require_once __DIR__ . '/ln_invoice_helpers.php';
require_once __DIR__ . '/system_config_master_helpers.php';

function installed_base_industry_segments(PDO $conn): array
{
    return scm_get_active_names($conn, 'industry_segment');
}

function installed_base_address_search_columns(): array
{
    return array_merge(
        complaint_address_search_columns(),
        ['address']
    );
}

function installed_base_address_display_value(array $row, string $field): string
{
    $value = trim((string) ($row[$field] ?? ''));

    if ($field === 'street_1' && $value === '' && !empty($row['address'])) {
        return trim((string) $row['address']);
    }

    return $value !== '' ? $value : '-';
}

function installed_base_from_post(array $post): array
{
    return array_merge([
        'order_ref_id' => trim((string) ($post['order_ref_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'dealer_name' => trim((string) ($post['dealer_name'] ?? '')),
        'machine_model_code' => trim((string) ($post['machine_model_code'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'commissioning_date' => trim((string) ($post['commissioning_date'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'industry_segment' => trim((string) ($post['industry_segment'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ], complaint_address_from_post($post));
}

function installed_base_validate(PDO $conn, array $data): ?string
{
    if ($data['order_ref_id'] === '' || (int) $data['order_ref_id'] <= 0) {
        return 'Order ID is required.';
    }

    if ($data['order_id'] === '') {
        return 'Order ID is required.';
    }

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }

    $addressError = complaint_validate_address_fields($data);
    if ($addressError !== null) {
        return $addressError;
    }

    if ($data['mobile'] === '') {
        return 'Mobile is required.';
    }

    if (!preg_match('/^[1-9]\d{9}$/', $data['mobile'])) {
        return 'Mobile must be a valid 10-digit number.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Email must be a valid email address.';
    }

    if ($data['dealer_name'] === '') {
        return 'Dealer Name is required.';
    }

    if ($data['machine_model_code'] === '' || $data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    if ($data['invoice_date'] === '') {
        return 'Invoice Date is required.';
    }

    if ($data['commissioning_date'] === '') {
        return 'Commissioning Date is required.';
    }

    if ($data['running_hours'] === '') {
        return 'Running Hours is required.';
    }

    if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] < 0) {
        return 'Running Hours must be a valid non-negative number.';
    }

    if ($data['industry_segment'] === '') {
        return 'Industry Segment is required.';
    }

    if (!scm_option_exists($conn, 'industry_segment', $data['industry_segment'])) {
        return 'Invalid Industry Segment selected.';
    }

    if (strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    return null;
}

function installed_base_get_order(PDO $conn, int $orderRefId): ?array
{
    return order_get_by_id($conn, $orderRefId);
}

function installed_base_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}

function installed_base_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function installed_base_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function installed_base_machine_model_label(array $row): string
{
    $code = trim((string) ($row['machine_model_code'] ?? ''));
    $description = trim((string) ($row['machine_model'] ?? ''));

    if ($code !== '' && $description !== '') {
        return $code . ' - ' . $description;
    }

    if ($description !== '') {
        return $description;
    }

    if ($code !== '') {
        return $code;
    }

    return '-';
}

function installed_base_entry_actions(
    int $id,
    bool $canAddServiceLog = false,
    bool $canAddSpareParts = false,
    bool $hasServiceLog = false
): string
{
    $encodedId = base64_encode((string) $id);

    $html = '
        <div class="d-flex gap-1">
            <a href="installed_base_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-installed-base-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';

    if ($canAddServiceLog) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark add-service-log-btn"
                data-id="' . $id . '" title="Add Service Log Capture">
                <i class="bi bi-clipboard-pulse"></i>
            </button>';
    }

    if ($canAddSpareParts && $hasServiceLog) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark add-spare-parts-btn"
                data-id="' . $id . '" data-prefill="installed_base" title="Spare Parts Consumption">
                <i class="bi bi-gear"></i>
            </button>';
    }

    $html .= '
            <a href="delete_installed_base.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this installed base record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';

    return $html;
}
