-- ============================================================================
-- POST-MIGRATION VERIFICATION — run AFTER proposed_geo_migration_*.sql
-- Read-only. Every block should return the exact expected counts listed.
-- ============================================================================

USE monetix;

-- (1) New geo columns populated exactly as expected (students)
--     present div 12, present dist 11, present upz 11
--     perm    div 12, perm    dist 11, perm    upz 10
--     (student 305 has division only; one student lacks permanent upazila)
SELECT
  SUM(present_admin_1_id  IS NOT NULL) AS present_level1,
  SUM(present_admin_2_id  IS NOT NULL) AS present_level2,
  SUM(present_admin_3_id  IS NOT NULL) AS present_level3,
  SUM(permanent_admin_1_id IS NOT NULL) AS permanent_level1,
  SUM(permanent_admin_2_id IS NOT NULL) AS permanent_level2,
  SUM(permanent_admin_3_id IS NOT NULL) AS permanent_level3,
  SUM(present_country_id = 1)  AS present_country_bd,
  SUM(permanent_country_id = 1) AS permanent_country_bd
FROM students;

-- (2) New geo columns populated for institutes (3 of 3)
SELECT COUNT(*) AS institutes_migrated
FROM institutes
WHERE country_id = 1
  AND admin_level_1_id IS NOT NULL
  AND admin_level_2_id IS NOT NULL
  AND admin_level_3_id IS NOT NULL;

-- (3) No orphans in new FK columns:
--     every new level-1/2/3 id must point to an active BD unit of the right level
SELECT 'student_bad_country' AS chk, COUNT(*) AS n FROM students s
 WHERE s.present_country_id IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM countries c WHERE c.id = s.present_country_id)
UNION ALL
SELECT 'student_bad_l1', COUNT(*) FROM students s
 LEFT JOIN administrative_units u ON u.id = s.present_admin_1_id
 WHERE s.present_admin_1_id IS NOT NULL
   AND (u.id IS NULL OR u.country_id <> 1 OR u.administrative_level_id <> 1)
UNION ALL
SELECT 'student_bad_l2', COUNT(*) FROM students s
 LEFT JOIN administrative_units u ON u.id = s.present_admin_2_id
 WHERE s.present_admin_2_id IS NOT NULL
   AND (u.id IS NULL OR u.country_id <> 1 OR u.administrative_level_id <> 2)
UNION ALL
SELECT 'student_bad_l3', COUNT(*) FROM students s
 LEFT JOIN administrative_units u ON u.id = s.present_admin_3_id
 WHERE s.present_admin_3_id IS NOT NULL
   AND (u.id IS NULL OR u.country_id <> 1 OR u.administrative_level_id <> 3);

-- (4) Hierarchy consistency per student:
--     level2.parent_id must equal level1 id; level3.parent_id must equal level2 id
--     Expected: 0 rows
SELECT 'student_broken_l2link' AS chk, COUNT(*) AS n FROM students s
 JOIN administrative_units l1 ON l1.id = s.present_admin_1_id
 JOIN administrative_units l2 ON l2.id = s.present_admin_2_id
 WHERE s.present_admin_2_id IS NOT NULL AND l2.parent_id <> l1.id
UNION ALL
SELECT 'student_broken_l3link', COUNT(*) FROM students s
 JOIN administrative_units l2 ON l2.id = s.present_admin_2_id
 JOIN administrative_units l3 ON l3.id = s.present_admin_3_id
 WHERE s.present_admin_3_id IS NOT NULL AND l3.parent_id <> l2.id;

-- (5) Institute hierarchy consistency. Expected: 0 rows
SELECT 'institute_broken_l2link' AS chk, COUNT(*) AS n FROM institutes i
 JOIN administrative_units l1 ON l1.id = i.admin_level_1_id
 JOIN administrative_units l2 ON l2.id = i.admin_level_2_id
 WHERE l2.parent_id <> l1.id
UNION ALL
SELECT 'institute_broken_l3link', COUNT(*) FROM institutes i
 JOIN administrative_units l2 ON l2.id = i.admin_level_2_id
 JOIN administrative_units l3 ON l3.id = i.admin_level_3_id
 WHERE l3.parent_id <> l2.id;

-- (6) Round-trip: new ids must map back (via code) to the same legacy BdGeo
--     keys that were stored. Expected: 0 mismatches.
SELECT 'student_roundtrip_present_l1' AS chk, COUNT(*) AS n FROM students s
 JOIN administrative_units u ON u.id = s.present_admin_1_id
 WHERE s.present_division_id <> ''
   AND u.code <> CONCAT('BD.D', s.present_division_id)
UNION ALL
SELECT 'student_roundtrip_present_l2', COUNT(*) FROM students s
 JOIN administrative_units u ON u.id = s.present_admin_2_id
 WHERE s.present_district_id <> ''
   AND u.code <> CONCAT('BD.T', s.present_district_id)
UNION ALL
SELECT 'student_roundtrip_present_l3', COUNT(*) FROM students s
 JOIN administrative_units u ON u.id = s.present_admin_3_id
 WHERE s.present_upazila_id <> ''
   AND u.code <> CONCAT('BD.U', s.present_upazila_id);

-- (7) Legacy columns untouched + total row counts unchanged.
--     Expected: students=304, institutes=3, units=566
SELECT 'legacy_division_still_present' AS chk, COUNT(*) FROM students WHERE present_division_id <> ''
UNION ALL SELECT 'legacy_district_still_present', COUNT(*) FROM students WHERE present_district_id <> ''
UNION ALL SELECT 'legacy_upazila_still_present', COUNT(*) FROM students WHERE present_upazila_id <> ''
UNION ALL SELECT 'geo_units_total', COUNT(*) FROM administrative_units
UNION ALL SELECT 'students_total', COUNT(*) FROM students
UNION ALL SELECT 'institutes_total', COUNT(*) FROM institutes;

-- (8) Sample of every migrated row with both legacy names and new names
--     to eyeball correctness (students with legacy present geo).
SELECT s.id AS student_id, s.first_name, s.last_name,
       s.present_division_id AS legacy_div, d.name AS l1_name, d.code AS l1_code,
       s.present_district_id AS legacy_dist, t.name AS l2_name, t.code AS l2_code,
       s.present_upazila_id  AS legacy_upz,  u.name AS l3_name, u.code AS l3_code
FROM students s
LEFT JOIN administrative_units d ON d.id = s.present_admin_1_id
LEFT JOIN administrative_units t ON t.id = s.present_admin_2_id
LEFT JOIN administrative_units u ON u.id = s.present_admin_3_id
WHERE s.present_admin_1_id IS NOT NULL
ORDER BY s.id;

-- (9) Sample of migrated institutes.
SELECT i.id, i.name,
       d.name AS l1_name, d.code AS l1_code,
       t.name AS l2_name, t.code AS l2_code,
       u.name AS l3_name, u.code AS l3_code
FROM institutes i
LEFT JOIN administrative_units d ON d.id = i.admin_level_1_id
LEFT JOIN administrative_units t ON t.id = i.admin_level_2_id
LEFT JOIN administrative_units u ON u.id = i.admin_level_3_id
ORDER BY i.id;

-- ============================================================================
-- END VERIFICATION
-- ============================================================================