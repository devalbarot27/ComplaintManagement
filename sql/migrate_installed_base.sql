CREATE TABLE IF NOT EXISTS installed_base (
    id SERIAL PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    fab_number VARCHAR(20),
    customer_name VARCHAR(200) NOT NULL,
    address TEXT,
    mobile VARCHAR(20),
    email VARCHAR(150),
    dealer_name VARCHAR(200),
    machine_model VARCHAR(150),
    invoice_date DATE,
    commissioning_date DATE,
    running_hours NUMERIC(10, 2),
    industry_segment VARCHAR(100),
    remarks TEXT,
    created_by INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_installed_base_deleted_at
    ON installed_base (deleted_at);

CREATE INDEX IF NOT EXISTS idx_installed_base_order_id
    ON installed_base (order_id);
