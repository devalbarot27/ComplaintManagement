<?php

function machine_model_search_products(PDO $conn, string $term, int $limit = 25): array
{
    $stmt = $conn->prepare('
        SELECT tplcode, tpldesc
        FROM product_master
        WHERE deleted_at IS NULL
          AND (
                tplcode ILIKE :term
             OR tpldesc ILIKE :term
          )
        ORDER BY tplcode
        LIMIT :limit
    ');
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function machine_model_search_plexecom_customer_units(PDO $conn, string $term, int $limit = 25): array
{
    $stmt = $conn->prepare("
        SELECT DISTINCT ON (TRIM(tplcode))
            TRIM(tplcode) AS tplcode,
            TRIM(tpldesc) AS tpldesc
        FROM plexecom_customer_units
        WHERE TRIM(COALESCE(tplcode, '')) <> ''
          AND (
                tplcode ILIKE :term
             OR tpldesc ILIKE :term
          )
        ORDER BY TRIM(tplcode), tpldesc
        LIMIT :limit
    ");
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function machine_model_normalize_row(array $row): ?array
{
    $code = trim((string) ($row['tplcode'] ?? ''));
    $description = trim((string) ($row['tpldesc'] ?? ''));

    if ($code === '') {
        return null;
    }

    return [
        'tplcode' => $code,
        'tpldesc' => $description,
    ];
}

function machine_model_search(PDO $conn, string $term, string $dpst = '90092', int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $combined = [];

    foreach (machine_model_search_products($conn, $term, $limit) as $row) {
        $normalized = machine_model_normalize_row($row);
        if ($normalized === null) {
            continue;
        }

        $combined[strtoupper($normalized['tplcode'])] = $normalized;
    }

    foreach (machine_model_search_plexecom_customer_units($conn, $term, $limit) as $row) {
        $normalized = machine_model_normalize_row($row);
        if ($normalized === null) {
            continue;
        }

        $key = strtoupper($normalized['tplcode']);
        if (!isset($combined[$key])) {
            $combined[$key] = $normalized;
        }
    }

    $rows = array_values($combined);

    usort($rows, static function (array $a, array $b): int {
        return strcasecmp($a['tplcode'], $b['tplcode']);
    });

    if (count($rows) > $limit) {
        $rows = array_slice($rows, 0, $limit);
    }

    return $rows;
}

function machine_model_to_select2_result(array $row): array
{
    $code = trim((string) ($row['tplcode'] ?? ''));
    $description = trim((string) ($row['tpldesc'] ?? ''));
    $label = $code . ' - ' . $description;

    return [
        'id' => $code,
        'text' => $label,
        'tplcode' => $code,
        'tpldesc' => $description,
    ];
}
