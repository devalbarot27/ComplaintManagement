-- Login lockout support columns on user_master.
-- Also auto-applied at runtime by login_ensure_lockout_columns().
ALTER TABLE user_master ADD COLUMN IF NOT EXISTS failed_login_attempts INTEGER NOT NULL DEFAULT 0;
ALTER TABLE user_master ADD COLUMN IF NOT EXISTS locked_until TIMESTAMP NULL;
ALTER TABLE user_master ADD COLUMN IF NOT EXISTS account_unlocked_at TIMESTAMP NULL;
ALTER TABLE user_master ADD COLUMN IF NOT EXISTS unlock_email_due_at TIMESTAMP NULL;
ALTER TABLE user_master ADD COLUMN IF NOT EXISTS unlock_email_sent_at TIMESTAMP NULL;
