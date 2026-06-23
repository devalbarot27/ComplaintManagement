ALTER TABLE complaints
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE complaint_assignments
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE complaint_activity_logs
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE complaint_closures
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE complaint_service_updates
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);
