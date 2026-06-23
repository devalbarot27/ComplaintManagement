-- Migrate complaint statuses to the new 5-status workflow
-- Run once on existing data after deploying the status update

-- Old Resolved (status 3) -> New Resolved (status 5)
UPDATE complaints c
SET status = 5
WHERE c.status = 3
  AND c.deleted_at IS NULL
  AND EXISTS (
      SELECT 1
      FROM complaint_closures cc
      WHERE cc.complaint_id = c.id
        AND cc.call_closure::text = 'Yes'
  );

-- Complaints with service update still In Progress -> Pending With HO (status 3)
UPDATE complaints c
SET status = 3
WHERE c.status = 2
  AND c.deleted_at IS NULL
  AND EXISTS (
      SELECT 1
      FROM complaint_service_updates csu
      WHERE csu.complaint_id = c.id
  )
  AND NOT EXISTS (
      SELECT 1
      FROM complaint_closures cc
      WHERE cc.complaint_id = c.id
        AND cc.call_closure::text = 'Yes'
  );

-- Closure No complaints -> Re-Open (status 4)
UPDATE complaints c
SET status = 4
WHERE c.deleted_at IS NULL
  AND EXISTS (
      SELECT 1
      FROM complaint_closures cc
      WHERE cc.complaint_id = c.id
        AND cc.call_closure::text = 'No'
  )
  AND NOT EXISTS (
      SELECT 1
      FROM complaint_closures cc2
      WHERE cc2.complaint_id = c.id
        AND cc2.call_closure::text = 'Yes'
  );
