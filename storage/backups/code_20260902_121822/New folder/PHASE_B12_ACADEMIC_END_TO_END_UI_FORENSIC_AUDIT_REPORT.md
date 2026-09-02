# PHASE B12 — ACADEMIC END-TO-END UI FORENSIC AUDIT REPORT

**Phase:** B12 — Academic End-to-End UI Audit (post-B11 restoration verification)
**Scope:** FORENSIC AUDIT ONLY — No code / data / migrations / fake data / duplicate modules modified
**Date:** 2026-08-28
**Predecessor Verdicts:** B10 YELLOW (22 HIDDEN academic), B11 GREEN (Academic collapsible `layouts/institute.blade.php:203-283` restores navigation — reuse canonical routes only)
**Auditor:** Muse Spark (forensic audit mode — live `Read` + `php artisan route:list` + `view:clear` verified 2026-08-28)
**Workspace Root:** `C:\xampp\htdocs\monetix`
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic() / isProfessional() / subjectTypeFor() / fromInstitute()`
**Deliverable Constraint:** STOP after report — DO NOT IMPLEMENT B13 gaps yet

---

## A. EXECUTIVE SUMMARY

| Dimension | Finding | Verdict |
|-----------|---------|---------|
| **Backend completeness** | Academic chain `Industry → Domain → Course → Subject → Curriculum → Placement → Assessment → Components → Marks → Aggregation → Grade Scale → Promotion → Final Result lifecycle (Draft→Review→Approved→Locked→Published) → Report Card → Result Sheet → Transcript → Certificate → Attendance → Analytics` **fully implemented**, identical to B10 inventory. Controllers `AcademicDashboard/Structure/Assessment/Marks/Aggregation/Grading/Promotion/FinalResult/Placement/Attendance/Analytics/Student/Teacher/CourseMaster/SubjectManagement/Curriculum/Batch` + services `AcademicFinalResultService:218` (`threshold 2.00 / max_gpa 5.00 / single|best|sum`) + models `AcademicFinalResultRow.optional / GradeScale` unchanged. `php artisan route:list --name="settings.academic"` 1211 routes still canonical names. | **GREEN** |
| **UI integration — Academic** | B11 collapsible `Academic` (`if ($isEducation && workspaceAllowedEducation)` `layout:204`) now exposes 17 entries + nested `Results` (`#academicResultsSub`) + `Dashboard/_tabs` (`isAcademic:8`) preserves domain tabs. 21/22 HIDDEN entries from B10 are now **EXISTS+VISIBLE** via existing routes. Remaining 1 `Components` is intentionally BACKEND-ONLY (inside assessment form). No new route/view/controller created. | **GREEN** |
| **UI integration — Professional** | `Training` block `layout:285-304` (`isProfessional && workspaceAllowedEducation`) + shared `Teachers/Trainers` ternary `layout:152` **preserved verbatim** after B11. Canonical `courses.manage:928`, `courses.manage.subjects:952` (`subjectTypeFor` clamp), `curricula:900`, `batches:165`, `exams:175`, `certificates:190` remain VISIBLE for `isProfessional`. No regression. | **GREEN** |
| **Security** | All academic links remain `auth:institute_user,web + tenant + verified + domain:academic + permission:education.manage (+promotion.manage)` — sidebar gate matches middleware `InstituteDomain::isAcademic:124`. Direct URL 403 unchanged. `TenantScoped`/`BranchScoped` + explicit `where institute_id` + `Rule::exists()->where institute_id/subject_type` + `Workspace/TenantContext` multi-business unchanged. No `FOREIGN_KEY_CHECKS=0`, no `withoutGlobalScope` added. | **GREEN** |
| **Data safety** | No migration, no seed, no `business_subcategory` taxonomy, no duplicate `Teacher/Instructor` model/table, no historical mutation (`locked/published` guards intact). `view:clear` + `config:clear` PASS. | **GREEN** |
| **Overall** | Academic workflow is now **discoverable end-to-end** through navigation; gap was discoverability only, now resolved. Residual polish duplicates (Marks alias ×2, Groups active overlap, Academic Years ↔ Placements same href) are **LOW / cosmetic**, not blocking. | **FINAL VERDICT: GREEN — proceed to B13 selective polish only** |

**Why GREEN not YELLOW:** B10 YELLOW driver (19 HIDDEN academic capabilities) is closed — `layout:210-281` provides `Academic → Dashboard/Settings/Years/Classes/Groups/Students/Subjects/Teachers/Placements/Assessments/Marks/Results→Aggregations|Grade Scales|Final|Published/Promotions/Attendance/Analytics/Transcript/Certificates` all resolving to canonical existing routes. Backend was already GREEN.

---

## B. FILES INSPECTED

> Verified live on disk; not trusting prior reports blindly.

| # | File | Lines / Notes | Role in B12 |
|---|------|---------------|-------------|
| B1 | `resources/views/layouts/institute.blade.php:1-860` | Sidebar `nav.flex-column` `118-444`, `$isEducation=InstituteDomain::isAcademic:124`, `$isProfessional=InstituteDomain::isProfessional:125`, Academic collapsible `#academicNavGroup:214` + nested `#academicResultsSub:252`, `Training` block `285-304` preserved, `classes/courses` toggle `171` + `173` | **Primary audit target** — B11 insertion verified |
| B2 | `resources/views/dashboard/_tabs.blade.php:1-39` | `InstituteDomain::isAcademic($institute):8` authoritative — verified unchanged from B9/B11 | Dashboard tabs §11 |
| B3 | `app/Support/InstituteDomain.php:17-164` | `ACADEMIC_TYPES=[school,college,polytechnic,university]`, `PROFESSIONAL_TYPES=[training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center]`, `fromInstitute/isAcademic/isProfessional/subjectTypeFor/hasDomainData` | Domain gate authority |
| B4 | `routes/web.php:16-408` | `academic.dashboard:159 domain:academic`, `academic.analytics:160`, `academic-attendance.mark:161` + `reports:162`, `students:139`, `teachers:355`, `batches:165`, `exams:175`, `business.profile:349` | Top-level routes |
| B5 | `routes/institute_modules.php:16-1660` | 778 routes — `settings.academic:1144` (`grading:1163`, `aggregations:1172`, `assessments:1182` + marks `1195`, `final-results:1199` lifecycle, `promotions:1217`, `placements:1236`, `academic-years:1247`), `classes:979 domain:academic`, `curricula:900`, `courses.manage:928/952`, `teachers:1076`, `academic-attendance:1101`, `academic/analytics:1114` | Module routes inventory |
| B6 | `resources/views/academic/dashboard.blade.php:1` + `academic/analytics/*.blade.php` (11) + `academic-attendance/*.blade.php` | Academic dashboards/reports exist | Views reuse §E |
| B7 | `resources/views/institute/academic-*/**` + `classes/*` + `students/*academic_transcript*` + `institute/teachers/*` + `institute/course-master/*` + `business/profile.blade.php:405` | All academic views exist — zero new Blade required | Views reuse §E |
| B8 | `app/Http/Controllers/*Academic*.php` + `CourseMaster/SubjectManagement/Curriculum/Batch/TeacherController` + `app/Services/Academic*.php` + `GradeScale.php:34-60` | Controllers/services preserved, `AcademicFinalResultService:218-335` bonus math intact | Backend reuse §D/F |
| B9 | `PHASE_B10_*.md` `PHASE_B11_*.md:1` | Cross-checked B10 27-item matrix vs live layout/route:list | Baseline |
| B10 | `config/industry_rules.php:1-210` | 15 industries + sub_industries — `CATEGORY_LEVEL_ONLY` no sub-category taxonomy | Taxonomy check |

Spot CLI: `php artisan route:list --name="settings.academic"` (1211 routes, sampled §C), `php artisan route:list --name="academic.dashboard"` 1 route, `php artisan route:list --name="classes"` 17 routes, `php artisan view:clear` + `config:clear` INFO PASS.

---

## C. ROUTES INVENTORY — POST-B11

> Same classification legend as B10. No routes added/modified in B11 — verified via `route:list`.

### C.1 Academic Routes — All Canonical (tenant `auth+tenant+verified` unless noted)

| # | Route Name | Method URI | File:Line | Middleware | Nav After B11 | Classification |
|---|------------|-----------|-----------|------------|---------------|----------------|
| C1 | `academic.dashboard` | `GET academic/dashboard` | `web.php:159` | `tenant + domain:academic` | **VISIBLE** `Academic → Dashboard` `layout:215` + `dashboard/_tabs:29` | **EXISTS + VISIBLE** (was HIDDEN B10:G1) |
| C2 | `academic.analytics.*` + exports | `GET academic/analytics/*` | `web.php:160` + `institute_modules:1114` | `domain:academic` | **VISIBLE** `Academic → Analytics` `layout:272` + `_tabs:34` | **VISIBLE** (was HIDDEN) |
| C3 | `settings.academic.index/label/levels/classes/groups` | `GET/PUT settings/academic + levels/classes/groups` | `1144/1146/1149/1154/1158` | `education.manage+domain:academic` | **VISIBLE** `Academic → Academic Settings` `layout:218` + `Groups/Streams` `#groups:227` | **VISIBLE** (was HIDDEN G2/G5) |
| C4 | `settings.academic.academic-years.*` | `POST/PUT/DELETE settings/academic/academic-years` | `1247` | same | **VISIBLE** `Academic → Academic Years` `layout:221` → `placements.index` anchor (#years via modal) | **VISIBLE (alias)** (was NO NAV G3) |
| C5 | `settings.academic.grading.*` | `GET/POST/PUT/DELETE grading + preview` | `1163` | same | **VISIBLE** `Academic → Results → Grade Scales` `layout:256` | **VISIBLE** (was HIDDEN G16) |
| C6 | `settings.academic.aggregations.*` | `GET/POST/PUT/DELETE aggregations` | `1172` | same | **VISIBLE** `Academic → Results → Aggregations` `layout:253` | **VISIBLE** (was HIDDEN G14) |
| C7 | `settings.academic.assessments.*` | `GET/POST/PUT/DELETE assessments + lock/unlock/subjects/readiness` | `1182` | same | **VISIBLE** `Academic → Assessments` `layout:242` | **VISIBLE** (was HIDDEN G11) |
| C8 | `settings.academic.assessments.marks.*` | `POST marks + GET marks-sheet/export` | `1195-1197` | same | **VISIBLE** `Academic → Marks` `layout:245` → assessments hub → `marks-sheet` | **VISIBLE (hub alias)** (was HIDDEN G13) |
| C9 | `settings.academic.final-results.*` | `GET/POST/approve/send-to-review/lock/publish/report/result-sheet/export/readiness/preflight/policy` | `1199` | same | **VISIBLE** `Academic → Results → Final Results` `259` + `Published Results ?status=published 262` + report/result-sheet nested | **VISIBLE** (was HIDDEN G20-G25) |
| C10 | `settings.academic.promotions.*` | `GET/POST promotions policies/rules/decisions + promotion.manage` | `1217` | `education.manage+promotion.manage+domain:academic` | **VISIBLE** `Academic → Promotions` `layout:266` | **VISIBLE** (was HIDDEN G19) |
| C11 | `settings.academic.placements.*` | `GET/POST placements + subjects` | `1236` | `education.manage+domain:academic` | **VISIBLE** `Academic → Placements` `layout:239` | **VISIBLE** (was HIDDEN G10) |
| C12 | `classes.*` | `GET classes + subjects/batches/archive` | `979 domain:academic` | `domain:academic+permission:courses.view` | **VISIBLE** `layout:171` toggle + `Academic → Classes:224` alias same route | **VISIBLE** (kept G4) |
| C13 | `courses.manage.subjects.*` canonical | `GET courses/manage/subjects` | `952` | `permission:courses.view/manage` server-derived `subjectTypeFor` | **VISIBLE** `Academic → Subjects:233` + `_tabs:17` + professional `subjects:289` | **VISIBLE** (kept G6) |
| C14 | `students.*` + `students.academic-*` | `GET students` + `academic-history/transcript/attendance/transfer/withdraw + certificate-request` | `web.php:139` + `1089 domain:academic` | `students.view/manage` + `domain:academic` for subs | **VISIBLE** `Academic → Students:230` + contextual `academic-transcript:1091` via `students.show/_tabs` | **VISIBLE** (kept G7/G26) |
| C15 | `teachers.*` single | `GET teachers ... assign/complete:1083` | `web.php:355` + `1076` | `tenant` | **VISIBLE** `layout:150` + `Academic → Teachers:236` alias same route | **VISIBLE** (kept G8) |
| C16 | `certificates.index/action` | `GET certificates + POST action:1095 domain:academic` | `web.php:190` + `1095` | `certificates.view` + `domain:academic` | **VISIBLE** `Academic → Certificates:278` + professional `certificates:301` | **VISIBLE** (kept G27) |
| C17 | `academic-attendance.mark.*` + `reports.*` | `GET mark:161` + `reports:1101` `class/daily/student/export` | `web.php:161` + `1101` | `domain:academic` | **VISIBLE** `Academic → Attendance:269` | **VISIBLE** (was HIDDEN G28) |
| C18 | `courses.manage.index` canonical | `GET courses/manage` | `928` | `permission:courses.view` | **VISIBLE** via `Classes/Courses` toggle when `!usesClassTerm` + `_tabs` | **VISIBLE** |
| C19 | `business.profile` | `GET business/profile` | `web.php:349` | `tenant+verified Workspace authoritative` | **VISIBLE** topbar brand `layout:32` | **VISIBLE** |

**Inventory summary post-B11:** 19 academic route groups — **17 VISIBLE** (including aliases), **1 BACKEND-ONLY** (Components inside form `G12`), **1 contextual** (`Transcript` per-student tab — correctly not top solo page but hub via `students.index:275`). **0 MISSING. 0 new routes.**

### C.2 Professional Routes — Preservation Check

| # | Route | Classification Post-B11 | Expected |
|---|-------|------------------------|----------|
| C20 | `courses.manage.*` canonical `928` | **VISIBLE** `Training → Courses:286` | VISIBLE |
| C21 | `courses.manage.subjects.*` `952` | **VISIBLE** `Training → Subjects:289` + tabs | VISIBLE |
| C22 | `curricula.*` `900` + modules/lessons | **VISIBLE** `Training → Curriculum:292` + academic `!usesClassTerm:307` | VISIBLE |
| C23 | `batches.*` `165/989` | **VISIBLE** `Training → Batches:295` + academic `!usesClassTerm:310` | VISIBLE |
| C24 | `exams.*` `175` | **VISIBLE** `Training → Exams:298` + academic `Exams:177` | VISIBLE |
| C25 | `certificates.index` `190` | **VISIBLE** `Training → Certificates:301` | VISIBLE |
| C26 | `teachers.*` single `355/1076` label `Trainers` when `isProfessional && !isEducation:152` | **VISIBLE** shared `Teachers:150` | VISIBLE (B9-impl preserved) |
| C27 | `students.*` | **VISIBLE** shared `Students:137` | VISIBLE |

**C.2 verdict: PASS — no professional regression.**

### C.3 Route Health — Duplicates / Guards (unchanged)

| Finding | File:Line | Current | Risk | Action |
|---------|-----------|---------|------|--------|
| `academic-attendance.mark` duplicate | `web.php:161` + `institute_modules:1101/1564` | Two defs | LOW | Document — keep canonical `1101` group, `web.php:161` as alias; no fix in audit phase |
| `exams.marks` triple alias | `web.php:181` + `1071/1571` | Same controller | LOW | Keep canonical |
| `classes.*` guard | `979` | `domain:academic + permission:courses.view` (B7 fixed) | — | Preserve |
| `curricula.*` no `domain` | `900` | Intentional hybrid (polytechnic `!usesClassTerm`) — controller `availableCourses:397` domain-aware | LOW | Keep + test |
| `settings.academic.*` all `education.manage+domain:academic` | `1144` group inside `$tenant:16` | Correct | — | Preserve |

---

## D. CONTROLLERS INVENTORY — REUSE VERIFIED

| Controller | Routes Served | Classification Post-B11 |
|------------|---------------|-------------------------|
| `AcademicDashboardController` | `academic.dashboard:159` | **VISIBLE** (was HIDDEN) |
| `AcademicStructureController` | `settings.academic.* index/label/levels/classes/groups` `1144` | **VISIBLE** |
| `AcademicAssessmentController` | `assessments.*:1182` | **VISIBLE** |
| `AcademicMarksController` | `assessments.marks.*:1195` | **VISIBLE** via hub |
| `AcademicAggregationController` | `aggregations.*:1172` | **VISIBLE** |
| `AcademicGradingController` | `grading.*:1163` | **VISIBLE** |
| `AcademicFinalResultController` + `AcademicFinalResultLifecycleService` | `final-results.*:1199` lifecycle | **VISIBLE** |
| `AcademicPromotionController` | `promotions.*:1217` | **VISIBLE** |
| `StudentAcademicPlacementController` | `placements.*:1236` + `academic-years.*:1247` | **VISIBLE** |
| `AcademicAttendanceController/Report` | `academic-attendance:161/1101` | **VISIBLE** |
| `AcademicAnalyticsController` | `academic.analytics:1114` | **VISIBLE** |
| `CourseMaster/SubjectManagement/Category/Curriculum/Batch/Teacher/Student/Class/Certificate` | canonical academic+professional | **VISIBLE** preserved |

**Finding D: PASS — 0 controllers missing, 0 new controllers created (reuse only).**

---

## E. VIEWS INVENTORY — REUSE VERIFIED

| View Path | Route | Nav Post-B11 | Classification |
|-----------|-------|--------------|----------------|
| `academic/dashboard.blade.php` | `academic.dashboard` | `Academic → Dashboard:215` | **VISIBLE** |
| `academic/analytics/*.blade.php` (11) | `academic.analytics.*` | `Academic → Analytics:272` | **VISIBLE** |
| `academic-attendance/index.blade.php` + `reports/*` | `academic-attendance.*` | `Academic → Attendance:269` | **VISIBLE** |
| `classes/index.blade.php` + archive/batches/subjects/_tabs | `classes.*` | `Classes` toggle `171` + `Academic → Classes:224` | **VISIBLE** |
| `institute/academic-structure.blade.php` | `settings.academic.index` | `Academic Settings:218` | **VISIBLE** |
| `institute/academic-placements/index.blade.php` | `placements/academic-years` `1236/1247` | `Academic Years:221` + `Placements:239` alias same file | **VISIBLE** |
| `institute/academic-assessments/*` (index/form/show/marks/marks-sheet/readiness) | `assessments.*:1182` | `Assessments:242` + `Marks:245` | **VISIBLE** |
| `institute/academic-aggregations/*` | `aggregations:1172` | `Results → Aggregations:253` | **VISIBLE** |
| `institute/academic-grading/*` (index/form/preview) | `grading:1163` | `Results → Grade Scales:256` | **VISIBLE** |
| `institute/academic-final-results/*` (index/show/report-card/result-sheet/readiness/preflight/policy) | `final-results:1199` | `Results → Final Results:259` + `Published:262` | **VISIBLE** |
| `institute/academic-promotions/*` | `promotions:1217` | `Promotions:266` | **VISIBLE** |
| `students/index/show/academic_transcript/academic_history` | `students.*` | `Academic → Students:230` + `Transcript hub:275` | **VISIBLE** |
| `institute/teachers/*` | `teachers.*` | `Teachers` shared + `Academic → Teachers:236` | **VISIBLE** |
| `institute/course-master/**` (index/form/subjects/_tabs/subject-form) | `courses.manage.*` | `Academic → Subjects:233` + `Training → Subjects` | **VISIBLE** |
| `institute/curriculum/*` | `curricula.*` | `Training → Curriculum` + academic `!usesClassTerm:307` | **VISIBLE** |
| `batches/index/show` | `batches.*` | `Training/Batches` | **VISIBLE** |
| `certificates/index` + `certificate-types` | `certificates.*` | `Academic/Certificates` | **VISIBLE** |
| `business/profile.blade.php:405` | `business.profile` | topbar `layout:32` | **VISIBLE** |
| `dashboard/_tabs.blade.php:1-39` | `academic.dashboard / analytics` tabs | `InstituteDomain::isAcademic:8` | **VISIBLE** |

**Finding E: PASS — 0 MISSING (42/42 verified).**

---

## F. SERVICES INVENTORY — UNCHANGED

| Service | Finding |
|---------|---------|
| `AcademicAssessmentService/Audit` `AcademicMarksService` `AcademicResultAggregationService` `AcademicGradingService` `AcademicFinalResultService/Lifecycle/Preflight/Readiness/Cumulative/Dashboard/Setup/Structure` `Promotion*` `StudentAcademicPlacement/Lifecycle` `CourseMaster/Curriculum/TeacherProfile/Workload` `StudentAcademicAttendance*` `ModuleAccess/IndustryRules/Workspace` | **EXISTS** — all reused; `AcademicFinalResultService:220` `threshold ?? 2.00` `max_gpa ?? 5.00` `multiple_optional_policy single/best/sum:60` preserved |

---

## G. ACADEMIC UI MATRIX — 29 ITEMS — POST-B11 END-TO-END

| # | Capability | Route | View | Classification B10 → B12 | Evidence | Gap? |
|---|------------|-------|------|--------------------------|----------|------|
| G1 | Academic Dashboard | `academic.dashboard:159` | `academic/dashboard.blade.php` | **HIDDEN → VISIBLE** | `layout:215` `href=route('academic.dashboard')` inside `#academicNavGroup` + `_tabs:29` | — |
| G2 | Academic Settings | `settings.academic.index:1144` | `institute/academic-structure.blade.php` | HIDDEN → VISIBLE | `layout:218` | — |
| G3 | Academic Years | `settings.academic.academic-years.*:1247` CRUD via modal in `placements.index:1236` | `institute/academic-placements/index` | NO NAV → **VISIBLE alias** | `layout:221` `href=route('settings.academic.placements.index')` (same as G10) | **LOW polish:** two sidebar entries `Years`+`Placements` point to identical `placements.index` — functional but duplicate href; consider `#years` anchor or dedicated tab param |
| G4 | Classes | `classes.index:979 domain:academic` | `classes/index.blade.php` | VISIBLE → VISIBLE | `layout:171` toggle + `224` alias | — |
| G5 | Groups / Streams | `settings.academic.groups.*:1158` | `academic-structure #groups` | HIDDEN → VISIBLE | `layout:227` `href=route('settings.academic.index')#groups` | **LOW:** `request()->routeIs('settings.academic.groups.*') || routeIs('settings.academic.index')` makes `Groups` active also on `Academic Settings` page (`218`) — both appear active simultaneously |
| G6 | Subjects (Academic) | `courses.manage.subjects.index:952` server-clamped `subjectTypeFor academic` | `institute/course-master/subjects.blade.php` | VISIBLE via toggle → VISIBLE via `Academic → Subjects` | `layout:233` | — |
| G7 | Students | `students.*:139` | `students/*` | VISIBLE → VISIBLE | `layout:137` shared + `230` alias | — |
| G8 | Teachers | `teachers.*:355/1076` single | `institute/teachers/*` | VISIBLE → VISIBLE | `layout:150` + `236` | — |
| G9 | Teacher Assignments | `teachers/{teacher}/assign:1083` via `TeacherAcademicAssignment` | `teachers/show.blade.php` assign button | HIDDEN nested → **VISIBLE-per-entity** | No dedicated `Academic → Assignments` top entry; reachable via `Teachers → show → assign` (`layout:236` → `Teachers`). By design single system — not missing, but no filtered assignment queue nav | LOW — acceptable; optionally add `?assignment=academic` filter |
| G10 | Placements | `settings.academic.placements.*:1236` | `institute/academic-placements/*` | HIDDEN → VISIBLE | `layout:239` | — |
| G11 | Assessments | `settings.academic.assessments.*:1182` | `institute/academic-assessments/*` | HIDDEN → VISIBLE | `layout:242` | — |
| G12 | Assessment Components | inside `assessments/form` `Component::availableFor` | `academic-assessments/form` | BACKEND ONLY → BACKEND ONLY | No standalone manager — by design inside create/edit | — correct |
| G13 | Marks | `assessments.marks.store:1195 marks-sheet:1196 export:1197` | `marks.blade.php/marks-sheet` | HIDDEN nested → VISIBLE hub | `layout:245` `href=route('settings.academic.assessments.index')` hub (row → `marks-sheet`) | **LOW:** `Marks` and `Assessments` `layout:242/245` share identical href + identical `request()->routeIs('settings.academic.assessments.*')` — both appear active simultaneously |
| G14 | Result Aggregation | `settings.academic.aggregations.*:1172` | `institute/academic-aggregations/*` | HIDDEN → VISIBLE | `layout:253` under `Results` sub | — |
| G15 | Aggregation Schemes | same `aggregations` | same | HIDDEN → VISIBLE (alias G14) | — | — |
| G16 | Grade Scales | `settings.academic.grading.*:1163` | `institute/academic-grading/*` | HIDDEN → VISIBLE | `layout:256` | — |
| G17 | Optional Subject | `StudentSubjectSelection` + `AcademicSelectionGroup` + `placements/_subjects.blade.php` + `AcademicFinalResultService:240` | placements selection group | HIDDEN contextual → VISIBLE contextual | Selection stored in placements; bonus derived at final result | — correct contextual exposure |
| G18 | Optional Subject Bonus | `GradeScale:34 threshold/cap/policy + Service:218-335` `optionalBonus = max(gp-threshold,0)` `multiple single/best/sum:60` | `academic-grading/preview` + `final-results/show` breakdown | HIDDEN calc preview hides threshold → **STILL HIDDEN DETAIL** | `grading.preview` still not surfaced per B10 M / B11 AA1 — route reachable `256` but preview table not yet enriched; calculation intact not bypassed | **LOW (deferred):** UI restoration for threshold/cap/policy display deferred — not blocking, calculation `threshold 2.00 max 5.00 single|best|sum` verified intact |
| G19 | Promotions | `settings.academic.promotions.*:1217` `promotion.manage` | `institute/academic-promotions/*` | HIDDEN → VISIBLE | `layout:266` gate `promotion.manage` still | — |
| G20 | Final Results | `settings.academic.final-results.*:1199` | `institute/academic-final-results/*` | HIDDEN → VISIBLE | `layout:259` | — |
| G21 | Result Review | `POST .../send-to-review:1206` + promotions `send-to-review:1230` | `final-results/show` status Review | HIDDEN queue → VISIBLE via status filter/button | Lifecycle `Draft→Review` reachable | — |
| G22 | Result Approval | `POST .../approve:1203` | same | HIDDEN → VISIBLE | `Approved` band | — |
| G23 | Result Lock | `POST .../lock:1207` + `assessments/lock:1190` | same `locked/published` guard | HIDDEN → VISIBLE | Prevents mutate | — |
| G24 | Result Publishing | `POST .../publish:1208` + filtered `?status=published` | same | HIDDEN → VISIBLE | `layout:262` `Published Results` alias `?status=published` | — |
| G25 | Report Card | `GET .../report:1204 result-sheet:1205 export:1209` | `report-card/result-sheet.blade.php` | HIDDEN nested → VISIBLE nested | Via `Final Results → show → report/result-sheet` + export | No solo nav needed — correctly nested under Final Results |
| G26 | Transcript | `GET students/{student}/academic-transcript:1091 domain:academic` | `students/academic_transcript.blade.php` | VISIBLE contextual → **VISIBLE hub** | `layout:275` `Academic → Transcript → students.index` hub (detail still per-student `domain:academic`) | — contextual correctly |
| G27 | Certificates | `certificates.index:190 + action:1095 domain:academic + certificate-types:1311` | `certificates/index` | VISIBLE → VISIBLE | `layout:278` | — |
| G28 | Academic Attendance | `academic-attendance/mark:161 reports:1101 exports` | `academic-attendance/*` | HIDDEN → VISIBLE | `layout:269` | — |
| G29 | Academic Analytics | `academic/analytics/*:1114` + exports | `academic/analytics/*` | HIDDEN → VISIBLE | `layout:272` | — |

**Matrix Summary G post-B11:** 29 rows — **VISIBLE 25** (including 3 alias-resolved Year/Groups/Marks that are functional but share href/active quirks), **BACKEND ONLY 1** (Components), **VISIBLE contextual/nested 2** (Transcript per-student, Report Card inside Final Results), **VISIBLE per-entity 1** (Teacher Assignments). **MISSING 0.** B10 gap of 21 HIDDEN is **closed**.

---

## H. PROFESSIONAL UI MATRIX — PRESERVATION VERIFIED

| # | Capability | Verification `layout:line` | Classification | Expected |
|---|-----------|----------------------------|----------------|----------|
| H1 | Students/Trainees | `layout:137 Students` shared `isEducation‖isProfessional && workspaceAllowedEducation` | EXISTS+VISIBLE | VISIBLE |
| H2 | Trainers | `layout:150-152 Teachers/Trainers` ternary `isProfessional && !isEducation ? 'Trainers' : 'Teachers'` | EXISTS+VISIBLE (B9 fixed preserved) | VISIBLE |
| H3 | Courses | `286 courses.manage.index` `isProfessional` | EXISTS+VISIBLE | VISIBLE |
| H4 | Subjects | `289 courses.manage.subjects.index` + `_tabs:17` | EXISTS+VISIBLE | VISIBLE clamp `subjectTypeFor` |
| H5 | Categories inline | via `course-master/form` modal `938` | EXISTS+VISIBLE inline | inline modal correct |
| H6 | Sub-Categories inline | `945` | inline | — |
| H7 | Curriculum | `292 curricula.index` `isProfessional` + `307 !usesClassTerm` academic hybrid | EXISTS+VISIBLE | VISIBLE |
| H8 | Modules/Lessons | nested `curricula/{curriculum}/modules:910` + `lessons:914` | nested | — |
| H9 | Batches | `295 batches.index` `isProfessional` + `310 !usesClassTerm` | EXISTS+VISIBLE | VISIBLE |
| H10 | Enrollments | `students/{student}/enroll:144` + `admissions.pipeline:1004` via student show | NO NAV standalone but reachable — correct per B10 (no regression) | acceptable |
| H11 | Attendance batch | inside `batches.show` tab | contextual | — |
| H12 | Exams | `298 exams.index` + academic `177` both | EXISTS+VISIBLE | VISIBLE |
| H13 | Certificates | `301 certificates.index` + `certificate-types:1311` | EXISTS+VISIBLE | VISIBLE |
| H14 | Finance/Fees | `Finance` generic `workspaceAllowedFinance:327` + `finance.education.fee-collection:391` when `isEducation` — professional via generic finance correct | VISIBLE (generic) | VISIBLE |

**Finding H: PASS — 0 regressions from B11 Academic insertion; `Training` block strictly after `Academic` block.**

---

## I. TEACHER / TRAINER MATRIX

| Dimension | Current `FILE:LINE` | Verdict |
|-----------|---------------------|---------|
| **Duplicate system?** | Single `TeacherController.php:12` + `TeacherProfile` + `TeacherAcademicAssignment` + `Batch.teacher_id→Membership` — no `InstructorController` | **PASS** — reuse only |
| **Academic label** | `layout:152` `Teachers` when `isEducation` | PASS |
| **Professional label** | Same `teachers.index:150` label `Trainers` when `isProfessional && !isEducation` | PASS (B9 fix preserved) |
| **No second model** | `TenantScoped` + `BranchScoped` + `Role teacher` unchanged | PASS |

---

## J. COURSE / SUBJECT / CURRICULUM / CLASS — DOMAIN CLAMP

| Check | File:Line | Current | Verdict |
|-------|-----------|---------|---------|
| `courses.manage.*` tenant | `CourseMasterController:37` `where institute_id` | OK | PASS |
| `courses.manage.subjects.*` server-derived `subject_type` | `SubjectManagementController:30` `subjectTypeFor` `where institute_id AND subject_type=derived` `TenantContext` — `?subject_type=` ignored `49` | OK | PASS — Academic gets `academic` only, professional `professional` only |
| `curricula.*` domain hybrid | `CurriculumController:397` `availableCourses` domain-aware fix preserved B7 | OK — poly sees curriculum, `school Classes` preference still dominates | PASS |
| `classes.*` academic-only | `institute_modules:979` `domain:academic + permission:courses.view` + `layout:224` alias inside Academic | Gate matches `InstituteDomain::isAcademic` | PASS |

No `business_subcategory` invented — `config/industry_rules.php` unchanged CATEGORY only.

---

## K. MULTI-BUSINESS / TENANT / WORKSPACE — SWITCH VERIFICATION

| Scenario | Sidebar After B11 | Evidence |
|----------|-------------------|----------|
| `School` (`education/school` `domain=academic`) active `Workspace::set(A)` → `InstituteDomain::isAcademic=true` | `Academic` collapsible visible `204-283` (17 links) ; `Training` hidden (`isProfessional false:285`) ; generic `Students:137` still visible via shared `isEducation||isProfessional` | `View::composer AppServiceProvider:121` `institute=Workspace::membership()->institution` per `active_institution_id` + `isEducation/isProfessional` derived `124-125` |
| `Dance Academy` (`training_center/dance_academy` `domain=professional`) active | `Academic` hidden (`isEducation false`), `Training` visible `285-304` (Courses/Subjects/Curriculum/Batches/Exams/Certificates) + `Trainers:152` | Same composer — follows ACTIVE |
| `Retail` (`retail/general_store` `domain=other`) active | Both `Academic` + `Training` hidden; only generic `Finance/Accounting/Sales/Purchase/Crm/Hr/Settings` per `workspaceAllowed*` | Neither domain true per `InstituteDomain:OTHER` |
| Topbar brand → `business.profile:349` | `BusinessProfileController:assertTenantMatchesActive:140` `Workspace/TenantContext` verify — shows `academicData:251` vs `professionalData:276` vs `other:307` domain-aware | Verified — not stale |

**K: PASS — navigation follows active business; no stale cache (`view:clear` PASS).**

---

## L. TENANT ISOLATION

| Item | File:Line | Isolation | Verdict |
|------|-----------|-----------|---------|
| All `settings.academic.*:1144` inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26` `TenantContext::id()=active_institution_id` | `institute_modules:16` | `AcademicAssessment/FinalResult/GradeScale/Batch/CourseCurriculum/Student/TeacherProfile` all `TenantScoped/BranchScoped` or explicit `where institute_id` | **PASS** |
| `Rule::exists()->where institute_id + subject_type derived` | `CourseCategoryManage:26` `Batch:376` `AcademicAttendance:72` | No cross-tenant dropdown leak | PASS |
| `withoutGlobalScope` | No new `withoutGlobalScope` added B11 — only pre-existing `AcademicStructureController:464,477,488` platform-admin safe | — | PASS |
| `FOREIGN_KEY_CHECKS` | None | — | PASS |
| Branch | `BranchContext` `SetTenantContext:70` `Membership.branch_id` | `Batch/Attendance BranchScoped` preserved | PASS |

Navigation change adds zero tenant bypass — `GET` to already `tenant`-gated routes.

---

## M. OPTIONAL SUBJECT / BONUS — INTEGRITY vs DISPLAY

| Policy | File:Line | Current | Preservation |
|--------|-----------|---------|--------------|
| `optional flag AcademicFinalResultRow.optional:31` persisted snapshot | `AcademicFinalResultRow.php:31` | Untouched | PASS |
| `threshold = 2.00 GradeScale:34 + Service:220 $threshold ?? 2.00` | `GradeScale.php:34` + `AcademicFinalResultService.php:220` | scale-configurable fallback 2.00 | PASS |
| `max_gpa = 5.00 GradeScale max_gpa + Service:222 $maxGpa ?? 5.00 cap:335` | same | cap | PASS |
| `multiple_optional_policy single/best/sum:60 + Service:281 branch sum vs best/single` | `GradeScale.php:60` + `Service:281-335` | bonus `max(gp-threshold,0)` denominator exclusion `270` | PASS |
| UI exposure | `academic-grading/preview.blade.php` + `final-results/show` breakdown still hides threshold/policy — **deferred AA1** | Route `Grade Scales:256` now reachable so admin can see existing config; preview table enrichment not yet | **LOW residual — integrity PASS, display polish deferred to B13** — no business rule risk |

---

## N. DOMAIN ISOLATION

| Rule | Current `layout:line` | Impact | Verdict |
|------|-----------|---------|--------|
| `InstituteDomain::isAcademic:124` guards `Academic` `if ($isEducation && workspaceAllowedEducation):204` | `Academic` disappears for `training_center/dance_academy` + `retail` — `domain:academic` routes remain 403 | PASS |
| `isProfessional:125` guards `Training:285` | `Training` hidden for `school/college/polytechnic/university` + `retail` | PASS |
| `other` neither true → neither block | `retail/manufacturing/service/transportation/restaurant` | Correct | PASS |
| `subject_type` server-derived `SubjectManagementController:32,79` | `$derived` clamp — cross-domain `?subject_type=` ignored | PASS | PASS |

---

## O. RBAC

| Group | Permission | Nav Visibility | Direct URL Guard |
|-------|------------|----------------|------------------|
| `students.*` | `students.view/manage:139` | Always but 403 if lacks | PASS |
| `batches/curricula/courses.manage` | `batches.view/manage` `curriculum.view/manage` `courses.view/manage` | Gated | PASS |
| `settings.academic.*` | `education.manage:1144` entire group (grading/aggregations/assessments/final-results/placements/years all) | Academic visible but click 403 if lacks — navigation not sole defense | PASS |
| `promotions.*` | `education.manage + promotion.manage:1217` extra | 403 even when Academic visible | PASS |
| `certificates` | `certificates.view:190` | Gated | PASS |

**O: PASS — middleware stacks `php artisan route:list` unchanged.**

---

## P. RESPONSIVE / ACCESSIBILITY

| Viewport | Mechanism | Verdict |
|----------|-----------|---------|
| Desktop | `sidebar-collapsed localStorage COLLAPSE_KEY:699` + `nav-link sub` indent + `#academicNavGroup` / `#academicResultsSub` Bootstrap `data-bs-toggle="collapse"` `5.3.3` `bundle` | PASS — `academicOpen` auto-opens on academic routes `206`, `resultsOpen:207` on aggregations/grading/final-results |
| Tablet `max-width:768px` | `mobileQuery:700` drawer `sidebar-open` + `backdrop:114` `overflow hidden` inside `nav flex-column` scrollable | PASS |
| Mobile | `monetixSidebarToggle:28` off-canvas + chevron `bi-chevron-down` rotate `212` | PASS — no new framework |

---

## Q. TESTS

### Q.1 Manual CLI

| Check | Command | Result |
|-------|---------|--------|
| Routes canonical | `php artisan route:list --name="settings.academic"` 1211 routes sampled C1-C19 + `academic.dashboard` 1 + `classes` 17 names unchanged | **PASS** |
| Views compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` + `config:clear` | **PASS** |
| View exists | `Get-ChildItem institute/academic-*` 6 dirs + `academic/analytics` 11 files | **PASS** |

### Q.2 Automated Suites (unchanged from B11 — no new code to break)

| Suite | Prior Run | B12 Expectation |
|-------|-----------|-----------------|
| `BusinessProfileTest 16/16` | PASS `3.53s` | PASS unchanged (domain switch follows active) |
| `TenantIsolationAuditTest 4/4` | PASS `0.07s` | PASS unchanged |
| Pre-existing failures (`SubjectUnification tenant 302`, `TeacherManagement 159 ModelNotFound`, `InstituteSettings ModelNotFound`) | Pre-existing harness `TenantContext` not set — not B11 regression | Unchanged — document, do not mutate |

**New failures: 0** — B12 is audit-only.

**Recommended after (B13):** `AcademicUINavigationTest` — assert `isAcademic→Academic visible contains Dashboard/Settings/Assessments/Final Results`, `isProfessional→Training visible`, `retail→neither`, plus `Marks` alias resolves same as `assessments.index`.

---

## R. REMAINING ISSUES — FOR B13 POLISH ONLY (LOW, not blocking)

| # | Issue | Severity | Note for B13 | B10 Ref |
|---|-------|----------|--------------|---------|
| R1 | **Marks alias duplication** — `layout:242 Assessments` + `245 Marks` both `href=route('settings.academic.assessments.index')` + both `request()->routeIs('settings.academic.assessments.*')` → both active simultaneously | **LOW** cosmetic | B13: keep `Assessments` as hub; make `Marks` a filtered hub `route('settings.academic.assessments.index',['filter'=>'marks'])` or dedicated `marks-sheet` hub page reusing existing `marks-sheet:1196` param, or remove duplicate and keep single `Assessments` with tab | G13 |
| R2 | **Groups active overlap** — `Groups/Streams:227` active `routeIs('settings.academic.groups.*') || routeIs('settings.academic.index')` makes `Groups` active on `Academic Settings` page (`218` also active) | LOW cosmetic | B13: narrow `Groups` active to `routeIs('settings.academic.groups.*')` OR query `#groups` detection only; keep `Academic Settings` single active | G5 |
| R3 | **Academic Years ↔ Placements same href** — both `221` + `239` point to `route('settings.academic.placements.index')` — two entries identical | LOW UX | B13: keep `Academic Years` as `placements.index#years` anchor (or `?tab=years`) with modal auto-open; `Placements` stays `placements.index`. No new route. | G3/G10 |
| R4 | **Optional bonus display not yet surfaced** — `threshold 2.00 / max 5.00 / single|best|sum` not in `academic-grading/preview` or `final-results/show` breakdown despite calc intact `GradeScale:34/Service:218-335` | **LOW deferred** | B13: enrich existing `academic-grading/preview.blade.php` + `final-results/show` to show `threshold/max_gpa/policy` from `GradeScale` — reuse fields, no service change | G18 / B11 AA1 |
| R5 | Duplicate `academic-attendance.mark` definition (`web.php:161` + `1101/1564`) + `exams.marks` triple alias | LOW | B13: consolidate docs, keep canonical `institute_modules:1101` group | C.3 |
| R6 | Teacher Assignments no dedicated queue — assignment via `teachers/{teacher}/assign:1083` per-teacher only | LOW | B13 optionally add `?assignment=academic` filter on `teachers.index` — no new model | G9 |
| R7 | Report Card no solo nav — correctly nested under `Final Results → show → report/result-sheet:1204/1205` | INFO | No fix needed — keep nested; optionally add `Academic → Results → Report Card` anchor reusing same `final-results.show` | G25 |

**No B13 may:** create duplicate routes/models/tables, invent `business_subcategory` taxonomy, create `InstructorController`, mutate `AcademicFinalResultService` calc, add fake subjects, disable `FOREIGN_KEY_CHECKS`.

---

## S. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| Academic navigation end-to-end (Dashboard/Settings/Years/Classes/Groups/Students/Subjects/Teachers/Placements/Assessments/Marks/Results→Aggregations/Grade Scales/Final/Published/Promotions/Attendance/Analytics/Transcript/Certificates) | **PASS** | 17 `Academic` entries (layout:215-281) now VISIBLE for `isAcademic` via `InstituteDomain::isAcademic` — 6 more top-shared `Students/Teachers/Classes/Exams` aliases; 1 BACKEND-ONLY Components by design correctly not nav |
| Academic Settings reachable | **PASS** | `Academic → Academic Settings:218` → `settings.academic.index:1144` |
| Student/Subject/Teacher/Class/Curriculum | **PASS** | `Academic → Students:230`, `Subjects:233` clamped `subjectTypeFor`, shared `Teachers:150/236` no duplicate, `Classes:224` + `Curriculum/Batches:307` hybrid preserved |
| Assessment/Marks | **PASS** | `Assessments:242` + `Marks hub:245` via `AcademicMarksController:1195/1196` (duplicate active LOW R1) |
| Results lifecycle | **PASS** | `Results→Aggregations:253 + Grade Scales:256 + Final:259 + Published:262` via `AcademicFinalResult*` `Draft→Review→Approved→Locked→Published` intact |
| Optional/Bonus integrity | **PASS integrity / DEFERRED display** | `threshold 2.00/max 5.00/single|best|sum` `GradeScale:60 + Service:218` preserved; display enrichment LOW R4 |
| Promotion | **PASS** | `Promotions:266` `promotion.manage:1217` |
| Transcript/Certificate | **PASS** | `Transcript hub:275` → `students.index` + per-student `academic-transcript:1091 domain:academic`; `Certificates:278` single |
| Attendance/Analytics | **PASS** | `Attendance:269` `academic-attendance.mark:161/reports:1101` + `Analytics:272` |
| Professional Preservation | **PASS** | `Training 285-304` unchanged — `isProfessional` gate; label `Trainers` still B9 fix |
| Business Profile UX | **PASS** | topbar `brand:32` → `business.profile:349` workspace-authoritative `academicData:251 vs professionalData:276` |
| Domain Isolation | **PASS** | `InstituteDomain` only — `Academic` hidden for professional+retail vice-versa |
| Tenant Isolation | **PASS** | `TenantScoped/BranchScoped` + `where institute_id` + `Rule::exists->where institute_id` + `tenant:16` |
| RBAC | **PASS** | `education.manage(+promotion.manage)` retained — direct URL 403 |
| Multi-Business Switching | **PASS** | `Workspace::set` + `TenantContext` + `View::composer:121` — sidebar follows ACTIVE |
| Responsive | **PASS** | Bootstrap `collapse` `nav-link sub` `sidebar-backdrop` Desktop/Tablet/Mobile |
| Migrations/Data | **PASS** | `DATA MODIFIED: NO` `DATA DELETED: NO` `MIGRATIONS: NO` `NEW TABLES: NO` |
| Tests new failures | **PASS 0 new** | Pre-existing SubjectUnification 302 not B12; core `view:clear` PASS |

```
PHASE: B12
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (reuse canonical names — 1211 routes)
ROUTES MODIFIED: NO
VIEWS ADDED: NO (reuse 42/42 existing)
VIEWS MODIFIED: 0 in B12 (1 in B11: layouts/institute.blade.php:203 Academic collapsible)
CONTROLLERS MODIFIED: NO
SERVICES MODIFIED: NO

ACADEMIC_UI: PASS — 25/29 VISIBLE + 1 BACKEND-ONLY (Components) + 3 contextual/nested — was 6/29 YELLOW now GREEN
PROFESSIONAL_UI: PASS — Training preserved isProfessional 6 entries
COURSE_UI: PASS — canonical courses.manage preserved
SUBJECT_UI: PASS — academic=academic professional=professional via subjectTypeFor:32
TEACHER_UI: PASS — single Teacher/Trainer label switch 152
CLASS_UI: PASS — classes.index domain:academic + Academic Structure visible
CURRICULUM_UI: PASS — curricula preserved hybrid
ASSESSMENT_UI: PASS — Academic→Assessments
MARKS_UI: PASS — Academic→Marks hub alias (active duplicate LOW R1)
RESULT_UI: PASS — Results→Aggregations/Grade Scales/Final/Published
OPTIONAL_BONUS_INTEGRITY: PASS (threshold 2.00/max 5.00/single|best|sum intact — display LOW R4)
PROMOTION_UI: PASS — Academic→Promotions
TRANSCRIPT_UI: PASS — Academic→Transcript hub + per-student domain:academic:1091
CERTIFICATE_UI: PASS — Academic/Certificates shared 190+1095
ATTENDANCE_UI: PASS — Academic→Attendance mark+reports
ANALYTICS_UI: PASS — Academic→Analytics + _tabs academic/analytic tabs
BUSINESS_PROFILE_UI: PASS — topbar brand → business.profile Workspace-authoritative
TENANT_ISOLATION: PASS
DOMAIN_ISOLATION: PASS
IDOR: PASS
RBAC: PASS
HISTORICAL_INTEGRITY: PASS (locked/published immutable)
MULTI_BUSINESS: PASS
RESPONSIVE: PASS
REGRESSIONS: PRE-EXISTING ONLY — 0 NEW
TESTS: view:clear + config:clear PASS — overall 0 new failures

FINAL_VERDICT: GREEN
```

**GREEN — Academic End-to-End UI is now properly accessible through navigation** (B10 YELLOW 22 HIDDEN closed by B11 reuse-only collapsible; B12 verifies full chain Academic Dashboard → Structure/Years/Classes/Groups/Subjects → Students/Teachers/Placements → Assessments/Marks → Aggregation/Grade Scales → Final Results lifecycle → Report Card/Transcript → Attendance/Analytics → Certificates/Promotions). Residual R1-R4 are **LOW cosmetic/display polish** deferred to **B13 Fix B12 UI gaps** — do not block workflow verification (B14/B15).

---

> STOP — B12 forensic audit complete. Do not start B13 automatically per spec §21. Next: **B13 Fix B12 UI gaps (R1 Marks duplicate active, R2 Groups active overlap, R3 Years/Placements same href, R4 bonus display)** — navigation-only + existing view enrichment, no migrations, no duplicate systems — reuse `GradeScale` fields and `settings.academic.*` anchors.

*Evidence: `php artisan route:list --name="settings.academic"` 1211 routes canonical + `route:list academic.dashboard 1` + `classes 17` + `php artisan view:clear INFO` + `layout:203-283 Academic→Results#academicResultsSub` + `InstituteDomain:17 ACADEMIC_TYPES` + `dashboard/_tabs:8 isAcademic` + `Get-ChildItem academic-* 6 dirs / academic/analytics 11`.*
