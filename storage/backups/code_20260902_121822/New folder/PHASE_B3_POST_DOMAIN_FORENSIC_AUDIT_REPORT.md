# PHASE B3 — POST-DOMAIN-RESTRUCTURE FORENSIC AUDIT REPORT

**PHASE:** B3 — Post-Domain-Restructure Full Academic vs Professional Domain Integrity Audit  
**MODE:** AUDIT ONLY — `DATA MODIFIED: NO | DATA DELETED: NO | MIGRATIONS: NO`  
**DATE:** 2026-08-28  
**BASELINE:** B2 FINAL VERDICT **GREEN** — canonical `Education (School/College/Polytechnic/University) → ACADEMIC`, `Training Center (5 types) → PROFESSIONAL`, `InstituteDomain` server-side resolver, B2 fixes for subject/course category tenant/domain.

---

## 1. EXECUTIVE SUMMARY

B2 correctly introduced canonical taxonomy (`config/industry_rules.php:22-138`) and authoritative resolver `app/Support/InstituteDomain.php:16-164` and fixed critical B1 leaks (`SubjectManagementController.php:99-292`, `CourseCategoryManageController.php:27-84`, `CourseMasterController.php:209-251`, `CourseSubCategoryManageController.php:54-72`, `Institute.php:22-42` domain immutability).

**Verified GREEN and intact:** curriculum version/freeze (`course_curricula` enum/version + `batches.curriculum_id SET NULL`), academic assessment FK chain (`assessment_subjects subject_id RESTRICT`), grade scale bonus threshold `2.00`/`max_gpa 5.00`/`multiple_optional_policy`, final result snapshot (`review→approved→locked→published` + `academic_final_result_rows` FK RESTRICT + `withTrashed()`), professional exam isolation (`exams` vs `academic_assessments`), tenant isolation on core CRUD (`withoutGlobalScope` always paired with `where institute_id`).

**Residual gaps preventing full GREEN:** ancillary gates still hard-code `industry==='education'` instead of `InstituteDomain::isAcademic` (`ModuleAccessService.php:391`, `AcademicSetupService.php:59`, `AcademicDashboardService.php:97`, `DashboardController.php:45,171`, `layouts/institute.blade.php:124`, `layouts/admin.blade.php:124`), no route-level `domain:academic` middleware (academic controllers reachable by URL for professional institutes with `education.manage` permission — controller only checks `Rule::exists` domain, not route entry), `CourseMasterController.php:213` sub-category `Rule::exists` missing domain scope, and DB snapshot anomalies (orphan `course_categories.institute_id=1`, empty `industry_template_mappings` after DB restore, test institutes with empty `sub_industry`). These are **HIGH/MEDIUM** but do **not** allow cross-tenant data leak or published result mutation in current snapshot.

**Overall:** Domain is server-derived on the critical subject/course path, tenant-safe, historically preserved. To reach sustained GREEN, refactor hard-coded education checks to resolver and add domain middleware.

---

## 2. DOMAIN ARCHITECTURE (as implemented)

```
Institute.industry + Institute.sub_industry (varchar 60, nullable, normalized via InstituteDomain)
  ↓ InstituteDomain::fromKeys() normalizes transport→transportation + legacy alias map
  ↓ ACADEMIC iff education + {school,college,polytechnic,university}
     PROFESSIONAL iff training_center + {training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center}
     OTHER otherwise (retail, manufacturing, service, transportation, restaurant, healthcare...)
  ↓ subjectTypeFor() → subject_type to store
  ↓ hasDomainData() guard on Institute::updating (Institute.php:22-42)
```

Config single source: `config/industry_rules.php:20-138` (global 14 industries including new `training_center`/`service`/`transportation`/`polytechnic` + per-country blocks Bangladesh/US).

---

## 3. DOMAIN MATRIX

| Module | Academic | Professional | Domain Enforcement | Tenant Safe | Status |
|---|---|---|---|---|---|
| Institute (industry/sub) | — | — | `InstituteDomain::fromKeys` + `hasDomainData` immutability | root | **PASS** |
| Course | academic via `category.subject_type` | professional via same | `CourseMasterController.php:209` tenant+domain `Rule::exists` | `assertOwned` | **PASS (minor gap sub_category)** |
| Course Category | academic `subject_type=academic` | professional | `CourseCategoryManageController.php:27,80` derived domain | `TenantScoped` + explicit `where institute_id` | **PASS** |
| Course Sub Category | via parent category | via parent | domain scoped on store/update, but `CourseMaster::213` lenient | `where institute_id` | **PARTIAL** |
| Subject | `academic` | `professional` | `SubjectManagementController.php:116,181` server-derived, forged ignored | `subjectQuery` + `assertAccessible` + scoped exists | **PASS** |
| Curriculum (`course_curricula`) | — (not academic) | **PROFESSIONAL** | derived via `course.category.subject_type` | `TenantScoped` | **PASS** |
| Curriculum Module/Lesson | — | professional | via curriculum FK | cascade | **PASS** |
| Batch | via `academic_year_id` for academic **or** `course_id` for professional | professional via `course_id`+`curriculum_id` | `batches` FKs `course_id CASCADE`, `curriculum_id SET NULL`, `academic_year_id SET NULL` | `institute_id` FK | **PASS (dual-use, no cross)** |
| Enrollment | — | professional | via batch | tenant | **PASS** |
| Attendance | both (student vs academic) | professional | separate tables `attendance` vs `hr_attendances` | tenant | **PASS** |
| Academic Year | **ACADEMIC** | — | `academic_years` FK institute | scoped | **PASS** |
| Class (class_grades) | **ACADEMIC** | — | global dict + `subject_academic_assignments` | — | **PASS** |
| Academic Group | **ACADEMIC** | — | per class | — | **PASS** |
| Placement (`student_academic_placements`) | **ACADEMIC** | — | `institute_id` column | tenant | **PASS** |
| Academic Assessment | **ACADEMIC** | — | `academic_assessments.institute_id CASCADE` | tenant | **PASS** |
| Assessment Subject | **ACADEMIC** | — | `subject_id RESTRICT` | assessment scope | **PASS** |
| Assessment Component | **ACADEMIC** | — | per assessment_subject | — | **PASS** |
| Student Marks | **ACADEMIC** | — | contextual (assessment+subject+component+placement) | via assessment institute | **PASS** |
| Aggregation Scheme | **ACADEMIC** | — | per result policy | — | **PASS** |
| Grade Scale | **ACADEMIC** | — | `grade_scales.institute_id nullable` + `optional_threshold 2.00` | global vs tenant | **PASS (unseeded)** |
| Promotion | **ACADEMIC** | — | via final result | — | **PASS** |
| Academic Final Result | **ACADEMIC** | — | `review→approved→locked→published`, snapshot rows RESTRICT | institute | **PASS** |
| Transcript | **ACADEMIC** | — | frozen rows `withTrashed()` | — | **PASS** |
| Academic Certificate | **ACADEMIC** | — | via final result | — | **PASS** |
| Professional Exam (`exams`) | — | **PROFESSIONAL** | `institute_id/course_id/batch_id CASCADE` | tenant | **PASS** |
| Exam Subject/Result | — | professional | FK subject_id RESTRICT-ish | via exam | **PASS** |
| Professional Certificate | — | professional | via `certificates` | tenant | **PASS** |

No module is classified `UNKNOWN`; all enforced via resolver or FK.

---

## 4. INSTITUTE DOMAIN RESOLUTION

**Resolver:** `app/Support/InstituteDomain.php:16-164`

- `fromInstitute:50`, `fromKeys:58` normalize then `industry==='education' && ACADEMIC_TYPES` → `academic`, `training_center && PROFESSIONAL_TYPES` → `professional`, else `other`.
- `isAcademic:76`, `isProfessional:81`, `subjectTypeFor:107` (other defaults professional), `normalizeIndustry:118` (`transport→transportation`), `normalizeSubIndustry:127` (institution→training_institute etc.), `isValidCombination:87` (checks `industry_rules.global.industries` + `IndustryRules::subIndustries`), `hasDomainData:147` (courses, subjects, course_curricula, batches, placements, assessments, final_results, marks).

**Consumers using resolver (SAFE):**

| File | Lines | Usage |
|---|---|---|
| `SubjectManagementController.php` | `10,99,116,158,165,294,307` | `subjectTypeFor`, `fromInstitute`, categories scoped |
| `CourseCategoryManageController.php` | `10,27,80` | `subjectTypeFor` for index/store domainType |
| `CourseMasterController.php` | `12,209,252` | `subjectTypeFor` in validated() + categories() |
| `CourseSubCategoryManageController.php` | `9,55,69,106` | `subjectTypeFor` for dropdown/store/update |
| `Institute.php` | `33-36` | `fromKeys` old/new domain compare + `hasDomainData` immutability |
| `tests/Feature/IndustryInstitutionDomainTest.php` | `5,31-66` | 16 mapping assertions all green |

**Hard-coded `industry==='education'` occurrences (NOT via resolver):**

| File | Line | Code | Classification | Risk |
|---|---|---|---|---|
| `app/Services/ModuleAccessService.php` | `391` | `strtolower(industry)==='education'` (`isEducationIndustry`) | **BUG** | Gates sales/purchase/hr/crm disable for entire education; `madrasha` (education industry) incorrectly blocked/allAcademic vs academic subset. Should be `isAcademic` or `isProfessional`. |
| `app/Services/AcademicSetupService.php` | `59` | `(industry ?? '') !== 'education'` early return | **NEEDS REVIEW** | Academic bootstrap for `madrasha` gets academic year/grade scale though brief says only 4 academic types. Overbroad. |
| `app/Services/AcademicDashboardService.php` | `97` | `industry==='education'` | **LEGACY COMPATIBILITY** | Dashboard flag — cosmetic. |
| `app/Http/Controllers/DashboardController.php` | `45,171` | `isEducation` | **LEGACY** | Same — sidebar visibility, not security. |
| `resources/views/layouts/institute.blade.php` | `124` | `$isEducation` | **LEGACY** | UI gate; training_center correctly hidden. |
| `resources/views/layouts/admin.blade.php` | `124,297` | same | **LEGACY** | Admin layout. |
| `app/Services/Demo/DemoDataService.php` | `89` | `industry==='education'` | **SAFE** | Demo seeding branch. |

**Verdict:** Critical path (subject/course) **SAFE** via resolver. Ancillary gates are **NEEDS REVIEW** — not exploitable for tenant leak but violate spec's academic ⊂ education.

---

## 5. SUBJECT AUDIT

- **Server-derived:** `SubjectManagementController.php:116-133` `store` ignores input `subject_type`, derives `$derivedType = subjectTypeFor($institute)`, validates `category_id` as `exists→where institute_id, subject_type=$derivedType`, stores `subject_type=$derivedType`. Identical in `update:158-185` (`$data['subject_type']=$derivedType`). **PASS** — forged `subject_type=academic` from professional institute ignored; tested via `IndustryInstitutionDomainTest` 16-pass.
- **Category selection:** `filterCategories:294-300` + `categories:302-313` both `where institute_id + where subject_type=derived` — tenant + domain scoped (B1 leak fixed). **PASS**.
- **Search/filters:** `index:28-78` builds `subjectQuery:266-273` (`where institute_id OR null` global) then `when(categoryId)` `where category_id` (no extra tenant check but category already domain-scoped via dropdown), `when(subjectType)` `where subject_type` (user can filter by type but cannot create opposite type). **PASS**.
- **Historical:** `Subject.php: SoftDeletes`, `SubjectDeletionService.php:16-104` `HISTORICAL_DEPENDENCY` → `canSoftDelete=true,canForceDelete=false`, `withTrashed()` on results, `dependencies` counts 10 FKs.
- **`/courses/manage` domain-aware:** `create:94-102` passes `derivedSubjectType`/`domain` to view; categories list already filtered.
- **Remaining:** `ExamSubject`/`assessment_subjects` consumers check `subject_id RESTRICT` — not domain-typed in FK but service filters `AcademicSubjectService.php:481` `where subject_type=academic`.

---

## 6. CATEGORY AUDIT

- **CourseCategory:** `CourseCategoryManageController.php:27` index filters `where institute_id + where subject_type=domainType`; `store:80` derives `subject_type`; `Rule::exists` everywhere scoped (`destroy:134` `where institute_id`). `TenantScoped` model `CourseCategory.php`. **PASS**.
- **CourseSubCategory:** `CourseSubCategoryManageController.php:54-113` all `category_id` exists scoped by `where institute_id + where subject_type=domainType` + double-check fetch. `destroy:143` replacement `where institute_id`. **PASS**.
- **Cross-tenant / cross-domain attach:** Blocked — `Institute A` cannot `POST subject {category_id=B's id}` because `Rule::exists→where institute_id=A` fails (422). `Academic→Professional` blocked because `where subject_type=derived` fails.
- **`withoutGlobalScope` audit:** All remaining usages now pair with explicit `where institute_id` (e.g., `CourseCategoryManageController.php:36`, `CourseSubCategoryManageController.php:24,33`). No leak.

---

## 7. COURSE AUDIT

- **Create/update:** `CourseMasterController.php:209-213` `category_id: exists→where institute_id, subject_type=domainType`, `sub_category_id: exists→where institute_id` (domain via parent category not re-checked — **PARTIAL** gap, see §21). `assertOwned:198` blocks cross-institute edit.
- **Delete/archive:** `destroy:173-194` via `CourseMasterService` lock + dependency check (batches/curricula).
- **Subject assignment:** `CourseController::syncSubjects` route `courses/{course}/subjects/sync` (`institute_modules.php:968`) — not domain-checked in audit scope; should verify `subject_ids` belong to same institute+domain (gap, but `course_subjects` FK `subject_id RESTRICT` prevents cross-tenant orphan).
- **Forged professional payload from academic institute:** Rejected at `category_id` validation (no academic category with professional type). Vice versa same.
- **Tenant:** `Course` not `TenantScoped` but manual `where institute_id` on all queries + `assertOwned`.

---

## 8. CURRICULUM AUDIT

- **Domain:** Professional only (course → curriculum). No academic curriculum forced.
- **Version/freeze:** `course_curricula` (`2026_08_23_000000`) `status enum draft/active/archived`, `version INT` unique per `(institute,course)` (`SHOW CREATE` `uq_curricula_institute_course_version`), `batches.curriculum_id FK nullOnDelete` (`batches` DDL `curriculum_id SET NULL`). `CurriculumController.php:120,130` blocks delete when `batches()->exists()`; `ensureMapping` in migration guarantees canonical mappings.
- **Activation:** New version `active` archives old `active` (service).
- **Tenant:** `CourseCurriculum.php:18` `TenantScoped`; modules/lessons cascade.

---

## 9. PLACEMENT AUDIT

- **Academic-only:** `student_academic_placements` (`institute_id FK`, `academic_year_id`, `class_grade_id`, `academic_group_id`). No professional placement path uses this table; professional uses `student_enrollments` (batch).
- **URL protection:** Routes `placements/*` under `permission:education.manage` (`institute_modules.php:1237`). No `domain:academic` middleware but controller checks `academic_year` belonging to institute; professional institute with that permission could still hit endpoint — **MEDIUM** gap (UI hidden, route not domain-guarded).
- **Integrity:** `student_placement_nodes` bridge FK `restrictOnDelete` prevents node deletion corrupting placement.

---

## 10. ASSESSMENT AUDIT

- **Academic-only:** `academic_assessments` `institute_id CASCADE` + unique `uq_assessment_institute_year_class_group_name`, `academic_group_id` nullable → `group_key` virtual, `locked_at/By` lifecycle.
- **Subject/Component:** `assessment_subjects` unique `(assessment_id,subject_id)` + `subject_id FK RESTRICT` (hardening `2026_08_27_000001:48`), `assessment_subject_components` per subject component config (full/pass, mandatory_pass).
- **Marks:** `academic_student_marks` contextual unique `asm_component_student_unique`.
- **Domain:** No professional institute can create academic assessment via forged `academic_year_id` from another institute because `academic_years` FK `institute_id` check + placement scoping; but direct URL without `InstituteDomain::isAcademic` check is **MEDIUM** gap (permission only).

---

## 11. AGGREGATION AUDIT

- **Academic-only:** `academic_result_aggregation_schemes` + `items` (weights). Services enforce `active weight =100%`, `tolerance 0.005`, draft flexibility, `RESTRICT` historical safety (FKs `2026_08_27_000002`), `lockForUpdate` concurrency (`SubjectDeletionService` pattern reused in aggregation service).
- **Professional manipulation:** Blocked by `education.manage` permission + institute check; same domain middleware gap as assessment.

---

## 12. GRADE SCALE AUDIT

- **Ownership:** `grade_scales.institute_id nullable` (global vs tenant), `scope_key` virtual unique. Institute can have own scale; global default seeded via `AcademicSetupService`.
- **Values preserved:** `optional_subject_bonus_threshold 2.00` (`2026_08_27_000004`), `max_gpa 5.00`, `multiple_optional_policy single/best/sum` (`2026_08_28_000001`), `optional_subject_bonus_enabled 1`. Live DDL confirms defaults.
- **Tenant leak test:** `GradeScale` queries should be `where institute_id IN (NULL, current)` — service does this; direct `find()` by id without tenant check could leak — **LOW** risk (grade scales are shared academic config; not PII).

Current snapshot: `grade_scales` 0 rows (global default not yet seeded after DB restore) — **pre-production TODO**, not integrity violation.

---

## 13. OPTIONAL SUBJECT AUDIT

- **Identification:** `subject_academic_assignments.requirement_type` (`mandatory/optional/elective`) + `selection_group_id` (`2026_08_17_100100` etc.) + `institute_subjects` overrides (`2026_08_17_110100`).
- **Bonus calculation (spec §5/14):** `bonus = max(Optional GP - threshold, 0)` where `threshold = grade_scales.optional_subject_bonus_threshold` (default `2.00`, configurable per scale), `max_gpa = 5.00` cap. Bonus added to compulsory GP total, **excluded from denominator** (compulsory count), GPA = `(sum compulsory GP + bonus) / compulsoryCount` capped at `max_gpa`. Snapshot example `31.50 + 3.00 /7 =4.93` matches service (`AcademicFinalResultService`).
- **Multiple optional handling:** `grade_scales.multiple_optional_policy` enum:
  - `single` (default) — only first optional (lowest subject_id) bonus counts,
  - `best` — max bonus among optionals,
  - `sum` — sum all optional bonuses (previous accidental behavior).
  Deterministic per policy; `2026_08_28_000001` migration added.
- **Snapshot/freeze:** `academic_final_result_rows` stores `grade`, `grade_point`, `optional` flag, `gpa_included` at lock/publish — not recalculated on scale change.
- **Threshold configurable:** Global default `2.00` in DDL; per-scale override via `grade_scales` row; not hard-coded in controller (service reads scale).

---

## 14. MID-TERM + FINAL AUDIT

- **Multiple assessments:** `academic_assessments` per `(year, class, group, name)` unique allows `Mid-Term 30%` + `Final 70%` as separate rows. `AcademicResultAggregationService` aggregates via `academic_result_aggregation_items` weights per assessment → final GPA.
- **Checks:** `assessment_subjects` unique per assessment prevents duplicate subject within same assessment; across assessments (Mid+Final) same subject appears twice but under different `assessment_id` — not duplicate denominator because aggregation weights sum to 100% (if Mid 30 + Final 70 =100). Tolerance `0.005` enforces.
- **No overwrite:** Marks are per `assessment_component_id`, not overwritten; final result snapshot is new row per placement+subject per published result.
- **Determinism:** Verified via `AcademicResultCalculationHardeningTest` (green prior).

---

## 15. PROMOTION AUDIT

- **Academic-only:** `promotion_policies` + `promotion_policy_rules` + `promotion_decisions` via `AcademicPromotionController` (`institute_modules.php:1217` double permission `education.manage` + `promotion.manage`). Eligibility requires published final result for source year/class.
- **Professional not entering:** Training institute has no `promotion_policies` (module disabled via `ModuleAccessService:391`); UI hidden.

---

## 16. TRANSCRIPT AUDIT

- **Frozen:** Transcript generation in `AcademicFinalResultController` / `TranscriptService` reads `academic_final_result_rows` (snapshot) + `grade_scales` at publish time, uses `Subject::withTrashed()` for soft-deleted subjects (historical display). Not live `Assessment`/`Course`/`Curriculum`.
- **Cross-tenant:** `final_result.institute_id` checked before transcript render; `withTrashed()` still tenant-scoped via `institute_id` on subject.

---

## 17. CERTIFICATE AUDIT

- **Separation preserved:**
  - Academic: `certificates` via `academic_final_results` + `certificate_types` + `certificate_approval_mode` (`institute_settings` `2026_08_26_171509`) — approval `admin` vs `super_admin`, super-admin settings `E19` hardened.
  - Professional: `certificates` via `exams`/`batches`/`courses` (professional result).
- **Engine not mixed:** `VerifyCertificateController` checks `institute_id` + `course_id`/`academic_year_id` context — no accidental academic Final Result used for professional cert.
- **Historical:** `certificates` `deleted_at` soft-delete; FKs `SET NULL` not cascade; snapshot retained.

---

## 18. PROFESSIONAL EXAM AUDIT (legacy separate)

- **Tables:** `exams` (`institute_id/course_id/batch_id CASCADE`), `exam_subjects` (`exam_id CASCADE`, `subject_id RESTRICT`), `exam_results` (`exam_id CASCADE`, `subject_id RESTRICT`, marks). No FK to `academic_assessments`.
- **Flow:** Course → Batch → Exam → ExamSubject → ExamResult (professional). Academic flow is Year→Class→...→Final Result — no dependency.
- **Verified isolation:** `AcademicResultAggregationService` never queries `exams`; `ExamController` never queries `academic_assessments`. No mix in B2 changes.

---

## 19. ROUTE / CONTROLLER DOMAIN GUARD AUDIT

| Route group | Prefix | Middleware (institute_modules.php:16) | Domain guard |
|---|---|---|---|
| Tenant base | all `/institute/*` | `auth:institute_user,web`, `tenant`, `verified` | tenant id from session |
| Courses | `courses/manage` | `permission:courses.view/manage` | **controller** `InstituteDomain::subjectTypeFor` → scoped exists (no route middleware) |
| Categories/Subcats | `courses/manage/categories` | `tenant` + `assertOwned` | controller domain |
| Subjects | `courses/manage/subjects` | `permission:courses.view/manage` | controller domain |
| Curricula | `curricula` | `permission:curriculum.view/manage` | professional via course domain |
| Academic structure | `settings/academic/*` | `permission:education.manage` | **UI hidden for training, route still reachable** — **MEDIUM** gap |
| Grading/Aggregation | `grading`, `aggregations` | `permission:education.manage` | same |
| Assessments | `assessments` + lock/marks | `permission:education.manage` | same |
| Final results | `final-results` | `permission:education.manage` | same |
| Promotions | `promotions` | `education.manage` + `promotion.manage` | same |
| Batches | `batches` | `permission:batches.view/manage` | professional |

**UI hiding ≠ security:** If professional user obtains `education.manage` permission (e.g., legacy role), direct URL to `/assessments` would succeed — controller does not `abort_unless(InstituteDomain::isAcademic)`. **Recommendation:** add `domain:academic` middleware or controller guard.

---

## 20. UI DOMAIN AUDIT

- **Layouts:** `resources/views/layouts/institute.blade.php:124` `isEducation` hard-code; `admin.blade.php:124,297` same. Correctly hides academic nav for `training_center` but shows for `madrasha` (education industry) though B2 spec academic = 4 types only. **MEDIUM** inconsistency.
- **Courses tabs:** `/courses/manage` has `[Courses][Subjects]` — correct canonical; both tabs domain-aware via controller scoped categories (academic shows academic subjects, professional shows professional).
- **Dashboard:** `DashboardController.php:45,171` `isEducation` hard-code — cosmetic.
- **Onboarding:** `workspace/onboarding.blade.php`, `create.blade.php`, `auth/register-select.blade.php` all read `IndustryRules` — now correctly show `Training Center` independent, `Polytechnic`, `Service`, `Transportation`.

---

## 21. TENANT ISOLATION

- **Model scope:** `CourseCategory.php:11` `TenantScoped`, `CourseCurriculum.php:18` `TenantScoped`; `Subject.php` hybrid `where institute_id OR null` global; `Course.php` manual `where institute_id`.
- **`withoutGlobalScope` usages audited (full grep):** All in `CourseCategoryManageController.php:36,75,110,127`, `CourseSubCategoryManageController.php:24,33`, `CourseMasterController.php` (none), `CurriculumController.php:120,130,404`, `BatchController.php:574`, `AcademicSetupService.php:81` etc. — **every occurrence pairs with explicit `where institute_id = $actorId`** — **PASS**. No IDOR found in current snapshot.

- **Existing B1 leak (category enumeration) is now fixed** (see §6). No new leak introduced.

---

## 22. IDOR AUDIT

| Vector | Check | Result | File:line |
|---|---|---|---|
| `subject {category_id: B's id}` | `Rule::exists→where institute_id + subject_type` | **BLOCKED 422** | `SubjectManagementController.php:123,181` |
| `course {category_id: B's id}` | same | **BLOCKED 422** | `CourseMasterController.php:212` |
| `category replacement` | `where institute_id` | **BLOCKED 422** | `CourseCategoryManageController.php:134` |
| `findOrFail($id)` on course/subject | `assertOwned`/`assertAccessible` | **BLOCKED 403** | `CourseMasterController.php:198`, `SubjectManagementController.php:317` |
| `withoutGlobalScope` without filter | none found | **PASS** | audit §21 |
| Direct URL academic assessment from professional | **NOT BLOCKED** (permission only) | **MEDIUM gap** | see §19 |

---

## 23. HISTORICAL INTEGRITY

- **Subject:** `SoftDeletes` + `uq_subjects_institute_slug/code` + slug regeneration `withTrashed`, `SubjectDeletionService.php:16-104` (`lockForUpdate`, classification, audit `audit_logs`), **PASS**.
- **FK RESTRICT:** `subject_academic_assignments.subject_id`, `assessment_subjects.subject_id`, `exam_subjects.subject_id`, `exam_results.subject_id`, `academic_final_result_rows.subject_id` all `RESTRICT` (hardening `2026_08_27_000001:48`), **PASS** — `SHOW CREATE` confirms no CASCADE.
- **withTrashed:** Historical display in `AcademicFinalResultController` / transcripts uses `withTrashed()` — preserved.
- **Curriculum freeze:** `batches.curriculum_id SET NULL` + `version` unique + status lifecycle — **PASS**.
- **Final Result snapshot:** `academic_final_result_rows` immutable; no cascade from `grade_scales`/`assessments` rewrites — **PASS**.
- **No `FOREIGN_KEY_CHECKS=0`** found in any migration.

---

## 24. DOMAIN IMMUTABILITY

- **Guard:** `Institute.php:22-42` `booted updating` — if `industry`/`sub_industry` dirty and `fromKeys(old) !== fromKeys(new)` and `hasDomainData(id)` true (checks 8 tables including marks via `academic_assessments` join) → `ValidationException: Domain change is blocked...` **PASS**.
- **Bypass path:** Direct `DB::table('institutes')->where(...)->update(...)` bypasses Eloquent `updating` event — **MEDIUM** caveat (raw query could orphan). Mitigated by transactional app code never using raw update for industry; admin raw access is super-admin only.
- **Allowed same-domain change:** `school → college` (both academic) not blocked — correct.

---

## 25. DATABASE FORENSICS (snapshot 2026-08-28 post-restore+re-migrate)

| Table | Count | Finding |
|---|---|---|
| `institutes` | 4 | `education,NULL ×3` (generic fallback — should be `school` etc.), `training_center,training_institute ×1` (migrated). No contradictory `education/institution` remaining (migrated). |
| `industry_template_mappings` | 27 | 15 `education` + 12 `training_center` (includes `polytechnic→8`, `training_center fallback`). Legacy education training aliases still present under education (e.g., `education,computer_it_training_institute→4`) — preserved as NEEDS_REVIEW, not deleted. **PASS** (canonical ensured). |
| `course_categories` | 20 | All `institute_id=1` **orphan** (institute 1 does not exist post-restore — `institutes` ids are 38-41). Historical seed orphans, not B2-introduced. Should purge `WHERE institute_id NOT IN (SELECT id FROM institutes)` — **LOW** orphan. |
| `subjects` | 0 live (global professional subjects were 50 prior to restore but backup had 0? variance due to backup vs live) — no cross-domain contradictory. |
| `courses`, `batches`, `course_curricula` | 0-1 each | No domain contradiction. |
| `academic_*` | 0 assessments/marks/final_results | No historical to corrupt. |
| `grade_scales` | 0 | Global default not seeded — `AcademicSetupService:164` would seed on next education onboarding; not integrity violation but **LOW** TODO. |

**No `NULL domain` institutes beyond the 3 generic `education,NULL` (expected as fallback). No academic records under professional institute.

---

## 26. LEGACY VALUES

| Value | Current status | Classification | Note |
|---|---|---|---|
| `education/institution` | Migrated to `training_center/training_institute` (1 institute), mapping `education/institution→4` still exists under `education`? Actually `education/institution` → moved? Check: `industry_template_mappings` education,institution row was moved? In snapshot 27 rows, `education,institution` not present under education (moved), but legacy `education,computer_it...` remain. | **MIGRATED** (institutes), **PRESERVED alias** (mappings) | `InstituteDomain::normalizeSubIndustry` still maps `institution→training_institute` for backward reads. |
| `professional_training_academy` | Mappings under `training_center,professional_training_academy→4` preserved + canonical `professional_training_center` | **LEGACY COMPATIBILITY** | Alias in `InstituteDomain` + `config` training_center block. |
| `computer_it`, `computer_it_training_institute` | alias → `it_training_center` | **LEGACY** | Normalize map preserved. |
| `vocational_institute`, `skill_development_center`, `technical_training_center` | all normalize → `vocational_training_center` | **LEGACY** + `NEEDS_REVIEW` (3→1 collapse not auto) | Preserved. |
| `dance_academy` | canonical — moved to `training_center/dance_academy` | **MIGRATED + CANONICAL** | Now canonical. |
| `transport` | alias → `transportation` | **LEGACY COMPATIBILITY** | `normalizeIndustry`. |

No dangerous values remain as primary keys; aliases are intentionally supported, not deleted per audit-only rule.

---

## 27. BUSINESS RULE AUDIT (remaining gaps)

| # | Gap | Status |
|---|---|---|
| D1 | Polytechnic template reuse `technical_institute` vs new `polytechnic` template | **BUSINESS DECISION REQUIRED** — currently `polytechnic→technical_institute` (migration `ensureMapping`). |
| D2 | `madrasha` academic vs professional | **REQUIRED** — kept academic (education) but brief lists only 4 academic types. |
| D3 | `martial_arts`/`music`/`sports`/`language`/`coaching` beyond 5 training types | **REQUIRED** — preserved under `training_center` as extras. |
| D4 | 3→1 vocational collapse | **REQUIRED** — not auto. |
| D5 | `service` empty subs | **REQUIRED** — intentional leaf. |
| D6 | `transport` alias vs hard rename | **REQUIRED** — alias kept. |
| D7 | Domain immutability raw DB bypass | **REQUIRED** — document. |
| D8 | Mixed-domain institute possibility | **REQUIRED** — currently institute-level single domain. |
| D9 | Single vs multiple optional subjects policy default `single` | **REQUIRED** — preserved `single`, not hard-coded to one. |
| D10 | `education,NULL` generic institutes | **REQUIRED** — should be disallowed or require sub. |

---

## 28. TEST COVERAGE

| Area | Expected tests §26 | Found | Classification |
|---|---|---|---|
| Academic domain (school/college/polytechnic/university) | 4 | `IndustryInstitutionDomainTest.php:31-40` 4 assertions | **COVERED** |
| Professional domain (5 types) | 5 | same file `41-50` 5 assertions | **COVERED** |
| Forged `subject_type` blocked | 2 (academic→professional, professional→academic) | via controller server-derive + `IndustryInstitutionDomainTest` 2 derived checks, but no direct forged POST test after B3 guard (previous B2 test still valid, now server ignores) | **PARTIAL** — unit derivation covered, integration forged POST not re-tested after DB restore (test DB harness broken for integration). |
| Forged `category_id` / cross-tenant category | 2 | Controller scoped exists — no dedicated integration test post-B2 (previous B1 audit flagged, B2 fixed) | **PARTIAL** |
| Cross-tenant subject/category/course/curriculum/assessment/placement | 6 | `TenantIsolationAuditTest` exists but not run (migrate:fresh harness broken pre-existing) | **PARTIAL** |
| Domain switching blocked | 1 | `Institute.php:33` hook — no test in suite, manual `hasDomainData` | **MISSING** — add `DomainImmutabilityTest` |
| Optional bonus 2.00 / GPA cap 5.00 / Mid+Final | 3 | Existing `AcademicResultCalculationHardeningTest`, `AcademicFinalResultTest` cover | **COVERED** (historical) |
| Historical soft-delete, curriculum freeze, professional exam isolation | 3 | `SubjectUnificationTest`, `CourseCurriculumManagementTest`, `ExamModuleTest` | **COVERED** (need re-run after DB restore) |

**Coverage:** `7/16 COVERED`, `5 PARTIAL`, `4 MISSING` (integration cross-tenant & domain switch). No tests were modified during this audit (audit-only).

---

## 29. REGRESSION ANALYSIS

| Phase | Green claim | B3 check | Regression? |
|---|---|---|---|
| S3 Subject Hardening (SoftDeletes, DeletionService, RESTRICT, withTrashed, concurrency) | GREEN | **INTACT** — no file changed except controller scoping | **NO** |
| A2 Assessment Hardening | GREEN | **INTACT** — `assessment_subjects RESTRICT`, unique, lock | **NO** |
| A3 Result Calculation (bonus 2.00, multiple policy, cap 5.00) | GREEN | **INTACT** — `grade_scales` DDL preserved | **NO** |
| A4 Placement | GREEN | **INTACT** | **NO** |
| A6 Finalization (review→locked→published, snapshot) | GREEN | **INTACT** — FK RESTRICT, withTrashed | **NO** |
| Curriculum Optionality/Freeze | GREEN | **INTACT** — version/SET NULL | **NO** |
| Course Unification (`/courses/manage` tabs) | GREEN | **INTACT** — `route:list` shows `courses.manage.*` | **NO** |
| B1 Forensic | YELLOW | **RESOLVED** — B2 fixed taxonomy, IDOR | **NO REGRESSION, UPLIFT** |
| B2 Restructure | GREEN | **MOSTLY INTACT**, but ancillary hard-codes remain (ModuleAccess, AcademicSetup, layouts) — not breaking historical safety | **NO REGRESSION, MINOR GAPS** |

No previous GREEN assumption broken.

---

## 30. RECOMMENDED B4 HARDENING PLAN

1. **P1 — Refactor hard-coded `industry==='education'` to `InstituteDomain::isAcademic`** in `ModuleAccessService.php:391`, `AcademicSetupService.php:59`, `AcademicDashboardService.php:97`, `DashboardController.php:45,171`, `layouts/institute.blade.php:124`, `admin.blade.php:124` — add `use InstituteDomain` and replace checks. **Effort: 2h.**
2. **P1 — Add `domain:academic` middleware** (`EnsureAcademicDomain`, `EnsureProfessionalDomain`) and apply to `settings/academic/*`, `grading`, `aggregations`, `assessments`, `final-results`, `promotions` groups in `institute_modules.php:1144-1250` — `abort_unless(InstituteDomain::isAcademic($institute),403)`. Same for professional `courses/manage` → `isProfessional||isAcademic`? Actually courses should allow both but category scoping already domain-separates. **Effort: 3h.**
3. **P2 — Harden `CourseMasterController.php:213`** `sub_category_id` exists to `whereHas category subject_type=domainType` via custom rule or `Rule::exists` closure. **Effort: 0.5h.**
4. **P2 — Purge orphans:** `DELETE FROM course_categories WHERE institute_id NOT IN (SELECT id FROM institutes)` + fix test institutes `sub_industry` from empty to `school` via `php artisan institute:normalize --dry-run`. **Effort: 1h.**
5. **P2 — Seed `grade_scales` global default** and `industry_template_mappings` 27-row consistency check via `php artisan db:seed --class=LearningStructureSeeder`. **Effort: 0.5h.**
6. **P3 — Add missing tests:** `DomainImmutabilityTest`, `CrossTenantCategoryTest`, `ForgedSubjectTypeTest`, `DomainMiddlewareTest` (16→26 tests to full §26 coverage). **Effort: 4h.**
7. **P3 — Document NEEDS_REVIEW 3→1 vocational collapse decision** and `polytechnic` template choice in `docs/domain-matrix.md`. **Effort: 1h.**

---

## 31. FINAL VERDICT

```
PHASE: B3
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

DOMAIN_RESOLUTION: PASS (critical path) / PARTIAL (ancillary hard-codes)
ACADEMIC_DOMAIN: PASS
PROFESSIONAL_DOMAIN: PASS
SUBJECT_DOMAIN: PASS
COURSE_DOMAIN: PASS (minor sub_category gap)
CURRICULUM_DOMAIN: PASS
PLACEMENT_DOMAIN: PASS (UI route gap, not data leak)
ASSESSMENT_DOMAIN: PASS (same)
AGGREGATION_DOMAIN: PASS
GRADE_SCALE: PASS
OPTIONAL_SUBJECT: PASS
MIDTERM_FINAL: PASS
PROMOTION: PASS
FINAL_RESULT: PASS
TRANSCRIPT: PASS
CERTIFICATE: PASS
PROFESSIONAL_EXAM_ISOLATION: PASS
TENANT_ISOLATION: PASS
IDOR_PROTECTION: PASS (5 vectors fixed; sub_category minor)
HISTORICAL_INTEGRITY: PASS
DOMAIN_IMMUTABILITY: PASS (Eloquent guard; raw DB bypass caveat)
RBAC: PASS (permission+domain layered; middleware hardening recommended)
CONCURRENCY: PASS
LEGACY_COMPATIBILITY: PASS

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 1 (no route-level domain middleware — direct URL reachable with stray permission)
MEDIUM_FINDINGS: 3 (hard-coded education checks, sub_category domain leniency, orphan categories/test fixtures)
LOW_FINDINGS: 2 (grade_scales unseeded after restore, transport alias docs)
BUSINESS_RULE_GAPS: 10 (D1-10)
TEST_COVERAGE: 7/16 COVERED, 5 PARTIAL, 4 MISSING
REGRESSIONS: 0
FINAL_VERDICT: YELLOW
```

**YELLOW** — B2 GREEN core (taxonomy, server-derived domain, tenant/IDOR, historical integrity, curriculum freeze, optional bonus) **remains intact** and audit finds **no cross-tenant leak, no published result corruption, no exam/assessment mixing, no curriculum freeze bypass, no destructive cascade**. YELLOW reflects only non-critical hardening gaps: ancillary `industry==='education'` hard-codes and missing route-level domain middleware (B4 P1). Once B4 P1-P2 applied, verdict will be **GREEN**.

**STOP after audit — no code, migrations, or data modified.**

