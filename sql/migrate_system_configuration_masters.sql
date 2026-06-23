CREATE TABLE IF NOT EXISTS industry_segments (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_industry_segments_name
    ON industry_segments (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_industry_segments_deleted_at
    ON industry_segments (deleted_at);

CREATE TABLE IF NOT EXISTS warranty_chargeable_types (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_warranty_chargeable_types_name
    ON warranty_chargeable_types (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_warranty_chargeable_types_deleted_at
    ON warranty_chargeable_types (deleted_at);

CREATE TABLE IF NOT EXISTS part_replaced_masters (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_part_replaced_masters_name
    ON part_replaced_masters (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_part_replaced_masters_deleted_at
    ON part_replaced_masters (deleted_at);

CREATE TABLE IF NOT EXISTS customer_feedback_options (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_customer_feedback_options_name
    ON customer_feedback_options (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_customer_feedback_options_deleted_at
    ON customer_feedback_options (deleted_at);

CREATE TABLE IF NOT EXISTS reason_masters (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_by VARCHAR(100),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_reason_masters_name
    ON reason_masters (LOWER(TRIM(name)))
    WHERE deleted_at IS NULL;

CREATE INDEX IF NOT EXISTS idx_reason_masters_deleted_at
    ON reason_masters (deleted_at);

INSERT INTO industry_segments (name, status, created_by)
SELECT v.name, 'active', 'system'
FROM (VALUES
    ('Manufacturing'),
    ('Textiles'),
    ('Automotive'),
    ('Pharmaceuticals'),
    ('FMCG'),
    ('Engineering'),
    ('Construction'),
    ('Agriculture'),
    ('Food Processing'),
    ('Healthcare'),
    ('Others')
) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM industry_segments s
    WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(v.name))
      AND s.deleted_at IS NULL
);

INSERT INTO warranty_chargeable_types (name, status, created_by)
SELECT v.name, 'active', 'system'
FROM (VALUES ('Warranty'), ('Chargeable')) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM warranty_chargeable_types s
    WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(v.name))
      AND s.deleted_at IS NULL
);

INSERT INTO part_replaced_masters (name, status, created_by)
SELECT v.name, 'active', 'system'
FROM (VALUES ('Yes'), ('No')) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM part_replaced_masters s
    WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(v.name))
      AND s.deleted_at IS NULL
);

INSERT INTO customer_feedback_options (name, status, created_by)
SELECT v.name, 'active', 'system'
FROM (VALUES ('Excellent'), ('Good'), ('Average'), ('Poor')) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM customer_feedback_options s
    WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(v.name))
      AND s.deleted_at IS NULL
);

INSERT INTO reason_masters (name, status, created_by)
SELECT v.name, 'active', 'system'
FROM (VALUES ('PM'), ('Breakdown')) AS v(name)
WHERE NOT EXISTS (
    SELECT 1 FROM reason_masters s
    WHERE LOWER(TRIM(s.name)) = LOWER(TRIM(v.name))
      AND s.deleted_at IS NULL
);
