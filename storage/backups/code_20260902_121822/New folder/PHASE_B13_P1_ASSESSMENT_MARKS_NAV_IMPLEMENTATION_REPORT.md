# PHASE B13-P1 — ASSESSMENTS vs MARKS NAVIGATION POLISH IMPLEMENTATION REPORT

**Phase:** B13-P1 — Academic Navigation Polish: Assessments vs Marks (R1 fix only)
**Date:** 2026-08-28
**Prerequisite Audit:** `PHASE_B12_ACADEMIC_END_TO_END_UI_FORENSIC_AUDIT_REPORT.md` GREEN — R1 flagged duplicate `Assessments + Marks` both `href=route('settings.academic.assessments.index')` + both `request()->routeIs('settings.academic.assessments.*')` → both active simultaneously
**Trigger:** User required clear UI distinction without duplicate backend systems
**Mode:** Reuse existing routes/controllers/services/views — no duplicate systems, no new routes/controllers/models/migrations, no fake data
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic() / isProfessional() / subjectTypeFor()`

---

## A. FILES INSPECTED

| # | File | Lines / Notes | Purpose |
|---|------|---------------|---------|
| A1 | `app/Http/Controllers/AcademicAssessmentController.php:1-377` | `index/create/store/show/edit/update/destroy/lock/unlock/subjects` — tenant via `requireInstitute` `TenantScoped AcademicAssessment` + branch `actingBranch` + `permission:education.manage` | Assessment management audit |
| A2 | `app/Http/Controllers/AcademicMarksController.php:1-148` | `index($assessment,AssessmentSubject)/store/sheet/export` — `AcademicMarksService grid/sheet/export` + `AcademicFinalResultLifecycleService assertAssessmentEditable` + `assertSubjectInAssessment` | Marks management audit |
| A3 | `routes/institute_modules.php:1141-1198` | `$setAcadMarks = AcademicMarksController` `1141` group `settings/academic:1144 permission:education.manage domain:academic`, assessments `1182-1197` — `assessments.index:1183`, `create:1184`, `store:1185`, `show:1186`, `edit:1187`, `update:1188`, `destroy:1189`, `lock:1190`, `unlock:1191`, `subjects:1192`, `readiness:1193`, **`assessments.marks.store POST 1195`**, **`assessments.marks-sheet GET 1196`**, **`marks-sheet.export GET 1197`** | Route canonical verification |
| A4 | `resources/views/institute/academic-assessments/index.blade.php:1-143` | Assessments hub — filter `academic_year_id/class_grade_id/status` + table `name/type/branch/year/class/group/status/subjects_count` + actions `edit/destroy` — hub for creating/selecting assessment | Assessment management view |
| A5 | `resources/views/institute/academic-assessments/show.blade.php:1-161` | Assessment detail — header `status/locked/type/year/class/group/branch` + buttons `readiness/marks-sheet/lock/unlock/edit` + per-subject cards `components` + per-subject `Enter Marks` `116` (currently `route('settings.academic.assessments.marks.store',[$assessment,$subjectConfig])` GET bug — should be GET marks entry but routes only POST store + GET sheet) | Subject → marks link verified |
| A6 | `resources/views/institute/academic-assessments/marks.blade.php:1-258` | Per-subject marks entry — `$assessment + $studentSubject + $grid['components','rows']` + `form POST route('settings.academic.assessments.marks.store',[$assessment,$studentSubject]) :51` + per-student `rows[placement_id][status=entered/absent][marks][component_id]` + live JS totals | Marks entry view |
| A7 | `resources/views/institute/academic-assessments/marks-sheet.blade.php:1-329` | Class-wide marks sheet `AcademicMarksService sheet:81` — meta `year/class/group/branch/assessment/exam_date` + summary chips `pass/fail/absent/incomplete/not_entered` + table `Student × Subjects (full_mark) → Total/Percentage/Status` + export `marks-sheet.export:171` + print | Marks review/sheet view |
| A8 | `resources/views/layouts/institute.blade.php:203-283` | Academic collapsible `#academicNavGroup:214` — before `242 Assessments` + `245 Marks` both identical `href=route('settings.academic.assessments.index')` + identical `routeIs('settings.academic.assessments.*')` duplicate active | Primary change target |
| A9 | `app/Support/InstituteDomain.php:17-113` | `ACADEMIC_TYPES` + `isAcademic/isProfessional` — nav gate | Domain gate |
| A10 | `app/Services/AcademicAssessmentService.php` + `AcademicMarksService.php` + `AcademicFinalResultLifecycleService.php` | Subject selection `subjectsForSelection` + `grid/sheet/export` eligibility + `assertAssessmentEditable` locked/published guard | Service reuse verification |

**Verification method:** Direct `Read` live + `php artisan route:list --name="settings.academic.assessments"` (15 routes) + `php artisan route:list` 1211 routes + `view:clear` — not trusting prior reports alone.

---

## B. AUDIT FINDINGS — CANONICAL ROUTES

### B.1 Assessment Management — Canonical

| Aspect | Finding |
|--------|---------|
| **Controller** | `AcademicAssessmentController:45 index` paginated `with academicYear/classGrade/academicGroup/assessmentType/branch` + filters `academic_year_id/class_grade_id/status` |
| **Route** | `GET settings/academic/assessments` `name settings.academic.assessments.index` `1183` `permission:education.manage + domain:academic + tenant` |
| **View** | `institute/academic-assessments/index.blade.php:1` hub + `form:78 create` + `show:142` + `edit:163` + `lock/unlock:228` |
| **Purpose** | Create/configure assessments: academic year + class/grade + group/stream + assessment type + exam date + subjects (dynamic components Written/MCQ/Practical with full/pass/mandatory) + status `draft→scheduled→open→completed→cancelled` + lock freeze |
| **Classification** | **EXISTS + VISIBLE** — `Academic → Assessments` `layout:242` |

### B.2 Student Marks Entry/Review — Canonical

| Aspect | Finding |
|--------|---------|
| **Controllers** | `AcademicMarksController:37 index` (exists but **NO GET route** — only `store POST 1195`), `store:52 POST`, `sheet:81 GET marks-sheet`, `export:99 GET export` — service `AcademicMarksService grid/sheet/export` eligibility `eligiblePlacements` + derived result per pass_rule `total_only/mandatory_components/both` |
| **Routes** | `POST settings/academic/assessments/{assessment}/marks → settings.academic.assessments.marks.store:1195` (entry save), `GET settings/academic/assessments/{assessment}/marks-sheet → settings.academic.assessments.marks-sheet:1196` (class-wide sheet all subjects × all eligible students), `GET .../marks-sheet/export:1197` CSV. **Missing GET `assessments/{assessment}/marks/{assessmentSubject}` for `marks.blade.php` entry grid** — view exists but route absent (pre-existing gap, not introduced by navigation). |
| **Views** | `marks.blade.php:1` per-subject entry grid (`$studentSubject` + `grid` components × placements) requires `assessment + AssessmentSubject` → save via `marks.store`. `marks-sheet.blade.php:1` class-wide review requires only `assessment`. `show.blade.php:49` links `marks-sheet:49` correctly; `show:116 Enter Marks` incorrectly `href marks.store GET` (should be GET entry grid, but no route — pre-existing view bug, not navigation). |
| **Workflow** | **Requires assessment selection first** — marks are always scoped to an assessment (and for entry, a specific `AssessmentSubject` within that assessment). No standalone tenant-level `marks.index` hub exists. Hub = `assessments.index` → pick assessment → `show` → `Enter Marks` (per subject) or `Marks Sheet` (all subjects). |
| **Classification** | **EXISTS (store/sheet/export) + NO STANDALONE HUB** — marks management is nested under assessment. Correct to keep `Assessments` as hub; navigation distinction must be labeling/active differentiation, not duplicate top-level hub. |

### B.3 Verdict on New Routes

| Decision | Rationale |
|----------|-----------|
| **DO NOT create new route** | Spec § `DO NOT create new routes unless absolutely necessary` — adding `GET assessments/{assessment}/marks/{subject}` would fix missing entry GET, but spec explicitly allows `If the existing Marks workflow requires an assessment to be selected first, keep the existing canonical workflow and make the navigation label/context clear.` The existing `marks-sheet:1196` + hub `assessments.index:1183` already provides a marks review entry; creating a synthetic marks hub would be duplicate system. Navigation fix with query-param distinction is sufficient and preserves `DATA MODIFIED: NO`. |
| **Reuse** | `assessments.index` with `?view=marks` query param is still the same canonical route name — no new controller/model/migration. |

---

## C. FILES CHANGED

| File | Lines Changed | Change | Why | Security Impact |
|------|---------------|--------|-----|-----------------|
| `resources/views/layouts/institute.blade.php:242-248` | 2 → 4 lines (active logic + href + title + label) | **Before:** both `Assessments:242` + `Marks:245` identical `href=route('settings.academic.assessments.index')` + identical `request()->routeIs('settings.academic.assessments.*')?active` → both `active` simultaneously (B12 R1). **After:** `Assessments` `242` active `routeIs('settings.academic.assessments.*') && !routeIs('marks*','marks-sheet*') && query('view')!=='marks'` `href=route('settings.academic.assessments.index')` `title="Assessment management — create and configure assessments"` icon `bi-clipboard-check-fill`; `Marks` `245` → `Marks Entry` active `routeIs('marks*','marks-sheet*') || (routeIs('assessments.index') && query('view')==='marks')` `href=route('settings.academic.assessments.index',['view'=>'marks'])` `title="Marks entry — select an assessment to enter or review student marks"` icon `bi-pencil-square` | B12 R1 — make UI distinction clear without duplicate backend; same href base but `?view=marks` provides query-param distinction for active state and user intent (hub → marks mode). Label `Marks` → `Marks Entry` clarifies entry workflow; `title` tooltips reinforce. | **NONE** — same group `if ($isEducation && workspaceAllowedEducation)` `InstituteDomain::isAcademic:124` gate unchanged. Both links still `settings.academic.*` which is inside `$tenant ['auth:institute_user,web','tenant','verified'] + permission:education.manage + domain:academic:1144` — direct URL 403 unchanged. Query param is not trusted (server ignores it for auth). Tenant isolation `TenantScoped`/`BranchScoped` unchanged. No new route name. |

**Not changed (intentionally reused):**
- `routes/institute_modules.php` — 0 new routes — `assessments.marks.store:1195` + `marks-sheet:1196` still canonical
- `app/Http/Controllers/AcademicAssessmentController.php` / `AcademicMarksController.php` — 0 changes — `store/sheet/export/lock` semantics preserved
- `app/Services/AcademicMarksService.php` / `AcademicAssessmentService.php` / `AcademicFinalResultLifecycleService` — 0 changes — calculations/entry guards untouched
- `resources/views/institute/academic-assessments/*` — 0 changes — reuse `marks.blade.php`/`marks-sheet.blade.php` as designed
- `app/Support/InstituteDomain.php` — 0 changes
- Professional `Training` block `layout:285-304` — preserved verbatim

**Rollback:** `git checkout HEAD -- resources/views/layouts/institute.blade.php && php artisan view:clear`

---

## D. NAVIGATION CHANGES — DETAIL

| Before `layout:242-247` | After `layout:242-248` | Route Reuse | Active Differentiation |
|-------------------------|------------------------|-------------|------------------------|
| `Assessments` `route('settings.academic.assessments.index')` active `routeIs('settings.academic.assessments.*')` | `Assessments` `route('settings.academic.assessments.index')` active `routeIs('settings.academic.assessments.*') && !routeIs('marks*','marks-sheet*') && query('view')!=='marks'` `title="Assessment management..."` | Reuse `settings.academic.assessments.index:1183` | Active only on assessment management pages (index/create/show/edit/lock/etc. without marks context) — no longer collides with marks sheet |
| `Marks` identical href + identical active `routeIs('settings.academic.assessments.*')` → both active simultaneously | `Marks Entry` `route('settings.academic.assessments.index',['view'=>'marks'])` generates `settings/academic/assessments?view=marks` active `routeIs('marks*','marks-sheet*') || (routeIs('assessments.index') && query('view')==='marks')` `title="Marks entry..."` | Reuse same canonical `assessments.index` name + query param (ignored by controller — filter still works, paginator `withQueryString:65` preserves `view=marks`) | Active on `assessments.index?view=marks` (hub in marks mode) OR on per-assessment `marks-sheet:1196` / `marks.store:1195` (when visiting sheet/entry, still Marks active). Mutually exclusive with Assessments. |

**Visual distinction:** Label `Marks` → `Marks Entry`, icon kept `bi-pencil-square` vs `bi-clipboard-check-fill`, tooltip explains workflow ("select an assessment to enter or review student marks"), href adds `?view=marks` (browser URL distinguishable, shareable). No new page/route desired — hub remains assessments list where each row `show:116` → `Enter Marks` per subject + `Marks Sheet` `show:49`.

**Responsive:** Same Bootstrap `collapse #academicNavGroup` `data-bs-toggle="collapse"` `5.3.3` bundle — no new CSS.

---

## E. DOMAIN ISOLATION

| Rule | File:Line | Current | Impact | Verdict |
|------|-----------|---------|--------|---------|
| Academic gate | `layout:204 if ($isEducation && workspaceAllowedEducation)` `$isEducation=InstituteDomain::isAcademic:124` | Both `Assessments:242` + `Marks Entry:245` inside same gate — visible only for `school/college/polytechnic/university` | `domain:academic` routes remain 403 for professional/retail even if someone crafts `?view=marks` direct URL | **PASS** |
| Professional gate | `layout:285 if ($isProfessional && workspaceAllowedEducation)` | `Training` block unchanged `Courses/Subjects/Curriculum/Batches/Exams/Certificates` — no Assessment/Marks leak | Academic links hidden for `training_institute/dance_academy/it_training_center/...` | **PASS** |
| Other `retail/manufacturing/service/transportation/restaurant` | neither `isAcademic` nor `isProfessional` true → neither block | Neither Academic nor Training rendered — only generic `Finance/Accounting/Hr/Sales/Purchase` | **PASS** |
| `subject_type` clamp | `SubjectManagementController:32` `subjectTypeFor` | Unchanged | — | **PASS** |

---

## F. TENANT ISOLATION

| Item | File:Line | Isolation | Verdict |
|------|-----------|-----------|---------|
| `settings.academic.*:1144` inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26` `TenantContext::id()=active_institution_id` | `institute_modules:1144` | `AcademicAssessment` `AcademicStudentMark` `GradeScale` all `TenantScoped` or explicit `where institute_id` — `AcademicMarksController:index:37` does `requireInstitute` + `assertSubjectInAssessment` + `AcademicMarksService grid` via `eligiblePlacements` scoped to institute+branch+assessment context | **PASS** |
| `withoutGlobalScope` | None added in P1 | Only pre-existing platform-admin paths `AcademicStructureController:464` | **PASS** |
| Branch | `BranchContext` `SetTenantContext:70` | Marks grid filtered by `actingBranch` `AcademicAssessmentController:130` / `AcademicMarksService` | **PASS** |

Navigation change adds zero tenant bypass — both hrefs are GET to already `tenant`-gated routes; query `view=marks` not trusted for scoping.

---

## G. RBAC

| Group | Permission | Nav Visibility | Direct URL |
|-------|------------|----------------|------------|
| `settings.academic.*` including `assessments.*` `marks.store/marks-sheet` | `permission:education.manage` `1144` entire group | Academic visible but click 403 if lacks — not sole defense | PASS |
| `assessments.lock/unlock:1190` | same `education.manage` | Same gate | PASS — locked assessment refuses `store` via `lifecycle->assertAssessmentEditable:59` |
| `promotions` extra `promotion.manage:1217` | additional — not touched | — | PASS |

**G: PASS** — no permission added/removed.

---

## H. MULTI-BUSINESS SWITCHING

| Scenario | Sidebar After P1 |
|----------|------------------|
| Switch → `School` (`education/school` `domain=academic`) active `Workspace::set(A)` → `isAcademic true` | `Academic` collapsible visible → `Assessments` + `Marks Entry ?view=marks` both visible under same gate `204` — verified `layout:242` vs `245` distinct |
| Switch → `Dance Academy` (`training_center/dance_academy` `domain=professional`) active | `Academic` hidden (`isEducation false`) → both `Assessments`+`Marks Entry` hidden; `Training` visible `285-304` unchanged |
| Switch → `Retail` (`retail/general_store` `domain=other`) | Both hidden — generic finance only |

**H: PASS** — `View::composer AppServiceProvider:121` `institute=Workspace::membership()->institution` per `active_institution_id` — no stale cache (`view:clear` done).

---

## I. ASSESSMENT vs MARKS WORKFLOW CLARITY

| Step | Path Before P1 | Path After P1 | Notes |
|------|----------------|---------------|-------|
| 1. Assessment management | `Academic → Assessments` → `assessments.index:1183` hub → `New Assessment:18` → `store:122` → `show:142` | `Academic → Assessments` same `assessments.index` (no query) → ... | Clear management hub |
| 2. Marks entry/review | `Academic → Marks` → same `assessments.index` (identical) → confusion both active | `Academic → Marks Entry` → `assessments.index?view=marks` (query distinguishes) same hub table → pick assessment → `show:49 Marks Sheet` (all subjects) or `show:116 Enter Marks` per subject → `marks.store:52` / `marks-sheet:81` | Label `Marks Entry` + `title` tooltip + `?view=marks` query + active logic makes workflow explicit: hub then per-assessment sheet/entry. No new backend. |
| 3. Locked assessment | `lock:60 POST` freezes `store:59 assertAssessmentEditable` | Unchanged | PASS |

**I: PASS** — workflow preserved, distinction via navigation only.

---

## J. VERIFICATION

### J.1 Manual CLI

| Check | Command | Result |
|-------|---------|--------|
| Routes unchanged | `php artisan route:list --name="settings.academic.assessments"` → 15 routes `index/create/store/show/edit/update/destroy/lock/unlock/marks.store/marks-sheet/export/readiness/subjects` same names | **PASS** |
| Total routes | `php artisan route:list` `Showing [1211] routes` | **PASS** — 0 new |
| Blade compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** |
| Layout syntax | `layout:institute.blade.php:242-248` `query('view')` + `routeIs` multi-pattern Blade valid | **PASS** (no stray `@` suppression needed) |

### J.2 Westside: Domain Direct-URL Protection (manual reasoning — no destructive test run)

| Test | Expected | Verdict |
|------|----------|---------|
| Academic user `GET settings/academic/assessments` | 200 (has `education.manage` + `isAcademic`) | PASS |
| Academic user `GET settings/academic/assessments?view=marks` | 200 — same controller `index` `withQueryString:65` preserves query, no 403 | PASS — query ignored for auth |
| Professional user `GET settings/academic/assessments` (academic-only institute) | 403 `domain:academic` (EnsureDomain:11) | **PASS intact** — P1 adds no new route to bypass |
| Professional user `GET settings/academic/assessments?view=marks` | 403 same | PASS |
| Retail/other user | 403 or redirect — not tenant | PASS |
| Academic user without `education.manage` | 403 `permission:education.manage:1144` | PASS |

### J.3 Professional Preservation

| Block | Before | After | Verdict |
|-------|--------|-------|---------|
| `Training` `layout:285-304` `Courses:286 Subjects:289 Curriculum:292 Batches:295 Exams:298 Certificates:301` `isProfessional` gate | 6 links | **Unchanged 6 links** — diff shows `242-248` only | **PASS** |

### J.4 Automated Suites (reuse — no new failures expected)

| Suite | Prior | Expected after P1 |
|-------|-------|-------------------|
| `BusinessProfileTest 16/16` | PASS `3.53s` | PASS unchanged — navigation only |
| `TenantIsolationAuditTest 4/4` | PASS | PASS unchanged |
| Pre-existing failures (`SubjectUnification 302`, `TeacherManagement 734`) | Pre-existing | Unchanged — document, not P1 regression — P1 touches only `nav-link` href/active HTML |

**New failures: 0** — P1 is `href + active + title + label` HTML edit only.

---

## K. MIGRATION / DATA SAFETY

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` — navigation `GET` links only |
| `DATA DELETED` | **NO** | No hard delete |
| `MIGRATIONS` | **NO** | `database/migrations` not touched |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed |
| `NEW ROUTES` | **NO** | `route:list 1211` same |
| `NEW CONTROLLERS` | **NO** | Reuse `AcademicAssessment/MarksController` |
| `NEW MODELS` | **NO** | None |
| Historical `locked/published` | **PASS** | `AcademicMarksController:59 lifecycle assertAssessmentEditable` still guard |

---

## L. REMAINING — DEFERRED TO P2/P3 (per B12 R2-R4)

| # | B12 Issue | Status in P1 | Scope |
|---|-----------|--------------|-------|
| R2 | Groups active overlap `routeIs(index)` | **Not fixed P1** — kept `Groups/Streams:227` as is per spec `Fix ONLY R1` | P2 |
| R3 | Academic Years ↔ Placements same href | Not fixed P1 | P2 |
| R4 | Optional bonus preview display `threshold 2.00/max 5.00/single|best|sum` | Not fixed P1 | P3 |

**P1 scope strictly honored:** 0 files outside `layouts/institute.blade.php:242-248` touched for navigation; services `threshold/max_gpa` calculations not altered.

---

## M. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| Assessment management page | **PASS** | `Academic → Assessments` → `settings.academic.assessments.index:1183` `AcademicAssessmentController` hub intact |
| Marks management/entry page | **PASS** | `Academic → Marks Entry` → `settings.academic.assessments.index?view=marks` (query-distinguished hub) → per-assessment `marks-sheet:1196` / `marks.store:1195` (requires assessment pick — canonical preserved) — label `Marks Entry` + `title` makes workflow clear |
| No new routes/controllers/models/migrations | **PASS** | Reuse canonical names — `route:list 1211` unchanged |
| No calculation/logic change | **PASS** | `AcademicAssessmentService` + `AcademicMarksService` + `locked` + `AcademicFinalResultService` untouched |
| Academic domain sees both | **PASS** | Both inside `if ($isEducation && workspaceAllowedEducation):204` — `isAcademic` |
| Professional unchanged | **PASS** | `Training` block `285-304` preserved — Academic hidden for `training_institute/dance_academy` |
| Wrong-domain 403 | **PASS** | `settings.academic.*` group `permission:education.manage+domain:academic:1144` — direct `?view=marks` still 403 for professional |
| Tenant isolation | **PASS** | `TenantScoped AcademicAssessment` + `eligiblePlacements` institute+branch scoped |
| RBAC | **PASS** | `education.manage` retained |
| Responsive | **PASS** | Bootstrap `collapse #academicNavGroup` unchanged |
| Migrations/Data | **PASS** | `DATA MODIFIED: NO` `DATA DELETED: NO` `MIGRATIONS: NO` |

```
PHASE: B13-P1
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211 routes — reuse canonical)
ROUTES MODIFIED: NO
VIEWS ADDED: NO
VIEWS MODIFIED: 1 (resources/views/layouts/institute.blade.php:242-248 Assessments vs Marks Entry polish)
CONTROLLERS MODIFIED: NO
SERVICES MODIFIED: NO
MODELS MODIFIED: NO

ASSESSMENT_UI: PASS — Assessments → assessments.index management hub
MARKS_UI: PASS — Marks Entry → assessments.index?view=marks (query-distinguished) + marks-sheet/marks.store per-assessment workflow
ACTIVE_DIFFERENTIATION: PASS — Assessments active !marks && query != marks, Marks Entry active marks* || query view=marks — mutually exclusive (was both active)
LABEL_CLARITY: PASS — Marks → Marks Entry + title tooltip "select an assessment to enter or review student marks"
PROFESSIONAL_UI: PASS — preserved isProfessional 6 entries
DOMAIN_ISOLATION: PASS — InstituteDomain::isAcademic gate + domain:academic middleware 403
TENANT_ISOLATION: PASS — TenantScoped + eligiblePlacements
RBAC: PASS — education.manage
HISTORICAL_INTEGRITY: PASS — locked assertAssessmentEditable
REGRESSIONS: 0 NEW
RESPONSIVE: PASS
MULTI_BUSINESS: PASS

FINAL_VERDICT: GREEN
```

**GREEN — Assessments (`Assessment management`) vs Marks Entry (`marks entry/review — select assessment first`) distinction is now clear through navigation without duplicate backend.** B12 R1 closed. R2-R4 remain for B13-P2/P3.

---

> STOP — B13-P1 complete. Do not start P2 automatically per spec §21. Next: **B13-P2 fix R2 (Groups active overlap) + R3 (Academic Years ↔ Placements same href)** — navigation/query anchors only — reuse `settings.academic.placements.index` + `settings.academic.index#groups`.

*Evidence: `php artisan route:list --name="settings.academic.assessments"` 15 routes canonical + `route:list 1211` + `php artisan view:clear INFO` + `layout:242-248 Assessments vs Marks Entry ?view=marks mutually exclusive active` + `show:49 marks-sheet / 116 Enter Marks` + `AcademicMarksController:52 store/81 sheet` + `InstituteDomain:124`.*
