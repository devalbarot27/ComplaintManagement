<?php

require_once __DIR__ . '/rbac_helpers.php';

function product_yn_options(): array
{
    return [
        'Y' => 'Yes (Y)',
        'N' => 'No (N)',
    ];
}

function product_order_type_options(): array
{
    return [
        '1' => 'Units',
        '2' => 'Spares',
    ];
}

function product_order_type_label($value): string
{
    $key = trim((string) $value);
    $options = product_order_type_options();

    return $options[$key] ?? product_display_value($value);
}

function product_normalize_yn(?string $value, string $default = ''): string
{
    $normalized = strtoupper(trim((string) $value));

    if ($normalized === 'YES' || $normalized === '1' || $normalized === 'TRUE') {
        return 'Y';
    }

    if ($normalized === 'NO' || $normalized === '0' || $normalized === 'FALSE') {
        return 'N';
    }

    if ($normalized === 'Y' || $normalized === 'N') {
        return $normalized;
    }

    return $default;
}

function product_from_post(array $post): array
{
    return [
        'dpst' => trim((string) ($post['dpst'] ?? '')),
        'product_group' => trim((string) ($post['product_group'] ?? '')),
        'tplcode' => trim((string) ($post['tplcode'] ?? '')),
        'tpldesc' => trim((string) ($post['tpldesc'] ?? '')),
        'dealer_price' => trim((string) ($post['dealer_price'] ?? '')),
        'tod_flag' => product_normalize_yn($post['tod_flag'] ?? 'N', 'N'),
        'excisable' => product_normalize_yn($post['excisable'] ?? 'N', 'N'),
        'mc' => trim((string) ($post['mc'] ?? '')),
        'vc' => trim((string) ($post['vc'] ?? '')),
        'fc' => trim((string) ($post['fc'] ?? '')),
        'cos' => trim((string) ($post['cos'] ?? '')),
        'valid' => product_normalize_yn($post['valid'] ?? '', ''),
        'warehouse' => trim((string) ($post['warehouse'] ?? '')),
        'otcode' => trim((string) ($post['otcode'] ?? '')),
        'company' => trim((string) ($post['company'] ?? '')),
        'order_type' => trim((string) ($post['order_type'] ?? '')),
    ];
}

function product_is_numeric_value(string $value): bool
{
    if ($value === '') {
        return true;
    }

    return (bool) preg_match('/^-?\d+(\.\d+)?$/', $value);
}

function product_is_integer_value(string $value): bool
{
    if ($value === '') {
        return true;
    }

    return (bool) preg_match('/^-?\d+$/', $value);
}

function product_validate(array $data): ?string
{
    if ($data['dpst'] === '') {
        return 'DPST is required.';
    }
    if (strlen($data['dpst']) > 10) {
        return 'DPST cannot exceed 10 characters.';
    }

    if ($data['product_group'] === '') {
        return 'Product Group is required.';
    }
    if (strlen($data['product_group']) > 50) {
        return 'Product Group cannot exceed 50 characters.';
    }

    if ($data['tplcode'] === '') {
        return 'TPL Code is required.';
    }
    if (strlen($data['tplcode']) > 20) {
        return 'TPL Code cannot exceed 20 characters.';
    }

    if ($data['tpldesc'] === '') {
        return 'TPL Description is required.';
    }
    if (strlen($data['tpldesc']) > 60) {
        return 'TPL Description cannot exceed 60 characters.';
    }

    if ($data['dealer_price'] !== '' && !product_is_numeric_value($data['dealer_price'])) {
        return 'Dealer Price must be numeric.';
    }
    if (strlen($data['dealer_price']) > 7) {
        return 'Dealer Price cannot exceed 7 characters.';
    }

    if ($data['tod_flag'] !== '' && !array_key_exists($data['tod_flag'], product_yn_options())) {
        return 'TOD Flag must be Y or N.';
    }

    if ($data['excisable'] !== '' && !array_key_exists($data['excisable'], product_yn_options())) {
        return 'Excisable must be Y or N.';
    }

    if ($data['mc'] !== '' && !product_is_numeric_value($data['mc'])) {
        return 'MC must be numeric.';
    }

    if ($data['vc'] !== '' && !product_is_numeric_value($data['vc'])) {
        return 'VC must be numeric.';
    }

    if ($data['fc'] !== '' && !product_is_numeric_value($data['fc'])) {
        return 'FC must be numeric.';
    }

    if ($data['cos'] === '') {
        return 'COS (Price) is required.';
    }
    if (!product_is_numeric_value($data['cos'])) {
        return 'COS (Price) must be numeric.';
    }

    if ($data['valid'] === '') {
        return 'Valid is required.';
    }
    if (!array_key_exists($data['valid'], product_yn_options())) {
        return 'Valid must be Y or N.';
    }

    if ($data['warehouse'] === '') {
        return 'Warehouse is required.';
    }
    if (strlen($data['warehouse']) > 3) {
        return 'Warehouse cannot exceed 3 characters.';
    }

    if (strlen($data['otcode']) > 3) {
        return 'OT Code cannot exceed 3 characters.';
    }

    if ($data['company'] === '') {
        return 'Company is required.';
    }
    if (!product_is_integer_value($data['company'])) {
        return 'Company must be an integer.';
    }

    if ($data['order_type'] === '') {
        return 'Order Type is required.';
    }
    if (!array_key_exists($data['order_type'], product_order_type_options())) {
        return 'Order Type must be Units or Spares.';
    }

    return null;
}

function product_tplcode_exists(PDO $conn, string $tplcode, int $orderType, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM product_master_vayu
        WHERE LOWER(TRIM(tplcode)) = LOWER(TRIM(:tplcode))
          AND order_type = :order_type
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':tplcode', $tplcode);
    $stmt->bindValue(':order_type', $orderType, PDO::PARAM_INT);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function product_nullable_numeric(?string $value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }

    return $value;
}

function product_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, [
        'dpst',
        'product_group',
        'tplcode',
        'tpldesc',
        'dealer_price',
        'tod_flag',
        'excisable',
        'CAST(mc AS TEXT)',
        'CAST(vc AS TEXT)',
        'CAST(fc AS TEXT)',
        'CAST(cos AS TEXT)',
        'valid',
        'warehouse',
        'otcode',
        'CAST(company AS TEXT)',
        'CAST(order_type AS TEXT)',
        'status',
        'updated_by',
    ]);
}

function product_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM product_master_vayu
        WHERE id = :id
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function product_yn_badge(string $value): string
{
    $normalized = product_normalize_yn($value, '');
    if ($normalized === 'Y') {
        return '<span class="badge bg-success">Y</span>';
    }
    if ($normalized === 'N') {
        return '<span class="badge bg-secondary">N</span>';
    }

    $raw = trim($value);
    return $raw !== '' ? htmlspecialchars($raw, ENT_QUOTES, 'UTF-8') : '-';
}

function product_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="product_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-product-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_product.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this product?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function product_resolve_updated_by(?string $username, ?int $userId = null): ?string
{
    $username = trim((string) $username);
    if ($username !== '') {
        return substr($username, 0, 30);
    }

    $userId = (int) $userId;
    if ($userId > 0) {
        return substr((string) $userId, 0, 30);
    }

    return null;
}

function product_bind_common(PDOStatement $stmt, array $data): void
{
    $stmt->bindValue(':dpst', $data['dpst']);
    $stmt->bindValue(':product_group', $data['product_group']);
    $stmt->bindValue(':tplcode', $data['tplcode']);
    $stmt->bindValue(':tpldesc', $data['tpldesc']);
    $stmt->bindValue(
        ':dealer_price',
        $data['dealer_price'] !== '' ? $data['dealer_price'] : '0'
    );
    $stmt->bindValue(':tod_flag', $data['tod_flag'] !== '' ? $data['tod_flag'] : 'N');
    $stmt->bindValue(':excisable', $data['excisable'] !== '' ? $data['excisable'] : 'N');
    $stmt->bindValue(':mc', product_nullable_numeric($data['mc']));
    $stmt->bindValue(':vc', product_nullable_numeric($data['vc']));
    $stmt->bindValue(':fc', product_nullable_numeric($data['fc']));
    $stmt->bindValue(':cos', product_nullable_numeric($data['cos']));
    $stmt->bindValue(':valid', $data['valid']);
    $stmt->bindValue(':warehouse', $data['warehouse']);
    $stmt->bindValue(
        ':otcode',
        $data['otcode'] !== '' ? $data['otcode'] : null,
        $data['otcode'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL
    );
    $stmt->bindValue(':company', (int) $data['company'], PDO::PARAM_INT);
    $stmt->bindValue(':order_type', (int) $data['order_type'], PDO::PARAM_INT);
}

function product_insert(PDO $conn, array $data, ?string $updatedBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO product_master_vayu (
            dpst, product_group, tplcode, tpldesc, dealer_price,
            tod_flag, excisable, mc, vc, fc, cos, valid,
            warehouse, otcode, company, order_type,
            status, updated_by, updated_date
        ) VALUES (
            :dpst, :product_group, :tplcode, :tpldesc, :dealer_price,
            :tod_flag, :excisable, :mc, :vc, :fc, :cos, :valid,
            :warehouse, :otcode, :company, :order_type,
            :status, :updated_by, CURRENT_TIMESTAMP
        )
    ');

    product_bind_common($stmt, $data);
    $stmt->bindValue(':status', '1');
    $resolvedUpdatedBy = product_resolve_updated_by($updatedBy);
    if ($resolvedUpdatedBy === null) {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $resolvedUpdatedBy);
    }
    $stmt->execute();
}

function product_update(PDO $conn, int $id, array $data, ?string $updatedBy): void
{
    $stmt = $conn->prepare('
        UPDATE product_master_vayu SET
            dpst = :dpst,
            product_group = :product_group,
            tplcode = :tplcode,
            tpldesc = :tpldesc,
            dealer_price = :dealer_price,
            tod_flag = :tod_flag,
            excisable = :excisable,
            mc = :mc,
            vc = :vc,
            fc = :fc,
            cos = :cos,
            valid = :valid,
            warehouse = :warehouse,
            otcode = :otcode,
            company = :company,
            order_type = :order_type,
            updated_by = :updated_by,
            updated_date = CURRENT_TIMESTAMP
        WHERE id = :id
    ');

    product_bind_common($stmt, $data);
    $resolvedUpdatedBy = product_resolve_updated_by($updatedBy);
    if ($resolvedUpdatedBy === null) {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $resolvedUpdatedBy);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function product_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        DELETE FROM product_master_vayu
        WHERE id = :id
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function product_display_value($value): string
{
    $value = trim((string) $value);
    return $value !== '' ? $value : '-';
}

function product_format_cos($value): string
{
    $value = trim((string) $value);
    if ($value === '' || !is_numeric($value)) {
        return '-';
    }

    return number_format((float) $value, 2, '.', '');
}
