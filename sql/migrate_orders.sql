CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    order_id VARCHAR(20) NOT NULL,
    order_year INTEGER NOT NULL,
    sequence_number INTEGER NOT NULL,
    fab_number VARCHAR(20),
    customer_name VARCHAR(200) NOT NULL,
    invoice_date DATE NOT NULL,
    dealer_name VARCHAR(200),
    machine_model VARCHAR(150),
    created_by INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_order_id_unique
    ON orders (order_id)
    WHERE deleted_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS idx_orders_year_sequence_unique
    ON orders (order_year, sequence_number)
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_orders_deleted_at
    ON orders (deleted_at);

ALTER TABLE installed_base
    ADD COLUMN IF NOT EXISTS order_ref_id INTEGER;

ALTER TABLE service_logs
    ADD COLUMN IF NOT EXISTS order_ref_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_installed_base_order_ref_id
    ON installed_base (order_ref_id);

CREATE INDEX IF NOT EXISTS idx_service_logs_order_ref_id
    ON service_logs (order_ref_id);
