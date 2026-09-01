<?php

function order_normalize_row(array $row): array
{
    $row['order_id'] = trim((string) ($row['order_no'] ?? $row['order_id'] ?? ''));

    return $row;
}

function order_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM tbl_vayu_orders_header
        WHERE id = :id
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? order_normalize_row($row) : null;
}

function order_get_by_order_id(PDO $conn, string $orderId): ?array
{
    $orderId = trim($orderId);
    if ($orderId === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT *
        FROM tbl_vayu_orders_header
        WHERE order_no = :order_no
    ');
    $stmt->bindValue(':order_no', $orderId);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? order_normalize_row($row) : null;
}

function order_search(PDO $conn, string $term, int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT id, order_no, dealer_address, order_category, created_at
        FROM tbl_vayu_orders_header
        WHERE order_no ILIKE :term
           OR COALESCE(dealer_address, \'\') ILIKE :term
           OR COALESCE(order_category, \'\') ILIKE :term
        ORDER BY id DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = order_normalize_row($row);
    }

    return $rows;
}

function order_to_select2_result(array $row): array
{
    $row = order_normalize_row($row);
    $orderNo = $row['order_id'];

    return [
        'id' => (int) $row['id'],
        'text' => $orderNo,
        'order_id' => $orderNo,
        'order_ref_id' => (int) $row['id'],
    ];
}

function order_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}
