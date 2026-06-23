<?php

function ln_invoice_format_date(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '';
    }

    $value = trim($value);

    if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
        return substr($value, 0, 10);
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function ln_invoice_resolve_invoice_date_for_fab(PDO $conn, string $fabno): ?string
{
    $fabno = trim($fabno);
    if ($fabno === '' || !ln_invoice_fabno_exists($conn, $fabno)) {
        return null;
    }

    return ln_invoice_get_invoice_date_by_fabno($conn, $fabno);
}

function ln_invoice_search_fabno(PDO $conn, string $term, int $limit = 25): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT fabno, MAX(inv_dt) AS inv_dt
        FROM ln_invoice_details
        WHERE fabno IS NOT NULL
          AND TRIM(fabno) <> \'\'
          AND fabno ILIKE :term
        GROUP BY fabno
        ORDER BY fabno
        LIMIT :limit
    ');
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ln_invoice_get_invoice_date_by_fabno(PDO $conn, string $fabno): ?string
{
    $fabno = trim($fabno);
    if ($fabno === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT MAX(inv_dt) AS inv_dt
        FROM ln_invoice_details
        WHERE fabno = :fabno
    ');
    $stmt->bindValue(':fabno', $fabno);
    $stmt->execute();

    $invDt = $stmt->fetchColumn();

    if ($invDt === false || $invDt === null || trim((string) $invDt) === '') {
        return null;
    }

    return ln_invoice_format_date((string) $invDt);
}

function ln_invoice_fabno_to_select2_result(array $row): array
{
    $fabno = trim((string) ($row['fabno'] ?? ''));

    return [
        'id' => $fabno,
        'text' => $fabno,
        'invoice_date' => ln_invoice_format_date($row['inv_dt'] ?? null),
    ];
}

function ln_invoice_fabno_exists(PDO $conn, string $fabno): bool
{
    $fabno = trim($fabno);
    if ($fabno === '') {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM ln_invoice_details
        WHERE fabno = :fabno
        LIMIT 1
    ');
    $stmt->bindValue(':fabno', $fabno);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}
