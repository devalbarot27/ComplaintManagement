<?php

function installed_base_static_orders(): array
{
    return [
        [
            'order_id' => 'ORD-10001',
            'fab_number' => '1000000001',
            'customer_name' => 'Alpha Textiles Pvt Ltd',
            'invoice_date' => '2024-01-15',
            'dealer_name' => 'Dealer One',
            'machine_model' => 'Model X100',
        ],
        [
            'order_id' => 'ORD-10002',
            'fab_number' => '1000000002',
            'customer_name' => 'Beta Engineering Works',
            'invoice_date' => '2024-02-20',
            'dealer_name' => 'Dealer Two',
            'machine_model' => 'Model X200',
        ],
        [
            'order_id' => 'ORD-10003',
            'fab_number' => '1000000003',
            'customer_name' => 'Gamma Pharma Ltd',
            'invoice_date' => '2024-03-10',
            'dealer_name' => 'Dealer Three',
            'machine_model' => 'Model X300',
        ],
        [
            'order_id' => 'ORD-10004',
            'fab_number' => '1000000004',
            'customer_name' => 'Delta Foods Pvt Ltd',
            'invoice_date' => '2024-04-05',
            'dealer_name' => 'Dealer Four',
            'machine_model' => 'Model X400',
        ],
        [
            'order_id' => 'ORD-10005',
            'fab_number' => '1000000005',
            'customer_name' => 'Epsilon Auto Components',
            'invoice_date' => '2024-05-18',
            'dealer_name' => 'Dealer Five',
            'machine_model' => 'Model X500',
        ],
    ];
}

function installed_base_search_orders(string $term, int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $needle = strtolower($term);
    $results = [];

    foreach (installed_base_static_orders() as $order) {
        $haystack = strtolower(implode(' ', [
            $order['order_id'],
            $order['fab_number'],
            $order['customer_name'],
        ]));

        if (strpos($haystack, $needle) === false) {
            continue;
        }

        $results[] = $order;

        if (count($results) >= $limit) {
            break;
        }
    }

    return $results;
}

function installed_base_industry_segments(): array
{
    return [
        'Manufacturing',
        'Textiles',
        'Automotive',
        'Pharmaceuticals',
        'FMCG',
        'Engineering',
        'Construction',
        'Agriculture',
        'Food Processing',
        'Healthcare',
        'Others',
    ];
}

function installed_base_from_post(array $post): array
{
    return [
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'address' => trim((string) ($post['address'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'dealer_name' => trim((string) ($post['dealer_name'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'commissioning_date' => trim((string) ($post['commissioning_date'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'industry_segment' => trim((string) ($post['industry_segment'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ];
}

function installed_base_validate(array $data): ?string
{
    if ($data['order_id'] === '') {
        return 'Order ID is required.';
    }

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }

    if ($data['address'] === '') {
        return 'Address is required.';
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

    if ($data['machine_model'] === '') {
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

    if (!in_array($data['industry_segment'], installed_base_industry_segments(), true)) {
        return 'Invalid Industry Segment selected.';
    }

    if (strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    return null;
}

function installed_base_get_order(string $orderId): ?array
{
    $orderId = trim($orderId);

    foreach (installed_base_static_orders() as $order) {
        if ($order['order_id'] === $orderId) {
            return $order;
        }
    }

    return null;
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

function installed_base_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="installed_base_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-installed-base-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_installed_base.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this installed base record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}
