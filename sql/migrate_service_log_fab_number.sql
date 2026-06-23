ALTER TABLE service_logs
    ADD COLUMN IF NOT EXISTS fab_number VARCHAR(50);

UPDATE service_logs sl
SET fab_number = ib.fab_number
FROM installed_base ib
WHERE sl.installed_base_id = ib.id
  AND ib.fab_number IS NOT NULL
  AND TRIM(ib.fab_number) <> ''
  AND (sl.fab_number IS NULL OR TRIM(sl.fab_number) = '');
