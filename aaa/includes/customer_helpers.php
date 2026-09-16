<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function customer_from_post(array $post): array
{
    return [
        'customer_code' => trim((string) ($post['customer_code'] ?? '')),
    ];
}

function customer_validate(array $data): ?string
{
    if ($data['customer_code'] === '') {
        return 'Customer code is required.';
    }

    if (strlen($data['customer_code']) > 9) {
        return 'Customer code cannot exceed 9 characters.';
    }

    return null;
}

function customer_code_exists(PDO $conn, string $customerCode, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM customer_master_sync
        WHERE LOWER(TRIM(customer_code)) = LOWER(TRIM(:customer_code))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':customer_code', $customerCode);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_search_filter(string $searchValue): array
{
    return [
        'sql' => '(cms.customer_code ILIKE :search OR cm.cuname ILIKE :search)',
        'params' => [':search' => '%' . $searchValue . '%'],
    ];
}

function customer_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT
            cms.*,
            TRIM(cm.cuname) AS customer_name
        FROM customer_master_sync cms
        LEFT JOIN customer_master cm ON TRIM(cm.cuno) = TRIM(cms.customer_code)
        WHERE cms.id = :id
          AND cms.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_master_get_by_code(PDO $conn, string $cuno): ?array
{
    $cuno = trim($cuno);
    if ($cuno === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT TRIM(cuno) AS cuno, TRIM(cuname) AS cuname
        FROM customer_master
        WHERE TRIM(cuno) = :cuno
        LIMIT 1
    ');
    $stmt->bindValue(':cuno', $cuno);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_master_format_label(array $row): string
{
    $cuno = trim((string) ($row['cuno'] ?? ''));
    $cuname = trim((string) ($row['cuname'] ?? ''));

    if ($cuno === '') {
        return '-';
    }

    return $cuname !== '' ? ($cuno . ' - ' . $cuname) : $cuno;
}

/**
 * @return array<int, array{id: string, text: string, cuname: string}>
 */
function customer_master_search(PDO $conn, string $search, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $sql = '
        SELECT TRIM(cuno) AS cuno, TRIM(cuname) AS cuname
        FROM customer_master
        WHERE length(TRIM(cuno)) > 0
    ';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (
            LOWER(cuno) LIKE LOWER(:search)
            OR LOWER(COALESCE(cuname, \'\')) LIKE LOWER(:search)
        )';
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY cuno ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cuno = trim((string) ($row['cuno'] ?? ''));
        if ($cuno === '') {
            continue;
        }
        $results[] = [
            'id' => $cuno,
            'text' => customer_master_format_label($row),
            'cuname' => trim((string) ($row['cuname'] ?? '')),
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
                onclick="return confirm(\'Delete this customer sync record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function customer_insert(PDO $conn, array $data, string $username): void
{
    $stmt = $conn->prepare('
        INSERT INTO customer_master_sync (customer_code, added_by, created_at)
        VALUES (:customer_code, :added_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':customer_code', $data['customer_code']);
    if ($username === '') {
        $stmt->bindValue(':added_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':added_by', $username);
    }
    $stmt->execute();
}

function customer_update(PDO $conn, int $id, array $data, string $username): void
{
    $existing = customer_get_by_id($conn, $id);
    if ($existing === null) {
        return;
    }

    $oldCustomerCode = trim((string) ($existing['customer_code'] ?? ''));
    $newCustomerCode = trim((string) ($data['customer_code'] ?? ''));

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('
            UPDATE customer_master_sync SET
                customer_code = :customer_code,
                updated_by = :updated_by,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $stmt->bindValue(':customer_code', $data['customer_code']);
        if ($username === '') {
            $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':updated_by', $username);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($oldCustomerCode !== '' && strcasecmp($oldCustomerCode, $newCustomerCode) !== 0) {
            customer_clear_users_customer_code($conn, $oldCustomerCode);
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function customer_clear_users_customer_code(PDO $conn, string $customerCode): int
{
    $customerCode = trim($customerCode);
    if ($customerCode === '') {
        return 0;
    }

    $stmt = $conn->prepare('
        UPDATE user_master
        SET customer_code = NULL,
            updated_at = CURRENT_TIMESTAMP
        WHERE LOWER(TRIM(customer_code)) = LOWER(TRIM(:customer_code))
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':customer_code', $customerCode);
    $stmt->execute();

    return $stmt->rowCount();
}

function customer_soft_delete(PDO $conn, int $id, string $username = ''): void
{
    $record = customer_get_by_id($conn, $id);
    if ($record === null) {
        return;
    }

    $customerCode = trim((string) ($record['customer_code'] ?? ''));

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare('
            UPDATE customer_master_sync
            SET deleted_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP,
                updated_by = COALESCE(NULLIF(:updated_by, \'\'), updated_by)
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $stmt->bindValue(':updated_by', $username);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($customerCode !== '') {
            customer_clear_users_customer_code($conn, $customerCode);
        }

        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}