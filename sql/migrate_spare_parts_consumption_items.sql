CREATE TABLE IF NOT EXISTS spare_parts_consumption_items (
    id SERIAL PRIMARY KEY,
    spare_parts_consumption_id INTEGER NOT NULL,
    spare_kit_number VARCHAR(100) NOT NULL,
    reason VARCHAR(50) NOT NULL,
    quantity NUMERIC(10, 2) NOT NULL,
    order_value NUMERIC(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_spare_parts_consumption_items_parent_id
    ON spare_parts_consumption_items (spare_parts_consumption_id);

CREATE INDEX IF NOT EXISTS idx_spare_parts_consumption_items_deleted_at
    ON spare_parts_consumption_items (deleted_at);

INSERT INTO spare_parts_consumption_items (
    spare_parts_consumption_id,
    spare_kit_number,
    reason,
    quantity,
    order_value,
    created_at,
    updated_at
)
SELECT
    sp.id,
    sp.spare_kit_number,
    sp.reason,
    sp.quantity,
    sp.order_value,
    sp.created_at,
    sp.updated_at
FROM spare_parts_consumption sp
WHERE sp.deleted_at IS NULL
  AND sp.spare_kit_number IS NOT NULL
  AND TRIM(sp.spare_kit_number) <> ''
  AND NOT EXISTS (
      SELECT 1
      FROM spare_parts_consumption_items spi
      WHERE spi.spare_parts_consumption_id = sp.id
        AND spi.deleted_at IS NULL
  );

ALTER TABLE spare_parts_consumption
    DROP COLUMN IF EXISTS spare_kit_number,
    DROP COLUMN IF EXISTS quantity,
    DROP COLUMN IF EXISTS order_value,
    DROP COLUMN IF EXISTS reason;
