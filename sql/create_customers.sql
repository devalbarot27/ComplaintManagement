-- Customers master (complaint_management)
CREATE TABLE IF NOT EXISTS customers (
    id SERIAL PRIMARY KEY,
    cust_code VARCHAR(50) NOT NULL,
    cust_name VARCHAR(255) NOT NULL,
    cust_addr VARCHAR(50) NOT NULL,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITHOUT TIME ZONE,
    deleted_at TIMESTAMP WITHOUT TIME ZONE
);

CREATE UNIQUE INDEX IF NOT EXISTS customers_cust_code_active_uidx
    ON customers (LOWER(TRIM(cust_code)))
    WHERE deleted_at IS NULL;

CREATE UNIQUE INDEX IF NOT EXISTS customers_cust_name_active_uidx
    ON customers (LOWER(TRIM(cust_name)))
    WHERE deleted_at IS NULL;
