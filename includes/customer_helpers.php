<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function customer_from_post(array $post): array
{
    return [
        'cuno' => trim((string) ($post['cust_code'] ?? '')),
        'cuname' => trim((string) ($post['cust_name'] ?? '')),
        'adr_code' => trim((string) ($post['cust_addr'] ?? '')),
    ];
}

function customer_validate(array $data): ?string
{
    if ($data['cuno'] === '') {
        return 'Customer code is required.';
    }

    if (strlen($data['cuno']) > 9) {
        return 'Customer code cannot exceed 9 characters.';
    }

    if ($data['cuname'] === '') {
        return 'Customer name is required.';
    }

    if (strlen($data['cuname']) > 120) {
        return 'Customer name cannot exceed 120 characters.';
    }

    if ($data['adr_code'] === '') {
        return 'Customer address is required.';
    }

    if (strlen($data['adr_code']) > 9) {
        return 'Customer address code cannot exceed 9 characters.';
    }

    return null;
}

function customer_code_exists(PDO $conn, string $cuno, string $excludeCuno = ''): bool
{
    $sql = '
        SELECT cuno
        FROM customer_master
        WHERE LOWER(TRIM(cuno)) = LOWER(TRIM(:cuno))
    ';
    if ($excludeCuno !== '') {
        $sql .= ' AND TRIM(cuno) != TRIM(:exclude_cuno)';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':cuno', $cuno);
    if ($excludeCuno !== '') {
        $stmt->bindValue(':exclude_cuno', $excludeCuno);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_name_exists(PDO $conn, string $cuname, string $excludeCuno = ''): bool
{
    $sql = '
        SELECT cuno
        FROM customer_master
        WHERE LOWER(TRIM(cuname)) = LOWER(TRIM(:cuname))
    ';
    if ($excludeCuno !== '') {
        $sql .= ' AND TRIM(cuno) != TRIM(:exclude_cuno)';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':cuname', $cuname);
    if ($excludeCuno !== '') {
        $stmt->bindValue(':exclude_cuno', $excludeCuno);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_search_filter(string $searchValue): array
{
    return [
        'sql' => '(cuno ILIKE :search OR cuname ILIKE :search OR adr_code ILIKE :search)',
        'params' => [':search' => '%' . $searchValue . '%'],
    ];
}

function customer_get_by_cuno(PDO $conn, string $cuno): ?array
{
    $cuno = trim($cuno);
    if ($cuno === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM customer_master
        WHERE TRIM(cuno) = :cuno
        LIMIT 1
    ');
    $stmt->bindValue(':cuno', $cuno);
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

    return $code !== '' ? $code . ($street !== '' ? ' - ' . $street . ($city2 !== '' ? ', ' . $city2 : '') . ($pin !== '' ? ' - ' . $pin : '') : '') : '-';
}

function customer_address_get_by_code(PDO $conn, string $adrCode): ?array
{
    $adrCode = trim($adrCode);
    if ($adrCode === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT adr_code, cuno, st1, st2, city2, pin, city, state, country
        FROM customer_address
        WHERE TRIM(adr_code) = :adr_code
        LIMIT 1
    ');
    $stmt->bindValue(':adr_code', $adrCode);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_address_label(PDO $conn, string $adrCode): string
{
    $row = customer_address_get_by_code($conn, $adrCode);
    if ($row === null) {
        return $adrCode !== '' ? $adrCode : '-';
    }

    return customer_address_format_label($row);
}

function customer_address_labels(PDO $conn, array $adrCodes): array
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
    $stmt = $conn->prepare($sql);
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
        foreach ($codes as $requested) {
            if (trim((string) $requested) === $code) {
                $labels[trim((string) $requested)] = $label;
            }
        }
    }

    return $labels;
}

function customer_address_search(PDO $conn, string $search, int $limit = 50): array
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

    $stmt = $conn->prepare($sql);
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

function customer_entry_actions(string $cuno): string
{
    $cuno = trim($cuno);
    $encodedCuno = base64_encode($cuno);

    return '
        <div class="d-flex gap-1">
            <a href="customer_details.php?id=' . htmlspecialchars($encodedCuno, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-customer-btn"
                data-cuno="' . htmlspecialchars($cuno, ENT_QUOTES, 'UTF-8') . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_customer.php?id=' . htmlspecialchars($encodedCuno, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this customer?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function customer_resolve_address_fields(PDO $conn, string $adrCode): array
{
    $row = customer_address_get_by_code($conn, $adrCode);

    return [
        'city' => trim((string) ($row['city'] ?? '')),
        'state' => trim((string) ($row['state'] ?? '')),
        'country' => trim((string) ($row['country'] ?? '')),
    ];
}

function customer_insert(PDO $conn, array $data, array $addrFields): void
{
    $stmt = $conn->prepare('
        INSERT INTO customer_master (cuno, cuname, adr_code, city, state, country)
        VALUES (:cuno, :cuname, :adr_code, :city, :state, :country)
    ');
    $stmt->bindValue(':cuno', $data['cuno']);
    $stmt->bindValue(':cuname', $data['cuname']);
    $stmt->bindValue(':adr_code', $data['adr_code']);
    $stmt->bindValue(':city', $addrFields['city']);
    $stmt->bindValue(':state', $addrFields['state']);
    $stmt->bindValue(':country', $addrFields['country']);
    $stmt->execute();
}

function customer_update(PDO $conn, string $cuno, array $data, array $addrFields): void
{
    $stmt = $conn->prepare('
        UPDATE customer_master SET
            cuname = :cuname,
            adr_code = :adr_code,
            city = :city,
            state = :state,
            country = :country
        WHERE TRIM(cuno) = :cuno
    ');
    $stmt->bindValue(':cuname', $data['cuname']);
    $stmt->bindValue(':adr_code', $data['adr_code']);
    $stmt->bindValue(':city', $addrFields['city']);
    $stmt->bindValue(':state', $addrFields['state']);
    $stmt->bindValue(':country', $addrFields['country']);
    $stmt->bindValue(':cuno', trim($cuno));
    $stmt->execute();
}

function customer_delete(PDO $conn, string $cuno): void
{
    $stmt = $conn->prepare('
        DELETE FROM customer_master
        WHERE TRIM(cuno) = :cuno
    ');
    $stmt->bindValue(':cuno', trim($cuno));
    $stmt->execute();
}
