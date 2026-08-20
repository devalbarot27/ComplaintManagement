<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function distance_wise_price_range_type_options(): array
{
    return [
        'lt' => 'Less than (<)',
        'between' => 'Between',
        'gt' => 'Greater than (>)',
    ];
}

function distance_wise_price_ensure_schema(PDO $conn): void
{
    $tableStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'distance_wise_prices'
        LIMIT 1
    ");
    $tableStmt->execute();

    if (!$tableStmt->fetchColumn()) {
        $conn->exec("
            CREATE TABLE distance_wise_prices (
                id SERIAL PRIMARY KEY,
                range_type VARCHAR(20) NOT NULL,
                from_km NUMERIC(12,2) NULL,
                to_km NUMERIC(12,2) NULL,
                price NUMERIC(12,2) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_by VARCHAR(150) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            )
        ");
        return;
    }

    $columnStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'distance_wise_prices'
          AND column_name = 'range_type'
        LIMIT 1
    ");
    $columnStmt->execute();
    if (!$columnStmt->fetchColumn()) {
        $conn->exec("ALTER TABLE distance_wise_prices ADD COLUMN range_type VARCHAR(20)");
        $conn->exec("UPDATE distance_wise_prices SET range_type = 'between' WHERE range_type IS NULL");
        $conn->exec("ALTER TABLE distance_wise_prices ALTER COLUMN range_type SET NOT NULL");
        $conn->exec("ALTER TABLE distance_wise_prices ALTER COLUMN from_km DROP NOT NULL");
        $conn->exec("ALTER TABLE distance_wise_prices ALTER COLUMN to_km DROP NOT NULL");
    }
}

function distance_wise_price_from_post(array $post): array
{
    $rangeType = strtolower(trim((string) ($post['range_type'] ?? 'between')));
    $fromKm = trim((string) ($post['from_km'] ?? ''));
    $toKm = trim((string) ($post['to_km'] ?? ''));

    if ($rangeType === 'lt') {
        $fromKm = '';
    } elseif ($rangeType === 'gt') {
        $toKm = '';
    }

    return [
        'range_type' => $rangeType,
        'from_km' => $fromKm,
        'to_km' => $toKm,
        'price' => trim((string) ($post['price'] ?? '')),
        'status' => strtolower(trim((string) ($post['status'] ?? 'active'))),
    ];
}

function distance_wise_price_validate(array $data): ?string
{
    $rangeType = (string) ($data['range_type'] ?? '');
    if (!array_key_exists($rangeType, distance_wise_price_range_type_options())) {
        return 'Range type is required.';
    }

    if ($rangeType === 'lt') {
        if ($data['to_km'] === '') {
            return 'To KM is required for Less than slabs.';
        }
        if (!is_numeric($data['to_km']) || (float) $data['to_km'] <= 0) {
            return 'To KM must be a number greater than 0.';
        }
    } elseif ($rangeType === 'gt') {
        if ($data['from_km'] === '') {
            return 'From KM is required for Greater than slabs.';
        }
        if (!is_numeric($data['from_km']) || (float) $data['from_km'] < 0) {
            return 'From KM must be a number greater than or equal to 0.';
        }
    } else {
        if ($data['from_km'] === '') {
            return 'From KM is required.';
        }
        if (!is_numeric($data['from_km']) || (float) $data['from_km'] < 0) {
            return 'From KM must be a number greater than or equal to 0.';
        }
        if ($data['to_km'] === '') {
            return 'To KM is required.';
        }
        if (!is_numeric($data['to_km']) || (float) $data['to_km'] < 0) {
            return 'To KM must be a number greater than or equal to 0.';
        }
        if ((float) $data['to_km'] <= (float) $data['from_km']) {
            return 'To KM must be greater than From KM.';
        }
    }

    if ($data['price'] === '') {
        return 'Price is required.';
    }
    if (!is_numeric($data['price']) || (float) $data['price'] < 0) {
        return 'Price must be a number greater than or equal to 0.';
    }

    if ($error = rbac_validate_status($data['status'])) {
        return $error;
    }

    return null;
}

/**
 * @return array{start: ?float, end: ?float, start_inclusive: bool, end_inclusive: bool}
 */
function distance_wise_price_interval(array $row): array
{
    $type = strtolower(trim((string) ($row['range_type'] ?? 'between')));
    $fromKm = $row['from_km'] === '' || $row['from_km'] === null ? null : (float) $row['from_km'];
    $toKm = $row['to_km'] === '' || $row['to_km'] === null ? null : (float) $row['to_km'];

    if ($type === 'lt') {
        return [
            'start' => null,
            'end' => $toKm,
            'start_inclusive' => false,
            'end_inclusive' => false,
        ];
    }

    if ($type === 'gt') {
        return [
            'start' => $fromKm,
            'end' => null,
            'start_inclusive' => false,
            'end_inclusive' => false,
        ];
    }

    return [
        'start' => $fromKm,
        'end' => $toKm,
        'start_inclusive' => true,
        'end_inclusive' => true,
    ];
}

function distance_wise_price_intervals_overlap(array $left, array $right): bool
{
    $leftCompletelyBeforeRight =
        $left['end'] !== null
        && $right['start'] !== null
        && $left['end'] <= $right['start'];

    $rightCompletelyBeforeLeft =
        $right['end'] !== null
        && $left['start'] !== null
        && $right['end'] <= $left['start'];

    return !$leftCompletelyBeforeRight && !$rightCompletelyBeforeLeft;
}

function distance_wise_price_range_overlaps(PDO $conn, array $data, int $excludeId = 0): bool
{
    $candidate = distance_wise_price_interval($data);
    $sql = '
        SELECT range_type, from_km, to_km
        FROM distance_wise_prices
        WHERE deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }

    $stmt = $conn->prepare($sql);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (distance_wise_price_intervals_overlap($candidate, distance_wise_price_interval($row))) {
            return true;
        }
    }

    return false;
}

function distance_wise_price_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['status', 'range_type'], function ($search) {
        return [
            'sql' => "(CAST(from_km AS TEXT) ILIKE :km_search
                OR CAST(to_km AS TEXT) ILIKE :km_search
                OR CAST(price AS TEXT) ILIKE :km_search)",
            'params' => [':km_search' => '%' . $search . '%'],
        ];
    });
}

function distance_wise_price_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM distance_wise_prices
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function distance_wise_price_format_number($value): string
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return '-';
    }

    $number = (float) $value;
    if (abs($number - (int) $number) < 0.00001) {
        return (string) (int) $number;
    }

    return number_format($number, 2, '.', '');
}

function distance_wise_price_format_rupees($value): string
{
    $formatted = distance_wise_price_format_number($value);
    if ($formatted === '-') {
        return '-';
    }

    return  html_entity_decode('&#8377;') . $formatted;
}

function distance_wise_price_range_label(array $row): string
{
    $type = strtolower(trim((string) ($row['range_type'] ?? 'between')));

    if ($type === 'lt') {
        return '< ' . distance_wise_price_format_number($row['to_km'] ?? '');
    }

    if ($type === 'gt') {
        return '> ' . distance_wise_price_format_number($row['from_km'] ?? '');
    }

    return distance_wise_price_format_number($row['from_km'] ?? '')
        . ' - '
        . distance_wise_price_format_number($row['to_km'] ?? '');
}

function distance_wise_price_range_type_label(string $rangeType): string
{
    $options = distance_wise_price_range_type_options();

    return $options[$rangeType] ?? 'Between';
}

function distance_wise_price_bind_km(PDOStatement $stmt, string $param, string $value): void
{
    if ($value === '') {
        $stmt->bindValue($param, null, PDO::PARAM_NULL);
        return;
    }

    $stmt->bindValue($param, number_format((float) $value, 2, '.', ''));
}

function distance_wise_price_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="distance_wise_price_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-distance-wise-price-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_distance_wise_price.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this distance wise price?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function distance_wise_price_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO distance_wise_prices (range_type, from_km, to_km, price, status, created_by, created_at)
        VALUES (:range_type, :from_km, :to_km, :price, :status, :created_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':range_type', $data['range_type']);
    distance_wise_price_bind_km($stmt, ':from_km', $data['from_km']);
    distance_wise_price_bind_km($stmt, ':to_km', $data['to_km']);
    $stmt->bindValue(':price', number_format((float) $data['price'], 2, '.', ''));
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function distance_wise_price_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE distance_wise_prices SET
            range_type = :range_type,
            from_km = :from_km,
            to_km = :to_km,
            price = :price,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':range_type', $data['range_type']);
    distance_wise_price_bind_km($stmt, ':from_km', $data['from_km']);
    distance_wise_price_bind_km($stmt, ':to_km', $data['to_km']);
    $stmt->bindValue(':price', number_format((float) $data['price'], 2, '.', ''));
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function distance_wise_price_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE distance_wise_prices
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function distance_wise_price_km_matches(float $km, array $row): bool
{
    $interval = distance_wise_price_interval($row);

    if ($interval['start'] !== null) {
        if ($interval['start_inclusive']) {
            if ($km < $interval['start']) {
                return false;
            }
        } elseif ($km <= $interval['start']) {
            return false;
        }
    }

    if ($interval['end'] !== null) {
        if ($interval['end_inclusive']) {
            if ($km > $interval['end']) {
                return false;
            }
        } elseif ($km >= $interval['end']) {
            return false;
        }
    }

    return true;
}

function distance_wise_price_get_active_slabs(PDO $conn): array
{
    distance_wise_price_ensure_schema($conn);

    $stmt = $conn->query("
        SELECT id, range_type, from_km, to_km, price
        FROM distance_wise_prices
        WHERE deleted_at IS NULL
          AND status = 'active'
        ORDER BY COALESCE(from_km, to_km) ASC, id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function distance_wise_price_find_for_km(PDO $conn, float $km): ?array
{
    $matches = [];
    foreach (distance_wise_price_get_active_slabs($conn) as $row) {
        if (distance_wise_price_km_matches($km, $row)) {
            $matches[] = $row;
        }
    }

    if ($matches === []) {
        return null;
    }

    usort($matches, static function (array $left, array $right): int {
        $leftStart = $left['from_km'] === null || $left['from_km'] === '' ? -INF : (float) $left['from_km'];
        $rightStart = $right['from_km'] === null || $right['from_km'] === '' ? -INF : (float) $right['from_km'];

        return $leftStart <=> $rightStart;
    });

    return $matches[count($matches) - 1];
}

function distance_wise_price_slabs_for_js(array $slabs): array
{
    $payload = [];
    foreach ($slabs as $row) {
        $payload[] = [
            'range_type' => (string) ($row['range_type'] ?? 'between'),
            'from_km' => $row['from_km'] === null || $row['from_km'] === '' ? null : (float) $row['from_km'],
            'to_km' => $row['to_km'] === null || $row['to_km'] === '' ? null : (float) $row['to_km'],
            'price' => (float) ($row['price'] ?? 0),
            'price_label' => distance_wise_price_format_rupees($row['price'] ?? ''),
        ];
    }

    return $payload;
}