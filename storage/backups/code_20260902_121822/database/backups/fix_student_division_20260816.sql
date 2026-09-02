-- ============================================================================
-- CORRECT the 7 students whose stored DIVISION does not match the district's
-- real division (legacy data anomaly found post-migration).
--
--   Fix rule: division := real parent of the student's district.
--     new columns    present_admin_1_id / permanent_admin_1_id  -> unit id
--     legacy columns present_division_id / permanent_division_id -> BdGeo key
--     (division unit id == BdGeo division key for BD: BD.D{k} = id k)
--
--   Backup of affected rows (ids 18,34,94,205,221,243,257) was taken before
--   this script: database/backups/pre_divfix_students_20260816_010655.sql
--
--   Idempotent: for already-correct students the SET reassigns the same value.
-- ============================================================================

USE monetix;

-- Present address
UPDATE students s
  JOIN administrative_units t ON t.id = s.present_admin_2_id   -- district
  JOIN administrative_units d ON d.id = t.parent_id            -- its real division
   SET s.present_admin_1_id = d.id,
       s.present_division_id = SUBSTRING(d.code, 5)
 WHERE s.present_admin_2_id IS NOT NULL;

-- Permanent address
UPDATE students s
  JOIN administrative_units t ON t.id = s.permanent_admin_2_id -- district
  JOIN administrative_units d ON d.id = t.parent_id            -- its real division
   SET s.permanent_admin_1_id = d.id,
       s.permanent_division_id = SUBSTRING(d.code, 5)
 WHERE s.permanent_admin_2_id IS NOT NULL;

-- ================================================================
-- Sanity: the only students whose division CHANGED must be the 7.
-- Expected: 7 rows (present) and 7 rows (permanent) with member=1 below.
-- ================================================================
SELECT 'present_changed' AS chk, COUNT(*) AS n FROM students
 WHERE present_admin_2_id IS NOT NULL AND present_division_id <> ''
UNION ALL SELECT 'permanent_changed', COUNT(*) FROM students
 WHERE permanent_admin_2_id IS NOT NULL AND permanent_division_id <> '';