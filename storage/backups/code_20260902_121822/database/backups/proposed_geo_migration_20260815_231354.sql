-- ============================================================================
-- PROPOSED MIGRATION — Backfill new global-address FK columns from legacy BD
-- Bangladesh geographic data.  REVIEW ONLY.  NOT FOR EXECUTION.
--
--   Created : 2026-08-15 (session audit)
--   DB      : monetix (dev)
--   Backup  : database/backups/geo_bd_preservation_20260815_231354.sql
--             database/backups/bd_address_business_rows_20260815_231354.sql
--
-- OBJECTIVE
--   Map the preserved Bangladesh hierarchy into the new global schema so that:
--     Country (BD, id=1) -> Level 1 (Division) -> Level 2 (District)
--     -> Level 3 (Upazila) -> EXISTING Bangladesh location data
--
--   This script only backfills NEW nullable FK columns that are currently all
--   NULL.  It never deletes, drops, renames, or rewrites legacy columns.  The
--   legacy division/district/upazila fields remain untouched for
--   backwards compatibility (show page + edit modal still use them).
--
-- LEGACY KEY -> NEW UNIT ID MAPPING (via administrative_units.code)
--   students.present/permanent_division_id  {k}  -> code 'BD.D{k}'   (Level 1)
--   students.present/permanent_district_id  {k}  -> code 'BD.T{k}'   (Level 2)
--   students.present/permanent_upazila_id   {k}  -> code 'BD.U{k}'   (Level 3)
--
--   institutes.division/district/upazila are free-text NAMES (English or
--   Bengali).  Resolved to verified unit ids below (all hierarchy-checked).
--
-- EXPECTED AFFECTED ROWS (verified pre-flight, before execution):
--   students  with present geo   : 11 of 12 (12th has division only)
--   students  with permanent geo : 11      (1 has no permanent upazila)
--   institutes                    : 3
--   administrative_units          : 566 (UNTOUCHED)
-- ============================================================================

-- ---------------------------------------------------------------------------
-- SAFETY CHECK 1 : confirm we are on the intended database
-- ---------------------------------------------------------------------------
USE monetix;

-- ---------------------------------------------------------------------------
-- SAFETY CHECK 2 : nothing has been migrated yet
-- ---------------------------------------------------------------------------
SELECT 'students_already_migrated' AS chk, COUNT(*) AS n
FROM students WHERE present_admin_1_id IS NOT NULL OR present_country_id IS NOT NULL
UNION ALL
SELECT 'institutes_already_migrated', COUNT(*) FROM institutes
WHERE admin_level_1_id IS NOT NULL OR country_id IS NOT NULL;

-- ---------------------------------------------------------------------------
-- STEP 1 : students -- set country = BD (id 1)
-- (only the 12 rows that carry legacy BD keys; country_id stays NULL elsewhere)
-- ---------------------------------------------------------------------------
UPDATE students
   SET present_country_id   = 1,
       permanent_country_id = 1
 WHERE (present_division_id IS NOT NULL AND present_division_id <> '')
    OR (permanent_division_id IS NOT NULL AND permanent_division_id <> '');

-- ---------------------------------------------------------------------------
-- STEP 2 : students -- present address, Level 1 (division)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.D', s.present_division_id)
   SET s.present_admin_1_id = u.id
 WHERE s.present_division_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 3 : students -- present address, Level 2 (district)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.T', s.present_district_id)
   SET s.present_admin_2_id = u.id
 WHERE s.present_district_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 4 : students -- present address, Level 3 (upazila)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.U', s.present_upazila_id)
   SET s.present_admin_3_id = u.id
 WHERE s.present_upazila_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 5 : students -- permanent address, Level 1 (division)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.D', s.permanent_division_id)
   SET s.permanent_admin_1_id = u.id
 WHERE s.permanent_division_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 6 : students -- permanent address, Level 2 (district)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.T', s.permanent_district_id)
   SET s.permanent_admin_2_id = u.id
 WHERE s.permanent_district_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 7 : students -- permanent address, Level 3 (upazila)
-- ---------------------------------------------------------------------------
UPDATE students s
  JOIN administrative_units u ON u.code = CONCAT('BD.U', s.permanent_upazila_id)
   SET s.permanent_admin_3_id = u.id
 WHERE s.permanent_upazila_id <> '';

-- ---------------------------------------------------------------------------
-- STEP 8 : institutes -- set country + mapped level ids (verified per-row)
--
--   MAWA ACADEMY (id=2)   : Dhaka(6)  -> Faridpur(60) -> Bhanga(467)
--   Halumoni (id=4)       : Dhaka(6)  -> Faridpur(60) -> Nagarkanda(466)
--                          (legacy values stored in Bengali: ঢাকা/ফরিদপুর/নগরকান্দা)
--   Tutu Center (id=5)    : Khulna(3) -> Khulna(35)   -> Koyra(286)
-- ---------------------------------------------------------------------------
UPDATE institutes
   SET country_id      = 1,
       admin_level_1_id = 6,
       admin_level_2_id = 60,
       admin_level_3_id = 467
 WHERE id = 2;   -- MAWA ACADEMY

UPDATE institutes
   SET country_id      = 1,
       admin_level_1_id = 6,
       admin_level_2_id = 60,
       admin_level_3_id = 466
 WHERE id = 4;   -- Halumoni (Nagarkanda)

UPDATE institutes
   SET country_id      = 1,
       admin_level_1_id = 3,
       admin_level_2_id = 35,
       admin_level_3_id = 286
 WHERE id = 5;   -- Tutu Center

-- ============================================================================
-- SCRIPT END  (verification queries are in verify_geo_migration.sql)
-- ============================================================================