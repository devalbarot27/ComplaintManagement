CREATE TABLE IF NOT EXISTS complaint_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_complaint_categories_name
    ON complaint_categories (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_complaint_categories_deleted_at
    ON complaint_categories (deleted_at);

CREATE INDEX IF NOT EXISTS idx_complaint_categories_status
    ON complaint_categories (status);
