-- Add customer_code to user_master (complaint_management)
ALTER TABLE user_master
    ADD COLUMN IF NOT EXISTS customer_code VARCHAR(20);

CREATE UNIQUE INDEX IF NOT EXISTS user_master_customer_code_active_uidx
    ON user_master (LOWER(TRIM(customer_code)))
    WHERE deleted_at IS NULL
      AND customer_code IS NOT NULL
      AND TRIM(customer_code) <> '';
