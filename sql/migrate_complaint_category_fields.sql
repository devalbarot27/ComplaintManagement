ALTER TABLE complaints
    ADD COLUMN IF NOT EXISTS complaint_category_id INTEGER,
    ADD COLUMN IF NOT EXISTS complaint_category_name VARCHAR(100);

CREATE INDEX IF NOT EXISTS idx_complaints_complaint_category_id
    ON complaints (complaint_category_id);
