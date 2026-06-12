<?php

function order_format_id(int $year, int $sequence): string
{
    return sprintf('ORD/%d/%05d', $year, $sequence);
}

function order_next_sequence(PDO $conn, int $year): int
{
    $stmt = $conn->prepare('
        SELECT COALESCE(MAX(sequence_number), 0) + 1 AS next_seq
        FROM orders
        WHERE order_year = :year
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':year', $year, PDO::PARAM_INT);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function order_from_post(array $post): array
{
    return [
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'dealer_name' => trim((string) ($post['dealer_name'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
    ];
}

function order_validate_create(array $data): ?string
{
    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }

    if ($data['invoice_date'] === '') {
        return 'Invoice Date is required.';
    }

    if ($data['dealer_name'] === '') {
        return 'Dealer Name is required.';
    }

    if ($data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    return null;
}

function order_create(PDO $conn, array $data, int $createdBy = 1): array
{
    $error = order_validate_create($data);
    if ($error !== null) {
        throw new InvalidArgumentException($error);
    }

    $conn->beginTransaction();

    try {
        $year = (int) date('Y');
        $sequence = order_next_sequence($conn, $year);
        $orderId = order_format_id($year, $sequence);

        $stmt = $conn->prepare('
            INSERT INTO orders (
                order_id, order_year, sequence_number,
                fab_number, customer_name, invoice_date,
                dealer_name, machine_model, created_by
            ) VALUES (
                :order_id, :order_year, :sequence_number,
                :fab_number, :customer_name, :invoice_date,
                :dealer_name, :machine_model, :created_by
            )
            RETURNING id, order_id, order_year, sequence_number,
                      fab_number, customer_name, invoice_date,
                      dealer_name, machine_model, created_at
        ');

        $stmt->bindValue(':order_id', $orderId);
        $stmt->bindValue(':order_year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':sequence_number', $sequence, PDO::PARAM_INT);
        $stmt->bindValue(':fab_number', $data['fab_number']);
        $stmt->bindValue(':customer_name', $data['customer_name']);
        $stmt->bindValue(':invoice_date', $data['invoice_date']);
        $stmt->bindValue(':dealer_name', $data['dealer_name']);
        $stmt->bindValue(':machine_model', $data['machine_model']);
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $conn->commit();

        return $row;
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function order_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM orders
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function order_get_by_order_id(PDO $conn, string $orderId): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM orders
        WHERE order_id = :order_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':order_id', trim($orderId));
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function order_search(PDO $conn, string $term, int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT id, order_id, fab_number, customer_name, invoice_date, dealer_name, machine_model
        FROM orders
        WHERE deleted_at IS NULL
          AND (
                order_id ILIKE :term
             OR fab_number ILIKE :term
             OR customer_name ILIKE :term
             OR dealer_name ILIKE :term
             OR machine_model ILIKE :term
          )
        ORDER BY order_year DESC, sequence_number DESC
        LIMIT :limit
    ');
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function order_to_select2_result(array $row): array
{
    $orderId = trim((string) $row['order_id']);
    $customerName = trim((string) ($row['customer_name'] ?? ''));

    return [
        'id' => (int) $row['id'],
        'text' => $orderId . ($customerName !== '' ? ' — ' . $customerName : ''),
        'order_id' => $orderId,
        'order_ref_id' => (int) $row['id'],
        'fab_number' => trim((string) ($row['fab_number'] ?? '')),
        'customer_name' => $customerName,
        'invoice_date' => $row['invoice_date'] ?? '',
        'dealer_name' => trim((string) ($row['dealer_name'] ?? '')),
        'machine_model' => trim((string) ($row['machine_model'] ?? '')),
    ];
}

function order_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y', strtotime($value));
}
