ALTER TABLE spare_parts_consumption
    ADD COLUMN IF NOT EXISTS fab_number VARCHAR(50);

UPDATE spare_parts_consumption sp
SET fab_number = ib.fab_number
FROM installed_base ib
WHERE sp.installed_base_id = ib.id
  AND ib.fab_number IS NOT NULL
  AND TRIM(ib.fab_number) <> ''
  AND (sp.fab_number IS NULL OR TRIM(sp.fab_number) = '');
