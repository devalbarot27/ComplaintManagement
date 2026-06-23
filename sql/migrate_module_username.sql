ALTER TABLE installed_base
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE service_logs
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);

ALTER TABLE spare_parts_consumption
    ADD COLUMN IF NOT EXISTS username VARCHAR(100);
