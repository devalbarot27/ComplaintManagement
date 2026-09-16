<?php

require_once __DIR__ . '/customer_master_helpers.php';

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

/**
 * @return array<int, string>
 */
function complaint_legacy_customer_columns(): array
{
    return [
        'customer_name',
        'street_1',
        'street_2',
        'pincode',
        'city',
        'district',
        'state',
        'customer_address',
    ];
}

function complaint_table_has_column(PDO $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'complaints'
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->bindValue(':column_name', $column);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function complaint_ensure_schema(PDO $conn): void
{
    customer_master_ensure_schema($conn);

    if (!complaint_table_has_column($conn, 'customer_id')) {
        $conn->exec('ALTER TABLE complaints ADD COLUMN customer_id INTEGER NULL');
    }

    if (complaint_table_has_column($conn, 'customer_name')) {
        require_once __DIR__ . '/installed_base_helpers.php';
        complaint_migrate_legacy_customer_fields($conn);

        foreach (complaint_legacy_customer_columns() as $column) {
            if (complaint_table_has_column($conn, $column)) {
                $conn->exec('ALTER TABLE complaints DROP COLUMN IF EXISTS ' . $column);
            }
        }
    }
}

function complaint_migrate_legacy_customer_fields(PDO $conn): void
{
    $selectCols = ['id'];
    foreach (complaint_legacy_customer_columns() as $column) {
        if ($column === 'customer_address') {
            continue;
        }
        if (complaint_table_has_column($conn, $column)) {
            $selectCols[] = $column;
        }
    }

    $stmt = $conn->query('
        SELECT ' . implode(', ', $selectCols) . '
        FROM complaints
        WHERE customer_id IS NULL
          AND deleted_at IS NULL
        ORDER BY id ASC
    ');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $customerId = installed_base_resolve_or_create_customer_from_legacy_row($conn, $row);
        if ($customerId <= 0) {
            continue;
        }

        $update = $conn->prepare('
            UPDATE complaints
            SET customer_id = :customer_id
            WHERE id = :id
              AND customer_id IS NULL
        ');
        $update->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $update->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        $update->execute();
    }
}

function complaint_customer_join_sql(string $complaintAlias = 'c', string $cmAlias = 'cm'): string
{
    return "LEFT JOIN customer_masters {$cmAlias}
        ON {$cmAlias}.id = {$complaintAlias}.customer_id
       AND {$cmAlias}.deleted_at IS NULL";
}

function complaint_scope_where_for_alias(string $where, string $tableAlias = 'c'): string
{
    $where = preg_replace('/\bcomplaints\.id\b/', $tableAlias . '.id', $where);
    $where = preg_replace('/(?<![.\w])deleted_at\b/', $tableAlias . '.deleted_at', $where);
    $where = preg_replace('/(?<![.\w])username\s*=/', $tableAlias . '.username =', $where);
    $where = preg_replace('/(?<![.\w])status\s*=/', $tableAlias . '.status =', $where);
    $where = preg_replace('/(?<![.\w])id\s*=\s*:complaint_id/', $tableAlias . '.id = :complaint_id', $where);

    return $where;
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

function complaint_validate_customer_id(PDO $conn, $customerId): ?string
{
    complaint_ensure_schema($conn);

    $id = (int) $customerId;
    if ($id <= 0) {
        return 'Customer is required.';
    }

    if (customer_master_get_by_id($conn, $id) === null) {
        return 'Selected customer is invalid.';
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

function complaint_address_display_value(array $row, string $field): string
{
    $value = trim((string) ($row[$field] ?? ''));

    if ($field === 'street_1' && $value === '' && !empty($row['customer_address'])) {
        return trim((string) $row['customer_address']);
    }

    return $value !== '' ? $value : '-';
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