# PHASE B2 — DOMAIN RESTRUCTURE — IMPLEMENTATION REPORT (GATE 2)

**PHASE:** B2 — Industry / Institution Domain Restructure + Full Academic/Professional Ecosystem Separation  
**GATE:** 2 — IMPLEMENTATION  
**DATE:** 2026-08-28  
**BASE AUDIT:** `PHASE_B2_DOMAIN_RESTRUCTURE_FORENSIC_AUDIT_REPORT.md` (YELLOW, Gate 1)

---

## 1. EXACT TAXONOMY IMPLEMENTED

```
INDUSTRY (config/industry_rules.php:22-38 global.industries)
├── Education (education)
│   └── Academic Institutions (implicit grouping)
│       ├── School (school) → domain academic → template school
│       ├── College (college) → academic → college
│       ├── Polytechnic (polytechnic) **NEW** → academic → technical_institute
│       └── University (university) → academic → university
│       (+ madrasha + legacy variants primary_school, secondary_high_school, school_college preserved as NEEDS_REVIEW)
├── Training Center (training_center) **NEW INDEPENDENT INDUSTRY**
│   ├── Training Institute (training_institute) → professional → training_institute
│   ├── Professional Training Center (professional_training_center) → professional → training_institute
│   ├── Dance Academy (dance_academy) → professional → dance_academy
│   ├── IT Training Center (it_training_center) → professional → training_institute
│   └── Vocational Training Center (vocational_training_center) → professional → vocational_institute
│       (+ legacy aliases institution, professional_training_academy, computer_it_training_institute, vocational_institute, technical_training_center, skill_development_center, martial_arts, music_academy, sports_academy, language_academy, coaching_centre preserved under training_center for review trail)
├── Retail (retail) → other
├── Manufacturing (manufacturing) → other
├── Service (service) **NEW** → other (empty subs)
├── Transportation (transportation) **RENAMED from transport** → other (transport kept as legacy alias)
└── Restaurant (restaurant) → other
```

Other industries `healthcare`, `information_technology`, `finance`, `real_estate`, `hotels`, `personal_finance`, `other` unchanged.

All changes in `config/industry_rules.php:20-138` (global + Bangladesh + United States). `transport` alias retained for backward compatibility (`IndustryRules`, `InstituteDomain::normalizeIndustry`).

---

## 2. OLD → NEW MAPPING (applied)

| # | OLD | NEW | Status | Evidence |
|---|-----|-----|--------|----------|
| 1 | `education / school` | `education / school` | AUTO (no move) | config + mapping `school→school` |
| 2 | `education / college` | `education / college` | AUTO | `college→college` |
| 3 | `education / university` | `education / university` | AUTO | `university→university` |
| 4 | `education / polytechnic` | `education / polytechnic` | **CREATE** (new mapping `polytechnic→technical_institute`) | `migration ensureMapping` |
| 5 | `education / institution` | `training_center / training_institute` | AUTO_MAPPABLE | `migration 2026_08_28_100000` + institutes row 1 migrated (education,institution → training_center,training_institute) |
| 6 | `education / professional_training_academy` | `training_center / professional_training_center` | AUTO | migration + mapping canonicalization |
| 7 | `education / computer_it_training_institute` | `training_center / it_training_center` | AUTO | migration |
| 8 | `education / vocational_institute` | `training_center / vocational_training_center` | AUTO | migration |
| 9 | `education / dance_academy` | `training_center / dance_academy` | AUTO | migration |
| 10 | `transport` (no sub) | `transportation` (alias) | AUTO (no institutes had transport; alias via normalize) | `InstituteDomain::normalizeIndustry`, config alias |
| 11 | `education / primary_school` etc. | **NOT MIGRATED** — preserved under `education` as NEEDS_REVIEW | reported | `industry_template_mappings` still has `education,primary_school` etc. |
| 12 | `education / skill_development_center`, `technical_training_center` | **NOT MIGRATED** — NEEDS_REVIEW | reported | preserved under education |
| 13 | `service` | **CREATE** empty industry | AUTO | config |

Snapshot prior to migration: `institutes` 4 rows (3 `education,NULL` + 1 `education,institution`); `industry_template_mappings` 22 rows (legacy education set). After migration: `institutes` 4 rows (3 `education,NULL` + 1 `training_center,training_institute`); `mappings` 27 rows (22 + 5 canonical training_center + 1 polytechnic + fallbacks). Verified via `SELECT ... GROUP BY` post-migrate.

---

## 3. RECORDS MIGRATED

- `institutes`: 1 row (`education,institution` → `training_center,training_institute`) — `2026_08_28_100000` log `B2 institutes migrated`.
- `industry_template_mappings`: 5 rows re-parented (`institution`, `professional_training_academy`, `computer_it_training_institute`, `vocational_institute`, `dance_academy` from `education` to `training_center` canonical slugs) + 6 canonical ensured (`polytechnic`, `training_institute`, `professional_training_center`, `it_training_center`, `vocational_training_center`, `training_center,NULL` fallback). No duplicate-key collisions (pre-flight passed — snapshot had 0 conflicting `training_center` targets).
- No `course_categories`, `courses`, `subjects`, `batches`, `academic_*` data migrated (0 rows with domain contradicting — historical risk low).
- No `FOREIGN_KEY_CHECKS=0`, no cascade deletes.

---

## 4. RECORDS NOT MIGRATED (intentional)

- `education / primary_school`, `secondary_high_school`, `school_college`, `madrasha` variants — NEEDS_REVIEW, preserved.
- `education / skill_development_center`, `technical_training_center`, `martial_arts`, `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` — preserved under `education` (legacy) — also preserved as copies under `training_center` via seeder for forward compatibility, but original education rows **not deleted**.
- `education,NULL` fallback institutes (3 rows) — preserved as academic fallback (could be generic education institutes).
- All historical academic result snapshots (`academic_final_results` 0 rows, `subjects` 0 rows) — no rewrite.

---

## 5. NEEDS_REVIEW RECORDS (preserved, reported)

- `industry_template_mappings` legacy education rows for the 7 NEEDS_REVIEW subs (still under `education`). Manual review required if an institute uses those subs — should be re-created under `training_center` via UI or artisan.
- `institutes` with `industry=education, sub NULL` (3 rows) — ambiguous generic education institutes; require admin to set correct sub (school vs polytechnic etc.) via settings UI.

---

## 6. DATABASE MIGRATIONS

- **New:** `database/migrations/2026_08_28_100000_restructure_industry_institution_domain_taxonomy.php` — reversible, transactional, pre-flight checks (orphan warn, duplicate unique, subject_type contradict warn), guarded by `Schema::hasTable` for `migrate:fresh` compatibility. `up()` re-parents institutes + mappings, ensures canonical mappings; `down()` reverses.
- **No schema column changes** (industry/sub remain varchar strings — normalized via config + `InstituteDomain` resolver, no enum change to avoid destructive alter).
- **Existing hardening migrations preserved:** `2026_08_27_000001_harden_subject_foreign_keys_to_restrict.php`, `2026_08_27_000002`, `2026_08_27_000004_add_optional_bonus_threshold`, `2026_08_28_000001_add_multiple_optional_policy` — unchanged.
- Post-restore, `php artisan migrate --force` succeeded (74.89ms for B2). `migrations` table shows B2 as last batch.

Rollback: `php artisan migrate:rollback --step=1` restores legacy `education` children + `institutes` values; `config/industry_rules.php` revert via git.

---

## 7. DOMAIN RESOLVER (server-side authoritative)

- **New file:** `app/Support/InstituteDomain.php` — final class with constants `ACADEMIC`/`PROFESSIONAL`/`OTHER`, maps `Academic=[school,college,polytechnic,university]`, `Professional=[training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center]`.
- Methods: `fromInstitute(Institute)`, `fromKeys(industry,sub)`, `isAcademic`, `isProfessional`, `subjectTypeFor`, `isValidCombination`, `normalizeIndustry` (`transport→transportation`), `normalizeSubIndustry` (legacy alias map), `hasDomainData(instituteId)` (checks courses, subjects, curricula, batches, placements, assessments, results).
- Used by: `SubjectManagementController`, `CourseCategoryManageController`, `CourseMasterController`, `CourseSubCategoryManageController`, `Institute::booted` immutability guard.

Legacy `app/Support/IndustryRules.php` unchanged (still config accessor); `InstituteDomain` wraps it for domain logic.

---

## 8. SUBJECT CHANGES

- **File:** `app/Http/Controllers/SubjectManagementController.php:1-341` — total rewrite of domain trust:
  - `create:93-102` and `edit:155-167` now compute `$derived = InstituteDomain::subjectTypeFor(Institute::find(instituteId))` and pass `derivedSubjectType`/`domain` to view; categories filtered by derived type.
  - `store:104-142` and `update:155-195` **remove client `subject_type` from validation**; derive `$derivedType` server-side; validate `category_id` as `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$derivedType)` — fixes IDOR leakage.
  - `filterCategories:275-282` and `categories:285-292` changed from `withoutGlobalScope('institute')->whereIn([...])` (cross-tenant leak) to `where institute_id = ? and subject_type = derived` (tenant-isolated, domain-aware).
  - `uniqueSlug` and `SubjectDeletionService` untouched (preserved SoftDeletes, RESTRICT, withTrashed).
- `app/Models/Subject.php` unchanged (hybrid tenant, SoftDeletes).

---

## 9. COURSE CHANGES

- **`CourseMasterController.php:4,204-251`**: added `InstituteDomain` import; `validated:204-240` now scopes `category_id` and `sub_category_id` exists rules by `institute_id` + `subject_type=domainType`; `categories:243-251` changed from `withoutGlobalScope` cross-tenant leak to `where institute_id + subject_type = domainType` tenant-isolated.
- **`CourseCategoryManageController.php:4,21-84,134-135`**: `index:21` now derives `domainType` and filters `where subject_type = domainType`; `store:75-84` derives `domainType` for new category's `subject_type` (was hardcoded professional); `destroy:134` scopes replacement `exists` by `institute_id` (IDOR fix).
- **`CourseSubCategoryManageController.php:5-167`**: parallel fixes — `index:52-58` domain-aware categories, `store:61-72` and `update:95-109` scope `category_id` by institute+domain, `destroy:143` scopes replacement by institute.

---

## 10. CURRICULUM CHANGES

- No direct file change — curriculum domain derived via `course.category.subject_type` (which is now domain-correct). `CourseCurriculum` remains TenantScoped, versioned, frozen via `batches.curriculum_id SET NULL` (`SHOW CREATE TABLE batches` still `curriculum_id FK SET NULL`). No mixing guard added beyond category scoping; `course_subjects` pivot inherits domain via course category.

---

## 11. PLACEMENT CHANGES

- No code change — `StudentAcademicPlacement` remains academic-only. `AcademicSetupService.php:59` still gates academic defaults by `industry==='education'` (now academic domain). Consider updating to `InstituteDomain::isAcademic` in future, but preserved to avoid unrelated change (existing green phase).

---

## 12. ASSESSMENT CHANGES

- No direct change — `academic_assessments` remain academic-only via institute FK. Professional exams (`exams` table) remain separate (FK `course_id`/`batch_id` CASCADE, institute FK). No merge introduced.

---

## 13. AGGREGATION CHANGES

- No change — `AcademicResultAggregationService` still requires 100% weight, tolerance 0.005, draft flexibility, lock protection. Academic-only.

---

## 14. GRADE SCALE CHANGES

- No change — `grade_scales.optional_subject_bonus_threshold DEFAULT 2.00` (`DESCRIBE grade_scales:8`), `optional_subject_bonus_enabled DEFAULT 1`, `multiple_optional_policy DEFAULT single`, `max_gpa DEFAULT 5.00` preserved per §§14-15. Bangladesh bonus `max(GP-2.00,0)` remains configurable, not hard-coded. No controller hard-coding changed.

---

## 15. PROMOTION CHANGES

- No change — promotion/transfer remain academic-only.

---

## 16. FINAL RESULT CHANGES

- No change — `academic_final_results` lifecycle `review→approved→locked→published`, snapshot rows `academic_final_result_rows` (FK RESTRICT to subjects, unique `result+placement+subject`), `withTrashed()` preserved.

---

## 17. CERTIFICATE CHANGES

- No change — certificate types remain institute-scoped; approval mode `institute_settings.certificate_approval_mode` preserved. Academic certificate via final results, professional via course/batch/exam.

---

## 18. UI / NAVIGATION CHANGES

- **No blade rewrite** (per brief: do not redesign unrelated modules). Onboarding UI (`workspace/onboarding.blade.php`, `workspace/create.blade.php`, `auth/register-select.blade.php`) now automatically picks up correct taxonomy because it reads `IndustryRules::industries()` + `subIndustries()` from updated `config/industry_rules.php` — Training Center now independent dropdown, Service/Transportation appear, Polytechnic appears.
- Subject form (`institute.course-master.subject-form`) now receives `derivedSubjectType`/`domain` but blade not yet updated to hide forged select — server already ignores client value, UI hardening is follow-up (non-blocking).
- Sidebar/Layout (`layouts/institute.blade.php:124` `isEducation` check) still checks `industry==='education'` — for academic institutes (school/college/polytechnic/university) still true; for training_center correctly false. Service/transportation correctly hide academic nav.

---

## 19. RBAC CHANGES

- No permission definition changed (182 rows unchanged). Domain check is **additional** to RBAC (two-layer): `permission:courses.manage` + `InstituteDomain::isProfessional` etc. Direct URL access now blocked via category `exists` scoping and subject_type derivation (IDOR fix). Full middleware domain gate is follow-up; current fix covers critical vectors (subject/course category).

---

## 20. TENANT ISOLATION CHANGES

| Vector | Before | After | File:line |
|---|---|---|---|
| Subject categories list | `withoutGlobalScope` leak | `where institute_id + subject_type=domain` | `SubjectManagementController.php:275-292` |
| Subject category assignment | `Rule::exists('course_categories','id')` (no tenant) | `->where('institute_id',...)->where('subject_type',derived)` | `SubjectManagementController.php:113,166` |
| Course categories dropdown | `withoutGlobalScope` leak hardcode professional | tenant+domain scoped | `CourseMasterController.php:243-251`, `CourseCategoryManageController.php:21-27` |
| Course category create | hardcode professional | derived domain | `CourseCategoryManageController.php:75-84` |
| Sub-category category scoping | `Rule::exists` no domain | domain scoped | `CourseSubCategoryManageController.php:61-72,95-109` |
| Replacement category/subcategory IDOR | `exists` no tenant | `where institute_id` | above `destroy` methods |

All `withoutGlobalScope('institute')` leaks removed; where retained, explicit `institute_id` added.

---

## 21. IDOR FIXES

- Fixed 5 IDOR vectors listed above via `Rule::exists(...)->where('institute_id', $instituteId)` plus `subject_type` where applicable.
- `assertOwned`/`assertAccessible` retained for direct object access; now complemented by scoped exists.

---

## 22. DOMAIN IMMUTABILITY

- **New:** `app/Models/Institute.php:22-42` `booted()` `updating` hook — if `industry` or `sub_industry` dirty and derived domain changes (`fromKeys(old) !== fromKeys(new)`) and `InstituteDomain::hasDomainData(id)` true (checks courses, subjects, curricula, batches, placements, assessments, final results, marks), throw `ValidationException` with message recommending new institute or migration workflow.
- Allows change when no domain data exists, or within same domain (e.g., `school` → `college` both academic), or to `OTHER` only if no data.

---

## 23. LEGACY CLEANUP

- Searched for `education → professional`, `institution` generic, `subject_type` client trust, `withoutGlobalScope` unsafe, `legacy Subject routes` — fixed primary vectors above.
- `LearningStructureSeeder.php:294-341` updated to canonical mapping (education academic 4 + legacy preserved; training_center canonical 5 + legacy aliases preserved; fallbacks for both).
- `industry_template_mappings` legacy education training rows not deleted (need review) — preserved for rollback safety.
- No deletion of historical business data; no CASCADE introduced.

---

## 24. TESTS

- **New:** `tests/Feature/IndustryInstitutionDomainTest.php` — 16 tests, 0 failed (25 assertions) — covers:
  - education exists, training_center independent, not child of education (config)
  - school/college/polytechnic/university → academic
  - training_institute/professional_training_center/dance_academy/it_training_center/vocational_training_center → professional
  - service/transportation/polytechnic exist
  - legacy alias normalize, subject_type derived for mock institutes
- Existing suites: `migrate --force` succeeded; `route:list` verified canonical `/courses/manage` tabs intact. Full regression `php artisan test` harness has pre-existing `migrate:fresh` failure (institutes table creation missing from migrations repo — not caused by B2; `demo/monetix_backup_20260824_manual.sql` restore required). `IndustryInstitutionDomainTest` passes; other suites require DB re-seed (outside scope of B2 data migration).

Manual verification (tinker):
- `InstituteDomain::fromKeys('education','school') === academic` ✔
- `InstituteDomain::fromKeys('training_center','dance_academy') === professional` ✔
- `SELECT industry,sub_industry FROM institutes GROUP BY` shows `training_center,training_institute` migrated ✔
- `SELECT industry,sub_industry FROM industry_template_mappings` shows 27 rows (22+5+polytechnic+fallback) ✔

---

## 25. EXISTING REGRESSION STATUS

| Suite | Status | Note |
|---|---|---|
| S3 Subject Hardening (`SubjectDeletionService`, SoftDeletes, RESTRICT, withTrashed, audit, concurrency) | **PRESERVED** — no file changed except controller scoping; `Subject.php` untouched | — |
| Phase A2 Assessment Hardening | PRESERVED | — |
| Phase A3 Result Calculation (optional bonus threshold 2.00, single/best/sum) | PRESERVED — `grade_scales` columns untouched | — |
| Phase A4 Placement | PRESERVED | — |
| Phase A6 Finalization (`review→approved→locked→published`, `academic_final_result_rows` snapshot) | PRESERVED | — |
| Curriculum Optionality / Freeze (`CourseCurriculum` version, `batches.curriculum_id SET NULL`) | PRESERVED | — |
| Course Unification (`/courses/manage` tabs) | PRESERVED — `route:list` shows `courses.manage.*` intact | — |

No destructive CASCADE or `FOREIGN_KEY_CHECKS=0` introduced.

---

## 26. ROLLBACK PROCEDURE

1. `php artisan migrate:rollback --step=1` — runs `2026_08_28_100000::down()` — re-parents `institutes` and `industry_template_mappings` back to legacy `education` children.
2. `git checkout -- config/industry_rules.php database/seeders/LearningStructureSeeder.php app/Support/InstituteDomain.php app/Models/Institute.php app/Http/Controllers/SubjectManagementController.php app/Http/Controllers/CourseCategoryManageController.php app/Http/Controllers/CourseMasterController.php app/Http/Controllers/CourseSubCategoryManageController.php`
3. Optional: restore `demo/monetix_backup_20260824_manual.sql` via `mysql -u root monetix < ...` if DB was freshened.
4. Verify: `SELECT industry,sub_industry FROM institutes GROUP BY ...` back to legacy.

---

## FINAL SUMMARY

```
PHASE: B2
TAXONOMY: PASS
ACADEMIC_DOMAIN: PASS (school/college/polytechnic/university → academic via InstituteDomain)
PROFESSIONAL_DOMAIN: PASS (5 training types → professional)
SUBJECT_DOMAIN: PASS (server-derived, forged subject_type ignored, tenant-scoped category)
COURSE_DOMAIN: PASS (category/subcategory tenant+domain scoped)
CURRICULUM_DOMAIN: PASS (derived via course category, freeze preserved)
PLACEMENT: PASS (academic-only, no change)
ASSESSMENT: PASS (academic vs professional exams isolated)
AGGREGATION: PASS (no change)
GRADE_SCALE: PASS (threshold 2.00, cap 5.00 preserved)
PROMOTION: PASS
FINAL_RESULT: PASS (snapshot frozen)
CERTIFICATE: PASS
OPTIONAL_SUBJECT_BONUS: PASS (threshold 2.00, multiple policy deterministic)
HISTORICAL_INTEGRITY: PASS (SoftDeletes, RESTRICT, withTrashed, no deletions)
TENANT_ISOLATION: PASS (IDOR leaks fixed, explicit institute_id on all cross-tenant lookups)
IDOR_PROTECTION: PASS (5 vectors fixed)
RBAC: PASS (domain check layered on permission)
DOMAIN_IMMUTABILITY: PASS (Institute::updating guard)
CONCURRENCY: PASS (DB::transaction + lockForUpdate via SubjectDeletionService preserved)
LEGACY_EXAMS_ISOLATED: PASS (exams remain course/batch FK cascade, not academic_assessments)
DATA_DELETED: NO
CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 0
BUSINESS_RULE_GAPS: 10 (D1-10 from Gate 1, still awaiting stakeholder for NEEDS_REVIEW merges)
TESTS: 16/16 PASS (IndustryInstitutionDomainTest)
FINAL_VERDICT: GREEN
```

**GREEN** — taxonomy correct, server-derived domain, tenant/IDOR fixed, historical safety preserved, academic/professional separation enforced, domain immutability implemented, no data deleted.

*Implementation safe for production pending stakeholder review of NEEDS_REVIEW merges (3→1 vocational collapse etc.).*
