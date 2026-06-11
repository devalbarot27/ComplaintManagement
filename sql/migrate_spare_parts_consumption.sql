CREATE TABLE IF NOT EXISTS spare_parts_consumption (
    id SERIAL PRIMARY KEY,
    installed_base_id INTEGER NOT NULL,
    service_log_id INTEGER NULL,
    serial_number VARCHAR(50) NOT NULL,
    consumption_date DATE NOT NULL,
    warranty_chargeable VARCHAR(50) NOT NULL,
    spare_kit_number VARCHAR(100) NOT NULL,
    quantity NUMERIC(10, 2) NOT NULL,
    order_value NUMERIC(12, 2) NOT NULL,
    reason VARCHAR(50) NOT NULL,
    running_hours NUMERIC(10, 2),
    remarks TEXT,
    created_by INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_spare_parts_deleted_at
    ON spare_parts_consumption (deleted_at);

CREATE INDEX IF NOT EXISTS idx_spare_parts_installed_base_id
    ON spare_parts_consumption (installed_base_id);

CREATE INDEX IF NOT EXISTS idx_spare_parts_service_log_id
    ON spare_parts_consumption (service_log_id);
