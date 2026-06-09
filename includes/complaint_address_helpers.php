<?php

function complaint_address_search_columns(): array
{
    return [
        'street_1',
        'street_2',
        'pincode',
        'city',
        'district',
        'state',
        'customer_address',
    ];
}

function complaint_address_from_post(array $post): array
{
    return [
        'street_1' => trim((string) ($post['street_1'] ?? '')),
        'street_2' => trim((string) ($post['street_2'] ?? '')),
        'pincode' => trim((string) ($post['pincode'] ?? '')),
        'city' => trim((string) ($post['city'] ?? '')),
        'district' => trim((string) ($post['district'] ?? '')),
        'state' => trim((string) ($post['state'] ?? '')),
    ];
}

function complaint_validate_address_fields(array $address): ?string
{
    if ($address['street_1'] === '') {
        return 'Street 1 is required.';
    }

    if ($address['pincode'] === '') {
        return 'Pincode is required.';
    }

    if (!preg_match('/^\d{6}$/', $address['pincode'])) {
        return 'Pincode must be a 6-digit number.';
    }

    if ($address['city'] === '') {
        return 'City is required.';
    }

    if ($address['district'] === '') {
        return 'District is required.';
    }

    if ($address['state'] === '') {
        return 'State is required.';
    }

    if (strlen($address['street_1']) > 255) {
        return 'Street 1 cannot exceed 255 characters.';
    }

    if (strlen($address['street_2']) > 255) {
        return 'Street 2 cannot exceed 255 characters.';
    }

    if (strlen($address['city']) > 100) {
        return 'City cannot exceed 100 characters.';
    }

    if (strlen($address['district']) > 100) {
        return 'District cannot exceed 100 characters.';
    }

    if (strlen($address['state']) > 100) {
        return 'State cannot exceed 100 characters.';
    }

    return null;
}

function complaint_format_address(array $row): string
{
    $parts = [];

    if (!empty($row['street_1'])) {
        $parts[] = trim((string) $row['street_1']);
    } elseif (!empty($row['customer_address'])) {
        $parts[] = trim((string) $row['customer_address']);
    }

    if (!empty($row['street_2'])) {
        $parts[] = trim((string) $row['street_2']);
    }

    $locality = array_filter([
        trim((string) ($row['city'] ?? '')),
        trim((string) ($row['district'] ?? '')),
        trim((string) ($row['state'] ?? '')),
    ]);

    if (!empty($locality)) {
        $parts[] = implode(', ', $locality);
    }

    if (!empty($row['pincode'])) {
        $parts[] = 'Pincode: ' . trim((string) $row['pincode']);
    }

    return implode(', ', $parts);
}

function complaint_format_address_html(array $row): string
{
    $lines = [];

    if (!empty($row['street_1'])) {
        $lines[] = htmlspecialchars(trim((string) $row['street_1']), ENT_QUOTES, 'UTF-8');
    } elseif (!empty($row['customer_address'])) {
        $lines[] = nl2br(htmlspecialchars(trim((string) $row['customer_address']), ENT_QUOTES, 'UTF-8'));
    }

    if (!empty($row['street_2'])) {
        $lines[] = htmlspecialchars(trim((string) $row['street_2']), ENT_QUOTES, 'UTF-8');
    }

    $locality = array_filter([
        trim((string) ($row['city'] ?? '')),
        trim((string) ($row['district'] ?? '')),
        trim((string) ($row['state'] ?? '')),
    ]);

    if (!empty($locality)) {
        $lines[] = htmlspecialchars(implode(', ', $locality), ENT_QUOTES, 'UTF-8');
    }

    if (!empty($row['pincode'])) {
        $lines[] = 'Pincode: ' . htmlspecialchars(trim((string) $row['pincode']), ENT_QUOTES, 'UTF-8');
    }

    if (empty($lines)) {
        return '-';
    }

    return implode('<br>', $lines);
}
