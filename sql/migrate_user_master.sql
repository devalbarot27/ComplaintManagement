CREATE TABLE IF NOT EXISTS user_master (
    id SERIAL PRIMARY KEY,
    role SMALLINT NOT NULL,
    username VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(64) NOT NULL,
    mobile_number VARCHAR(20),
    last_login_at TIMESTAMP NULL,
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_user_master_username_active
    ON user_master (LOWER(TRIM(username)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_user_master_deleted_at
    ON user_master (deleted_at);

CREATE INDEX IF NOT EXISTS idx_user_master_role
    ON user_master (role);
