-- products table structure
-- PostgreSQL DDL matching the current ComplaintManagement products schema

CREATE SEQUENCE IF NOT EXISTS products_id_seq;

CREATE TABLE IF NOT EXISTS products (
    id                  INTEGER       NOT NULL DEFAULT nextval('products_id_seq'::regclass),
    dpst                VARCHAR(20)   NULL,
    product_group       VARCHAR(50)   NULL,
    tplcode             VARCHAR(20)   NULL,
    tpldesc             VARCHAR(60)   NULL,
    dealer_price        VARCHAR(20)   NULL DEFAULT '0',
    tod_flag            VARCHAR(1)    NULL DEFAULT 'N',
    excisable           VARCHAR(1)    NULL DEFAULT '1',
    mc                  NUMERIC       NULL DEFAULT 0,
    vc                  NUMERIC       NULL DEFAULT 0,
    fc                  NUMERIC       NULL DEFAULT 0,
    cos                 NUMERIC       NULL,
    valid               VARCHAR(1)    NULL,
    warehouse           VARCHAR(20)   NULL,
    otcode              CHAR(3)       NULL,
    company             VARCHAR(50)   NULL,
    payment_term        VARCHAR(100)  NULL,
    status              CHAR(3)       NULL,
    created_by          INTEGER       NULL,
    updated_by          INTEGER       NULL,
    created_at          TIMESTAMP     NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP     NULL,
    deleted_at          TIMESTAMP     NULL,
    CONSTRAINT products_pkey PRIMARY KEY (id)
);

ALTER SEQUENCE products_id_seq OWNED BY products.id;

-- Unique active TPL Code (soft-delete aware)
CREATE UNIQUE INDEX IF NOT EXISTS uq_products_tplcode_active
    ON products (lower(TRIM(BOTH FROM tplcode)))
    WHERE deleted_at IS NULL
      AND tplcode IS NOT NULL
      AND TRIM(BOTH FROM tplcode) <> '';
