<?php

function machine_model_search(PDO $conn, string $term, string $dpst = '90092', int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT tplcode, tpldesc
        FROM (
            SELECT TRIM(tplcode) AS tplcode, TRIM(tpldesc) AS tpldesc
            FROM product_master
            WHERE dpst::text = TRIM(:dpst)
              AND UPPER(TRIM(status)) = 'YES'
              AND UPPER(TRIM(valid)) = 'Y'
              AND (
                    tplcode ILIKE :term
                 OR tpldesc ILIKE :term
              )

            UNION

            SELECT TRIM(tplcode) AS tplcode, TRIM(tpldesc) AS tpldesc
            FROM plexecom_customer_units
            WHERE TRIM(dpst) = TRIM(:dpst)
              AND TRIM(COALESCE(tplcode, '')) <> ''
              AND (
                    tplcode ILIKE :term
                 OR tpldesc ILIKE :term
              )
        ) AS combined
        WHERE tplcode <> ''
        ORDER BY tplcode
        LIMIT :limit
    ");
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':dpst', $dpst);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
