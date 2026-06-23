-- Role & Permission Management schema

CREATE TABLE IF NOT EXISTS roles (
    id              SERIAL PRIMARY KEY,
    role_name       VARCHAR(100) NOT NULL,
    description     TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by      VARCHAR(100),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP,
    deleted_at      TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_roles_role_name
    ON roles (LOWER(TRIM(role_name)))
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS modules (
    id              SERIAL PRIMARY KEY,
    module_name     VARCHAR(100) NOT NULL,
    module_slug     VARCHAR(100) NOT NULL,
    description     TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by      VARCHAR(100),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP,
    deleted_at      TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_modules_module_slug
    ON modules (LOWER(TRIM(module_slug)))
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS permissions (
    id              SERIAL PRIMARY KEY,
    module_id       INT NOT NULL REFERENCES modules(id),
    permission_name VARCHAR(100) NOT NULL,
    permission_slug VARCHAR(100) NOT NULL,
    description     TEXT,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by      VARCHAR(100),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP,
    deleted_at      TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_permissions_module_slug
    ON permissions (module_id, LOWER(TRIM(permission_slug)))
    WHERE deleted_at IS NULL;

CREATE TABLE IF NOT EXISTS role_permissions (
    id              SERIAL PRIMARY KEY,
    role_id         INT NOT NULL REFERENCES roles(id),
    permission_id   INT NOT NULL REFERENCES permissions(id),
    created_by      VARCHAR(100),
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at      TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS uq_role_permissions
    ON role_permissions (role_id, permission_id)
    WHERE deleted_at IS NULL;
