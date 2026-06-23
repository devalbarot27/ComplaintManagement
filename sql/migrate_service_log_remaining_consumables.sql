ALTER TABLE service_logs
    ADD COLUMN IF NOT EXISTS separator_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS separator_remaining_hours NUMERIC(10, 2),
    ADD COLUMN IF NOT EXISTS air_filter_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS air_filter_remaining_hours NUMERIC(10, 2),
    ADD COLUMN IF NOT EXISTS oil_filter_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS oil_filter_remaining_hours NUMERIC(10, 2),
    ADD COLUMN IF NOT EXISTS oil_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS oil_remaining_hours NUMERIC(10, 2),
    ADD COLUMN IF NOT EXISTS valve_kit_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS valve_kit_remaining_hours NUMERIC(10, 2),
    ADD COLUMN IF NOT EXISTS grease_remaining_date DATE,
    ADD COLUMN IF NOT EXISTS grease_remaining_hours NUMERIC(10, 2);
