CREATE TABLE IF NOT EXISTS service_logs (
    id SERIAL PRIMARY KEY,
    installed_base_id INTEGER NOT NULL,
    order_id VARCHAR(50) NOT NULL,
    serial_number VARCHAR(50),
    machine_model VARCHAR(150),
    warranty_chargeable VARCHAR(50) NOT NULL,
    complaint_date DATE NOT NULL,
    issue_description TEXT NOT NULL,
    engineer_name VARCHAR(150) NOT NULL,
    visit_date DATE NOT NULL,
    action_taken TEXT NOT NULL,
    closure_date DATE NOT NULL,
    part_replaced VARCHAR(10) NOT NULL,
    running_hours NUMERIC(10, 2),
    loaded_hours NUMERIC(10, 2),
    customer_feedback VARCHAR(100),
    remarks TEXT,
    created_by INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_service_logs_deleted_at
    ON service_logs (deleted_at);

CREATE INDEX IF NOT EXISTS idx_service_logs_installed_base_id
    ON service_logs (installed_base_id);
