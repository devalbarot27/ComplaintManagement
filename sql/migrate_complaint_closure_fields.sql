ALTER TABLE complaint_closures
    ADD COLUMN IF NOT EXISTS closure_datetime TIMESTAMP;

ALTER TABLE complaint_closures
    ADD COLUMN IF NOT EXISTS customer_feedback VARCHAR(100);
