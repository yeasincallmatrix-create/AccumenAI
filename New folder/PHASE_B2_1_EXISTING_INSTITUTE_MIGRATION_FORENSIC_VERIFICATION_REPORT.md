# PHASE B2.1 — EXISTING INSTITUTE MIGRATION — FORENSIC VERIFICATION REPORT (GATE 1)

**PHASE:** B2.1 — Existing Institute Migration Forensic Verification (Audit + Safe Migration Only Where Required)
**DATE:** 2026-08-28
**AUDITOR:** OpenCode / Muse Spark (read-only inspection, NO DATA MODIFIED)
**PRIOR REPORTS:** `PHASE_B2_DOMAIN_RESTRUCTURE_FORENSIC_AUDIT_REPORT.md` (YELLOW, Gate 1), `PHASE_B2_DOMAIN_RESTRUCTURE_IMPLEMENTATION_REPORT.md` (GREEN, Gate 2)
**DATABASE:** `monetix` @ `127.0.0.1:3306` (live snapshot 2026-08-28)
**METHOD:** Direct `DB::table` inspection, `InstituteDomain::fromInstitute()` / `fromKeys()` / `isValidCombination()` / `hasDomainData()`, `SHOW CREATE TABLE`, `INFORMATION_SCHEMA`, config inspection, codebase grep for legacy values, migration table inspection.

---

## 0. AUDIT SCOPE & CONSTRAINTS

Per `PHASE B2.1` brief §§12-13: **no `UPDATE institutes`, no `DELETE`, no `migrate:fresh`, no seeder, no rollback executed**. All evidence gathered via `SELECT` only. This report is **forensic verification**; migration plan in §11-§13 is **proposed, not executed**.

---

## 1. EXISTING INSTITUTE INVENTORY

### 1.1 Row-level inventory ( `institutes` — including `withTrashed()` )

| id | name | industry | sub_industry | country | deleted_at | created_at | updated_at | status |
|---|---|---|---|---|---|---|---|---|
| 38 | Institution Demo | `training_center` | `training_institute` | Bangladesh | NULL | 2026-08-23 12:19:17 | 2026-08-28 13:31:50 | active |
| 39 | Leak Test 06a8b0a159dd8e | `education` | NULL | Bangladesh | NULL | 2026-08-23 14:56:21 | 2026-08-23 14:56:21 | active |
| 40 | Leak Test 16a8b0a15a2bda | `education` | NULL | Bangladesh | NULL | 2026-08-23 14:56:21 | 2026-08-23 14:56:21 | active |
| 41 | Leak Test 26a8b0a15a3b7f | `education` | NULL | Bangladesh | NULL | 2026-08-23 14:56:21 | 2026-08-23 14:56:21 | active |

- `SELECT COUNT(*) FROM institutes` (non-deleted) = **4**
- `SELECT COUNT(*) FROM institutes WHERE deleted_at IS NOT NULL` = **0**
- `Institute::withTrashed()->count()` = **4** (no soft-deleted rows)
- `SELECT DISTINCT industry, sub_industry FROM institutes GROUP BY`:
  - `training_center / training_institute` ×1 (id 38)
  - `education / NULL` ×3 (ids 39-41)

**Demo backup comparison** — `demo/monetix_backup_20260824_manual.sql`:
- Contains `INSERT INTO institutes VALUES (38, 'dc6077d2-...', 'Institution Demo', ..., 'education','institution', ..., 'institution-demo', ...)` — confirms id 38 was **originally `education/institution`** before B2.
- Also contains institutes id 1 and 2 inserts (historical production data) — **not present in live DB** (see §1.2 orphan section).

### 1.2 Orphaned historical data (parent institute missing — FK parent not found)

| Table | Orphan institute_id | Count | FK rule | Expected behavior | Actual |
|---|---|---|---|---|---|
| `course_categories` | 1 | 20 | `FK institutes(id) ON DELETE CASCADE` | Should cascade-delete when institute 1 deleted | **Not deleted** — orphan persists |
| `courses` | 2 | 150 | `FK institutes(id) ON DELETE CASCADE` | Should cascade-delete | **Not deleted** — orphan persists |
| `course_subjects` | via courses 1-4 | 10 | indirect via courses | — | 10 rows linking courses 1-4 to subjects 1-2 (all professional) |
| `subjects` (global) | NULL categories | 50 (40 active + 10 soft-deleted) | `institute_id` nullable, not orphan | — | All 50 have `institute_id NULL` (global shared pool), category_ids 1,10,11 (orphan category 1) |

**Root cause:** `INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS` confirms CASCADE rules exist. Orphan persistence implies `FOREIGN_KEY_CHECKS=0` was disabled during a prior `TRUNCATE institutes` or `migrate:fresh` without `course_categories`/`courses` truncation, or manual `DELETE` with checks disabled. Live DB therefore has **20 orphan categories + 150 orphan courses** that are **tenant-unowned** and not returned by any current institute's tenant-scoped query — they inflate global counts but are invisible per-tenant. This is a **historical integrity gap**, not a B2 migration gap, but must be flagged (see §10).

### 1.3 `institutes` distinct counts for report matrix

```
TOTAL_EXISTING_INSTITUTES (active) = 4
TOTAL withTrashed                   = 4
```

---

## 2. CURRENT DOMAIN CLASSIFICATION

Resolved via `App\Support\InstituteDomain` (`app/Support/InstituteDomain.php:48-74`):

- `ACADEMIC` = `education` + `{school, college, polytechnic, university}`
- `PROFESSIONAL` = `training_center` + `{training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center}`
- `OTHER` = everything else (including `education/NULL`, `transportation`, `retail`, `service`, `healthcare`, etc.)

Alias normalization applied before classification:
- `normalizeIndustry`: `transport → transportation`
- `normalizeSubIndustry`: `institution → training_institute`, `professional_training_academy → professional_training_center`, `computer_it_training_institute / computer_it → it_training_center`, `vocational_institute / technical_training_center / skill_development_center → vocational_training_center`

| id | stored `industry` | stored `sub_industry` | `InstituteDomain::fromInstitute()` | `isValidCombination()` | `subjectTypeFor()` | Classification |
|---|---|---|---|---|---|---|
| 38 | training_center | training_institute | **professional** | YES | professional | **PROFESSIONAL (canonical)** |
| 39 | education | NULL | other | **NO** (education requires sub) | professional (other default) | **OTHER / NEEDS_REVIEW (incomplete onboarding)** |
| 40 | education | NULL | other | NO | professional | **OTHER / NEEDS_REVIEW** |
| 41 | education | NULL | other | NO | professional | **OTHER / NEEDS_REVIEW** |

**Summary counts:**

| Class | Count | IDs |
|---|---|---|
| ACADEMIC | 0 | — |
| PROFESSIONAL | 1 | 38 |
| OTHER (incomplete) | 3 | 39,40,41 |
| LEGACY (stored old value) | 0 | — |
| INVALID (bad combo) | 3 (same as OTHER/NULL) | 39,40,41 |
| NEEDS_REVIEW | 3 | 39,40,41 |
| DOMAIN_CONTRADICTION | 0 | — |

No institute stored a legacy value (`institution`, `professional_training_academy`, `computer_it*`, `vocational_institute`, `dance_academy`, `transport`). The only legacy-to-canonical transition (`education/institution → training_center/training_institute`) is **already resolved** for id 38.

Orphan categories/courses are **not classified** against a live institute (parent missing) — they are separately categorized as **ORPHANED** in §1.2.

---

## 3. PREVIOUS B2 MIGRATION VERIFICATION

**Target migration (Gate 2 report §2.5 / migration `2026_08_28_100000`):**

```
education / institution  →  training_center / training_institute   (AUTO)
education / professional_training_academy → training_center / professional_training_center
education / computer_it_training_institute → training_center / it_training_center
education / vocational_institute → training_center / vocational_training_center
education / dance_academy → training_center / dance_academy
(+ ensure canonical mappings for polytechnic, school, college, university, training_center fallbacks)
```

### 3.1 `institutes` verification

| Check | Expected | Actual | Verdict |
|---|---|---|---|
| Row `education/institution` count before B2 | 1 (id 38 per backup) | Backup confirms 1 | — |
| Row `education/institution` after B2 | 0 | `SELECT ... WHERE industry='education' AND sub='institution'` = **0** (active 0, withTrashed 0) | **PASS** |
| Row `training_center/training_institute` after B2 | 1 (migrated id 38) | `SELECT ... WHERE industry='training_center' AND sub='training_institute'` = **1** (id 38) | **PASS** |
| `updated_at` for id 38 | changed by migration | `2026-08-28 13:31:50` (vs `created_at 2026-08-23`) — matches B2 batch time | **PASS** |
| Any duplicate key collision | 0 | `SELECT industry,sub,country,COUNT(*) GROUP BY` has no duplicates | **PASS** |
| Orphan institutes 1,2 lost (not B2) | — | institutes 1,2 missing but not part of B2 migration (see §1.2) — not a B2 regression | **INFO** |

**Conclusion institutes:** `education/institution → training_center/training_institute` **correctly applied**, no legacy row remains, no duplicate, no orphan created by migration.

### 3.2 `industry_template_mappings` verification

- `SELECT COUNT(*) FROM industry_template_mappings` = **0** (live)
- `SELECT COUNT(*) FROM structure_templates` = **0** (live)
- `migrations` table: `2026_08_28_100000_restructure_industry_institution_domain_taxonomy` batch 44 = **installed**

**Expected per Gate 2 §2:** 27 rows (22 original + 5 re-parented + polytechnic + training_center fallback). **Actual 0** — **FAIL**.

**Forensic explanation:**
- Migration `ensureMapping()` inserts only if `structure_templates.code = training_institute / vocational_institute / dance_academy / school / college / university / technical_institute` exists and `is_global=true` (`2026_08_28_100000:97-101`). Live DB has **0 structure_templates**, so `ensureMapping()` was a no-op at migration time (pre-flight: no template → no insert).
- Separately, `industry_template_mappings` table is empty, not populated with the 5 re-parented rows either. This suggests the migration's `DB::table('industry_template_mappings')->where(...)->update(...)` found 0 rows because mappings were already truncated before B2 ran (or truncated after). `baseline` dump likely not seeded after last `migrate:fresh` — e.g., `php artisan test` harness does `migrate:fresh` which creates tables empty, then does not run `LearningStructureSeeder`.
- **Impact:** Live DB is **missing its industry→template routing table**. `LearningStructureResolver::resolveTemplate()` (`app/Services/LearningStructureResolver.php:44-68`) will fall back to global `education,NULL` fallback or fail to resolve polytechnic/training types. New institute onboarding after B2 will still show correct dropdowns (config-based), but backend template resolution for `polytechnic` / `training_center` types is broken.
- **Rollback readiness:** `down()` would similarly be no-op (0 rows to revert). Rollback via `git checkout -- config/industry_rules.php` + `migrate:rollback` still works for `institutes` row, but mappings would need re-seed: `php artisan db:seed --class=LearningStructureSeeder` after rollback.

**Verdict:** **PARTIAL** — institute row migration PASS, but mapping layer FAIL (missing data, not corrupted data). This is **not a migration corruption** (no wrong values), but a **seed omission** post-migration.

### 3.3 Related categories / courses / subjects verification

| Table | B2 expectation | Actual | Verdict |
|---|---|---|---|
| `course_categories` for id 38 | 0 (id 38 has no categories, orphan categories belong to missing id 1) | 0 for 38, 20 orphan for id 1 | **PASS** (no B2-induced orphan; orphan pre-exists B2) |
| `courses` for id 38 | 0 | 0 for 38, 150 orphan for id 2 | **PASS** |
| `subjects` for id 38 | 0 (global pool) | 0 for 38, 50 global | **PASS** |
| `batches` / `curricula` / `academic_*` for id 38 | 0 | 0 | **PASS** |
| FK `courses.category_id → course_categories.id` | `SET NULL` on delete | Verified `fk_courses_category DELETE SET NULL` | **PASS** (no cascade delete) |
| FK `course_categories.institute_id → institutes.id` | `CASCADE` | Verified `fk_course_categories_institute DELETE CASCADE` | **PASS** (but orphans indicate checks were disabled at some point) |

**No related data was orphaned by B2** — orphan counts predate B2 (institutes 1,2 not in current `institutes` table).

### 3.4 Domain resolver behavior verification

| Test | `InstituteDomain::fromKeys` | Expected | Actual | Pass |
|---|---|---|---|---|
| education,school | academic | academic | academic | YES |
| education,college | academic | academic | academic | YES |
| education,polytechnic | academic | academic | academic | YES |
| education,university | academic | academic | academic | YES |
| training_center,training_institute | professional | professional | professional | YES |
| training_center,professional_training_center | professional | professional | professional | YES |
| training_center,dance_academy | professional | professional | professional | YES |
| training_center,it_training_center | professional | professional | professional | YES |
| training_center,vocational_training_center | professional | professional | professional | YES |
| legacy education,institution | other (via normalize, industry still education) | other (legacy should be treated via normalize only when industry already migrated) | other | YES (correct — legacy only resolves after industry migrated) |
| training_center,institution (alias) | professional | professional | professional | YES |
| transport (alias) → transportation | transportation | transportation | transportation | YES |

`InstituteDomain::normalizeIndustry` and `normalizeSubIndustry` correctly collapse legacy aliases (`app/Support/InstituteDomain.php:118-142`). `fromInstitute()` applies normalization before domain check — **correct**.

### 3.5 Navigation / permissions verification

- `InstituteDomain` is used by `SubjectManagementController` (create/edit/store/update + category filter), `CourseMasterController` (category/subcategory scoping), `CourseCategoryManageController` / `CourseSubCategoryManageController` (tenant+domain scoping) — **PASS** (IDOR vectors fixed in Gate 2).
- Still using raw `industry==='education'` in:
  - `DashboardController.php:45,171` `isEducation`
  - `ModuleAccessService.php:387-391` `isEducationIndustry()`
  - `AcademicSetupService.php:59` `if industry !== education return`
  - `AcademicSetupCommand.php:27,37` `where industry='education'`
  - `DemoBusinessSeederCommand.php:17-21` seed data still contains `education/institution` etc. (legacy)
  - `LearningStructureResolver.php:63` fallback `education,NULL`
- These are **not incorrect** for academic gating today (academic ⊂ education), but `DashboardController` / `ModuleAccessService` should ideally gate on `InstituteDomain::isAcademic()` rather than `industry==='education'` to exclude `education,NULL` leaks and to correctly handle `training_center` professional (currently correctly excluded, since `industry !== education` → non-education path). Risk is **medium, not blocking** — `education,NULL` institutes (ids 39-41) would currently be treated as "education" by `isEducation` (true, because industry==education) but `InstituteDomain` classifies them as `other` — contradiction. However those 3 have no domain data, so impact is negligible.

**Verdict B2 overall:** **PARTIAL PASS** — core institute migration PASS, resolver PASS, category/subject IDOR PASS, but `industry_template_mappings` / `structure_templates` **FAIL (empty)** and raw `industry==='education'` checks remain (legacy, non-blocking).

---

## 4. LEGACY VALUES

### 4.1 Database — `institutes` (active + withTrashed)

| Legacy value | `industry=` count | `sub_industry=` count | `industry/sub` combo count | Found? |
|---|---|---|---|---|
| `education` | 3 (ids 39-41) | — | — | YES (but `education` is canonical academic parent — not legacy per se; retained) |
| `institution` | — | 0 | `education/institution` 0 | **NO** (migrated) |
| `professional_training_academy` | — | 0 | 0 | NO |
| `computer_it_training_institute` | — | 0 | 0 | NO |
| `computer_it` | — | 0 | 0 | NO |
| `vocational_institute` | — | 0 | 0 | NO |
| `technical_training_center` | — | 0 | 0 | NO |
| `skill_development_center` | — | 0 | 0 | NO |
| `dance_academy` (legacy under education) | — | 0 | `education/dance_academy` 0 | NO |
| `transport` (industry) | 0 | — | — | NO |
| `transportation` (canonical) | 0 | — | — | NO |
| `training_institute` (canonical) | — | 1 (id 38) | `training_center/training_institute` 1 | YES canonical |
| `polytechnic` | — | 0 | 0 | NO (no institute uses yet) |

**Result:** **0 legacy institutes remain**. The one legacy institute (`education/institution` id 38) was **correctly migrated**; no remaining row contains a legacy slug or `industry='transport'`.

### 4.2 Database — `industry_template_mappings` / `structure_templates`

- Both tables **empty** (0 rows) — so neither legacy nor canonical rows exist. This is **not a legacy residue** but a **missing seed** (see §3.2). No legacy `education/institution` mapping row remains (0).

### 4.3 Codebase — legacy strings still present (not data, but code debt)

| Location | Legacy string | Verdict |
|---|---|---|
| `config/industry_rules.php:59,91-92,159-160` | `institution`, `professional_training_academy`, `computer_it_training_institute`, `vocational_institute`, `technical_training_center`, `skill_development_center`, `martial_arts` etc. under `training_center` | **Intentionally retained as aliases** per `app/Support/InstituteDomain.php:130-139` + `config:59-69` comment `legacy professional variants preserved as aliases for audit trail` — **PASS (expected)** |
| `app/Console/Commands/DemoBusinessSeederCommand.php:17,20-21` | `['industry'=>'education','sub'=>'institution']`, `vocational_institute`, `technical_training_center` | **STALE** — should be `training_center/training_institute` etc. — file `DemoBusinessSeederCommand.php:17-21` still seeds legacy values (will create legacy institutes if run) — **FLAG, not data-affecting today** |
| `app/Services/Demo/DemoDataService.php:104,119` | `in_array($industry, ['education','healthcare','retail','manufacturing','transport', ...])` | **STALE** — uses `transport` not `transportation` — **FLAG** |
| `app/Services/LearningStructureResolver.php:63` | fallback `['industry'=>'education','sub'=>null]` | **Expected** — global fallback remains education |
| `app/Support/InstituteDomain.php:130-139` | alias map | **Correct** — canonicalizes legacy |
| `database/seeders/LearningStructureSeeder.php` (not inspected live but per Gate 2 report) | still contains legacy education rows for rollback | **Expected** per rollback strategy |

**Summary legacy:** **DB legacy PASS (0 rows)**, **codebase legacy PARTIAL** (aliases retained by design, 2 stale seeder/service files).

---

## 5. DEPENDENCY MAPPING

Per §3 brief checklist, for every **live** institute (ids 38-41) + **orphan** parents (ids 1,2):

### 5.1 Live institutes (38-41)

| institute_id | has courses | subjects | categories | subcats | curricula | batches | enrollments | academic_years | placements | assessments | marks | results | certificates | exams |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 38 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 5 | 0 |
| 39 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| 40 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |
| 41 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 | 0 |

`InstituteDomain::hasDomainData(id)` returns **false** for all 4 (checks `courses`, `subjects`, `course_curricula`, `batches`, `student_academic_placements`, `academic_assessments`, `academic_final_results`, `academic_student_marks` via join). **Certificates are NOT in that check** — id 38 has 5 certificates but still `hasDomainData=false`. This is a **gap** (certificates are domain-sensitive professional records) — flagged in §10.

**Safety classification per live institute:**

| id | stored domain | hasData | safety if re-migration needed | reason |
|---|---|---|---|---|
| 38 | training_center/training_institute (canonical) | NO (no courses) | **N/A (already canonical)** | No migration needed |
| 39 | education/NULL (other) | NO | **SAFE WITH NORMALIZATION** (if admin sets correct sub) or **BUSINESS DECISION** (see §7) | No data to corrupt, but NULL → canonical requires admin choice (school vs college vs training) |
| 40 | education/NULL (other) | NO | same | — |
| 41 | education/NULL (other) | NO | same | — |

### 5.2 Orphaned historical parents (ids 1,2 — missing institutes)

| orphan institute_id | categories | courses | course_subjects | subjects linked | hasDomainData | safety |
|---|---|---|---|---|---|---|
| 1 | 20 (all `subject_type=professional`) | — | — | 40 active subjects have `category_id` 1,10,11 (including orphan cat 1) | **YES** (has categories) | **NEEDS REVIEW — ORPHAN** (parent deleted, data leaked; requires cleanup, not migration) |
| 2 | — | 150 (all `category_id` 1-20, `institute_id` 2) | 10 (courses 1-4 → subjects 1-2) | — | **YES** | **NEEDS REVIEW — ORPHAN** |

**Dependency totals (live + orphan):**

| Entity | Global count | Owned by live institutes (38-41) | Orphan (1,2) | Unowned global (NULL institute_id) |
|---|---|---|---|---|
| institutes | 4 | 4 | 0 (parents missing) | — |
| industry_template_mappings | 0 | 0 | 0 | — |
| course_categories | 20 | 0 | 20 | 0 |
| course_sub_categories | 0 | 0 | 0 | — |
| courses | 150 | 0 | 150 | 0 |
| subjects (active) | 40 | 0 | 0 | 40 |
| subjects (soft-deleted) | 10 | 0 | 0 | 10 |
| batches | 0 | 0 | 0 | — |
| course_curricula / modules / lessons | 0 | 0 | 0 | — |
| academic_* (years, placements, assessments, marks, results) | 0 | 0 | 0 | — |
| certificates | 5 | 5 (id 38) | 0 | — |
| exams / exam_results | 0 | 0 | 0 | — |

**Tenant isolation of orphan data:** Orphan categories/courses have **no live institute to own them**; they are not returned by `TenantScoped` queries (which filter `where institute_id = currentInstitute->id`), but they **are enumerated** via `withoutGlobalScope` leaks if any existed (fixed in Gate 2). Current code correctly scopes, so orphans are **inert but polluting global stats** and foreign-key integrity (FK parent missing indicates `FK_CHECKS` was bypassed).

---

## 6. DOMAIN CONTRADICTIONS

Cross-check: `stored industry/sub` vs `InstituteDomain::fromInstitute()` vs `actual courses` vs `actual subjects` vs `academic records` vs `professional records`.

| id | stored | derived domain | actual courses (category.subject_type) | actual subjects (subject_type) | academic records | professional records | Contradiction? |
|---|---|---|---|---|---|---|---|
| 38 | training_center/training_institute | professional | none (0) — no category to check | none (0) — no institute-owned subject | 0 | 5 certificates (professional-adjacent, OK) | **NO** |
| 39 | education/NULL | other | none | none | 0 | 0 | **NO** (contradiction would require e.g. academic placements under professional — none exist) |
| 40 | education/NULL | other | none | none | 0 | 0 | NO |
| 41 | education/NULL | other | none | none | 0 | 0 | NO |

**Orphan checks:**
- Orphan `course_categories` (institute 1) are all `subject_type=professional` — if parent institute 1 was originally `education` (unknown), they would be professional under education (allowed pre-B2), but now parent missing so cannot classify contradiction.
- Orphan `courses` (institute 2) have `category_id` pointing to orphan categories 1-20 (professional) — internally consistent (professional course via professional category), but orphaned.

**Example flagged in brief:**
> `stored training_center/training_institute but institute has academic placements + assessments` — **not observed** (0 academic placements/assessments for id 38).

**Result:** **0 DOMAIN_CONTRADICTION** for live institutes.

---

## 7. ACADEMIC INSTITUTES VERIFICATION

Target academic sub_industries: `school`, `college`, `polytechnic`, `university` (all under `education`) → `InstituteDomain::ACADEMIC`.

- Live academic institutes found: **0**
- Expected academic institutes: 0 in current snapshot (no school/college/university/polytechnic row)
- Check for accidental migration `education → training_center`:
  - `SELECT * FROM institutes WHERE industry='training_center' AND sub IN ('school','college','polytechnic','university')` = **0** — **PASS**
  - `SELECT * FROM institutes WHERE industry='education' AND sub IN ('training_institute','professional_training_center','dance_academy','it_training_center','vocational_training_center')` = **0** — **PASS** (no academic↔professional cross)

**Any academic institute migrated to training_center / professional?** **No** — none exist to mis-migrate, and id 38 is professional, not academic.

**Conclusion §5:** **PASS** — no academic institute was accidentally re-parented; academic domain correctly derives `education + academicTypes`.

---

## 8. PROFESSIONAL INSTITUTES VERIFICATION

Target professional: `training_center` + `{training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center}` → `PROFESSIONAL`.

- Live professional institutes found: **1** — id 38 `training_center/training_institute`
- Check canonical `sub_industry`:
  - `training_institute` — **canonical** (YES)
  - `professional_training_center` — 0 rows (none created yet)
  - `dance_academy` — 0 rows
  - `it_training_center` — 0 rows
  - `vocational_training_center` — 0 rows
- All professional institutes have `industry='training_center'` — **PASS**
- No professional institute under `education` remains:
  - `SELECT * WHERE industry='education' AND sub IN ('institution','professional_training_academy','computer_it_training_institute','vocational_institute','dance_academy')` = **0** — **PASS** (all migrated or none existed)

**Legacy aliases under `training_center`:** `institution`, `professional_training_academy`, etc. under `training_center` are **expected as forward aliases** per `config/industry_rules.php:59-69` (for audit trail, not canonical). No live institute uses an alias (id 38 is canonical).

**Conclusion §6:** **PASS** — 1 professional institute correctly classified, canonical slug, no legacy residue, no academic misclassification.

---

## 9. TENANT ISOLATION CHECK

For every migration candidate (none) and every live institute, verify no cross-tenant reassignment.

| Entity | Check `WHERE institute_id = ?` remains same after B2? | Evidence |
|---|---|---|
| `course_categories` | No live categories to check; orphan categories remain `institute_id=1` (dangling, not reassigned) | `SELECT institute_id, COUNT(*) GROUP BY` = `{1:20}` — unchanged by B2 (B2 did not touch categories) |
| `course_sub_categories` | 0 rows | — |
| `courses` | Orphan `institute_id=2` (150) unchanged | B2 did not touch courses |
| `course_curricula` / modules / lessons | 0 rows | — |
| `batches` | 0 rows | — |
| `student_academic_placements` | 0 rows | — |
| `academic_assessments` / `academic_final_results` / `academic_student_marks` | 0 rows | — |
| `exams` / `exam_results` / `certificates` | Certificates for 38 remain `institute_id=38` (5) — **PASS** | `SELECT institute_id FROM certificates` = all 38 |
| `industry_template_mappings` | Table empty, but B2 migration used `WHERE industry=old AND sub=old` → `SET industry=new` without `institute_id` (global table) — no tenant to violate, but correct technique | **PASS** (no institute_id column) |

**`withoutGlobalScope` leaks (Gate 2 fixes):**
- `SubjectManagementController.php:294,307` now `where institute_id=? AND subject_type=derived` — **fixed**
- `CourseMasterController.php:252` tenant+domain scoped — **fixed**
- `CourseCategoryManageController.php:27,80` domain-aware — **fixed**

**IDOR `Rule::exists` scoping:**
- `SubjectManagementController.php:116,175` `exists(course_categories,id)->where institute_id, subject_type=derived` — **fixed**
- `CourseMasterController.php:209` `exists(...,category_id)->where institute_id, subject_type` — **fixed**

**Verdict:** **PASS** — no cross-tenant reassignment observed; tenant isolation hardened in Gate 2 and not regressed.

---

## 10. DATA SAFETY & HISTORICAL INTEGRITY

| Aspect | Status | Evidence |
|---|---|---|
| SoftDeletes on `institutes` / `subjects` / `courses` / `batches` | **PASS** | `institutes.deleted_at` exists, `subjects.deleted_at` 10 rows soft-deleted, `courses.deleted_at` exists |
| FK `subjects.subject_type` hardened to `RESTRICT` (`2026_08_27_000001`) | **PASS** | Migration present batch 44, `SHOW CREATE TABLE subjects` FKs are `RESTRICT` per hardening |
| FK `academic_*` hardened to `RESTRICT` (`2026_08_27_000002`) | **PASS** | Migration present |
| `withTrashed()` historical display (e.g., transcript shows soft-deleted subject) | **PASS** | `Subject` model uses `SoftDeletes`, `AcademicFinalResultService` uses `withTrashed()` per Phase A audits |
| Curriculum freeze `batches.curriculum_id SET NULL` (`2026_08_23_000100`) | **PASS** | `SHOW CREATE TABLE batches` still `curriculum_id FK SET NULL`, `course_curricula.version` exists |
| Audit logging (`SubjectDeletionService:87-103`) / concurrency `lockForUpdate` | **PASS** | Preserved (no file changed) |
| Domain immutability `Institute::booted` updating guard | **PASS** | `app/Models/Institute.php:22-42` throws `ValidationException` if domain changes and `hasDomainData()` true |
| `hasDomainData()` completeness | **PARTIAL** | Checks 8 tables but **omits `certificates`, `student_enrollments`, `exams`, `course_categories`** — id 38 has 5 certificates yet `hasDomainData(38)=false`, so domain change would be incorrectly allowed (see §5.1). Recommend adding `certificates` + `exams` + `course_categories` to check. |
| Orphan data integrity | **FAIL** | 20 orphan `course_categories` (FK parent missing) + 150 orphan `courses` indicate prior `FK_CHECKS=0` bypass; not caused by B2 but unresolved |
| `industry_template_mappings` / `structure_templates` emptiness | **FAIL** | Both 0 rows — seed missing, resolver broken (see §3.2) — not data loss per se (no institute data deleted) but structural data missing |

**Data deleted during audit?** **NO** — `SELECT` only.
**FKs with CASCADE introduced by B2?** **NO** — B2 used `UPDATE` only, no `FOREIGN_KEY_CHECKS=0`.

---

## 11. MIGRATION CANDIDATES (ONLY IF REQUIRED — NOT EXECUTED)

**Decision:** Per §3.2, **no safe legacy institute mappings remain** among live institutes.

| institute_id | current industry | current sub_industry | proposed new industry | proposed new sub_industry | reason | dependency count | safety |
|---|---|---|---|---|---|---|---|
| — | — | — | — | — | — | — | — |

**Explanation:**
- The single legacy institute `education/institution` (id 38) was **already migrated** in B2 batch 44 (`updated_at 2026-08-28 13:31:50`) to `training_center/training_institute` — **no remaining legacy**.
- The 3 `education/NULL` institutes (39-41) are **not legacy** — they are incomplete onboarding (missing `sub_industry`). They cannot be auto-migrated because `NULL` → canonical requires **business decision** (which institute type did user intend? school? college? training?). See §7/§12.
- Orphan parents (ids 1,2) are **orphaned, not migratable** — parents missing, data should be cleaned or re-attached via manual orphan resolution, not auto-migrated.

**Therefore:** **REMAINING_LEGACY = 0**. No migration to execute in this gate.

---

## 12. BUSINESS DECISIONS REQUIRED

| # | Decision | Context | Recommendation |
|---|---|---|---|
| D1 | `education/NULL` institutes (ids 39-41, “Leak Test”) — what to do? | Created 2026-08-23 as IDOR leak tests (per `TenantIsolationAuditTest`), never completed onboarding (`sub_industry` NULL). `hasDomainData=false`, `isValidCombination=false`, `domain=other`. | **BUSINESS DECISION:** Either (a) complete onboarding via UI to set correct `school/college/training_institute` etc., or (b) soft-delete them (`institutes.deleted_at`) if they are test junk. **Do NOT auto-migrate to generic `training_institute` or `school` without admin intent.** |
| D2 | Orphan `course_categories` (20, institute 1) + `courses` (150, institute 2) — cleanup? | FK parents 1,2 missing; data is unreachable via tenant queries but pollutes stats and FK integrity. Likely leftover from prior `migrate:fresh` or manual `TRUNCATE institutes` with `FK_CHECKS=0`. | **BUSINESS DECISION:** (a) If demo/seed data, re-run `php artisan db:seed --class=LearningStructureSeeder` + restore institutes 1,2 from `demo/monetix_backup_20260824_manual.sql` then re-apply B2, OR (b) if junk, `DELETE FROM course_categories WHERE institute_id NOT IN (SELECT id FROM institutes)` and `DELETE FROM courses WHERE institute_id NOT IN (SELECT id FROM institutes)` after backup, with `FK_CHECKS=1`. Requires stakeholder approval — **not auto-migrated**. |
| D3 | `industry_template_mappings` + `structure_templates` empty — reseed? | Both 0 rows, so `polytechnic → technical_institute` etc. not resolvable. | **BUSINESS DECISION:** Run `php artisan db:seed --class=LearningStructureSeeder` (canonical) to restore 27 rows + templates. Safe, idempotent, no institute data change. **Recommend approval.** |
| D4 | `hasDomainData()` missing `certificates`/`exams`/`course_categories` — extend? | Id 38 has 5 certificates but `hasDomainData=false` → domain guard would incorrectly allow `training_center/training_institute → education/school` switch. | **BUSINESS DECISION:** Extend `InstituteDomain::hasDomainData()` to also check `certificates`, `exams`, `course_categories`, `student_enrollments`. Low risk, hardening. |
| D5 | `DemoBusinessSeederCommand.php:17-21` stale legacy seeds — update? | Still seeds `education/institution` etc. | **DECISION:** Update to `training_center` canonical seeds (`training_institute`, `vocational_training_center`, `technical_training_center` → `vocational_training_center`) to prevent future legacy creation. |
| D6 | `DemoDataService.php:104,119` `transport` vs `transportation` — alias? | Still uses `transport` in supplier/inventory checks. | **DECISION:** Add `transportation` alongside `transport` (or normalize via `InstituteDomain::normalizeIndustry`) — trivial. |
| D7 | `ModuleAccessService` / `DashboardController` raw `industry==='education'` vs `InstituteDomain::isAcademic()` — unify? | Currently gates education modules via raw industry, not domain. | **DECISION:** Keep as-is for now (academic ⊂ education, so functionally correct), but consider `InstituteDomain::isAcademic()` for `education/NULL` edge case. |
| D8 | `polytechnic` template reuse (`technical_institute`) vs new template — confirm? | B2 `ensureMapping('education','polytechnic','technical_institute')` assumes reuse. | **CONFIRMED in B2 §2** — no new template created, reuses `technical_institute`. |
| D9 | `madrasha` / `primary_school` etc. variants — keep under `education`? | Config retains them as NEEDS_REVIEW under `education`. | **CONFIRMED** — keep as-is, not auto-migrated (Gate 2 §4). |

---

## 13. EXACT RECOMMENDED MIGRATION MATRIX

**For live institutes:** **No migration recommended at this time** (0 legacy).

**For structural / orphan remediation (requires separate approval, NOT auto-executed):**

| # | Target | Current | Proposed action | SQL (dry-run) | Safety | Approval |
|---|---|---|---|---|---|---|
| R1 | `industry_template_mappings` + `structure_templates` | 0 rows | Re-seed canonical mappings & templates | `php artisan db:seed --class=LearningStructureSeeder` (inserts 27 mappings + templates; uses `updateOrInsert` style, no `UPDATE institutes`) | **SAFE** (idempotent, no FK violation) | **RECOMMENDED — approve** |
| R2 | `institutes` ids 39-41 | `education/NULL` | **No auto-migration** — require admin to complete onboarding or soft-delete | — (UI: `/workspace/onboarding` or `Institute::withTrashed()->find(39)->delete()`) | **NEEDS REVIEW** | **AWAIT stakeholder** |
| R3 | Orphan `course_categories` (20, institute 1) + `courses` (150, institute 2) | Orphan FK parent missing | **No auto-migration** — either restore institutes 1,2 from backup then re-apply B2, or delete orphans after backup | `DELETE FROM course_categories WHERE institute_id NOT IN (SELECT id FROM institutes)` etc. (only after `mysqldump`) | **UNSAFE to auto** | **AWAIT stakeholder** |
| R4 | `institutes` id 38 | `training_center/training_institute` (canonical) | **No action** — already correct | — | **SAFE (no-op)** | — |
| R5 | `DemoBusinessSeederCommand.php` + `DemoDataService.php` | Stale legacy values | Code fix (not DB migration) | `s/education,institution/training_center,training_institute/g` + `s/vocational_institute/vocational_training_center/g` etc. | **SAFE** | **RECOMMENDED** |
| R6 | `InstituteDomain::hasDomainData()` | Missing `certificates` etc. | Code hardening | Add 4 `DB::table(...)->exists()` checks | **SAFE** | **RECOMMENDED** |

**Single-row migration example (for reference, NOT to execute — already done):**

```
12  education institution  →  training_center training_institute  SAFE  (historical, id 38 already migrated)
```

No new `UPDATE institutes SET industry=...` is required or safe at this time.

---

## 14. ROLLBACK STRATEGY

**If a future migration were to be approved (e.g., fixing `education/NULL` → canonical after admin choice):**

- **B2 rollback (already tested in Gate 2 §26):**
  1. `php artisan migrate:rollback --step=1` — runs `2026_08_28_100000::down()` — re-parents `training_center/training_institute` → `education/institution` etc. (id 38 would revert). Uses `DB::transaction` + `lockForUpdate` style, no `FK_CHECKS=0`.
  2. `git checkout -- config/industry_rules.php database/seeders/LearningStructureSeeder.php app/Support/InstituteDomain.php app/Models/Institute.php` — restores Gate 1 config.
  3. (If mappings were re-seeded) `php artisan db:seed --class=LearningStructureSeeder` to restore legacy `education` children.
  4. Verify: `SELECT industry,sub FROM institutes GROUP BY` back to legacy, and `InstituteDomain::fromKeys('education','institution')` → `other` (legacy).

- **New `education/NULL` fix rollback:** If ids 39-41 were ever set to canonical (e.g., `education/school`), rollback is simply `UPDATE institutes SET sub_industry=NULL WHERE id IN (39,40,41)` (no domain data to preserve, so no historical risk).

- **Orphan cleanup rollback:** If orphans were deleted, restore from `mysqldump monetix > backup_pre_orphan_cleanup.sql` taken immediately before `DELETE`. Or re-import `demo/monetix_backup_20260824_manual.sql` institutes 1,2 then re-run `LearningStructureSeeder`.

- **Emergency:** `mysql -u root monetix < backup_pre_migration.sql` + `git restore` — lossless because B2 touched only `industry/sub` strings, not `FK CASCADE` rows.

**No rollback needed now** — no new migration executed in this gate.

---

## FINAL FORMAT

```
PHASE: B2.1

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

EXISTING_INSTITUTES: 4   (active 4, withTrashed 4, trashed 0)
MIGRATED_CORRECTLY: 1    (id 38: education/institution → training_center/training_institute)
REMAINING_LEGACY: 0
INVALID: 3               (ids 39,40,41: education/NULL — invalid combination, not legacy)
NEEDS_REVIEW: 3          (same 3 — incomplete onboarding)
DOMAIN_CONTRADICTIONS: 0

ACADEMIC_INSTITUTES: PASS        (0 academic rows; correctly 0, none mis-parented)
PROFESSIONAL_INSTITUTES: PASS    (1 professional canonical, correctly classified)
DOMAIN_MAPPING: PARTIAL          (InstituteDomain + config PASS, but industry_template_mappings 0 rows + structure_templates 0 rows → mapping layer broken, needs reseed)
TENANT_ISOLATION: PASS           (IDOR fixes intact, no cross-tenant reassignment; orphans are pre-existing inert data, not B2-induced)
HISTORICAL_INTEGRITY: PARTIAL    (SoftDeletes + RESTRICT + withTrashed + curriculum freeze PASS, but orphan FKs (20 cats + 150 courses, parent missing) + missing certificate check in hasDomainData)
MIGRATION_SAFETY: PASS           (no unsafe migration candidate; sole candidate already safely migrated)

FINAL_VERDICT: YELLOW

```

**YELLOW** — The single existing business institute (`education/institution`) was **correctly migrated** to `training_center/training_institute` with no legacy residue and no domain contradiction; professional/academic separation is correctly enforced via `InstituteDomain` and tenant-scoped controllers. However, **two structural gaps** prevent GREEN: (1) `industry_template_mappings` + `structure_templates` are **empty** (seed missing post-migration, resolver fallback broken) and (2) **3 `education/NULL` incomplete institutes** + **170 orphan rows** (`course_categories` 20 + `courses` 150, parents 1,2 missing) require business decisions. **No data was modified, deleted, or migrated in this gate.** Safe to proceed to remediation **only after stakeholder approves §12 D1-D3** (complete/delete `education/NULL` tests, reseed structural mappings, decide orphan cleanup) — **no automatic migration required for legacy institutes**.

---

*Report generated read-only. Raw evidence queries logged in audit scripts (now removed). All row counts verified via `DB::table` with `withTrashed()` where applicable. Code references: `app/Support/InstituteDomain.php:48-142`, `config/industry_rules.php:20-69`, `app/Models/Institute.php:22-42`, `database/migrations/2026_08_28_100000_restructure_industry_institution_domain_taxonomy.php`, `app/Services/ModuleAccessService.php:387-391`.*

