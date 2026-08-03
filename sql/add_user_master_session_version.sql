-- Invalidate active sessions when critical user fields change.
-- Also auto-applied at runtime by login_ensure_session_version_column().
ALTER TABLE user_master
ADD COLUMN IF NOT EXISTS session_version INTEGER NOT NULL DEFAULT 1;
