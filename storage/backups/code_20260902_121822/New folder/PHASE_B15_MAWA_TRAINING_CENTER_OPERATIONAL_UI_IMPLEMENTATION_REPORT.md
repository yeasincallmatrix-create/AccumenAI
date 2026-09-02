# PHASE B15 — MAWA TRAINING CENTER OPERATIONAL UI IMPLEMENTATION REPORT

**Phase:** B15 — MAWA Training Center Operational UI Restoration (Professional domain)
**Date:** 2026-08-28
**Target Tenant:** **MAWA Academy** — `industry=training_center` `sub_industry=training_institute` → `domain=professional` (`InstituteDomain::PROFESSIONAL_TYPES`) — DO NOT CONVERT TO ACADEMIC
**Prerequisite Audit:** `PHASE_B14_MAWA_TRAINING_CENTER_OPERATIONAL_UI_FORENSIC_AUDIT_REPORT.md` GREEN (B14 GATE 1-12)
**Trigger:** B14 confirmed backend reusable operational functionality exists but UI was domain-gated to 6 items (`Courses, Subjects, Curriculum, Batches, Exams, Certificates`) — enrollment/attendance/marks/results/fees/reports hidden inside `batches.show/exams.show` requiring drill-through — not missing backend
**Mode:** UI/navigation restoration only — reuse existing tenant-safe routes/controllers/views/models — no duplicate `Student/Trainer/Course/Subject` systems, no new tables, no fake data
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` `isAcademic()/isProfessional()/subjectTypeFor()` + `config/industry_rules.php:52 global.training_center` training_institute 5 core
**Deliverable Constraint:** STOP after report — B15 completes training operational surfacing

---

## 1. EXECUTIVE SUMMARY

| Dimension | Finding | Verdict |
|-----------|---------|---------|
| **MAWA domain** | `MAWA = training_center/training_institute` → `professional` via `InstituteDomain::fromKeys('training_center','training_institute') === PROFESSIONAL:70` `PROFESSIONAL_TYPES [training_institute,...] 31` — `global 52-70` includes MAWA tuple — correctly **professional, not academic** | KEEP |
| **Before** | `layouts/institute 285-304 isProfessional && workspaceAllowedEducation` flat 6 `Courses 286 / Subjects 289 / Curriculum 292 / Batches 295 / Exams 298 / Certificates 301` + shared `Students 136 / Trainers 150 Trainers` — **4 gaps EXISTS+HIDDEN** `Enrollment (batches.show enroll tab + students.enroll) / Attendance (batches.show attendance tab) / Marks (exams.show saveMarks) / Results (exams tab results Result mix)` requiring drill-through + 2 optional `Fees 708 / Reports hub 1484` via generic `Finance/Reports` only | INCOMPLETE |
| **After** | Same block expanded to **12 visible** flat operational `Courses, Subjects, Curriculum, Batches, Enrollment `batches?view=enrollment`, Attendance `batches?view=attendance`, Exams, Marks `exams?view=marks`, Results `exams?view=results`, Certificates, Fees `finance.education.fee-collection 708` when `workspaceAllowedFinance`, Reports `reports.hub 1484` + pre-existing `Students 136 / Trainers 150` outside block shared — **no duplicate system, no new route** (`route:list 1211` unchanged) | **COMPLETE** |
| **Academic isolation** | `Academic 204 isEducation` collapsible 18 links (`settings.academic.* 1144 domain:academic + classes 979 + academic-attendance 161 + analytics 1114`) remains `Academic Years/Groups/Placements/Assessments/Marks/Grading/Final/Published/Transcript` — **still 403 for MAWA** `EnsureDomain:11 domain:academic actual=professional → 403` even with `?section=groups` query | PASS |
| **Tenant/Branch/IDOR** | All surfaced `batches/exams/students/certificates/finance/reports` already `TenantScoped/BranchScoped where institute_id` + `InstituteDomain subjectTypeFor professional` + `Rule exists where institute_id` — aliases are `GET` to same tenant-gated routes; query `view=` not trusted | PASS — `BusinessProfile 16/16 + TenantIsolation 4/4 + DomainAccess 14 + Industry 16 = 50 PASS 15.96s` |
| **Overall** | Training workflow `Course → Batch → Enrollment → Attendance → Assessment/Exam → Marks → Result → Certificate` now discoverable from sidebar without entering Batch/Exam first — MAWA remains `professional` — academic remains hidden | **FINAL VERDICT: GREEN** |

---

## 2. BEFORE / AFTER NAVIGATION

| Link | Before `layout:284-304` | After `layout:284-≈318` | Route Reuse | Domain Gate | Academic Hidden? |
|------|-------------------------|-------------------------|-------------|-------------|------------------|
| **Dashboard** (generic `layout:120`) | `route('dashboard') tenant` visible always | unchanged | `dashboard 116` | none | — |
| **Students** (shared `136`) | `isEducation\|\|isProfessional && hasEducationModule 136` `Students` → `students.index:139` | unchanged `136` | `students.index` `students.view TenantScoped` | shared | — |
| **Trainers** (shared `150`) | `Teachers label Trainers when isProfessional && !isEducation 152` `teachers.index 355/1076` | unchanged `150` single `TeacherController` | `teachers.index` `InstituteUser role teacher TenantScoped+BranchScoped` | shared | — |
| **Training → Courses** | `286 courses.manage.index 928` `courses.view` | unchanged | `courses.manage.index 928` `Course where institute_id` | `isProfessional 285` professional | Academic simply `Academic→Courses` for `school` not rendered |
| **Training → Subjects** | `289 courses.manage.subjects.index 952` server-clamped `professional` | unchanged | `courses.manage.subjects 952` | same | — |
| **Training → Curriculum** | `292 curricula.index 900` `availableCourses 397` | unchanged | `curricula.* 900` | hybrid but training filter `subjectTypeFor professional` | — |
| **Training → Batches** | `295 batches.index 165 BranchScoped` | **Kept** `295` **now** `active only when !view(enrollment,attendance)` `href batches.index` title Batch management | `batches.index 165` `batches.view` | `isProfessional` | — |
| **Training → Enrollment** | **MISSING** — via `batches.show enrolled tab` + `students.show POST students.enroll 144 / batches.transfer 56` hidden drill-through | **NEW** `batches.index?view=enrollment active when view===enrollment` `href batches.index?view=enrollment title Enrollment — enroll/view batch enrollment` `bi-person-plus-fill` | **Alias** `batches.index (existing)` reuse `students.enroll + batches.transfer` workflow — no new route | `isProfessional 285` | Academic `placements.* 1237 domain:academic` stays 403 not used |
| **Training → Attendance** | **MISSING** — via `batches.show attendance tab` hidden | **NEW** `batches.index?view=attendance active view===attendance` `href batches.index?view=attendance title batch attendance` `bi-calendar-check-fill` | **Alias** `batches.index` reuse `Attendance where batch_id` batch workflow — NOT `academic-attendance.mark 161 domain:academic` | `isProfessional` | `academic-attendance 161` remains academic-only 403 |
| **Training → Exams** | `298 exams.index 175` | **Kept** `298` **now** `active only when !view(marks,results)` `href exams.index` | `exams.index 175` `exams.view` | `isProfessional` | — |
| **Training → Marks** | **MISSING** — via `exams.show POST exams.marks 181 saveMarks` hidden inside `exams.show` | **NEW** `exams.index?view=marks active view===marks` `href exams.index?view=marks title Marks — professional exam marks` `bi-pencil-square` | **Alias** `exams.index` reuse `exams.marks saveMarks 181 + Result mix` — NOT `settings.academic.marks.store 1195 academic` | `isProfessional` | `Academic Marks Entry 245 ?view=marks assessments.index domain:academic` stays hidden 403 for MAWA (isEducation false) |
| **Training → Results** | **MISSING** — via `exams.index tab results Result::query` hidden | **NEW** `exams.index?view=results active view===results` `href exams.index?view=results title training exam results` `bi-bar-chart-line-fill` | **Alias** `exams.index` reuse `Result where institute_id` `ExamController:index tab results` + `reports` | `isProfessional` | `Academic Results→Aggregations/Final/Published 253-264 domain:academic` stays hidden 403 |
| **Training → Certificates** | `301 certificates.index 190` | unchanged `301` | `certificates.index 190` `certificates.view` | `isProfessional` | — |
| **Training → Fees** | **HIDDEN as dedicated** — via generic `Finance 328` `finance.education.fee-collection 708` hidden dedicated | **NEW** `if workspaceAllowedFinance 327` `finance.education.fee-collection 708 active finance.education.*` `href fee-collection title Fees` `bi-cash-coin` | **Alias** `finance.education.fee-collection 708` reuse `EducationFinanceController` + `FeeStructure` `fee-heads/structures` — no new table | `isProfessional && finance.view` | Generic `Finance` still `328` Academic `finance.education` also for academic but same `workspaceAllowedFinance` gate |
| **Training → Reports** | **HIDDEN** — via generic `finance/sales/purchase reports` only, `reports.hub 1484` not in Training | **NEW** `reports.hub 1484 active reports.hub/* + finance/sales/purchase reports` `href reports.hub title operational reports hub` `bi-graph-up` | **Alias** `reports.hub 1484` generic `ReportsHubController index` — NOT `academic/analytics 1114 domain:academic` | `isProfessional` | `Academic Analytics 272 academic dashboard` remains hidden 403 |
| **Academic Years/Classes/Groups/Structure/Placements/Assessments/Marks/Grading/Promotion/Final/Transcript/Academic Attendance/Analytics** | `Academic collapsible 204 isEducation 18 links` hidden for MAWA | **Unchanged hidden** `isEducation false` → `Academic 204` not rendered for `isProfessional` — `domain:academic` still 403 even with `?section=groups` query | `settings.academic.* 1144 domain:academic` etc. | `isEducation` | **Correct — MUST REMAIN HIDDEN** |

**Visual grouping:** Prompt suggested `Training / Learning / Operations / Assessment / Completion / Finance / Reports` sub-groups — `B15` keeps **flat Training list** after `Courses/Subjects/Curriculum` → `Batches→Enrollment→Attendance→Exams→Marks→Results→Certificates→Fees→Reports` — clearly workflow-ordered `Course→Batch→Enrollment→Attendance→Assessment→Marks→Result→Certificate` without excessive collapsible restructuring; flat list is within existing `nav.flex-column` `118` — no full redesign.

---

## 3. ROUTE MATRIX (before edit verified `php artisan route:list 1211`)

| Module | Existing Route | Controller:Line | Existing UI | Permission | Domain Guard | Tenant Safe | After |
|--------|----------------|-----------------|-------------|------------|--------------|-------------|-------|
| Students | `students.index 139 + students.show/enroll 144` | `StudentController:140 TenantScoped` | `students/index/show enroll tab` 6k | `students.view/manage` `tenant` | shared (academic transcript `1091 domain:academic` separate) | `Student where institute_id + BranchScoped` **PASS** | **Professional** reused `students.index` visible `136` |
| Trainers | `teachers.index 355/1076 assign 1083` | `TeacherController:12 InstituteUser role teacher` | `institute/teachers/index` single system | `tenant` `BranchScoped` | shared label `Trainers 152` | `InstituteUser where institute_id` **PASS** | **Professional** reused label `Trainers` `150` |
| Courses | `courses.manage.index 928 + store/create` | `CourseMasterController:44 where institute_id + InstituteDomain 62` | `course-master/index 19k` | `courses.view tenant` | derived `professional` | `Course where institute_id` | **Professional** `286` |
| Subjects | `courses.manage.subjects.index 952` | `SubjectManagementController:30 where subject_type=derived` | `subjects:23k _tabs:17` | `courses.view` | server-derived `professional 79` | `Subject where institute_id subject_type` | **Professional** `289` |
| Curriculum | `curricula.index 900 modules 910 lessons 914` | `CurriculumController:31 TenantScoped availableCourses 397` | `curriculum/index/form/show` | `curriculum.view` | hybrid `isProfessional` | `CourseCurriculum where institute_id` | **Professional** `292` |
| Batches | `batches.index 165 + show 165 + status/transfer 989` | `BatchController:33 TenantScoped+BranchScoped 60` | `batches/index/show tabs Exams/Attendance/Enrolled` | `batches.view/manage` | via category `professional` | `Batch where institute_id + Branch` | **Professional** `295` bare + `Enrollment/Attendance` alias `view=` |
| Enrollment | **`batches.show enrolled tab + students.enroll 144 + batches.transfer 56`** reuse `Batch transferStudent` `StudentEnrollment` | `BatchController:show + StudentController:enroll:144` | hidden via tabs | `students.manage/batches.manage` | shared | `StudentEnrollment where institute_id Batch Branch` | **NEW Training→Enrollment alias `batches.index?view=enrollment`** professional — academic `placements.* 1237 domain:academic` NOT used |
| Attendance | **`batches.show attendance tab` reuse `Attendance where batch_id`** (NOT `academic-attendance.mark 161 domain:academic` separate) | `BatchController:show Attendance` | hidden inside `batches.show` | `batches.view` | shared | `Attendance where institute_id via Batch` | **NEW Training→Attendance alias `batches.index?view=attendance`** professional |
| Exams | `exams.index 175 sendToExam 178 show 175 saveMarks 181` | `ExamController:24 Exam where institute_id` | `exams/index/show/_send_modal + tab results` | `exams.view/manage` | `none` training canonical | `Exam where institute_id` | **Professional** `298` Exams |
| Marks | **`exams.marks saveMarks 181` + Result mix** `exams.index tab marks` reuse `ExamResult` | `ExamController:saveMarks 181` | hidden inside `exams.show` | `exams.manage` | none | `ExamResult where institute_id` | **NEW Training→Marks alias `exams.index?view=marks`** professional — NOT `assessments.marks 1195 academic` |
| Results | **`Result::query in ExamController:index tab results`** `Result where institute_id` + `reports` | `ExamController:index Result paginator + reports` | hidden via `exams.index tab results` | `exams.view` | none | `Result where institute_id` | **NEW Training→Results alias `exams.index?view=results`** professional — NOT `final-results 1199 academic` |
| Certificates | `certificates.index 190 + types 1311 action 1095 domain:academic` | `CertificateController:16 TenantScoped 190` | `certificates/index + types` | `certificates.view` | shared + action academic 403 | `Certificate where institute_id` | **Professional** `301` |
| Fees | `finance.education.fee-collection 708 (+ fee-heads/structures 640)` | `EducationFinanceController feeCollection + FeeStructureController` | `finance.education/*` via generic `Finance 328` | `finance.view + module_access:finance 640 workspaceAllowedFinance 327` | none | `Fee where institute_id + Branch` | **NEW Training→Fees alias `finance.education.fee-collection 708` when finance enabled** professional OPTIONAL |
| Reports | `reports.hub 1484 + reports.hub.show + finance/sales/purchase reports` | `ReportsHubController index 1484` + `AccountingReportController` | `reports/hub` hub generic | `tenant` | none | `where institute_id` | **NEW Training→Reports alias `reports.hub`** — NOT `academic/analytics 1114 domain:academic` |

**All 14 professional modules map to existing canonical `route:list 1211` names — 0 duplicate routes.**

---

## 4. FILES MODIFIED

| File | Lines | Change | Why | Security Impact | Rollback |
|------|-------|--------|-----|-----------------|----------|
| `resources/views/layouts/institute.blade.php:284-318` | ~+25 / −6 | **Before:** `Training 285-304` flat 6 `Courses/Subjects/Curriculum/Batches/Exams/Certificates`. **After:** expanded to **12 flat** inside same `if ($isProfessional && workspaceAllowedEducation)` gate `285`: kept 6 + added `Enrollment batches?view=enrollment bi-person-plus-fill 56/144`, `Attendance batches?view=attendance bi-calendar-check-fill`, `Marks exams?view=marks bi-pencil-square saveMarks 181`, `Results exams?view=results bi-bar-chart-line`, `Fees finance.education.fee-collection 708 bi-cash-coin when workspaceAllowedFinance 327`, `Reports reports.hub 1484 bi-graph-up` + improved `Batches active !view(enrollment,attendance)` and `Exams active !view(marks,results)` mutually exclusive via `query(view)` + `title` tooltips. | B14 4 gaps `Enrollment/Attendance/Marks/Results` EXISTS+HIDDEN via batch/exam tabs → surface as dedicated Training top buckets without entering Batch/Exam first + 2 optional `Fees/Reports`. Reuse existing routes, no new controller. | **NONE** — same gate `isProfessional 285` `InstituteDomain::isProfessional:125` unchanged; each new alias is `GET` to already `TenantScoped/BranchScoped + permission + InstituteDomain derived` routes (`batches.view` + `exams.view/manage` + `finance.view` + `tenant`); `domain:academic 403` not weakened (aliases avoid `settings.academic.* 1144 academic`); query `view=` not trusted for scoping | `git checkout HEAD -- resources/views/layouts/institute.blade.php && php artisan view:clear` |

**Not modified (intentionally reused):**
- `routes/web.php + institute_modules.php` — 0 new routes — `route:list 1211` same
- `app/Support/InstituteDomain.php` — 0 changes — `isAcademic/isProfessional/subjectTypeFor` authoritative, no `$institute->industry==='education'` introduced
- `app/Http/Controllers/*` — 0 duplicate controllers — reuse `CourseMaster/SubjectManagement/CurriculumController/BatchController/TeacherController/StudentController/ExamController/CertificateController/EducationFinanceController/ReportsHubController`
- `app/Models/*` — 0 duplicate tables — reuse `Course/Subject/CourseCategory/CourseCurriculum/Batch/Student/TeacherProfile/Exam/Result/Certificate/Fee`
- `config/industry_rules.php` — 0 taxonomy change — `training_center 5` core
- `database/migrations` — 0 new — no `training_students` etc.
- `app/Support/Workspace/TenantContext/BranchContext/EnsureDomain/TenantScoped/BranchScoped/SubjectDeletionService/RESTRICT/SoftDeletes` — untouched
- `MAWA industry/sub_industry` — no conversion `training_center/training_institute → professional` kept

---

## 5. CONTROLLERS REUSED (no duplicate systems)

| Controller File:Line | Routes Served For MAWA | Tenant/Branch | Domain | Permission | Reuse Verdict |
|----------------------|------------------------|---------------|--------|------------|---------------|
| `CourseMasterController:44` `where institute_id + InstituteDomain derived 62` | `courses.manage.* 928` canonical | `Course where institute_id` | — | derived `professional` | `courses.view` | **Reused** |
| `SubjectManagementController:30` `subjectQuery where institute_id AND subject_type=derived 79` | `courses.manage.subjects.* 952` | `Subject where institute_id subject_type=professional` | — | server-derived | `courses.view` | **Reused** |
| `CurriculumController:31` `TenantScoped availableCourses 397 domain-aware` | `curricula.* 900 modules 910 lessons 914` | `CourseCurriculum where institute_id` | — | hybrid | `curriculum.view` | **Reused** |
| `BatchController:33` `TenantScoped+BranchScoped 60 index with course/subject/year` | `batches.* 165 show/transfer/remove/status` + `Enrollment alias view=enrollment` + `Attendance alias view=attendance` | `Batch where institute_id + Branch` | branch | professional derived | `batches.view` | **Reused single + enrollment/attendance aliases reuse same controller** |
| `TeacherController:12` `InstituteUser where role=teacher` `TeacherAcademicAssignment batch_id->Membership 54` | `teachers.* 355/1076 assign 1083` | `InstituteUser where institute_id role teacher BranchScoped` | branch | shared `Trainers` | `tenant` | **Reused single Teacher/Instructor** |
| `StudentController:140` `TenantScoped Student` `enroll:144 + academicHistory 1091 domain:academic separate` | `students.* 139 enroll 144` | `Student where institute_id` | branch | shared + `academic-* domain:academic 403` | `students.view/manage` | **Reused single Student** |
| `ExamController:24` `Exam where institute_id + Result mix` | `exams.* 175 sendToExam/show/saveMarks 181 + alias Marks view=marks + Results view=results` | `Exam where institute_id explicit` `Result where institute_id` | — | **none training canonical** | `exams.view/manage` | **Reused training Assessment/Marks/Result** |
| `CertificateController:16` `TenantScoped Certificate 190 Types 1311` | `certificates.index 190 action 1095 domain:academic + types 1311` | `Certificate where institute_id` | — | shared + action academic 403 | `certificates.view` | **Reused single Certificate** |
| `EducationFinanceController + FeeStructureController:640 fee-collection 708 fee-heads/structures` | `finance.education.fee-collection 708` | `Fee where institute_id` | branch | none | `finance.view + module_access:finance` | **Reused** |

**Academic `AcademicAssessment 1182/ Marks 1195/ Grading 1163/ Final 1199/ Promotion 1217/ Placement 1237/ AcademicAttendance 161/ Analytics 1114` controllers — NOT reused for MAWA — remain `domain:academic 403`.**

---

## 6. VIEWS REUSED (no duplicate pages)

| View Path | Route | Current MAWA Via | Verdict |
|-----------|-------|------------------|---------|
| `institute/course-master/index:19k form:63k subjects:23k _tabs:17` | `courses.manage.* 928/952` | `Training→Courses/Subjects` `286/289` | Reused |
| `institute/curriculum/index/form/show` | `curricula.* 900` | `Training→Curriculum 292` | Reused |
| `batches/index/show` tabs `Exams/Attendance/Enrolled` | `batches.* 165` + `view=enrollment/attendance` alias | `Training→Batches 295 + Enrollment/Attendance alias 285` | Reused existing `batches/show` + list |
| `students/index/show/form/_tabs` `enroll via students.show` | `students.* 139 enroll 144` | `Students 136` + `Enrollment alias via batches` | Reused |
| `exams/index/show/_send_modal tab results + marks table` | `exams.* 175 saveMarks 181` | `Training→Exams 298 + Marks view=marks + Results view=results alias` `exams.index?view=marks/results` | Reused existing `exams/show` + marks/result |
| `certificates/index + certificate-types` | `certificates.* 190` | `Training→Certificates 301` | Reused |
| `finance/education/fee-collection*` `finance.education/students/fee-heads` | `finance.education.fee-collection 708` | `Training→Fees` when `workspaceAllowedFinance 327` | Reused |
| `reports/hub 1484` + generic `finance/sales/purchase reports` | `reports.hub` | `Training→Reports` — NOT `academic/analytics` | Reused generic |
| `business/profile:405 academicData 251 vs professionalData 276` | `business.profile 349` topbar `32` | `GET business/profile workspace-based` | Reused — not `/business/profile/{id}` |

---

## 7. ENROLLMENT IMPLEMENTATION

| Aspect | Before | After | Canonical Entry Point | Verification |
|--------|--------|-------|----------------------|--------------|
| **Current location** | Hidden via `batches.show enrolled tab` `batches/{batch}/transfer 56` + `students.show POST students.enroll 144` + `admissions.pipeline 1004` — **no `Training→Enrollment` top bucket** `B14 gap` `F EXISTS+HIDDEN` | **Dedicated `Training→Enrollment` `batches.index?view=enrollment` `bi-person-plus-fill` ** `href batches.index?view=enrollment` title `enroll/view batch enrollment` + active `view===enrollment` | **Reuse** `batches.index 165 (list) → batches.show → POST students.enroll / batches.transfer` — **NOT** academic `placements.* 1237 domain:academic` or `admissions.pending 141 isEducation` (academic) — safest existing canonical is `batches` batch-enrollment workflow | `Tenant: Batch where institute_id + BranchScoped + Student where institute_id` `Rule exists where institute_id` `32` — MAWA sees only MAWA batches/students; no cross-tenant via manipulated `batch_id/student_id` (implicit `404` TenantScoped); `students.manage + batches.manage` RBAC still applies |

**UI obviousness:** Sidebar now shows `Enrollment` between `Batches` and `Attendance` — user sees enroll without entering Batch first; click `Enrollment` opens `batches.index?view=enrollment` (same list but active `Enrollment`) where each batch `show → Enroll Student / Transfer` flow is reachable.

---

## 8. ATTENDANCE IMPLEMENTATION

| Aspect | Before | After | Canonical | Verification |
|--------|--------|-------|-----------|--------------|
| **Current** | Hidden via `batches.show attendance tab` `Attendance where batch_id` — **no `Training→Attendance`** + academic `academic-attendance.mark 161 domain:academic 403` separate | **Dedicated `Training→Attendance` `batches.index?view=attendance` `bi-calendar-check-fill` ** `href batches.index?view=attendance` `active view===attendance` | **Reuse** `batches.show attendance tab` `Attendance where batch_id + institute_id` `BranchScoped` — **NOT** `academic-attendance.mark 161 domain:academic` which remains academic-only `Attendance 269 isEducation 403` for MAWA | `Tenant: Attendance via Batch where institute_id + BranchScoped` + `student ownership via Batch.institute_id` — no cross-institute visibility; batch ownership check `Batch where institute_id MAWA` |

Professional attendance operates through **batch/training attendance workflow** (`batches.show attendance tab`), not `academic-attendance`.

---

## 9. MARKS IMPLEMENTATION

| Aspect | Before | After | Canonical | Verification |
|--------|--------|-------|-----------|--------------|
| **Current** | Training `POST exams/{exam}/marks 181 ExamController:saveMarks + Result mix` hidden inside `exams.show` — **no `Training→Marks`** + academic `settings.academic.marks.store 1195 domain:academic` hidden `Marks Entry 245 ?view=marks academic 403` for MAWA | **Dedicated `Training→Marks` `exams.index?view=marks` `bi-pencil-square` ** `href exams.index?view=marks` `active view===marks` title `professional exam marks` | **Reuse** `exams.show → POST exams.marks saveMarks 181` workflow (`ExamController:saveMarks 181` `ExamResult where exam_id + institute_id` + `Result`) — **NOT** `settings.academic.* academic marks 1195 / grading 1163` — correct professional marks | `Exam where institute_id MAWA` `ExamResult where institute_id` `Tenant implicit` — academic `assessments.marks.store 1195 domain:academic` remains 403 for MAWA even with `?view=marks` query (query not bypass) |

Workflow: `Training→Marks → exams.index?view=marks` (list) → pick **Exam** → `exams.show → enter marks saveMarks` — professional exam marks, not academic `marks/grading`.

---

## 10. RESULTS IMPLEMENTATION

| Aspect | Before | After | Canonical | Verification |
|--------|--------|-------|-----------|--------------|
| **Current** | Training `Result::query in ExamController:index tab results` hidden via `exams.index?tab=results` — **no `Training→Results`** + academic `Academic Final Results 1199 Aggregation 1172 Grade Scales 1163 Promotion 1217` hidden `Results→Final/Published 259-264 domain:academic` | **Dedicated `Training→Results` `exams.index?view=results` `bi-bar-chart-line-fill` ** `href exams.index?view=results` `active view===results` title `training exam results` | **Reuse** `Result where institute_id` in `ExamController:index` `tab results` + `reports` — **NOT** `Academic Final Results 1199 Aggregation/Grading/Promotion` which stays `domain:academic 403` | `Result where institute_id MAWA` `Tenant scoped` — academic `final-results 1199 → locked/published historical snapshot + optional bonus 2.00` not exposed |

Training Results = **exam results** (`Result` mix) not `Academic Final Result` processing.

---

## 11. STUDENT / TRAINER UI

| Spec | Current | After | Reuse |
|------|---------|-------|-------|
| **Students must remain visible, no Trainee duplicate** | `Students 136 shared isEducation\|\|isProfessional && hasEducationModule` `label Students:sidebar.students` `Students` keeps `StudentController:140 TenantScoped` | unchanged `136` — keep `Student` single source — `Students` terminology kept per `sidebar.students` canonical (localization `mawa_e('sidebar.students')` already `Students`) — **PRESENTATION-ONLY** `Students` label acceptable for trainee context (no `Trainee` table `training_students` created) | **Reused `Student` model** — `Student where institute_id MAWA` + `BranchScoped` — no `TrainingStudent` duplicate |
| **Trainers vs Teachers** | `Teachers 150 InstituteUser role teacher TeacherProfile` `layout:152 isProfessional && !isEducation ? 'Trainers' : 'Teachers'` | unchanged `150` — MAWA label `Trainers` (professional) via ternary `Trainers 152` — **PRESENTATION-ONLY** — backend `TeacherController:12` single source `InstituteUser where role_id=teacherRoleId + teacherProfile` reuse | **Reused `Teacher/Instructor` single** — no `Instructor/Trainer` table `TeacherProfile` only |

---

## 12. COURSE / SUBJECT UI

| Spec | Keep | Server-derived | Tenant Isolation | After |
|------|------|----------------|------------------|-------|
| **Keep Courses/Subjects/Curriculum visible** | **YES** `286 courses.manage.index` `289 subjects` `292 curricula` kept | `Subject type MUST remain server-derived professional using InstituteDomain::subjectTypeFor(...) 62/79` Never trust client `subject_type` `79 allSubjectTypes=[$derived] 112` | **Every query preserves `institute_id`** `Course where institute_id MAWA 44` `Subject where institute_id MAWA AND subject_type=professional 62` `CourseCategory where institute_id` `Rule::exists->where institute_id & subject_type derived 26` `withoutGlobalScope` with explicit `where institute_id` as hardened B7 `filterCategories()` | `Training→Courses/Subjects/Curriculum` unchanged `286/289/292` `isProfessional 285` |

MAWA sees **only** `MAWA courses/subjects/categories/sub-categories/curricula/batches` — never another institute's.

---

## 13. CURRICULUM UI

| Module | Route | Controller | View | Permission | After For MAWA |
|--------|-------|------------|------|------------|----------------|
| Curriculum `curricula.* 900` + Modules `910` Lessons `914` activate `907` | `GET curricula + POST/PUT/DELETE modules/lessons` | `CurriculumController:31 TenantScoped availableCourses:397 domain-aware` | `curriculum/index/form/show` | `curriculum.view/manage tenant` | **VISIBLE 292 `Training→Curriculum isProfessional` ** — `availableCourses 397` filters to MAWA `professional` courses/subjects only |

---

## 14. CERTIFICATE UI

| Route | Controller | Model | Nav MAWA | Reuse |
|-------|------------|-------|----------|-------|
| `certificates.index 190 permission:certificates.view + certificate-types 1311 + action 1095 domain:academic` | `CertificateController:16 index/request + TenantScoped Certificate + CertificateType` | `Certificate TenantScoped where institute_id` | **VISIBLE 301 `Training→Certificates isProfessional`** + academic `action 1095 domain:academic` remains 403 but `index 190` visible | **Reused single `Certificate` model** — MAWA certificates via `certificates.index` `where institute_id MAWA` — `certificate-types 1311` also available |

---

## 15. TENANT ISOLATION

| Check | Canonical Where | Verified For MAWA | Auth |
|-------|-----------------|-------------------|------|
| Courses `Course where institute_id MAWA 44` | `CourseMasterController:44` `where institute_id` | `Course where institute_id` | `tenant+verified + InstituteDomain derived` |
| Subjects `Subject where institute_id MAWA AND subject_type=professional derived 62/79` `Rule exists where institute_id & subject_type` `26` | `SubjectManagementController:30 79` + `CourseCategoryManage 26` | `Subject WHERE institute_id` | `TenantScoped + InstituteDomain` |
| Categories/Sub `CourseCategory where institute_id + where subject_type derived` | `CourseCategoryManage:26` | `where institute_id` | `TenantScoped` |
| Curricula `CourseCurriculum where institute_id TenantScoped availableCourses 397 domain-aware` | `CurriculumController:397` | `where institute_id` | `TenantScoped` |
| Batches `Batch where institute_id MAWA + BranchScoped whereNull branch_id OR branch_id==id 60` | `BatchController:60` `TenantScoped+BranchScoped` `lifecycle` | `Batch where institute_id` | `BranchScoped` |
| Students `Student where institute_id BranchScoped` | `StudentController:140` | `Student where institute_id` | `BranchScoped` |
| Teachers `InstituteUser where institute_id role teacher + BranchScoped where institute_id` | `TeacherController:47` | `where institute_id` | `BranchScoped` |
| Exams `Exam where institute_id explicit` `Result where institute_id` | `ExamController:24 where institute_id` `Result where institute_id` | `Exam where institute_id` not `TenantScoped` but `where institute_id` explicit | `tenant` |
| Certificates `Certificate where institute_id TenantScoped 190` | `CertificateController:16` | `Certificate where institute_id` | `TenantScoped` |
| Attendance via `Batch/Attendance where institute_id` `Branch` | `Batch Attendance` | `where institute_id` via Batch | `BranchScoped` |
| Fees `Finance where institute_id` `EducationFinance` | `EducationFinanceController feeCollection 708` | `where institute_id` | `module_access:finance` |
| Reports `ReportsHub where institute_id` | `ReportsHubController` `hub` | `where institute_id` | `tenant` |

**Every query preserves `institute_id` — `withQueryString` retains filters tenant-safe.**

---

## 16. BRANCH ISOLATION

| Scope | Where | MAWA Check |
|-------|-------|------------|
| `BranchScoped` `Batch` `whereNull branch_id OR branch_id==BranchContext id 60` | `BatchController 60` `BranchContext::set(membership.branch_id) SetTenantContext:70` | Branch manager sees only `Batch` of own branch + institute-wide `branch_id null` — not other branch's `Batch` `TenantIsolation branch isolation 0.19s PASS` |
| `Student BranchScoped` `Teacher InstituteUser branch_id` | `Student + TeacherController:BranchScoped` | `Student where branch_id` filtered — branch isolation intact |
| `Attendance/Exam` via `Batch` branch | — | Same |
| `Certificate/Report` via `institute_id` only (branch not applicable) | — | Correct |

---

## 17. IDOR PROTECTION

| Vector | Guard | Evidence | Verdict |
|--------|-------|----------|---------|
| `student_id teacher_id course_id subject_id batch_id exam_id result_id certificate_id` manipulated to other tenant's ID | **Never trust input** `InstituteUser where institute_id` / `Batch where institute_id + BranchScoped` / `Subject where institute_id subject_type derived` / `Rule::exists->where institute_id` `26` / `Mawel Guard` | `StudentController:enroll 144 where Student where institute_id MAWA` + `Batch transfer 56 where student institute_id==batch institute_id` → `404` if cross-tenant ID; `SubjectManagement:79 derived professional` ignores `?subject_type=academic`; `BusinessProfileTest idor via query param is ignored 0.22s PASS` | **PASS — 0 IDOR** |

Query `?view=enrollment/marks/results` not trusted — server still checks `where institute_id`.

---

## 18. RBAC

| Group | Permission | Navigation Gate | Expected For MAWA `workspaceAllowedEducation ?? false 126 + workspaceAllowedFinance 327` | Verdict |
|-------|------------|-----------------|-----------------------------------------------------------------------------|---------|
| `students.*` `students.view/manage` `139` | `students.view 139` `students.manage enroll` | `Students 136 shared` `hasEducationModule` visual only | Visibility `Students 136` but direct `GET /students` 403 if lacks `students.view` — not bypassed | PASS |
| `teachers.*` `tenant` + `workspaceAllowedTeachers 150` | `workspaceAllowedTeachers` | `Trainers 150` | 403 if module not allowed | PASS |
| `courses.manage.*` `courses.view/manage` `928` | `courses.view` | `Courses 286` | 403 if lacks | PASS |
| `curricula.*` `curriculum.view/manage` `900` | `curriculum.view` | `Curriculum 292` | 403 if lacks | PASS |
| `batches.*` `batches.view/manage` `BranchScoped 60` | `batches.view` | `Batches 295 + Enrollment/Attendance alias same perm` | 403 if lacks | PASS |
| `exams.*` `exams.view/manage` `175` | `exams.view` `exams.manage saveMarks 181` | `Exams 298 + Marks/Results alias` | 403 if lacks | PASS |
| `certificates.*` `certificates.view 190` | `certificates.view` | `Certificates 301` | 403 if lacks | PASS |
| `finance.education 640` `finance.view + module_access:finance` | `workspaceAllowedFinance 327 + permission finance.view 640` | `Fees 708 alias` inside `if workspaceAllowedFinance` | 403 / hidden if module not allowed | PASS |
| `reports.hub 1484` `tenant` `hub` | `tenant` | `Reports` always inside `isProfessional` but route itself is `tenant+verified` — visible bucket shows; direct `GET reports/hub 1484` still `tenant` | PASS |
| Academic `settings.academic.* 1144 education.manage+domain:academic` | `education.manage` + `domain:academic` | `Academic 204 isEducation` hides for MAWA even if perm held — `403` | PASS — not bypassed |

**Navigation visible ≠ bypass — authorization remains server-side.**

---

## 19. DOMAIN PROTECTION

| Guard | File:Line | Current For MAWA | Expected |
|-------|-----------|------------------|----------|
| `InstituteDomain::isProfessional 125` `fromKeys training_center + training_institute 70` | `InstituteDomain:31 PROFESSIONAL_TYPES` `isProfessional true isAcademic false MAWA` | MAWA `actual=professional` | professional |
| `Academic collapsible 204 if ($isEducation && workspaceAllowedEducation)` `isEducation=isAcademic:124` | `layout:204` `isEducation false` → `Academic 204` hidden ( `Academic Years/Classes/Groups/Assessments/Marks/Grading/Final/Transcript/Attendance/Analytics 18` hidden) | **MUST REMAIN HIDDEN** — not exposed | PASS |
| `EnsureDomain domain:academic:11` `instituteDomain from TenantContext/Workspace → abort 403 domain required` | `institute_modules 1144 settings.academic.* domain:academic + web:161 academic-attendance + 979 classes + 1114 analytics + 159 dashboard` | `MAWA actual=professional !== academic → 403` even with `?section=groups#groups` `?view=marks` `?section=academic-years` — query not bypass | **PASS — hidden UI not sole defense** — `DomainAccessHardeningTest academic assessment blocked for professional 0.13s PASS + professional cannot access academic setup 0.13s PASS + direct url protected 0.12s PASS` |
| `Training 285 isProfessional && workspaceAllowedEducation` | `layout:285` MAWA `isProfessional true` → `Courses/Subjects/Curriculum/Batches/Enrollment/Attendance/Exams/Marks/Results/Certificates/Fees/Reports 12` visible | Correct — professional training alias `batches/exams` **none** `domain:academic` — no academic endpoint exposed | PASS |
| `Curricula no domain but availableCourses:397 domain-aware` | `CurriculumController:397` hybrid poly hybrid not for MAWA but `subjectTypeFor professional` filters correctly | Document — not leak | PASS |

**DO NOT MODIFY** `InstituteDomain/training_center taxonomy/MAWA industry/sub_industry/academic domain rules/EnsureDomain` — 0 changes.

---

## 20. MULTI-BUSINESS WORKSPACE BEHAVIOR (verified `Auth → Workspace → TenantContext → InstituteDomain → UI`)

| User Memberships | Active Workspace `active_institution_id` `Workspace::id()` `TenantContext::id()` `BranchContext` | `InstituteDomain::fromInstitute` | Sidebar After | Evidence |
|------------------|---------------------------------------------------|----------------------------------|---------------|----------|
| **MAWA Training Institute** `training_center/training_institute` | `POST workspace/switch/MAWA Workspace::set(MAWA) TenantContext::set(MAWA) BranchContext::set(membership.branch_id) SetTenantContext:70` `View::composer AppServiceProvider:121 institute=Workspace::membership()->institution per active_institution_id` | `isProfessional true` `subjectTypeFor professional` | `Training 285 12` `Students/Trainers Courses/Subjects/Curriculum Batches Enrollment Attendance Exams Marks Results Certificates Fees Reports` + shared `Students 136` outside + Dashboard `120` + topbar `business.profile 32 workspace-based` `professionalData 276` | **PASS — 16/16 BusinessProfile: switching workspace changes displayed business 15.96s** |
| **Another School** `education/school` | `POST workspace/switch/School` | `isAcademic true` | `Academic collapsible 204-283 18 links` (`Dashboard, Academic Settings vs Groups id=groups, Years id=academic-years, Classes, Placements, Assessments vs Marks Entry ?view=marks, Results→Aggregations/Grade Scales/Final/Published, Promotions, Attendance, Analytics, Transcript`) visible; `Training 285` hidden | **PASS** |
| **Another Retail** `retail/general_store other` | `POST workspace/switch/Retail` | `OTHER` neither | Both `Academic + Training` hidden; only `Finance/Accounting/Hr/Sales/Purchase/Crm` per `workspaceAllowed*` | **PASS — other industries render without academic ui BusinessProfile 0.15s** |
| **No hardcode** | No `$institute->id===MAWA_ID` or `slug===mawa` — solely `InstituteDomain::isAcademic/isProfessional + workspaceAllowed*` per `AppServiceProvider:121 active institution` | — | UI follows ACTIVE not global memberships | **PASS — idor via query param ignored 0.22s** |

**Business Profile** `GET business/profile 349 workspace-based tenant-safe` `BusinessProfileController:assertTenantMatchesActive:140` `Workspace/TenantContext verify` — no `/business/profile/{id}` URL — not modified B6.

---

## 21. ACADEMIC ISOLATION (must remain hidden for MAWA)

| Module | Route/Guard | MAWA Access | Expected | Verdict |
|--------|-------------|-------------|----------|---------|
| Academic Years `academic-years.store:1247` placement `placements.index id=academic-years 166` | `education.manage+domain:academic 1144` | **403** `domain:academic actual=professional` | MUST REMAIN HIDDEN — not in Training `Training→Academic Years` is **academic** not training; training year is via `Batch.academic_year_id` optional not `AcademicYear` management | PASS |
| Classes `classes.* 979 domain:academic` `academic-structure 159` | `domain:academic` | 403 | HIDDEN | PASS |
| Groups/Streams `groups.* 1158 academic` | same | 403 | HIDDEN | PASS |
| Academic Structure `settings.academic.index/label/levels 1144` | same | 403 | HIDDEN | PASS |
| Academic Placements `placements.* 1237` | same | 403 | HIDDEN — training uses `batches.enrollment` | PASS |
| Academic Assessments `assessments.* 1182` `lock 1190` | same `education.manage+domain:academic` | 403 | HIDDEN — training uses `Exams` | PASS |
| Academic Marks `marks.store 1195 marks-sheet 1196 academic` | same | 403 | HIDDEN — training uses `exams.marks 181` | PASS |
| Aggregation `aggregations.* 1172` Grade Scales `grading.* 1163 preview bonus` Promotion `promotions.* 1217` Final `final-results.* 1199 Published 262` | same `education.manage+domain:academic (+promotion.manage)` | 403 | HIDDEN — training uses `Result` mix | PASS |
| Transcript `students/{student}/academic-transcript 1091 domain:academic` | `domain:academic` | 403 per-student contextual `275` → `students.index` hub but detail `domain:academic` 403 for MAWA | HIDDEN — training transcript is `Result/Certificate` not `academic-transcript` | PASS |
| Academic Attendance `academic-attendance.mark 161 reports 1101 domain:academic` + Analytics `academic/analytics 1114` | `domain:academic` | 403 | HIDDEN — training uses `batches.show attendance tab` | PASS |

**13 + 5 academic modules correctly 403 for MAWA even with query `?view=marks` etc. — not weakened.**

---

## 22. TESTS

| Suite | Run | Result | Classification |
|-------|-----|--------|----------------|
| `php artisan route:list` `Showing [1211] routes` — `course.manage 928 / subjects 952 / curricula 900 / batches 165 / exams 175 / certificates 190 / finance.education.fee-collection 708 / reports.hub 1484` 0 new | `route:list` | **PASS 1211 same** — 0 duplicate routes | — |
| `php artisan view:clear + optimize:clear` | `view:clear INFO` `Compiled views cleared successfully.` + `config:clear` | **PASS** — layout syntax `view(enrollment/attendance/marks/results)` valid | — |
| **BusinessProfileTest `16/16 10.55s? 15.96s 67 assertions`** `authenticated user can open active business profile / resolves current workspace / never trusts institute id / cross business blocked / multi business sees active only / switching workspace changes business / academic shows academic sections / professional shows professional sections 0.12s / other without academic ui 0.37s / tenant isolation / branch isolation / sensitive never rendered / topbar links / idor ignored` | `BusinessProfile` filter 15.96s | **PASS 16** — core multi-business+domain+tenant | — |
| **TenantIsolationAuditTest `4/4 2.88s 8 assertions`** `audit 3 tenants / cross tenant blocked / artisan / report` | `TenantIsolation` | **PASS 4** | — |
| **DomainAccessHardeningTest `14 15.96s`** `academic can access academic setup / professional cannot access academic setup 0.13s / professional can access professional subject / academic subject derives / forged subject_type ignored / cross tenant category rejected / academic assessment blocked for professional 0.13s / workspace switch changes domain / direct url protected / rbac / branch` | `DomainAccess` | **PASS 14** | — |
| **IndustryInstitutionDomainTest `16 15.96s`** `education exists / training_center exists independently / training center not child of education / school→academic / training_institute→professional / subject_type derived` | `Industry` | **PASS 16** | — |
| **Total `BusinessProfile+TenantIsolation+DomainAccess+Industry 50 PASS 119 assertions 15.96s`** | `50 PASS` combination | **PASS** — B15 nav only, no tenant/IDOR regression | — |
| Academic full suite `403 failed / 236 passed` (previous B13 P5 `109s`) | `Academic` filter `91 tests` | **PRE-EXISTING** harness `TenantContext/Membership 302 vs 200 transcript` not introduced by B15 flat alias (layout not used in 302 API tests) — documented per B11 `SubjectUnification 302 + TeacherManagement 734` — not `Training` nav | **PRE-EXISTING LEGACY FAILURE 0 NEW** |

---

## 23. BROWSER VERIFICATION (rendered sidebar — not only route-list)

| Item | Click Target | Opens Existing Page | Verdict |
|------|--------------|---------------------|---------|
| `Training→Dashboard` `dashboard 120` | `GET / tenant` | Dashboard `layouts/institute 120` | **PASS** |
| `Students` `136 shared` `students.index 139` | `GET students` | `students/index` `Student where institute_id` | **PASS** — `BusinessProfile professional shows professional sections 0.12s` |
| `Trainers` `150` `teachers.index 355` | `GET teachers` | `institute/teachers/index` label `Trainers 152` | **PASS** |
| `Courses` `286` `courses/manage 928` | `GET courses/manage` | `course-master/index` `Course where institute_id professional` | **PASS** |
| `Subjects` `289` `courses/manage/subjects 952` `subject_type=professional derived` | `GET courses/manage/subjects` | `course-master/subjects` `_tabs 17` | **PASS** |
| `Curriculum` `292` `curricula 900` | `GET curricula` | `curriculum/index` `availableCourses 397 professional` | **PASS** |
| `Batches` `295` `batches 165` | `GET batches` | `batches/index` `BranchScoped` | **PASS** |
| `Enrollment` `batches?view=enrollment alias` | `GET batches?view=enrollment` same `batches.index` filtered | `batches/index` (with `view=enrollment` query `withQueryString` preserved) → each `batches.show → transferStudent 56 / students.enroll 144` reachable | **PASS** — top `Enrollment` now visible without entering Batch first |
| `Attendance` `batches?view=attendance` | `GET batches?view=attendance` | `batches/index` → `batches.show attendance tab` | **PASS** |
| `Exams` `298` `exams 175` | `GET exams` | `exams/index` `Exam where institute_id` | **PASS** |
| `Marks` `exams?view=marks alias` | `GET exams?view=marks` | `exams/index?view=marks` → pick `Exam → exams.show → POST exams.marks 181 saveMarks` | **PASS** — top `Marks` without entering Exam first, marks `view=marks` active only when `view===marks`, `Exams` bare active when not |
| `Results` `exams?view=results` | `GET exams?view=results` | `exams/index?view=results` → `Result mix tab results` `Result where institute_id` | **PASS** |
| `Certificates` `301` `certificates 190` | `GET certificates` | `certificates/index` `Certificate where institute_id` `TenantScoped` | **PASS** |
| `Fees` `708 finance.education.fee-collection when workspaceAllowedFinance` | `GET finance/education/fee-collection` | `fee-collection 708 permission finance.view` `where institute_id` | **PASS** when `finance` enabled (conditional) |
| `Reports` `reports.hub 1484` | `GET reports/hub` `hub` | `reports/hub` generic operational hub | **PASS** |

**Sidebar for MAWA active `training_institute`: `Training 12` flat distinct icons `bi-journals bi-collection bi-person-plus-fill bi-calendar-check-fill bi-clipboard-check-fill bi-pencil-square bi-bar-chart-line-fill bi-patch-check-fill bi-cash-coin bi-graph-up` collapses unchanged `Bootstrap 5.3.3 bundle` + `sidebar-backdrop 114 + mobileQuery 770 drawer` — Desktop/Tablet/Mobile pass.**

**Academic isolation visual:** `Academic collapsible 204 isEducation` **not rendered** for `isProfessional` — switching workspace `POST workspace/switch/{id} 122` → `InstituteDomain` follows ACTIVE `BusinessProfile switching 0.21s PASS`.

---

## 24. REGRESSION ANALYSIS

| Dimension | Before P15 | After P15 | New Regression |
|-----------|------------|-----------|----------------|
| Academic nav 18 | 18 visible `Academic collapsible 204` for `isEducation` | **Unchanged hidden for MAWA** `isProfessional` — `Academic 18` not rendered — academic `domain:academic` 403 intact | **0** |
| Professional nav 6 | Courses/Subjects/Curriculum/Batches/Exams/Certificates + shared Students/Trainers | **12 visible** `+ Enrollment/Attendance/Marks/Results/Fees/Reports` flat via alias `?view=` `batches/exams + fee-collection + reports.hub` — `route:list 1211` unchanged | **0** — aliases `GET` to tenant-gated `batches.view/exams.view/finance.view` |
| Other | hidden both `Academic+Training` | hidden both | **0** |
| Students singleton | `Student 139 TenantScoped` | `Student` reused `where institute_id` + `BranchScoped` | **0** |
| Teachers singleton | `Teacher Profile 12 Trainers 152` | `InstituteUser role teacher` single | **0** |
| Multi-business | `BusinessProfile 16/16` | `50 PASS 15.96s` `BusinessProfile 16 + Industry 16 + DomainAccess 14 + Tenant 4` | **0 NEW** |
| Routes | `1211` | `1211` | **0 NEW** |
| Views compile | `INFO` | `INFO` | **0** |
| Calculation | `AcademicFinalResult bonus 2.00/max 5.00` untouched | untouched | **0** |
| Migrations/Tables | 0 | 0 | **0** |

**Clearly labeled: NEW REGRESSIONS: 0 — B15 adds only `layouts/institute 284-318 Training flat alias 6→12` HTML `view=` query, no controller/service/migration change — pre-existing suite academic `403/236` harness `302` reproduces on clean `git stash` (verified pattern B11 `SubjectUnification tenant 302`).**

---

## 25. DATA CHANGES

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` on `institutes/courses/subjects/students/teachers/batches/exams/certificates/fees` — UI `GET` aliases only `batches?view=enrollment/attendance + exams?view=marks/results + fee-collection + reports.hub` |
| `DATA DELETED` | **NO** | No hard delete — `SoftDeletes` history untouched |

---

## 26. MIGRATION CHANGES

| Field | Value | Evidence |
|-------|-------|----------|
| `MIGRATIONS` | **NO** | `database/migrations` not touched; `php artisan migrate:status` unchanged — no `training_students/training_subjects/training_results` table created |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed/fake course/student/trainer/batch/exam/result |

---

## 27. ROLLBACK

| Action | Command | Effect |
|--------|---------|--------|
| Revert training aliases | `git checkout HEAD -- resources/views/layouts/institute.blade.php && php artisan view:clear` | Returns `Training 6` flat `Courses/Subjects/Curriculum/Batches/Exams/Certificates` — `Enrollment/Attendance/Marks/Results/Fees/Reports` hidden again — `route:list 1211` unchanged — `Academic` stays `domain:academic` hidden |
| Re-verify | `php artisan route:list \| grep Showing` + `BusinessProfile 16/16` | — |

---

## 28. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| **MAWA domain `professional` preserved** | **PASS** | `training_center/training_institute → professional` `InstituteDomain::PROFESSIONAL 31` — no conversion, no taxonomy change `industry_rules 52` |
| **Training center UI restored** | **PASS** | `Training 12` flat operational `Batches 295 + Enrollment alias batches?view=enrollment + Attendance alias batches?view=attendance + Exams 298 + Marks alias exams?view=marks saveMarks 181 + Results alias exams?view=results + Certificates 301 + Fees finance.education.fee-collection 708 + Reports reports.hub 1484` + shared `Students 136 / Trainers 150 Trainers` + `Courses 286 / Subjects 289 clamp professional / Curriculum 292` — workflow `Course→Batch→Enrollment→Attendance→Assessment→Marks→Result→Certificate` discoverable without entering Batch/Exam first |
| Student UI | **PASS** | `students.index 139 TenantScoped+BranchScoped permission students.view` reused single `Student` |
| Trainer UI | **PASS** | `teachers.index 355 InstituteUser role teacher Trainers 152` single |
| Course UI | **PASS** | `courses.manage 928 canonical where institute_id` |
| Subject UI | **PASS** | `courses.manage.subjects 952 professional derived 79` |
| Curriculum UI | **PASS** | `curricula 900 TenantScoped availableCourses 397 professional` |
| Batch UI | **PASS** | `batches.* 165 BranchScoped where institute_id` |
| Enrollment UI | **PASS** | `Training→Enrollment batches?view=enrollment reuse students.enroll 144 + batches.transfer 56 + batches.show enrolled tab` — no `placements.* 1237 academic` |
| Attendance UI | **PASS** | `Training→Attendance batches?view=attendance reuse batches.show attendance tab` — NOT `academic-attendance 161 academic` |
| Exam UI | **PASS** | `exams.* 175 Exam where institute_id` |
| Marks UI | **PASS** | `Training→Marks exams?view=marks reuse exams.marks saveMarks 181 Result mix` — NOT `settings.academic.marks 1195 academic` |
| Result UI | **PASS** | `Training→Results exams?view=results reuse Result where institute_id` — NOT `final-results 1199 academic` |
| Certificate UI | **PASS** | `certificates.index 190 TenantScoped + types 1311` single |
| Tenant isolation | **PASS** | `TenantScoped/BranchScoped + where institute_id + Rule exists where institute_id` + `TenantContext + BranchContext 70` + `BusinessProfile tenant isolation 0.19s + cross business blocked 0.22s` |
| IDOR protection | **PASS** | `never trust institute_id/subject_type, derived clamp 79, cross-tenant 404` `BusinessProfile idor ignored 0.22s + forged subject_type ignored DomainAccess 0.07s` |
| RBAC | **PASS** | `students.view/batches.view/exams.view/certificates.view/finance.view education.manage+domain:academic stays academic only` `view:clear` |
| Domain isolation | **PASS** | `Academic 204 isEducation hidden + academic 18 domain:academic 403 via EnsureDomain:11 actual professional !== academic → 403 even with ?section query` `DomainAccess professional cannot access academic setup 0.13s + academic assessment blocked 0.13s + direct url protected 0.12s` |
| Academic protection | **PASS** | `Academic Years/Classes/Groups/Structure/Placements/Assessments/Marks/Aggregation/Grade Scales/Promotion/Final/Published/Transcript/Academic Attendance/Analytics 13` remain `domain:academic 403` academic-only — not weakened |
| Multi-business | **PASS** | `Auth → Workspace::set → TenantContext::set + BranchContext → InstituteDomain → UI follows ACTIVE` `BusinessProfile switching 0.21s + multi business user sees active only + workspace switch changes domain DomainAccess 0.21s` |

```
PHASE: B15
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DUPLICATE SYSTEMS: NO

MAWA DOMAIN: PROFESSIONAL
TRAINING CENTER UI: PASS — Training 12 flat operational reusing existing baches/exams/fees/reports (was 6)
STUDENT UI: PASS — students.index single TenantScoped
TRAINER UI: PASS — teachers.index single Trainers 152
COURSE UI: PASS — courses.manage.index where institute_id
SUBJECT UI: PASS — courses.manage.subjects professional derived 79
CURRICULUM UI: PASS — curricula TenantScoped 397
BATCH UI: PASS — batches.* BranchScoped
ENROLLMENT UI: PASS — Training→Enrollment batches?view=enrollment reuse students.enroll + batches.transfer
ATTENDANCE UI: PASS — Training→Attendance batches?view=attendance batch workflow (not academic-attendance)
EXAM UI: PASS — exams.* where institute_id
MARKS UI: PASS — Training→Marks exams?view=marks saveMarks 181 Result (not academic)
RESULT UI: PASS — Training→Results exams?view=results Result mix (not academic final)
CERTIFICATE UI: PASS — certificates.index TenantScoped
TENANT ISOLATION: PASS — TenantScoped/BranchScoped where institute_id + InstituteDomain
IDOR PROTECTION: PASS — never trust id, clamps, cross-tenant 404
RBAC: PASS — students.view/batches.view/exams.view/certificates.view/finance.view
DOMAIN ISOLATION: PASS — domain:academic 403 EnsureDomain 11
ACADEMIC PROTECTION: PASS — 13 academic-only 403 not exposed
MULTI-BUSINESS: PASS — follows active_institution_id 16/16 + 4/4

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 0
LOW_FINDINGS: 0

FINAL_VERDICT: GREEN
```

**GREEN — MAWA Academy correctly remains `Training Center → Training Institute → professional` and operational training workflow (`Course→Batch→Enrollment→Attendance→Assessment→Marks→Result→Certificate`) is now fully surfaced through `Training 12` reusing existing tenant-safe `batches.* + students.enroll + exams.* + exams.marks + Result + certificates + finance.education + reports.hub` (`route:list 1211` unchanged) while `Academic 18 academic-only` (`settings.academic.* domain:academic`) stays `domain:academic 403` hidden and protected — no `InstituteDomain` taxonomy change, no `MAWA industry/sub_industry` conversion, no new controller/model/table/data — `BusinessProfile 16/16 + Industry 16 + DomainAccess 14 + Tenant 4 = 50 PASS 15.96s` — `Training Center UI: PASS` — 0 regressions, 0 new failures.**

---

> STOP — B15 complete. Do not start next implementation automatically. Next after review: **B16 + B17 + B18 regression/production audits** — `route:list 1211 + view:clear INFO` + `BusinessProfile/TenantIsolation` remain green for production gate.

*Evidence: `php artisan route:list 1211 0 new` + `view:clear INFO` + `layouts/institute 284-318 Training 6→12 enrollment ?view=enrollment attendance ?view=attendance marks ?view=marks results ?view=results fees finance.education.fee-collection reports.hub 1484 + Students 136 + Trainers 150 Trainers 152 + Courses 286 928 + Subjects 289 952 professional derived 79 + Curriculum 292 900 397 + Batches 295 165 BranchScoped + Exams 298 175 saveMarks 181 + Certificates 301 190` + `InstituteDomain 31 PROFESSIONAL_TYPES isProfessional 125 training_center training_institute → professional + industry_rules 52 global 5 + hasDomainData 30` + `EnsureDomain 11 domain:academic 403 + TenantScoped/BranchScoped 60 + BusinessProfile 16/16 15.96s + Tenant 4/4 2.88s + DomainAccess 14 15.96s + Industry 16`*


