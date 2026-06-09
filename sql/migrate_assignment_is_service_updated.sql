-- Service-update flag per assignment
ALTER TABLE complaint_assignments
    ADD COLUMN IF NOT EXISTS is_service_updated INTEGER NOT NULL DEFAULT 0;

-- Backfill: assignments that already have a service update
UPDATE complaint_assignments ca
SET is_service_updated = 1
WHERE EXISTS (
    SELECT 1
    FROM complaint_service_updates csu
    WHERE csu.assignment_id = ca.id
);
