-- Split installed_base.address into structured address fields
ALTER TABLE installed_base
    ADD COLUMN IF NOT EXISTS street_1 VARCHAR(255),
    ADD COLUMN IF NOT EXISTS street_2 VARCHAR(255),
    ADD COLUMN IF NOT EXISTS pincode VARCHAR(20),
    ADD COLUMN IF NOT EXISTS city VARCHAR(100),
    ADD COLUMN IF NOT EXISTS district VARCHAR(100),
    ADD COLUMN IF NOT EXISTS state VARCHAR(100);

-- Preserve existing address text in Street 1
UPDATE installed_base
SET street_1 = address
WHERE address IS NOT NULL
  AND TRIM(address) <> ''
  AND (street_1 IS NULL OR TRIM(street_1) = '');
