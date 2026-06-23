CREATE TABLE IF NOT EXISTS password_history (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_password_history_username
    ON password_history (username);

CREATE INDEX IF NOT EXISTS idx_password_history_user_id
    ON password_history (user_id);

CREATE INDEX IF NOT EXISTS idx_password_history_username_created
    ON password_history (username, created_at DESC);
