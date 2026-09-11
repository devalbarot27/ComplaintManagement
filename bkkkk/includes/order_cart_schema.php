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

    $ensured = true;
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