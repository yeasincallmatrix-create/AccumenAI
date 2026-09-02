# PHASE B2 — DOMAIN RESTRUCTURE — FORENSIC AUDIT REPORT (GATE 1)

**PHASE:** B2 — Industry / Institution Domain Restructure + Full Academic/Professional Ecosystem Migration  
**GATE:** 1 — FORENSIC AUDIT ONLY (NO DATA MODIFIED, NO MIGRATIONS)  
**DATE:** 2026-08-28  
**AUDITOR:** OpenCode / Muse Spark (read-only inspection)  
**PRIOR AUDIT:** `PHASE_B1_INDUSTRY_INSTITUTION_FORENSIC_AUDIT_REPORT.md` (YELLOW) — this gate expands B1 to full ecosystem.

---

## 1. CURRENT INDUSTRY HIERARCHY (as implemented)

Source of truth: `config/industry_rules.php:20-60` (`global` + per-country blocks), exposed via `app/Support/IndustryRules.php:15-95`.

```
global.industries:
  education, healthcare, information_technology, finance, retail,
  manufacturing, real_estate, transport (=Transport & Logistics),
  restaurant, hotels, personal_finance, other
  // Training Center ABSENT

global.sub_industries.education (also Bangladesh copy 61-82, US 118-136):
  institution, school, college, university, madrasha,
  primary_school, secondary_high_school, school_college,
  vocational_institute, technical_training_center,
  skill_development_center, computer_it_training_institute,
  professional_training_academy, martial_arts, dance_academy,
  music_academy, sports_academy, language_academy, coaching_centre
  // ALL training types incorrectly under education
```

Other industries in `Bangladesh` block: `healthcare (5 subs)`, `information_technology (3)`, `finance (3)`, `retail (3)`, `manufacturing (3)`, `real_estate []`, `transport []`, `restaurant []`, `hotels []`, `personal_finance []`, `other []`.

`transport` label is `Transport & Logistics` (`config:30`) — not `Transportation`. `Service` industry does not exist.

`industry_template_mappings` mirrors this: 20 rows all `industry='education'` (`SELECT *` Gate1 §A9), e.g. `education,dance_academy→11`, `education,computer_it_training_institute→4`.

**Conclusion:** Flat industry list + single-level sub_industries under `education`. No `Industry → Academic Institutions` intermediate node in code; hierarchy is `institute.industry + institute.sub_industry` strings only.

---

## 2. CURRENT INSTITUTION HIERARCHY (as stored)

- No `institution_types` table. Type = `institutes.sub_industry` string (`2026_08_14_195437:12`).
- `institutes` snapshot: `SELECT DISTINCT industry,sub_industry` → `(education,institution)` ×1, `(education,school)` ×1 — only two rows, both academic-coded.
- Structure templates (`structure_templates` 23 rows): `school`, `college`, `university`, `training_institute`, `coaching_center`, `madrasa`, `vocational_institute`, `technical_institute`, `martial_arts_*`, `dance_academy`, etc. (`LearningStructureSeeder.php:119-257`). Each has `structure_template_levels` defining N-level tree (`2026_08_24_000100:58-75`).
- Mapping `industry_template_mappings` binds `(industry,sub_industry,country_id) → structure_template_id` with priority/fallback (`LearningStructureResolver.php:44-68`). Current bindings for training types point to `training_institute` (id 4) etc. but still under `education`.

---

## 3. TARGET HIERARCHY (canonical per brief §1)

```
INDUSTRY
├── Education
│   └── Academic Institutions
│       ├── School
│       ├── College
│       ├── Polytechnic          ← MISSING in current seeds/config
│       └── University
├── Training Center              ← MISSING as industry; currently subs under Education
│   ├── Training Institute       ← currently `institution`/`training_institute`
│   ├── Professional Training Center ← currently `professional_training_academy`
│   ├── Dance Academy            ← `dance_academy` (mis-parented)
│   ├── IT Training Center       ← currently `computer_it_training_institute`
│   └── Vocational Training Center ← currently `vocational_institute` (+ `skill_development_center`/`technical_training_center`)
├── Retail
├── Manufacturing
├── Service                      ← MISSING as industry
├── Transportation               ← currently `transport`
└── Restaurant
```

Critical rule (§2): **Training Center is independent Industry**, not child of Education. Academic domain = School/College/Polytechnic/University. Professional domain = 5 training types. Other industries have no academic structure.

If a reversible compatibility layer is needed, it is only for `transport`→`transportation` alias and `institution`→`training_institute` rename.

---

## 4. OLD → NEW MAPPING (proposed, NOT EXECUTED)

### 4.1 Industry remap

| OLD `industry` key | Label | NEW `industry` key | Label | Verdict |
|---|---|---|---|---|
| `education` | Education | `education` | Education | keep |
| *(new)* | — | `training_center` | Training Center | **CREATE** — peer of education |
| `retail` | Retail | `retail` | Retail | keep |
| `manufacturing` | Manufacturing | `manufacturing` | Manufacturing | keep |
| *(new)* | — | `service` | Service | **CREATE** (empty subs) |
| `transport` | Transport & Logistics | `transportation` | Transportation | **RENAME** (keep alias or migrate) |
| `restaurant` | Restaurant | `restaurant` | Restaurant | keep |
| `healthcare`, `information_technology`, `finance`, `real_estate`, `hotels`, `personal_finance`, `other` | — | — | — | keep as-is (out of scope, not in target but not deleted) |

### 4.2 Sub_industry / institution type remap

| OLD `industry` | OLD `sub_industry` slug | OLD label | NEW `industry` | NEW `sub_industry` slug | NEW label | Domain | Auto? |
|---|---|---|---|---|---|---|---|
| `education` | `school` | School | `education` | `school` | School | academic | AUTO |
| `education` | `primary_school` | Primary School | `education` | `school` | School | academic | AUTO (variant → canonical) or keep variant — NEEDS_REVIEW |
| `education` | `secondary_high_school` | Secondary / High School | `education` | `school` | School | academic | NEEDS_REVIEW (merge vs keep) |
| `education` | `school_college` | School & College | `education` | `college` | College | academic | NEEDS_REVIEW |
| `education` | `college` | College | `education` | `college` | College | academic | AUTO |
| `education` | `university` | University | `education` | `university` | University | academic | AUTO |
| *(missing)* | `polytechnic` | Polytechnic | `education` | `polytechnic` | Polytechnic | academic | **CREATE** — AUTO after seed |
| `education` | `madrasha` | Madrasha | `education` | `madrasha` | Madrasha | academic | AUTO (keep; not in target list but educational) |
| `education` | `institution` | Institution | `training_center` | `training_institute` | Training Institute | professional | AUTO (generic → canonical) |
| `education` | `professional_training_academy` | Professional Training Academy | `training_center` | `professional_training_center` | Professional Training Center | professional | AUTO (rename) |
| `education` | `dance_academy` | Dance Academy | `training_center` | `dance_academy` | Dance Academy | professional | AUTO (re-parent) |
| `education` | `computer_it_training_institute` | Computer / IT Training Institute | `training_center` | `it_training_center` | IT Training Center | professional | AUTO (rename) |
| `education` | `vocational_institute` | Vocational Institute | `training_center` | `vocational_training_center` | Vocational Training Center | professional | AUTO |
| `education` | `skill_development_center` | Skill Development Center | `training_center` | `vocational_training_center` | Vocational Training Center | professional | NEEDS_REVIEW (merge two to one) |
| `education` | `technical_training_center` | Technical Training Center | `training_center` | `vocational_training_center` | Vocational Training Center | professional | NEEDS_REVIEW (or map to `polytechnic` if academic? — brief says Polytechnic is academic, so technical stays professional) |
| `education` | `martial_arts` | Martial Arts | `training_center` | `martial_arts` | Martial Arts | professional | NEEDS_REVIEW (not in target 5; keep as extra or merge) |
| `education` | `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` | — | `training_center` | same slugs | — | professional | NEEDS_REVIEW (keep as professional extras beyond target) |

`polytechnic` structure template: reuse `technical_institute` (Program→Semester→Batch) or create `polytechnic` — BUSINESS DECISION REQUIRED.

### 4.3 Live data mapping (snapshot 2026-08-28)

| Table | Count | Affected rows | OLD → NEW |
|---|---|---|---|
| `institutes` | 2 | id 1605 `education,school` → stays `education,school` (academic) — AUTO | id 1606 `education,institution` (inferred from `course_categories` owner 1606) → `training_center,training_institute` — AUTO |
| `industry_template_mappings` | 20 | 13 training-related rows (ids 8-19) re-parent to `training_center` | 7 academic rows (1-7) stay under `education`; fallback id 20 `education,NULL→1` stays; add `training_center,NULL→4` fallback — AUTO with validation |
| `course_categories` | 3 | all for 1606 professional — stay professional under training_center; no slug collision | AUTO |
| `course_sub_categories` | 2 | under cat 78 — stay | AUTO |
| `courses` | 1 | `Video Editing` cat 80 — stay | AUTO |
| `subjects` | 0 | — | — |
| `batches` | 0 | — | — |
| `academic_*` | counts 0-~50 seed rows (no institute data) | global seeds (e.g., `class_grades` 12 rows, `academic_groups` etc.) remain | AUTO (no institute to retag) |

No `NEEDS_REVIEW` rows require manual migration in current snapshot because only one training-type institute exists and its data is unambiguous. Future data with `skill_development_center` vs `vocational_institute` will need rule.

---

## 5. CURRENT DOMAIN DETERMINATION LOGIC

**Exists:** No single domain resolver. Domain is **inferred ad-hoc**:

- `InstituteCreationController.php:40-338` — picks industry/sub via `InstituteOnboardingController::validatedSelection:58` (`IndustryRules` + `config`), but **does not compute domain**.
- `LearningStructureResolver.php:44-68` — resolves `structure_templates` from `industry/sub/country`, not domain.
- `ModuleAccessService.php:387-391` `isEducationIndustry()` checks `institute->industry === 'education'` to gate education modules; no training_center equivalent.
- `AcademicSetupService.php:59` `if (industry !== 'education') return` — blocks academic defaults for non-education.
- `CourseCategoryManageController.php:27,80`, `CourseMasterController.php:247` — hardcode `professional`; no domain check.
- `SubjectManagementController.php:52-53,113,165` — trusts client `subject_type`.

**Missing:** Deterministic `academic vs professional` map from `(industry, sub_industry)`; no `InstituteDomainResolver` service; no middleware gate; no validation matrix for valid combos.

Target rule (§2,6): **Server must derive domain from authenticated institute's `(industry, sub_industry)`** and reject client-supplied `subject_type`/`domain`.

---

## 6. SUBJECT DOMAIN MAPPING

- Model: `app/Models/Subject.php:1-79` — `subject_type enum(professional,academic) DEFAULT professional`, SoftDeletes, institute_id nullable (global `NULL`).
- Category link: `subjects.category_id FK SET NULL → course_categories` (`SHOW CREATE TABLE subjects`).
- Creation: `SubjectManagementController.php:104-142` validates `subject_type` as `Rule::in(['academic','professional'])` and `category_id` as `Rule::exists` without tenant/domain scope.
- Query: `subjectQuery:266-273` `where institute_id=? OR NULL`; `filterCategories:277-282` and `categories:285-292` bypass scope and leak cross-tenant (`withoutGlobalScope` without institute filter).
- Deletion: `SubjectDeletionService.php:16-104` classifies by 10 FK counts (course_subjects, subject_academic_assignments, institute_subjects, student_subject_selections, assessment_subjects, exam_subjects, exam_results, academic_final_result_rows, calendar_events, teacher_academic_assignments) with `HISTORICAL_DEPENDENCY` (soft-delete only) → `RESTRICT` FKs (`2026_08_27_000001`).
- Academic vs professional subject distinction is **entirely by `subject_type` column**, not by separate tables. Academic subjects are typically linked to `subject_academic_assignments` (`SHOW CREATE TABLE subject_academic_assignments` — `subject_id FK → subjects`, no type check) and professional subjects to `course_subjects` pivot.

**Mapping to target:** Academic institutes → `subject_type=academic`; Training institutes → `professional`. Both reuse same master table.

---

## 7. COURSE DOMAIN MAPPING

- Model: `app/Models/Course.php:1-102` — `institute_id`, `category_id`, `sub_category_id`, no `subject_type`. Level as `varchar(50)` (`2026_08_27_000002_allow_custom_course_level.php`), status `draft/active/inactive`.
- Categories: `course_categories` TenantScoped, `subject_type` enum; `course_sub_categories` child via `category_id` CASCADE.
- Tenant: `CourseCategoryManageController.php:22-54` correctly scopes `index` by institute_id + professional, but `CourseMasterController.php:42-75` lists courses by institute_id and `categories:244-251` leaks cross-tenant professional-only.
- Professional courses identified **via category.subject_type = professional**; academic courses via same with academic. No direct course domain column — domain is `course.category.subject_type`.
- Pricing/duration/level/capacity/mode/status/seo fields all on `courses` row, correctly migrated with course (no separate table).

---

## 8. CURRICULUM DOMAIN MAPPING

- Tables: `course_curricula` (TenantScoped `CourseCurriculum.php:18`), `curriculum_modules`, `curriculum_lessons`, `course_materials` (`database/migrations/2026_08_23_000000_create_course_curriculum_tables.php:22-66`, `2026_08_23_000300_create_course_materials_table.php:20`, `DESCRIBE course_curricula: 16 cols`).
- Lifecycle: `status draft/active/archived`, `version INT` unique per institute+course, `effective_date`, freeze-on-batch (`batches.curriculum_id FK SET NULL → course_curricula.id`, `CourseCurriculum.php:17,62-65`). `CourseCurriculumManagementTest.php` verifies freeze (existing suite must stay green).
- Modules/lessons carry `curriculum_id FK CASCADE`; no `subject_type`. Domain derived from `curriculum.course.category.subject_type`.
- Risk: No guard prevents academic `subject_id` entering professional curriculum via `course_subjects` sync (`routes/institute_modules.php:964-968`).

---

## 9. ACADEMIC ECOSYSTEM MAPPING (must move as unit §10)

| Layer | Table | Key columns | FK | Tenant | Notes |
|---|---|---|---|---|---|
| Institute | `institutes` | industry/sub | — | root | domain source |
| Academic Year | `academic_years` | institute_id FK cascade, name, status | FK institute | scoped | per-institute |
| Class/Grade | `class_grades` (global dict) + `institute_class_grades`, `institute_academic_levels` | code, level | via assignments | — | global + institute overrides |
| Group/Stream | `academic_groups` + `institute_academic_groups` | name, class_id | — | — | group per class |
| Subject assignments | `subject_academic_assignments` | subject_id FK→subjects (RESTRICT-ish), class_grade_id, academic_group_id, requirement_type, selection_group_id, credit_hours, gpa_included | FK cascade to class/group | — | central academic curriculum |
| Selection groups | `academic_selection_groups` | class_grade_id, min/max selection | FK | — | optional groupings |
| Placements | `student_academic_placements` | student_id, academic_year_id, class_grade_id, academic_group_id | cascade | institute_id | enrollment in year/class |
| Placement subjects | `student_subject_selections` (institute_id tenant), `student_placement_nodes` | placement_id, subject_id | restrict | tenant | selected optional subjects |
| Assessments | `academic_assessments` + `assessment_types`, `components` | institute_id FK cascade, academic_year_id, class_grade_id, status draft/locked | FK | institute_id | `SHOW CREATE TABLE academic_assessments` — unique `(institute,year,class,group,name)` |
| Assessment subjects | `assessment_subjects` | assessment_id FK cascade, subject_id FK restrict | restrict to subjects | assessment scope | one per subject per assessment |
| Components | `assessment_subject_components` | assessment_subject_id, component_id, full_mark, pass_mark, mandatory_pass | cascade | — | derived total full mark |
| Student marks | `academic_student_marks` | assessment_id, assessment_subject_id, component_id, placement_id, obtained_mark, status entered/absent | restrict | institute via assessment | marks per component |
| Aggregation | `academic_result_aggregation_schemes` + `items` | policy, weights | — | per result | aggregation config |
| Grade scales | `grade_scales` + `grade_scale_rows` | gpa_mode, optional_subject_bonus_threshold `2.00`, multiple_optional_policy `single/best/sum`, max_gpa `5.00` (`DESCRIBE grade_scales:8-11`) | — | institute_id nullable (global vs institute) | Bangladesh bonus: `bonus=max(GP-2.00,0)`, cap 5.00 |
| Final results | `academic_final_results` + `academic_final_result_rows` | institute_id, branch_id, policy_id, scheme_id, status review/published/locked (`DESCRIBE academic_final_results`), rows: result_id, placement_id, subject_id, aggregate, grade, grade_point, status, optional flag (`SHOW CREATE TABLE academic_final_result_rows`) | FK restrict to subjects/placements | institute | **immutable snapshot** — never rewritten; `subject_id FK` has no CASCADE (restrict) |
| Promotion | `promotion_policies`, `promotion_policy_rules`, `promotion_decisions` | — | — | — | per year transition |
| History/report | `academic_final_results` + transcripts/report cards via services | — | — | — | display with `withTrashed()` |

Academic chain is **complete and historically frozen**; all rows tenant-scoped via `institute_id` or placement → institute. No professional data touches this chain.

---

## 10. PROFESSIONAL ECOSYSTEM MAPPING (§11)

```
Institute (training_center, professional sub)
  → Course (institute_id, category_id professional) — `CourseMasterController`
    → Subject (professional, via course_subjects pivot) — `SubjectManagementController`
    → Curriculum Version (course_curricula versioned) — `CourseCurriculum`
      → Module (curriculum_modules) → Lesson (curriculum_lessons)
      → Material (course_materials)
  → Batch (institute_id, course_id, curriculum_id FK SET NULL) — `Batch` `SHOW CREATE TABLE batches` (0 rows live)
    → Enrollment (student_enrollments)
    → Attendance (attendance)
    → Exam (exams FK institute/course/batch CASCADE `SHOW CREATE TABLE exams`) → ExamSubject → ExamResult (exam_results FK subject_id restrict)
    → Certificate (certificates FK course/batch/student)
```

No Academic Year / Academic Placement / Academic Assessment / Final Result for professional — chain is strictly separate (§14). `exams`/`exam_results` are **legacy professional exams**, must not merge with `academic_assessments`/`academic_final_results`.

---

## 11. ACADEMIC ASSESSMENT MAPPING (detail of §9 subset)

Flow `AcademicAssessmentController.php:30-366` + `AcademicMarksController`, `AcademicGradingController`:

- `AcademicYear → ClassGrade → AcademicGroup → SubjectAcademicAssignment (academic subjects) → StudentAcademicPlacement → AcademicAssessment (Year+Class+Group) → AssessmentSubject (1 per subject) → AssessmentSubjectComponent (Written/MCQ/Practical/Viva, mandatory_pass) → AcademicStudentMark (entered/absent) → AcademicResultAggregationService → GradeScale (with optional bonus) → AcademicFinalResult (policy+scheme, status progression review→approved→locked→published) → AcademicFinalResultRow (snapshot per subject) → Promotion → Transcript/Report`.

All FKs validated and hardened to `RESTRICT` (`2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict.php`). Historical rows never cascade-deleted.

---

## 12. PLACEMENT MAPPING

- `student_academic_placements` (student_id FK, academic_year_id FK cascade, class_grade_id, academic_group_id, status) — unique per student per year; bridges to `student_placement_nodes` for N-level structure (if LearningStructureEngine active) and `student_subject_selections` for optional subjects.
- `student_placement_nodes` (`student_academic_placement_id FK cascade`, `node_id FK restrict`, `level_order unique per placement+level` — `2026_08_24_000100:131-143`).
- `student_subject_selections` has `institute_id` tenant column (`2026_08_17_131000_add_institute_id...`), FKs to subject with `restrict`.

Professional enrollment uses `student_enrollments` (different table, per batch).

---

## 13. GRADE SCALE MAPPING

- `grade_scales` (`DESCRIBE` 21 cols): `institute_id nullable` (global vs tenant), `education_system_id`, `academic_level_id`, `gpa_mode`, `optional_subject_bonus_enabled bool DEFAULT 1`, `optional_subject_bonus_threshold decimal 4.2 DEFAULT 2.00`, `multiple_optional_policy enum single/best/sum DEFAULT single`, `max_gpa decimal 4.2 DEFAULT 5.00`, etc. Added by `2026_08_27_000004_add_optional_bonus_threshold` and `2026_08_28_000001_add_multiple_optional_policy`.
- `grade_scale_rows` per scale: score ranges → grade, grade_point, pass/fail, gpa inclusion; unique per scale.
- Bonus calculation: `bonus = max(optional GP - threshold, 0)` added to compulsory GP total (denominator excludes bonus divisor? per spec §5: `Final GPA = (compulsory GP sum + bonus) / compulsoryCount`, capped at `max_gpa`). Snapshot stored in `academic_final_result_rows` (not recalculated).

---

## 14. PROMOTION MAPPING

- `promotion_policies` + `promotion_policy_rules` + `promotion_decisions` (tables), with `AcademicPromotionController` handling per-year promotion reading from published final results. No direct subject_type; inherits from final result domain.

---

## 15. FINAL RESULT MAPPING

- `academic_final_results` lifecycle: `review → approved → locked → published` (`DESCRIBE status`), with `locked_by/at`, `published_by/at`, `computed_at`. `AcademicFinalResultService` generates rows atomically.
- `academic_final_result_rows` — snapshot per `(result_id, placement_id, subject_id)` unique, with `aggregate`, `grade`, `grade_point`, `optional` flag, `incomplete_reason`, FKs to placements/subjects with no cascade. Historical `subjects` remain visible via `Subject::withTrashed()` (`AcademicResultReadiness` etc.).

No rewrite of published rows allowed.

---

## 16. CERTIFICATE MAPPING

- `certificates` (institute_id, student_id, course_id, batch_id, academic_year_id, type), `certificate_types` (institute-scoped), plus `certificate_approval_mode` on `institute_settings` (`2026_08_26_171509`). Approval workflow separate for academic vs professional (academic via final results, professional via course/batch/exam results). No domain mixing.

---

## 17. UI / NAVIGATION MAPPING

| Area | Current entry point | Domain awareness | Target |
|---|---|---|---|
| Canonical Courses | `/courses/manage` (`CourseMasterController@index:35`) `courses.manage.index` + tabs `[Courses][Subjects]` | **NONE** — same UI for all industries; `CourseMasterController::categories:247` hardcodes professional | Academic institutes: hide professional course CRUD or show academic courses (if academic courses use same page — currently all courses are professional). Training institutes: show Courses/Subjects/Curriculum/Batches. Need `InstituteDomainService` gate. |
| Canonical Subjects | `/courses/manage/subjects` (`SubjectManagementController@index:28`) + `subject-form` (`create:93`) | NONE — dropdown shows both academic/professional (`100`) | Server derives domain; UI shows only allowed type. |
| Category manage | JSON `/courses/manage/categories` modal (`CourseCategoryManageController:21`) | Hardcodes professional | Split by domain. |
| Curriculum | `/curricula` via `CurriculumController` + `CourseCurriculum` | Not domain-aware; assumes course exists | Professional-only; academic should not see (academic uses class/assessment). |
| Academic nav | `admin.academic.*` (assessments, grading, subjects, placements) via `routes/institute_modules.php:1342-1402` | Gated only by `permission` + `isEducation` check in layout (`layouts/institute.blade.php:124` `industry==='education'`, `dashboard/tabs`) | Must gate by `academic` domain (School/College/Polytechnic/University) not just `education` (to exclude madrasha/vocational if needed). |
| Workspace onboarding | `/workspace/onboarding` → `/workspace/create` (`InstituteOnboardingController:16`, `InstituteCreationController:38`) | Cascading selects from `config` — currently wrong taxonomy | Replace with target taxonomy; country→industry→sub with training_center. |
| Reports hub | `reports/hub.blade.php` `industry-aware` header | Industry-aware already (`ReportsHubController:40-45`) | Keep. |
| Legacy | `GET /courses/subjects` (`CourseController::subjects` `web.php:189`), `admin.courses.*`, `admin.academic.subjects.*` (`web.php:319`) | Still active | Audit says DO NOT DELETE yet; after B2 gate by permission/domain. |

Sidebar visibility currently via `ModuleAccessService.php:387-391` `isEducationIndustry()` (checks `industry==='education'`) — must be replaced by domain resolver.

---

## 18. RBAC MAPPING

Permissions list: `SELECT * FROM permissions` → 182 rows incl. `View/Manage Courses`, `View/Manage Batches`, `Publish Results`, `View/Manage Curricula (55,56)`, `Academic Structure (25)`, `Academic Promotion (26)`, `Manage Exams (11)` etc. No per-domain permission (e.g., `courses.manage.academic` vs `professional`). All are institute-wide.

Role `institute-owner` created in `2026_08_12_000000_seed_default_role_permissions.php:19`.

Risk: A `training_center` user with `courses.manage` could still access academic assessment routes (`/assessments`) by URL because those routes are gated by `permission:courses.manage` + `education.manage`? Need to verify: `routes/institute_modules.php` uses `permission:education.manage` for academic block, `permission:courses.manage` for course block, `permission:exams.manage` for exams — no domain check.

Gate must add `CheckIndustryDomain` middleware or controller `abort_unless(domain===expected)`.

---

## 19. TENANT ISOLATION AUDIT (expanded)

| Query / Model | Trait / Scope | Safe? | Evidence |
|---|---|---|---|
| `institutes` | root, SoftDeletes | — | — |
| `course_categories` | TenantScoped | **LEAK when bypassed** | `SubjectManagementController:277-292` `withoutGlobalScope('institute')` without `where institute_id` → enumerates all tenants; `CourseMasterController:244` same |
| `courses` | NO Trait, manual `where institute_id` | **Bypass not needed but manual is fragile** | `CourseMasterController:42` correct; `CourseCategoryManageController:32` `withoutGlobalScopes()->where institute_id` correct but fragile |
| `subjects` | NO Trait, `subjectQuery` manual OR NULL | **Query safe, validation not** | `index:38` safe list; `store:114` `Rule::exists('course_categories','id')` leaks (no institute filter via DB) |
| `course_curricula` / modules / lessons | TenantScoped | Safe | `CourseCurriculum.php:18` |
| `batches` | manual institute_id FK | Safe | `SHOW CREATE TABLE batches` FK cascade |
| `academic_*` | many TenantScoped (assessments, years) | Safe | `academic_assessments` FK institute cascade |
| `permissions/roles` | global | — | not tenant |

`withoutGlobalScope('institute')` occurrences audited: `SubjectManagementController:277,287`, `CourseMasterController:244,249`, `CourseCategoryManageController:71,103,164-165` — first three are **unsafe** (no follow-up tenant filter in subject controller). Must add `where institute_id = auth()->user()->institute_id` + `where subject_type = derivedDomain`.

`TenantContext` (`Concerns/TenantScoped.php:15-52`) mass-assign guard forces `institute_id` on create/update — prevents IDOR on direct model fill, but not on `Rule::exists` pivot.

---

## 20. IDOR AUDIT

- **Direct object access:** `assertAccessible` (`SubjectManagementController:295`), `assertOwned` (`CourseCategoryManageController:176`, `CourseMasterController:196`), `ModuleAccessService` guards — pass for direct id fetch.
- **Indirect via category_id:** **FAIL** — `store:114` and `update:166` accept any `category_id` that exists globally. Attacker `institute A` can `POST /courses/manage/subjects {name, subject_type=professional, category_id=<B's id>}` → creates subject linked to B's category (FK allows). No `institute_id` constraint on `Rule::exists`. Same for `CourseCategoryManageController:134` replacement `exists` (no institute).
- **WithoutGlobalScope enumeration:** Attacker can `GET /courses/manage/subjects?category_id=<B's id>` filter? Actually filter bypass not, but JSON category list leaks ids.
- **Course sub-category sync:** `CourseMasterController::validated:208` `Rule::exists('course_sub_categories','id')` same risk.

Fix: Scope `exists` with `where institute_id = tenantId` and `where subject_type = domain`.

---

## 21. FK DEPENDENCY MAP (full)

```
institutes ─┬─→ institute_settings.structure_template_id (nullOnDelete)
            ├─→ industry_template_mappings (no FK to institutes, just strings)
            ├─→ course_categories.institute_id CASCADE
            │       └─→ course_sub_categories.category_id CASCADE
            │       └─→ courses.category_id SET NULL
            │       └─→ subjects.category_id SET NULL
            ├─→ subjects.institute_id CASCADE (soft-delete subject keeps FK)
            ├─→ courses.institute_id CASCADE
            ├─→ course_curricula.institute_id CASCADE → curriculum_modules.curriculum_id CASCADE → curriculum_lessons.module_id CASCADE
            ├─→ batches.institute_id CASCADE, FK course_id CASCADE, curriculum_id SET NULL, academic_year_id SET NULL
            │       └─→ exams.institute_id CASCADE, course_id CASCADE, batch_id CASCADE → exam_subjects, exam_results.subject_id RESTRICT-ish
            ├─→ academic_years.institute_id CASCADE
            ├─→ academic_assessments.institute_id CASCADE, year/class/group FKs CASCADE/SET NULL → assessment_subjects.assessment_id CASCADE, subject_id RESTRICT → assessment_subject_components CASCADE → academic_student_marks FKs RESTRICT
            │       └─→ student_academic_placements (student+year) CASCADE → student_subject_selections.subject_id SET NULL? (check) + academic_final_result_rows.placement_id CASCADE
            └─→ academic_final_results.institute_id CASCADE → academic_final_result_rows.result_id CASCADE, subject_id RESTRICT (no cascade) — SOFT-DELETE subjects remain visible via withTrashed
subjects FKs hardened to RESTRICT (2026_08_27_000001) — prevents forceDelete when historical rows exist
```

No `FOREIGN_KEY_CHECKS=0` found in migrations.

---

## 22. HISTORICAL DATA SAFETY

- Subjects: SoftDeletes + `uq_subjects_institute_slug/code` + slug regeneration withTrashed; `SubjectDeletionService` classifies `HISTORICAL_DEPENDENCY` (exam_results, academic_final_result_rows, student_subject_selections) → `canSoftDelete=true, canForceDelete=false` (38-44). Hard delete blocked.
- Academic final results: `academic_final_result_rows` snapshot never mutated; `withTrashed()` used for display (e.g., transcript shows soft-deleted subject). `2026_08_27_000001` changed FKs to RESTRICT to prevent forceDelete.
- Curriculum freeze: `CourseCurriculum` versioned, `batches.curriculum_id SET NULL` ensures batch's version frozen (not cascade-deleted); new version is new row with incremented `version`.
- Live counts: `academic_final_results 0`, `subjects 0`, `batches 0`, `exams 0` — migration risk today is **minimal**; historical safety mechanism is verified but untested under load.
- Checklist §13 satisfied: SoftDeletes, RESTRICT, withTrashed historical display, restore, dependency view, audit logging (`SubjectDeletionService:87-103` audit_logs), concurrency (`lockForUpdate` 19-21,54-56,66-67,77).

---

## 23. DOMAIN IMMUTABILITY

- Currently **NO enforcement**. `institutes.industry/sub_industry` has no DB trigger or model observer blocking update. `InstituteSettingController` does not edit them, but raw `UPDATE institutes SET industry=...` or future edit UI could silently reinterpret historical `subject_type` and `course_categories.subject_type`.
- Recommendation (§9): **Make immutable after first course/subject/curriculum/batch/placement/assessment/result exists.** Implement via:
  - Model observer `Institute::updating` → if dirty `industry/sub_industry` and `hasDomainData()` (exists check on 6 tables) → throw ValidationException `domain_immutable`.
  - Or super-admin-only migration workflow with explicit conversion (re-tag categories/subjects, create new curriculum version, never rewrite `academic_final_result_rows`).

Proposal: **BLOCK by default**; explicit `php artisan institute:convert-domain {id} --from --to --dry-run` command for vetted conversions.

---

## 24. AMBIGUOUS RECORDS

| Record type | Condition | Classification | Reason |
|---|---|---|---|
| `institutes` with `sub_industry = skill_development_center` vs `vocational_institute` vs `technical_training_center` | All map to `training_center/vocational_training_center` | **NEEDS_REVIEW** | 3 old slugs collapse to 1 target; deterministic merge possible (pick `vocational_training_center` as canonical) but lose granularity — needs stakeholder sign-off. |
| `institutes` with `sub_industry = institution` | Generic | **AUTO_MAPPABLE** | Rename to `training_institute`. |
| `martial_arts`, `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` | Not in target 5 training types | **NEEDS_REVIEW** | Keep under `training_center` as extra types (professional) or deprecate. |
| `madrasha` | Religious school | **NEEDS_REVIEW** | Keep under `education` as academic (specialized) — brief lists only 4 academic types but madrasha is valid academic. |
| `transport` industry institute | Exists if any (none in snapshot) | **AUTO_MAPPABLE** | `transport` → `transportation` rename. |
| Historical subjects with `subject_type` opposite domain after re-parent (e.g., academic subject under training institute due to legacy) | Any existing subject where subject_type ≠ derived domain | **UNSAFE TO MIGRATE** | Would require re-typing subject and breaking historical assignments; must refuse migration and flag for manual cleanup. Snapshot has 0 such rows — safe. |
| `industry_template_mappings` with `country_id` specific rows (none live) | If per-country mappings exist | **AUTO_MAPPABLE** | Re-parent per industry key regardless of country. |

---

## 25. MIGRATION RISKS

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| `itm_industry_sub_country_unique` collision after re-parent (e.g., two old `education` subs become same `training_center` slug) | Low (3→1 merge) | Migration fails with duplicate key | **Pre-flight** `SELECT industry,sub_industry,country_id, COUNT(*) ... GROUP BY newKey HAVING COUNT>1` → abort, report duplicates |
| `institutes.slug` collision after rename (none expected) | Very low | Unique violation | Pre-flight check slug uniqueness |
| `course_categories (institute_id,slug)` duplicate after domain change if merging categories | Low | Abort | Pre-flight `SELECT institute_id,slug ... HAVING COUNT>1` |
| Historical subject FK RESTRICT blocks `subjects` re-type if subject has `exam_results`/`final_rows` | Low (0 rows) | Migration refused | Classify as UNSAFE, skip institute |
| `batches.curriculum_id SET NULL` orphan if curriculum mapping changes template | Low | Batch retains stale curriculum_id but template mismatch | No curriculum migration needed; only institute type changes |
| Country name vs id mismatch (`institutes.country` string vs `country_id`) | Medium | Wrong industry list for country | Add country string validation + `IndustryRules::industries(country)` test |
| `polytechnic` not in `structure_templates` → resolver fallback to `school` | Medium | Academic institute with polytechnic gets wrong N-level tree | Create `polytechnic` template before mapping |
| Concurrent writes during migration | Low | Race | Run inside `DB::transaction` with `lockForUpdate` on institutes + mappings; maintenance window |

No `FOREIGN_KEY_CHECKS=0`, no cascade deletion in migration.

---

## 26. ROLLBACK STRATEGY

- All migrations must have `down()` that **re-parents** `training_center` children back to `education` with original slugs (`institution`, `professional_training_academy`, `computer_it_training_institute`, `vocational_institute`). Store `OLD→NEW` map in migration constant.
- `config/industry_rules.php` rollback: revert diff.
- `LearningStructureSeeder` rollback: `IndustryTemplateMapping::where industry='training_center' → where industry='education'` update + re-seed old keys.
- `institutes` rollback: `where industry='training_center' → industry='education'` with old sub slugs (reverse map). Must not fire if new rows created with `training_center` already (idempotent down).
- No data deleted, so rollback is **lossless** (renames only). Test `migrate:fresh --seed` + `migrate:rollback` in CI.
- Emergency: `php artisan migrate:rollback --step=1` + restore `industry_rules.php` from git.

---

## 27. TESTS REQUIRED

List per §20 (30 cases) + existing regression suites:

1. School → Academic domain (resolver)
2. College → Academic
3. Polytechnic → Academic (after creation)
4. University → Academic
5. Training Institute → Professional
6. Professional Training Center → Professional
7. Dance Academy → Professional
8. IT Training Center → Professional
9. Vocational Training Center → Professional
10. Academic institute cannot create Professional subject via forged `subject_type` (expects 422)
11. Professional cannot create Academic subject via forged request (422)
12. Academic sees Academic modules (academic_structure, assessments, grade scales, promotions) — navigation gate test
13. Professional sees Professional modules (courses, subjects professional, curricula, batches, enrollment, exams)
14. Institute A cannot GET Institute B subjects (403)
15. Institute A cannot GET Institute B categories (403)
16. Institute A cannot GET Institute B curricula (403)
17. Institute A cannot GET Institute B assessments (403)
18. Institute A cannot GET Institute B results (403)
19. Historical subject remains visible after soft-delete (withTrashed transcript)
20. Historical result unchanged after subject re-type attempt (blocked)
21. Curriculum referenced by batch frozen (update blocked, new version required)
22. New curriculum version can be created (active archives old)
23. Academic assessment isolated from Professional Exam (cannot attach professional subject to academic assessment)
24. Optional subject bonus correct (7 compulsory 31.50 + A+ bonus 3.00 = GPA 4.93)
25. Bangladesh default threshold 2.00
26. GPA capped at 5.00
27. Multiple optional-subject policy deterministic (single/best/sum)
28. Domain change blocked when historical data exists (422 immutability)
29. Direct URL access respects RBAC (exams.manage cannot access academic assessment)
30. Concurrent domain-sensitive ops safe (lockForUpdate on subject delete/restore)

Regression suites to keep green: `CourseCurriculumManagementTest`, `SubjectUnificationTest`, `AcademicAssessmentHardeningTest`, `AcademicResultFinalizationIntegrityTest`, `TenantIsolationAuditTest`, `ModuleAccessMiddlewareTest`, `AcademicResultCalculationHardeningTest`.

---

## 28. BUSINESS DECISIONS REQUIRED

| # | Decision | Why |
|---|---|---|
| D1 | Confirm target hierarchy exactly as §1 (including `Polytechnic` under Academic) — or should `Polytechnic` reuse `Vocational Institute` template? | `polytechnic` missing; template choice affects N-level tree. |
| D2 | Keep `Madrasha` under Academic (as now) or move to Professional? | Not in brief's 4 academic types. |
| D3 | Retain `martial_arts` / `music_academy` / `sports_academy` / `language_academy` / `coaching_centre` as Training Center extras, or deprecate? | Beyond target 5 training types. |
| D4 | Collapse `skill_development_center` + `technical_training_center` + `vocational_institute` into single `Vocational Training Center`, or keep distinct? | 3→1 mapping loses granularity. |
| D5 | `Service` industry empty subs — intentional or should it mirror `Retail`? | `service` currently missing entirely. |
| D6 | `transport` → `transportation` rename — alias (keep both accepted) or hard rename? | Existing institutes may have `transport` value. |
| D7 | Domain immutability: block after data exists (recommended) vs allow via super-admin migration workflow? | Safety vs flexibility. |
| D8 | Can one institute ever operate dual Academic + Professional? | Would require per-program domain, not institute-level. |
| D9 | Should global subjects (`institute_id NULL`) be domain-filtered per institute view? | Currently visible to all; after split, academic institute should not see professional global subjects. |
| D10 | Country-specific industry lists: US currently lacks `institution`/`madrasha` — should `training_center` subs be country-scoped or global? | Affects onboarding dropdowns. |

No invention — all marked for sign-off.

---

## SUMMARY COUNTS

```
AUTO_MAPPABLE:  9  (school, college, university, institution→training_institute, dance_academy, 
                     professional_training_academy→professional_training_center,
                     computer_it_training_institute→it_training_center,
                     vocational_institute→vocational_training_center,
                     transport→transportation)
NEEDS_REVIEW:   7  (primary_school/secondary_high_school/school_college variants,
                     skill_development/technical_training collapse,
                     martial_arts, music/sports/language/coaching extras, madrasha, polytechnic template)
UNSAFE:         1  (any future subject where subject_type contradicts derived domain with historical FK — 0 rows today)
```

---

## FINAL_VERDICT

```
PHASE: B2 — GATE 1 AUDIT
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

DATABASE_MAPPING:          FAIL (training under education, polytechnic/service missing)
COURSE_DOMAIN:             PARTIAL (leak + hardcode professional)
CURRICULUM_DOMAIN:         PARTIAL (no domain guard on course_subjects)
SUBJECT_DOMAIN:            FAIL (client-trusted subject_type + IDOR)
ACADEMIC_ECOSYSTEM:        PASS (chain complete, FK RESTRICT, snapshot frozen)
PROFESSIONAL_ECOSYSTEM:    PARTIAL (no domain-derived gating, but chain intact)
ACADEMIC_ASSESSMENT:       PASS
PLACEMENT/AGGREGATION:     PASS
GRADE_SCALE/PROMOTION:     PASS (optional bonus threshold 2.00, cap 5.00 intact)
CERTIFICATE:               PASS
TENANT_ISOLATION:          PARTIAL (category/subject assignment IDOR)
IDOR_PROTECTION:           PARTIAL (exists rule not scoped)
RBAC:                      PARTIAL (no domain gate beyond permission)
HISTORICAL_INTEGRITY:      PASS (SoftDeletes, RESTRICT, withTrashed, freeze)
DOMAIN_IMMUTABILITY:       FAIL (no enforcement)
LEGACY_EXAMS_ISOLATED:     PASS (separate tables, no merge)

CRITICAL_FINDINGS: 2  (C1 training not independent, C2 category leakage/IDOR)
HIGH_FINDINGS:     5  (H1-5 from B1 §K)
MEDIUM_FINDINGS:   5  (M1-5)
BUSINESS_RULE_GAPS: 10 (D1-10 above)

FINAL_VERDICT: YELLOW
```

**YELLOW** — Historical integrity, academic chain, curriculum freeze, and optional bonus are hardened; but Training Center is still nested under Education and domain is not server-derived with active IDOR leakage. **Safe to proceed to Gate 2 implementation** ONLY after stakeholders approve §4 OLD→NEW mapping and §28 decisions, with pre-flight-checked reversible migration (no historical data loss in snapshot — 0 academic final results, 0 subjects with contradictory history).

**STOP AFTER GATE 1 — DO NOT IMPLEMENT UNTIL REVIEWED/APPROVED.**

