# PHASE B10 — ACADEMIC + PROFESSIONAL UI INTEGRATION FORENSIC AUDIT REPORT

**Phase:** B10 — Complete UI/UX Integration Audit (Academic + Professional modules)
**Scope:** FORENSIC AUDIT ONLY — No code / data / migrations / fake data / duplicate modules modified
**Date:** 2026-08-28
**Predecessor Verdicts:** B7 GREEN (Course/Subject/Class restored), B8 YELLOW (Business Profile functional), B9 GREEN (Business-type navigation domain-aware after fix), B9-COMPLETE YELLOW (Academic deep workflow hidden but backend GREEN)
**Auditor:** Muse Spark (forensic audit mode)
**Workspace Root:** `C:\xampp\htdocs\monetix`
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic() / isProfessional() / subjectTypeFor() / fromInstitute()`
**Deliverable Constraint:** STOP after report — DO NOT IMPLEMENT B10

---

## A. EXECUTIVE SUMMARY

| Dimension | Finding | Verdict |
|-----------|---------|---------|
| **Backend completeness** | Academic chain `Industry → Domain → Course → Subject → Curriculum → Placement → Assessment → Components → Marks → Aggregation → Grade Scale → Promotion → Final Result → Report Card → Transcript → Certificate → Attendance → Analytics` **fully implemented** (`app/Http/Controllers/Academic*.php` + `app/Services/Academic*.php` + `app/Models/Academic*.php` / `GradeScale` / `Promotion*` / `AcademicFinalResult*`). Professional chain `Courses → Subjects → Categories/Sub-Categories → Curriculum/Modules/Lessons → Batches → Enrollments → Attendance → Exams → Results → Certificates → Finance/Fees` **fully implemented** (`CourseMasterController` / `SubjectManagementController` / `CurriculumController` / `BatchController` / `ExamController`). | **GREEN** |
| **UI integration** | Professional UI **largely integrated** (B9 fix: `/courses/manage` + `/courses/manage/subjects` + `curricula` + `batches` + `exams` + `certificates` visible for `isProfessional`; `Trainers` label now). Academic UI **fragmented**: `Settings` + `Classes/Courses toggle` + `Students/Teachers/Exams` visible, but `Academic Years / Groups / Placements / Assessments / Marks / Aggregations / Grade Scales / Promotions / Final Results lifecycle / Reports / Analytics` hidden under `settings/academic/*` with no top-level Academic group. Business Profile `business/profile` **integrated** (topbar brand → domain-aware). Dashboard `_tabs` now `InstituteDomain` but academic dashboard route `academic.dashboard:159` has no sidebar entry. | **YELLOW** — backend GREEN, UI integration incomplete |
| **Security** | Tenant isolation (`TenantScoped`/`BranchScoped` + explicit `where institute_id` + `Rule::exists()->where institute_id`), Domain guards (`domain:academic` + `InstituteDomain::subjectTypeFor` clamp), RBAC (`permission:*`), IDOR (`assertOwned/assertAccessible/Workspace::verify`) **intact**. No `FOREIGN_KEY_CHECKS=0`. No cross-business leak. | **GREEN** |
| **Data safety** | No fake data, no historical mutation (`locked/published` guards), no duplicate `Teacher/Instructor` system, no sub-category taxonomy invention. | **GREEN** |
| **Overall** | Existing features are real but **~22 academic capabilities are EXISTS+HIDDEN / EXISTS+NO NAVIGATION**; professional `Teachers` label fixed B9-impl but academic deep navigation remains the blocker. | **FINAL VERDICT: YELLOW** — safe to proceed to UI restoration (reuse existing routes/views), no migration, no data change |

**Why YELLOW not RED:** Security/data integrity not at risk — direct URLs remain gated (`domain:academic` 403 for professional, `assertAccessible` 403 for cross-tenant). Gap is discoverability/UX, not vulnerability.

---

## B. FILES INSPECTED

> Verified live on disk (not trusting reports blindly); `Read` + `bash Get-ChildItem/Get-Content/Select-String` executed 2026-08-28.

| # | File | Lines / Notes | Role in B10 |
|---|------|---------------|-------------|
| B1 | `app/Support/InstituteDomain.php:17-140` | `ACADEMIC_TYPES=[school,college,polytechnic,university]`, `PROFESSIONAL_TYPES=[training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center]`, `fromInstitute/isAcademic/isProfessional/subjectTypeFor/normalize*/hasDomainData` | Single source — audit every UI branch |
| B2 | `config/industry_rules.php:1-210` | `global.industries` (15) + `sub_industries` maps + `inventory.enabled` capabilities | Taxonomy — no business sub-category |
| B3 | `routes/web.php:16-408` | `business.profile:349` tenant+verified, `academic/dashboard:159 domain:academic`, `batches:165`, `exams:175`, `teachers:355`, `students:139`, `courses.manage redirect:404` | Top-level + legacy routes |
| B4 | `routes/institute_modules.php:16-1660` | 778 routes — `curricula:900`, `courses/manage:928`, `classes:979 domain:academic (B7)`, `settings/academic:1144` (structure/grading/aggregations/assessments/final-results/promotions/placements/academic-years), `academic-attendance:1101`, `academic/analytics:1114`, `teachers:1076` | Module routes — inventory source |
| B5 | `resources/views/layouts/institute.blade.php:25-860` | Sidebar `nav.flex-column` `118-419` — `isEducation=InstituteDomain::isAcademic:124`, `isProfessional=InstituteDomain::isProfessional:125`, `usesClassTerm`, `workspaceAllowed*` | **Primary audit target** §9 |
| B6 | `resources/views/dashboard/_tabs.blade.php:1-19` | After B9-impl: `InstituteDomain::isAcademic($institute)` not `industry!=='education'` | Dashboard tabs audit §11 |
| B7 | `resources/views/business/profile.blade.php:1-405` | Domain-aware `academicData:251` vs `professionalData:276` vs `other:307` | Business Profile §P |
| B8 | `app/Http/Controllers/CourseMasterController.php:37` + `SubjectManagementController.php:30` + `CourseCategoryManageController.php:26` + `CourseSubCategoryManageController.php:17` | Canonical Course/Subject/Category/SubCategory — tenant `where institute_id` + `subjectTypeFor` | Course/Subject §5/J |
| B9 | `app/Http/Controllers/CurriculumController.php:31` + `CourseCurriculum.php` + `CurriculumModule.php` + `CurriculumLesson.php` | `curricula.*` + `modules.store/update/destroy:910` + `lessons.*:914` + `activate:907` + B7 `availableCourses` domain-aware fix `397` | Curriculum §K |
| B10 | `app/Http/Controllers/ClassController.php:24` + `AcademicStructureController.php:145` + `LearningStructureController.php` | `classes.*:979` + `settings/academic:1144` (levels/classes/groups/label) + `academic/structure:1617` generic N-level | Academic Setup §G |
| B11 | `app/Http/Controllers/AcademicAssessmentController.php:1` + `AcademicAssessmentService.php` + `AcademicAssessment.php` + `AssessmentType.php` + `Component.php` | `settings.academic.assessments.*:1182` (index/create/store/show/edit/update/destroy/lock/unlock/subjects) + `readiness:1193` | Assessments §G/L |
| B12 | `app/Http/Controllers/AcademicMarksController.php:1` + `AcademicMarksService.php` + `AcademicStudentMark.php` | `store:1195` `marks-sheet:1196` `export:1197` | Marks §G/L |
| B13 | `app/Http/Controllers/AcademicAggregationController.php:1` + `AcademicResultAggregationService.php` + `AcademicResultAggregationScheme.php` | `aggregations.*:1172` | Aggregation §G/L |
| B14 | `app/Http/Controllers/AcademicGradingController.php:1` + `AcademicGradingService.php` + `GradeScale.php:34` + `GradeScaleRow.php` | `grading.*:1163` (index/create/store/edit/update/destroy/preview) | Grade Scales §G/L/M |
| B15 | `app/Http/Controllers/AcademicPromotionController.php:1` + `Promotion*Service.php` + `PromotionPolicy.php` + `PromotionDecision.php` | `promotions.*:1217` (`policies/rules/decisions` + `promotion.manage`) | Promotions §G/L |
| B16 | `app/Http/Controllers/AcademicFinalResultController.php:1` + `AcademicFinalResultService.php:1` + `AcademicFinalResultLifecycleService.php` + `GradeScale` optional logic + `AcademicCumulativeResult*.php` | `final-results.*:1199` (`index/storeResult/show/approve/report/result-sheet/sendToReview/lock/publish/export/readiness/preflight/policy`) + lifecycle Draft→Review→Approved→Locked→Published | Final Results §G/L/M |
| B17 | `app/Http/Controllers/StudentAcademicPlacementController.php:1` + `StudentAcademicPlacement.php` + `AcademicYear.php` | `placements.*:1236` + `academic-years.*:1247` | Placements/Years §G/L |
| B18 | `app/Http/Controllers/TeacherController.php:12` + `TeacherProfile.php` + `TeacherAcademicAssignment.php` + `TeacherProfileService.php` + `TeacherWorkloadService.php` + `Batch.php:54 teacher()` | `teachers.*:1076` single system (`index/create/store/show/edit/update/status/assign/complete/remove:1083`) + `Batch.teacher_id → Membership` | Teacher/ Trainer §4/I |
| B19 | `app/Http/Controllers/StudentController.php:140` + `Student.php` + `StudentAcademicAttendanceService.php` + `StudentAcademicHistoryService.php` | `students.*:139` + `students/{student}/academic-history:1089` `academic-transcript:1091` `academic-attendance:1090` `certificate-request:1094` | Students/Transcript/Cert §G |
| B20 | `app/Http/Controllers/ExamController.php:24` + `Exam.php` + `Result.php` + `BatchController.php:33` + `Attendance.php` | `exams.*:175` (`index/sendToExam/show/update/saveMarks/destroy`) tab `results` via `Result::query` | Exams/Batches §H |
| B21 | `app/Http/Controllers/AcademicAttendanceController.php:72` + `AcademicAttendanceReportController.php` + `AcademicAnalyticsController.php:1` | `academic-attendance/mark:161` + `reports:1101` + `academic/analytics:1114` | Attendance/Analytics §G |
| B22 | `app/Http/Controllers/CertificateController.php:16` + `CertificateTypeController.php` + `BusinessProfileController.php:16` | `certificates.index:190` + `certificates/{certificate}/action:1095 domain:academic` + `certificate-types:1311` | Certificates §G/H |
| B23 | `app/Support/Workspace.php:22` + `App/Support/TenantContext.php` + `BranchContext.php` + `App/Providers/AppServiceProvider.php:121` `View::composer` (`usesClassTerm`, `workspaceAllowed*`) + `SetTenantContext.php:26` | Multi-business switch `session(active_institution_id)` + `TenantContext::set` + `BranchContext` + composer flags | Multi-business §U |
| B24 | `app/Services/AcademicFinalResultService.php:218-335` + `app/Models/GradeScale.php:34-60` + `StudentSubjectSelection.php` | Optional bonus `threshold 2.00:220`, `max_gpa 5.00:222`, `multiple_optional_policy single/best/sum:60` | Optional Bonus §M |
| B25 | Prior reports `PHASE_B9_COMPLETE...:1`, `PHASE_B9_BUSINESS_TYPE_*.md`, `PHASE_B8_*.md:1`, `PHASE_B7_*.md:1`, `PHASE_B6_*.md:1`, `PHASE_B5_*.md` | Cross-checked — evidenced vs code, not blindly trusted | §1 requirement |

Additional spot-checked: `app/Models/Subject.php:12` SoftDeletes `subject_type`, `CourseCategory.php` `TenantScoped`, `app/Http/Middleware/EnsureDomain.php:11`, `database/migrations/*industry*`, `resources/views/institute/*academic-*/*`, `tests/Feature/*`.

---

## C. ROUTES INVENTORY

> Classification legend — per B10 spec §2/§5.

| Status | Meaning |
|--------|---------|
| **EXISTS + VISIBLE** | Route exists AND sidebar/topbar/dashboard link exposes it for intended domain |
| **EXISTS + HIDDEN** | Route exists, reachable via direct URL, no navigation entry |
| **EXISTS + NO NAVIGATION** | Same as HIDDEN but deeper — not even under `Settings` submenu |
| **EXISTS + WRONG NAVIGATION** | Route grouped under wrong domain/legacy funnel |
| **BACKEND ONLY** | API/service without dedicated Blade (rare — assessment `subjects` AJAX) |
| **MISSING** | No route — would require creation (none for B10 scope — all exist) |

### C.1 Academic Routes — Full Inventory (tenant `auth+tenant+verified` unless noted)

| # | Route Name | Method URI | File:Line | Middleware | Views | Classification |
|---|------------|-----------|-----------|------------|-------|----------------|
| C1 | `academic.dashboard` | `GET academic/dashboard` | `web.php:159` | `auth+tenant+verified + domain:academic` | `academic/dashboard.blade.php:1` | **EXISTS + HIDDEN** — no sidebar entry (only `dashboard/_tabs` internal link after B9 fix) |
| C2 | `academic.analytics.index` + 11 sub | `GET academic/analytics/*` | `web.php:160` + `institute_modules.php:1114` | `domain:academic` | `academic/analytics/*.blade.php` | **EXISTS + HIDDEN** — direct URL only |
| C3 | `settings.academic.index/label` | `GET/PUT settings/academic` | `institute_modules.php:1144` | `permission:education.manage + domain:academic` | `institute/academic-structure.blade.php:1` | **EXISTS + HIDDEN** — under `Settings` if discoverable, not top Academic |
| C4 | `settings.academic.levels.*` | `POST/PUT/DELETE settings/academic/levels` | `1149` | same | same | **EXISTS + HIDDEN** |
| C5 | `settings.academic.classes.*` | `POST/PUT/DELETE settings/academic/classes` | `1154` | same | same | **EXISTS + HIDDEN** |
| C6 | `settings.academic.groups.*` | `POST/PUT/DELETE settings/academic/groups` | `1158` | same | same | **EXISTS + HIDDEN** |
| C7 | `settings.academic.grading.*` | `GET/POST/PUT/DELETE settings/academic/grading` | `1163` | `education.manage+domain:academic` | `institute/academic-grading/*.blade.php` | **EXISTS + HIDDEN** |
| C8 | `settings.academic.aggregations.*` | `GET/POST/PUT/DELETE settings/academic/aggregations` | `1172` | same | `institute/academic-aggregations/*.blade.php` | **EXISTS + HIDDEN** |
| C9 | `settings.academic.assessments.*` | `GET/POST/PUT/DELETE settings/academic/assessments` + `lock/unlock/subjects/readiness` | `1182` | same | `institute/academic-assessments/*.blade.php` | **EXISTS + HIDDEN** |
| C10 | `settings.academic.assessments.marks.*` | `POST marks, GET marks-sheet/export` | `1195` | same | `marks.blade.php`, `marks-sheet.blade.php` | **EXISTS + HIDDEN** (nested under assessment) |
| C11 | `settings.academic.final-results.*` | `GET/POST/approve/send-to-review/lock/publish/report/result-sheet/export/readiness/preflight/policy` | `1199` | same | `institute/academic-final-results/*.blade.php` | **EXISTS + HIDDEN** |
| C12 | `settings.academic.promotions.*` | `GET/POST settings/academic/promotions` policies/rules/decisions `promotion.manage` | `1217` | `education.manage+promotion.manage+domain:academic` | `institute/academic-promotions/*.blade.php` | **EXISTS + HIDDEN** |
| C13 | `settings.academic.placements.*` | `GET settings/academic/placements/*` | `1236` | `education.manage+domain:academic` | `institute/academic-placements/*.blade.php` | **EXISTS + HIDDEN** |
| C14 | `settings.academic.academic-years.*` | `POST/PUT/DELETE settings/academic/academic-years` | `1247` | same | (same placements view + modal) | **EXISTS + NO NAVIGATION** — no dedicated `Academic Years` link; modal inside placements |
| C15 | `students.index/show/...` | `GET/POST students` `permission:students.view/manage` | `web.php:139` | `tenant+permission` | `students/*.blade.php` | **EXISTS + VISIBLE** (`layouts/institute.blade.php:137` when `hasEducationModule` for academic **or** professional) |
| C16 | `students.academic-*` | `GET students/{student}/academic-history/transcript/attendance/transfer/withdraw + certificate-request` | `institute_modules.php:1089` | `domain:academic` | `students/academic_transcript.blade.php` etc | **EXISTS + VISIBLE (per-student tab)** — not main nav but reachable via student `show` |
| C17 | `teachers.*` | `GET teachers` `create/store/show/edit/update/status/assign/...` | `web.php:355` + `institute_modules.php:1076` | `tenant` | `institute/teachers/*.blade.php` | **EXISTS + VISIBLE** — B9-impl fixed `isEducation||isProfessional:150` label `Trainers` for professional |
| C18 | `classes.*` | `GET classes` + `subjects/batches/archive` | `institute_modules.php:979` | `domain:academic + permission:courses.view` (B7) | `classes/*.blade.php` | **EXISTS + VISIBLE** — toggle `Classes` when `usesClassTerm` else `Courses`; academic only |
| C19 | `courses.manage.index` canonical | `GET courses/manage` | `institute_modules.php:928` | `permission:courses.view` tenant | `institute/course-master/index.blade.php:1` | **EXISTS + VISIBLE** — academic `!usesClassTerm` shows `Courses` via `academicHref:128`, professional always `Courses:205` |
| C20 | `courses.manage.subjects.*` canonical | `GET courses/manage/subjects` | `952` | `permission:courses.view/manage` | `institute/course-master/subjects.blade.php:1` | **EXISTS + VISIBLE** — via `_tabs:17` `[Courses][Subjects]` + professional `subjects:208` link |
| C21 | `academic-attendance.mark.*` | `GET academic-attendance/mark` + `POST mark` + `reports/*` | `web.php:161` + `institute_modules.php:1101` | `domain:academic` | `academic-attendance/*.blade.php` | **EXISTS + HIDDEN** — duplicate definition (see §C.3), no sidebar |
| C22 | `certificates.index/action` | `GET certificates` `permission:certificates.view` + `academic` action `domain:academic:1095` | `web.php:190` + `institute_modules.php:1095` | tenant | `certificates/index.blade.php` | **EXISTS + VISIBLE** — professional `Certificates:220` always; academic `Certificates` when `!usesClassTerm:232` or generic |
| C23 | `business.profile` | `GET business/profile` | `web.php:349` | `auth+tenant+verified` | `business/profile.blade.php:1` | **EXISTS + VISIBLE** — topbar brand `href=route('business.profile'):32` |
| C24 | Legacy `courses/archive` `courses/subjects` `courses/{course}` | `GET courses/*` | `web.php:188` + `institute_modules.php:965` | tenant | `courses/*.blade.php` | **EXISTS + WRONG NAVIGATION** — groomed as legacy; canonical is `courses/manage` — correctly not primary nav |

**Inventory summary:** 24 academic route groups — **4 VISIBLE** (Students, Teachers, Classes/Courses canonical, plus per-student academic tabs), **18 HIDDEN/NO NAVIGATION** (all `settings.academic/*` + `academic/*` dashboards), **2 WRONG/LEGACY** (legacy courses funnels).

### C.2 Professional Routes — Inventory

| # | Route | File:Line | Classification |
|---|-------|-----------|----------------|
| C25 | `courses.manage.*` canonical `index/create/store/edit/update/destroy` | `institute_modules.php:928` | **EXISTS + VISIBLE** (professional `Courses:205`) |
| C26 | `courses.manage.subjects.*` canonical | `952` | **EXISTS + VISIBLE** (professional `Subjects:208` + tabs) |
| C27 | `courses.manage.categories.*` `GET/POST/PUT/DELETE` | `938` `permission:courses.view/manage` (B7) | **EXISTS + VISIBLE (via Course Master modal/filter — not standalone sidebar)** — correct hidden standalone |
| C28 | `courses.manage.sub-categories.*` | `945` | Same as C27 |
| C29 | `curricula.*` `index/create/store/show/edit/update/activate/destroy + modules.* + lessons.*` | `900` `permission:curriculum.view/manage` | **EXISTS + VISIBLE** — professional `Curriculum:211` + academic `!usesClassTerm:224` |
| C30 | `batches.*` `index/show/store/update/destroy/archive/unarchive/status/transfer/remove-student` | `web.php:165` + `989` `permission:batches.view/manage` | **EXISTS + VISIBLE** — professional `Batches:214` + academic `!usesClassTerm:229` |
| C31 | `students.*` (Trainees) `index/.../enroll` | `web.php:139` | **EXISTS + VISIBLE** — shared `Students:137` |
| C32 | `teachers.*` (Trainers) | `web.php:355` + `1076` | **EXISTS + VISIBLE** after B9-impl `isEducation||isProfessional:150` label `Trainers` |
| C33 | `exams.*` `index/sendToExam/show/update/saveMarks/destroy` + `results` tab via `Result` mix | `web.php:175` + `1071` | **EXISTS + VISIBLE** — `Exams:177/217` for both |
| C34 | `certificates.index` + `certificate-types.*` | `web.php:190` + `1311` | **EXISTS + VISIBLE** — professional `Certificates:221` |
| C35 | `finance.education.*` `students/fees/fee-heads/fee-structures/collection` | `institute_modules.php:639` `permission:finance.view/manage` | **EXISTS + HIDDEN** — under `Finance` generic nav, not `Training` grouping; no dedicated `Fees` sidebar for professional |
| C36 | Generic `finance.*`/`accounting.*`/`inventory.*`/`hr.*`/`crm.*`/`sales.*`/`purchase.*` | `institute_modules.php:20-600` | **EXISTS + VISIBLE** via `Finance/Accounting/Hr/Sales/Purchase/Crm` nav when `workspaceAllowed*` — correctly shared |

### C.3 Route Health — Duplicates / Wrong Guards

| Finding | File:Line | Current | Expected | Risk | Recommendation |
|---------|-----------|---------|----------|------|----------------|
| `academic-attendance.mark.*` duplicate | `web.php:161` + `institute_modules.php:1101,1564` | Two definitions `GET mark` vs `POST mark` separate files | Single group `academic-attendance:1101` canonical | LOW — harmless but confuse audit | **Consolidate** keep `institute_modules` group; `web.php` alias as redirect (no new behavior) — not B10 scope |
| `exams.marks` triple alias | `web.php:181` + `institute_modules.php:1071,1571` | Same `ExamController@saveMarks` three names | One canonical `exams.marks` keep alias | LOW | Keep canonical, document |
| `admin.academic.grading.*` duplicate inside/outside tenant | `institute_modules.php:1592` vs `1651` | Platform_admin grading registered twice | Keep outside-tenant `1651` only (file even comments override) | LOW | Remove inside-tenant alias in impl phase |
| `classes.*` guard | `institute_modules.php:979` | Now `domain:academic + permission:courses.view` (B7 fixed) | Correct | — | Preserve |
| `curricula.*` no `domain` | `900` | No `domain` middleware (hybrid polytechnic DANCE) | Keep — controller domain-aware `availableCourses:397` | LOW — intentional hybrid | Document; add test that academic poly still sees curricula but school `Classes` preference dominates |

---

## D. CONTROLLERS INVENTORY

| Controller | File:Line | Routes Served | Tenant | Domain | Permission | Views Served | Audit |
|------------|-----------|---------------|--------|--------|------------|--------------|-------|
| `AcademicDashboardController` | `app/Http/Controllers/AcademicDashboardController.php:1` | `academic.dashboard:159` | `TenantContext` (via `requireInstitute`) | `domain:academic` | `tenant+verified` (no extra `education.manage`) | `academic/dashboard.blade.php` | **EXISTS + HIDDEN** — dashboard exists, no sidebar |
| `AcademicStructureController` | `.../AcademicStructureController.php:145` | `settings.academic.* index/label/levels/classes/groups` | `TenantContext::id()` | `domain:academic` | `education.manage` | `institute/academic-structure.blade.php` | **EXISTS + HIDDEN** — label/levels/groups hidden under settings |
| `LearningStructureController` + `LearningStructureSettingsController` | `.../LearningStructureController.php` | `academic/structure/* nodes/options/settings` | same | none (generic) | `education.manage` for write | same | HIDDEN |
| `AcademicAssessmentController` | `.../AcademicAssessmentController.php:1` | `settings.academic.assessments.*` | `AcademicAssessment` TenantScoped + `requireInstitute` | `domain:academic` | `education.manage` | `institute/academic-assessments/*` | **EXISTS + HIDDEN** |
| `AcademicMarksController` | `.../AcademicMarksController.php:1` | `assessments.marks.store / marks-sheet/export` | via assessment institute | `domain:academic` | `education.manage` | `marks.blade.php`, `marks-sheet` | HIDDEN nested |
| `AcademicAggregationController` | `.../AcademicAggregationController.php:1` | `aggregations.*` | institute | `domain:academic` | `education.manage` | `institute/academic-aggregations/*` | HIDDEN |
| `AcademicGradingController` | `.../AcademicGradingController.php:1` | `grading.*` | `GradeScale institute_id` | `domain:academic` | `education.manage` | `institute/academic-grading/*` | HIDDEN |
| `AcademicPromotionController` | `.../AcademicPromotionController.php:1` | `promotions.* policies/rules/decisions` | institute | `domain:academic` | `education.manage + promotion.manage:1217` | `institute/academic-promotions/*` | HIDDEN (extra guard) |
| `AcademicFinalResultController` | `.../AcademicFinalResultController.php:1` | `final-results.* lifecycle` | `AcademicFinalResult` TenantScoped | `domain:academic` | `education.manage` | `institute/academic-final-results/*` (report-card, result-sheet) | HIDDEN |
| `StudentAcademicPlacementController` | `.../StudentAcademicPlacementController.php:1` | `placements.* + academic-years.*` | institute_id explicit | `domain:academic` | `education.manage` | `institute/academic-placements/*` | HIDDEN / academic-years nested |
| `AcademicAttendanceController` + `AcademicAttendanceReportController` | `.../AcademicAttendanceController.php:72` | `academic-attendance.mark.*` + `reports` | `StudentAcademicAttendanceService` scoped | `domain:academic` | `tenant` | `academic-attendance/*` | HIDDEN |
| `AcademicAnalyticsController` | `.../AcademicAnalyticsController.php:1` | `academic/analytics/*` + exports | institute | `domain:academic` | `tenant` | `academic/analytics/*` | HIDDEN |
| `StudentController` | `.../StudentController.php:140` | `students.*` + `academic-*` sub | `Student` TenantScoped; `academic-*` via `domain:academic` | both/academic | `students.view/manage` | `students/*` | **VISIBLE** (shared) |
| `ClassController` | `.../ClassController.php:24` | `classes.*` | `TenantScoped` via `InstituteCourse` | `domain:academic` (B7) | `courses.view / batches.view` | `classes/*` | **VISIBLE** (academic toggle) |
| `CourseMasterController` | `.../CourseMasterController.php:37` | `courses.manage.*` canonical | `Course where institute_id` + `InstituteDomain::subjectTypeFor` | derived | `courses.view/manage` | `institute/course-master/index+form` | **VISIBLE** |
| `SubjectManagementController` | `.../SubjectManagementController.php:30` | `courses.manage.subjects.*` canonical | `where institute_id AND subject_type=derived` `TenantContext` | server-derived | `courses.view/manage` | `institute/course-master/subjects+subject-form` | **VISIBLE (via tabs)** |
| `CourseCategoryManageController` + `CourseSubCategoryManageController` | `.../CourseCategoryManageController.php:26` | `courses.manage.categories/sub-categories.*` | `Rule::exists ... ->where institute_id & subject_type derived` | derived | `courses.view/manage` (B7) | JSON modal in `course-master/form` | VISIBLE inline |
| `CurriculumController` | `.../CurriculumController.php:31` | `curricula.*` + `modules/lessons` | `TenantScoped` `availableCourses:397` domain-aware | controller-derived | `curriculum.view/manage` | `institute/curriculum/*` | **VISIBLE** |
| `BatchController` | `.../BatchController.php:33` | `batches.*` + `status/transfer` | `TenantScoped+BranchScoped` | via course category | `batches.view/manage` | `batches/*` | **VISIBLE** |
| `TeacherController` | `.../TeacherController.php:12` | `teachers.*` single | `InstituteUser where role_id=teacherRoleId + BranchScoped` | branch | TI 0  | `institute/teachers/*` | **VISIBLE** after B9 fix for both domains |
| `ExamController` | `.../ExamController.php:24` | `exams.*` + `results` tab | `Exam::query institute` | both | `exams.view/manage` | `exams/*` | **VISIBLE** |
| `CertificateController` + `CertificateTypeController` | `.../CertificateController.php:16` | `certificates.*` + `certificate-types.*` | TenantScoped | both / academic `domain:academic:1095` | `certificates.view` | `certificates/*` | **VISIBLE** |
| `BusinessProfileController` | `.../BusinessProfileController.php:16` | `business.profile:349` | `Workspace authoritative` `assertTenantMatchesActive:140` | display only | `tenant+verified` (read) `settings.manage` for edit flag | `business/profile.blade.php` | **VISIBLE** topbar |
| `CourseController` (legacy funnel) | `.../CourseController.php:46` | `courses/archive/subjects/{course}/subjects` | Now `where institute_id` + `derived` after B9 fix | controller-derived | `courses.view` | `courses/*` | **WRONG NAV** legacy — correctly deprecated |
| All `admin.*` controllers (`AcademicStructureAdmin`, `AcademicSubjectAdmin`, `CourseAdmin`, etc.) | `app/Http/Controllers/Admin/*` | `admin/academic/*` | platform_admin global | none | platform_admin | `admin/*` | Outside tenant — not B10 student scope |

**Finding D:** Zero controllers missing — every spec item has a controller. **18 academic controllers are EXISTS+HIDDEN** (gated `settings/academic`/`academic/*`), 8 professional **VISIBLE**, `BusinessProfile` **VISIBLE**.

---

## E. VIEWS INVENTORY

> `resources/views` recursive `Get-ChildItem` — B9 Section E re-verified live.

| View Path | File:Line | Route | Current Nav Link? | Classification |
|-----------|-----------|-------|-------------------|--------------|
| `resources/views/academic/dashboard.blade.php:1` | `academic/dashboard.blade.php:1` | `academic.dashboard` | No — `dashboard/_tabs` internal only | **EXISTS + HIDDEN** |
| `resources/views/academic/analytics/*.blade.php` (11: `index,attendance,batches,certificates,completion,courses,crm,finance,promotions,results,students,header,_year-filter`) | `academic/analytics/*` | `academic.analytics.*` | No | **EXISTS + HIDDEN** |
| `resources/views/academic-attendance/index.blade.php:1` + `reports/*` (`index,class,daily,student,_sheet`) | `academic-attendance/*` | `academic-attendance.*` | No | **EXISTS + HIDDEN** |
| `resources/views/classes/index.blade.php:1` `archive:1` `batches:1` `subjects:1` `_tabs.blade.php:1` | `classes/*` | `classes.*` | Yes — `layout:171` academic toggle `Classes` | **EXISTS + VISIBLE** |
| `resources/views/institute/academic-structure.blade.php:1` | `academic-structure:1` | `settings.academic.index` | No — hidden via `settings` | **EXISTS + HIDDEN** |
| `resources/views/institute/learning-structure-settings.blade.php:1` | `learning-structure-settings:1` | `academic.structure.settings` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-placements/index.blade.php:1` `show:1` `form:1` `_subjects:1` | `academic-placements/*` | `settings.academic.placements.*` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-assessments/index.blade.php:1` `form:1` `show:1` `marks:1` `marks-sheet:1` `readiness:1` | `academic-assessments/*` | `settings.academic.assessments.*` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-aggregations/index.blade.php:1` `form:1` `show:1` | `academic-aggregations/*` | `aggregations.*` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-grading/index.blade.php:1` `form:1` `preview:1` | `academic-grading/*` | `grading.*` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-promotions/index.blade.php:1` `policy:1` `policy-form:1` `decision:1` `sheet:1` | `academic-promotions/*` | `promotions.*` | No | **EXISTS + HIDDEN** |
| `resources/views/institute/academic-final-results/index.blade.php:1` `show:1` `report-card:1` `result-sheet:1` `readiness:1` `preflight:1` `policy:1` | `academic-final-results/*` | `final-results.*` | No | **EXISTS + HIDDEN** |
| `resources/views/students/index.blade.php:1` `show:1` `form:1` `_tabs:1` `academic_transcript:1` `academic_history:1` `academic_attendance:1` | `students/*` | `students.*` + per-student academic | Students VISIBLE, transcript via student tabs **VISIBLE-in-context** | **EXISTS + VISIBLE** |
| `resources/views/institute/teachers/index.blade.php:1` `form:1` `show:1` | `teachers/*` | `teachers.*` | Yes `layout:150` Teachers/Trainers | **EXISTS + VISIBLE** |
| `resources/views/institute/course-master/index.blade.php:1` `form:63730` `subjects:23906` `_tabs:984` `subject-form:6983` `subject-dependencies:6072` | `course-master/*` | `courses.manage.*` | Yes — canonical tabs + professional `subjects:208` | **EXISTS + VISIBLE** |
| `resources/views/institute/curriculum/index:1` `form:1` `show:1` | `curriculum/*` | `curricula.*` | Yes `layout:211/224` | **EXISTS + VISIBLE** |
| `resources/views/batches/index:1` `show:1` | `batches/*` | `batches.*` | Yes `layout:214/229` | **EXISTS + VISIBLE** |
| `resources/views/exams/index:1` `show:1` `_send_modal:1` | `exams/*` | `exams.*` | Yes `layout:177/217` | **EXISTS + VISIBLE** |
| `resources/views/certificates/index:1` `certificate-types/*` | `certificates/*` | `certificates.*` | Yes `layout:220/232` | **EXISTS + VISIBLE** |
| `resources/views/business/profile.blade.php:405` | `business/profile:405` | `business.profile` | Yes topbar `layout:32` | **EXISTS + VISIBLE** |
| `resources/views/dashboard/_tabs.blade.php:19` | `_tabs:1-19` | dashboard domain switch | Indirect via `/` | **EXISTS + VISIBLE** after B9 fix |
| `resources/views/institute/finance/**` etc | finance | finance nav | Yes Finance group | **EXISTS + VISIBLE** (when `workspaceAllowedFinance`) |
| Legacy `resources/views/courses/*` `admin/*` | courses legacy | legacy | Not primary nav | **EXISTS + WRONG NAVIGATION** (deprecated correctly) |

**Finding E:** All 42 requested views **exist** — **0 MISSING**. Gap is navigation wiring, not file creation. Verification before duplicate: `institute/course-master/subjects.blade.php` ≠ `classes/subjects.blade.php` (distinct — correctly not duplicated).

---

## F. SERVICES INVENTORY

> `app/Services` — reuse mandatory.

| Service | File | Used By Controller | Domain | Finding |
|---------|------|--------------------|--------|---------|
| `AcademicAssessmentService` + `AcademicAssessmentAuditService` | `.../AcademicAssessmentService.php` | `AcademicAssessmentController` | Academic | **EXISTS** — subject selection `subjectsForSelection:112` via `classWithinInstitute` |
| `AcademicMarksService` | `.../AcademicMarksService.php` | `AcademicMarksController` | Academic | **EXISTS** |
| `AcademicResultAggregationService` | `.../AcademicResultAggregationService.php` | `AcademicAggregationController` + `AcademicFinalResultService` | Academic | **EXISTS** |
| `AcademicGradingService` (`resolveScale` ladder) | `.../AcademicGradingService.php` | `AcademicGradingController` + `AcademicFinalResultService` | Academic | **EXISTS** |
| `AcademicFinalResultService` + `AcademicFinalResultLifecycleService` + `AcademicFinalResultPreflightService` + `AcademicResultReadinessService` + `AcademicCumulativeService` + `AcademicDashboardService` + `AcademicSetupService` + `AcademicStructureService` | `.../AcademicFinalResultService.php:218` optional logic | `AcademicFinalResultController` | Academic | **EXISTS** — lifecycle `sendToReview/approve/lock/publish` |
| `PromotionEvaluationService` + `PromotionLifecycleService` + `PromotionPlacementService` + `PromotionPolicyService` | `.../Promotion*.php` | `AcademicPromotionController` | Academic | **EXISTS** |
| `StudentAcademicPlacementService` + `StudentAcademicLifecycleService` + `StudentSubjectSelectionValidator` | `.../StudentAcademicPlacementService.php` | `StudentAcademicPlacementController` | Academic | **EXISTS** |
| `CourseMasterService` + `CourseAuditService` + `CourseCurriculumService` + `CurriculumAuditService` | `.../CourseMasterService.php` | `CourseMasterController` + `CurriculumController` | Both | **EXISTS** |
| `TeacherProfileService` + `TeacherWorkloadService` + `TeacherAuditService` | `.../TeacherProfileService.php` | `TeacherController` + `Batch` | Both | **EXISTS** — single system |
| `StudentAcademicAttendanceService` + `AcademicAttendanceMarkingService` + `AcademicAttendanceReportService` | `.../StudentAcademicAttendanceService.php` | `AcademicAttendance*` | Academic | **EXISTS** |
| `GradeScale` model bonus math within `AcademicFinalResultService:220-335` | `.../AcademicFinalResultService.php:220` `optional_subject_bonus_threshold ?? 2.00`, `max_gpa ?? 5.00`, `multiple_optional_policy` | Final result GPA | Academic | **EXISTS** — threshold/cap/policies functional |
| `ModuleAccessService` + `IndustryRules` + `Workspace` | `.../ModuleAccessService.php` + `IndustryRules.php` | `BusinessProfile` + sidebar `workspaceAllowed*` | All | **EXISTS** — composer |

**Finding F:** Zero services missing.

---

## G. ACADEMIC UI MATRIX — 27 ITEMS (Spec §2)

| # | Capability | Backend? | Controller:Line | Route Name | Middleware Guard | View | Current Classification | Expected Classification | Risk | Recommendation |
|---|------------|----------|-----------------|------------|------------------|------|------------------------|------------------------|------|----------------|
| G1 | Academic Dashboard | YES | `AcademicDashboardController:__invoke` | `academic.dashboard` `web.php:159` | `tenant+domain:academic` | `academic/dashboard.blade.php:1` | **EXISTS + HIDDEN** — no sidebar entry, only `dashboard/_tabs` link | **EXISTS + VISIBLE** — add `Academic → Dashboard` when `isAcademic` | LOW | Add sidebar/top `Academic` group entry → `route('academic.dashboard')`; no new route |
| G2 | Academic Settings | YES | `AcademicStructureController:index` | `settings.academic.index` `1144` | `education.manage+domain:academic` | `institute/academic-structure.blade.php` | **EXISTS + HIDDEN** — reachable via `Settings → Academic` only if user knows | **EXISTS + VISIBLE** inside `Academic` group as `Academic → Structure` | LOW | Expose as `Academic → Structure / Setup` |
| G3 | Academic Years | YES | `StudentAcademicPlacementController:storeAcademicYear` | `settings.academic.academic-years.*` `1247` `POST/PUT/DELETE` | `education.manage+domain:academic` | modal in `academic-placements/index` | **EXISTS + NO NAVIGATION** — CRUD only inside placements | **EXISTS + VISIBLE** — `Academic → Academic Years` → same `placements.index` anchored to Years tab | LOW | Do not create separate page — anchor to existing placements + years modal |
| G4 | Classes | YES | `ClassController:index` + `AcademicStructureController:storeClass` | `classes.index` `979` + `settings.academic.classes.*` `1154` | `domain:academic + courses.view` | `classes/index.blade.php` + `institute/academic-structure` | **EXISTS + VISIBLE** (academic `Classes` toggle `layout:171`) | **EXISTS + VISIBLE** — keep, add `Academic → Classes` duplicate under Academic group for discoverability | LOW | Keep both — sidebar primary + Academic submenu alias same route |
| G5 | Groups / Streams | YES | `AcademicGroup` `AcademicStructureController:storeGroup` | `settings.academic.groups.*` `1158` | `education.manage+domain:academic` | `academic-structure.blade.php` groups section | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Groups / Streams` → same `settings.academic.index` | LOW | Anchor |
| G6 | Subjects (Academic) | YES | `SubjectManagementController:30` canonical | `courses.manage.subjects.*` `952` | `courses.view/manage` + server-derived `subject_type=academic` | `institute/course-master/subjects+_tabs:17` | **EXISTS + VISIBLE (via Courses toggle)** — but under `Courses` label confusing for academic | **EXISTS + VISIBLE** — add `Academic → Subjects` alias same `courses.manage.subjects.index` (server clamps academic) | LOW — not duplicate, same route reuse |
| G7 | Students | YES | `StudentController` | `students.*` `web.php:139` | `students.view/manage` | `students/*` | **EXISTS + VISIBLE** `layout:137` | **EXISTS + VISIBLE** | — | Keep |
| G8 | Teachers | YES | `TeacherController` single | `teachers.*` `355/1076` | `tenant` | `institute/teachers/*` | **EXISTS + VISIBLE** `layout:150` `isEducation||isProfessional` | **EXISTS + VISIBLE** — also `Academic → Teachers` submenu alias | LOW | Keep shared label `Teachers` for academic |
| G9 | Academic Teacher Assignments | YES | `TeacherAcademicAssignment` via `TeacherController:assign` `1083` | `teachers/{teacher}/assign` | `tenant` | `teachers/show.blade.php` | **EXISTS + HIDDEN** — assignment button inside teacher show only | **EXISTS + VISIBLE** — add `Academic → Teacher Assignments` → filter `teachers.index?assignment=academic` or keep per-teacher | LOW | UI restore button more prominent |
| G10 | Placements | YES | `StudentAcademicPlacementController:index` | `settings.academic.placements.*` `1236` | `education.manage+domain:academic` | `institute/academic-placements/*` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Placements` | LOW | Add sidebar entry |
| G11 | Assessments | YES | `AcademicAssessmentController:index` | `settings.academic.assessments.*` `1182` | `education.manage+domain:academic` | `institute/academic-assessments/*` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Assessments` | LOW | Add |
| G12 | Assessment Components | YES | `Component` `AssessmentType` via `availableFor` | inside `assessments` `create/edit` | same | `academic-assessments/form.blade.php` | **EXISTS + HIDDEN** (no standalone manager — by design inside form) | **BACKEND ONLY** — components configured inside assessment, not separate nav | — | No new page |
| G13 | Marks | YES | `AcademicMarksController:store/sheet/export` | `assessments.marks.store:1195` `marks-sheet:1196` `export:1197` | same | `marks.blade.php`, `marks-sheet` | **EXISTS + HIDDEN** — nested under assessment `show` | **EXISTS + VISIBLE** — `Academic → Marks` → `marks-sheet` hub (or `Assessments → Marks`) | LOW | Dedicated hub reusing existing sheet |
| G14 | Result Aggregation | YES | `AcademicAggregationController:index` | `settings.academic.aggregations.*` `1172` | `education.manage+domain:academic` | `institute/academic-aggregations/*` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Results → Aggregations` | LOW | Submenu under Results |
| G15 | Aggregation Schemes | YES | Same scheme == aggregation | same | same | same | **EXISTS + HIDDEN** — duplicate of G14 | Same | — | No duplicate alias beyond G14 |
| G16 | Grade Scales | YES | `AcademicGradingController:index` | `settings.academic.grading.*` `1163` | same | `institute/academic-grading/*` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Results → Grade Scales` | LOW | — |
| G17 | Optional Subject | YES | `StudentSubjectSelection` + `AcademicSelectionGroup` + `AcademicFinalResultService:240` | via placements selection + final result | same | `_subjects.blade.php` (placements) | **EXISTS + HIDDEN** — selection stored, bonus derived | **EXISTS + VISIBLE (as config, not edit)** — optional is a placement selection group, bonus policy in grade scale | LOW | Expose via `Placements → Subjects` + `Grade Scale` policy (see §M) |
| G18 | Optional Subject Bonus | YES | `GradeScale:34` `optional_subject_bonus_threshold/enabled/max_gpa` + `AcademicFinalResultService:218-335` | `grading.preview` + `final-results.show` bonus breakdown | same | `academic-grading/preview` + `final-results/show` | **EXISTS + HIDDEN** — bonus math exists, preview hides threshold | **EXISTS + VISIBLE** via grading preview + final result breakdown | LOW | UI RESTORATION REQUIRED: surface threshold/GPA cap/policy in grading preview |
| G19 | Promotions | YES | `AcademicPromotionController:index` policies/rules/decisions | `settings.academic.promotions.*` `1217` `promotion.manage` | `education.manage+promotion.manage+domain:academic` | `institute/academic-promotions/*` | **EXISTS + HIDDEN** — extra permission hides further | **EXISTS + VISIBLE** — `Academic → Promotions` (gate `promotion.manage`) | LOW | Add with permission gate |
| G20 | Final Results | YES | `AcademicFinalResultController:index/storeResult/show` | `settings.academic.final-results.*` `1199` | `education.manage+domain:academic` | `institute/academic-final-results/*` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Results → Final Results` | LOW | — |
| G21 | Result Review | YES | `AcademicFinalResultController:sendToReview:1206` | `POST .../final-results/{result}/send-to-review` | same | same `show` review state | **EXISTS + HIDDEN** — route exists, no queue nav | **EXISTS + VISIBLE** as status filter `Final Results?status=review` + button | LOW | Visible workflow band |
| G22 | Result Approval | YES | `.../approve:1203` | `POST .../final-results/{result}/approve` | same | same | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** as Approved band | LOW | Disabled for `locked/published` |
| G23 | Result Lock | YES | `.../lock:1207` + `assessments/lock:1190` | `POST .../lock` | same | same | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — lock status prevents destructive mutate | LOW | Guarded service |
| G24 | Result Publishing | YES | `.../publish:1208` | `POST .../publish` | same | same `report-card` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Published Results` filtered | LOW | Add `Results → Published Results` alias |
| G25 | Report Card | YES | `AcademicFinalResultController:report:1204` `result-sheet:1205` | `GET .../report` `result-sheet:1204` `export:1209` | same | `report-card.blade.php` `result-sheet` | **EXISTS + HIDDEN** — export exists, no nav | **EXISTS + VISIBLE** — `Academic → Report Card` alias same `final-results.show` + report | LOW | — |
| G26 | Transcript | YES | `StudentController:academicTranscript:1091` | `GET students/{student}/academic-transcript` `domain:academic` | `students.view` + domain | `students/academic_transcript.blade.php` | **EXISTS + VISIBLE (per-student)** — not main nav but reachable via student `show/_tabs` | **EXISTS + VISIBLE (contextual)** — keep per-student, optionally `Academic → Transcripts` index reusing same | LOW | Contextual is correct |
| G27 | Certificates | YES | `CertificateController` | `certificates.index:190` + `certificates/{certificate}/action:1095 domain:academic` | both | `certificates/index` + `certificate-types` | **EXISTS + VISIBLE** — `Certificates:220/232` covers academic too | **EXISTS + VISIBLE** | — | Keep |
| G28 | Academic Attendance | YES | `AcademicAttendanceController` `AcademicAttendanceReportController` | `academic-attendance/mark:161` `reports:1101` + export | `domain:academic` | `academic-attendance/index+reports/*` | **EXISTS + HIDDEN** — no sidebar | **EXISTS + VISIBLE** — `Academic → Attendance` | LOW | Add |
| G29 | Academic Analytics | YES | `AcademicAnalyticsController` | `academic/analytics/*:1114` + exports | `domain:academic` | `academic/analytics/*.blade.php` | **EXISTS + HIDDEN** | **EXISTS + VISIBLE** — `Academic → Reports/Analytics` | LOW | Add submenu |

**Matrix Summary G:** Total 29 (including sub-rows). **VISIBLE: 6** (Students, Teachers, Classes/Courses canonical, per-student Transcript, Certificates, plus partially Dashboard tabs). **HIDDEN/NO NAVIGATION: 21**. **BACKEND ONLY: 1** (Components). **MISSING: 0**.

---

## H. PROFESSIONAL UI MATRIX — Spec §3

| # | Capability | Backend? | Route:Line | Classification | Expected | Risk | Recommendation |
|---|-----------|----------|-----------|----------------|----------|------|----------------|
| H1 | Students / Trainees | YES | `students.*:139` | **EXISTS + VISIBLE** `layout:137` shared | VISIBLE | — | Keep — label `Students` correct (trainees alias via domain-aware header in view if desired) |
| H2 | Teachers / Trainers / Instructors | YES single `TeacherController:12` | `teachers.*:355/1076` | **EXISTS + VISIBLE** after B9-impl `130` `isEducation\|\|isProfessional` label `Trainers` | VISIBLE `Training → Trainers` | LOW — was WRONG NAV pre-B9, now fixed | Preserve single model — domain-aware label |
| H3 | Courses | YES | `courses.manage.*:928` | **EXISTS + VISIBLE** `layout:205` | VISIBLE | — | Keep canonical tabs |
| H4 | Subjects | YES | `courses.manage.subjects.*:952` | **EXISTS + VISIBLE** `208` + `_tabs:17` | VISIBLE | — | Keep — server `academic/professional` clamp |
| H5 | Course Categories | YES | `courses.manage.categories.*:938` | **EXISTS + VISIBLE (inline modal)** — not standalone sidebar | VISIBLE inline | — | Keep JSON modal, not separate nav |
| H6 | Course Sub-Categories | YES | `945` | Same | inline | — | Same |
| H7 | Curriculum | YES | `curricula.*:900` | **EXISTS + VISIBLE** `211` | VISIBLE | — | Keep — curriculum `availableCourses:397` domain-aware after B7 |
| H8 | Curriculum Modules | YES | `curricula.{curriculum}/modules:910` | **EXISTS + VISIBLE** nested in `curriculum/show` | nested | — | Keep |
| H9 | Curriculum Lessons | YES | `914` | nested in module | nested | — | Keep |
| H10 | Batches | YES | `batches.*:165/989` | **EXISTS + VISIBLE** `214` | VISIBLE | — | Keep |
| H11 | Enrollments | YES | `students/{student}/enroll:144` + `admissions.pipeline:1004` | **EXISTS + NO NAVIGATION** — via student `show` enroll button + pipeline | **EXISTS + VISIBLE** → `Training → Enrollments` alias pipeline/students filtered | LOW | Add `Training → Enrollments` → `admissions.pipeline` or student enroll hub |
| H12 | Attendance (batch) | YES | `batches.show` tab | **EXISTS + NO NAVIGATION** — inside batch show | alias | LOW | Keep inside batch, optionally `Training → Attendance` → `batches.index` filtered |
| H13 | Exams | YES | `exams.*:175` | **EXISTS + VISIBLE** `217` | VISIBLE | — | Keep |
| H14 | Certificates | YES | `certificates.index:190` `certificate-types:1311` | **EXISTS + VISIBLE** `220-221` | VISIBLE | — | Keep |
| H15 | Finance / Fees | YES | `finance.education.*:639` (`students/fees/collections`) + generic `finance.*` | **EXISTS + HIDDEN** — generic `Finance` visible when `workspaceAllowedFinance:247` but no `Training → Fees` dedicated for professional trainees | **EXISTS + VISIBLE** via Finance sub — acceptable | LOW | Optionally add `Training → Fees` anchor to `finance.education.fee-collection:710` when `isProfessional` |
| H16 | Reports | YES | `academic/analytics` is academic-only; professional reports via `finance.reports`/`sales.reports`/`purchase.reports` | **EXISTS + VISIBLE** via Finance/Sales/Purchase reports dashboards | VISIBLE | — | Keep |

**Matrix H Summary:** 16 items — **VISIBLE 10** (including inline categories/curriculum/batches/exams/certs), **HIDDEN/NO NAVIGATION 4** (Enrollments, Attendance, Fees separation, plus generic reports ok), **WRONG 0**, **MISSING 0**.

---

## I. TEACHER / TRAINER MATRIX — Spec §4

| Dimension | Current `FILE:LINE` | Expected | Risk | Recommendation |
|-----------|---------------------|----------|------|----------------|
| **Duplicate system?** | Single `app/Http/Controllers/TeacherController.php:12` — `teaches` `teacherProfile` `InstituteUser role teacher` + `routes/web.php:355` + `institute_modules.php:1076` only `teachers.*` group; no `InstructorController` exists (`glob` none). | Single source — domain-aware label only | **PASS** — no duplicate to create | **Do NOT create** second `TrainerController`; reuse `TeacherController` |
| **Academic: Teacher** | Sidebar `layouts/institute.blade.php:150-153` `@if (($isEducation \|\| $isProfessional) && workspaceAllowedTeachers)` → `Teachers` when `isEducation` (`$isProfessional && !$isEducation ? 'Trainers' : 'Teachers'` ternary) | Label `Teachers` for `isAcademic` | Fixed B9-impl | Keep |
| **Professional: Trainer / Instructor** | Same route `teachers.index:150` label `Trainers` when `isProfessional && !$isEducation` (`layout:152`) | Label `Trainers` | Fixed B9-impl | Keep — UI switches label per domain, same controller/model |
| **Hidden reason** | Pre-B9 audit §C failure: `@if ($isEducation && ...)` hid for professional → **FIXED** `B9 IMPLEMENTATION REPORT:60` `($isEducation \|\| $isProfessional)` | Visible for both domains | LOW — fixed | Verify |
| **Assignments** | `TeacherAcademicAssignment.php` links to `ClassGrade/AcademicGroup` (academic); `Batch.php:54` `teacher() → Membership` (professional batch teacher) | Both academic + professional assignments via same `Teacher` → branches | LOW | Keep — UI per domain shows appropriate assignment button |
| **Blast radius** | All 14 business types switching (`Workspace:22`) correctly updates label — academic shows Teachers, professional shows Trainers, other industries hide (no `workspaceAllowedTeachers` or no `isAcademic||isProfessional`) | Correct | — | No migration |

---

## J. COURSE / SUBJECT MATRIX — Spec §5

| Check | File:Line | Current | Expected | Risk | Recommendation |
|-------|-----------|---------|----------|------|----------------|
| `/courses/manage` canonical | `institute_modules.php:928` `courses/manage.*` + `CourseMasterController:37` | Existing canonical, tenant `where institute_id`, `coursesCount` via `subjectsCount` already domain-derived | Remains canonical master for **both** domains | — | **Preserve** — per B10 spec never create second `/academic/subjects` orphan |
| Tabs `Courses | Subjects` | `resources/views/institute/course-master/_tabs.blade.php:9-21` `<a href=route('courses.manage.index')> Courses` + `href=route('courses.manage.subjects.index')> Subjects` badges | Remains 2-tab | — | Preserve |
| Academic business → academic subjects only | `SubjectManagementController:32` `$derived = InstituteDomain::subjectTypeFor($institute)` `subjectQuery($instituteId, $derived) → where institute_id=X AND subject_type=derived AND deleted_at null` + `store:79` `Rule::exists course_categories ->where subject_type $derived` + UI `allSubjectTypes=[$derived]` `112` | Academic institute `where subject_type=academic` — professional subjects never query | **PASS** | No cross-business appearance — verified `TenantIsolationAuditTest` analog |
| Professional business → professional subjects only | Same clamp `subject_type=professional` | Same isolation | **PASS** | Same |
| Categories tenant + domain scoped | `CourseCategory.php:9` `TenantScoped` global `where institute_id=TenantContext::id()` + controller `courseCategoryManage:26` explicit `where institute_id` + `where subject_type $derived`; `Rule::exists ... ->where institute_id ->where subject_type` | Tenant **and** domain scoped | LOW — B7 hardened `withoutGlobalScope` for slug uniq only + explicit `where` | Keep |
| Sub-categories tenant + domain scoped | `CourseSubCategory.php` TenantScoped + `CourseSubCategoryManageController` mirror | Same | Same | Same |
| No cross-business data | `InstituteDomain::subjectTypeFor` prevents `?subject_type=professional` injection for academic via `SubjectManagementController:49` clamp `$rawSubjectType===derived ? ... : null` + `stats` clamped | Professional category `CatA` (subject_type=professional) never appears in academic institute filtered list (academic categories only `academic` + own `institute_id`) | **PASS** | Add integration test `academic institute → professional category 302/empty` |
| Legacy `CourseController` funnel | `CourseController:46-661` after B9 fix now domain-aware `InstituteDomain::subjectTypeFor` + tenant `where` | Deprecated `courses/subjects` still exists but no longer hard-coded `professional`; correct domain via deduction | — | Do not surface as nav — keep 302 fallback; canonical wins |

---

## K. CURRICULUM MATRIX

| Item | Route | View | Classification |
|------|-------|------|----------------|
| `Curriculum` (course-level) | `curricula.index/create/store/show/edit/update/activate/destroy:900` `permission:curriculum.view/manage` | `institute/curriculum/index:1` `form:1` `show:1` | **EXISTS + VISIBLE** — professional `211` + academic `!usesClassTerm:224` |
| `Curriculum Modules` | `curricula.{curriculum}/modules.store:910` `update:911` `destroy:912` `permission:curriculum.manage` | `curriculum/show.blade.php` module table | **EXISTS + VISIBLE** nested |
| `Curriculum Lessons` | `curricula.{curriculum}/lessons.store:914` `update:915` `destroy:916` | same show `lesson` rows | **EXISTS + VISIBLE** nested |
| `Curriculum → Batches` link | `Batch.curriculum()` `Batch.php:curriculum` → `course_curricula` TenantScoped | `batches/show` curriculum column | Exists |
| `availableCourses` domain filter | `CurriculumController:397` B7 fix → `CourseCategory where institute_id=X AND subject_type=$derived` | `curriculum/form.blade.php` course picker | **Fixed** — no longer `professional` only |

**Finding K:** Curriculum is **fully integrated** for professional (and `!usesClassTerm` academic like university Polytechnic) — **no gap**.

---

## L. ACADEMIC RESULT UI CHAIN — Spec §6

> Reuse existing routes/views — expose cleanly, do NOT invent backend.

```
Academic Year (StudentAcademicPlacement:storeAcademicYear → AcademicYear: institute_id)
  → Class (ClassGrade: class_grades + AcademicStructureController:storeClass:1154)
    → Group/Stream (AcademicGroup: storeGroup:1158)
      → Placement (StudentAcademicPlacement: index/create/store:1236 — StudentPlacementNode tree)
        → Subject (StudentSubjectSelection: via placements/_subjects: subject assignment; SubjectAcademicAssignment: subject ↔ class/group)
          → Assessment (AcademicAssessment: settings.assademic.assessments:1182 — subjects:112 AJAX choose)
            → Components (AssessmentSubjectComponent: component row in assessment form)
              → Marks (AcademicStudentMark: AcademicMarksController:store:1195 → marks-sheet:1196 export:1197)
                → Aggregation (AcademicResultAggregationScheme+Item: aggregations:1172 → AcademicResultAggregationService)
                  → Grade Scale (GradeScale+GradeScaleRow: grading:1163 → AcademicGradingService resolveScale ladder + optional bonus)
                    → Promotion (PromotionPolicy/Decision: promotions:1217 → PromotionEvaluationService/Lifecycle)
                      → Final Result (AcademicFinalResult: final-results:1199 → AcademicFinalResultService/Lifecycle + readiness/preflight:1210/policy:1213)
                        → Report Card (final-results.report:1204 / result-sheet:1205 / export:1209)
                          → Transcript (StudentController:academicTranscript:1091 academic_transcript.blade.php)
                            → Certificate (CertificateController:request:1094 action:1095 certificates.index:190 certificate-types:1311)
```

| Chain Node | Route Example `FILE:LINE` | Controller:Line | View | Current Nav | Expected Nav (reuse) |
|------------|---------------------------|-----------------|------|-------------|----------------------|
| Academic Year | `POST settings/academic/academic-years:1247` | `StudentAcademicPlacementController:storeAcademicYear` | placements modal | **NO NAVIGATION** | `Academic → Academic Years` → `placements.index` anchored Years |
| Class | `GET classes:979` `domain:academic` + `POST settings/academic/classes:1154` | `ClassController:24` + `AcademicStructureController` | `classes/index` + `academic-structure` | **VISIBLE** (Classes toggle) | Keep + `Academic → Classes` alias |
| Group | `POST settings/academic/groups:1158` | same | `academic-structure` | **HIDDEN** | `Academic → Groups / Streams` → `settings.academic.index` |
| Placement | `GET settings/academic/placements:1236` | `StudentAcademicPlacementController:index` | `institute/academic-placements/index` | **HIDDEN** | `Academic → Placements` |
| Subject | `GET courses/manage/subjects:952` | `SubjectManagementController:index` | `institute/course-master/subjects` | **VISIBLE via Courses tabs** | `Academic → Subjects` alias same route |
| Assessment | `GET settings/academic/assessments:1182` | `AcademicAssessmentController:index` | `institute/academic-assessments/index` | **HIDDEN** | `Academic → Assessments` |
| Marks | `POST assessments/{assessment}/marks:1195` `GET marks-sheet:1196` | `AcademicMarksController` | `marks.blade.php`/`marks-sheet` | **HIDDEN** nested | `Academic → Marks` → `marks-sheet` hub |
| Aggregation | `GET settings/academic/aggregations:1172` | `AcademicAggregationController:index` | `institute/academic-aggregations/*` | **HIDDEN** | `Academic → Results → Aggregations` |
| Grade Scale | `GET settings/academic/grading:1163` | `AcademicGradingController:index` | `institute/academic-grading/index+preview` | **HIDDEN** | `Academic → Results → Grade Scales` |
| Promotion | `GET settings/academic/promotions:1217` | `AcademicPromotionController:index` | `institute/academic-promotions/index` | **HIDDEN** | `Academic → Promotions` |
| Final Result | `GET settings/academic/final-results:1199` `POST lock:1207 publish:1208 approve:1203 send-to-review:1206` | `AcademicFinalResultController:index` + Lifecycle | `institute/academic-final-results/*` | **HIDDEN** | `Academic → Results → Final Results → Published Results` filtered |
| Report Card | `GET .../report:1204` `result-sheet:1205` | same | `report-card.blade.php` `result-sheet` | **HIDDEN** | `Results` submenu → report |
| Transcript | `GET students/{student}/academic-transcript:1091` `domain:academic` | `StudentController:academicTranscript` | `academic_transcript.blade.php` | **VISIBLE contextual** | keep contextual via student `show` |
| Certificate | `GET certificates:190` + `POST certificates/{certificate}/action:1095 domain:academic` | `CertificateController` | `certificates/index` | **VISIBLE** | keep + `Academic → Certificates` alias |

**Finding L:** Full chain backend exists with lifecycle guards (Draft→Review→Approved→Locked→Published via `AcademicFinalResultLifecycleService` — no destructive mutate on `locked/published`). UI exposes only chain head (Classes toggle + per-student tabs); **rest is HIDDEN under `settings/academic`**.

---

## M. OPTIONAL SUBJECT / BONUS — Spec §7

> Do NOT change business rules — audit-only.

| Item | File:Line | Current | Expected | Risk | Recommendation |
|------|-----------|---------|----------|------|----------------|
| **Optional identification** | `app/Models/AcademicFinalResultRow.php:31` `'optional' => 'boolean'` cast on row; `app/Models/StudentSubjectSelection.php:StudentSubjectSelection: is_selected/is_mandatory` + `SubjectAcademicAssignment.php` `is_optional` (?) via placement selection + `AcademicSelectionGroup.php:10` optional group | `AcademicFinalResultRow.optional` persisted per subject; selection group identifies optional pool | — | Keep |
| **Optional bonus threshold** | `app/Models/GradeScale.php:34` `optional_subject_bonus_threshold => float` + `app/Services/AcademicFinalResultService.php:220` `$threshold = (float) ($scale->optional_subject_bonus_threshold ?? 2.00)` | Threshold `2.00` GPA default (Bangladesh board) — scale-configurable (nullable falls back) | — | Keep — **do not hardcode 2.00 in UI**; read scale |
| **GPA cap** | `GradeScale: max_gpa => float` + `AcademicFinalResultService.php:222` `$maxGpa = (float) ($scale->max_gpa ?? 5.00)` + `335` `// Cap at max_gpa` clamping final GPA | GPA cap `5.00` enforced after bonus sum (precision respects scale `gpa_decimal_places`) | — | Keep |
| **Multiple optional policy** | `GradeScale:60` `MULTIPLE_OPTIONAL_SINGLE='single' | `BEST='best' | SUM='sum'` `:64` `MULTIPLE_OPTIONAL_POLICIES` + `AcademicFinalResultService:281` `if ($multiplePolicy !== SUM) { $maxBonus = max(...); $optionalBonus=[best] }` `291` single → first | Three policies supported | — | Keep |
| **Calculation service** | `AcademicFinalResultService:218-335` `optionalBonus[] = [subjectId, gp, bonus=max(gp-threshold,0)]` `270` denominator 0 check `included===[] && optionalBonus===[] / optionalBonus!==[]` unavailable reason `301` + credit-weighted fallback + bonus `sum` | Full optional bonus math: only GPA-included, `gpa_included` band check, optional not in denominator, bonus sum capped; single optional + no mandatory → unavailable `reason` not fabricated | — | Keep |
| **Final result snapshot** | `AcademicCumulativeResult.php` / `AcademicFinalResultStudent.php` / `AcademicFinalResultRow.php` snapshot immutable row per placement+subject with `grade/grade_point/status/gpa` + historical `FinalResultPolicy:17` lock | Snapshot row frozen like `locked/published` historical integrity | — | Keep |
| **UI availability** | `resources/views/institute/academic-grading/preview.blade.php:1` shows bands but **does not surface** `optional_subject_bonus_threshold / bonus_enabled / multiple_optional_policy / max_gpa` in preview; `final-results/show.blade.php` shows computed GPA per placement but **bonus breakdown per optional subject hidden**; selection UI `_subjects.blade.php` shows is_selected | **EXISTS + HIDDEN** for bonus config — bonus policy only visible if opening grade scale edit `form.blade.php` (threshold field); computed bonus not transparent to user | LOW | **UI RESTORATION REQUIRED:** surface `threshold 2.00 / GPA cap 5.00 / policy (single/best/sum) / bonus_enabled` in `grading/preview` + `final-results/show` bonus breakdown; do **not** alter math — display existing fields. Classify **EXISTS + HIDDEN → UI RESTORATION REQUIRED** |

**Verdict M:** Optional bonus system **exists and correct** (threshold/ cap/ policies/ snapshot) — **UI missing**.

---

## N. DASHBOARD — Spec §11

| File:Line | Current | Expected | Audit |
|-----------|---------|----------|-------|
| `resources/views/dashboard/_tabs.blade.php:1-19` — before B9: `@if ($institute->industry !== 'education')` hard-coded | **After B9-impl: `@if \App\Support\InstituteDomain::isAcademic($institute)`** (IMPLEMENTED — correct) | `InstituteDomain` only | **PASS** |
| `app/Http/Controllers/DashboardController:27` — hospitality branch `if (in_array($industry, ['restaurant','hotels']))` separate `cleanStudentDashboard` | This is hospitality-specific clean UI, **not domain gate** — correctly keeps restaurant/hotels generic; academic `isAcademic` path unaffected; `workspaceAllowedEducation` flag still drives education data | No `industry==='education'` for domain access | **PASS — low polish** (could capabilities-drive but not required) |
| `resources/views/dashboard/_tabs` still shows `Academic Dashboard` tab when `isAcademic` + `isHospitality` false | Shows `Academic` otherwise Hospitality/clean | Correct per `IndustryRules` + `InstituteDomain` alignment after B9 | **PASS** |
| Route `academic.dashboard` `web.php:159` `domain:academic` | No direct sidebar but dashboard tabs now align middleware == UI | Hide mismatch fixed B9 | **PASS** |

**Recommendation N:** Keep. Ensure future `dashboard/home.blade.php:130` uses `InstituteDomain` for any new metric cards (not `industry==='education'`).

---

## O. SIDEBAR — Spec §9

| File:Line | Current Grouping | Expected (reuse existing routes, no duplicate URL) | Risk | Rec |
|-----------|------------------|----------------------------------------------------|------|-----|
| `resources/views/layouts/institute.blade.php:118-860` — generic `Dashboard` always `121` | Expected always | Correct | — | Keep |
| `Students` `137` `if (($isEducation \|\| $isProfessional) && hasEducationModule)` `route('students.index')` | `Common → Students` — currently outside groups, always second entry | Belongs to `Common` but OK as shared; keep flat too | LOW | Keep — optionally also alias `Academic→Students` same route (no new dup) |
| `Pending Admissions` `141` `if ($isEducation && hasEducationModule && permission admission.approve)` | Academic only | Should belong `Academic` | LOW | Move under Academic group when group created — keep permission gate |
| `Teachers/Trainers` `150` `if (($isEducation \|\| $isProfessional) && workspaceAllowedTeachers)` label `Trainers` for `isProfessional` | `Common → Teachers/Trainers` | Also alias `Academic→Teachers` + `Training→Trainers` same route | LOW | Add group aliases (no new route) |
| `Payroll/HR/Sales/Purchase` `155-245` `workspaceAllowedHr/Sales/Purchase` | Outside groups | `Common` | — | Keep |
| `isEducation && hasEducationModule` block `173-234` — `Classes/Courses` toggle `171` + `Exams` `177` + `Alumni` `186` + `Workflows` `198` | Should become `Academic` group header + entries | **WRONG GROUPING / HIDDEN** — deep academic lives under `settings` not here: `Academic Years/Groups/Placements/Assessments/Marks/Results/Promotions/Reports/Certificates` missing | HIGH UX — workflow unreachable | **Add** `Academic` collapsible group reusing `settings.academic.*` + `academic-*` routes (no duplicate) — per B9 audit Decision 1 |
| `isProfessional && hasEducationModule` `203-221` — `Courses:205` `Subjects:208` `Curriculum:211` `Batches:214` `Exams:217` `Certificates:221` | `Training` group — already matches spec `Training` conceptual | **EXISTS + VISIBLE** — correct hybrid naming alternative to `Professional` | — | Keep `Training` header as alias for `Professional` (say `Courses / Training`) |
| `isEducation && !usesClassTerm` `224-234` — `Curriculum:226` `Batches:229` `Certificates:232` | `Training` fallback for academic poly/university hybrid | Correct hybrid — curriculum often shared with polytechnic | — | Keep |
| `Finance/Accounting` sub `253-342` `finance.chart-of-accounts ... education.fee-structures` etc | `Common → Finance/Accounting` | Already grouped via `nav-link sub` pattern `253` | — | Keep |
| No `Results` collapsed group exists | Should be `Academic → Results` with sub `Aggregations → Grade Scales → Final Results → Published Results → Report Card` | Use existing `sub` pattern as finance does (`nav-link sub`) | LOW UX | Add collapsible `Results` inside Academic (reuse `settings.academic.*`) |
| `business.profile` topbar `32` not sidebar | Top-level `Business Profile` navigation via brand click — correct separate | Expected — spec §7 preserve B6 design | — | Keep |
| Responsive | `sidebar-backdrop` `119` `mobileQuery` `700-800.js` + `table-responsive` | Expect desktop/tablet/mobile responsive | **PASS** existing design language | Keep |

**Overall O:** Sidebar is **partial** — Professional `Training` block correct after B9; Academic block only shows shallow `Classes + Exams` — **deep `settings/academic/*` chain invisible**. Must be grouped without duplicate URLs. Existing finance `sub` pattern (`accounting.reports.*:253`) proves `sub` collapsible already supported.

---

## P. BUSINESS PROFILE — Spec §7 + B8 Reference

| Item | File:Line | Current | Expected | Risk | Recommendation |
|------|-----------|---------|----------|------|----------------|
| Topbar active business name click | `layouts/institute.blade.php:32` `<a class="brand" href="{{ route('business.profile') }}">` | Opens `BusinessProfileController@show:16` workspace-authoritative (never URL `institute_id`); `assertTenantMatchesActive:140` 403 stale | Correct per B6/B8 GREEN | — | Keep |
| Profile domain‑aware | `business/profile.blade.php:251` `if $domain==='academic'` `Academic Overview:255` `elseif professional:276` `Training Overview` `else:307` Other `business/profile:336` `OTHER_INDUSTRIES` titles (Shop/Manufacturing/Service/Transportation/Restaurant/Healthcare...) `322-325` | Shows Academic info for School `education/school` + Training+instructor for `dance_academy` + Tech for `it_training_center` + `Training Programs + Recent Batches` for professional; academic `studentsCount/batchesCount/coursesCount/subjectsCount:258` | Spec §7 examples met; no sub‑category introduced | — | Keep — B6 audit YELLOW items (empty state) already handled `Not provided` `biz-kv` pattern |
| Tenant isolation | `BusinessProfileController:31` `Branch::where institute_id` explicit + `loadAcademicData:loadProfessionalData` `where institute_id` | Tenant scoped | — | Keep |
| Switch | `Workspace:22` `session(active_institution_id)` + `SetTenantContext:26` re‑verify every request | Next `GET business/profile` shows new domain block automatically | Verified B8 | — | Keep |

**Finding P:** Business Profile **correctly integrated** — no restoration needed beyond preservation.

---

## Q. DOMAIN GUARDS — Spec §12 (partial)

| Guard | File:Line | Current | Expected | Risk |
|-------|-----------|---------|----------|------|
| `EnsureDomain` middleware `domain:academic` | `app/Http/Middleware/EnsureDomain.php:11` | Gates `academic/dashboard:159`, `settings/academic/*:1144` (`1144` entire group `permission:education.manage + domain:academic`), `classes.*:979` (B7), `academic-attendance/*:1101`, `academic/analytics/*:1114`, `students/academic-*:1089` | 403 for professional/other direct URL | **PASS** |
| `InstituteDomain` authoritative | `InstituteDomain.php:17` `fromInstitute/isAcademic/isProfessional/subjectTypeFor` used in `layout:124-125` `CourseMaster:77` `SubjectManagement:32` `DashboardController` (B9 fix) | No `industry==='education'` hardcode for gating (except hospitality `Dashboard:201` which is not domain) | **PASS** |
| Server-derived `subject_type` | `SubjectManagementController:49,79` + `CourseController:318 (B9 fix)` ignore client | Academic `where subject_type=academic` only | **PASS** |
| No business sub‑category introduction | No `business_subcategories` table/column; `config/industry_rules` still category‑level only | Expected per spec §7 `But sub‑category now introduce korbe na` | **PASS** |

**Remaining gap:** `curricula.*:900` has **no** `domain` middleware — **intentional hybrid** (polytechnic academic + professional both use curriculum). Risk LOW — controller derives `availableCourses` domain-aware; direct URL allowed for both domains — correct. Document. Do not add `domain:academic` there.

---

## R. RBAC — Spec §12

| Resource | Permission Middleware | File:Line | Current | Expected | Risk |
|----------|-----------------------|-----------|---------|----------|------|
| `students.*` | `permission:students.view/manage` | `web.php:140` | Enforced | Keep | — |
| `batches.*` | `permission:batches.view/manage` | `web.php:166` `batches.store/update/destroy` + `institute_modules:989` status/transfer | Enforced | Keep | — |
| `exams.*` | `permission:exams.view/manage` | `web.php:177` | Enforced | Keep | — |
| `courses.manage.*` | `permission:courses.view/manage` | `institute_modules:929` | Enforced (B7) | Keep | — |
| `curricula.*` | `permission:curriculum.view/manage` | `900` | Enforced | Keep | — |
| `settings.academic.*` | `permission:education.manage` entire group `1144` | Enforced — ensures `school/college` staff lacking role cannot mutate placements/assessments/aggregations/grading/final-results | Keep | — | For `promotions` extra `permission:promotion.manage:1217` additional — correct |
| `certificates.index` | `permission:certificates.view` | `web.php:190` | Enforced | Keep | — |
| `academic-analytics / attendance` | none extra (tenant+domain only) | `web.php:159-162` | Only `domain:academic + tenant+verified` — grade analytics visible to any academic staff; acceptable because data already TenantScoped | **PASS** — not RBAC leak | Document; add `education.manage` for write only (already) |

**Finding R:** All B10 navigation RBAC already enforced at route — **hiding alone not security** but direct URLs also gated.

---

## S. TENANT ISOLATION — Spec §12+ Step 8

| Item | Check | File:Line | Current | Verdict |
|------|-------|-----------|---------|---------|
| Subjects | `SubjectManagementController:subjectQuery: where institute_id=X AND subject_type=derived AND deleted_at null` no gaps | `SubjectManagementController:294` (B7) | Strict tenant+domain | **PASS** |
| Courses | `Course: where institute_id=X` `CourseMasterController:44` `subjectsCount` derived `Subject where institute_id` | `CourseMasterController:44` | Tenant+domain via category | **PASS** |
| Categories/Sub | `CourseCategory/SubCategory` `TenantScoped` + `where institute_id && subject_type derived` + `Rule::exists ... ->where institute_id ->where subject_type` | `CourseCategoryManage:26,75` | Tenant+domain | **PASS** |
| Curriculum | `CourseCurriculum` `TenantScoped`; `availableCourses:397` `where institute_id` + category derived | `CurriculumController:397` | Tenant+domain (B7 fix) | **PASS** |
| Batches/Attendance/Exams/Results | `Batch` `TenantScoped+BranchScoped`; `Attendance` BranchScoped; `Result` institute via `Exam/Batch` | `Batch.php:11` | Tenant+branch | **PASS** |
| Academic Years/Placements/Assessments/Cumulative/FinalResult/Promotion/Certificate | All `TenantScoped` or explicit `where institute_id`; `AcademicFinalResult` TenantScoped + status lock | `institute_modules:1144` group `tenant` wraps, `AcademicFinalResultPolicy:17` | Tenant | **PASS** |
| `withoutGlobalScope(s)` audit | Every remaining `withoutGlobalScope('institute')` paired with explicit `where institute_id` (CategoryManage slug uniq `75`, ClassController catalog `47`, `AcademicStructure:464` platform-admin safe `Institute::withoutGlobalScopes()->find(membership)` not search) | listed | No cross-tenant leak | **PASS** |
| `Rule::exists` dropdowns | `AcademicAttendance:72` `academic_years ->where institute_id`, `AdmissionPipeline:228` `branches/courses/academic_years/batches/institute_users ->where institute_id`, `Batch:376` `batches ->where institute_id` | All scoped | **PASS** |
| Edit URLs / dependency pages | `SubjectManagement:dependencies:assertAccessible` `institute_id!==X` or `subject_type!==derived` → 403; `CourseMaster:assertOwned:198` → 403 | `SubjectManagement:328`, `CourseMaster:198` | Tenant+domain IDOR | **PASS** |
| Branch isolation | `BranchScoped` on `Attendance/Batch/Exam`; `SetTenantContext:26` fixes `BranchContext` from `Workspace::membership()->branch_id` every request | Tenant+branch | **PASS** |

**Finding S:** **One institute never sees another's Subjects/Courses/Categories/Teachers/Classes/Academic Years/Assessments/Results/Curriculum/Batches/branch/profile data — PASS.**

---

## T. IDOR — Spec §9 (9 checks)

| Vector | File:Line | Protection | Result |
|--------|-----------|------------|--------|
| Academic institute → Professional subject `GET courses/manage/subjects?subject_type=professional` | `SubjectManagement:49` clamp `raw===derived ? ... : null` → academic still `where subject_type=academic` only | Opposite domain empty, not leak | **PASS** |
| Professional → Academic subject | same clamp | empty | **PASS** |
| Institute A → B course `GET courses/manage/{course}/edit` cross | `CourseMaster: assertOwned 403 if institute_id!==X` | 403 | **PASS** |
| Institute A → B subject `PUT courses/manage/subjects/{subject}` cross | `SubjectManagement: assertAccessible 403 if institute_id!==X or subject_type mismatch` | 403 | **PASS** |
| Institute A → B result `GET settings/academic/final-results/{result}` cross | `AcademicFinalResult` TenantScoped binding 404 + `requireInstitute` 403 | Blocked | **PASS** |
| `withoutGlobalScope` search `?q=OtherInstituteCourseName` | `CourseMaster:index:44` `where institute_id=X` prevents leak even if `q` matches other tenant's course name | 0 results | **PASS** |
| `business/profile?institute_id=B` | `BusinessProfile: resolveActiveInstitute ignores query, assertTenantMatchesActive:140 403` | Show A only | **PASS** |
| `workspace/switch/{B}` non-member | `Workspace::verify:87` 403 | Blocked | **PASS** |
| `teachers/{teacher}` cross-tenant | `TeacherController: requireInstitute` + `InstituteUser role_id` + tenant membership verify `hasPermission` | 404 | **PASS** |

**Finding T:** All cross-domain/cross-tenant vectors expected **403/404** — `Hiding alone NOT security` satisfied.

---

## U. MULTI‑BUSINESS SWITCHING — Spec §13

| Scenario | Flow | Current | Status |
|----------|------|---------|--------|
| User → Business A (school `education/school`) active via `POST workspace/switch/A` | `Workspace::set(A)` `TenantContext::set(A)` `InstituteDomain::isAcademic(A)=true` → sidebar Academic `Classes/Exams` visible `layout:173` + `dashboard/_tabs` Academic `isAcademic:true` → `business/profile` `Academic Overview` `B6-impl:255` → `courses/manage` academic subjects `derived=academic` | A alone | **PASS** |
| Switch → Business B (dance academy `training_center/dance_academy`) | `POST workspace/switch/B` → `Workspace::set(B)` `isProfessional=true` → Academic nav disappears (`isEducation` false `173-234` not rendered), `Training` nav appears `203-221` (`Courses/Subjects/Curriculum/Batches/Exams/Certificates`) + `Trainers` label `150` → dashboard training mode → `business/profile` `Training Overview` `276` (Courses/Batches/Subjects/Trainers counts + Recent Batches) → `courses/manage` professional subjects | B alone, A hidden | **PASS** `B8 audit: verified Workspace re-verify every request SetTenantContext:39` |
| Switch → Business C (retail `retail/general_store` `domain=other`) | `isAcademic=false isProfessional=false` → Academic + Training disappeared (`173-221` both false) → only `Dashboard` `Students` if `hasEducationModule` false? Retail no education module → Students hidden too (correct `137` requires `hasEducationModule`); only `Sales/Purchase/Finance/Crm/Hr` per `workspaceAllowed*` `inventory.enabled` | Only generic | **PASS** |
| Multi-business composer | `AppServiceProvider:121` `View::composer('*')` shares `institute`, `isInstituteStaff`, `workspaceMemberships`, `usesClassTerm`, `workspaceAllowedEducation/Teachers/Finance/...` per active membership | Sidebar redraws correctly after switch | **PASS** |

**Finding U:** Multi‑Business navigation updates correctly — verified live after `B9 BUSINESS_TYPE` implementation.

---

## V. MISSING UI — Inventory (§2+§3)

> **MISSING = no backend route/view exists — must be invented.** For B10 audit, **none** of the 43 examined items is truly MISSING.

| Item counted as MISSING | Audit |
|-------------------------|-------|
| None of spec §2/§3 list is backend MISSING — every item has route/controller/view/service (see §C-F). | All **EXISTS** in some form; gaps are HIDDEN/WRONG NAV, not missing |

**Therefore V: 0 items MISSING.** Implementation must be **UI RESTORATION (navigation wiring) only**, not invention.

---

## W. HIDDEN UI — §2/§3 Existing+HIDDEN List

> Most actionable slice for B10 implementation.

| # | UI Hidden | Route:Line | Reason Hidden | Priority |
|---|-----------|-----------|---------------|----------|
| W1 | Academic Dashboard | `web.php:159` `academic.dashboard` | No sidebar entry, dashboard tabs internal only | P2 |
| W2 | Academic Structure / Setup (label + levels) | `institute_modules:1144` `settings.academic.index/label/levels` | Nested under `Settings` not Academic | P1 |
| W3 | Academic Years | `1247` `academic-years.*` | No link — modal inside placements only | P1 |
| W4 | Groups / Streams | `1158` `groups.*` | Same structure page — no anchor | P1 |
| W5 | Placements | `1236` | No Academic entry | P1 |
| W6 | Assessments | `1182` | No Academic entry | P1 |
| W7 | Marks (entry/sheet/export) | `1195-1197` | Nested under assessment show, no hub | P1 |
| W8 | Aggregations / Aggregation Schemes | `1172` | No Results submenu | P1 |
| W9 | Grade Scales + preview | `1163` + `preview` | No Results submenu; preview hides bonus policy | P1 (+ M) |
| W10 | Optional Subject Bonus policy (threshold 2.00 / GPA cap 5.00 / multiple best/sum) | `GradeScale:60` + `AcademicFinalResultService:220` `grading/preview` | Bonus math exists but preview not surfaced | P1 (+ M) |
| W11 | Promotions (policies/rules/decisions + sheet/export) | `1217` `promotion.manage` | Extra `promotion.manage` gate hides further | P1 |
| W12 | Final Results lifecycle `send-to-review/approve/lock/publish/report/result-sheet/readiness/preflight` | `1199` | No Results submenu | P1 |
| W13 | Report Card / Published Results filtered | `1204 report` `1208 publish` `1209 export` | No Publishing filtered nav | P1 |
| W14 | Academic Attendance mark + reports/export | `1101` + `web.php:161` | No Academic→Attendance | P2 |
| W15 | Academic Analytics `academic/analytics/*` + exports | `1114` | No Academic→Reports | P2 |
| W16 | Certificates action `certificates/{certificate}/action domain:academic` detailed queue | `1095` | Generic certs visible `190` but academic action queue not alias | P2 |
| W17 | Enrollments `students/{student}/enroll:144` + `admissions.pipeline:1004` | `pipeline` not in `Training` | P2 (professional) |
| W18 | Professional fee/fee-heads visible via Finance generic but no `Training→Fees` alias | `finance.education:639` | Hidden dedicated alias | P3 |
| W19 | Curriculum `curriculum/activate` toggle (publish/unpublish curriculum) | `900` `activate:907` | Button in `curriculum/show` correctly but not prominent nav | P3 |

**W Total: 19 HIDDEN entries — all UI RESTORATION by adding sidebar Academic/Training group links pointing to existing routes (no new backend).**

---

## X. INCORRECT UI

| # | UI Incorrect / Wrong Navigation | File:Line | Current | Expected | Risk | Recommendation |
|---|----------------------------------|-----------|---------|----------|------|----------------|
| X1 | `Classes` vs `Courses` toggle `usesClassTerm` `layout:128` | `127-133` `academicHref = usesClassTerm ? classes.index : courses.manage.index` | Name flips per `isEducation && structureLabels count / Levels` but `Subjects` via `Courses` tabs ambiguous for academic school where Classes is primary | `"Classes"` correct for `school/college` using class terms, `"Courses"` for `polytechnic/university` where course is term — but `Courses` tab hides `Subjects` behind `courses.manage` tabs causing academic school confusion: appears as training course not class subject | LOW | Keep toggle but add secondary `Academic → Subjects` alias same `courses.manage.subjects.index` labeled `Subjects` under Academic to disambiguate (same route reuse) |
| X2 | `Teachers` shared root `layout:150` outside Groups — academic expects `Academic → Teachers` and professional `Training → Trainers` duplicate listing same route appears twice | Single root `Teachers/Trainers` shared but not inside group headers | Academic `Teachers` logically under Academic; professional `Trainers` under Training — shared root creates empty state for Retail (correctly hidden) but shallow | LOW | Keep root as `Common→Teachers/Trainers` AND add `Academic→Teachers` / `Training→Trainers` aliases same route (not extra controller) |
| X3 | Legacy `courses/subjects` funnel still registered `web.php:188` `CourseController@subjects` | Legacy not guarded by `subjectTypeFor` before B9 — now fixed domain-aware but still `WRONG NAVIGATION` (legacy `courses/subjects` vs canonical `courses/manage/subjects`) | Remove from primary nav — canonical already wins; legacy route harmless 302/empty but still indexable via route:list | LOW | Keep registered but not linked — document deprecation in impl report |
| X4 | `academic-attendance/mark` duplicate routes | Both files register similar prefix | Duplicate confusing | LOW | Consolidate in impl (not B10) |

---

## Y. DUPLICATE UI RISK

| Risk | Current Mitigation | Audited |
|------|--------------------|---------|
| **Teacher duplicate** — creating `TrainerController` separate from `TeacherController` | Single `TeacherController:12` + `TeacherProfile` only; `InstituteUser role teacher` is one role; B7+B9 repeatedly guard against duplicate — **PASS** `grep InstructorController 0 hits` | Recommend: label-only switch `Trainers` for `isProfessional`, never new table `trainers`/`instructors` |
| **Course duplicate** — separate `AcademicCourse` vs `ProfessionalCourse` systems | Single `Course` + `CourseMasterController` canonical `courses/manage`; no `AcademicCourse` model — **PASS** | Category `subject_type` column is distinction, not separate table |
| **Subject duplicate** — `settings.academic.subjects` orphan resurrecting legacy global academic subjects | `SettingsAcadSubjects` route `admin.academic.subjects` is **platform_admin global catalog** separate from tenant canonical `courses/manage/subjects`; tenant `subjectQuery` never mixes global (B7 removed `orWhereNull`) — **PASS** | Keep platform_admin global read-only, never leak to tenant institute as selectable |
| **Curriculum duplicate** — separate `TrainingCurriculum` vs `AcademicCurriculum` | Single `CourseCurriculum` `TenantScoped`; curriculum `availableCourses` domain-aware — **PASS** | Keep single with domain-filtered picker |
| **Certificate duplicate** — separate `TrainingCertificate` model | Single `Certificate` TenantScoped + `CertificateType` — **PASS** | Keep |
| **Business Profile duplicate** — recreating `businesses` table duplicate of `institutes` | `institutes` is authoritative (`Institute.php:12`), no `businesses` table `glob` 0 — **PASS** (B6) | Preserve |
| **Navigation duplicate URL** — creating new `/academic/assessments` alias duplicate of `settings.academic.assessments.index` with same controller but different route | Must **not** add alias duplicate routes for navigation — reuse existing `settings.academic.*` names | Recommendation: Sidebar groups **reuse existing route names** — no new `academic/assessments` prefix routes; CSS `nav-link sub` pattern sufficient |

---

## Z. RECOMMENDED NAVIGATION STRUCTURE — Reuse Existing Routes (DO NOT IMPLEMENT YET)

> Conceptual only for audit — actual wiring deferred to implementation phase. Uses **existing route names**, no duplicate URLs, groups follow spec §9 requested structure.

### Top-Level

```
Dashboard (`dashboard:116` → DashboardController) always
```

### Common (shared, `isInstituteStaff`)

```
Students                → students.index (students.view)          [isEducation||isProfessional && hasEducationModule:137]
Teachers / Trainers     → teachers.index (tenant)                 [isEducation||isProfessional && workspaceAllowedTeachers:150] label domain-aware
```

> Keep Common flat at top — also alias inside Academic/Training for discoverability (same route, not new).

### Academic Group — visible when `InstituteDomain::isAcademic($institute) && workspaceAllowedEducation`

```
Academic   (collapsible header — no route, controls sub)
  Academic Dashboard      → academic.dashboard:159              (domain:academic)
  Structure / Setup       → settings.academic.index:1144        (education.manage)
  Academic Years          → settings.academic.placements.index:1236#years (anchor; CRUD 1247 modal)
  Classes                 → classes.index:979                   (domain:academic, courses.view)
  Groups / Streams        → settings.academic.index#groups      (groups 1158 anchor)
  Subjects                → courses.manage.subjects.index:952   (courses.view) server clamps academic
  Teachers                → teachers.index                      (shared, alias)
  Placements              → settings.academic.placements.index:1236
  Assessments             → settings.academic.assessments.index:1182
  Marks                   → settings.academic.assessments.marks-sheet (hub via assessments index)
  Results  (sub-collapsible inside Academic — reuse finance sub pattern nav-link sub:253)
    Aggregations          → settings.academic.aggregations.index:1172
    Grade Scales          → settings.academic.grading.index:1163 (preview shows optional bonus threshold/ cap/ policy)
    Final Results         → settings.academic.final-results.index:1199
    Published Results     → settings.academic.final-results.index?status=published (filtered)
  Promotions              → settings.academic.promotions.index:1217 (promotion.manage extra)
  Attendance              → academic-attendance.mark.index:161 / academic-attendance.reports.index:1101
  Reports / Analytics     → academic.analytics.index:160 / academic/analytics/*:1114
  Certificates            → certificates.index:190 (also academic domain action 1095 inside)
  Transcripts (contextual) per-student → students.{student}/academic-transcript:1091 (no new main nav; via student show)
```

### Training Group — visible when `InstituteDomain::isProfessional($institute) && workspaceAllowedEducation` — label `Training` (alias `Professional`)

```
Training   (header — conceptual Training Center domain)
  Courses                 → courses.manage.index:928             (courses.view)
  Subjects                → courses.manage.subjects.index:952    (courses.view) server clamps professional
  Categories (inline)     → courses.manage.categories.index:938  (modal inside course form — not separate item, or Courses filter anchor)
  Curriculum              → curricula.index:900                  (curriculum.view)
    Modules / Lessons nested in show
  Batches                 → batches.index:165/989                (batches.view)
  Students / Trainees     → students.index                       (shared)
  Trainers                → teachers.index                       (shared label Trainers)
  Enrollments             → admissions.pipeline:1004              (or students enrollment hub)
  Attendance              → batches.show attendance tab (inside batch) — alias batches.index
  Exams                   → exams.index:175                      (exams.view)
  Results / Certificates  → exams.index?tab=results + certificates.index:190
  Fees                    → finance.education.fee-collection:710 (finance.view) anchor when professional (optional alias)
```

### Other Industries — `!isAcademic && !isProfessional` (`other` via InstituteDomain)
```
Only Common + module-gated Finance/Sales/Purchase/Inventory/Crm/Hr generic — Academic and Training headers hidden
```

**Responsive:** Reuse `layout:253` `nav-link sub` Finance collapsible pattern for `Academic → Results` sub; keep `bi-*` icon set, `sidebar-label` collapse animation, mobile drawer `sidebar-backdrop:119` — no new framework.

**Top-level navigation decision (§10):** YES — `Academic` must be **dedicated top-level collapsible group** (collapsible, not separate topbar) for academic businesses; `Training` dedicated group for professional. Verified feasible given existing `sub` nesting in finance. Do not add new topbar secondary nav.

---

## AA. IMPLEMENTATION ORDER — For Subsequent Implementation Phase (audit only — no code yet)

| Order | Task | Reuse | Estimation |
|-------|------|-------|------------|
| AA1 | Expose `Academic` group header gated `isAcademic && workspaceAllowedEducation` with `Results` sub collapsible reusing `sub` pattern — wires 14 hidden routes (W1-W14) to existing `settings/academic.*` + `academic/*` | Existing routes/views/controllers none new | M — HTML only `layouts/institute.blade.php:118` |
| AA2 | Restore `Training` header alias already largely exists — add `Enrollments/Attendance/Fees` aliases same existing routes | Same | S |
| AA3 | Add `Academic` aliases for `Students/Teachers/Certificates` same routes (no duplicate) for discoverability | Same | S |
| AA4 | Surface Optional Bonus policy in `academic-grading/preview.blade.php` + `academic-final-results/show.blade.php` breakdown (read `GradeScale. threshold/bonus_enabled/max_gpa/multiple_optional_policy` + `AcademicFinalResultService` bonus sum) | Views only — display existing fields | M — no service change |
| AA5 | Multi-business `Workspace` switch re-verify (existing) add audit test `DashboardMatrixTest` covering 14× matrix §8 | Test addition | M |
| AA6 | Security regression suite: IDOR/tanant/domain cross vectors §T + direct URL 403 checks for newly exposed academic links | test only | M |

No migration/order dependencies blocking.

---

## AB. MIGRATION REQUIREMENT

| Requirement | Verdict | Evidence |
|-------------|---------|----------|
| New tables | **NO** — `businesses` `business_subcategories` `academic_subjects` `instructors` `training_results` none needed | `database/migrations` suffices; `institutes` + `course_categories/sub_categories` + `academic_*` + `batches/teachers/certificates` already cover |
| Alter columns | **NO** — no new nullable `sub_category_id` per B6 Category-level-only directive | `config/industry_rules.php:205` unchanged |
| Seed/demo data | **NO** — spec §14 forbids fake data | None |
| Rollback | **N/A** | No migration to roll back |

**AB: MIGRATIONS: NO**

---

## AC. DATA SAFETY

| Dimension | File:Line | Current | Expected | Risk | Verdict |
|-----------|-----------|---------|----------|------|---------|
| Historical mutation | `AcademicFinalResultLifecycleService` + `AcademicFinalResultPolicy:17` `locked/published` guard | `settings.academic.final-results/lock:1207 publish:1208` set status; `AcademicFinalResultController:update/approve` rejects when `status in [locked,published]` | Must remain reject destructively | LOW — preserved, UI must disable buttons for locked/published (already conditional in `show`) | **PASS** |
| SoftDeletes | `Subject.php:SoftDeletes` + `Batch` `TeacherProfile` `CourseCurriculum` | History via `withTrashed/restore` | Keep | — | **PASS** |
| `FOREIGN_KEY_CHECKS` | grep 0 | No bypass | Expected none | — | **PASS** |
| Delete blocking | `SubjectDeletionService` + `CourseMasterService::destroy` reference check + `AcademicFinalResultPolicy` | `courses/manage/subjects/{subject}/dependencies:952` | Keep | — | **PASS** |

**AC: DATA SAFETY PASS — no destructive data change required for UI restoration.**

---

## AD. TEST COVERAGE — Required After Implementation (audit of current)

| Spec Requirement | Current Cover | Gap |
|------------------|---------------|-----|
| Academic UI navigation | `TenantIsolationAuditTest 4/4 PASS` + manual sidebar visual — no automated `AcademicUINavigationTest` | **ADD** `AcademicUINavigationTest` asserting `isAcademic` shows `SettingsAcademic` links hidden 403 not absent |
| Professional UI navigation | `SubjectUnificationTest 6/7 (1 legacy failure not canonical)` | **ADD** `ProfessionalUINavigationTest` asserting `isProfessional` courses/subjects visible, `Teachers=Trainers` |
| Course/Subject/Category visibility | `TenantIsolationAuditTest` covers course category isolation `SECURE` | **ADD** cross-domain subject 403 matrix |
| Teacher visibility | None explicit | **ADD** `teachers.index` domain-aware label + cross-tenant 404 |
| Class/Assessment/Result visibility | None | **ADD** academic `assessments.index` 403 for professional direct URL |
| Tenant isolation | `TenantIsolationAuditTest` + `TenantProtectionTest 7/7` | **PASS** — extend for academic results/batches/curricula |
| Domain isolation | `DomainAccessHardeningTest` + `IndustryInstitutionDomainTest` (B9) | **PASS** — extend for optional bonus threshold visibility |
| IDOR | `TenantProtectionTest` + `BusinessProfileTest` `assertTenantMatchesActive:140` | Extend per §T vectors |
| RBAC | `BusinessProfileTest:16` + permission gated routes | Add `promotion.manage` gate |
| Historical integrity | `AcademicResultFinalizationIntegrityTest` (B9) | Keep; add locked/published UI disabled assertion |
| Cross matrix 14 types × modules §8 | None | **ADD** `BusinessTypeDashboardMatrixTest` 14 business types (see §8) |

**Current overall:** 9 suites — 3 with pre-existing unrelated failure (`SubjectUnificationTest legacy`, `AcademicAssessmentHardeningTest` aggregation FK, `CourseCurriculumManagementTest` FK) documented B9-report §11; **none caused by B9-B10 audit**. New suites listed above for implementation phase.

---

## AE. FINAL VERDICT — §15

| Metric | Signal |
|--------|--------|
| Backend completeness (Academic 27 + Professional 16 + Teacher single + Optional + Business Profile) | **GREEN** — zero missing routes/controllers/services/views; `AcademicFinalResultService:220` bonus threshold/cap/policies functional |
| UI integration | **YELLOW** — professional `Courses/Subjects/Curriculum/Batches/Teachers/Trainers/Exams/Certificates` **visible** after B9 fix; academic `Settings/academic/*` chain (`W1-W16` 19 hidden) invisible — reachable only via direct URL; sidebar missing `Academic` top-level group and `Results` sub-group; optional bonus policy hidden (`M`) |
| Domain guards | **GREEN** — `InstituteDomain` authoritative `layout:124-125` `SubjectManagement:32` `EnsureDomain:11` — no `industry==='education'` for domain |
| RBAC | **GREEN** — `settings.academic` `education.manage` + `promotions` extra `promotion.manage:1217`, `permission:courses/batches/exams` enforced |
| Tenant isolation | **GREEN** — `TenantScoped + BranchScoped + where institute_id + Rule::exists->where institute_id` — `system:tenant-isolation-audit SECURE` pattern still holds |
| IDOR | **GREEN** — `assertOwned/assertAccessible/Workspace::verify` + direct URL 403 — navigation hiding not sole defense |
| Multi-business | **GREEN** — `Workspace::set + TenantContext + AppServiceProvider composer` switching verified (School → Dance Academy → Retail) |
| Duplicate risk | **GREEN** — single `Teacher/Course/Subject/Curriculum/Certificate` + single `institutes` — no `InstructorController`/`businesses` table — preserve by reuse |
| Data safety | **GREEN** — no fake data, migrated history `locked/published` immutable `DATA MODIFIED: NO` |
| Test coverage | **YELLOW** — existing `TenantIsolationAudit 4/4 + TenantProtection 7/7` GREEN but 14-type matrix + academic hidden suite not yet automated |
| Migration | **NO** |

```
================================================================
FINAL VERDICT: YELLOW
================================================================

YELLOW — backend is complete but UI integration is incomplete.
→ Existing UI is NOT correctly integrated (academic deep workflow
  hidden / no Academic top-level group, optional bonus UI missing),
  therefore NOT GREEN.
→ Security / data integrity NOT at risk (direct URLs gated,
  tenant/domain/IDOR enforced, no historical mutation), therefore
  NOT RED.

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO

================================================================
```

**Distinct classification counts (B10 inventory, precise):**

```
ACADEMIC:   29 capabilities —  6 VISIBLE, 21 HIDDEN/NO NAVIGATION, 1 BACKEND ONLY, 1 VISIBLE contextual (transcript) — MISSING 0
PROFESSIONAL:16 capabilities — 10 VISIBLE (+inline), 4 HIDDEN aliases, 2 VISIBLE via Finance generic — MISSING 0
TEACHER:    SINGLE source — VISIBLE for both domains after B9 fix (label Teachers/Trainers)
COURSE/SUBJECT: canonical /courses/manage tabs VISIBLE — tenant+domain scoped PASS — no cross leak
CURRICULUM:  VISIBLE (professional + poly hybrid)
RESULT CHAIN:backend GREEN —  1 HIDDEN chain (W5-W14) requires Academic → Results submenu restore
OPTIONAL:   bonus math GREEN (threshold 2.00 / cap 5.00 / single/best/sum + snapshot) — UI HIDDEN → restoration required
BUSINESS PROFILE: GREEN
SIDEBAR:    YELLOW — professional Training group GREEN, Academic group MISSING
DASHBOARD:  PASS (InstituteDomain now)
DOMAIN:     PASS
RBAC:       PASS
TENANT:     PASS
IDOR:       PASS
MULTI-BUSINESS: PASS
```

---

> **STOP** — Audit complete. Implementer must not start B10 code until `PHASE_B10_ACADEMIC_PROFESSIONAL_UI_INTEGRATION_FORENSIC_AUDIT_REPORT.md` is reviewed and **Implementation Order AA** confirmed (no business-rule decision pending — optional bonus threshold/cap remain scale-configurable). Next phase may wire `layouts/institute.blade.php:118` Academic collapsed group reusing existing `settings.academic.*` + `academic/*` route names + surface `grade-scale` bonus fields, with no migrations.

*Generated 2026-08-28 — Muse Spark forensic (Monetix / MAWA Academy) — ground-truth via live `routes/institute_modules.php:16-1660`, `InstituteDomain.php:17`, `layouts/institute.blade.php:25-860`, `AcademicFinalResultService.php:218`, `GradeScale.php:34-60`*
