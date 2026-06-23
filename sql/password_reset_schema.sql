-- Password reset and history tables (complaint_management / obconn)

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id              SERIAL PRIMARY KEY,
    usr_name        VARCHAR(100) NOT NULL,
    token_hash      VARCHAR(64) NOT NULL,
    expires_at      TIMESTAMP NOT NULL,
    used_at         TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_reset_tokens_hash
    ON password_reset_tokens (token_hash);

CREATE TABLE IF NOT EXISTS password_history (
    id              SERIAL PRIMARY KEY,
    user_id         INT,
    username        VARCHAR(100) NOT NULL,
    password        VARCHAR(64) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_history_username
    ON password_history (username);
