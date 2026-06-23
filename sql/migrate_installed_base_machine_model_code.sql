ALTER TABLE installed_base
    ADD COLUMN IF NOT EXISTS machine_model_code VARCHAR(50);
