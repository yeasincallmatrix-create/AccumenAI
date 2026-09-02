-- ============================================================================
-- CORRECT the remaining upazila (L3) mismatches. The stored DISTRICE didn't
-- match the upazila's real parent district (and division followed).
--
--   Fix rule: for a student's chosen upazila, rebuild the chain so that
--     upazila = kept,  district := real parent of upazila,
--     division := real parent of that district.
--   Both new (admin_*_id) and legacy (division/district_id) columns updated.
--
--   Affects only the 3 students whose upazila's real district != stored:
--     205 Gita (present Tarash->Sirajganj->Rajshahi; permanent Dumuria->
--            Khulna district->Khulna division)
--     221 Faria (present+permanent Lalmai->Comilla->Chattagram)
--     257 Fahim (present+permanent Sadarsouth->Comilla->Chattagram)
--
--   Backup: database/backups/pre_upazilafix_students_20260816_011129.sql
--   Idempotent: WHERE only matches rows whose upazila real-district differs.
-- ============================================================================

USE monetix;

-- Present address
UPDATE students s
  JOIN administrative_units u ON u.id = s.present_admin_3_id     -- upazila (trust)
  JOIN administrative_units t ON t.id = u.parent_id              -- real district
  JOIN administrative_units d ON d.id = t.parent_id              -- real division
   SET s.present_admin_2_id = t.id,
       s.present_admin_1_id = d.id,
       s.present_district_id = SUBSTRING(t.code, 5),
       s.present_division_id = SUBSTRING(d.code, 5)
 WHERE s.present_admin_3_id IS NOT NULL
   AND u.parent_id <> s.present_admin_2_id;

-- Permanent address
UPDATE students s
  JOIN administrative_units u ON u.id = s.permanent_admin_3_id
  JOIN administrative_units t ON t.id = u.parent_id
  JOIN administrative_units d ON d.id = t.parent_id
   SET s.permanent_admin_2_id = t.id,
       s.permanent_admin_1_id = d.id,
       s.permanent_district_id = SUBSTRING(t.code, 5),
       s.permanent_division_id = SUBSTRING(d.code, 5)
 WHERE s.permanent_admin_3_id IS NOT NULL
   AND u.parent_id <> s.permanent_admin_2_id;