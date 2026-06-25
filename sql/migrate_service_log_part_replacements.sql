CREATE TABLE IF NOT EXISTS service_log_part_replacements (
    id SERIAL PRIMARY KEY,
    service_log_id INTEGER NOT NULL,
    machine_model_code VARCHAR(50) NOT NULL,
    machine_model VARCHAR(150) NOT NULL,
    running_hours NUMERIC(10, 2) NOT NULL,
    loaded_hours NUMERIC(10, 2) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_service_log_part_replacements_service_log_id
    ON service_log_part_replacements (service_log_id);

CREATE INDEX IF NOT EXISTS idx_service_log_part_replacements_deleted_at
    ON service_log_part_replacements (deleted_at);
