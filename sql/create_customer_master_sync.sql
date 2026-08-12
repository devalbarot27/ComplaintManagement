-- Customer Master Sync (complaint_management / ob database)
CREATE TABLE IF NOT EXISTS customer_master_sync (
    id SERIAL PRIMARY KEY,
    customer_code VARCHAR(9) NOT NULL,
    added_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE
);

CREATE UNIQUE INDEX IF NOT EXISTS customer_master_sync_code_active_uidx
    ON customer_master_sync (LOWER(TRIM(customer_code)))
    WHERE deleted_at IS NULL;
