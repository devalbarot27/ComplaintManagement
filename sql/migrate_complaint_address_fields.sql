-- Split customer_address into structured address fields
ALTER TABLE complaints
    ADD COLUMN IF NOT EXISTS street_1 VARCHAR(255),
    ADD COLUMN IF NOT EXISTS street_2 VARCHAR(255),
    ADD COLUMN IF NOT EXISTS pincode VARCHAR(20),
    ADD COLUMN IF NOT EXISTS city VARCHAR(100),
    ADD COLUMN IF NOT EXISTS district VARCHAR(100),
    ADD COLUMN IF NOT EXISTS state VARCHAR(100);

-- Preserve existing address text in Street 1
UPDATE complaints
SET street_1 = customer_address
WHERE customer_address IS NOT NULL
  AND TRIM(customer_address) <> ''
  AND (street_1 IS NULL OR TRIM(street_1) = '');
