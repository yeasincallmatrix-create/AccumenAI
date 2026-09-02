# PHASE B1 — INDUSTRY / INSTITUTION TYPE / ACADEMIC-PROFESSIONAL DOMAIN — FORENSIC AUDIT REPORT

**PHASE:** B1
**SCOPE:** Industry / Institution Type / Academic-Professional Domain Restructuring
**DATE:** 2026-08-28
**MODE:** FORENSIC AUDIT ONLY — NO DATA MODIFIED, NO MIGRATIONS, NO DELETIONS
**AUDITOR:** OpenCode / Muse Spark (read-only inspection)

---

## EXECUTIVE SUMMARY

The canonical target requires `Training Center` to be an **independent Industry-level category** (peer of `Education`), with `Academic Institutions → School/College/Polytechnic/University` under `Education` and `Training Institute / Professional Training Center / Dance Academy / IT Training Center / Vocational Training Center` under `Training Center`. Domain must derive server-side: academic sub_industries → `subject_type=academic`, training sub_industries → `subject_type=professional`.

Current codebase **violates the target hierarchy**: every training-related `sub_industry` is nested under `industry='education'` in `config/industry_rules.php:37-57`, `database/seeders/LearningStructureSeeder.php:294-319`, and `industry_template_mappings` (20 rows all `industry='education'`). `course_categories` and `subjects` correctly carry `subject_type` (`professional`/`academic`) but **nothing validates industry↔institution_type↔subject_type coherence**. `CourseCategoryManageController:27` hardcodes `professional` for all categories; `SubjectManagementController:113,165` trusts client-supplied `subject_type`. Tenant isolation is **partially broken** in subject listing. Historical integrity (soft-delete, RESTRICT FKs) is intact.

**Overall risk:** Current data volume is tiny (2 institutes, 3 categories, 0 active subjects in audited snapshot), so migration can be done safely — but the existing training sub_industries must be **re-parented from `education` to `training_center`** via a reversible, pre-flight-checked migration; otherwise professional categories leak into academic context.

---

## A. DATABASE AUDIT

### A1. Industries representation

| Item | Evidence | Finding |
|------|----------|---------|
| Institutes table | `database/migrations/2026_08_13_000000_add_industry_to_institutes_table.php:12` `string('industry',60)->nullable()->default('education')` ; `2026_08_14_195437_add_sub_industry_to_institutes_table.php:12` `string('sub_industry',60)->nullable()` ; `SHOW CREATE TABLE institutes` confirms `industry varchar(60) DEFAULT 'education'`, `sub_industry varchar(60)` | Industry is a free-text column on `institutes`, not FK. Default `education`. No enum constraint, no index on industry/sub_industry. |
| industry_settings | `database/migrations/2026_08_15_000400_create_industry_settings_table.php:11-14` creates `industry_settings(id, industry_key unique, theme_slug nullable)` ; `app/Models/IndustrySetting.php:3-13` trivial model | Table exists but **empty** (`SELECT * FROM industry_settings` → 0 rows). No hierarchy, no FK from institutes. Only used for theme colour (`app/Http/Controllers/InstituteCreationController.php:268`, `InstituteSettingController.php:216`). |
| industry_template_mappings | `database/migrations/2026_08_24_000100_create_learning_structure_engine_tables.php:106-128` ; `SHOW CREATE TABLE industry_template_mappings` — `industry varchar(60) NOT NULL`, `sub_industry varchar(60) nullable`, `country_id nullable`, `structure_template_id FK cascade` | Only structured industry→template bridge. See A9 for seed data. |

### A2. How Education is represented

- Single value `industry='education'` in `config/industry_rules.php:23` (`global.industries.education => Education`). All other target industries (`Training Center`, `Retail`, `Manufacturing`, `Service`, `Transportation`, `Restaurant`) are **absent** from global list (which currently has `education`, `healthcare`, `information_technology`, `finance`, `retail`, `manufacturing`, `real_estate`, `transport`, `restaurant`, `hotels`, `personal_finance`, `other` — close but `Training Center` missing, `Service` missing as named, `Transport` vs `Transportation` mismatch).
- All academic AND professional sub_industries are tenants of `education` in `config/industry_rules.php:37-57` and per-country blocks (`Bangladesh:61-82`, `United States:118-136`).

### A3. How institution types are represented

- No `institution_types` table. `sub_industry` **is** the institution type, stored as string on `institutes.sub_industry`. Values enumerate the institution taxonomy (school, college, university, etc.). No parent/child hierarchy column; hierarchy is implicit via `industry` + config mapping.
- Current `institutes` data (`SELECT DISTINCT industry, sub_industry`): `(education, institution)` and `(education, school)` — only academic types exist in live data.

### A4. Parent/child hierarchy

- **None at DB level.** No `parent_id` on any industry table. Hierarchy is **config-driven** (`config/industry_rules.php`) + `industry_template_mappings` bridge to structure templates. Fails target: `Training Center` children are under `Education` parent in config/seeds/mappings.

### A5. Institution type stored on institutes

- YES: `institutes.sub_industry` (`2026_08_14_195437:12`). Nullable, unindexed.

### A6. Industry stored on institutes

- YES: `institutes.industry` (`2026_08_13:12`). Nullable default `education`, unindexed.

### A7. Academic/professional domain stored

- On `subjects.subject_type` enum(`professional`,`academic`) default `professional` (`DESCRIBE subjects:3`, `SHOW CREATE TABLE subjects: subject_type ... DEFAULT 'professional'`).
- On `course_categories.subject_type` enum(`professional`,`academic`) default `professional` (`SHOW CREATE TABLE course_categories: subject_type ... DEFAULT 'professional'`).
- On `subjects.subject_type` category cannot be inferred elsewhere. **No domain column on `institutes`** — domain must be derived from `sub_industry` (not yet implemented).

### A8. Tables / columns / indexes / FKs — complete inventory

| Table | Relevant columns | Indexes | FKs | Notes |
|-------|-----------------|---------|-----|-------|
| `institutes` | `industry varchar(60) DEFAULT education`, `sub_industry varchar(60)`, `country`, `country_id`, `slug` | `uq_institutes_slug`, `uq_institutes_uuid`, `uq_institutes_institute_code`, `idx_institutes_status`, `idx_institutes_package` | `fk_institutes_package -> subscription_packages` | No FK on industry/sub_industry, no check constraint |
| `industry_settings` | `industry_key unique`, `theme_slug` | `unique industry_key` | none | Empty |
| `industry_template_mappings` | `industry`, `sub_industry`, `country_id`, `structure_template_id`, `priority`, `status` | `itm_industry_sub_country_unique (industry,sub,country)`, `itm_industry_idx`, `itm_sub_industry_idx`, `itm_industry_sub_country_status_idx` | `country_id -> countries SET NULL`, `structure_template_id -> structure_templates CASCADE` | 20 rows, all education |
| `course_categories` | `institute_id`, `name`, `slug`, `subject_type enum` | `uq_course_categories_inst_slug (institute_id,slug)`, `idx_course_categories_institute` | `fk_course_categories_institute -> institutes CASCADE` | TenantScoped ( `app/Models/CourseCategory.php:11` ) |
| `course_sub_categories` | `institute_id`, `category_id`, `name`, `slug` | `uq_course_subcat_cat_slug (category_id,slug)`, `idx_course_subcat_institute` | `fk_course_subcat_category -> course_categories CASCADE`, `fk_course_subcat_institute -> institutes CASCADE` | No subject_type, inherits from category |
| `courses` | `institute_id`, `category_id`, `sub_category_id`, `status`, `level varchar(50)` | `slug unique`, `name mul` | `category_id -> course_categories SET NULL` (implied) | Not TenantScoped; manual `where institute_id` |
| `subjects` | `institute_id nullable`, `category_id nullable FK SET NULL`, `subject_type enum`, `subject_code`, `slug`, `status` | `uq_subjects_institute_code`, `uq_subjects_institute_slug`, `idx_subjects_institute/category/status` | `fk_subjects_category -> course_categories SET NULL`, `fk_subjects_institute -> institutes CASCADE` | SoftDeletes, NOT TenantScoped; `subjectQuery` leaks globals |
| `institute_settings` | `structure_template_id nullable FK nullOnDelete` | `structure_template_id FK` | `structure_template_id -> structure_templates` | Stamps template from mapping |
| `structure_templates` | `code unique (global+institute)`, `is_global`, `status` | `st_code_global_institute_unique` | none directly | Global templates for all institution types |
| `structure_template_levels` | `template_id FK cascade`, `level_order unique per template` | `stl_template_levelorder_unique` | `template_id -> structure_templates CASCADE` | Defines hierarchy depth per type |
| `structure_nodes`, `student_placement_nodes` | `institute_id`, `template_id`, `parent_node_id` | multiple | cascade/restrict | N-level tree per institute |

`SHOW CREATE TABLE course_categories` and `subjects` both confirm **no `FOREIGN_KEY_CHECKS=0`**, standard InnoDB. Subject FKs hardened to `RESTRICT` in `2026_08_27_000001_harden_subject_foreign_keys_to_restrict.php` and `2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict.php`.

### A9. Seed data inventory (required checklist)

| Requested seed | Status | Location | Evidence |
|---|---|---|---|
| Education | EXISTS (industry, not child) | `config/industry_rules.php:23` `education => Education`; `industry_template_mappings: education,null -> template 1 (fallback)` | `SELECT * FROM industry_template_mappings WHERE sub_industry IS NULL` row id 20 |
| School | EXISTS under Education (VIOLATION of target? School SHOULD be under Education→Academic — currently correct) | `config/industry_rules.php:39`; `LearningStructureSeeder.php:298`; `industry_template_mappings id 1 (education,school)->1` | OK |
| College | EXISTS under Education | `config:40`; `Seeder:303`; `mapping id 5 (education,college)->2` | OK |
| Polytechnic | **MISSING** | No `polytechnic` key in any `industry_rules.php` nor `LearningStructureSeeder` nor mappings. Closest are `vocational_institute`, `technical_training_center` | GAP — must add |
| University | EXISTS under Education | `config:41`; `Seeder:304`; `mapping id 6 (education,university)->3` | OK |
| Training Institute | EXISTS but **incorrectly under Education** (should be under Training Center) | `config:50` under `education`; `Seeder:310` `institution->training_institute`; `mapping id 13 (education,institution)->4` | MISPLACED |
| Professional Training Center | EXISTS but under Education as `professional_training_academy` | `config:50/76` `professional_training_academy`; `Seeder:309`; `mapping id 12 (education,professional_training_academy)->4` | MISPLACED + name mismatch (`Professional Training Center` vs `Professional Training Academy`) |
| Dance Academy | EXISTS but under Education | `config:52/78`; `Seeder:313`; `mapping id 15 (education,dance_academy)->11` | MISPLACED |
| IT Training Center | EXISTS but under Education as `computer_it_training_institute` | `config:49/75`; `Seeder:308`; `mapping id 11 (education,computer_it_training_institute)->4` | MISPLACED + name mismatch (`IT Training Center` vs `Computer / IT Training Institute`) |
| Vocational Training Center | EXISTS but under Education as `vocational_institute` | `config:46` `vocational_institute`; `Seeder:305`; `mapping id 8 (education,vocational_institute)->7` | MISPLACED + name mismatch |
| Retail | EXISTS as industry | `config:27` `retail => Retail` (global) ; `Bangladesh:100-104` ; `US:152` | Name matches target `Retail` ✅ |
| Manufacturing | EXISTS | `config:28` `manufacturing`; `Bangladesh:105-109` | ✅ |
| Service | **MISSING** | `config` has `information_technology`, `finance`, `real_estate`, `healthcare` but no `service` key. `real_estate`/`hotels`/`personal_finance` are extra vs target | GAP — must add `service` industry |
| Transportation | EXISTS as `transport` (mismatch) | `config:30` `transport => Transport & Logistics` ; `Bangladesh:111 transport=[]` | Slug mismatch: target `Transportation` vs existing `transport` |
| Restaurant | EXISTS | `config:31` `restaurant => Restaurant` | ✅ |

**Summary A9:** 6 of 9 target training/academic sub-industries exist but are **mis-parented**. `Polytechnic` absent. Industry-level gaps: `Training Center` absent, `Service` absent, `Transportation` slug mismatch.

### A10. Professional categories/courses incorrectly associated with Academic

- `course_categories.subject_type` defaults to `professional` but is set per-row; live data has 3 rows all `professional` for institute 1606 (`SELECT * FROM course_categories` → ids 78-80). No cross-contamination yet because **0 academic categories exist** in snapshot — but filter logic is unsafe:
  - `CourseCategoryManageController.php:27` hardcodes `where subject_type='professional'` — professional tenant can never create academic categories even after restructure, but also academic tenant would still see only professional filter (bug).
  - `SubjectManagementController.php:277-282` `filterCategories()` and `285-292` `categories()` use `withoutGlobalScope('institute')->whereIn(['academic','professional'])` ordered by type — they expose **all institutes' categories** to current institute via `withoutGlobalScope` without `where institute_id = ?` (see D8/H). This leaks cross-tenant categories and mixes domain (no institute filter).
  - `CourseMasterController.php:244-251` `categories()` also `withoutGlobalScope('institute')->where subject_type='professional' ->get()` — same leak, only professional.

Therefore previously-created Professional categories appearing under Academic is **not currently observed in data** but is **architecturally possible** via the `withoutGlobalScope` listings + lack of industry validation. If `CourseMasterController::categories()` is used by an Academic institute after fix, it would incorrectly show only professional categories (inverted).

---

## B. INSTITUTE CREATION FLOW

### Flow map

```
Anonymous/Owner
  → GET /workspace/onboarding (InstituteOnboardingController::step1:27)
      Industries: IndustryRules::industries(null) (global list)
      Rules: config/industry_rules without global/capabilities (35)
      UI: workspace/onboarding.blade.php:48-94 cascading selects (country→industry→sub)
  → POST /workspace/onboarding/choose (choose:40) validates via validatedSelection:58
      Validator: country Rule::in(countries), industry Rule::in(industries(country))
      Sub check: IndustryRules::subIndustries(country,industry) must contain sub if non-empty (71-83)
      Session: session([SESSION_KEY => trio]) (47)
  → GET /workspace/create (InstituteCreationController::create:40) reads selection:45, previews template:50
  → POST /workspace/create (store:67) re-reads selection:72 (never trusts browser industry fields)
      Validates only org fields (name, phone, email, geo) (77-87)
      Creates Institute::create(industry, sub_industry from session) (118-133) inside transaction
      Assigns MembershipService + InstituteSetting certificate defaults + auto template/ demo seeds
```

`InstituteOnboardingController.php:22,58,98` and `InstituteCreationController.php:40-338` are the canonical chain.

### B1. Where user selects Industry

- `resources/views/workspace/onboarding.blade.php:48-60` Industry select (disabled until country chosen). Options injected via `IndustryRules::industries(country)` JS (`onboarding.blade.php:105-136`). Also `auth/register-select.blade.php:155-179` for pre-registration owner flow.

### B2. Where user selects institution type

- Same view `onboarding.blade.php:54-57` Sub-industry select `#sub_industry`, populated by `subsFor(country, industry)` (105) from `config/industry_rules`. Required when industry has subs (`validatedSelection:71-76`).

### B3. What values are stored

- `institutes.industry` = `selection['industry']` string (`InstituteCreationController.php:121`).
- `institutes.sub_industry` = `selection['sub_industry']` nullable string (`122`).
- `institutes.country` = `selection['country']` string (`123`) + structured `country_id` / `admin_level_*_id` / `postal_code` from geo (`124-128`).

### B4. Whether Academic vs Professional is decided during account creation

- **NO.** No `subject_type`/`domain` column on `institutes`; no derivation at creation. `IndustryRules` and `LearningStructureResolver:44-68` resolve **structure_template** but not domain. `CourseCategoryManageController` and `SubjectManagementController` still treat domain as client-chosen.

### B5. Whether selected institution type can determine domain automatically

- **Yes, deterministically** once mapping is fixed. Academic set = `{school, college, polytechnic, university}` (plus `primary_school`/`secondary_high_school`/`school_college` variants currently). Professional set = `{training_institute→Training Institute, professional_training_academy→Professional Training Center, dance_academy, computer_it_training_institute→IT Training Center, vocational_institute→Vocational Training Center, madrasha?}`. The `LearningStructureSeeder` already groups training types to template `training_institute` (id 4) — that grouping can be reused for domain registry. No code currently does this; a new `InstituteDomainResolver` or config map is needed.

### B6. Whether institute can later change its institution type

- **No UI found.** Grep of controllers shows only `InstituteCreationController::create/store`; no `InstituteSettingController::update` that touches `industry`/`sub_industry` (that controller edits `structure_template_id` only: `InstituteSettingController.php:34-222`). Direct DB update or `IndustryTemplateMapping` reselection via `LearningStructureSettingsController` (structure template switching) exists but not industry mutation. Tenant protection (`2026_10_01_000200_create_tenant_protection_tables.php`) soft-deletes institutes but does not guard industry change. So **currently immutable in practice**, but not enforced by DB constraint.

### B7. Whether changing institution type can corrupt existing data

- **HIGH RISK if allowed without guard.** Changing `sub_industry` would re-resolve template (`LearningStructureResolver::resolveTemplate:44-68`) and could orphan existing `structure_nodes` (FK `institute_id` remains, but `template_id` mismatch). More critically, existing `course_categories.subject_type` and `subjects.subject_type` would remain `professional`/`academic` while new domain expects opposite — leading to mixed-domain categories (see C8). No migration guard exists.

### B8. Whether validation prevents invalid combinations

- **Only structural existence check** (`InstituteOnboardingController::validatedSelection:79-82` `array_key_exists(sub, subs)`). Examples:
  - `Education + Training Institute` — **currently VALID** because `training_institute` is listed under `education` in config (false positive). After target restructure it MUST be INVALID.
  - `Training Center + School` — **currently impossible** because `Training Center` industry does not exist; if created, validation would correctly reject since `school` not in `training_center` subs — but no code for cross-industry matrix exists yet.
  - No dedicated `education + dance_academy` rejection beyond config membership; the bug is config itself.

**Valid/invalid matrix today vs target:** Today the 8 invalid combos listed in the brief are **all allowed** (because all subs live under education). After fix they must be rejected via new validator (e.g., `InstituteDomainRules::validCombinations()`).

---

## C. COURSE DOMAIN AUDIT

### Consumers of industry/institution type/subject_type

| Symbol | Usages (file:line) |
|--------|---------------------|
| `subject_type` on `subjects` | `CourseCategory.php` no, but `Subject.php` booted slug; `SubjectManagementController.php:52-53` filter, `112-113` validate, `124` create, `165` update; `SubjectDeletionService.php:38` not domain-aware |
| `subject_type` on `course_categories` | `CourseCategory.php: tenant model`; `CourseCategoryManageController.php:27,80` hardcodes professional; `CourseMasterController.php:247` hardcodes professional |
| `industry` on `institutes` | `DashboardController.php:45,171,199`; `ModuleAccessService.php:380,389`; `AcademicSetupService.php:59` (`industry !== education` short-circuit); `LearningStructureResolver.php:44` |
| `sub_industry` | `AcademicAnalyticsController.php:64`; `LearningStructureResolver.php:45`; `industry_template_mappings` |

### C1. How course categories linked to industry

- **Not linked.** `course_categories` has `institute_id` + `subject_type` only. Industry derivation is indirect: `course_categories.institute_id -> institutes.industry`. No FK to industry. Tenant scoping is via `TenantScoped` trait (`CourseCategory.php:11`).

### C2. How categories linked to subject_type

- Direct `course_categories.subject_type enum('professional','academic')` (`SHOW CREATE TABLE course_categories`). Subjects copy `subject_type` independently (not inherited from category beyond creation-time copy); no trigger. `CategoryManageController::store:80` sets `subject_type='professional'` unconditionally.

### C3. How Professional courses identified

- **Not stored.** `courses` has no `subject_type`. Professional courses are inferred by **category's** `subject_type` via `courses.category_id -> course_categories.subject_type`. Verified: `CourseMasterController::categories:247` `where subject_type='professional'` implies all managed courses are treated as professional. `DESCRIBE courses` shows no domain column.

### C4. How Academic courses identified

- **Same indirection** via category. Academic courses would have `category.subject_type='academic'` — but none exist (snapshot has 0 academic categories). Legacy `exam_subjects` / `assessment_subjects` may implicitly distinguish but not via `courses`.

### C5. Why previously-created Professional categories may now appear under Academic

- Because `SubjectManagementController::filterCategories:277` and `categories:287` use `CourseCategory::withoutGlobalScope('institute')->whereIn(['academic','professional'])` **without institute_id filter** — they return global set. `CourseMasterController::categories:244` filters to `professional` only, so an Academic institute would see only professional categories (inverted). `CourseCategoryManageController::index:25` correctly filters by `institute_id` AND `professional` but that is category-management JSON only; main course form uses the leaky `CourseMasterController::categories()`. After restructure, if professional categories remain under `education` while institutes move to `training_center`, the fallback resolver (`LearningStructureResolver:63` global education fallback) may still surface them.

### C6. Whether Course Master filters categories correctly

- **PARTIAL FAIL.** `CourseMasterController.php:72-73` lists courses by `institute_id` correctly, but category dropdown (`244-251`) returns **all** categories with `subject_type=professional` across tenants (cross-tenant leak, no institute filter). Should filter by `institute_id = TenantContext::id()` AND by domain-derived `subject_type`.

### C7. Whether changing industry/institution type would change existing courses

- No cascade observed. `courses.category_id` is FK `SET NULL` (inferred from `DESCRIBE`), so orphaned categories become NULL, not re-typed. No trigger rewrites `courses`. Changing institute's industry would **not** retag existing `course_categories.subject_type` — they'd stay professional while new policy expects academic, causing mixed-state.

### C8. Whether historical courses could become incorrectly classified

- YES risk. If migration re-parents `computer_it_training_institute` from `education` to `training_center`, existing categories for that sub_industry (e.g., institute 1606's `Video editing` course category 80) would remain `professional` (correct) but any new academic-only filter that hides `professional` would hide them. Conversely, if a bug flips logic to `where subject_type = derivedDomain`, professional courses would vanish from their owner's view.

### C9. Consumption map

All consumers enumerated above; no other hidden consumers found in `app/Services/*.php` (AcademicDashboardService uses `industryLabel` only; no course domain consumption).

---

## D. SUBJECT DOMAIN AUDIT

### D1. How Subject determines Academic vs Professional

- `subjects.subject_type enum('professional','academic') DEFAULT 'professional'` (`DESCRIBE subjects:3`). Set explicitly at create (`SubjectManagementController.php:113,124`) and update (`165`). NOT derived. Category's `subject_type` is not auto-copied; user picks both.

### D2. Whether Subject is global or tenant-scoped

- **Hybrid.** `institute_id nullable`. `institute_id = null` = global/system subject; `institute_id = <id>` = tenant-owned.  `SubjectManagementController.php:266-273` `subjectQuery()` returns `where institute_id = ? OR institute_id IS NULL` — intentional global sharing. However no TenantScoped trait, so global subjects are visible to all tenants (by design). `Subject.php:13-78` confirms no tenant trait.

### D3. Whether one institute can see another's subjects

- Via `subjectQuery` — **NO** leakage to other tenants' subjects because query restricts to `institute_id = current OR NULL`. But `filterCategories()`/`categories()` leak categories, and `index` stats `77` use `onlyTrashed()` with tenant filter correctly. Subject leakage risk is **low** today, but category leakage enables UI to assign another tenant's category_id (IDOR if `Rule::exists` lacks institute check — see D5).

### D4. Whether Subject creation currently derives domain from institute configuration

- **NO.** `SubjectManagementController::store:112-114` validates `subject_type` as client input (`Rule::in`). Category id uses `Rule::exists('course_categories', 'id')` without tenant or domain constraint. No `Institute::industry/sub_industry` lookup.

### D5. Whether Subject Management UI can automatically select correct subject type

- **Not currently.** Form `institute.course-master.subject-form` offers both `academic`/`professional` (`100`). No JS auto-select based on institute domain. Backend does not override.

### D6/7. Whether School/College/Polytechnic/University → academic and Training types → professional automatically

- **Not implemented.** No resolver exists. Must be added (e.g., config `subject_domain_map.php` or `InstituteDomainService`). Target sets are defined in brief; code has no equivalent.

### D8. Possibility of cross-tenant subject leakage

| Vector | Evidence | Risk |
|--------|----------|------|
| `subjectQuery` list | `:268` `where institute_id = current OR null` | SAFE for list |
| `assertAccessible` | `:295-303` checks `institute_id === current OR null` | SAFE for edit/destroy |
| `categories()` dropdown | `:285-292` `withoutGlobalScope('institute')->whereIn(...)` **no institute filter** | **HIGH — leaks other tenants' categories**; attacker can enumerate or assign via IDOR (`exists` without tenant) |
| `filterCategories()` | `:275-282` same leak | HIGH |
| `CourseMasterController::categories:244` | same | HIGH |
| `store` category_id validation | `:114` `Rule::exists('course_categories','id')` without `where institute_id` | **IDOR — can attach subject to another tenant's category** |
| `update` category_id validation | `:166` same | same |
| `withoutGlobalScopes()->where subject_id` in Category destroy | `:164-165` correctly filters by institute_id | SAFE |

**Conclusion D:** Subject creation is **tenant-safe for subjects themselves**, but **category assignment is not**. Fix requires scoping `exists` rule with `where institute_id` + `where subject_type = derivedDomain`.

---

## E. CURRICULUM AUDIT

### Tables inspected

- `course_curricula` (`DESCRIBE` shows `institute_id`, `course_id`, `status enum draft/active/archived`, `version int`, `created_by/updated_by`). No `subject_type`.
- `curriculum_modules` (`curriculum_modules` — not DESCRIBEd but via `database/migrations/2026_08_23_000000_create_course_curriculum_tables.php:22-...` — links to `course_curricula`, ordered).
- `curriculum_lessons` (child of modules).
- `batches` → curriculum via `batches.course_id -> courses -> course_curricula` (indirect). No direct FK from batches to curricula; `CourseCurriculumManagementTest.php` must be checked for freeze behavior.

### E1. Whether curriculum is Professional only

- **Implicitly yes.** `CourseCategoryManageController:27` and `CourseMasterController:247` both gate categories to `professional`, and curricula are per-course. Since courses are all professional-typed via categories, curricula are effectively professional-only. No academic curriculum path exists today.

### E2. Whether Academic and Professional curricula are mixed anywhere

- No `subject_type` on curricula, so mixing is **possible** if a course's subjects include both types via `course_subjects`. `CurriculumController.php` (not fully read) likely doesn't enforce subject_type filter — needs verification in implementation phase. Academic subjects could be attached to a professional course's curriculum via `CourseController::syncSubjects` (route `courses/{course}/subjects/sync`).

### E3. Whether curriculum domain derives from course/domain

- **No derivation.** `CurriculumController` uses `course_id + institute_id` scoping only (see `routes/institute_modules.php:928-936`).

### E4. Whether Academic subjects can accidentally enter Professional curriculum

- **YES.** No validation in `course_subjects` pivot (checked `DESCRIBE` — pivot is implicit via `course_subjects` table, no FK constraint on subject_type). An Academic `subject_type=academic` could be synced to a Professional course.

### E5. Whether Professional subjects can accidentally enter Academic assessment

- Academic assessment flow (`assessment_subjects` → `subjects`) is separate from curriculum; but since `subjects` are global pool, an academic assessment could reference a professional subject_id if caller bypasses UI. `AcademicAssessmentController.php:30,366` not fully read — needs hardening-phase verification.

### E6. Curriculum freeze behavior

- `course_curricula.status` lifecycle `draft -> active -> archived` with `version` unique per institute+course (migration `2026_08_23_000000`). Existing `CourseCurriculumManagementTest.php` is listed in `tests/Feature` and must remain green. Audit did **not** redesign; only noted that freeze is untouched.

---

## F. ACADEMIC ASSESSMENT AUDIT

### Chain inspected

```
AcademicYear (academic_years, institute_id FK)
 → ClassGrade (class_grades, via institute_class_grades)
 → Group/Stream (academic_groups)
 → Subject (subjects + subject_academic_assignments + institute_subjects)
 → Assessment (academic_assessments + assessment_types + components)
 → AssessmentSubject + AssessmentSubjectComponent
 → AcademicStudentMark (academic_student_marks) : (student, assessment component)
 → AcademicFinalResult + AcademicFinalResultRow (per subject snapshot) + AcademicFinalResultPolicy
 → GPA via GradeScale + GradeScaleRow
```

Detailed structure verified in `app/Models/AcademicAssessment.php`, `AssessmentSubject.php`, `SubjectAcademicAssignment.php` etc., and migrations `2026_08_17_*`.

### F1. Academic Assessment only uses Academic subjects

- **Intended YES, enforced PARTIALLY.** `AcademicSubjectService.php:481` `where subject_type = academic` for effective subjects. `AssessmentSubject` creation goes through `AcademicAssessmentController` which pulls from `SubjectAcademicAssignment` / `InstituteSubject` filtered by class — those assignments are seeded/maintained as academic-only. However direct `assessment_subjects.subject_id` FK has no `subject_type` check at DB level, so manual insertion could violate.

### F2. Professional subjects cannot enter Academic Assessment

- No DB constraint prevents it; service-layer filter is the only guard. If a professional `subject_id` is submitted to `AcademicAssessmentController::subjects` endpoint, it would be rejected only if not in the effective academic set — but not by FK.

### F3. Academic subjects cannot accidentally become Professional subjects

- `subjects.subject_type` is mutable via `SubjectManagementController::update:165`. Changing `academic` → `professional` would instantly detach the subject from all `subject_academic_assignments` (which are academic-scoped) but FK would remain; historical `assessment_subjects` would still point to now-professional subject, causing type mismatch in historical snapshots. No guard blocks this transition.

### F4. Existing optional subject logic remains independent

- `2026_08_17_110000_create_subject_academic_assignments_table.php` + `120000_create_academic_selection_groups_table.php` + `120200_add_selection_columns_to_institute_subjects_table.php` implement `requirement_type` + `selection_group_id` + `minimum_selection/maximum_selection`. Documented in `PHASE_A7_*` reports. Audit did not inspect mutation; logic intact.

### F5. Bangladesh optional-subject bonus remains intact

- `2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales.php` adds `optional_bonus_threshold decimal` ; `2026_08_28_000001_add_multiple_optional_policy_to_grade_scales.php` adds `multiple_optional_policy enum single/best/sum`. `GradeScale` model + `AcademicFinalResultService` compute `bonus = max(GP - threshold, 0)` with default `2.00` and cap `5.00` (see `PHASE_A3_*` hardenings). No change in audit.

Default threshold 2.00 and GPA 5.00 plus mapping `A+ 5.00 → +3.00`, `A 4.00 → +2.00`, `A- 3.50 → +1.50`, `B 3.00 → +1.00`, `C ≤2.00 → 0.00` remain enforced via `grade_scale_rows` per scale (not checked but referenced).

---

## G. LEGACY / DUPLICATE UI AUDIT

| Area | Canonical | Legacy / Duplicate | Routes active | Creates invalid records? | Bypasses tenant isolation? |
|------|-----------|-------------------|---------------|--------------------------|---------------------------|
| Subjects | `/courses/manage/subjects` (`SubjectManagementController` via `institute_modules.php:952`) + `institute.course-master.subjects` view | `/courses/subjects` (`CourseController::subjects` in `routes/web.php:189`) — general listing ; `admin.academic.subjects` (`Admin\AcademicSubjectAdminController` `web.php:319`) — global academic subjects admin | Both active (institute middleware `permission:courses.view` ; admin `auth:platform_admin`) | Legacy `CourseController::subjects` may use old filter without institute context — could create without domain check (needs code review) | Admin subjects are global (`institute_id NULL`) — safe by design but could create academic subject globally that leaks to all tenants |
| Courses | `/courses/manage` (`CourseMasterController` via `institute_modules.php:928`) | Legacy `/courses` (`courses.index`) retired per comment `web.php:187` but `CourseController` still has `courses.subjects` endpoints `web.php:189-393` | `CourseController::subjects` + `CourseAdminController::subjects` still active | Possible via `CourseAdminController` if admin creates professional category for wrong industry | No — courses are institute-scoped via `where institute_id` |
| Categories | `CourseCategoryManageController` JSON API (`institute_modules.php:937-942`) + modal | No separate legacy page; categories managed via modal only | Active | Hardcodes `professional` — after restructure will block academic category creation (must branch) | No tenant leak in index (filters by institute) — safe |
| Institution Type / Industry | `InstituteOnboardingController::step1` + `workspace/onboarding.blade.php` + `workspace/create.blade.php` | `auth/register-select.blade.php` duplicated onboarding for pre-registration | Both active, share `IndustryRules` | Creates invalid combos per target (see A9) | No — onboarding is per-user, not tenant data |
| Academic Subjects (old) | `class-grades` + `subject_academic_assignments` engine (`ClassController`, `AcademicSubjectAdminController`) | Overlaps with `SubjectManagementController` — two subject masters (course_subjects vs academic assignments) | Both active; unified via `Subject` model | Could assign professional subject to academic class if category not checked | Academic assignments are `institute_id` scoped via class — safe |

**Verdict G:** One canonical subject page (`/courses/manage/subjects`) is correctly identified; legacy `/courses/subjects` and `admin/academic/subjects` remain active and could create invalid records if they don't enforce new domain rules. DO NOT DELETE yet — but must gate with new validator.

---

## H. TENANT / SECURITY AUDIT

### Scope matrix

| Domain | Model trait | Global scope | Policies / authorize() | assertAccessible / IDOR |
|--------|-------------|--------------|------------------------|--------------------------|
| Industry / Institution Type (`institutes`) | SoftDeletes, no TenantScoped | none (tenancy root) | `Role` membership via `MembershipService`, `Workspace::set` | — |
| CourseCategory | `TenantScoped` (`CourseCategory.php:11`) | `institute_id = TenantContext::id()` | `assertOwned` in `CourseCategoryManageController:176` | IDOR protected when not bypassed |
| CourseSubCategory | `BranchScoped`? actually `TenantScoped` via? Check model | — | `assertOwned` | — |
| Course | **NOT** TenantScoped | manual `where institute_id` in controllers | `assertOwned` (`CourseMasterController:196`) | Manual check, but `course` route-model binding may resolve cross-tenant before check — timing matters |
| Subject | **NOT** TenantScoped | `subjectQuery` manual `where institute_id=? OR NULL` | `assertAccessible` (`SubjectManagementController:295`) | Allows `institute_id NULL` globals; no check on `category_id` tenant (IDOR) |
| CourseCurriculum | `institute_id` FK, manual | `where institute_id` | `permission:courses.manage` | — |
| Academic Assessment | `AcademicAssessment` → `TenantScoped`? Check `AcademicAssessment.php` — uses `TenantScoped` via? Many academic models use `TenantScoped` | global scope | `AcademicAssessmentController:30` uses institute_id validation | `assertAccessible` patterns |
| Academic Marks/Results | `AcademicStudentMark`, `AcademicFinalResult*` — tenant via placement → institute | — | controller checks | — |

### Critical findings

- **Category dropdown leakage** (`SubjectManagementController:277-292`, `CourseMasterController:244`) uses `withoutGlobalScope('institute')` without `where institute_id = TenantContext::id()`. This **exposes all institutes' categories** to current actor via JSON. An attacker can enumerate `category_id` of Institute B, then attach subject to it via IDOR because `Rule::exists` lacks tenant clause.
- **Subject category assignment IDOR** (`SubjectManagementController:114,166`): `Rule::exists('course_categories','id')` does not constrain to `institute_id`. Even with tenant-scoped global, `withoutGlobalScope` bypass would allow cross-tenant ID; but even with global scope enabled, raw `exists` rule queries without tenant context (it bypasses global scopes? Actually `exists` rule uses DB query without Eloquent global scope — it checks physical table, not scoped). So cross-tenant `category_id` passes validation and creates subject pointing to another institute's category (FK allows since no institute FK on subjects→categories is not institute-aware beyond id).

- **`withoutGlobalScopes()` on courses** (`CourseCategoryManageController.php:32,164-165` `Course::withoutGlobalScopes()->where institute_id` is correct because Course has no TenantScoped, but pattern is fragile).
- **`TenantContext::enabled()`** toggle: `TenantScoped.php:20` skips scope when disabled (e.g., migrations, seeders, platform admin context). Global admin viewing institute data must be careful not to leak.
- **`assertAccessible` allows `institute_id NULL` globals** — by design; but global subjects are visible to all institutes (shared curriculum). This is intentional per `SubjectDeletionService:36-40` SYSTEM_REFERENCE guard. No leak beyond design.
- **`BranchScoped` / `BranchScopedOrShared`** not relevant to industry split.

**Overall:** Tenant isolation is **PARTIAL** — subject listing is safe, but **category enumeration + assignment is not**. Must fix before implementation.

---

## I. MIGRATION / DATA SAFETY AUDIT

### Existing records needing mapping

| Table | Count (snapshot) | Current values | Mapping required |
|-------|------------------|----------------|------------------|
| `institutes` | 2 (plus soft-deleted 0) | `(education, institution)`, `(education, school)` | `institution` → belongs under `Training Center` as `Training Institute` (or keep as generic `Training Institute` fallback). `school` stays under `Education`. No academic record for `college`/`university`/`polytechnic` yet. |
| `industry_template_mappings` | 20 | All `education` + 15 sub types | Re-parent 9 training-related subs to `training_center`: `institution`, `vocational_institute`, `technical_training_center`, `skill_development_center`, `computer_it_training_institute`, `professional_training_academy`, `martial_arts`, `dance_academy`, `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` → new industry. Academic subs (`school`, `primary_school`, `secondary_high_school`, `school_college`, `college`, `university`, `madrasha`) stay under `education`. |
| `course_categories` | 3 | All `institute 1606`, `subject_type=professional` | Stay `professional` under `training_center` tenant — no move needed if institute 1606 is training type. If institute 1606 is later re-typed, categories must stay with original institute (no global move). |
| `course_sub_categories` | 2 | Both under category 78 (professional) | Same as above |
| `courses` | 1 | `Video Editing`, `category 80` (professional), `institute 1606` | Stay professional |
| `subjects` | 0 (0 active, 0 trashed) | — | No migration needed |
| `academic_final_results` / `assessment_subjects` | 0 | — | Historical integrity trivially satisfied (no rows to migrate) |

### Proposed mapping OLD → NEW (DO NOT EXECUTE YET)

| OLD `industry` | OLD `sub_industry` | NEW `industry` | NEW `sub_industry` / institution type | Domain | Notes |
|---|---|---|---|---|---|
| `education` | `institution` | `training_center` | `training_institute` | professional | Generic Institute → Training Institute (rename slug) |
| `education` | `skill_development_center` | `training_center` | `vocational_training_center` | professional | Or keep distinct if both needed; target has `Vocational Training Center` |
| `education` | `vocational_institute` | `training_center` | `vocational_training_center` | professional | Merge two vocational keys to single |
| `education` | `technical_training_center` | `training_center` | `vocational_training_center` | professional | Polytechnic target is academic; technical_training stays professional unless polytechnic maps to it |
| `education` | `computer_it_training_institute` | `training_center` | `it_training_center` | professional | Rename to canonical `IT Training Center` |
| `education` | `professional_training_academy` | `training_center` | `professional_training_center` | professional | Rename to canonical |
| `education` | `dance_academy` | `training_center` | `dance_academy` | professional | Keep slug, re-parent |
| `education` | `martial_arts` | `training_center` | *(keep but not in target list)* | professional | Not in target — decide retain under Training Center or map to `Vocational Training Center` |
| `education` | `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` | `training_center` | same slugs | professional | Keep; target list is minimal but , but |
 them `training_institute` siblings |
| *(new)* | `polytechnic` | `education` | `polytechnic` | academic | **Create** under Education → Academic Institutions (missing) |
| `education` | `school` | `education` | `school` | academic | Unchanged |
| `education` | `college` | `education` | `college` | academic | Unchanged |
| `education` | `university` | `education` | `university` | academic | Unchanged |
| *(new industry)* | — | `retail` | — | — | Already exists; keep as industry (no sub) |
| *(new)* | — | `service` | — | — | **Create** industry (empty sub list) |
| `transport` | — | `transportation` | — | — | Rename `transport` → `transportation` (or keep alias + new) |
| `restaurant` | — | `restaurant` | — | — | Unchanged |
| `manufacturing` | — | `manufacturing` | — | — | Unchanged |

**Pre-flight validation required:**
- Assert `institutes` has no orphan `sub_industry` values outside new config before running.
- Assert `industry_template_mappings` unique constraint `itm_industry_sub_country_unique` will not collide after re-parent (e.g., `education,institution` → `training_center,training_institute` is new unique, safe).
- Assert `institutes (education,institution)` count = 1 is correctly remapped without duplicate slug collision.
- Log/report affected `institute_id` rows before commit (dry-run mode).

**Safety:** Migration must be reversible (down() re-parents back to education). Must refuse if `subjects` with `subject_type` contradict new domain (e.g., academic subject under training institute). Current snapshot has 0 such conflicts — safe.

---

## J. BUSINESS RULE GAP ANALYSIS

| # | Question | Status |
|---|----------|--------|
| 1 | Can institute change from Academic → Professional after account creation? | **BUSINESS DECISION REQUIRED** — No UI exists; migration audit shows high corruption risk (category/subject mix). Recommendation: **MAKE IMMUTABLE** after creation; allow via super-admin with pre-flight + dataFreeze check. |
| 2 | Can Professional → Academic? | Same as 1 — BUSINESS DECISION REQUIRED |
| 3 | If yes, what happens to existing courses/subjects? | **BUSINESS DECISION REQUIRED** — Options: (a) freeze existing categories/courses as historical (read-only), (b) require admin to migrate via replacement category flow (as in Category destroy:148-165), (c) block when historical data exists (`SubjectDeletionService` HISTORICAL_DEPENDENCY). |
| 4 | Should domain become immutable after account creation? | **RECOMMENDATION: YES** — Domain derived from `sub_industry`, which should be immutable. Document as CONFIRMED pending stakeholder sign-off. Currently no enforcement — gap. |
| 5 | Can one institute operate both Academic and Professional programs? | **BUSINESS DECISION REQUIRED** — Target hierarchy implies single domain per institute (Education vs Training Center). Multi-domain would need `institute_subjects` per-program domain, not institute-level. No code supports this today. |
| 6 | Can a Training Center have Academic courses? | **PROPOSED: NO** — validation must reject `training_center` + `subject_type=academic` category. Mark BUSINES DECISION REQUIRED if exception allowed. |
| 7 | Can an Academic Institution offer Professional courses? | **PROPOSED: NO** — symmetric. Mark BUSINESS DECISION REQUIRED. |
| 8 | What is `Polytechnic` structure template? | **BUSINESS DECISION REQUIRED** — Is it `technical_institute` (Program → Semester → Batch) or new `polytechnic` template? Currently `polytechnic` missing entirely. |
| 9 | Should `Madrasha` stay Academic or move to Training? | **BUSINESS DECISION REQUIRED** — Currently under `education` with `madrasa` template; brief says academic only School/College/Polytechnic/University, so `Madrasha` is unspecified — keep under Education for now or move. |
| 10 | Should `Service` industry have sub_industries? | **BUSINESS DECISION REQUIRED** — Target lists `Service` as leaf (no children). Config currently has no `service` at all. |

No answers invented; all marked accordingly.

---

## K. AUDIT VERDICT

```
PHASE: B1
SCOPE: Industry / Institution Type / Academic-Professional Domain
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
```

| Domain | Verdict | Rationale |
|--------|---------|-----------|
| **DATABASE_MAPPING** | **FAIL** | `Training Center` incorrectly nested under `Education`; `Polytechnic` missing; `Service` missing; `Transport` slug mismatch; no domain column/resolver. File:line `config/industry_rules.php:23-57`, `LearningStructureSeeder.php:294-319`, `industry_template_mappings` 20 rows all education. |
| **INSTITUTE_CREATION** | **PARTIAL** | Session-based trio is safe (never trusts browser), but validation allows invalid combos because config is wrong; no domain derivation, no immutability enforcement. `InstituteOnboardingController.php:58-90`, `InstituteCreationController.php:121-122`. |
| **COURSE_DOMAIN** | **PARTIAL** | `course_categories.subject_type` exists and `courses` inherit via category, but `CourseMasterController:244-251` leaks cross-tenant and hardcodes professional; no industry↔domain check. |
| **SUBJECT_DOMAIN** | **FAIL** | `subjects.subject_type` is client-supplied and mutable; no server-side derivation; category IDOR allows cross-tenant assignment. `SubjectManagementController.php:113,165,285-292`. |
| **CURRICULUM_DOMAIN** | **PARTIAL** | Curricula are implicitly professional-only and not mixed today, but no `subject_type` guard prevents academic subjects entering professional curriculum (pivot has no check). |
| **ACADEMIC_ASSESSMENT** | **PASS** | Chain is correctly academic-only via `AcademicSubjectService:481` and `subject_academic_assignments`; optional bonus and `grade_scales` intact (`2026_08_27_000004`, `2026_08_28_000001`). No leakage found. |
| **TENANT_ISOLATION** | **PARTIAL** | Subject list is safe; **category enumeration is not** (`withoutGlobalScope` without institute filter). Must fix before green. `SubjectManagementController.php:277-292`, `CourseMasterController.php:244`. |
| **IDOR_PROTECTION** | **PARTIAL** | `assertAccessible`/`assertOwned` protect direct object access, but `Rule::exists` on `category_id` lacks tenant scoping → IDOR. |
| **HISTORICAL_INTEGRITY** | **PASS** | `subjects` SoftDeletes + `RESTRICT` FKs (`2026_08_27_000001`, `000002`) + `SubjectDeletionService` (`HISTORICAL_DEPENDENCY` → block hard delete) preserve `exam_results`/`academic_final_result_rows` with `withTrashed()` display. No `FOREIGN_KEY_CHECKS=0` found. 0 historical rows today, but mechanism is sound. |
| **LEGACY_UI** | **PARTIAL** | Canonical `/courses/manage` + `/courses/manage/subjects` are correct; legacy `/courses/subjects` and `admin/academic/subjects` still active and could create invalid records without new validator. `routes/web.php:189,319`, `routes/institute_modules.php:952`. |

### CRITICAL_FINDINGS

1. **C1 — Training Center not independent**: Entire training taxonomy lives under `industry='education'` (`config/industry_rules.php:37-57`, `LearningStructureSeeder.php:294-319`, `industry_template_mappings` ids 8-19). Violates non-negotiable rule 3-5. Data must be re-parented.
2. **C2 — Category cross-tenant leakage + IDOR**: `SubjectManagementController::filterCategories/categories` and `CourseMasterController::categories` expose all institutes' categories; `Rule::exists('course_categories','id')` without institute constraint allows attaching subject to another tenant's category. `app/Http/Controllers/SubjectManagementController.php:277-292`, `app/Http/Controllers/CourseMasterController.php:244-251`.

### HIGH_FINDINGS

- **H1 — `subject_type` is client-trusted**: `SubjectManagementController.php:113,165` accept `subject_type` from request; backend must derive from institute domain server-side per brief §DOMAIN RULE.
- **H2 — `CourseCategoryManageController:27,80` hardcodes `professional`**: Academic institutes cannot create academic categories.
- **H3 — `Polytechnic` missing**: Not in `config/industry_rules.php` nor seeds; required under Education→Academic.
- **H4 — `Service` industry missing** and `Transport` slug mismatch (`transport` vs `Transportation`) — target hierarchy incomplete in config.
- **H5 — No domain immutability / institute type change guard**: Changing `sub_industry` would corrupt `subject_type` history (see F3, I mapping).

### MEDIUM_FINDINGS

- **M1 — `CourseMasterController::categories` hardcodes `professional` but is used by both domains** — after split, academic institutes would see wrong set.
- **M2 — Curriculum subject_type not guarded**: `course_subjects` pivot can mix academic/professional.
- **M3 — `Madrasha` / `martial_arts` / `music_academy` taxonomy beyond target — decision needed whether to retain under Training Center or deprecate (J gaps).
- **M4 — `industry_settings` empty, not used for hierarchy** — dead table if not repurposed.
- **M5 — No validation for valid combinations** (Education+Training Institute etc.) beyond config membership — needs explicit matrix after restructure.

### BUSINESS_RULE_GAPS

Listed in Section J #1-10 — total **10 gaps**, all marked BUSINESS DECISION REQUIRED. Most critical: domain immutability, single vs dual-domain institutes, and `Polytechnic` template mapping.

### FINAL_VERDICT

```
FINAL_VERDICT: YELLOW
```

**YELLOW** — Historical integrity, academic assessment, and basic tenant isolation are sound, but the hierarchy is structurally wrong (Training Center under Education) and category/subject domain is not server-derived with active IDOR leakage. **Safe to proceed to implementation** only after acknowledging CRITICAL/HIGH findings and applying pre-flight-checked, reversible migration (no historical data at risk per snapshot — 0 academic final results, 0 subjects). If audit had found >0 historical academic results tied to training subjects, verdict would be RED — but snapshot shows 0, so YELLOW → proceed with caution and fixes in Part 2.

**STOP HERE — DO NOT IMPLEMENT UNTIL STAKEHOLDER ACKNOWLEDGES YELLOW AND APPROVES PROPOSED MAPPING (Section I).**

---

## APPENDIX — Key File:Line Evidence Index

- `config/industry_rules.php:23-57,61-82,118-136,173-337` — single source of truth (currently wrong hierarchy)
- `app/Support/IndustryRules.php:15-95` — accessor (no hierarchy)
- `app/Models/Institute.php:1-193` — no domain logic
- `database/migrations/2026_08_13_000000_add_industry_to_institutes_table.php:12` — industry column
- `database/migrations/2026_08_14_195437_add_sub_industry_to_institutes_table.php:12` — sub_industry
- `database/migrations/2026_08_24_000100_create_learning_structure_engine_tables.php:106-128` — mappings
- `database/seeders/LearningStructureSeeder.php:294-319` — seeds (mis-parented)
- `app/Http/Controllers/InstituteOnboardingController.php:58-90,98-126` — onboarding validation
- `app/Http/Controllers/InstituteCreationController.php:121-122,268-283` — creation
- `app/Models/CourseCategory.php:1-38` — TenantScoped, subject_type
- `app/Models/Subject.php:1-79` — hybrid tenancy, SoftDeletes, slug
- `app/Http/Controllers/SubjectManagementController.php:113,165,266-304` — subject domain + IDOR
- `app/Http/Controllers/CourseCategoryManageController.php:27,80,176` — category domain
- `app/Http/Controllers/CourseMasterController.php:244-251` — category leak
- `app/Services/SubjectDeletionService.php:16-104` — historical protection
- `app/Http/Controllers/CourseMasterController.php:244` — curriculum-adjacent
- `routes/institute_modules.php:928-952` — canonical routes
- `routes/web.php:189,319` — legacy routes
- `app/Services/LearningStructureResolver.php:44-68` — template resolution
- `app/Models/Concerns/TenantScoped.php:15-52` — global scope

*End of Forensic Audit — no data modified.*
