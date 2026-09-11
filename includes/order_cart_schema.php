<?php

require_once __DIR__ . '/product_helpers.php';

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 * Ensures cart and submitted-order tables can store the selected price type.
 */
function cart_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    product_ensure_schema($conn);

    // PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
    $conn->exec("
        ALTER TABLE tbl_vayu_cartitems
        ADD COLUMN IF NOT EXISTS price_type VARCHAR(50) NULL DEFAULT 'clp'
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS price_type VARCHAR(50) NULL DEFAULT 'clp'
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS customer_id INTEGER NULL
    ");
    $conn->exec("
        ALTER TABLE plexecom_customer_units
        ADD COLUMN IF NOT EXISTS order_source VARCHAR(30) NULL DEFAULT 'normal'
    ");

    $ensured = true;
}

function plexecom_public_table_exists(PDO $conn, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    $stmt = $conn->prepare('SELECT to_regclass(:name)');
    $stmt->execute([':name' => 'public.' . $table]);
    $cache[$table] = (bool) $stmt->fetchColumn();

    return $cache[$table];
}

function plexecom_public_column_exists(PDO $conn, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = :table_name
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->execute([
        ':table_name' => $table,
        ':column_name' => $column,
    ]);
    $cache[$key] = (bool) $stmt->fetchColumn();

    return $cache[$key];
}

/**
 * SQL expression for Recent Orders "Order Type":
 * Create Order / Order Booking → Normal Order
 * FOC → FOC
 * Service Claim → Service Claim
 */
function plexecom_order_type_sql(PDO $conn, string $alias = 'a'): string
{
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    if ($a === '') {
        $a = 'a';
    }

    $sql = "CASE
            WHEN LOWER(TRIM(COALESCE({$a}.order_source, ''))) = 'foc' THEN 'FOC'
            WHEN LOWER(TRIM(COALESCE({$a}.order_source, ''))) IN ('service_claim', 'service-claim', 'service') THEN 'Service Claim'
            WHEN UPPER(TRIM(COALESCE({$a}.pono, ''))) LIKE 'FOC-%' THEN 'FOC'
            WHEN UPPER(TRIM(COALESCE({$a}.pono, ''))) LIKE 'SC-%'
              OR UPPER(TRIM(COALESCE({$a}.pono, ''))) LIKE 'SVC-%'
              OR UPPER(TRIM(COALESCE({$a}.pono, ''))) LIKE 'SERVICE-%' THEN 'Service Claim'";

    if (plexecom_public_table_exists($conn, 'foc_claims')
        && plexecom_public_column_exists($conn, 'foc_claims', 'ln_order_number')
    ) {
        $focDeleted = plexecom_public_column_exists($conn, 'foc_claims', 'deleted_at')
            ? 'AND fc.deleted_at IS NULL'
            : '';
        $sql .= "
            WHEN EXISTS (
                SELECT 1
                FROM foc_claims fc
                WHERE TRIM(COALESCE(fc.ln_order_number, '')) <> ''
                  AND TRIM(fc.ln_order_number) = TRIM({$a}.refno)
                  {$focDeleted}
            ) THEN 'FOC'";
    }

    if (plexecom_public_table_exists($conn, 'service_claims')
        && plexecom_public_column_exists($conn, 'service_claims', 'ln_order_number')
    ) {
        $scDeleted = plexecom_public_column_exists($conn, 'service_claims', 'deleted_at')
            ? 'AND sc.deleted_at IS NULL'
            : '';
        $sql .= "
            WHEN EXISTS (
                SELECT 1
                FROM service_claims sc
                WHERE TRIM(COALESCE(sc.ln_order_number, '')) <> ''
                  AND TRIM(sc.ln_order_number) = TRIM({$a}.refno)
                  {$scDeleted}
            ) THEN 'Service Claim'";
    }

    $sql .= "
            ELSE 'Normal Order'
        END";

    return $sql;
}

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 *
 * @return array<string, string>
 */
function cart_price_type_options(): array
{
    return [
        'clp' => 'CLP - Customer List Price',
        'dealer' => 'Dealer Price',
        'level_1' => 'Level 1 Approval Price',
        'level_2' => 'Level 2 Approval Price',
    ];
}

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 */
function cart_normalize_price_type(?string $value): string
{
    $normalized = strtolower(trim((string) $value));
    $options = cart_price_type_options();

    if (array_key_exists($normalized, $options)) {
        return $normalized;
    }

    return 'clp';
}

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 */
function cart_price_type_label(?string $value): string
{
    $key = cart_normalize_price_type($value);
    $options = cart_price_type_options();

    return $options[$key] ?? 'CLP - Customer List Price';
}

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 *
 * @return array{clp: float, dealer: float, level_1: float, level_2: float}
 */
function cart_product_price_map(array $product): array
{
    return [
        'clp' => cart_numeric_price($product['cos'] ?? null),
        'dealer' => cart_numeric_price($product['dealer_price'] ?? null),
        'level_1' => cart_numeric_price($product['level_1_approval_price'] ?? null),
        'level_2' => cart_numeric_price($product['level_2_approval_price'] ?? null),
    ];
}

/**
 * L1 + L2 approval when dealer price is below L1.
 * Dealer price at or above L1 goes for Level 1 approval only.
 * Dealer price below L2 is rejected and is not added to the cart.
 */
function cart_dealer_price_requires_l2(array $product, float $unitPrice): bool
{
    $prices = cart_product_price_map($product);
    $l1 = $prices['level_1'];

    return $l1 > 0 && $unitPrice < $l1;
}

function cart_dealer_price_below_l2(array $product, float $unitPrice): bool
{
    $prices = cart_product_price_map($product);
    $l2 = $prices['level_2'];

    return $l2 > 0 && $unitPrice < $l2;
}

/**
 * Classify cart price type from the entered dealer price vs catalog L1 / L2 thresholds.
 */
function cart_price_type_from_unit_price(array $product, float $unitPrice): string
{
    if (cart_dealer_price_requires_l2($product, $unitPrice)) {
        return 'level_2';
    }

    $prices = cart_product_price_map($product);
    $l1 = $prices['level_1'];
    if ($l1 > 0 && $unitPrice <= $l1) {
        return 'level_1';
    }

    return 'dealer';
}

/**
 * PRICE TYPE: Added for CLP / Level 1 / Level 2 Approval Price
 */
function cart_resolve_unit_price(array $product, string $priceType): float
{
    $prices = cart_product_price_map($product);
    $key = cart_normalize_price_type($priceType);

    return $prices[$key] ?? $prices['clp'];
}

function cart_numeric_price($value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    if (!is_numeric($value)) {
        return 0.0;
    }

    return (float) $value;
}