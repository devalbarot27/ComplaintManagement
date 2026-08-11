<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function customer_from_post(array $post): array
{
    return [
        'cust_code' => trim((string) ($post['cust_code'] ?? '')),
        'cust_name' => trim((string) ($post['cust_name'] ?? '')),
        'cust_addr' => trim((string) ($post['cust_addr'] ?? '')),
    ];
}

function customer_validate(array $data): ?string
{
    if ($data['cust_code'] === '') {
        return 'Customer code is required.';
    }

    if (strlen($data['cust_code']) > 50) {
        return 'Customer code cannot exceed 50 characters.';
    }

    if ($data['cust_name'] === '') {
        return 'Customer name is required.';
    }

    if (strlen($data['cust_name']) > 255) {
        return 'Customer name cannot exceed 255 characters.';
    }

    if ($data['cust_addr'] === '') {
        return 'Customer address is required.';
    }

    if (strlen($data['cust_addr']) > 50) {
        return 'Customer address cannot exceed 50 characters.';
    }

    return null;
}

function customer_code_exists(PDO $conn, string $custCode, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM customers
        WHERE LOWER(TRIM(cust_code)) = LOWER(TRIM(:cust_code))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':cust_code', $custCode);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_name_exists(PDO $conn, string $custName, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM customers
        WHERE LOWER(TRIM(cust_name)) = LOWER(TRIM(:cust_name))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':cust_name', $custName);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_search_filter(string $searchValue): array
{
    return [
        'sql' => '(cust_code ILIKE :search OR cust_name ILIKE :search OR cust_addr ILIKE :search)',
        'params' => [':search' => '%' . $searchValue . '%'],
    ];
}

function customer_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM customers
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_address_format_label(array $row): string
{
    $code = trim((string) ($row['adr_code'] ?? ''));
    if ($code === '') {
        return '-';
    }

    $st1 = trim((string) ($row['st1'] ?? ''));
    $st2 = trim((string) ($row['st2'] ?? ''));
    $city2 = trim((string) ($row['city2'] ?? ''));
    $pin = trim((string) ($row['pin'] ?? ''));
    $street = trim($st1 . ' ' . $st2);

    return $code . ' : ' . $street . ', ' . $city2 . ' - ' . $pin;
}

function customer_address_get_by_code(PDO $dpconn, string $adrCode): ?array
{
    $adrCode = trim($adrCode);
    if ($adrCode === '') {
        return null;
    }

    $stmt = $dpconn->prepare('
        SELECT adr_code, cuno, st1, st2, city2, pin
        FROM customer_address
        WHERE TRIM(adr_code) = :adr_code
        LIMIT 1
    ');
    $stmt->bindValue(':adr_code', $adrCode);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_address_label(PDO $dpconn, string $adrCode): string
{
    $row = customer_address_get_by_code($dpconn, $adrCode);
    if ($row === null) {
        return $adrCode !== '' ? $adrCode : '-';
    }

    return customer_address_format_label($row);
}

function customer_address_labels(PDO $dpconn, array $adrCodes): array
{
    $codes = [];
    foreach ($adrCodes as $code) {
        $code = trim((string) $code);
        if ($code !== '') {
            $codes[$code] = $code;
        }
    }

    if ($codes === []) {
        return [];
    }

    $placeholders = [];
    $params = [];
    $i = 0;
    foreach ($codes as $code) {
        $key = ':c' . $i;
        $placeholders[] = $key;
        $params[$key] = $code;
        $i++;
    }

    $sql = '
        SELECT adr_code, st1, st2, city2, pin
        FROM customer_address
        WHERE TRIM(adr_code) IN (' . implode(', ', $placeholders) . ')
    ';
    $stmt = $dpconn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $labels = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = trim((string) ($row['adr_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $label = customer_address_format_label($row);
        $labels[$code] = $label;
        // Match padded CHAR values from customer_address.adr_code
        foreach ($codes as $requested) {
            if (trim((string) $requested) === $code) {
                $labels[trim((string) $requested)] = $label;
            }
        }
    }

    return $labels;
}

function customer_address_search(PDO $dpconn, string $search, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $sql = '
        SELECT adr_code, st1, st2, city2, pin
        FROM customer_address
        WHERE length(TRIM(adr_code)) > 0
    ';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (
            LOWER(adr_code) LIKE LOWER(:search)
            OR LOWER(COALESCE(st1, \'\')) LIKE LOWER(:search)
            OR LOWER(COALESCE(st2, \'\')) LIKE LOWER(:search)
            OR LOWER(COALESCE(city2, \'\')) LIKE LOWER(:search)
            OR LOWER(COALESCE(pin, \'\')) LIKE LOWER(:search)
        )';
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY adr_code ASC LIMIT ' . (int) $limit;

    $stmt = $dpconn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = trim((string) ($row['adr_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $results[] = [
            'id' => $code,
            'text' => customer_address_format_label($row),
        ];
    }

    return $results;
}

function customer_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="customer_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-customer-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_customer.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this customer?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function customer_insert(PDO $conn, array $data, string $username): void
{
    $stmt = $conn->prepare('
        INSERT INTO customers (cust_code, cust_name, cust_addr, created_by, created_at)
        VALUES (:cust_code, :cust_name, :cust_addr, :created_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':cust_code', $data['cust_code']);
    $stmt->bindValue(':cust_name', $data['cust_name']);
    $stmt->bindValue(':cust_addr', $data['cust_addr']);
    if ($username === '') {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':created_by', $username);
    }
    $stmt->execute();
}

function customer_update(PDO $conn, int $id, array $data, string $username): void
{
    $stmt = $conn->prepare('
        UPDATE customers SET
            cust_code = :cust_code,
            cust_name = :cust_name,
            cust_addr = :cust_addr,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':cust_code', $data['cust_code']);
    $stmt->bindValue(':cust_name', $data['cust_name']);
    $stmt->bindValue(':cust_addr', $data['cust_addr']);
    if ($username === '') {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $username);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function customer_soft_delete(PDO $conn, int $id, string $username = ''): void
{
    $stmt = $conn->prepare('
        UPDATE customers
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = COALESCE(NULLIF(:updated_by, \'\'), updated_by)
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':updated_by', $username);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
