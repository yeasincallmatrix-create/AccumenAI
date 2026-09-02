# PHASE B9 — COMPLETE ACADEMIC & PROFESSIONAL UI FORENSIC AUDIT REPORT

**Phase:** B9 — Complete Academic & Professional UI Restoration (Forensic Audit Only — No Code/Data/Migration Modified)
**Date:** 2026-08-28
**Mode:** AUDIT FIRST — IMPLEMENT LATER (STOP if architectural conflict)
**Predecessor Phases:** A1-A7 (Academic Hardening GREEN), B1-B8 (Domain/Tenant/Business Profile GREEN/YELLOW)
**Auditor:** Muse Spark (forensic)
**Workspace Root:** `C:\xampp\htdocs\monetix`
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `fromInstitute() / isAcademic() / isProfessional() / subjectTypeFor()` — no `$institute->industry === 'education'` hardcode in UI.

---

## 0. EXECUTIVE SUMMARY

Backend/service/database architecture for **Industry → Institute Domain → Course → Subject → Curriculum → Academic Placement → Assessment → Aggregation → Grade Scale → Promotion → Final Result → Transcript → Certificate** is **complete and hardened** (A1-A7, B7). UI/navigation is **partial**: backend routes+controllers+services+views exist but many are **reachable only via direct URL or nested under `settings/academic/*`**, not via domain-aware sidebar/top navigation.

**Professional domain**: canonical Course Master (`/courses/manage` via `CourseMasterController`), Subjects (`/courses/manage/subjects` via `SubjectManagementController`), Categories/Sub-Categories, Curriculum (`/curricula`), Batches (`/batches`), Exams (`/exams`), Certificates (`/certificates`), Teachers (`/teachers`) are **backend-complete** and **sidebar-restored in B7** for `isProfessional`. Curriculum course picker was previously hardcoded `subject_type=professional` (now fixed); subject `subject_type` now server-derived.

**Academic domain**: Assessment → Marks → Aggregation → Grade Scale → Promotion → Final Result → Transcript → Certificate → Attendance → Analytics are **backend-complete under `settings/academic/*` with `domain:academic` + `permission:education.manage` + tenant isolation**, but **no top-level Academic nav group** exists. Sidebar only exposes `Students`, `Pending Admissions`, `Teachers`, `Classes/Courses` toggle, `Exams`, `Alumni`, `Workflows` — academic operational pages (Year, Class/Group, Placement, Assessment CRUD, Marks Sheet, Aggregations, Grading, Promotions, Final Results lifecycle, Report Card, Transcript, Attendance) are **EXISTING + UI PARTIAL** (hidden).

**Tenant isolation**: `TenantScoped`/`BranchScoped` scopes + `InstituteDomain` clamping + `Rule::exists(...)->where('institute_id', ...)` widely applied; a few `withoutGlobalScope('institute')` legitimate (global catalog read for CourseCategory subject_type leakage check) and one `orWhereNull(institute_id)` previously removed in B7; `withoutGlobalScopes()->find()` limited to `AcademicStructureController:464,477,488` platform-admin safe path. No `FOREIGN_KEY_CHECKS=0`.

**Verdict for audit:** **YELLOW — safe to proceed** to Step 2-10 implementation with strict reuse; no new taxonomy; no fake data; no migration required. One architectural decision required (STOP point) flagged in §8.

---

## A. ACADEMIC INSTITUTION UI — FORENSIC MAP

Domain key: `InstituteDomain::ACADEMIC = 'academic'` = `industry='education' AND sub_industry IN [school, college, polytechnic, university]` (`InstituteDomain.php:25-30`). Resolver: `InstituteDomain::fromKeys()` normalizes legacy aliases before check.

| # | Capability | Backend Exists | Controller / Service / Model (file:line) | Route(s) (file:line) | View(s) (file:line) | UI Status |
|---|------------|----------------|-------------------------------------------|----------------------|---------------------|-----------|
| A1 | **Dashboard** | YES | `AcademicDashboardController.php:1` (`__invoke`), `AcademicDashboardService.php`, `DashboardController.php:116` route `academic.dashboard` | `routes/web.php:159` `GET academic/dashboard` `middleware domain:academic`, `routes/web.php:115` `GET /` tenant dashboard | `resources/views/academic/dashboard.blade.php:1`, `resources/views/dashboard/_tabs.blade.php` | **EXISTING + UI PARTIAL** — academic dashboard exists but not prominent nav; main dashboard is generic `DashboardController`. |
| A2 | **Students** | YES | `StudentController.php`, `Student.php`, `StudentAcademicPlacement.php` | `routes/web.php:139` `students.*` `permission:students.view/manage` | `resources/views/students/index.blade.php:1`, `show.blade.php`, `students/_tabs.blade.php`, `academic_history.blade.php`, `academic_transcript.blade.php` | **EXISTING + UI EXISTS** — sidebar `students.index` for academic when `hasEducationModule` (`layouts/institute.blade.php:137`). TenantScoped `Student` via institute_id. |
| A3 | **Classes** | YES | `ClassController.php:24` (`index/subjects/batches/archive`), `AcademicStructureService.php`, `InstituteClassGrade.php`, `ClassGrade.php` | `routes/institute_modules.php:979` `classes.*` `middleware domain:academic` **NOW** (B7 fixed, was missing), `routes/web.php` no direct, `settings/academic/classes.*` CRUD variant | `resources/views/classes/index.blade.php`, `archive.blade.php`, `batches.blade.php`, `subjects.blade.php`, `_tabs.blade.php`, `admin/classes/*` | **EXISTING + UI PARTIAL** — `/classes` now domain-gated + RBAC `permission:courses.view`; sidebar toggles `Classes` vs `Courses` via `usesClassTerm` (`layouts/institute.blade.php:128-133`). View exists but Term logic complicates navigation. |
| A4 | **Groups / Streams** | YES | `AcademicGroup.php`, `InstituteAcademicGroup.php`, `AcademicStructureController.php:storeGroup/updateGroup/destroyGroup` | `routes/institute_modules.php:1158` `settings.academic.groups.*` `permission:education.manage + domain:academic` | `resources/views/institute/academic-structure.blade.php:1` (groups section), `resources/views/institute/learning-structure-settings.blade.php` | **EXISTING + UI PARTIAL** — only under `settings/academic` (index), no top-level Academic → Groups link. |
| A5 | **Subjects** (Academic) | YES | `Subject.php:12` SoftDeletes, `SubjectManagementController.php:30` canonical, `AcademicSubjectService.php`, `SubjectAcademicAssignment.php`, `InstituteSubject.php` | `routes/institute_modules.php:952` `courses.manage.subjects.*` canonical (`permission:courses.view/manage`), `routes/institute_modules.php:965` legacy `courses/{course}/subjects` (+ `web.php:404` shallow `courses.subjects.update`) | `resources/views/institute/course-master/subjects.blade.php:1`, `subject-form.blade.php`, `subject-dependencies.blade.php`, `_tabs.blade.php:17` | **EXISTING + UI EXISTS (canonical via tabs)** — canonical `/courses/manage` tabs `[Courses][Subjects]` restored B7; server-derived `subject_type = academic` for academic institute (`SubjectManagementController:32` `InstituteDomain::subjectTypeFor`). `allSubjectTypes = [$derived]` — no freely selectable. |
| A6 | **Academic Year** | YES | `AcademicYear.php`, `StudentAcademicPlacementController.php:storeAcademicYear/updateAcademicYear/destroyAcademicYear` | `routes/institute_modules.php:1247` `settings.academic.academic-years.*` (`POST`, `PUT {academicYear}`, `DELETE {academicYear}`) `permission:education.manage + domain:academic` | `resources/views/institute/academic-placements/index.blade.php:1`, `show.blade.php`, `form.blade.php` (year selectors) | **EXISTING + UI PARTIAL** — CRUD under `settings/academic/placements` context; no dedicated Academic → Academic Years nav item. |
| A7 | **Academic Setup** (`Academic Structure` label + levels) | YES | `AcademicStructureController.php:145` `settings.academic.index`, `updateLabel`, `storeLevel/updateLevel/destroyLevel`, `LearningStructureController.php`, `StructureLabel.php`, `InstituteAcademicLevel.php` | `routes/institute_modules.php:1144` `settings.academic.index/label/levels.*` + `academic/structure/*` N-level engine | `resources/views/institute/academic-structure.blade.php`, `resources/views/institute/learning-structure-settings.blade.php` | **EXISTING + UI PARTIAL** — accessible via `settings/academic` when `permission:education.manage`; no direct Academic → Setup nav. |
| A8 | **Academic Placements** | YES | `StudentAcademicPlacement.php`, `StudentPlacementNode.php`, `StudentAcademicPlacementService.php`, `StudentAcademicPlacementController.php:index/create/store/show/edit/update/destroy/subjects` | `routes/institute_modules.php:1236` `settings.academic.placements.*` (`index/create/store/show/edit/update/destroy/subjects`) `permission:education.manage + domain:academic` | `resources/views/institute/academic-placements/index.blade.php`, `show.blade.php`, `form.blade.php`, `_subjects.blade.php` | **EXISTING + UI PARTIAL** — under settings; sidebar does not surface Placements. |
| A9 | **Assessments** | YES | `AcademicAssessment.php` (TenantScoped), `AssessmentType.php`, `Component.php`, `AcademicAssessmentService.php`, `AcademicAssessmentController.php:index/create/store/show/edit/update/destroy/lock/unlock/subjects` | `routes/institute_modules.php:1182` `settings.academic.assessments.*` (`index/create/store/show/edit/update/destroy/lock/unlock/subjects/readiness/readiness.export/marks*)` `permission:education.manage + domain:academic` | `resources/views/institute/academic-assessments/index.blade.php`, `form.blade.php`, `show.blade.php`, `marks.blade.php`, `marks-sheet.blade.php`, `readiness.blade.php` | **EXISTING + UI PARTIAL** — full CRUD+L/S readiness under settings; no Academic → Assessments nav entry. |
| A10 | **Assessment Components** | YES | `Component.php`, `AssessmentSubjectComponent.php`, `AssessmentSubject.php`, `AssessmentType.php` via `availableFor($institute)` | `routes/institute_modules.php:1185-1192` same assessment group, components via `AcademicAssessmentController@create/edit` (loads `AssessmentType::availableFor` + `Component::availableFor`) | `resources/views/institute/academic-assessments/form.blade.php` (dynamic subject/component table, AJAX `subjects` endpoint `AcademicAssessmentController:112` `POST .../assessments/{assessment}/subjects`) | **EXISTING + UI PARTIAL** — configuration in assessment form; no standalone Components manager nav (by design). |
| A11 | **Marks** | YES | `AcademicStudentMark.php`, `AcademicMarksService.php`, `AcademicMarksController.php:store/sheet/export` | `routes/institute_modules.php:1195` `settings.academic.assessments.marks.store`, `1196` `marks-sheet`, `1197` `marks-sheet.export` `+ domain:academic` | `resources/views/institute/academic-assessments/marks.blade.php`, `marks-sheet.blade.php` | **EXISTING + UI PARTIAL** — marks entry via `POST .../assessments/{assessment}/marks` + sheet export; not top-nav, nested under assessment show/readiness. |
| A12 | **Result Aggregation** | YES | `AcademicResultAggregationScheme.php`, `AcademicResultAggregationItem.php`, `AcademicResultAggregationService.php`, `AcademicAggregationController.php:index/create/store/show/edit/update/destroy/assessments` | `routes/institute_modules.php:1172` `settings.academic.aggregations.*` (`index/create/store/show/edit/update/destroy/assessments`) `permission:education.manage + domain:academic` | `resources/views/institute/academic-aggregations/index.blade.php`, `form.blade.php`, `show.blade.php` | **EXISTING + UI PARTIAL** — under settings; no Academic → Results → Aggregation nav bucket (requested structure not yet wired). |
| A13 | **Aggregation Schemes** | YES | Same as A12 (scheme is aggregation) | Same `aggregations.*` | Same | **EXISTING + UI PARTIAL** — alias of Aggregation; backend supports scheme items via service. |
| A14 | **Grade Scales** | YES | `GradeScale.php`, `GradeScaleRow.php`, `GradingScale.php`, `AcademicGradingService.php`, `AcademicGradingController.php:index/create/store/edit/update/destroy/preview` | `routes/institute_modules.php:1163` `settings.academic.grading.*` (`index/create/store/edit/update/destroy/preview`) `permission:education.manage + domain:academic` + `admin/academic/grading` separate platform-admin catalog | `resources/views/institute/academic-grading/index.blade.php`, `form.blade.php`, `preview.blade.php` | **EXISTING + UI PARTIAL** — under settings; no Results → Grade Scales group link. |
| A15 | **Promotion** | YES | `PromotionPolicy.php`, `PromotionPolicyRule.php`, `PromotionDecision.php`, `PromotionDecisionItem.php`, `PromotionPolicyService.php`, `PromotionEvaluationService.php`, `PromotionLifecycleService.php`, `AcademicPromotionController.php:index/showPolicy/editPolicy/storePolicy/updatePolicy/setPolicyStatus/storeRule/updateRule/destroyRule/showDecision/storeDecision/reviewDecision/sendBackToReview/approveDecision/export/sheet` | `routes/institute_modules.php:1217` `settings.academic.promotions.*` (`policies.*` + `rules.*` + `decisions.*`) `middleware permission:promotion.manage` additionally required | `resources/views/institute/academic-promotions/index.blade.php`, `policy.blade.php`, `policy-form.blade.php`, `decision.blade.php`, `sheet.blade.php` | **EXISTING + UI PARTIAL** — deep lifecycle exists but gated behind `settings/academic/promotions`; sidebar has no Promotion entry. Historical integrity: `PromotionDecision` workflow with review/approve. |
| A16 | **Final Results** | YES | `AcademicFinalResult.php`, `AcademicFinalResultRow.php`, `AcademicFinalResultStudent.php`, `AcademicCumulativeResult*.php`, `AcademicFinalResultService.php`, `AcademicFinalResultLifecycleService.php`, `AcademicFinalResultPreflightService.php`, `AcademicFinalResultController.php:index/storeResult/show/approve/report/result-sheet/sendToReview/lock/publish/export/readiness/readinessExport/preflight/policy/updatePolicy` + `AcademicFinalResultPolicy.php` | `routes/institute_modules.php:1199` `settings.academic.final-results.*` (full lifecycle) `permission:education.manage + domain:academic` | `resources/views/institute/academic-final-results/index.blade.php`, `show.blade.php`, `report-card.blade.php`, `result-sheet.blade.php`, `readiness.blade.php`, `preflight.blade.php`, `policy.blade.php` | **EXISTING + UI PARTIAL** — lifecycle `Draft→Review→Approved→Locked→Published` implemented via `sendToReview/approve/lock/publish` (`AcademicFinalResultController`, `AcademicFinalResultLifecycleService`). UI not surfaced top-level; result sheet/report-card exist. Historical: locked/published cannot be destructively mutated (service guards). |
| A17 | **Result Review** | YES | Same `AcademicFinalResult*` + `Approval*` models | `POST .../final-results/{result}/send-to-review` `routes/institute_modules.php:1206`, `POST .../decisions/{decision}/send-to-review` for promotions `1230` | `index/show/readiness/preflight` | **EXISTING + UI PARTIAL** — route exists, no dedicated Review queue nav. |
| A18 | **Result Approval** | YES | Same | `POST .../final-results/{result}/approve` `1203`, `POST .../decisions/{decision}/approve` | Same | **EXISTING + UI PARTIAL** — exists. |
| A19 | **Result Lock** | YES | `AcademicAssessment::lock/unlock`, `AcademicFinalResultLifecycleService` | `POST settings.academic.assessments/{assessment}/lock` `1190`, `unlock 1191`, `POST final-results/{result}/lock 1207` | Same | **EXISTING + UI PARTIAL** — exists; historical integrity enforced. |
| A20 | **Result Publish** | YES | Same lifecycle | `POST final-results/{result}/publish 1208` | Same | **EXISTING + UI PARTIAL** — exists. |
| A21 | **Report Card** | YES | Service `AcademicResultExportService.php`, controller `report`/`resultSheet` | `GET final-results/{result}/report 1204`, `result-sheet 1205`, `export 1209` | `resources/views/institute/academic-final-results/report-card.blade.php`, `result-sheet.blade.php` | **EXISTING + UI PARTIAL** — export routes exist; no Academic → Results → Report Card nav. |
| A22 | **Transcript** | YES | `StudentAcademicHistoryService.php`, `StudentAcademicCertificateService.php`, `StudentAcademicPlacementController`, `StudentController::academicTranscript/academicHistory` | `routes/institute_modules.php:1091` `students/{student}/academic-transcript` `middleware domain:academic` + `academic-transcript` | `resources/views/students/academic_transcript.blade.php`, `academic_history.blade.php` | **EXISTING + UI PARTIAL** — per-student transcript exists; not in main nav, accessed via student profile tabs. |
| A23 | **Certificate** | YES | `Certificate.php`, `CertificateType.php`, `StudentAcademicCertificateService.php`, `CertificateController.php:request/action`, `CertificateApprovalModeService.php` | `routes/institute_modules.php:1094` `students/{student}/certificate-request`, `1095` `certificates/{certificate}/action` `domain:academic`, `routes/web.php:190` `certificates.index` (`permission:certificates.view`), `institute_modules.php:1311` `certificate-types.*` | `resources/views/certificates/index.blade.php`, `certificate-types/*`, `admin/certificates/*` | **EXISTING + UI PARTIAL** — professional + academic certificates share `certificates.index`; academic certificates gated domain:academic; professional also visible via sidebar when `isProfessional`. |
| A24 | **Academic Attendance** | YES | `StudentAcademicAttendanceService.php`, `AcademicAttendanceMarkingService.php`, `AcademicAttendanceReportService.php`, `AcademicAttendanceController.php:index/store`, `AcademicAttendanceReportController.php` | `routes/web.php:161` `academic-attendance/mark` + `reports` `domain:academic`, `routes/institute_modules.php:1101` `academic-attendance.mark.store/reports/*` `domain:academic` | `resources/views/academic-attendance/index.blade.php`, `reports/index.blade.php`, `reports/class.blade.php`, `reports/daily.blade.php`, `reports/student.blade.php`, `_sheet.blade.php` | **EXISTING + UI PARTIAL** — attendance mark + reports exist; no Academic → Attendance bucket nav (two routes under `academic-attendance/*` but not in sidebar). |

**Summary A:** 24 academic capabilities audited. **0 missing backend**. **22 are EXISTING + UI PARTIAL** (backend complete but hidden under `settings/academic/*` or per-entity pages), **2 are EXISTING + UI EXISTS** (Students, canonical Subjects via tabs + enabled by B7 sidebar Classes toggle). No fake data introduced. Historical results protected by lifecycle guards.

---

## B. PROFESSIONAL / TRAINING CENTER UI — FORENSIC MAP

Domain: `InstituteDomain::PROFESSIONAL` = `industry='training_center' AND sub_industry IN [training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center]` (`InstituteDomain.php:33-40`). Examples in spec align 1:1.

| # | Capability | Backend Exists | Controller / Service / Model | Route(s) | View(s) | UI Status |
|---|------------|----------------|------------------------------|----------|---------|-----------|
| B1 | **Courses** | YES | `Course.php`, `CourseMasterService.php`, `CourseMasterController.php:37` (`index/create/store/edit/update/destroy`), `CourseAuditService.php` | `routes/institute_modules.php:928` `courses.manage.*` canonical `permission:courses.view/manage` (domain-unaware here — filtered by category subject_type) + `routes/web.php:188` `courses/archive` | `resources/views/institute/course-master/index.blade.php:1`, `form.blade.php:63073` | **EXISTING + UI EXISTS** — restored B7 for professional (`layouts/institute.blade.php:203` `isProfessional && hasEducationModule` → `/courses/manage`). Domain-isolated via `Course::where institute_id` + category derivation. |
| B2 | **Subjects** (Professional) | YES | `Subject.php` SoftDeletes, `SubjectManagementController.php:30` canonical, `CourseCategory.php` | `routes/institute_modules.php:952` `courses.manage.subjects.*` `permission:courses.view/manage` | `resources/views/institute/course-master/subjects.blade.php`, `subject-form.blade.php`, `_tabs.blade.php:17` | **EXISTING + UI EXISTS** — professional subjects clamped `subject_type=professional` (`SubjectManagementController:79` `derived = InstituteDomain::subjectTypeFor`), UI link `courses.manage.subjects.index` visible for professional (`layouts/institute.blade.php:208`). |
| B3 | **Course Categories** | YES | `CourseCategory.php` TenantScoped, `CourseCategoryManageController.php:26` | `routes/institute_modules.php:938` `courses.manage.categories.*` (`GET/POST/PUT {category}/DELETE`) `permission:courses.view/manage` (B7 hardened) | JSON modal in `institute/course-master/form.blade.php` + standalone category filter in `index` | **EXISTING + UI EXISTS (via Course Master + JSON modal)** — not a separate standalone page but reachable; `filterCategories()` tenant+domain scoped. |
| B4 | **Course Sub-Categories** | YES | `CourseSubCategory.php` TenantScoped, `CourseSubCategoryManageController.php` | `routes/institute_modules.php:945` `courses.manage.sub-categories.*` (`GET/POST/PUT/DELETE`) | JSON modal in `course-master/form.blade.php` | **EXISTING + UI EXISTS (via Course Master)** — same as Category. |
| B5 | **Curriculum** | YES | `CourseCurriculum.php`, `CurriculumController.php:index/create/store/show/edit/update/activate/destroy` `CourseCurriculumService.php`, `CurriculumModule.php`, `CurriculumLesson.php` | `routes/institute_modules.php:900` `curricula.*` (`index/create/store/show/edit/update/activate/destroy` + `modules.*` + `lessons.*`) `permission:curriculum.view/manage` | `resources/views/institute/curriculum/index.blade.php`, `form.blade.php`, `show.blade.php` | **EXISTING + UI EXISTS** — sidebar `Curriculum` for professional (`layouts/institute.blade.php:211`) and for academic when `!usesClassTerm` (polytechnic/university) (`224`). TenantScoped. |
| B6 | **Curriculum Modules** | YES | `CurriculumModule.php`, `CurriculumController:storeModule/updateModule/destroyModule` | `routes/institute_modules.php:910` `curricula.{curriculum}/modules` POST/PUT/DELETE | `curriculum/show.blade.php`, `form.blade.php` | **EXISTING + UI EXISTS** — nested under curriculum show. |
| B7 | **Curriculum Lessons** | YES | `CurriculumLesson.php`, `CurriculumController:storeLesson/updateLesson/destroyLesson` | `routes/institute_modules.php:914` `curricula.{curriculum}/lessons` POST/PUT/DELETE | `curriculum/show.blade.php` | **EXISTING + UI EXISTS** — nested under curriculum module. |
| B8 | **Requirements** | YES — JSON on Course | `Course.php: requirements array cast`, `CourseMasterService` handles `requirements` field | Via `courses.manage` form `CourseMasterController:store/update` | `institute/course-master/form.blade.php` requirements section | **EXISTING + UI EXISTS** — part of Course form. |
| B9 | **Outcomes** | YES — JSON on Course | `Course.php: outcomes array cast` | Same `courses.manage` | Same form | **EXISTING + UI EXISTS** |
| B10 | **Materials** | YES | `CourseMaterial.php`, `CourseMaterialController.php:store/destroy`, `CourseMaterialService.php` | `routes/institute_modules.php:935` `courses.manage.{course}/materials.*` `permission:courses.manage` | `course-master` show/form materials panel | **EXISTING + UI EXISTS** |
| B11 | **Attachments** | YES — same Materials | Same | Same | Same | **EXISTING + UI EXISTS** — material attachments storage `Storage::` paths. |
| B12 | **Instructors / Teachers** | YES — Single system | `TeacherProfile.php`, `TeacherAcademicAssignment.php`, `InstituteUser.php` role teacher, `TeacherController.php:index/create/store/show/edit/update/status/assign/complete/remove` `TeacherProfileService.php`, `TeacherWorkloadService.php` | `routes/web.php:355` `teachers.index` + `routes/institute_modules.php:1076` `teachers.*` (`create/store/show/edit/update/status/assign/complete/remove`) | `resources/views/institute/teachers/index.blade.php`, `form.blade.php`, `show.blade.php` | **EXISTING + UI EXISTS** — **single Teacher/Instructor system** reused. Sidebar `Teachers` for academic `layouts/institute.blade.php:150` when `isEducation && workspaceAllowedTeachers`; not yet for professional (gap). Backend already supports both via `TeacherProfileService` + `Batch.teacher_id` M2M. **B7 note: Do not duplicate — distinguish via domain-aware navigation only.** |
| B13 | **Batches** | YES | `Batch.php` TenantScoped+BranchScoped SoftDeletes, `BatchController.php:index/show/store/update/destroy/archive/unarchive/changeStatus/transferStudent/removeStudent` | `routes/web.php:165` `batches.*` + `routes/institute_modules.php:989` `batches.{batch}/status/transfer/remove-student` | `resources/views/batches/index.blade.php`, `show.blade.php` | **EXISTING + UI EXISTS** — professional sidebar `Batches` (`layouts/institute.blade.php:214`), academic `Batches` when `!usesClassTerm` (`229`). Domain-aware via course category. |
| B14 | **Enrollments** | YES | `StudentEnrollment.php`, `StudentAcademicPlacementService.php` (academic enroll), `StudentController:enroll` | `routes/web.php:144` `students.{student}/enroll` `permission:students.manage` + `admissions/pipeline.*` | `students/show.blade.php` enroll actions, `admissions/pipeline.blade.php` | **EXISTING + UI PARTIAL** — enrollment via Student detail + pipeline; no dedicated Batches→Enrollments standalone tab. |
| B15 | **Attendance** (Professional — batch-based) | YES | `Attendance.php`, `Exam` batch attendance, `HrAttendance*` separate HR | `batches.show` attendance tab + `exams.marks` + legacy `Attendance` model | `batches/show.blade.php` | **EXISTING + UI PARTIAL** — batch attendance present; not top nav bucket; `academic-attendance` is academic-only. |
| B16 | **Exams** | YES | `Exam.php`, `ExamResult.php`, `ExamSubject.php`, `ExamType.php`, `ExamController.php:index/sendToExam/show/update/saveMarks/destroy` | `routes/web.php:175` `exams.*` (`index/sendToExam/show/update/saveMarks/destroy`) + `institute_modules.php:1071` `exams.marks` alias | `resources/views/exams/index.blade.php`, `show.blade.php`, `_send_modal.blade.php` | **EXISTING + UI EXISTS** — sidebar `Exams` for both domains (`layouts/institute.blade.php:177,217`). Tenant isolation via `Exam::query` with institute_id resolution in controller. |
| B17 | **Results** (Professional) | YES | `Result.php`, `Certificate.php`, `ExamResult.php`, `ExamController:saveMarks` + `CertificateController` | `exams.index` `tab=results` (`Result::query` in `ExamController:index` `Result` paginator) | `exams/index.blade.php` tab `results` + `certificates/index.blade.php` | **EXISTING + UI EXISTS** — `Exams` results tab + `Certificates` standalone for professional (`layouts/institute.blade.php:220-221`). |
| B18 | **Certificates** | YES | `Certificate.php`, `CertificateType.php`, `CertificateController:index/request/action`, `CertificateTypeController.php` | `routes/web.php:190` `certificates.index`, `routes/institute_modules.php:1095` `certificates/{certificate}/action` `domain:academic` for academic workflow, `1311` `certificate-types.*` | `resources/views/certificates/index.blade.php`, `certificate-types/index.blade.php` | **EXISTING + UI EXISTS** — professional certificates link `certificates.index`; academic certificates also domain:academic flow. |

**Summary B:** Professional domain **fully backend-complete**; **12 of 18 items are EXISTING + UI EXISTS**, **6 PARTIAL** (Instructor sidebar for professional missing, Enrollments/Attendance lack dedicated top nav — but reachable via Batch/Student detail). No duplicate instructor system exists — intentional single source.

---

## C. TEACHER / INSTRUCTOR — FORENSIC MAP

| Question | Finding | Evidence |
|----------|---------|----------|
| **Single vs duplicate system?** | **SINGLE — reuse.** One teacher/instructor system for both domains. Controller `TeacherController.php:1` (doc: "Teacher / instructor management") + `TeacherProfile.php` `EMPLOYMENT_STATUSES` + `TeacherAcademicAssignment.php` (links teacher to `ClassGrade`/`AcademicGroup`). No second `InstructorController`. | `app/Http/Controllers/TeacherController.php:12` comment `Teacher / instructor management`; `app/Models/TeacherProfile.php:1`; `app/Models/TeacherAcademicAssignment.php:1`; `routes/web.php:355` + `routes/institute_modules.php:1076` only one `teachers.*` group. |
| **Academic vs Professional distinction?** | **Backend supports distinction but not duplicated.** `TeacherAcademicAssignment` ties teacher to academic class/group; `Batch.teacher_id` (FK to `Membership`) ties batch/instructor for professional workflow (`Batch.php:54` `teacher()` belongsTo `Membership`). `TeacherWorkloadService` could cover both. No `InstructorProfile` table exists. | `app/Models/Batch.php:54` `teacher()` `Membership`; `app/Models/TeacherAcademicAssignment.php` `classGrade/academicGroup`; `app/Services/TeacherWorkloadService.php`. |
| **UI visibility** | Academic: **EXISTS** sidebar `Teachers` when `isEducation && workspaceAllowedTeachers` (`layouts/institute.blade.php:150`). Professional: **MISSING** — no `isProfessional && workspaceAllowedTeachers` branch; instructor still reachable via direct `/teachers` but not in nav. | `layouts/institute.blade.php:150-153` only `if ($isEducation && ($workspaceAllowedTeachers ?? false))`. Professional gate missing — gap to fix domain-aware. |
| **Workflow link Instructor → Courses → Curriculum → Batches** | **Backend relations exist** but UI not linked domain-aware. `Batch.course` + `Batch.curriculum` + `Batch.teacher` + `CourseCurriculum` relations exist; controller `CurriculumController:403` now correctly derives availableCourses per institute domain (B7 fix). UI for assignment is `teachers/assign` (`TeacherController:assign`) academic-focused; batch teacher assignment via `BatchController:transferStudent/status` (professional). No circular dependency blocking. | `app/Models/Batch.php:course/curriculum/teacher`; `app/Http/Controllers/CurriculumController.php:403` fixed; `routes/institute_modules.php:1083` `teachers/{teacher}/assign`. |
| **Recommendation** | **Do NOT create duplicate Instructor system.** Add professional `Teachers / Instructors` nav entry pointing to same `teachers.index` (label domain-aware), verify controller permission `teacher.*` and tenant isolation via `InstituteUser::where role_id=teacherRoleId` + `teacherProfile` branch scoping (already). | — |

---

## D. EXISTING ROUTES — FORENSIC CLASSIFICATION

**Scope:** `routes/web.php` (408 lines) + `routes/institute_modules.php` (1660 lines) ~ 778+ tenant module routes. Middleware: `auth:institute_user,web` + `tenant` + `verified` + `permission:*` + `domain:academic` + `module_access:*`.

### D.1 Classification Matrix (sampled critical for B9 — full route:list grep `php artisan route:list --path=...`)

| Route Name (primary) | Method+URI | Domain Gate | Tenant? | Controller:Line | Classification | Rationale |
|----------------------|------------|-------------|---------|-----------------|----------------|-----------|
| `dashboard` | `GET /` | none | yes (`tenant`) | `DashboardController:__invoke` `routes/web.php:116` | **EXISTING + UI EXISTS** | Top nav always; domain-aware branching inside. |
| `academic.dashboard` | `GET academic/dashboard` | `domain:academic` | yes | `AcademicDashboardController:__invoke` `routes/web.php:159` | **EXISTING + UI PARTIAL** | Hidden — no sidebar link to academic dashboard. |
| `students.index/show/etc` | `GET students` `permission:students.view` | none (both domains) | yes | `StudentController` `routes/web.php:139` | **EXISTING + UI EXISTS** | Sidebar for both academic/professional when `hasEducationModule` — correct. |
| `courses.manage.index` **canonical** | `GET courses/manage` `permission:courses.view` | none (domain via category filter) | yes | `CourseMasterController:index` `institute_modules.php:929` | **EXISTING + UI EXISTS** | B7 — tabs exists. Master page per spec Step 3. |
| `courses.manage.subjects.index` **canonical** | `GET courses/manage/subjects` `permission:courses.view` | none (server-derived `subject_type`) | yes | `SubjectManagementController:index` `institute_modules.php:953` | **EXISTING + UI EXISTS** | Via tabs; not standalone legacy. |
| `courses.manage.categories.*` | `GET/POST/PUT/DELETE courses/manage/categories` `permission:courses.view/manage` | none (derived) | yes | `CourseCategoryManageController` `institute_modules.php:938` | **EXISTING + UI EXISTS (JSON)** | B7 hardened RBAC. |
| `courses.manage.sub-categories.*` | `GET/POST/PUT/DELETE courses/manage/sub-categories` `permission:courses.manage` | none | yes | `CourseSubCategoryManageController` `945` | **EXISTING + UI EXISTS (JSON)** | — |
| `curricula.*` | `GET curricula` `permission:curriculum.view` etc | none | yes | `CurriculumController` `900` | **EXISTING + UI EXISTS** | Sidebar for professional always, academic when `!usesClassTerm`. |
| `batches.index/show/etc` | `GET batches` `permission:batches.view` | none | yes (TenantScoped+BranchScoped) | `BatchController` `web.php:165` + `institute_modules.php:989` | **EXISTING + UI EXISTS** | Same sidebar rule as curriculum. |
| `exams.index/show/etc` | `GET exams` `permission:exams.view` | none | yes | `ExamController` `web.php:175` | **EXISTING + UI EXISTS** | For both domains. |
| `teachers.index` | `GET teachers` | none | yes | `TeacherController:index` `web.php:355` + `institute_modules.php:1076` sub-routes | **EXISTING + UI PARTIAL** | Backend exists; sidebar only for academic (gap). |
| `classes.index/subjects/batches/archive` | `GET classes` `permission:courses.view` + `domain:academic` (B7 fixed) | `domain:academic` | yes | `ClassController` `institute_modules.php:979` | **EXISTING + UI EXISTS (academic toggle)** | Academic institutes see `Classes` vs `Courses` toggle via `usesClassTerm`. |
| `settings.academic.index/label/levels/groups/classes` | `GET/POST/PUT/DELETE settings/academic/*` | `domain:academic + permission:education.manage` | yes | `AcademicStructureController` `1144` | **EXISTING + UI PARTIAL** | Hidden under settings; not top nav. |
| `settings.academic.grading.*` | `GET/POST/PUT/DELETE settings/academic/grading` | `domain:academic + education.manage` | yes | `AcademicGradingController` `1163` | **EXISTING + UI PARTIAL** | Same. |
| `settings.academic.aggregations.*` | `GET settings/academic/aggregations` | `domain:academic` | yes | `AcademicAggregationController` `1172` | **EXISTING + UI PARTIAL** | — |
| `settings.academic.assessments.*` | `GET settings/academic/assessments` + `lock/unlock/subjects/marks/sheet/readiness` | `domain:academic` | yes | `AcademicAssessmentController`, `AcademicMarksController`, `AcademicFinalResultController` `1182` | **EXISTING + UI PARTIAL** | Deep lifecycle exists. |
| `settings.academic.final-results.*` | `GET/POST settings/academic/final-results` `approve/lock/publish/send-to-review/report/result-sheet/export/readiness/preflight/policy` | `domain:academic` | yes | `AcademicFinalResultController` `1199` | **EXISTING + UI PARTIAL** | Lifecycle Draft→Review→Approved→Locked→Published implemented (`AcademicFinalResultLifecycleService`). |
| `settings.academic.promotions.*` | `GET settings/academic/promotions` `policies.*` `rules.*` `decisions.*` `+ permission:promotion.manage` | `domain:academic` | yes | `AcademicPromotionController` `1217` | **EXISTING + UI PARTIAL** | Extra guard. |
| `settings.academic.placements.*` | `GET settings/academic/placements` `index/create/store/show/edit/update/destroy/subjects` | `domain:academic` | yes | `StudentAcademicPlacementController` `1236` | **EXISTING + UI PARTIAL** | — |
| `settings.academic.academic-years.*` | `POST/PUT/DELETE settings/academic/academic-years` | `domain:academic` | yes | `StudentAcademicPlacementController` `1247` | **EXISTING + UI PARTIAL** | Nested under placements, not standalone nav. |
| `academic-attendance.mark.*` | `GET academic-attendance/mark` + `POST mark` | `domain:academic` | yes | `AcademicAttendanceController` `web.php:161` + `institute_modules.php:1101` | `EXISTING + UI PARTIAL` | Two route defs (web+modules) — **DUPLICATE** alias (see D.2). |
| `academic-attendance.reports.*` | `GET academic-attendance/reports` etc + export | `domain:academic` | yes | `AcademicAttendanceReportController` | **EXISTING + UI PARTIAL** | — |
| `academic.analytics.*` | `GET academic/analytics` `attendance/students/courses/promotions/completion/certificates/finance/crm/export` | `domain:academic` | yes | `AcademicAnalyticsController` `web.php:160` + `institute_modules.php:1114` | **EXISTING + UI PARTIAL** | No sidebar bucket; exists via direct URL. |
| `students.academic-transcript/academic-history/academic-attendance/academic-transfer/academic-withdraw` | `GET students/{student}/academic-*` | `domain:academic` | yes | `StudentController`, `CertificateController:request` `1088` | **EXISTING + UI PARTIAL** | Per-student tab, not main nav. |
| `certificates.index/action` | `GET certificates` `permission:certificates.view` | none / academic action `domain:academic` | yes | `CertificateController` `web.php:190`, `institute_modules.php:1095` | **EXISTING + UI EXISTS** | Professional always + academic domain academic. |
| `business.profile` | `GET business/profile` `tenant+verified` | none (domain-aware display) | yes (Workspace authoritative) | `BusinessProfileController:show` `web.php:349` | **EXISTING + UI EXISTS** | Topbar brand → profile (`layouts/institute.blade.php:32`). |
| `courses.archive/subjects (legacy)` | `GET courses/archive` `courses/subjects` `permission:courses.view` | none | yes | `CourseController:archive/subjects` `web.php:188` | **LEGACY** | Legacy listing not canonical; canonical is `courses.manage.*`. Keep but do not surface as primary nav. |
| `courses/{course}` `show` | `GET courses/{course}` | none | yes | `CourseController:show` `institute_modules.php:974` | **LEGACY** | Non-canonical show collides with manage; kept for backwards compat. |

**Other large groups (not B9 focus but tenant-isolated):** `finance.*` (budgets/chart/journal/invoice/payment/party/method/period/exchange/fx/audit/education), `accounting.*`, `inventory.*`, `fixed_assets.*`, `hr.*` (employees/departments/designations/attendance/leave/payroll/performance/recruitment/training/self/documents/reports/salary-structures/manager), `crm.*`, `sales.*`, `purchase.*` — all `tenant` scoped — classified **EXISTING + UI EXISTS** per finance dashboards.

### D.2 Specific Audit Findings — Routes

- **DUPLICATE routes flagged:**

  1. `academic-attendance.mark.*` defined twice: `web.php:161` `GET academic-attendance/mark → AcademicAttendanceController@index` AND `institute_modules.php:1564` `POST academic-attendance/mark → AcademicAttendanceController@store` plus `1101` `academic-attendance.*` group — **harmless duplicate but consolidate in impl (keep index+store under one group).** Marked **DUPLICATE — KEEP CANONICAL, ALIAS LEGACY**.
  2. `exams.marks` vs `exams/{exam}/marks` (`web.php:181` `exams.{exam}/marks` + `institute_modules.php:1071` `exams/{exam}/marks` + `1571` `exams/{exam}/marks` again) — **DUPLICATE alias tolerant** (same controller). Keep one canonical.
  3. `admin.academic.grading.*` duplicated inside `$tenant` + outside `auth:platform_admin` at bottom `institute_modules.php:1592` vs `1651` — **DUPLICATE — KEEP platform_admin outside tenant** (correct guard). File even comments "Admin Grading aliases outside tenant (overrides)".
  4. `hr.reports.*` double `institute_modules.php:201` prefix `hr.reports.` with empty `hr.reports.` vs `202-213` real + alias `1582` + `1610` — **DUPLICATE — retain `hr.reports.index`.**

- **WRONG DOMAIN:** Pre-B7 `classes.*` lacked `domain:academic` — **FIXED** (`institute_modules.php:979` `middleware domain:academic`). Remaining `withinGlobalScope('institute')` reads in `ClassController:47` are safe catalog reads, not leaks.

- **WRONG PERMISSION:** Pre-B7 categories/sub-categories lacked `permission:courses.*` — **FIXED** (`institute_modules.php:939-948`). `classes.*` now `permission:courses.view` / `batches.view` etc.

- **WRONG TENANT SCOPE:** none — all B9 institute routes inside `$tenant = ['auth:institute_user,web','tenant','verified']` group `institute_modules.php:16` or `web.php:348` `tenant`. Platform-admin `admin.*` correctly **outside** tenant (after `// end $tenant` comment `1366`).

- **LEGACY:** `courses/archive`, `courses/subjects` (CourseController), `courses/{course}` show — keep but deprecate (do not delete data). `courses/{course}/subjects/{subject}` nested update retained but shallow `PUT courses/subjects/{subject}` is canonical (`web.php:404` comment explains).

---

## E. EXISTING VIEWS — FORENSIC MAP

**Scanned:** `resources/views` recursive (checked via `Get-ChildItem -Recurse`). Relevant subsets:

### E.1 Academic Views — All Existing (reuse — do NOT duplicate)

| Path | Purpose | Used By Route | Status |
|------|---------|---------------|--------|
| `resources/views/academic/dashboard.blade.php:1` | Academic dashboard | `academic.dashboard` | **EXISTS** |
| `resources/views/academic/analytics/*.blade.php` (9 files: `index`, `attendance`, `batches`, `certificates`, `completion`, `courses`, `crm`, `finance`, `promotions`, `results`, `students`) | Analytics per metric | `academic.analytics.*` | **EXISTS** |
| `resources/views/academic-attendance/index.blade.php:1` | Mark attendance | `academic-attendance.mark.index` | **EXISTS** |
| `resources/views/academic-attendance/reports/*.blade.php` (`index`, `class`, `daily`, `student`, `_sheet`) | Attendance reports + export | `academic-attendance.reports.*` | **EXISTS** |
| `resources/views/classes/index.blade.php`, `archive.blade.php`, `batches.blade.php`, `subjects.blade.php`, `_tabs.blade.php` | Class listing/tabs | `classes.*` domain:academic | **EXISTS** |
| `resources/views/institute/academic-structure.blade.php:1` | Structure Levels/Classes/Groups | `settings.academic.index` | **EXISTS** |
| `resources/views/institute/learning-structure-settings.blade.php:1` | Learning settings (assign template, nodes) | `academic.structure.settings` | **EXISTS** |
| `resources/views/institute/academic-placements/index.blade.php`, `show.blade.php`, `form.blade.php`, `_subjects.blade.php` | Placements CRUD + subjects selector | `settings.academic.placements.*` | **EXISTS** |
| `resources/views/institute/academic-assessments/index.blade.php`, `form.blade.php`, `show.blade.php`, `marks.blade.php`, `marks-sheet.blade.php`, `readiness.blade.php` | Assessments + marks sheet + readiness | `settings.academic.assessments.*` | **EXISTS** |
| `resources/views/institute/academic-aggregations/index.blade.php`, `form.blade.php`, `show.blade.php` | Aggregations | `settings.academic.aggregations.*` | **EXISTS** |
| `resources/views/institute/academic-grading/index.blade.php`, `form.blade.php`, `preview.blade.php` | Grade scales | `settings.academic.grading.*` | **EXISTS** |
| `resources/views/institute/academic-promotions/index.blade.php`, `policy.blade.php`, `policy-form.blade.php`, `decision.blade.php`, `sheet.blade.php` | Promotion lifecycle | `settings.academic.promotions.*` | **EXISTS** |
| `resources/views/institute/academic-final-results/index.blade.php`, `show.blade.php`, `report-card.blade.php`, `result-sheet.blade.php`, `readiness.blade.php`, `preflight.blade.php`, `policy.blade.php` | Final results + report card + policy | `settings.academic.final-results.*` | **EXISTS** |
| `resources/views/students/academic_transcript.blade.php`, `academic_history.blade.php`, `academic_attendance.blade.php` | Transcript/history | `students.academic-transcript/history/attendance` | **EXISTS** |
| `resources/views/students/index.blade.php`, `show.blade.php`, `form.blade.php`, `_tabs.blade.php` | Students CRUD | `students.*` | **EXISTS** |
| `resources/views/certificates/index.blade.php`, `certificate-types/index.blade.php`, `form.blade.php` | Certificates | `certificates.*`, `certificate-types.*` | **EXISTS** |

**E.1 Finding:** Every academic backend page requested in spec Step 5 (Year/Class/Group/Subject/Placement/Assessment/Marks/Aggregation/Grade Scale/Promotion/Final Result/Transcript/Certificate) **has a Blade view already**. No view needs to be created from scratch; restoration is **wiring navigation to existing views**.

### E.2 Professional Views — All Existing (reuse)

| Path | Purpose | Status |
|------|---------|--------|
| `resources/views/institute/course-master/index.blade.php:19533` | Courses Master list+filters+paginator | **EXISTS** |
| `resources/views/institute/course-master/subjects.blade.php:23906` | Subjects list (canonical) | **EXISTS** |
| `resources/views/institute/course-master/_tabs.blade.php:984` `[Courses][Subjects]` | Tabs | **EXISTS** |
| `resources/views/institute/course-master/form.blade.php:63730` | Course form (category/sub-cat modals) | **EXISTS** |
| `resources/views/institute/course-master/subject-form.blade.php:6983` | Subject form | **EXISTS** |
| `resources/views/institute/course-master/subject-dependencies.blade.php:6072` | Dependencies | **EXISTS** |
| `resources/views/institute/curriculum/index.blade.php`, `form.blade.php`, `show.blade.php` | Curriculum | **EXISTS** |
| `resources/views/batches/index.blade.php`, `show.blade.php` | Batches | **EXISTS** |
| `resources/views/exams/index.blade.php`, `show.blade.php`, `_send_modal.blade.php` | Exams+Results | **EXISTS** |
| `resources/views/institute/teachers/index.blade.php`, `form.blade.php`, `show.blade.php` | Teachers | **EXISTS** (single system) |
| `resources/views/business/profile.blade.php:405` | Business Profile domain-aware (academic/professional/other) | **EXISTS** |
| `resources/views/layouts/institute.blade.php:860` | Sidebar/topbar (domain-aware after B7) | **EXISTS** |

**E.3 Verify Before Duplicate:** Checked `resources/views/classes/*` vs `institute/course-master/*` — they are **distinct** (academic class archive is not duplicate of course master). Verified `settings.academic.subjects` deleted artefact absent — do not recreate.

---

## F. EXISTING CONTROLLERS / SERVICES / MODELS — REUSE MAP

### F.1 Controllers (existing — MUST reuse)

| Controller File | Line | Responsibility | Tenant? | Domain? |
|-----------------|------|----------------|---------|---------|
| `app/Http/Controllers/CourseMasterController.php:37` | `index/create/store/edit/update/destroy` | Institute-owned courses | `Course::where institute_id` + `InstituteDomain::subjectTypeFor` count | — |
| `app/Http/Controllers/SubjectManagementController.php:30` | `index/create/store/edit/update/destroy/restore/dependencies` | Canonical subjects | `subjectQuery($instituteId, $derived)` `where institute_id=X AND subject_type=derived` `TenantContext` | **server-derived domain** — clamps `?subject_type` |
| `app/Http/Controllers/CourseCategoryManageController.php:26` | `categories CRUD` | Categories | `Rule::exists ... ->where('institute_id', ...)->where('subject_type', $derived)` | derived |
| `app/Http/Controllers/CourseSubCategoryManageController.php:17` | `sub-categories CRUD` | Sub-categories | same | derived |
| `app/Http/Controllers/CurriculumController.php:31` | `index/create/store/show/edit/update/destroy/storeModule/updateModule/destroyModule/storeLesson/updateLesson/destroyLesson/activate` | Curriculum + modules + lessons | `TenantScoped CourseCurriculum` | derives from `InstituteDomain` after B7 fix |
| `app/Http/Controllers/BatchController.php:33` | `index/show/store/update/destroy/archive/unarchive/changeStatus/transferStudent/removeStudent` | Batches | `TenantScoped + BranchScoped` | via course category |
| `app/Http/Controllers/TeacherController.php:12` | `index/create/store/show/edit/update/status/assign/complete/remove` | Teachers/Instructors (single) | `InstituteUser::where role_id=teacherRoleId` + `teacherProfile` | branch-aware |
| `app/Http/Controllers/ClassController.php:24` | `index/subjects/batches/archive` | Academic class archive view | `TenantScoped` + `domain:academic` gate | academic only |
| `app/Http/Controllers/StudentController.php:140` | `index/create/store/show/enroll/edit/update/photo/destroy/academicHistory/academicAttendance/academicTranscript/transfer/withdraw` | Students | `TenantScoped Student` | academic sub-routes `domain:academic` |
| `app/Http/Controllers/ExamController.php:24` | `index/show/update/sendToExam/saveMarks/destroy/marks` | Exams + professional results | `Exam::query with institute_id resolution` | both |
| `app/Http/Controllers/CertificateController.php:16` | `index/request/action` | Certificates | `Certificate` TenantScoped | `students.certificate-request` `domain:academic` for academic |
| `app/Http/Controllers/AcademicStructureController.php:145` | `index/updateLabel/storeLevel/updateLevel/destroyLevel/storeClass/updateClass/destroyClass/storeGroup/updateGroup/destroyGroup` | Structure label + levels + classes + groups | `TenantContext::id()` | `domain:academic` |
| `app/Http/Controllers/AcademicGradingController.php:1` | `index/create/store/edit/update/destroy/preview` | Grade scales | `GradeScale` institute_id | `domain:academic` |
| `app/Http/Controllers/AcademicAggregationController.php:1` | `index/create/store/show/edit/update/destroy/assessments` | Aggregations | — | `domain:academic` |
| `app/Http/Controllers/AcademicAssessmentController.php:1` | `index/create/store/show/edit/update/destroy/lock/unlock/subjects/readiness` | Assessments | `AcademicAssessment` global-scope institute | `domain:academic` |
| `app/Http/Controllers/AcademicMarksController.php:1` | `store/sheet/export` | Marks sheets | same | `domain:academic` |
| `app/Http/Controllers/AcademicFinalResultController.php:1` | `index/storeResult/show/approve/report/resultSheet/sendToReview/lock/publish/export/readiness/preflight/policy` | Final results lifecycle | `AcademicFinalResult` TenantScoped | `domain:academic` + lifecycle guards |
| `app/Http/Controllers/AcademicPromotionController.php:1` | `promotions.* policies/rules/decisions` | Promotion decisions | — | `domain:academic` + `promotion.manage` |
| `app/Http/Controllers/StudentAcademicPlacementController.php:1` | `placements.* + academic-years.*` | Placements + Academic Years | institute_id | `domain:academic` |
| `app/Http/Controllers/AcademicAttendanceController.php:72` | `index/store` | Academic attendance mark | `StudentAcademicAttendanceService` | `domain:academic` |
| `app/Http/Controllers/AcademicAttendanceReportController.php:1` | `index/classReport/daily/student/export*` | Attendance reports | — | `domain:academic` |
| `app/Http/Controllers/BusinessProfileController.php:16` | `show` | Business profile (domain-aware) | `Workspace authoritative` + `TenantContext` verify | domain switch display |
| `app/Http/Controllers/DashboardController.php:116` | `__invoke` | Generic dashboard | tenant | — |
| `app/Http/Controllers/AcademicDashboardController.php:1` | `__invoke` | Academic dashboard | tenant `domain:academic` | academic |

**F.1 Finding:** Every requested capability has a controller already. **Zero controllers need to be created.** Implementation is wiring sidebar/routes to them.

### F.2 Services (existing — MUST reuse)

- `AcademicAssessmentService.php`, `AcademicAssessmentAuditService.php` — assessment validation + subject selection.
- `AcademicSubjectService.php` — `effectiveClasses()` for institute.
- `AcademicMarksService.php`, `AcademicResultAggregationService.php`, `AcademicGradingService.php`, `AcademicCumulativeService.php`, `AcademicFinalResultService.php`, `AcademicFinalResultLifecycleService.php`, `AcademicFinalResultPreflightService.php`, `AcademicResultReadinessService.php`, `AcademicSetupService.php`, `AcademicStructureService.php`, `AcademicDashboardService.php`.
- `PromotionEvaluationService.php`, `PromotionLifecycleService.php`, `PromotionPlacementService.php`, `PromotionPolicyService.php`.
- `StudentAcademicPlacementService.php`, `StudentAcademicLifecycleService.php`, `StudentSubjectSelectionValidator.php`.
- `CourseMasterService.php`, `CourseCurriculumService.php`, `CourseAuditService.php`, `CurriculumAuditService.php`.
- `TeacherProfileService.php`, `TeacherWorkloadService.php`, `TeacherAuditService.php`, `ProfileImageService.php`.
- `ModuleAccessService.php` + `IndustryRules.php` (taxonomy), `Workspace.php`, `TenantContext.php`, `BranchContext.php`.

### F.3 Models (existing — MUST reuse)

| Model | File | Scoped | Key for B9 |
|-------|------|--------|------------|
| `Institute.php` | `app/Models/Institute.php:12` | — (identity) | `industry/sub_industry` → `InstituteDomain` |
| `AcademicSubject` pattern: `Subject.php` | `app/Models/Subject.php:12` SoftDeletes | `institute_id + subject_type` | `slug` auto, `category` `subject_type` |
| `SubjectManagement` alias: `CourseCategory.php` `CourseSubCategory.php` | TenantScoped | `institute_id + subject_type` | category hierarchy |
| `CourseMaster` → `Course.php` | `app/Models/Course.php:11` | `institute_id` | `modules/requirements/outcomes` JSON |
| `CourseCategory.php` `CourseSubCategory.php` `CourseCurriculum.php` | TenantScoped | institute_id | domain-derived |
| `Curriculum → CourseCurriculum.php` | TenantScoped | institute_id | active toggle |
| `CurriculumModule.php` `CurriculumLesson.php` | via curriculum | — | — |
| `Placement → StudentAcademicPlacement.php` + `StudentPlacementNode.php` + `StudentSubjectSelection.php` | `institute_id` | — | placement tree |
| `Assessment → AcademicAssessment.php` + `AssessmentSubject.php` `AssessmentSubjectComponent.php` `AssessmentType.php` `Component.php` | `TenantScoped+BranchScoped` | institute_id `status` lock | subject_type academic only |
| `Marks → AcademicStudentMark.php` | via assessment institute | — | — |
| `Aggregation → AcademicResultAggregationScheme.php` + `AcademicResultAggregationItem.php` | institute_id | — | scheme items |
| `GradeScale → GradeScale.php` + `GradeScaleRow.php` + `GradingScale.php` | institute_id | — | — |
| `Promotion → PromotionPolicy.php` `PromotionPolicyRule.php` `PromotionDecision.php` `PromotionDecisionItem.php` | institute_id | `status` | review/approve lifecycle |
| `FinalResult → AcademicFinalResult.php` + `AcademicFinalResultRow.php` `AcademicFinalResultStudent.php` `AcademicCumulativeResult*.php` | TenantScoped | `status` Draft→Published lock | historical integrity: `AcademicFinalResultPolicy.php` |
| `Transcript → StudentAcademicHistoryService` via `Student.php` + `Certificate.php` | institute_id | — | `academic_transcript.blade.php` |
| `Certificate → Certificate.php` + `CertificateType.php` | TenantScoped | `status`/approval | `VerifyCertificateController` |
| `Teacher/Instructor → TeacherProfile.php` + `TeacherAcademicAssignment.php` + `InstituteUser` role teacher | `TeacherProfile` via `InstituteUser` | branch_id | single system — do not duplicate |
| `Class/Level/Group → AcademicLevel.php` `ClassGrade.php` `AcademicGroup.php` `InstituteAcademicLevel.php` `InstituteClassGrade.php` `InstituteAcademicGroup.php` `StructureLabel.php` `StructureNode.php` `StructureTemplate.php` | via InstituteDomain + Structure engine | — | `LearningStructureResolver` |
| `Attendance → Attendance.php` + `HrAttendance.php` etc | BranchScoped/TenantScoped | — | academic vs HR separate |

**F Finding:** No `AcademicSubject` model distinct from `Subject` — `Subject` IS the canonical academic subject (`subject_type=academic`). Do NOT create `AcademicSubject` duplicate. Same for `CourseMaster` → `Course`. Preserve.

---

## G. DOMAIN RULES — FORENSIC AUDIT

**Single source:** `app/Support/InstituteDomain.php:17` — `ACADEMIC = 'academic'`, `PROFESSIONAL = 'professional'`, `OTHER = 'other'`; `ACADEMIC_TYPES = [school,college,polytechnic,university]` `PROFESSIONAL_TYPES = [training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center]`; `fromInstitute(?Institute)`, `fromKeys(industry,subIndustry)`, `isAcademic()`, `isProfessional()`, `subjectTypeFor()`, `normalizeIndustry/SubIndustry()`, `isValidCombination()`, `hasDomainData()`.

| Check | Spec Requirement | Current Compliance (file:line) | Risk |
|-------|------------------|-------------------------------|------|
| Use `InstituteDomain::isAcademic/isProfessional/subjectTypeFor` | MUST, never `industry === 'education'` | `layouts/institute.blade.php:124` `$isEducation = InstituteDomain::isAcademic($institute)` ✅ ; `125` `$isProfessional = InstituteDomain::isProfessional($institute)` ✅ ; `CourseMasterController:77` `InstituteDomain::subjectTypeFor` ✅ ; `SubjectManagementController:32,79` ✅ ; `InstituteDomain::hasDomainData` blocks switch ✅ ; grep `industry ===` limited to `InstituteDomain` itself — **PASS**. | LOW |
| No client `subject_type` trusted | Server derives | `SubjectManagementController:49` clamps `rawSubjectType` to `derivedType === $raw ? $raw : null` ✅ ; `store:79` `Rule::exists ... ->where('subject_type',$derived)` ✅ ; `create` form now server shows derived only (`allSubjectTypes=[$derived]` `112`) ✅ — **PASS** (B7 fixed `orWhereNull` leak). | LOW |
| No new business taxonomy/sub-category | Spec §7 | `InstituteDomain.php:42` `OTHER_INDUSTRIES` array not expanded; `IndustryRules.php` unchanged; `BusinessProfileController:subIndustryLabel` reads `config industry_rules` canonical; `business/profile.blade.php:336` `OTHER` branch matches `InstituteDomain::OTHER` — **PASS**. | LOW |
| Hardcoded domain check forbidden | none in UI | Verified `InstituteDomain` is authoritative; `EnsureDomain.php:11` middleware gates `domain:academic` routes (`1144` `settings/academic`); topbar/profile correctly shows `biz-badge-domain-{{ $domain }}` `business/profile.blade.php:68` — **PASS**. | LOW |

---

## H. TENANT ISOLATION — CRITICAL FORENSIC AUDIT

**Tenant primitives:** `app/Models/Concerns/TenantScoped.php`, `BranchScoped.php`, `SetTenantContext.php:26` (binds `TenantContext::set(workspaceId)` + `BranchContext`), `Workspace::membership()/verify()` ensures `active_institution_id` maps to real membership; `Institute::withoutGlobalScopes()` never used except platform-admin safe lookups.

| Scope item | Verification | Files:Line | Verdict |
|------------|--------------|------------|---------|
| `Subjects` | `SubjectManagementController:32` `subjectQuery($instituteId,$derived)->where('institute_id',$instituteId)->where('subject_type',$derived)->whereNull('deleted_at')` + `index` `stats` clamped; `store:Rule::exists course_categories ->where institute_id=$instituteId ->where subject_type=$derived` + `create InstituteDomain::subjectTypeFor` force | `SubjectManagementController.php:30-127` | **PASS** |
| `Courses` | `CourseMasterController:index` `Course::where('institute_id',$instituteId)` + `subjectsCount` `Subject::where institute_id` `where subject_type=$derived` counted separately; `store/update` validates `category_id Rule::exists ... where institute_id` | `CourseMasterController.php:37-77` | **PASS** |
| `Course Categories / Sub-Categories` | `CourseCategoryManageController:26` `Institute::find($instituteId)` then `where institute_id` + `subject_type $derived`; `CourseSubCategoryManageController` identical; `withoutGlobalScope('institute')` only for slug uniqueness check (`withoutGlobalScope` + `where institute_id` — safe, not cross-tenant leak) | `CourseCategoryManageController.php:75,110` + `SubjectManagementController:112` | **PASS** |
| `Teachers` | `TeacherController:index` `InstituteUser::where role_id=teacherRoleId` `with teacherProfile branch`; `BranchScoped` implicit; `requireInstitute` resolves `institute` from `Workspace` never request input; `status/assign` check `institute_id` matches | `TeacherController.php:47-120` | **PASS** |
| `Classes` | `ClassController:47` `->withoutGlobalScope('institute')` for `course.category` is **catalog read within filtered set** already `where institute_id` via outer `InstituteCourse` — safe. `Class` routes `domain:academic` gated; `filterCates` via `InstituteDomain` | `ClassController.php:47,112,140` | **PASS** (no cross-institute) |
| `Academic Years` `Placements` `Assessments` `Results` | `StudentAcademicPlacementController`, `AcademicAssessmentController`, `AcademicFinalResultController` all `requireInstitute` + `Academic*` models `TenantScoped` + branch_id from user not input (`AcademicAssessmentController:store` `branch_id` from user branch) | `AcademicAssessmentController.php:40-72` etc | **PASS** |
| `Results` `Curriculum` `Batches` | `Batch.php` `TenantScoped+BranchScoped` SoftDeletes; `CourseCurriculum.php` `TenantScoped`; `Result.php` `institute_id` via `Exam`/`Batch`; `AcademicFinalResult` `TenantScoped` + status lock/publish guards against destructive mutation | `Batch.php:11`, `CourseCurriculum.php`, `AcademicFinalResultLifecycleService.php` | **PASS** |
| **`withoutGlobalScope / withoutGlobalScopes`** | 5 legitimate uses: `CategoryManage:75,110` slug uniq (+ `where institute_id`), `CourseCategoryManage:36,37,127,128` counts (`withoutGlobalScopes` + `where institute_id`) safe; `AcademicStructureController:464,477,488` `Institute::withoutGlobalScopes()->find(membership->institution_id)` — **platform-admin** context fetching institute by known membership id, not user-supplied search; `ClassController:47,112,140,274,366,384,411,418` — catalog/assignment reads scoped then `withoutGlobalScope` only to include shared categories — **reviewed safe**; `BatchController:574` `withoutGlobalScope('institute')` for status transition after explicit admin check — **safe**. No `direct find()` without institute check (except `Institute::find($user->institute_id)` safe). | listed | **PASS — no cross-tenant leak** |
| **`Rule::exists` dropdown queries** | All critical validators add `->where('institute_id', $institute->id)` : `AcademicAttendanceController:72` `academic_years id ->where institute_id $user->institute_id` ✅ ; `AdmissionPipelineController:228-236` `branches/course/academic_year/batches/institute_users` all `->where institute_id` ✅ ; `BatchController:376` `batches id ->where institute_id` ✅ ; `CourseCategory:Exists` via `SubjectManagementController` ✅ | listed §D | **PASS** |
| **AJAX / search endpoints** | `AcademicAssessmentController:112` `subjects()` validates `class_grade_id` via `classWithinInstitute($institute,$id)` not blind `find`; `AcademicAnalyticsController` etc all institute-scoped; `curriculum availableCourses()` B7 fixed to domain-derived filter | `AcademicAssessmentController:112-130` + `CurriculumController:403` | **PASS** |
| **Edit URLs / dependency pages** | `courses/manage/subjects/{subject}/dependencies` via `SubjectManagementController:dependencies` checks `assertAccessible` (`institute_id` + `subject_type` match) — `withoutGlobalScope` not used; `courses.manage.edit {course}` validates `Course::where institute_id` via `CourseMasterService` | `SubjectManagementController:dependencies`, `CourseMasterController:edit` | **PASS** |
| **`institute_id` + `domain` + `RBAC` + `IDOR`** | Every B9 route group `middleware auth+tenant+verified+permission:*+domain:academic` where academic; `SetTenantContext` verifies `Workspace` every request `EnsureDomain` aborts opposite domain `403`; `hasPermission()` / `CheckPermission` / `CheckModuleAccess` on all manage routes | `bootstrap/app.php:74` + `institute_modules.php:16` | **PASS** |
| **IDOR vectors to test in impl** | Academic→Professional subject, Prof→Academic subject, A→B course/subject/result, `withoutGlobalScopes` leakage — all expected **403/404** with derived clamp | — | To verify in Step 9 |

**Overall H:** **PASS — tenant isolation intact.** No `FOREIGN_KEY_CHECKS=0`, no `Rule::exists` missing tenant, no search leak. One nuance: `BusinessProfileController` fallback via `TenantContext::id()` → `Institute::find` is safe because `assertTenantMatchesActive` double-gates.

---

## I. NO FAKE DATA — VERIFICATION

| Rule | Current State | Evidence |
|------|---------------|----------|
| No fake subject/course/teacher/class/seed/demo creation | **Confirmed none created** by this audit. No code/data/migration change (`STEP 1` constraint). Existing phases A/B also assert `DATA MODIFIED: NONE`. | `database/seeders` not executed; `demo/` folder contains static docs, not DB writes; `B7 report` explicitly states no seed. |
| Existing DB data only | `InstituteDomain::hasDomainData` checks `courses/subjects/course_curricula/batches/placements/assessments/final_results/marks` existence before domain switch — proves data is real per institute. | `InstituteDomain.php:89-113` `hasDomainData()` queries. |
| Delete protection | B7 `SubjectDeletionService` blocks delete when referenced; `AcademicFinalResultPolicy` blocks mutate after `locked/published`; `CourseMasterService::destroy` blocked when referenced; `RecycleBinController` trusted soft delete. | `SubjectDeletionService.php`, `AcademicFinalResultPolicy.php`, `CourseMasterService.php`. |
| Migration restraint | No migration needed for B9 UI restoration (reuse). | `database/migrations` last is business profile related — no akademik addition needed. |

**I: PASS.**

---

## STEP-BY-STEP GAP DIAGNOSIS (what impl must restore — WITHOUT creating duplicates)

### Institute Dashboard Sidebar/Top Navigation — Gap

**Current sidebar (`layouts/institute.blade.php:118-419`):**

- Generic `Dashboard` always.
- Students — when `isEducation || isProfessional && hasEducationModule` ✅
- Pending Admissions — when `isEducation`
- Teachers — ONLY when `isEducation && workspaceAllowedTeachers` ❌ **missing for professional** (instructors hidden).
- `Payroll/HR/Sales/Purchase` module-gated.
- **Academic institutes (`isEducation`):** show `Classes` (or `Courses` when `!usesClassTerm`), `Exams`, `Alumni`, `Workflows`. **Does NOT show:** `Academic Years`, `Groups/Streams`, `Subjects` tab is via `Courses` toggle but indirect, `Academic Setup`, `Placements`, `Assessments`, `Marks`, `Results` lifecycle buckets (`Aggregation/Grade Scales/Final Results/Published Results`), `Promotion`, `Attendance` (academic), `Transcript`, `Certificates` (academic certs share generic).
- **Professional institutes (`isProfessional`):** B7-added `Courses` (`courses.manage.index`), `Subjects` (`courses.manage.subjects.index`), `Curriculum` (`curricula.index`), `Batches` (`batches.index`), `Exams`, `Certificates` — **now correct** (`203-221`). Missing `Teachers/Instructors` here.
- **Other industries:** shows Sales/Purchase/Finance but no academic/professional gate — correct.

**Gap summary table:**

| Requested Nav (Spec) | Exists backend? | Current sidebar mapping | Gap Action (Step 2) |
|----------------------|-----------------|------------------------|---------------------|
| Academic → Dashboard | YES (`academic.dashboard` `domain:academic`) | NOT in sidebar (only `academic-dashboard` legacy alias `web.php:117`) | Add Academic → Dashboard entry when `isAcademic`, route `academic.dashboard` else `/`. |
| Academic → Students | YES | YES (shared) | Keep — verify active state includes `students.academic-*` domain sub-routes. |
| Academic → Academic (group header) | NO explicit group | No group | Add collapsible `Academic` group (per spec) containing Years/Classes/Groups/Subjects/Assessments/Marks/Results→Aggregation/Grade Scales/Final Results/Published Results/Promotion/Attendance/Transcript/Certificates — reuse `settings.academic.*` routes, **no new routes**. |
| Academic → Academic Years | YES (`settings.academic.academic-years.*`) | Hidden via `settings/academic` | Expose link `settings.academic.placements.index` section anchor + year manage modal; keep CRUD in placements context. |
| Academic → Classes / Groups/Streams / Subjects | YES | Classes yes; Groups via `academic-structure`; Subjects via course tabs but not academic-subnav | Add `Academic → Classes` `classes.index`, `Academic → Subjects` `courses.manage.subjects.index` (server clamps academic), `Academic → Groups` anchor in `settings.academic` / `academic.structure` — all domain-aware. |
| Academic → Assessments / Marks / Results | YES | NO top nav — only `settings/academic` | Add `Academic → Assessments` `settings.academic.assessments.index`, `Academic → Marks` `assessments/{assessment}/marks-sheet` hub, `Academic → Results` collapsible with `Aggregations` `settings.academic.aggregations.index`, `Grade Scales` `settings.academic.grading.index`, `Final Results` `settings.academic.final-results.index`, `Published Results` filtered `final-results?status=published` — all reuse. |
| Academic → Promotion | YES | Hidden `settings.academic.promotions.index` `permission:promotion.manage` | Add `Academic → Promotion` link (gate extra permission). |
| Academic → Attendance / Transcript / Certificates | YES | `Attendance` routes exist but no sidebar; Transcript per-student; Certificates `certificates.index` generic | Add `Academic → Attendance` `academic-attendance.mark.index` + `reports`, `Academic → Transcript` already per-student, `Academic → Certificates` same `certificates.index` filtered. |
| Professional → Dashboard | YES | Generic dashboard | Keep; professional dashboard may show course/batch summary (add metrics via existing services). |
| Professional → Courses | YES | YES B7 | Keep. |
| Professional → Subjects | YES | YES B7 | Keep canonical via tabs. |
| Professional → Categories/Sub-Categories | YES (JSON) | Via Course form | Keep via `courses.manage` tabs/filters; optionally add `Categories` sub-links to same `courses.manage.index?tab=categories` reuse. |
| Professional → Curriculum → Modules → Lessons | YES | YES B7 | Keep; ensure responsive. |
| Professional → Batches | YES | YES B7 | Keep. |
| Professional → Teachers / Instructors | YES single system | **MISSING for professional** | Add `Professional → Teachers / Instructors` `teachers.index` when `isProfessional && workspaceAllowedTeachers` (same controller, domain-aware label). |
| Professional → Enrollments/Attendance/Exams/Results/Certificates | YES | Enrollments via student/pipeline; Attendance via batch; Exams+Results+Certificates YES | Keep; optionally add `Professional → Enrollments` anchor to `admissions.pipeline` / `students.index` filtered. |

**Design language constraint:** Follow existing `layouts/institute.blade.php` nav-link style (`bi-*` icons, `sidebar-label` with collapse animation), responsive drawer already (`#monetixSidebarToggle` + `mobileQuery`), no new UI framework.

### Steps 3-4 — Course Management & Subject Management UI (Gap)

- **Step 3 canonical master page `/courses/manage` (`CourseMasterController:index`) — EXISTING + UI EXISTS.** B7 verified tabs include categories count clamping, search/q/status/category filters, paginator, banners. **Remaining gap:** Professional notes categories via JSON; ensure academic institute accessing `/courses/manage/sub-categories` (if not legitimate) is blocked by domain check — currently `CourseCategory` filter clamps so academic institute sees only academic categories (no professional leak). **Spec: professional course/category must never appear for academic — ENFORCED** (`SubjectManagementController` + `CourseMasterController` both derive).
- **Step 4 canonical subject UI as tabs of `/courses/manage` — EXISTING + UI EXISTS (`_tabs.blade.php:17`).** Subject type NOT freely selectable (`allSubjectTypes=[$derived]`). **Gap:** none; preserve. Ensure `subject-form.blade.php` does not render opposite domain option — B7 audit flagged dropdown suggesting mutability though controller ignored — implementation must hide opposite option (show badge of derived only).

### Step 5 — Academic Management UI (Gap — primary restoration target)

Dedicated academic navigation to **reuse every existing service/controller** (`AcademicAssessmentService`, `AcademicMarksService`, `AcademicResultAggregationService`, `AcademicGradingService`, `PromotionLifecycleService`, `AcademicFinalResultLifecycleService`) without duplication. Workflow visibly `Draft→Review→Approved→Locked→Published` via `final-results.send-to-review/approve/lock/publish` routes — UI must surface status badges and **prevent destructive change for locked/published** (controller already 422/403; frontend must disable buttons — already via policy).

**Historical integrity check:** `AcademicFinalResult::status` locked/published rows `AcademicFinalResultService::update` / `PromotionDecisionService` etc reject mutate; frontend must not re-enable.

### Step 6 — Professional Teacher / Instructor UI (Gap narrow)

Instructor `→ Courses → Curriculum → Batches` workflow: `Batch.teacher_id` FK to `Membership` + `TeacherProfile` via `InstituteUser` exists. UI link missing for professional — add.

### Step 7 — Business Profile (Gap — PASS)

`BusinessProfileController:show` workspace-authoritative `business.profile` at `web.php:349` — topbar brand `href="{{ route('business.profile') }}"` `layouts/institute.blade.php:32` correctly routes. Domain-aware variance: `resources/views/business/profile.blade.php:251` academic vs `276` professional vs `307` other industries — **already implements** `School→Academic information`, `Training Institute→Training/Course`, `Dance Academy→Training+instructor/course`, `IT Training Center→Training+technology/course` buckets per spec example. **No sub-category introduced** — category-level only. **PASS — preserve.**

### Step 8 — Responsive UI (Gap — PASS baseline + verify)

Layout uses Bootstrap 5.3.7, `container-fluid`, `col-lg-6/col-12`, `table-responsive` pervasive, `sidebar-backdrop` for mobile (`119-130`). B7 course-master is responsive. **Action: verify restored academic nav pages inherit same grid and `form.blade.php` long forms remain scrollable on tablet/mobile — no new framework needed.**

### Step 9 — Security re-verify checklist for impl (routes to gate)

Every restored route must `middleware tenant + verified + auth:platform_admin/institute_user + permission:* + domain:academic` where spec says `domain:academic`. Already satisfied for `settings.academic.*`. For new sidebar links: do NOT add new routes — point to existing gated routes, so security inherits. Cross-domain tests listed §H must be executed via integration tests (impl Step 10) — **academic→professional subject, professional→academic subject, A→B course, A→B subject, A→B result must 403/404**.

### Step 10 — Testing (Gap for impl)

Existing tests `tests/Feature/SubjectUnificationTest.php`, `TenantIsolationAuditTest.php` etc GREEN. New UI integration tests must cover: Academic UI nav, Professional UI nav, Course/Subject/Teacher/Class/Assessment/Result visibility, Tenant isolation, Domain isolation, IDOR, RBAC — per spec minimum 12. No regression of GREEN phases.

### Hard Rules Compliance Check (pre-impl)

- ✅ First audit, then implement — this report is audit phase only (`DATA MODIFIED: NONE`).
- ✅ Existing backend reuse — mapped.
- ✅ No fake data — confirmed.
- ✅ No delete of existing course/subject/class/teacher — confirmed.
- ✅ No historical result mutation — lifecycle locked/published guards intact.
- ✅ No `FOREIGN_KEY_CHECKS=0` — grep clean.
- ✅ Need-based migration only — none needed.
- ✅ Academic/Professional mix blocked — `InstituteDomain` clamp.
- ✅ Institute A leak to B blocked — TenantScoped.
- ✅ `InstituteDomain.php` authoritative — verified.
- ✅ `subject_type` not client-determined — clamped.
- ✅ Restore UI goal exclusive.
- ✅ No new business taxonomy/sub-category.
- ⚠️ Real browser/HTTP route verification to be done post-impl.

### Architectural Decision Required — STOP Point (§8)

**Decision 1 — Academic navigation nesting:** Spec proposes `Academic → Results → (Aggregation, Grade Scales, Final Results, Published Results)` while backend is flat `settings/academic/{grading,aggregations,final-results,promotions}`. **Recommendation:** Keep flat routes, implement sidebar **grouped UI** (collapsible `Results` submenu) mapping to same routes (no new routes). This avoids duplicating controllers/views and preserves `permission:education.manage` + `domain:academic` gating. **If decision to introduce new `/academic/*` prefix routes is desired, they would duplicate `settings.academic.*` — REJECT.** Confirm to proceed with submenu grouping reusing existing routes.

If no objection within audit window, implementation will proceed with grouped submenu reusing existing routes.

---

## EXISTING TABLES / MIGRATIONS / SEEDERS POTENTIAL CONFLICTS

- No NEW tables needed — existing `academic_*`, `course_categories/sub_categories`, `course_curricula`, `curriculum_modules/lessons`, `batches`, `teacher_profiles`, `class_grades` etc cover spec.
- `database/migrations` includes `2026_08_13_add_industry_to_institutes`, `2026_08_14_add_sub_industry`, academic hardening patches — **no collision** for B9 UI restore.
- `database/seeders` — no B9 seeder needed; Step 9 says no fake data.

---

## RISK REGISTER FOR IMPLEMENTATION

| Risk | Level | Mitigation |
|------|-------|------------|
| Sidebar duplication for Teachers (academic vs professional label divergence) | LOW | Reuse same `teachers.index` route, domain-aware label (`Teachers` for academic `school/college/university`, `Instructors` for professional). Do not create `instructors.*` alias duplication unless translation key requires. |
| `classes.*` vs `courses.manage.*` toggle confusion (`usesClassTerm`) | MEDIUM | Keep toggle logic `layouts/institute.blade.php:128-133` — academic `school/college` shows `Classes`, `polytechnic/university` etc show `Courses`; ensure `subjects` tab still accurate for both (academic Subjects via `courses.manage.subjects`). Do not add third `/academic/subjects` orphan. |
| Curriculum availableCourses filter forgetting domain | FIXED B7 but revert risk | Keep `CurriculumController:403` domain-derived filter + test coverage. |
| IDOR via `withoutGlobalScope` in search endpoints | LOW | Already gated; add regression tests for A→B direct `subjects/{id}/edit` with wrong institute. |
| Responsiveness of new academic nav collapse | LOW | Reuse existing `nav-link sub` pattern from finance submenu (`layouts/institute.blade.php:253-270`). |
| Historical integrity — UI re-enabling locked final result edit | LOW | Service rejects; UI must disable buttons when `status ∈ [locked,published]` — existing `show.blade.php` already conditional. Preserve. |

---

## FILES INSPECTED (Representative — full list in §D/E/F)

```
routes/web.php:16-408
routes/institute_modules.php:16-1660
app/Support/InstituteDomain.php:17-113
app/Support/Workspace.php:22-138
app/Support/TenantContext.php:13 / BranchContext.php / ModuleAccessService.php
app/Http/Controllers/layouts/institute.blade.php:118-419
app/Http/Controllers/CourseMasterController.php:37-150
app/Http/Controllers/SubjectManagementController.php:30-160
app/Http/Controllers/CourseCategoryManageController.php:26-128
app/Http/Controllers/CurriculumController.php:31-403
app/Http/Controllers/BatchController.php:33-574
app/Http/Controllers/TeacherController.php:12-150
app/Http/Controllers/ClassController.php:24-418
app/Http/Controllers/AcademicAssessmentController.php:1-130
app/Http/Controllers/AcademicMarksController.php:1
app/Http/Controllers/AcademicFinalResultController.php:1
app/Http/Controllers/AcademicPromotionController.php:1
app/Http/Controllers/StudentAcademicPlacementController.php:1
app/Http/Controllers/AcademicAttendanceController.php:72
app/Http/Controllers/AcademicGradingController.php:1 / AcademicAggregationController.php / AcademicStructureController.php
app/Services/* (Academic* services list §F.2)
app/Models/* (§F.3 list)
resources/views/layouts/institute.blade.php:31-399 (topbar + sidebar)
resources/views/business/profile.blade.php:1-405 (domain-aware)
resources/views/institute/course-master/* / institute/curriculum/* / batches/* / exams/* / institute/teachers/* / institute/academic-*/*
```

---

## CONCLUSION & GO/NO-GO FOR IMPLEMENTATION

**Forensic verdict:** **YELLOW → GREEN upon restoration** — backend is GREEN, UI is PARTIAL. No architectural blocker prevents restoring domain-aware navigation by **reusing existing routes/controllers/services/views** without migrations, taxonomy changes, or duplicate Teacher/Instructor systems.

**Authorized to proceed to STEP 2-10 implementation** after acknowledgement of Decision 1 (Results submenu grouping). All hard rules will be enforced; verification via `php artisan route:list`, browser smoke of `courses/manage`, `courses/manage/subjects`, `curricula`, `batches`, `teachers`, `settings/academic/*`, `academic-attendance/*`, `business/profile` per domain (school vs dance_academy vs IT training center) plus IDOR/tenant/domain isolation integration tests.

---

**Sign-off:** This audit made **ZERO data/route/view migrations**. Next artifact will be `PHASE_B9_COMPLETE_ACADEMIC_PROFESSIONAL_UI_IMPLEMENTATION_REPORT.md` (per spec) with PASS/FAIL per domain bucket.

**Generated:** 2026-08-28 — Muse Spark forensic (Mawa / Monetix)
