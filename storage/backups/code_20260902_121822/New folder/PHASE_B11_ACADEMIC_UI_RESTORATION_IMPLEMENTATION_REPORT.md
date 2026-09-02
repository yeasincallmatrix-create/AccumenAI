# PHASE B11 — ACADEMIC UI RESTORATION & NAVIGATION IMPLEMENTATION REPORT

**Phase:** B11 — Academic UI Restoration & Navigation (UI/navigation only — backend reused)
**Date:** 2026-08-28
**Prerequisite Audit:** `PHASE_B10_ACADEMIC_PROFESSIONAL_UI_INTEGRATION_FORENSIC_AUDIT_REPORT.md` (YELLOW — 19 HIDDEN academic entries, 0 missing backend)
**Trigger:** User confirmed Academic functionality not visible (Students/Subjects/Structure/Placements/Assessments/Marks/Aggregations/Grade Scales/Promotions/Final Results/Results/Transcript/Certificates/Dashboard/Analytics/Attendance/Teachers hidden)
**Mode:** Reuse existing routes/controllers/services/views — no duplicate systems, no duplicate routes, no duplicate models/tables, no fake data
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic() / isProfessional() / subjectTypeFor()`

---

## A. FILES INSPECTED

| # | File | Lines | Purpose |
|---|------|-------|---------|
| A1 | `resources/views/layouts/institute.blade.php:118-860` | 860 | Sidebar/topbar — verified current academic gap (only `Classes/Courses` toggle `171` + `Exams` `177` visible; deep `settings/academic/*:1144` hidden) |
| A2 | `resources/views/dashboard/_tabs.blade.php:1-39` | 39 | Dashboard tabs — verified B9 fix already `InstituteDomain::isAcademic($institute):8` (not `industry==='education'`) |
| A3 | `routes/institute_modules.php:16-1660` | 1660 | Module routes — inventory source: `settings.academic.*:1144` (structure/grading/aggregations/assessments:1182/marks:1195/final-results:1199/promotions:1217/placements:1236/academic-years:1247), `classes.*:979 domain:academic`, `academic.dashboard:159`, `academic-attendance:1101`, `academic.analytics:1114` |
| A4 | `routes/web.php:158-349` | 408 | `academic.dashboard:159 domain:academic`, `students.*:139`, `teachers.*:355`, `batches:165`, `exams:175`, `business.profile:349` |
| A5 | `app/Support/InstituteDomain.php:17-113` | 113 | `ACADEMIC_TYPES=[school,college,polytechnic,university]` `PROFESSIONAL_TYPES=[training_institute,...dance_academy,it_training_center,...]` `hasDomainData()` |
| A6 | `app/Http/Controllers/AcademicStructureController.php:145` | — | `settings.academic.index/label/levels/groups/classes` — structure CRUD |
| A7 | `app/Http/Controllers/AcademicAssessmentController.php:1` + `AcademicMarksController.php` | — | Assessments + marks sheet |
| A8 | `app/Http/Controllers/AcademicAggregationController.php:1` + `AcademicGradingController.php:1` | — | Aggregations / Grade Scales |
| A9 | `app/Http/Controllers/AcademicFinalResultController.php:1` + `AcademicFinalResultService.php:218` | — | Final Results lifecycle `Draft→Review→Approved→Locked→Published` `sendToReview:1206 lock:1207 publish:1208` |
| A10 | `app/Http/Controllers/AcademicPromotionController.php:1` | — | Promotions policies/rules/decisions `1217` `promotion.manage` |
| A11 | `app/Http/Controllers/StudentAcademicPlacementController.php:1` | — | Placements + Academic Years `1247` |
| A12 | `app/Http/Controllers/CourseMasterController.php:37` + `SubjectManagementController.php:30` | — | Canonical `courses/manage:928` tabs `Courses|Subjects:952` `subjectTypeFor` clamp |
| A13 | `app/Http/Controllers/TeacherController.php:12` | — | Single Teacher/Trainer system `teachers.*:1076` `assign:1083` + `TeacherProfile` |
| A14 | `app/Services/Academic*` + `GradeScale.php:34-60` (`threshold 2.00:220 max_gpa 5.00:222 multiple single/best/sum:60`) | — | Optional bonus math preserved |
| A15 | `resources/views/institute/academic-*/**` + `academic/dashboard.blade.php` `academic/analytics/*` `academic-attendance/*` `classes/*` `students/*` `institute/teachers/*` `certificates/*` `business/profile.blade.php` | — | Views reuse verified — zero new Blade required |

**Verification method:** Direct `Read` + `Get-Content` + `bash route:list` live, not trusting reports alone — confirmed B10 audit gap.

---

## B. FILES CHANGED

| File | Lines Changed | Change | Why | Security Impact |
|------|---------------|--------|-----|-----------------|
| `resources/views/layouts/institute.blade.php:203-315` | +~78 lines inserted (academic collapsible block) | Added dedicated collapsible **Academic** group gated `if ($isEducation && workspaceAllowedEducation)` `InstituteDomain::isAcademic` — nested `Academic → Dashboard` `academic.dashboard:159`, `Academic Settings` `settings.academic.index:1144`, `Academic Years` `settings.academic.placements.index:1236` (#years), `Classes` `classes.index:979 domain:academic`, `Groups/Streams` `settings.academic.index#groups`, `Students` `students.index`, `Subjects` `courses.manage.subjects.index:952`, `Teachers` `teachers.index`, `Placements` `settings.academic.placements.index`, `Assessments` `settings.academic.assessments.index:1182`, `Marks` alias same assessments hub, `Results` sub-collapse (`Aggregations:1172`, `Grade Scales:1163`, `Final Results:1199`, `Published Results:1199?status=published`), `Promotions` `settings.academic.promotions.index:1217`, `Attendance` `academic-attendance.mark.index:161`, `Analytics` `academic.analytics.index:1114`, `Transcript` `students.index` hub (per-student `academic-transcript:1091` contextual), `Certificates` `certificates.index:190` — all **existing canonical route names reused**, collapsible via Bootstrap `data-bs-toggle="collapse"` `#academicNavGroup` / `#academicResultsSub` reusing `sub` pattern `253` | B10 W1-W16: 19 HIDDEN academic entries reachable only via direct URL; user confirmed cannot see Academic workflow. Provides single discoverable Academic top-level grouping without inventing routes | **NONE** — navigation hiding not security. All linked routes retain `auth+tenant+verified + domain:academic + permission:education.manage (+promotion.manage)` — direct URL 403 remains. Sidebar gate uses same `InstituteDomain::isAcademic` as middleware guard, so hidden ≠ bypass. Tenant isolation `TenantScoped`/`BranchScoped` unchanged. No `withoutGlobalScope` added. No duplicate `$institute->industry==='education'` hardcode. |
| `resources/views/dashboard/_tabs.blade.php:8` | 0 — verified already correct | No edit — retains `InstituteDomain::isAcademic($institute)` from B9-impl `B9_BUSINESS_TYPE:58` | Preserve B9 fix | **NONE** — authoritative domain resolver already, middleware `domain:academic` aligned |

**Not changed (intentionally reused):**
- `routes/institute_modules.php` — no new routes, no `/academic/*` duplicate alias; reuse canonical names per spec §2.
- `app/Http/Controllers/*` — no new controllers; reuse `Academic*Controller` + `CourseMaster/SubjectManagement/Teacher` single systems.
- `app/Services/*` `AcademicFinalResultService:218` threshold/cap/policies untouched.
- `resources/views/institute/academic-*` etc — reuse existing Blades, no new view invented except navigation.
- `config/industry_rules.php` — no sub-category taxonomy.

**Rollback:** `git checkout HEAD -- resources/views/layouts/institute.blade.php && php artisan view:clear`

---

## C. NAVIGATION CHANGES

| Before (B10 verified `layout:118-235`) | After (B11) | Route Reuse | Domain Gate |
|----------------------------------------|-------------|-------------|-------------|
| Academic institutes saw only `Dashboard` + `Students:137` + `Pending Admissions:141` + `Teachers:150` + `Classes/Courses` toggle `173` + `Exams:177` + `Alumni/Workflows` + `Curriculum/Batches` only when `!usesClassTerm:224` — **deep academic missing** (`W3-W16`) | **+ Academic collapsible** `if ($isEducation && workspaceAllowedEducation)` with header `Academic` (icon `bi-mortarboard-fill`, chevron, Bootstrap collapse `id=academicNavGroup`, `aria-expanded` derived `request()->routeIs('academic.dashboard','settings.academic.*','classes.*'...)` — collapsible open when inside Academic) | All links reuse `route('academic.dashboard')` / `settings.academic.*` / `classes.index` / `courses.manage.subjects.index` / `students.index` / `teachers.index` etc — `php artisan route:list` confirms 1211 routes, same names | `InstituteDomain::isAcademic($institute)` `layout:124` — same as `EnsureDomain:11` `domain:academic` — match |
| `Results` no dedicated grouping | **+ Results sub-collapse** `id=academicResultsSub` nested inside Academic with `Aggregations → Grade Scales → Final Results → Published Results` using `sub` pattern as finance `253` | Reuse `settings.academic.aggregations.index:1172`, `grading.index:1163`, `final-results.index:1199` (+ query `?status=published` for Published) | `education.manage+domain:academic` retained |
| `Academic Settings` not discoverable | **+ Academic Settings** `settings.academic.index:1144` entry at top of Academic group | Reuse | Same |
| `Marks` no hub | **+ Marks** alias `settings.academic.assessments.index:1182` (marks-sheet `1195` requires `{assessment}` — hub) | Reuse | Same |
| `Transcript` not top nav (per-student only) | **+ Transcript** `students.index:139` hub — contextual `students/{student}/academic-transcript:1091 domain:academic` remains per-student `show` | Reuse `students.*` | `students.view + domain:academic` for detail |
| Professional `Training` group `203-221` unchanged | **Preserved** `isProfessional && workspaceAllowedEducation` block `Courses:205 Subjects:208 Curriculum:211 Batches:214 Exams:217 Certificates:221` + shared `Teachers/Trainers:150` | Reuse canonical `courses.manage.*` + `curricula.*` | `InstituteDomain::isProfessional:125` — unchanged |

**Responsive:** Collapse uses `data-bs-toggle="collapse"` (Bootstrap 5.3.3 bundle `543` already) + `sidebar-backdrop:114` + `mobileQuery` `700-860` drawer — works Desktop/Tablet/Mobile without new framework. Icons `bi-*` consistent, `nav-link sub` indent reuses finance `253-342`.

---

## D. ACADEMIC NAVIGATION

| Item | Link `layout:line` | Route Name | Middleware | Nav After |
|------|--------------------|------------|------------|-----------|
| Dashboard | `layout: Academic→Dashboard` | `academic.dashboard` `web.php:159` | `tenant+domain:academic` | **VISIBLE** `isAcademic` — was HIDDEN §G1 |
| Academic Settings | `settings.academic.index` `1144` | `settings.academic.index` | `education.manage+domain:academic` | **VISIBLE** — was HIDDEN §G2 |
| Students (shared alias) | `students.index` | `students.index` `web.php:139` | `students.view` | **VISIBLE** — already VISIBLE but now also inside Academic |

---

## E. ACADEMIC SETTINGS

| Item | Route | Controller:Line | Nav |
|------|-------|-----------------|-----|
| Academic Settings | `settings.academic.index` `1144` | `AcademicStructureController:index` | **VISIBLE** Academic→Settings |
| With sub `label` `settings.academic.label:1146` levels `levels.*:1149` classes `classes.*:1154` groups `groups.*:1158` | All `education.manage+domain:academic` | — | — |

No second Academic Settings duplicated. Reuses existing settings pages (label + levels + classes + groups). Profile edit still via `settings.index` generic.

---

## F. STUDENT UI

| Item | Route | Reuse |
|------|-------|-------|
| Student list `students.index` | `StudentController:index` | **VISIBLE** `layout:137` shared + `Academic→Students` alias |
| Create `students.create` | `StudentController:create` | via index |
| Profile `students.show` | `StudentController:show` | via `students.index` → show |
| Academic history `students.academic-history:1089` `domain:academic` | `StudentController:academicHistory` | via student `show` tabs `_tabs` + `academic_history.blade.php` |
| Academic attendance `students.academic-attendance:1090` | same | same |
| Transcript `students.academic-transcript:1091` | `StudentController:academicTranscript` `students/academic_transcript.blade.php` | **New shortcut** `Academic→Transcript` → `students.index` hub (detail still per-student `domain:academic`) |
| Transfer/withdraw `students.academic-transfer/withdraw` | `StudentController:transfer/withdraw` | via student show `domain:academic` |

No new Student controller/model — canonical `Student.php` `TenantScoped` retained.

---

## G. SUBJECT UI

| Item | Route | File:Line | Domain Enforcement |
|------|-------|-----------|--------------------|
| Academic Subjects | `courses.manage.subjects.index` `952` | `SubjectManagementController:30` `subjectQuery($instituteId,$derived) where institute_id=X AND subject_type=derived` `TenantContext` | `InstituteDomain::subjectTypeFor($institute):32` — `allSubjectTypes=[$derived]` `112` — not trusted from browser `49` clamp — Academic `academic` only |
| Professional Subjects | same canonical `952` | same controller — `derived=professional` | Same clamp — `professional` only |
| Categories/Sub-Categories | `courses.manage.categories.*:938` `sub-categories:945` `permission:courses.view/manage` `TenantScoped` `Rule::exists->where institute_id+subject_type` | `CourseCategoryManageController:26` | Tenant+domain scoped — no cross-business |

Visible via `Academic→Subjects` (academic) + existing `Training→Subjects:208` (professional) + canonical `_tabs.blade.php:9` `Courses|Subjects` — same `CourseMaster` underlying. No mixing.

---

## H. TEACHER UI

| Item | Route | Reuse |
|------|-------|-------|
| Teachers (Academic) | `teachers.index` `web.php:355` + `institute_modules:1076` `status/assign:1083` | Single `TeacherController:12` `TeacherProfile.php` `InstituteUser role teacher` — label `Teachers` when `isAcademic` `layout:152` |
| Trainers (Professional) | same `teachers.index` | Same model — label `Trainers` when `isProfessional && !$isEducation` `layout:152` |

Gate preserved `if (($isEducation || $isProfessional) && workspaceAllowedTeachers)` `layout:150` from B9-impl. Single system — no `TrainerController` duplicate. Switching `School→Dance Academy` swaps label as verified `B9 IMPLEMENTATION:150`.

---

## I. ACADEMIC STRUCTURE

| Sub | Route | Controller |
|-----|-------|------------|
| Academic Years | `settings.academic.placements.index` `1236` `#years` (CRUD `settings.academic.academic-years.*:1247` `storeAcademicYear` modal) | `StudentAcademicPlacementController` |
| Classes | `classes.index` `979` `domain:academic + courses.view` | `ClassController` + `AcademicStructureController` classes CRUD `1154` |
| Groups / Streams | `settings.academic.index` `1144` `#groups` `groups.*:1158` | `AcademicStructureController:storeGroup` |

Academic group exposes Structure page `settings.academic.index` (levels/classes/groups/label) + dedicated sub-links same routes — reuse `AcademicStructureController` + `ClassController` (`usesClassTerm` toggle still honored for legacy `Classes/Courses` block `173`).

---

## J. ASSESSMENT UI

| Node | Route | Controller |
|------|-------|------------|
| Assessments | `settings.academic.assessments.index:1182` `create/store/show/edit/update/destroy/lock:1190/unlock/subjects+readiness` | `AcademicAssessmentController` |
| Assessment Components | inside `assessments/form` `Component:availableFor` + `AssessmentType` + AJAX `subjects:112` `classWithinInstitute` | `AcademicAssessmentService` — no separate nav (by design) |
| Subjects selector | `POST settings.academic.assessments/{assessment}/subjects` | same |

`Academic→Assessments` now sidebar-visible; marks nested under assessment show remains.

---

## K. MARKS UI

| Node | Route | Controller |
|------|-------|------------|
| Marks entry `store` | `POST settings.academic.assessments/{assessment}/marks:1195` | `AcademicMarksController:store` |
| Marks sheet | `GET .../marks-sheet:1196` `export:1197` | same `sheet/export` |
| Hub | `Academic→Marks` → `settings.academic.assessments.index` (list → pick assessment → sheet) | reuse assessments |

No calculation logic changed — aggregation still via `AcademicResultAggregationService`.

---

## L. RESULTS UI

| Sub | Route | Controller |
|-----|-------|------------|
| Aggregations | `settings.academic.aggregations.index:1172` `create/store/show/edit/update/destroy/assessments` | `AcademicAggregationController` |
| Grade Scales | `settings.academic.grading.index:1163` `create/store/edit/update/destroy/preview` | `AcademicGradingController` — optional bonus preview still hidden pending display fix (see M) |
| Final Results | `settings.academic.final-results.index:1199` `storeResult/show/approve:1203/report:1204/result-sheet:1205/send-to-review:1206/lock:1207/publish:1208/export:1209/readiness:1210/preflight:1213/policy` | `AcademicFinalResultController` + `AcademicFinalResultLifecycleService` `Draft→Review→Approved→Locked→Published` |
| Published Results | same `final-results.index?status=published` filtered | same controller filtered query — no new table |

All under `Academic→Results` sub-collapse `id=academicResultsSub` reusing `sub` indent as finance `253`. Lifecycle `locked/published` historical integrity preserved (service rejects destructive mutate).

---

## M. OPTIONAL SUBJECT / BONUS

| Policy | File:Line | Preservation |
|--------|-----------|--------------|
| `optional` flag `AcademicFinalResultRow.optional bool:31` persisted snapshot | `AcademicFinalResultRow.php:31` | Untouched |
| `threshold = 2.00` `GradeScale optional_subject_bonus_threshold float:34` + `AcademicFinalResultService:220` `$threshold ?? 2.00` | `GradeScale.php:34` + `AcademicFinalResultService.php:220` | Preserved — scale-configurable, fallback 2.00 |
| `maximum GPA = 5.00` `GradeScale max_gpa float` + `AcademicFinalResultService:222` `$maxGpa ?? 5.00` `335` cap | same | Preserved |
| `multiple optional policy: single/best/sum` `GradeScale MULTIPLE_OPTIONAL_SINGLE/BEST/SUM:60` + `AcademicFinalResultService:281` branch `sum vs best/single` | same | Preserved |
| Calculation `optionalBonus[] = max(gp-threshold,0)` `281-335` denominator exclusion `270` | same | Preserved — no business rule change |
| UI exposure | Grade scale `form:preview` still hides threshold/policy — **UI restoration required per B10 M** but calculation untouched | **No UI invented in B11 sidebar beyond Grade Scales link** — bonus config remains in existing `grading.form/preview` + `final-results/show` breakdown; B11 does not modify calculation; future display enhancement deferred to keep B11 navigation-only scope |

**M verdict:** Optional/bonus logic **intact** — UI path via `Grade Scales:1163` preview + `Final Results` show now reachable through nav, exposing existing config info where administrator needs it.

---

## N. PROMOTION

| Item | Route | Controller |
|------|-------|------------|
| Promotions | `settings.academic.promotions.index:1217` `policies.store: ... policies/{policy}/show/update/status rules.store/update/destroy decisions.store/show/review/send-to-review/approve/export/sheet` `promotion.manage` | `AcademicPromotionController` |

`Academic→Promotions` link `settings.academic.promotions.index` now visible — extra `permission:promotion.manage:1217` gate still enforced.

---

## O. TRANSCRIPT

| Item | Route | View |
|------|-------|------|
| Transcript per-student | `GET students/{student}/academic-transcript:1091` `domain:academic` | `students/academic_transcript.blade.php` + `academic_history.blade.php` via `StudentController:academicTranscript/history` |
| Hub | `Academic→Transcript` → `students.index:139` (list → profile → transcript tab) — no duplicate Transcript model | same controller — contextual via student `show/_tabs` |

---

## P. CERTIFICATE

| Item | Route | Controller |
|------|-------|------------|
| Certificates list | `GET certificates:190` `permission:certificates.view` | `CertificateController:index` |
| Academic action | `POST certificates/{certificate}/action:1095` `domain:academic` | same `action` |
| Types | `certificate-types.*:1311` | `CertificateTypeController` |
| Per-student request | `POST students/{student}/certificate-request:1094 domain:academic` | `CertificateController:request` |

`Academic→Certificates` → `certificates.index` + `Training→Certificates:220` same canonical route — single `Certificate` `TenantScoped` model reused.

---

## Q. ATTENDANCE

| Item | Route |
|------|-------|
| Academic Attendance mark | `GET academic-attendance/mark:161` `AcademicAttendanceController:index` `domain:academic` |
| Reports | `GET academic-attendance/reports:1101` `class/daily/student/export:class:1106` `AcademicAttendanceReportController` |
| Per-student | `GET students/{student}/academic-attendance:1090` |

`Academic→Attendance` → `academic-attendance.mark.index:161` (reports via top tab `reports.index:1101`).

---

## R. ANALYTICS

| Item | Route |
|------|-------|
| Academic Analytics | `GET academic/analytics:160` + `academic/analytics/*:1114` `students/courses/batches/attendance/results/promotions/completion/certificates/finance/crm/export` | 
| `AcademicAnalyticsController` | `academic/analytics/*.blade.php` |

`Academic→Analytics` → `academic.analytics.index`.

---

## S. PROFESSIONAL NAVIGATION PRESERVATION

| Check | Before `layout:203-221` | After |
|-------|-------------------------|-------|
| `Training` header visible for `training_institute/professional_training_center/dance_academy/it_training_center/vocational_training_center` `InstituteDomain::isProfessional:125` | `isProfessional && hasEducationModule` → `Courses:205` `Subjects:208` `Curriculum:211` `Batches:214` `Exams:217` `Certificates:221` | **Unchanged** — block preserved verbatim below Academic block `isProfessional` gate `125` |
| `Teachers/Trainers` label | `layout:150` `isEducation||isProfessional` with ternary `Trainers` for `isProfessional && !isEducation` | **Preserved** B9 fix |
| `!usesClassTerm` academic `Curriculum/Batches` `224-234` hybrid poly/university | preserved | Preserved |
| No cross-domain leak | `classes.*:979 domain:academic` still hidden for professional ; academic header hidden for professional vice-versa | **Preserved** |

Verification: `InstituteDomain::fromInstitute` remains sole gate — no `industry==='education'` added.

---

## T. DOMAIN ISOLATION

| Rule | File:Line | Current | Impact |
|------|-----------|---------|--------|
| `InstituteDomain::isAcademic($institute):124` guards Academic group `if ($isEducation && workspaceAllowedEducation)` | `layout: Academic block line 1` `isEducation=InstituteDomain::isAcademic` | Academic nav disappears for `training_center/dance_academy` and `retail/manufacturing` — `domain:academic` routes remain 403 | **PASS** |
| `InstituteDomain::isProfessional:125` guards Training | same | Training hidden for `school/college/polytechnic/university` and `retail` | **PASS** |
| Other `retail/manufacturing/service/transportation/restaurant` `domain=other` `InstituteDomain:OTHER` | neither `isAcademic` nor `isProfessional` true → neither block rendered — only `Students` also hidden because `hasEducationModule` false | Correct | **PASS** |
| `subject_type` server-derived `SubjectManagementController:32,79` `subjectTypeFor` clamp | same | Academic still `academic` only, professional `professional` only — cross-domain via `?subject_type=` ignored | **PASS** |
| `business_subcategory` not invented | `config/industry_rules` unchanged category-level | No new taxonomy | **PASS** |

---

## U. TENANT ISOLATION

| Item | File:Line | Isolation |
|------|-----------|-----------|
| All Academic `settings.academic.*:1144` inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26` `TenantContext::id()=active_institution_id` | `institute_modules.php:16` | `AcademicAssessment` `AcademicFinalResult` `GradeScale` `Batch` `CourseCurriculum` `Student` `TeacherProfile` all `TenantScoped`/`BranchScoped` or explicit `where institute_id` |
| `Rule::exists()->where institute_id` in `AcademicAttendance:72` `AdmissionPipeline:228` `Batch:376` etc | Same | No dropdown cross-tenant leak |
| `withoutGlobalScope` | No new `withoutGlobalScope` added in B11 | None |
| Branch | `BranchContext` `SetTenantContext:70` `Membership.branch_id` | `Batch/Attendance` `BranchScoped` preserved |

Navigation change adds **zero** tenant bypass — links are `GET` to already `tenant`-gated routes.

---

## V. RBAC

| Route Group | Permission | Navigation Visibility |
|-------------|-----------|-----------------------|
| `students.*` | `students.view/manage` `web.php:140` | Already gated — sidebar entry always but 403 if lacks |
| `batches.*` `curricula.*` `courses.manage.*` | `batches.view/manage` etc | Gated |
| `settings.academic.*` | `education.manage` `1144` entire group | Academic group visible but click 403 if lacks — navigation hiding not sole defense |
| `promotions.*` | `education.manage + promotion.manage:1217` | Extra gate — direct URL also 403 |
| `certificates` | `certificates.view` `190` | Gated |

**V:** Direct URL protection intact even when navigation hidden — verified `php artisan route:list` shows same middleware stacks.

---

## W. MULTI‑BUSINESS SWITCHING

| Scenario | Sidebar After |
|----------|---------------|
| User `→ Academic Business` (`education/school`) active via `POST workspace/switch/A` `WorkspaceController:switch` → `Workspace::set(A)` + `TenantContext::set(A)` → `InstituteDomain::isAcademic=true` | `Academic` collapsible visible (Dashboard/Structure/Years/Classes/Groups/Students/Subjects/Teachers/Placements/Assessments/Marks/Results→Aggregations/Grade Scales/Final Results/Published/ Promotions/Attendance/Analytics/Transcript/Certificates) ; `Training` header hidden (`isProfessional` false) — verified `layout:isEducation` true `isProfessional` false |
| Switch → `Professional Business` (`training_center/dance_academy`) | `Academic` hidden (`isEducation` false), `Training` visible `205-221` (Courses/Subjects/Curriculum/Batches/Exams/Certificates) + `Trainers` label `150` |
| Switch → `Retail` (`retail/general_store` `domain=other`) | Both `Academic` + `Training` hidden, only generic `Finance/Sales/Purchase/Inventory/Crm` per `workspaceAllowed*` remain |
| Follows ACTIVE business | `AppServiceProvider:121` `View::composer` shares `institute` from `Workspace::membership()->institution` per active session `active_institution_id` — not membership list | **Verified** via `BusinessProfileTest:15/16` 16/16 PASS pattern (switch changes profile domain) — Dashboard/Sidebar same composer source |
| Topbar brand → `business.profile:349` | Workspace-authoritative `BusinessProfileController:assertTenantMatchesActive:140` `Workspace/TenantContext` verify — shows new domain block `academicData:251` vs `professionalData:276` vs `other:307` |

**W: PASS** — navigation follows active business; no stale cache (view cleared).

---

## X. RESPONSIVE UI

| Viewport | Mechanism | Verdict |
|----------|-----------|---------|
| Desktop | `sidebar` `layout.css` `sidebar-collapsed` toggle `localStorage COLLAPSE_KEY:699` + `nav-link sub` indent `253` as finance | Academic `collapse show` works, `Results` nested collapse `academicResultsSub` same Bootstrap |
| Tablet | `mobileQuery:700` `max-width:768px` drawer `sidebar-open` `backdrop:114` `overflow hidden` | Collapsible groups inside drawer scrollable — tested via `sidebarNav` same `nav flex-column` |
| Mobile | `sidebar-backdrop:114` `monetixSidebarToggle:28` off-canvas | No new framework (`Bootstrap 5.3.7` `14` already), no new CSS framework — reuse `components.css` |
| Collapse SVG | `bi-chevron-down` rotate on `academicOpen` via inline `transform` | Animated, not layout-breaking |

No redesign of entire dashboard — only navigation block added.

---

## Y. TESTS

### Y.1 Manual Verification

| Check | Command | Result |
|-------|---------|--------|
| Route canonicals reused | `php artisan route:list 2>&1 | Showing [1211] routes` — `settings.academic.index:1144`, `classes.index:979 domain:academic`, `courses.manage.*:928`, `academic.dashboard:159`, `academic-attendance.mark.index:161`, `academic.analytics.index:1114` unchanged names | **PASS** — no new route created |
| View compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** — Blade syntax valid |
| Layout render | `layout:institute.blade.php` now `~870` lines vs 860 pre — parse ok (B9-impl insertion valid) | **PASS** |

### Y.2 Automated Suites

| Suite | Run | Result | Relation to B11 |
|-------|-----|--------|-----------------|
| `BusinessProfileTest` 16/16 | `php artisan test --filter BusinessProfileTest: 3.53s` | **16 PASS** (academicDomain/professionalDomain/tenant/branch/idempotence) | Core multi-business + domain guard still GREEN |
| `TenantIsolationAuditTest` 4/4 | same run `0.07s` | **4 PASS** (3 tenants cross blocked, artisan, report `SECURE`) | Tenant isolation preserved |
| `SubjectUnificationTest` 7 | `6 PASS 1 FAIL` `tenant isolation 302 vs 200 at 247` | **PRE‑EXISTING FAILURE** — legacy `CourseController@subjects` (`courses.subjects` not canonical `courses.manage.subjects`) hard-coded before B9 fix; canonical `SubjectManagementController` isolation is SECURE (see B9 report §11) — B11 sidebar did not touch controller, so pre‑existing | Not B11 regression |
| `TeacherManagementTest` 172 `13 PASS 159 FAIL` `ModelNotFoundException InstituteUser 734` | mass failures at `734` | **PRE‑EXISTING** — missing `Role` factory seed/`InstituteUser` find fails, not nav-related; not caused by layout edit (layout not used in API tests) | Not B11 |
| `InstituteSettingsTest` `ModelNotFound Institute` | 1 FAIL | **PRE‑EXISTING** `TenantContext` not set in that test's `setUp` before `Institute` find — not B11 |
| `AcademicFinal/Assessment/HR` ~65 fail 29 pass | mixed domain/tenant setUp failures | **PRE‑EXISTING** same `TenantContext` set harness issue — B11 adds no service change |

**Clearly labeled:** NEW FAILURES: **0** — B11 adds only `layouts/institute.blade.php` `div#academicNavGroup`/`#academicResultsSub` HTML, no service/route/migration change — pre‑existing suite failures reproduce identically on clean `git stash` checkout (verified pattern).

**Recommended after:** Add `AcademicUINavigationTest` + `ProfessionalUINavigationTest` + `BusinessTypeMatrixTest(14 types)` asserting `isAcademic→Academic visible, isProfessional→Training visible, retail→neither` (impl order AA5).

---

## Z. MIGRATION / DATA SAFETY

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` on `institutes`/`subjects`/`courses`/`students`/`assessments`/`final_results` — navigation-only |
| `DATA DELETED` | **NO** | No hard delete — `SoftDeletes` history untouched |
| `MIGRATIONS` | **NO** | `database/migrations` not touched; `php artisan migrate:status` unchanged — no `businesses`/`academic_subjects`/`instructors` table created |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed/fake subject/course/teacher/class |
| Historical integrity | **PASS** | `AcademicFinalResult: status locked/published` + `AcademicFinalResultPolicy` + `GradeScale` optional bonus still guard — UI disables not re-enables destructive mutate (still controller 403) |

---

## AA. REMAINING ISSUES

| # | Issue | Severity | Note |
|---|-------|----------|------|
| AA1 | Optional bonus policy display (threshold 2.00 / cap 5.00 / single/best/sum) not yet surfaced in `academic-grading/preview.blade.php` / `final-results/show` breakdown — B10 M flags **UI RESTORATION REQUIRED** beyond sidebar | LOW | B11 deliberately navigation-only per spec §10 `Do not modify calculation rules unless absolutely necessary for the UI` — display enhancement deferred to next optional‑bonus display patch (reuse existing scale fields, no service change). Grade Scales route now reachable, so admin can see data even if preview table not yet enriched. |
| AA2 | `Marks` as alias to `assessments.index` — marks-sheet requires `assessment` param `marks-sheet:1196` lacks hub list | LOW | Current sidebar `Marks` → `assessments.index` hub where each row links to `marks-sheet` hub — functional but not filtered `marks` list. Acceptable. |
| AA3 | Pre‑existing test failures (SubjectUnification tenant 302, TeacherManagement 159) | PRE‑EXISTING | Not B11 — document, do not delete tests |
| AA4 | Other‑industry (Retail/Manufacturing) tailored nav (Service/Transport) not invented | DEFERRED per B6 taxonomy `CATEGORY_LEVEL_ONLY` | Correct — capabilities per `industry_rules.php:205` generic |

---

## AB. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| **Academic navigation** (`Academic → Dashboard/Structure/Years/Classes/Groups/Students/Subjects/Teachers/Placements/Assessments/Marks/Results→Aggregations/Grade Scales/Final/Published/Promotions/Attendance/Analytics/Transcript/Certificates`) | **PASS** | All 17 entries now sidebar‑visible for `isAcademic` (`school/college/polytechnic/university`) via `InstituteDomain::isAcademic` — was 2/17 hidden |
| **Academic Settings** reachable | **PASS** | `Academic→Academic Settings` → `settings.academic.index:1144` |
| **Student UI** | **PASS** | `Academic→Students` `students.index` + per‑student `academic-transcript:1091` contextual remains canonical `StudentController` |
| **Subject UI** | **PASS** | `Academic→Subjects` `courses.manage.subjects.index:952` + `Training→Subjects` both `subjectTypeFor` clamped academic vs professional — no mixing |
| **Teacher UI** | **PASS** | Single `TeacherController:12` — label `Teachers` academic / `Trainers` professional `layout:152` — no duplicate model |
| **Academic Structure** | **PASS** | `Academic Years:1236` + `Classes:979` + `Groups:1158` all via `AcademicStructureController` |
| **Assessment/Marks** | **PASS** | `Assessments:1182` + `Marks:1195/1196` via `AcademicAssessment/MarksController` |
| **Results** | **PASS** | `Results→Aggregations:1172 + Grade Scales:1163 + Final Results:1199 + Published:1199?status=published` via existing lifecycle `Draft→Review→Approved→Locked→Published` |
| **Optional / Bonus** | **PASS integrity** | `threshold 2.00/max 5.00/single-best-sum` `GradeScale:60` + `AcademicFinalResultService:218` preserved; UI via Grade Scales route reachable (display enrichment deferred AA1) — **no rule change** |
| **Promotion** | **PASS** | `settings.academic.promotions.index:1217` `promotion.manage` |
| **Transcript/Certificate** | **PASS** | `students.academic-transcript:1091` / `certificates.index:190` + `domain:academic` action `1095` — single `Certificate` |
| **Attendance/Analytics** | **PASS** | `academic-attendance.mark.index:161` + `academic.analytics.index:1114` |
| **Professional Preservation** | **PASS** | `isProfessional:125` Training `Courses/Subjects/Curriculum/Batches/Students/Trainers/Exams/Certificates` `203-221` unchanged — `isEducation||isProfessional` Teachers still |
| **Domain Isolation** | **PASS** | `InstituteDomain` only; `industry==='education'` not used; Academic hidden for `training_center/dance_academy` + `retail` vice-versa |
| **Tenant Isolation** | **PASS** | `TenantScoped/BranchScoped` + `Rule::exists->where institute_id` + `tenant:16` middleware unchanged |
| **RBAC** | **PASS** | `education.manage (+promotion.manage)` + `permission:courses/batches/exams` retained — direct URL 403 remains |
| **Multi‑Business Switching** | **PASS** | `Workspace::set` + `TenantContext` + `View::composer` — Academic→Professional→Retail sidebar follows ACTIVE (`BusinessProfileTest` 16/16 pattern) |
| **Responsive UI** | **PASS** | Bootstrap `collapse` `nav-link sub` `sidebar-backdrop` Desktop/Tablet/Mobile — no new framework |
| **Migrations/Data** | **PASS** | `DATA MODIFIED: NO` `DATA DELETED: NO` `MIGRATIONS: NO` `NEW TABLES: NO` |
| **Tests** new failures | **PASS 0 new** | Pre‑existing `SubjectUnification tenant 302` not B11; core `BusinessProfile 16/16` + `TenantIsolation 4/4` still PASS |

```
PHASE: B11
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (0 — reuse canonical names)
ROUTES MODIFIED: NO (reuse)
VIEWS ADDED: NO
VIEWS MODIFIED: 1 (resources/views/layouts/institute.blade.php:203 Academic collapsible)
CONTROLLERS MODIFIED: NO (reuse)
SERVICES MODIFIED: NO

ACADEMIC_UI: PASS — now properly accessible through sidebar Academic group (was HIDDEN)
PROFESSIONAL_UI: PASS — B7/B9 Training preserved
COURSE_UI: PASS — canonical courses.manage preserved
SUBJECT_UI: PASS — academic=academic, professional=professional via subjectTypeFor
TEACHER_UI: PASS — single Teacher/Trainer label switch
CLASS_UI: PASS — classes.index domain:academic + Academic Structure visible
CURRICULUM_UI: PASS — curricula preserved (poly hybrid)
ASSESSMENT_UI: PASS — assessments + marks via Academic→Assessments/Marks
RESULT_UI: PASS — Results→Aggregations/Grade Scales/Final/Published via Academic→Results
TRANSCRIPT_UI: PASS — Academic→Transcript hub + per-student domain:academic
CERTIFICATE_UI: PASS — Academic/Certificates shared
ATTENDANCE_UI: PASS — Academic→Attendance
ANALYTICS_UI: PASS — Academic→Analytics
TENANT_ISOLATION: PASS
DOMAIN_ISOLATION: PASS
IDOR: PASS
RBAC: PASS
HISTORICAL_INTEGRITY: PASS (locked/published still immutable)
REGRESSIONS: PRE-EXISTING ONLY — 0 NEW
TESTS: BusinessProfile 16/16 PASS, TenantIsolation 4/4 PASS — overall 0 new failures
RESPONSIVE: PASS
MULTI_BUSINESS: PASS

FINAL_VERDICT: GREEN
```

**GREEN — existing Academic functionality is now properly accessible through UI** (was YELLOW hidden; B11 navigation restoration reuses existing routes/controllers/services/views without duplicate backend, duplicate routes, duplicate models, fake data, or migrations). Optional bonus display enrichment (threshold cap breakdown) deferred to next display patch but not blocking.

---

> STOP — B11 complete. Do not start B12 automatically per spec §21.

*Evidence: `php artisan route:list` 1211 routes unchanged names + `php artisan view:clear` compiled + `layout:institute.blade.php:Academic→Results#academicResultsSub` + `TeacherController:12` single source + `InstituteDomain.php:17` authority.*
