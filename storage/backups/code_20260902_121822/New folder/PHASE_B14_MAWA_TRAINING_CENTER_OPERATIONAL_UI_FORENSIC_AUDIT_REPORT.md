# PHASE B14 — MAWA TRAINING CENTER OPERATIONAL UI FORENSIC AUDIT REPORT

**Phase:** B14 — MAWA Training Center Operational UI Forensic Audit (AUDIT ONLY)
**Scope:** READ-ONLY FORENSIC AUDIT — No code / data / migration / route / view / config modified
**Date:** 2026-08-28
**Target Business:** **MAWA Academy** — `industry=training_center` `sub_industry=training_institute` → `domain=professional` (per `InstituteDomain::PROFESSIONAL_TYPES`) — DO NOT CONVERT TO ACADEMIC
**Auditor:** Muse Spark (forensic audit mode — live `Read` + `php artisan route:list --path` + `config/industry_rules` + `Workspace/TenantContext/EnsureDomain` + `TenantScoped/BranchScoped` inspected 2026-08-28)
**Workspace Root:** `C:\xampp\htdocs\monetix`
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` `fromInstitute()/fromKeys()/isAcademic()/isProfessional()/subjectTypeFor()` + `config/industry_rules.php:20 global` + `routes/web.php:120* + institute_modules.php:1144`
**Deliverable Constraint:** STOP after report — DO NOT START IMPLEMENTATION until reviewed

---

## 1. EXECUTIVE SUMMARY

| Dimension | Finding | Verdict |
|-----------|---------|---------|
| **MAWA domain state** | MAWA `training_center/training_institute` correctly resolves `InstituteDomain::PROFESSIONAL` `professional` via `training_center + [training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center]` `InstituteDomain:31`. `IndustryRules global.training_center 52-70` includes MAWA's exact tuple. Conversion to academic would require `education/school` etc. and would be blocked by `Institute::booted hasDomainData` if courses/batches exist `Institute:30-47` — DO NOT CONVERT. | **CORRECT — KEEP PROFESSIONAL** |
| **Current Professional UI** | Sidebar `layouts/institute.blade.php:284-304 isProfessional && workspaceAllowedEducation` exposes **6 canonical items** `Courses 286 courses.manage.index:928` `Subjects 289 courses.manage.subjects:952` `Curriculum 292 curricula.*:900` `Batches 295 batches.*:165` `Exams 298 exams.*:175` `Certificates 301 certificates.index:190` + shared `Teachers/Trainers 150 isEducation\|\|isProfessional` label `Trainers`. No dedicated `Enrollment / Attendance / Assessments-Marks / Results` top navigation; those exist as `batches.show` tab / `exams.marks` / `students.enroll` but not in `Training` grouping. | **EXISTS BUT INCOMPLETE — 6 visible, 4 operational hidden** |
| **Reusability verdict** | Of 35 modules inspected, **10 MUST/SHOULD SHOW** for professional (reuse existing `Student/Teacher/Course/Subject/Curriculum/Batch/Enrollment/Attendance/Exam-Assessment/Result-Certificate`), **12 MUST REMAIN ACADEMIC-ONLY** (`Academic Years, ClassGrade, Groups/Streams, AcademicPlacement, Promotion, FinalResult Transcript, Aggregation/Grading preview with bonus`); **6 are correctly degree-gated `domain:academic`**. No new taxonomy/table needed. | **REUSE EXISTING — 0 NEW SYSTEMS** |
| **Tenant/IDOR safety** | All candidate training modules are already `TenantScoped/BranchScoped` + explicit `where institute_id` + `InstituteDomain::subjectTypeFor` clamp + `Rule::exists->where institute_id` + `EnsureDomain domain:academic` blocks academic-only routes for MAWA (403) | **GREEN** |
| **Workflow gap** | MAWA can `Course → Batch → Enroll → Attendance (batch.show) → Exam → Certificate` but **cannot discover** `Academic Assessment/Marks/Result` (academic-only `domain:academic`) for training; must reuse **training ExamController** path `exams.marks` for Marks/Results instead of `Academic*` chain — verified as CANONICAL for professional | **GAP = NAVIGATION, NOT BACKEND** |
| **Overall** | Substantial training backend exists and is safe to surface; academic-only concepts must stay hidden for MAWA; 4 operational items need navigation surfacing, not new architecture. | **AUDIT READY FOR GATE 2 IMPLEMENTATION AFTER APPROVAL** |

---

## 2. CURRENT MAWA DOMAIN STATE

| Attribute | Persisted Value | Resolver | Expected |
|-----------|-----------------|----------|----------|
| `institutes.industry` | `training_center` | `InstituteDomain::fromKeys('training_center','training_institute') === PROFESSIONAL:70` `config industry_rules global.training_center 52-70` contains `training_institute→Training Institute` | `training_center` |
| `institutes.sub_industry` | `training_institute` | `PROFESSIONAL_TYPES 31` includes `training_institute` (first element) — no alias needed | `training_institute` |
| `IndustryRules Bangladesh.training_center 85` | Same 5 core + legacy aliases `institution/external` | Normalized via `normalizeSubIndustry:128` `institution → training_institute` | Consistent |
| `InstituteDomain::isAcademic(MAWA)` | `false` | `fromInstitute 50 → fromKeys industry=training_center not education → OTHER/professional branch` | `false` |
| `InstituteDomain::isProfessional(MAWA)` | `true` | `industry===training_center && in_array(sub, PROFESSIONAL_TYPES)` `70` | `true` |
| `subjectTypeFor(MAWA)` | `professional` | `subjectTypeFor 108 → PROFESSIONAL → professional` — used by `CourseMasterController:62` + `SubjectManagementController:79` `where subject_type=professional` | `professional` |
| `Institute::booted domain immutability 30` | Throws if `industry/sub_industry` dirty changes domain `oldDomain !== newDomain` && `hasDomainData(institute_id)` true (courses/subjects/curricula/batches/placements/assessments) | MAWA with existing courses/batches cannot be silently converted to `education/school` → must create new institute | **Blocking — DO NOT ATTEMPT** |

**Verdict:** MAWA is correctly **professional** — no migration needed — taxonomy is canonical per `global 52-70` and BD `85` entries.

---

## 3. CURRENT NAVIGATION STATE (`layouts/institute.blade.php:118-501`)

| Block | Line | Gate | Items Visible For MAWA (`isProfessional`) | Items Hidden (academic) |
|-------|------|------|-------------------------------------------|-------------------------|
| Topbar brand | `32` | `route('business.profile')` `Workspace authoritative` | `business.profile 349` topbar brand `MAWA Academy ✓` | — |
| Dashboard | `120` | `route('dashboard') tenant` | `dashboard 120` always | — |
| Shared Academic/Professional | `136 students 150 trainers 150` | `isEducation\|\|isProfessional && hasEducationModule 136` + `workspaceAllowedTeachers 150` | `Students 136` + `Teachers label Trainers when isProfessional && !isEducation 152` | Correct shared |
| Legacy `Classes/Courses` toggle `173-179` | `isEducation && hasEducationModule 173` | **HIDDEN for MAWA** (`isEducation false`) — correct | `Classes:224 / Courses:174` hidden |
| **Academic collapsible `Academic`** `204-283` | `isEducation && workspaceAllowedEducation 204` + `$academicOpen 206 routeIs academic.dashboard/.../settings.academic.*` | **HIDDEN for MAWA** (`isEducation false`) → all 18 academic links hidden (`Academic Settings, Years, Groups, Assessments, Marks, Results→Aggregations/Grade Scales/Final/Published, Promotions, Attendance, Analytics, Transcript, Certificates` inside) | Correct — must remain hidden (see §17) |
| **Professional `Training`** `285-304` | `isProfessional && workspaceAllowedEducation 285` | **6 visible:** `Courses 286 courses.manage.index 928`, `Subjects 289 courses.manage.subjects 952 + _tabs`, `Curriculum 292 curricula.* 900`, `Batches 295 batches.* 165/989`, `Exams 298 exams.* 175`, `Certificates 301 certificates.index 190` | `Enrollment/Attendance/Assessments-Marks/Results` not yet surfaced as top Training items |
| Hybrid `!usesClassTerm` `306-316` | `isEducation && !usesClassTerm 306` | **HIDDEN for MAWA** (not education) — `Curriculum/Batches/Certificates` duplicate from training not rendered for professional | — |
| Generic `Finance/Accounting/Hr/Crm` `317-429` | `workspaceAllowed*` | `Finance 328, Accounting 331, Hr 155, Crm 317` visible per module entitlements | — |

**Dashboard `_tabs.blade.php:1-39`:** `$showAcademic = InstituteDomain::isAcademic(institute):8` — for MAWA `showAcademic false` → only `Dashboard` tab shown, `Academic Dashboard 29` + `Education Analytics 34` hidden — correct.

---

## 4. MODULE INVENTORY (inspected 2026-08-28 — file:line current behavior)

| # | Module | Controller/Service (file:line) | Route (file:line) | View (file:line) | Tenant | Branch | Permission | Domain |
|---|--------|--------------------------------|-------------------|------------------|--------|--------|------------|--------|
| 1 | Dashboard | `DashboardController:__invoke` `routes/web.php:116 dashboard` | `/ tenant` | `layouts/institute 120` | tenant | — | `auth` | none |
| 2 | Business Profile | `BusinessProfileController:16 show` `Workspace authoritative` | `GET business/profile 349 tenant+verified` | `business/profile:405 academicData:251 vs professionalData:276` | `assertTenantMatchesActive:140` | — | `tenant+verified` | display-only |
| 3 | Students / Trainees | `StudentController:140 index/create/store/show/enroll/edit/update/photo/destroy/academicHistory 1089 domain:academic` | `GET students 139 permission:students.view tenant` + `POST {student}/enroll 144` | `students/index/show/form/_tabs academic_transcript` | `TenantScoped Student::where institute_id` | N/A | `students.view/manage` | shared + `students.academic-* 1089 domain:academic` separate |
| 4 | Teachers / Trainers | `TeacherController:12 index/create/store/show/edit/update/status/assign 1076` `TeacherProfile/TeacherAcademicAssignment/Batch.teacher->Membership 54` | `GET teachers 355/1076 tenant` `POST assign 1083` | `institute/teachers/index/form/show` | `InstituteUser where role=teacher + BranchScoped` | branch | `tenant` | shared — label domain-aware `layout:152` |
| 5 | Courses | `CourseMasterController:30 index/create/store/edit/update/destroy` `where institute_id + InstituteDomain::subjectTypeFor` `62` | `GET courses/manage 928 permission:courses.view tenant` canonical | `institute/course-master/index:19k + form:63k` | `Course where institute_id` | — | `courses.view/manage` | derived via `category subject_type` |
| 6 | Subjects | `SubjectManagementController:30 canonical index/create/store/edit/update/restore/dependencies` `where institute_id AND subject_type=derived 79` | `GET courses/manage/subjects 952 canonical` | `institute/course-master/subjects:23k _tabs:17 subject-form 6k` | same | — | `courses.view/manage` | server-clamped `professional` for MAWA |
| 7 | Categories / Sub-Categories | `CourseCategoryManageController:26` `CourseSubCategoryManageController:17` `Rule::exists->where institute_id & subject_type derived` | `courses.manage.categories.* 938 + sub-categories 945` | JSON modal `course-master/form` | `TenantScoped CourseCategory where institute_id` | — | `courses.view/manage` | derived |
| 8 | Curriculum | `CurriculumController:31 index/create/store/show/edit/update/activate/destroy storeModule/updateModule/destroyModule/storeLesson 403` `CourseCurriculum TenantScoped availableCourses:397 domain-aware` | `curricula.* 900 curricula.{curriculum}/modules 910 lessons 914` `permission:curriculum.view/manage tenant` | `institute/curriculum/index/form/show` | `TenantScoped CourseCurriculum` | — | `curriculum.view/manage` | hybrid `isProfessional` |
| 9 | Curriculum Modules/Lessons | Same controller | same | same show | same | — | same | — |
| 10 | Batches | `BatchController:33 index/show/store/update/destroy/archive/unarchive/changeStatus 56 transferStudent/removeStudent` `TenantScoped+BranchScoped` | `batches.* web:165 + 989 status/transfer/remove-student` | `batches/index/show` tab `Exams/Attendance` `show` | `TenantScoped+BranchScoped Batch:60` | branch | `batches.view/manage` | via course category `professional` |
| 11 | Enrollment | `StudentEnrollment via StudentController:enroll:144` + `BatchController:transferStudent` `Admissions pipeline 1004` | `students/{student}/enroll 144 + admissions/pipeline.*` inside `batches.show enroll` | `students/show enroll actions` `batches/show enrolled tab` | via `StudentEnrollment institute_id` | branch | `students.manage` | none |
| 12 | Attendance | `Attendance model Batch attendance` `HrAttendance* separate` `Batch.show attendance tab` `AcademicAttendanceController 72 domain:academic separately NOT for training` | `batches.show attendance tab` (no dedicated `academic-attendance.mark` for MAWA) | `batches/show attendance` | `Batch Attendance where institute_id` | branch | `batches.view` | training = batch, academic = `academic-attendance 161 domain:academic` (must remain academic) |
| 13 | Classes | `ClassController:24 index/subjects/batches/archive` `AcademicStructureService` `domain:academic 979` | `classes.* 979 domain:academic permission:courses.view tenant` | `classes/index/archive/_tabs` | `TenantScoped InstituteCourse` | — | `courses.view` | `domain:academic` |
| 14 | Academic Years | `StudentAcademicPlacementController storeAcademicYear 279` `AcademicYear institute_id` | `POST academic-years 1247 PUT/DELETE academic-years/{year} 1248 tenant` NO `GET` index — section inside `placements.index:166` `id=academic-years` | `academic-placements/index:166 years manager` | `AcademicYear where institute_id` | — | `education.manage+domain:academic` | **academic-only** |
| 15 | Placements (Academic) | `StudentAcademicPlacementController:54 index/create/store/show/edit/update/destroy/subjects` `StudentAcademicPlacement inScope + academicYearHasHistory 480` | `placements.* 1237 tenant education.manage+domain:academic` | `academic-placements/index/show/form` `id=academic-years` shared | `inScope + AcademicYear institute_id` | branch via `Student` | `education.manage+domain:academic` | academic-only |
| 16 | Assessments (Academic) | `AcademicAssessmentController:45 index/create/store/show/edit/update/destroy/lock 1190/unlock/subjects 99 subjectForSelection` `AcademicAssessment TenantScoped` | `settings.academic.assessments.* 1182 tenant education.manage+domain:academic 1182` | `academic-assessments/index/form/show/marks/marks-sheet` | `TenantScoped` | branch `actingBranch 316` | `education.manage+domain:academic` | **academic-only** |
| 17 | Marks (Academic) | `AcademicMarksController:37 index/store:52 sheet:81 export:99` `grid/sheet eligibility + lifecycle assertAssessmentEditable 59` | `POST assessments/{assessment}/marks:1195 + GET marks-sheet:1196 export:1197 domain:academic` NO `GET marks/{subject}` (missing) | `marks.blade + marks-sheet` | scoped assessment | branch | `education.manage+domain:academic` | academic-only (nested) |
| 18 | Aggregation / Grade Scales | `AcademicAggregationController:1 , AcademicGradingController:52` `AcademicResultAggregationService / GradeScale ladder global→country→system→level→institute override` | `aggregations.* 1172 + grading.* 1163 preview 1164 domain:academic` | `academic-aggregations / academic-grading/index/form/preview 31 bonus card threshold/max/policy` | `GradeScale where institute_id? level?` | — | `education.manage+domain:academic` | academic-only |
| 19 | Final Results / Promotion | `AcademicFinalResultController:1 lifecycle Draft→Review→Approved→Locked→Published + AcademicPromotionController:1` `StudentAcademicHistoryService` | `final-results.* 1199 + promotions.* 1217 promotion.manage` | `academic-final-results/index/show/report-card transcript` | `TenantScoped` | — | `education.manage+domain:academic (+promotion.manage)` | academic-only |
| 20 | Exams (Professional) | `ExamController:24 index/sendToExam/show/update/saveMarks 181/destroy` `ExamResult/ExamSubject/ExamType` | `exams.* web:175 permission:exams.view tenant + 989 alias` `POST send-to-exam 178` `POST {exam}/marks 181` | `exams/index/show/_send_modal + tab results` | `Exam::where institute_id` explicit `Exams` not TenantScoped but `institute_id` where | — | `exams.view/manage` | **shared (training canonical)** |
| 21 | Certificates | `CertificateController:16 index/request 1094 action 1095 domain:academic` `CertificateTypeController 1311` | `GET certificates 190 permission:certificates.view + 1095 domain:academic action + 1311 types` | `certificates/index certificate-types` | `TenantScoped Certificate` | — | `certificates.view` | shared + academic action `1095 domain:academic` |
| 22 | Analytics/Reports | `AcademicAnalyticsController:1 academic/analytics/* 1114` `AcademicAttendanceReportController 1101` `Crm/Sales/Purchase/Finance reports` | `GET academic/dashboard 159 domain:academic + reports/*` `academic-attendance/mark 161` vs `finance/online-payments` | `academic/analytics/dashboard/business/profile 251 vs 276` | tenant | — | verified+domain | academic analytics `domain:academic` |

---

## 5. STUDENT VISIBILITY ANALYSIS (MAWA)

| Route | Controller:Line | View | Permission | Domain | Tenant/Branch | Current Nav Visibility For MAWA | Exact Reason Hidden/Missing | Safe To Reuse For Professional? |
|-------|-----------------|------|------------|--------|---------------|----------------------------------|------------------------------|---------------------------------|
| `students.index 139` + `show/enroll` `StudentController:140` `AcademicHistory academic-transcript 1091 domain:academic separate` | `StudentController:140 index students.view` `Student TenantScoped` `BranchScoped via Student.branch_id` | `students/index/show/form students/_tabs academic_transcript.blade` | `students.view/manage` | **shared none + academic sub `domain:academic` separately** | `TenantScoped+Student branch` `BranchScoped` | **VISIBLE** `layout:136 (isEducation\|\|isProfessional && hasEducationModule)` `MAWA sees` + also `layout:230 Academic→Students` hidden for MAWA (isEducation false) but `136` shared covers | Not hidden — shared correctly | **MUST SHOW → REUSE existing Student** — `Student where institute_id + branch_id` `student_type?` not invent `Trainee` table — `Trainers label` parallel not needed — students.manage still tenant |
| `students.academic-transcript 1091 domain:academic` | same controller `academicTranscript` | same transcript blade | `students.view + domain:academic` | **domain:academic** | tenant | **HIDDEN for MAWA** `domain:academic` 403 — correct | Transcript is **academic-only placement history** not batch enrollment | **ACADEMIC ONLY** — training transcript should be `Result/Certificate` not academic transcript |

**GATE 5 check:** `Student::query()->where institute_id MAWA` — fine; `subject_type` not relevant.

---

## 6. TEACHER / TRAINER VISIBILITY ANALYSIS (MAWA)

| Route | Controller:Line | View | Permission | Domain | Tenant/Branch | Current Nav | Reason Hidden | Reuse? |
|-------|-----------------|------|------------|--------|---------------|-------------|---------------|--------|
| `teachers.index 355/1076` `TeacherController:12 InstituteUser where role=teacher + TeacherProfile` `assign:1083` | `TeacherController:47` `InstituteUser where role_id=teacherRoleId` `whereHas teacherProfile` BranchScoped + `TeacherAcademicAssignment batch_id->Membership 54` | `institute/teachers/index/form/show` | `tenant` no extra perm? | **none** | `InstituteUser role teacher TenantScoped+BranchScoped` | **VISIBLE** `layout:150 isEducation\|\|isProfessional && workspaceAllowedTeachers` label `Trainers when isProfessional && !isEducation 152` | Not hidden — correctly surfaces single system — `MAWA sees Trainers` | **MUST SHOW — REUSE SINGLE Teacher/Instructor system** — DO NOT CREATE `InstructorController` — verified no `InstructorProfile` table; `Batch.teacher_id->Membership` reused for both domains |
| Academic teacher assignment `teachers/{teacher}/assign 1083` via `TeacherAcademicAssignment classGrade/academicGroup` | `TeacherAcademicAssignment` classGrade/academicGroup | same show `assign` | same | same | branch | **VISIBLE per-entity** `teachers.show assign` | Not top nav bucket — by design inside `Teachers` detail — correct for both | Reusable via same model `Batch.teacher_id` for training |

---

## 7. COURSE VISIBILITY ANALYSIS (MAWA)

| Route | Controller | Where Clause | Subject_type | Categories | View | Perm | Domain | Nav MAWA | Gap |
|-------|------------|--------------|--------------|------------|------|------|--------|----------|-----|
| `courses.manage.index:928` canonical `CourseMasterController:44 where institute_id + with category` `subjectsCount where subject_type=derived professional 62` | `CourseMasterController:44` `Course::where institute_id MAWA` `where status/category` `TenantScoped+BranchScoped? Course institute_id only` | `Course institute_id MAWA` + `category filter` `category_id where institute_id? CourseCategory TenantScoped where institute_id derived` | **Server-derived** `InstituteDomain::subjectTypeFor(institute) → professional` `derived` not from `?subject_type` | `CourseCategory TenantScoped where institute_id` `Rule::exists->where institute_id & subject_type` CategoryManage `26` | `course-master/index:19k + form:63k` tabs `Courses\|Subjects 17` | `courses.view/manage` `tenant` | `tenant+verified` `derived` | **VISIBLE** `layout:286 Training→Courses isProfessional` | **MUST SHOW — correctly isolated** — no cross-tenant leak `where institute_id MAWA` |

**GATE 5 verified:** MAWA sees only `Course where institute_id=MAWA`, `Subject where institute_id=MAWA AND subject_type=professional`, `Category where institute_id=MAWA`, curriculum `availableCourses:397 domain-aware` correctly filters to professional courses for MAWA.

---

## 8. SUBJECT VISIBILITY ANALYSIS (MAWA)

| Route | Controller:Line | Where | Nav MAWA | Tenant Check | Verdict |
|-------|-----------------|-------|----------|--------------|---------|
| `courses.manage.subjects.index:952 canonical SubjectManagementController:30 subjectQuery(instituteId, derived) where institute_id=X AND subject_type=derived + TenantScoped + InstituteUser` | `SubjectManagementController:79 derived = InstituteDomain::subjectTypeFor(institute)` `allSubjectTypes=[$derived] where subject_type=$derived 112` | **VISIBLE** `layout:289 Training→Subjects isProfessional` + `_tabs Courses\|Subjects 17` | `Subject where institute_id MAWA AND subject_type=professional` isolated `Rule::exists->where institute_id & subject_type derived` | **MUST SHOW + MUST REMAIN professional isolated** — academic `academic` subjects not visible to MAWA; professional subjects not visible to School tenant; correctly server-clamped even if `?subject_type=academic` forged |

---

## 9. CURRICULUM VISIBILITY ANALYSIS (MAWA)

| Route | Controller | Model | Nav MAWA | Guard |
|-------|------------|-------|----------|-------|
| `curricula.* 900 index/create/store/show/edit/update/activate/destroy + modules store/update/destroy 910 lessons 914 + activate:907` `CurriculumController:31` `CourseCurriculum TenantScoped availableCourses:397 domain-aware` `modules/lessons` | `CourseCurriculum TenantScoped` `where institute_id` `CurriculumModule/Lesson` | **VISIBLE** `layout:292 Training→Curriculum isProfessional` + poly hybrid `307 !usesClassTerm` not for MAWA | `permission:curriculum.view/manage tenant` `availableCourses` derives professional courses for MAWA via `subjectTypeFor` — curriculum only exposes MAWA professional courses/subjects (verified `B7 397`) — no academic subject leakage |

**Classification: MUST SHOW** — training curriculum (modules/lessons under course) is core to `Course→Curriculum→Batch` workflow for MAWA.

---

## 10. BATCH VISIBILITY ANALYSIS (MAWA)

| Route | Controller:Line | Model Tenant | Nav MAWA | Branch | Verdict |
|-------|-----------------|--------------|----------|--------|---------|
| `batches.* web:165 + 989 status/transfer/remove-student 56/81` `BatchController:43 index where course_id/branch/instructor/status + with course/subject/year` `TenantScoped+BranchScoped Batch SoftDeletes 60` `archived/unarchived/status/transferStudent/removeStudent` `BatchLifecycleService` | `Batch where institute_id MAWA` `with course where institute_id` `course.subjects` | **VISIBLE** `layout:295 Batches isProfessional` + `Academic !usesClassTerm 310` not for MAWA | `branch_id` branch isolation `76 batch.instructor_id Membership` | **MUST SHOW** — batch `teacher()` `Membership 54` + `course()` category `professional` — only MAWA batches |

**Enrollment inside Batch:** `batches.show enrolled tab` + `students/{student}/enroll 144 + BatchController:transferStudent 56` currently reachable via `batches.show` detail — **no dedicated top Training→Enrollment bucket** — gap identified (see Gap Matrix).

---

## 11. ENROLLMENT VISIBILITY ANALYSIS (MAWA)

| Concept | Existing Route | Controller | View | Permission | Domain | Tenant/Branch | Current Nav MAWA | Reason Hidden | Reusable? |
|---------|----------------|------------|------|------------|--------|---------------|------------------|---------------|-----------|
| **Batch Enrollment** | `POST batches/{batch}/transfer 56 , enroll StudentController:144 students/{student}/enroll`, `admissions/pipeline.* 1004` via `Student→Batch` `StudentEnrollment/BatchStudent` | `StudentController:144 enroll` `BatchController:56 transferStudent` `StudentEnrollment model institute_id` | `students/show enroll actions` `batches/show enrolled tab` `admissions/pipeline` | `students.manage` `batches.manage` | none | `StudentEnrollment where institute_id` `Batch BranchScoped` | **NO TOP NAV** — via `batches.show` tab + `students.show` enroll button + `admissions.pending 141 isEducation && hasPermission admission.approve` (**hidden for MAWA isEducation false**) | Hidden because only `admissions.pending` is nav but gated `isEducation` — training enrollment has **no bucket** | **SHOULD SHOW — REUSE existing StudentEnrollment/Batch transfer** — map to existing `batches.show#enrolled` or add `Training→Enrollment` alias to `batches.index?tab=enrolled` reusing same canonical — DO NOT CREATE new `TrainingStudent` table |
| Academic Placement | `placements.* 1237` `StudentAcademicPlacementController:54` `academicYears` | `academic-placements/index` `id=academic-years` | `education.manage+domain:academic` | tenant | **HIDDEN for MAWA 403** — correct | Academic placement is `ClassGrade/Group/AcademicYear` bound — not for training | **ACADEMIC ONLY** — must remain hidden |

**GATE 5 Enrollment isolation verified:** `Enrollment only exposes MAWA students (Student where institute_id) + MAWA batches (Batch where institute_id)` via `BatchController:56 transferStudent where student institute_id==batch institute_id` check.

---

## 12. ATTENDANCE VISIBILITY ANALYSIS (MAWA)

| Route | Controller | View | Domain | Tenant | Nav MAWA | Reason Hidden | Reusable? |
|-------|------------|------|--------|--------|----------|---------------|-----------|
| **Batch Attendance** `batches.show attendance tab` (training) `Attendance where batch_id + institute_id` | `BatchController:show` `Attendance` model `Exam batch attendance` | `batches/show attendance tab` | none | `Batch Attendance TenantScoped` | **NO TOP NAV** — inside `batches.show` only — no `Training→Attendance` bucket | Intentional nesting — correct but undiscoverable | **SHOULD SHOW — add Training→Attendance alias** `Training → Attendance → batches.index` filtered attendance or reuse `batches.show` tab — DO NOT EXPOSE `academic-attendance.mark 161 domain:academic` (academic-only) |
| `academic-attendance.mark.* web:161 + 1101 / reports 1101` `AcademicAttendanceController:72 AcademicAttendanceReportController` `domain:academic` | `academic-attendance/index + reports/class/daily/student` | `domain:academic` | `StudentAcademicAttendanceService institute scoped` | **HIDDEN for MAWA 403** — correct | Academic attendance `ClassGrade/Group/AcademicYear` bound | **ACADEMIC ONLY** — keep hidden; training uses batch attendance, not academic attendance |

---

## 13. ASSESSMENT VISIBILITY ANALYSIS (MAWA)

| Type | Route | Controller | View | Permission | Domain | Tenant/Branch | Nav MAWA | Reason Hidden | Safe For Professional? |
|------|-------|------------|------|------------|--------|---------------|----------|---------------|------------------------|
| **Training Exam** `exams.* web:175` `sendToExam/show/update/saveMarks:181/destroy` + alias `exams.marks 1071` `ExamController:24` | `ExamController:24` `Exam::where institute_id` explicit `institute_id where` `Batch` relation | `exams/index/show/_send_modal tab results` `Result::query` `Batch` | `exams.view/manage tenant` | **none** | `Exam where institute_id` | **VISIBLE** `layout:298 Training→Exams isProfessional` — correct | Not hidden — functional for `Course→Batch→Exam→Marks` training path | **MUST SHOW — REUSE ExamController — CANONICAL FOR TRAINING** |
| **Academic Assessment** `settings.academic.assessments.* 1182` `assessments.index/create/store/show/edit/update/destroy/lock/unlock/subjects 99 readiness 93 marks.store 1195 marks-sheet 1196` `AcademicAssessmentController:45` `AcademicAssessment TenantScoped + AcademicAssessmentService subjectsForSelection 112` | `academic-assessments/index/form/show/marks/marks-sheet` | `education.manage+domain:academic 1182` | `TenantScoped+Branch branch actingBranch 316` | **HIDDEN for MAWA 403** `domain:academic` — correct | Academic assessments are `ClassGrade/Group/AcademicYear` + `ComponentAssessmentSubjectComponent` + `PassRule` — not Batch/Course | **ACADEMIC ONLY — DO NOT EXPOSE** — training must use `Exams` not `AcademicAssessment` |

**GATE 6 finding:** MAWA's `Assessments` visible via `Training→Exams` (canonical professional); `Academic Assessment 1182` correctly hidden.

---

## 14. MARKS VISIBILITY ANALYSIS (MAWA)

| Type | Route | Controller | View | Permission | Domain | Nav MAWA | Reason Hidden | Reuse? |
|------|-------|------------|------|------------|--------|----------|---------------|--------|
| **Training Marks** `POST exams/{exam}/marks 181 ExamController:saveMarks` `exams.marks alias 1071/1571` | `ExamController:saveMarks` `ExamResult` | `exams/show marks table` + `exams/index tab results` | `exams.manage` | none | `ExamResult where institute_id` | **VISIBLE via Exams→Show→Marks** but **NO standalone Training→Marks bucket** — gap | `Training Marks Entry` should reuse `exams.show → saveMarks` — currently `Marks Entry 245 ?view=marks` is `assessments.index?view=marks` academic-only → wrong for MAWA |
| **Academic Marks** `POST assessments/{assessment}/marks 1195 + GET marks-sheet 1196 export 1197` `AcademicMarksController:52 store 81 sheet` `AssessmentSubject + AcademicStudentMark + Aggregation` | `marks.blade + marks-sheet` | `education.manage+domain:academic` | scoped assessment | **HIDDEN for MAWA 403** — academic | **ACADEMIC ONLY** — `Marks Entry 245 ?view=marks` is `assessments.index?view=marks academic` → 403 for MAWA — correct hidden but means MAWA currently has no top `Marks` bucket for training |

**Classification:** `MUST SHOW` for training via **Exam marks** (`exams.marks`), not academic `assessments.marks`. P1 `Marks Entry ?view=marks` is academic-only and should remain hidden for MAWA — need separate `Training→Marks` alias to `exams` marks.

---

## 15. RESULT VISIBILITY ANALYSIS (MAWA)

| Type | Route | Controller/Service | Calculation | Nav MAWA | Hidden Reason | Reuse? |
|------|-------|-------------------|-------------|----------|---------------|--------|
| **Training Result** `Result::query in ExamController:index Result paginator` + `exams.index tab results` `Certificate via Result` | `ExamController:index Result::where institute_id` `Result model institute_id` | `exams/index tab results + certificates/index` | **NO standalone Training→Results bucket** — via `Exams tab results` only | `Training Results hidden as dedicated nav` | **SHOULD SHOW — reuse Result mix** — add `Training→Results → exams.index?tab=results` alias reusing same canonical — DO NOT transform to academic `final-results 1199` |
| **Academic Result** `AcademicResultAggregationScheme → AcademicResultAggregationService → AcademicGradingService GradeScale → AcademicFinalResultService threshold 2.00 max 5.00 single/best/sum → AcademicFinalResultRow locked/published → ReportCard` `settings.academic.final-results.* 1199 + aggregations 1172 + grading preview 1164 + promotions 1217` | `AcademicFinalResultController + Aggregation/Grading/Promotion services + historical integrity locked/published` | `education.manage+domain:academic` | **HIDDEN for MAWA correctly** — `settings.academic.final-results.*` inside `Academic→Results` `isEducation 248` | Academic result `Year→Class→Group→Placement→Aggregation→GradeScale → Promotion→Final→ReportCard→Transcript→Certificate` with `locked/published` historical freeze + `optional bonus` budget `2.00` — not Batch/Course | **ACADEMIC ONLY — MUST REMAIN HIDDEN** — `Published Results 262 ?status=published` also academic |
| Grade Scales `grading.* 1163 preview 1164` | `AcademicGradingController:52 GradeScale extends inst override ladder + AcademicGradingService resolveScaleForClass` | same | same | **HIDDEN** | Academic scale hierarchy | **ACADEMIC ONLY** |

---

## 16. CERTIFICATE VISIBILITY ANALYSIS (MAWA)

| Route | Controller | Model | Permission | Domain | Nav MAWA | Hidden Reason | Reuse? |
|-------|------------|-------|------------|--------|----------|---------------|--------|
| `GET certificates 190 permission:certificates.view tenant` `CertificateController:index 16 TenantScoped 190 + request 1094 students/{student}/certificate-request domain:academic + 1095 certificates/{certificate}/action domain:academic` `certificate-types.* 1311` | `CertificateController:16` `Certificate TenantScoped` `where institute_id` `CertificateType` | `certificates/index + certificate-types` `admin/certificates` | `certificates.view` | `none for index, domain:academic for 1095 action` | **VISIBLE** `layout:301 Training→Certificates isProfessional` + `278 Academic→Certificates` + `190` shared | Not hidden — correctly shared single `Certificate` model reused for both domains — `isProfessional` still sees `Certificates` | **MUST SHOW — REUSE single Certificate** — Training `Certificates` 301 correct, academic `action 1095 domain:academic` remains 403 for MAWA but `certificates.index` visible |

---

## 17. ACADEMIC-ONLY MODULE ANALYSIS (MUST REMAIN HIDDEN FOR PROFESSIONAL)

| # | Module | File:Line | Why Academic-Only | MAWA Behavior | Recommendation |
|---|--------|-----------|-------------------|---------------|--------------|
| A01 | **Academic Years** `AcademicYear + StudentAcademicPlacementController storeAcademicYear 279` | `institute_modules:1247 academic-years.store tenant education.manage+domain:academic` `academic-placements/index:166 years manager` | Year is `StudentAcademicPlacement academic_year_id` + `AggregationScheme academic_year_id` + `PromotionPolicy academic_year_id` historical `academicYearHasHistory 480` — training Batches use `Course/Curriculum` not `AcademicYear` | **Hidden for MAWA** `settings.academic.placements.index?section=academic-years#academic-years 221 isEducation` 403 | **AUD: ACADEMIC ONLY — MUST REMAIN HIDDEN** — no training year concept; reuse Batches |
| A02 | **Class/Grade** `ClassGrade / InstituteClassGrade + AcademicStructureController 145` | `classes.* 979 domain:academic + settings.academic.classes.* 1154` `academic-structure 159` N-level `education_system→level→class→group` | Classes are `School/College Class Grade` hierarchy, not `Course` — training uses `CourseMaster` not `ClassGrade` | **Hidden** `isEducation` toggle `173` | **ACADEMIC ONLY** |
| A03 | **Groups/Streams** `AcademicGroup / InstituteAcademicGroup` | `groups.* 1158 domain:academic` `academic-structure 159 groups per class` | Group = `Science/Commerce/Arts` stream under Class, not batch grouping | **Hidden** `?section=groups#groups 227` | **ACADEMIC ONLY** |
| A04 | **Academic Structure / Label / Levels** `settings.academic.index/label 1144/1146 levels.* 1149` `LearningStructureController academic/structure/* nodes:1114` | `AcademicStructureService resolve institute->country override + LearningStructure generic N-level` | Configuration for `Level/Class/Group` instantiation — not for training `CourseCategory/Subject` | **Hidden** `Academic Settings 218 ?section=groups` | **ACADEMIC ONLY** |
| A05 | **Academic Placement** `StudentAcademicPlacement + selections` | `placements.* 1237 domain:academic` `placementHasHistory 465` | Placement binds `Student → AcademicYear+ClassGrade+AcademicGroup+Subjects` + `PromotionDecisionItem next_placement_id` historical | **Hidden** `placements.index 239 isEducation` | **ACADEMIC ONLY** |
| A06 | **Academic Assessment** `AcademicAssessment + Component + AssessmentSubject` | `assessments.* 1182 domain:academic` `Component mandatory_pass` | Assessment is per `AcademicYear/Class/Group/Subject` with `lock/unlock` + `subjectForSelection` | **Hidden** `Assessments 242 / Marks Entry 245` isEducation | **ACADEMIC ONLY** |
| A07 | **Marks (Academic)** `assessments.marks.store 1195 marks-sheet 1196` | `AcademicMarksController 52/81` `AcademicStudentMark + Aggregation` | Marks are per `AssessmentSubject Component` + `Academic FinalResult lifecycle` not `ExamResult` | **Hidden** `?view=marks` academic | **ACADEMIC ONLY** |
| A08 | **Aggregation / Grade Scales / Promotion / Final Result** `aggregations 1172 grading 1163 final-results 1199 promotions 1217` | `AcademicAggregationService GradeScale mirror Course? no` `AcademicPromotionService` `AcademicFinalResultService threshold 2.00 max 5.00` historical `locked/published` | Grade ladder `GLOBAL→COUNTRY→SYSTEM→LEVEL→INSTITUTE override` + `optional bonus` + `PromotionDecision` + `FinalResultRow frozen` are `Year/Class/Group` based, not Batch | **Hidden** `Results→Aggregations 253 / Grade Scales 256 / Final 259 / Published 262` + `Promotions 266` | **ACADEMIC ONLY** |
| A09 | **Transcript** `students/{student}/academic-transcript 1091 domain:academic` | `StudentController academicTranscript/history` + `StudentAcademicHistoryService` | Transcript = per-student `academic_history.blade` `academic_transcript` `placement→year/class/group/subject snapshot` | **Hidden per-student contextual** `isEducation` hub `275` → `students.index` but detail `domain:academic` 403 for MAWA | **ACADEMIC ONLY** |
| A10 | **Academic Attendance** `academic-attendance.mark 161 reports 1101 domain:academic` | `AcademicAttendanceController 72 AcademicAttendanceReportService` `StudentAcademicAttendance` | Mark attendance per `ClassGrade Group AcademicYear` daily `101-110` | **Hidden** `Academic→Attendance 269 isEducation` | **ACADEMIC ONLY** — training uses **Batch attendance** `batches.show` |
| A11 | **Academic Analytics** `academic/analytics/* 1114 10 exports` | `AcademicAnalyticsController` `academic/analytics/index students/batches/certificates/...` | Analytics `students/batches/attendance/results/promotions/completion` sliced by `AcademicYear/Class` | **Hidden** `Academic→Analytics 272 + dashboard _tabs isAcademic:8` | **ACADEMIC ONLY** |

**Total 11 MUST REMAIN HIDDEN for professional — no change required.**

---

## 18. PROFESSIONAL TRAINING MODULE ANALYSIS (reusable for MAWA)

| # | Module | Current Classification | Expected For MAWA |
|---|--------|------------------------|-------------------|
| P01 | Dashboard `dashboard 116` | MUST SHOW — generic | MUST SHOW |
| P02 | Business Profile `business.profile 349` `academicData 251 vs professionalData 276` | MUST SHOW — topbar brand 32 already | MUST SHOW |
| P03 | Students/Trainees `students.* 139` shared | MUST SHOW — single `Student` reuse — `Students` label correct (optionally `Trainees` alias via header when professional, but label `Students` acceptable per `sidebar.students`) | **MUST SHOW** — keep `Students` |
| P04 | Trainers `teachers.* 355/1076` label `Trainers` when `isProfessional && !isEducation 152` | MUST SHOW — single `TeacherController` reuse, no duplicate | **MUST SHOW — Trainers `150`** |
| P05 | Courses `courses.manage.* 928` | MUST SHOW — canonical tabs `Courses` | MUST SHOW |
| P06 | Subjects `courses.manage.subjects.* 952` `subject_type=professional` | MUST SHOW — derived clamp | MUST SHOW |
| P07 | Categories/Sub-Categories `938/945` modal | SHOULD SHOW (via Course Master modal) — OPTIONAL standalone | MUST SHOW inline via Course Master (keep hidden standalone, not missing) |
| P08 | Curriculum `curricula.* 900` + Modules `910` + Lessons `914` | MUST SHOW — professional curriculum | MUST SHOW |
| P09 | Batches `batches.* 165/989` | MUST SHOW | MUST SHOW |
| P10 | Enrollment `students/{student}/enroll 144 + batches/{batch}/transfer/remove-student 56` inside `batches.show/batches.index` | **SHOULD SHOW — EXISTS BUT CURRENTLY HIDDEN** — via batch detail only, no top `Training→Enrollment` bucket | **GAP → SHOULD SHOW** via alias `Enrollments → batches` or `admissions.pipeline` |
| P11 | Attendance (Batch) `batches.show attendance tab` | **SHOULD SHOW — EXISTS BUT HIDDEN** — inside batch show | **GAP → SHOULD SHOW** alias `Attendance → batches` |
| P12 | Assessments / Exams `exams.* 175` `sendToExam/show/saveMarks` | **MUST SHOW — Exams 298 already visible** — correct canonical for training | MUST SHOW |
| P13 | Marks (Training) `exams/{exam}/marks 181 ExamController:saveMarks` + `Result mix` | **MUST SHOW but hidden as dedicated nav** — via `exams.show` `saveMarks` only | **GAP → MUST SHOW** via `Training→Marks → exams.marks hub` or `Exams→Results` alias — DO NOT map to academic `assessments.marks` |
| P14 | Results (Training) `Result::query in ExamController:index` `exams.index tab results` | **SHOULD SHOW — EXISTS BUT HIDDEN** — via Exams results tab only | **GAP → SHOULD SHOW** alias `Training→Results → exams.index#results` |
| P15 | Certificates `certificates.index 190 + types 1311` | **MUST SHOW — Training→Certificates 301 already visible** — single model reused | MUST SHOW |
| P16 | Fees (Education Finance) `finance.education.* 640 fee-heads/structures/students/fee-collection 708` `permission finance.view/manage module_access:finance` | **OPTIONAL — HIDDEN but generic `Finance` 328 visible when workspaceAllowedFinance** — no `Training→Fees` dedicated for trainees | **OPTIONAL** — acceptable via generic `Finance` but could add `Training→Fees alias 708` |
| P17 | Reports/Analytics `reports/hub 1484 + academic/analytics hidden` `finance/sales/purchase reports` | **OPTIONAL — EXISTS** — professional reports via `finance/sales/purchase` + `exams results` — not missing per se | **OPTIONAL** — keep via generic |
| P18 | CRM linkage `crm.* 230` | **OPTIONAL — EXISTS** `crm.dashboard` shared | OPTIONAL |

---

## 19. ROUTE MAPPING — EVERY EXISTING ROUTE RELEVANT TO MAWA (forensic inventory)

> Legend: **EXISTS+VISIBLE** = MAWA sees nav; **EXISTS+HIDDEN** = direct URL only but permitted for professional domain (none=403); **EXISTS+ACADEMIC 403** = academic-only 403 for MAWA (correct); **LEGACY** = keep but not primary

| # | Route Name | Method URI | File:Line | Middleware | Nav MAWA | Expected For Professional | Gap |
|---|------------|-----------|-----------|------------|----------|---------------------------|-----|
| R01 | `dashboard` | `GET /` `116` | `web.php` | `tenant+verified` | **VISIBLE 120** | VISIBLE | — |
| R02 | `business.profile` `349` | `GET business/profile` | `web` | `tenant+verified` | **VISIBLE topbar 32** | VISIBLE | — |
| R03 | `students.index` `139` `students.view` | `GET students` | `web 139` | `tenant` `students.view` | **VISIBLE 136/230** shared | **MUST VISIBLE** | — |
| R04 | `teachers.index` `355/1076` single | `GET teachers` `InstituteUser role teacher` | `web 355` `1076 assign 1083` | `tenant` `BranchScoped` `label Trainers 152` | **VISIBLE 150** | **MUST VISIBLE** | — |
| R05 | `courses.manage.index` **canonical** `928` `courses.view` | `GET courses/manage` | `institute_modules 928` | `tenant courses.view` `InstituteDomain derived` | **VISIBLE 286** | MUST VISIBLE | — |
| R06 | `courses.manage.subjects.index` **canonical** `952` | `GET courses/manage/subjects` | `952` | `tenant courses.view` server-clamped | **VISIBLE 289** + _tabs `17` | MUST VISIBLE | — |
| R07 | `courses.manage.categories.* 938` `POST/PUT/DELETE` | `GET/POST` `938` | `938` | `courses.view/manage` derived | **HIDDEN standalone** via `course-master/form modal` — correct | VISIBLE inline | — |
| R08 | `curricula.* 900 + modules 910 lessons 914` | `GET curricula` `permission:curriculum.view` | `900` | `TenantScoped` `availableCourses 397` | **VISIBLE 292** | MUST VISIBLE | — |
| R09 | `batches.* web:165 + 989 status/transfer/remove-student` | `GET batches/paginate + GET {batch} + POST store/update/destroy/status/transfer` | `web 165` `989` | `batches.view/manage` `TenantScoped+BranchScoped` | **VISIBLE 295** | MUST VISIBLE | — |
| R10 | `students.enroll 144 + admissions.pipeline 1004` | `POST students/{student}/enroll` `permission:students.manage` | `web 144` `admissions/pipeline` | `tenant` | **NO NAV** via `batches.show enroll tab` + `students.show` | **EXISTS+HIDDEN → SHOULD VISIBLE alias** | GAP |
| R11 | `exams.* web:175 sendToExam/show/saveMarks/destroy + alias 1071` | `GET exams index/sendToExam/show/saveMarks 181` | `web 175` `1071` | `exams.view/manage` `tenant` | **VISIBLE 298** | MUST VISIBLE | — |
| R12 | `exams.marks saveMarks 181 + alias 1071/1571` triple | `POST {exam}/marks` | `web 181` | `exams.manage` | **HIDDEN** inside `exams.show` — no bucket | **EXISTS+HIDDEN → MUST VISIBLE alias Training→Marks** | GAP |
| R13 | `certificates.index 190 + types 1311` | `GET certificates` | `web 190` `1311` | `certificates.view` | **VISIBLE 301** | MUST VISIBLE | — |
| R14 | `academic.dashboard 159 domain:academic` | `GET academic/dashboard` | `web 159 domain:academic tenant` | `domain:academic` | **HIDDEN 403 for MAWA** correct | **ACADEMIC ONLY 403** | — |
| R15 | `settings.academic.* 1144 assessments 1182 marks 1195 grading 1163 aggregations 1172 final-results 1199 promotions 1217 placements 1237 academic-years 1247` | `GET/POST settings/academic/*` | `1144-1251 domain:academic+education.manage tenant` | `TenantScoped` | **HIDDEN 403 for MAWA** (`Academic collapsible 204 isEducation false`) | **ACADEMIC ONLY 403** | — |
| R16 | `classes.* 979 domain:academic` | `GET classes` | `979 domain:academic courses.view` | `domain:academic` | **HIDDEN 403 for MAWA** | ACADEMIC ONLY | — |
| R17 | `academic-attendance.mark.* 161 domain:academic` `academic-analytics.* 1114 domain:academic` | `GET academic-attendance/mark + reports` | `161/1101` | `domain:academic` | **HIDDEN 403** correct | ACADEMIC ONLY | — |
| R18 | `finance.education.* 640 students/fee-heads/structures/collection 708` | `GET finance/education/fee-collection` `permission finance.view module_access:finance` | `640` | `workspaceAllowedFinance` | **HIDDEN as dedicated `Training→Fees` but VISIBLE via generic Finance 328** | OPTIONAL | — |
| R19 | `legacy courses/archive courses/subjects courses/{course} 974` | `GET courses/archive/subjects/{course}/subjects` | `web 188 legacy` | `tenant` | **LEGACY DUPLICATE** `WRONG NAV` — keep but not primary `courses.manage 928` is canonical | NOT APPLICABLE | — |

**Inventory summary:** 19 groups — **8 VISIBLE** (dashboard/business students/trainers courses/subjects curricula batches exams certificates) + **4 HIDDEN OPERATIONAL** (enrollment via batch, attendance inside batch, marks via exams.show, results via exams tab) + **7 ACADEMIC ONLY correctly 403** + 1 legacy.

---

## 20. CONTROLLER MAPPING

| Controller | File:Line | Routes Served | Tenant | Branch | Domain For MAWA | Permission | Audit |
|------------|-----------|---------------|--------|--------|------------------|------------|-------|
| `StudentController` | `StudentController:140` | `students.* 139 + students.academic-* 1089 domain:academic` shared | `Student TenantScoped+Branch` + `InScope` | branch | **shared** + academic sub `domain:academic` 403 for MAWA detail | `students.view/manage` | **MUST REUSE — single Student** |
| `TeacherController` | `TeacherController:12` | `teachers.* 355/1076 single` | `InstituteUser where role=teacher` + `BranchScoped` + `teacherProfile` | branch | none | `tenant` | **MUST REUSE single** |
| `CourseMasterController` | `CourseMasterController:30` | `courses.manage.* 928` canonical | `Course where institute_id + InstituteDomain derived 62` | — | derived `professional` | `courses.view/manage` | **MUST REUSE** |
| `SubjectManagementController` | `SubjectManagementController:30` | `courses.manage.subjects.* 952` | `where institute_id AND subject_type=derived` `TenantScoped` | — | server-derived `professional` | `courses.view/manage` | **MUST REUSE** |
| `CurriculumController` | `CurriculumController:31` | `curricula.* 900 modules 910 lessons 914 activate 907` | `CourseCurriculum TenantScoped availableCourses 397 domain-aware` | — | hybrid `isProfessional` via controller `397` professional courses | `curriculum.view/manage` | **MUST REUSE** |
| `BatchController` | `BatchController:33` | `batches.* 165/989 status/transfer/remove-student` | `TenantScoped+BranchScoped Batch` | branch | via course category `professional` | `batches.view/manage` | **MUST REUSE** |
| `ExamController` | `ExamController:24` | `exams.* 175 sendToExam/show/saveMarks 181 destroy` | `Exam::where institute_id` explicit | — | **none — training canonical** | `exams.view/manage` | **MUST REUSE — training Assessment** |
| `CertificateController` | `CertificateController:16` | `certificates.index 190 + request 1094 domain:academic + action 1095 domain:academic + types 1311` | `Certificate TenantScoped` | — | index none, action academic 403 for MAWA | `certificates.view` | **MUST REUSE single** |
| Academic `AcademicAssessment/ Marks/ Aggregation/ Grading/ FinalResult/ Promotion/ StudentAcademicPlacement/ Attendance/ Analytics` | `Academic*Controller` `settings.academic.* 1144 domain:academic` | all `settings.academic.* 1144` | `TenantScoped InstituteAcademic*` `GradeScale institute_id` | branch some | **domain:academic 403 for MAWA** | `education.manage+domain:academic (+promotion.manage)` | **ACADEMIC ONLY — DO NOT REUSE** |
| `ClassController` `AcademicStructureController` `LearningStructureController` | `ClassController:24 academic/structure 1114` | `classes.* 979 domain:academic + academic/structure nodes` | `InstituteCourse TenantScoped` + `global catalog` | — | `domain:academic` | `courses.view` | **ACADEMIC ONLY** |

---

## 21. VIEW MAPPING

| View Path | File:Line | Route | Current MAWA Nav | Expected For Professional |
|-----------|-----------|-------|------------------|---------------------------|
| `academic/dashboard.blade.php:1` + `analytics/* 1114` | `academic/dashboard 159 + analytics 1114` | `academic.dashboard/domain:academic` | **HIDDEN 403** — not for MAWA | HIDDEN — academic-only |
| `classes/index/archive/batches/subjects/_tabs` | `classes.* 979 domain:academic` | `classes.*` | **HIDDEN** `layout:173 isEducation` | HIDDEN |
| `institute/academic-structure.blade.php + academic-placements/index 166 id=academic-years + academic-assessments/marks/aggregations/grading/final-results/promotions + academic-attendance/reports` | `settings.academic.* 1144` | all academic | **HIDDEN** `Academic collapsible 204` | HIDDEN |
| `institute/course-master/index:19k form:63k subjects:23k _tabs 17 subject-form 6k` | `courses.manage.* 928/952` | canonical tabs | **VISIBLE 286/289** | VISIBLE |
| `institute/curriculum/index/form/show` | `curricula.* 900` | — | **VISIBLE 292** | VISIBLE |
| `batches/index/show` tab `Exams/Attendance` | `batches.* 165` | — | **VISIBLE 295** via `Batches` + enrolled/attendance tabs inside `show` | VISIBLE |
| `students/index/show/form/_tabs academic_transcript` | `students.* 139` | `students.*` | **VISIBLE 136** shared | VISIBLE |
| `institute/teachers/index/form/show` | `teachers.* 355/1076` | — | **VISIBLE 150 Trainers** | VISIBLE |
| `exams/index/show/_send_modal tab results` | `exams.* 175` | `exams.*` | **VISIBLE 298** — exams + results tab | VISIBLE |
| `certificates/index + types` | `certificates.* 190` | — | **VISIBLE 301** | VISIBLE |
| `business/profile:405 academicData 251 vs professionalData 276 vs other 307` | `business.profile 349` | topbar 32 | **VISIBLE** `professionalData 276` for MAWA | VISIBLE |
| `dashboard/_tabs:8 isAcademic` | `academic.dashboard` | tabs | **HIDDEN academic tabs for MAWA** only Dashboard | HIDDEN |

---

## 22. PERMISSION MAPPING

| Route Group | Permission | MAWA Visibility | Expected |
|-------------|------------|-----------------|----------|
| `students.*` `students.view/manage` `web:139` | `students.view` for index/show, `manage` for create/enroll | VISIBLE 136 | **MUST SHOW — has`students.view`** — 403 if lacks |
| `teachers.*` `tenant` no extra perm `355` | `workspaceAllowedTeachers` `150` gate `layout:150` | VISIBLE `Trainers` | MUST SHOW |
| `courses.manage.* 928` `courses.view/manage` `tenant` | `workspaceAllowedEducation` + `courses.view` | VISIBLE | MUST SHOW |
| `curricula.* 900` `curriculum.view/manage` | same | VISIBLE | MUST SHOW |
| `batches.* 165` `batches.view/manage` `BranchScoped` | same | VISIBLE | MUST SHOW |
| `exams.* 175` `exams.view/manage` | same | VISIBLE | MUST SHOW |
| `certificates 190` `certificates.view` | `certificates.view` | VISIBLE | MUST SHOW |
| `settings.academic.* 1144` `education.manage+domain:academic` | `education.manage 1144 + domain:academic` — `Academic collasible 204 isEducation` hides for MAWA even if user has `education.manage` — correct | **HIDDEN 403** for MAWA even if perm held — middleware 403 | **ACADEMIC ONLY** |
| `academic-attendance/analytics 161/1114` `domain:academic` | `tenant` no perm but domain | HIDDEN | ACADEMIC ONLY |
| `finance.education 640` `finance.view/manage + module_access:finance` | `workspaceAllowedFinance 327` | HIDDEN dedicated `Training→Fees` but generic `Finance 328` visible if module enabled | OPTIONAL |
| `admin.*` `platform_admin` | `platform_admin` | never for MAWA tenant | — |

---

## 23. DOMAIN GUARD MAPPING

| Guard | File:Line | Current Behavior For MAWA | Expected | Risk |
|-------|-----------|---------------------------|----------|------|
| `InstituteDomain::fromKeys('training_center','training_institute')` `fromInstitute 50` → `PROFESSIONAL` | `InstituteDomain:70` `if industry===training_center && in_array(sub, PROFESSIONAL_TYPES)` → `PROFESSIONAL` | MAWA resolves `isProfessional true, isAcademic false` | `PROFESSIONAL` | — |
| `InstituteDomain::isAcademic 124` guards `Academic collapsible 204` `if ($isEducation && workspaceAllowedEducation)` `$isEducation=isAcademic` | `layout:204` — MAWA `isEducation false` → `Academic` hidden → all `settings.academic.* 1144` not exposed | Correct — academic hidden | — | PASS |
| `InstituteDomain::isProfessional 125` guards `Training 285-304` `if ($isProfessional && workspaceAllowedEducation)` | MAWA `isProfessional true` → `Courses/Subjects/Curriculum/Batches/Exams/Certificates` visible | Correct | — | PASS |
| `Middleware EnsureDomain:11 handle(domain)` `instituteDomain from TenantContext/Workspace 20 → abort 403 domain required` | `institute_modules:1144 domain:academic` + `web:159 academic/dashboard domain:academic` + `979 classes domain:academic` + `161 academic-attendance` + `1114 analytics` | MAWA `actual=professional !== academic` → 403 for all `settings.academic.*` even with `?section=groups#groups` query `view=marks` `section=academic-years` still 403 | Correct — `?section` not bypass | **PASS — hidden UI not sole defense** |
| `curricula.* 900` no `domain` but controller `availableCourses:397` domain-aware | Poly/university hybrid `!usesClassTerm 306` not for MAWA; training `professional` courses filtered correctly | Correct | — | PASS |
| `classes.* 979` `domain:academic` fixed B7 | MAWA cannot see `Classes` toggle `173` | Correct | — | PASS |
| `curricula.*` no domain intentionally | Keep — training hybrid poly still needs curriculum but `school Classes` preference dominates | Document | — | LOW |

---

## 24. TENANT / BRANCH ISOLATION

| Check | Spec | Actual For MAWA | Evidence |
|-------|------|-----------------|----------|
| All training modules `students/teachers/courses/subjects/categories/curricula/batches/exams/certificates` tenant | `TenantContext::id()=active_institution_id` `SetTenantContext:26` `TenantContext::set(Workspace::set)` `Workspace::membership()->institution` per `active_institution_id` | `Course where institute_id MAWA` `Subject where institute_id MAWA subject_type=professional` `Category where institute_id MAWA` `Curriculum CourseCurriculum where institute_id` `Batch where institute_id` `Student where institute_id` `Teacher InstituteUser where institute_id + role teacher` `Exam where institute_id` `Certificate where institute_id TenantScoped` — all `where institute_id` explicit or `TenantScoped` | **PASS — 4/4 TenantIsolationAuditTest 2.88s** `BusinessProfile authenticated user can open active business profile / cross business profile blocked 0.17s / multi business user sees active only 0.27s / tenant isolation 0.13s` |
| Branch isolation | `BranchContext::set(membership->branch_id) SetTenantContext:70` `Batch BranchScoped whereNull branch_id or branch_id==id` `BatchLifecycleService` | Branch manager in MAWA sees only `Batch whereNull branch_id OR branch_id==membership branch_id` — not other branch's batches | **PASS** |
| `withoutGlobalScope` abuse | None new in B13 — only pre-existing platform-admin `AcademicStructureController:464` | — | PASS |
| `FOREIGN_KEY_CHECKS=0` / `withoutGlobalScopes()->find()` | None new — only platform `AdminCertificate` outside tenant `1366` | — | PASS |
| Route total | `php artisan route:list Showing [1211] routes` — 0 new | — | PASS |
| Views compile | `php artisan view:clear INFO` | — | PASS |

---

## 25. IDOR ANALYSIS

| Vector | Guard For MAWA | Evidence | Verdict |
|--------|----------------|----------|---------|
| `institute_id` from request forged `?institute_id=OTHER` | Never trusted — `CourseMasterController:39 $instituteId = (int)$request->user()->institute_id` not input; `StudentAcademicPlacementController:555 resolveInstitute` from `InstituteUser.institute_id / Workspace::membership()->institution_id` never `request input`; `AcademicAssessmentController:354` same | All `store/update` do `Rule::exists->where('institute_id', $institute->id)` `Batch:376` `AcademicAttendance:72` `AdmissionPipeline:228` | **PASS — query ?section=groups/years/view=marks not trusted** |
| `subject_type=academic` forged when MAWA professional | Server-derived `SubjectManagementController:79 derived = InstituteDomain::subjectTypeFor(institute)` `where subject_type=derived professional 112` ignores `?subject_type` | `allSubjectTypes=[$derived]` not from input | **PASS** |
| `course_id / subject_id cross-tenant` | `filterCategories() TenantScoped where institute_id` + `Rule::exists categories/sub-categories ->where institute_id & subject_type derived` `CourseCategoryManageController:26` | `Category where institute_id MAWA` only | PASS |
| `enrollment student_id from other tenant` | `StudentController:enroll 144 + BatchController:56 transferStudent where student institute_id==batch institute_id` | `StudentEnrollment` checks `Student where institute_id` | PASS |
| `exam_id / certificate_id` | `Exam::query->where institute_id MAWA` via `ExamController:24` explicit `institute_id` where, `Certificate TenantScoped` | — | PASS |
| Business profile | `BusinessProfileController:assertTenantMatchesActive:140 Workspace/TenantContext verify` | `BusinessProfileTest idor via query param is ignored 0.18s PASS` | **PASS** |

---

## 26. MULTI-BUSINESS ANALYSIS

| Transition | Session Active Institute `active_institution_id` `Workspace::id()` `TenantContext::id()` `BranchContext` | `InstituteDomain::fromInstitute` | Sidebar After | Evidence |
|------------|---------------------------------------------------|----------------------------------|---------------|----------|
| **Academic →** `Business A School education/school` | `POST workspace/switch/A Workspace::set(A) TenantContext::set(A) BranchContext::set(membership.branch_id)` `View::composer AppServiceProvider:121 institute=Workspace::membership()->institution per active_institution_id` | `isAcademic true` `isProfessional false` | `Academic collapsible 204-282` 18 links (`Dashboard, Academic Settings vs Groups id=groups, Years id=academic-years, Classes, Placements, Assessments vs Marks Entry ?view=marks, Results→Aggregations/Grade Scales/Final/Published, Promotions, Attendance, Analytics, Transcript, Certificates, Students, Subjects, Teachers, Transcript`) visible; `Training 285` hidden; Dashboard `_tabs academic` `isAcademic:8` visible; Profile `academicData:251` | **PASS** |
| **→ Professional (MAWA)** `Business B training_institute training_center/dance_academy` | `POST workspace/switch/B` | `isAcademic false` `isProfessional true` | `Academic 204` hidden (`isEducation false`), `Training 285-304` Courses/Subjects/Curriculum/Batches/Exams/Certificates visible + `Trainers 152 Trainers` + shared `Students 136` + `Students 230` hidden academic but shared covers | **PASS — professional business shows professional sections BusinessProfile 0.12s** |
| **→ Other** `Business C Retail retail/general_store domain=other` | `POST workspace/switch/C` | `OTHER` neither true | Both `Academic + Training` hidden; only `Finance/Accounting/Hr/Sales/Purchase/Crm` per `workspaceAllowed*` | **PASS — other industries render without academic ui 0.37s** |
| **→ Academic back** | `POST workspace/switch/A` | `isAcademic true` | `Academic` reappears, `Training` hidden — cache cleared `view:clear` not stale | **PASS — 16/16 BusinessProfileTest 10.55s 67 assertions** |
| **No hardcode** | No `institute->id===MAWA_ID` or `slug===MAWA` hardcode — solely `InstituteDomain::fromInstitute` + `workspaceAllowedEducation` | — | Sidebar follows ACTIVE not membership list | **PASS** |

**Workspace resolver:** `Workspace::resolveAfterLogin 113` 0 memberships→null, 1→auto-activate, N→explicit choice if valid else picker — verified Forbes pattern.

---

## 27. LEGACY ROUTE ANALYSIS

| Legacy Route | File:Line | Current Behavior | Expected | Risk |
|--------------|-----------|------------------|----------|------|
| `courses/archive 188 + courses/subjects 188 + courses/{course} show 974 courses/{course}/subjects/{subject} nested` `CourseController:46 archive/subjects` `courses.subjects.update 404 shallow PUT courses/subjects/{subject} overwrites nested` | `web:188` `institute_modules:974` | `LEGACY DUPLICATE — EXISTS BUT WRONG NAV` — kept for backwards compat `// end $tenant 1366` inside tenant but **canonical is `courses.manage.* 928 + courses.manage.subjects.* 952`** — not primary nav, correctly not surfaced | Keep but do not surface as primary — `courses.manage 928` is canonical (B12 C.2) | LOW |
| `academic-attendance.mark.* duplicate` `web:161 + 1101/1564 POST mark store 1564` | `web:161 GET mark + institute_modules 1101` | Duplicate alias — harmless but confuse audit — `institute_modules:1101 canonical` | Consolidate docs keep `1101` group, `web:161` alias as redirect (not new behavior) | LOW |
| `exams.marks triple alias` `web:181 + institute_modules 1071/1571` same `ExamController:saveMarks` | `web:181` `1071` | Triple alias same controller — keep one `exams.marks` canonical `alias 1607 send-to-exam` | Keep canonical document | LOW |
| `admin.academic.grading duplicate inside/outside tenant 1592 vs 1651` | `1651 outside platform_admin` vs `1592 inside` | Duplicate — keep outside tenant `1651` only (file comments override) | Remove inside alias in impl phase | LOW |
| `courses/subjects/{subject} nested update` `404` comment overwrites | `web:404` | Shallow `PUT courses/subjects/{subject}` is canonical | Keep | — |

**No legacy route currently needed for MAWA training — canonical `courses.manage`, `curricula`, `batches`, `exams` already cover training.**

---

## 28. DUPLICATE-SYSTEM ANALYSIS

| Question | Finding | Evidence |
|----------|---------|----------|
| **Duplicate Teacher/Instructor?** | **SINGLE — REUSE** — One `TeacherController:12 Teacher / instructor management` + `TeacherProfile + TeacherAcademicAssignment batch_id->Membership 54` — no `InstructorController`, no `InstructorProfile`, no `Instructor` table | `glob InstructorController none`, `TeacherController comment Teacher / instructor management:12` — MAWA `Trainers` label switches `layout:152 isProfessional && !isEducation ? 'Trainers' : 'Teachers'` same route `teachers.index 355` |
| **Duplicate Student/Trainee?** | **SINGLE — REUSE** — `StudentController:140 students.index + enroll 144` + `StudentAcademicPlacement separate academic` — no `TraineeController`, no `Trainee` table — training trainees are `Student where institute_id MAWA` | No `Trainee` model; `Student TenantScoped` serves both |
| **Duplicate Course/Subject?** | **SINGLE — REUSE** — `CourseMasterController:30 where institute_id` `SubjectManagementController:30 where institute_id AND subject_type=derived` — no `TrainingCourse/AcademicCourse` split — same table `courses` `subjects` tenant-filtered by `institute_id + subject_type derived` | Correct |
| **Duplicate Assessment?** | **TWO SYSTEMS — BUT NOT DUPLICATE, VERTICALLY SEPARATED BY DOMAIN** — `ExamController:24 exams.*` **is training** `Batch→Exam→Result` (Course/Batch bound, `ExamResult` table) vs `AcademicAssessmentController:45 assessments.*` **is academic** `Year/Class/Group/Subject Component` `AcademicStudentMark` `AcademicFinalResultRow locked/published` historical | Training **MUST USE `ExamController`** — academic `AcademicAssessment 1182 domain:academic 403` must remain hidden for MAWA — no duplication, domain separation |
| **Duplicate Result/Certificate?** | **SHARED BUT DEGREE** — `Result` (training) via `Result model institute_id` `ExamController result tab` + `Certificate single TenantScoped` `certificates.index 190` shared; `CertificateTypeController 1311` shared; `AcademicFinalResultRow` is **academic-only** snapshot `locked/published` not `Result` | Training `Result` via `Result` query; Certificate single reused for both — `certificates/{certificate}/action 1095 domain:academic` academic-only stays 403 for MAWA but `index 190` visible |
| **Duplicate Category?** | NO — `CourseCategory + CourseSubCategory` single `TenantScoped` `where institute_id` `Rule::exists where institute_id & subject_type` | — |

**Recommendation: DO NOT CREATE any duplicate `Trainer/Trainee/Instructor/TrainingCourse` systems — reuse singles with domain-aware label/server clamp.**

---

## 29. NAVIGATION GAP MATRIX — MAWA-SPECIFIC (domain=professional)

| # | Capability | Backend Exists | Current Navigation For MAWA | Classification (spec matrix) | Expected For Professional | Risk | Recommendation (audit-only, not impl) |
|---|------------|---------------|-----------------------------|------------------------------|---------------------------|------|---------------------------------------|
| 1 | Dashboard | YES `DashboardController:__invoke` `web:116` | **EXISTS+VISIBLE 120** | **A. MUST SHOW** | VISIBLE | — | Keep |
| 2 | Business Profile | YES `BusinessProfileController:16 show workspace authoritative 140` | **VISIBLE topbar 32** | A | VISIBLE | — | Keep `professionalData 276` |
| 3 | Students / Trainees | YES `StudentController:140 students.* 139 TenantScoped` | **VISIBLE shared 136 Students + isProfessional** | **A MUST SHOW** | VISIBLE | — | Keep shared label `Students` (Trainees alias optional header) |
| 4 | Trainers | YES single `TeacherController:12 355/1076` | **VISIBLE 150 Trainers** when `isProfessional && workspaceAllowedTeachers` | A | VISIBLE | — | Keep `Trainers` label 152 |
| 5 | Courses | YES `CourseMasterController:30 canonical 928` | **VISIBLE 286** | A | VISIBLE | — | Keep |
| 6 | Subjects | YES `SubjectManagementController:30 canonical 952 professional clamp` | **VISIBLE 289 + _tabs 17** | A | VISIBLE | — | Keep |
| 7 | Categories/Sub-Categories | YES `CourseCategoryManage 26 / SubCategory 17` `938/945` modal | **HIDDEN standalone** via modal — correct **C. OPTIONAL** | C OPTIONAL | VISIBLE inline via Course Master — acceptable | — | Keep modal, no solo sidebar |
| 8 | Curriculum | YES `CurriculumController:31 curricula.* 900 modules 910 lessons 914` `availableCourses 397 domain-aware` | **VISIBLE 292** | A | VISIBLE | — | Keep |
| 9 | Modules/Lessons | YES inside curriculum `910/914` | **HIDDEN nested** inside `curricula.show` | A | VISIBLE nested | — | Keep nested |
| 10 | Batches | YES `BatchController:33 batches.* 165/989 BranchScoped` | **VISIBLE 295** | A | VISIBLE | — | Keep |
| 11 | **Enrollment** | YES `StudentEnrollment students.enroll 144 + batches transfer 989 status 84` | **EXISTS BUT CURRENTLY HIDDEN** — via `batches.show enrolled tab` + `students.show enroll` + `admissions.pipeline 1004` but **NO `Training→Enrollment` top bucket** `REASON: admissions.pending 141 isEducation gated` | **F. EXISTS BUT CURRENTLY HIDDEN — SHOULD SHOW B** | **SHOULD SHOW** `Training→Enrollments → pipeline or batches.show#enrolled alias` reusing same canonical `students.enroll/batches.transfer` | LOW | **SURFACE** alias `Training→Enrollment → batches.index` or `admissions.pipeline` reusing existing — DO NOT CREATE new table |
| 12 | **Attendance** | YES `Attendance batch attendance batches.show tab` vs `academic-attendance 161 domain:academic` separate | **EXISTS+HIDDEN** inside `batches.show` — **NO `Training→Attendance` bucket** | F **SHOULD SHOW** | SHOULD SHOW `Training→Attendance → batches.show attendance tab` alias | LOW | Surface alias keep inside batch not academic-attendance |
| 13 | **Assessments / Exams** | YES Training `ExamController:24 exams.* 175` **visible 298** ; Academic `AcademicAssessment 1182 domain:academic` 403 | **EXISTS+VISIBLE via Exams 298** — training canonical is `Exams` not `Assessments` | **A MUST SHOW** `Exams` | VISIBLE 298 — correct | — | Keep `Exams` |
| 14 | **Marks** | YES Training `POST exams/{exam}/marks 181 ExamController:saveMarks` + `Result mix` **inside exams.show** — NO top bucket ; Academic `assessments.marks.store 1195 academic domain:academic` hidden `?view=marks` academic | **F EXISTS BUT HIDDEN — SHOULD SHOW B (training marks)** `REASON: Training marks has no dedicated nav — academic Marks Entry 245 is academic 403` | **F → B** SHOULD SHOW training marks | **MUST SHOW training** `Training→Marks → exams.marks` alias reusing `ExamController saveMarks` — NOT academic `assessments.marks` | LOW | **SURFACE training Marks** mapping `exams.show→saveMarks` — do not expose `assessments.marks` |
| 15 | **Results** | YES Training `Result::query in ExamController:index` `exams.index tab results` — academic `AcademicFinalResult 1199 academic domain:academic` hidden | **F EXISTS BUT HIDDEN** — via `Exams tab results` only `REASON: no Training→Results bucket` | F **SHOULD SHOW** | SHOULD SHOW `Training→Results → exams.index?tab=results` alias | LOW | Surface alias reuse `Result` |
| 16 | **Certificates** | YES `CertificateController 16 TenantScoped 190 types 1311` | **EXISTS+VISIBLE 301 Training→Certificates** + academic action 1095 403 | **A MUST SHOW** | VISIBLE `certificates.index 190` | — | Keep single model |
| 17 | Academic Years | YES `AcademicYear 1247 academic domain:academic` `placements.index id=academic-years` | **EXISTS+ACADEMIC 403 HIDDEN for MAWA** `Academic→Academic Years 221 isEducation 403` correct | **D ACADEMIC ONLY — MUST REMAIN HIDDEN** `isEducation false` | HIDDEN 403 | — | Keep hidden — training uses Batch year via `academic_year_id` on Batch optional but not `AcademicYear` management |
| 18 | Classes / Groups/Streams / Placements / Assessments(Marks)/ Aggregation/ Grading/ Promotion/ FinalResult/ Transcript / Academic Attendance/Analytics | All academic chain `1182/1195/1172/1163/1199/1217/1237/1101/1114` `domain:academic` | **ALL HIDDEN 403 for MAWA** via `Academic collapsible 204` | **D ACADEMIC ONLY** | HIDDEN | — | Keep hidden — `BUSINESS DECISION REQUIRED` if professional needs similar concept but no safe existing reassignment |
| 19 | Fees | YES `finance.education.* 640 fee-heads/structures/collection 708 module_access:finance` + generic `finance 328` | **HIDDEN as dedicated Training→Fees but VISIBLE via generic Finance 328 when workspaceAllowedFinance` | **C OPTIONAL** | VISIBLE via generic `Finance` — acceptable | LOW | Optionally add `Training→Fees alias 708` when `isProfessional` — reuse same `finance.education` |
| 20 | Reports/Analytics | YES `academic/analytics 159/1114 academic domain` vs `finance/sales/purchase reports` + `reports/hub 1484` | **HIDDEN academic analytics correct, but generic reports visible via Finance/Sales/Purchase** | **C OPTIONAL** | VISIBLE via generic | — | Keep |

**Gap tally for MAWA:** 4 operational gaps `Enrollment, Attendance, Marks, Results` are `EXISTS BUT CURRENTLY HIDDEN` — should become **SHOULD SHOW** via navigation alias reusing existing canonical routes (`batches.*`, `exams.*`, `Result`). 0 gaps for core `Students/Trainers/Courses/Subjects/Curriculum/Batches/Exams/Certificates`. 11 academic-only correctly hidden.

---

## 30. RECOMMENDED PROFESSIONAL TRAINING NAVIGATION (mapped to canonical routes — no duplicate creation)

> Conceptually `Training → Dashboard/Students/Trainers/Courses/Subjects/Curriculum/Batches/Enrollment/Attendance/Assessments-Marks/Results/Certificates/Reports` but **every item below maps to an existing canonical route**; items marked `NOT CURRENTLY IMPLEMENTED` have no safe canonical — `BUSINESS DECISION REQUIRED` if needed later.

| # | Sidebar Label | Canonical Route Name (`php artisan route:list 1211`) | Method URI | Controller:Line | Middleware/Pref | Domain Guard | Why Safe For MAWA |
|---|---------------|------------------------------------------------------|-----------|-----------------|-----------------|--------------|-------------------|
| T01 | Dashboard | `dashboard` `web:116` | `GET /` | `DashboardController:__invoke` | `tenant+verified` | none | Generic — already visible |
| T02 | Business Profile | `business.profile` `349` | `GET business/profile` | `BusinessProfileController:16` `assertTenantMatchesActive:140` | `tenant+verified` | display only | ProfessionalData `276` |
| T03 | **Students / Trainees** | `students.index` `web:139` | `GET students` | `StudentController:140` `Student TenantScoped` | `students.view tenant` | shared | `Student where institute_id MAWA` — single system |
| T04 | **Trainers** | `teachers.index` `web:355/1076` | `GET teachers` `POST assign 1083` | `TeacherController:12` `InstituteUser role teacher` | `tenant` | shared label `Trainers 152` | Single `Teacher/Instructor` system |
| T05 | **Courses** | `courses.manage.index` **canonical** `928` | `GET courses/manage` | `CourseMasterController:44` `Course where institute_id` | `courses.view tenant` | derived `professional` | `InstituteDomain subjectTypeFor` |
| T06 | **Subjects** | `courses.manage.subjects.index` `952` | `GET courses/manage/subjects` | `SubjectManagementController:30` `where institute_id AND subject_type=professional` | `courses.view` | server-derived | Clamp |
| T07 | **Curriculum** | `curricula.index` `900` + `modules 910 lessons 914` nested | `GET curricula` + `POST modules/lessons` | `CurriculumController:31` `TenantScoped availableCourses 397` | `curriculum.view/manage` | hybrid | Training curriculum |
| T08 | **Batches** | `batches.index` `165` + `show/store/update/destroy/status/transfer/remove-student 989` | `GET batches` `GET {batch}` | `BatchController:33` `TenantScoped+BranchScoped` | `batches.view/manage` | via category | Batch `where institute_id` |
| T09 | **Enrollment** | `batches.show` tab enrolled **ALIAS** `batches.index` **or** `admissions.pipeline.index 1004` + `students.enroll 144` | `GET batches/{batch}` `POST students/{student}/enroll` `POST batches/{batch}/transfer` | `BatchController:show` `StudentController:enroll:144` | `batches.view` `students.manage` | shared | Reuse `StudentEnrollment/Batch transfer` — alias `Training→Enrollment → batches.index` filtered `tab=enrolled` |
| T10 | **Attendance** | `batches.show` attendance tab **ALIAS** `batches.index` | `GET batches/{batch}` | `BatchController:show` `Attendance batch` | `batches.view` | shared | Reuse `Attendance where batch_id` — alias `Training→Attendance → batches.index` or `batches.show#attendance` |
| T11 | **Assessments / Exams** | `exams.index` `web:175` `sendToExam 178 show 175 saveMarks 181` | `GET exams` `POST sendToExam` | `ExamController:24` `Exam where institute_id` | `exams.view/manage` | **none (training canonical)** | **Keep `Exams 298`** |
| T12 | **Marks** | `exams.show → exams.marks saveMarks 181` **ALIAS** `exams.marks 1071` | `POST exams/{exam}/marks` | `ExamController:saveMarks 181` | `exams.manage` | none | **Alias `Training→Marks → exams.show#marks` reusing `ExamResult`** — DO NOT map to academic `assessments.marks 1195 domain:academic` |
| T13 | **Results** | `exams.index tab results` `Result::query in ExamController:index` + `certificates.index` | `GET exams?tab=results` `Result where institute_id` | `ExamController:index Result paginator` | `exams.view` | none | **Alias `Training→Results → exams.index?tab=results`** — DO NOT use academic `final-results 1199` |
| T14 | **Certificates** | `certificates.index` `190` `permission:certificates.view` + `certificate-types 1311` | `GET certificates` | `CertificateController:16` `TenantScoped` | `certificates.view` | shared | Single model reuse `Training→Certificates 301` |
| T15 | **Fees** | `finance.education.fee-collection 708` `permission finance.view module_access:finance` **alias OPTIONAL** `finance.education.students/fee-heads/fee-structures 640` | `GET finance/education/fee-collection` | `EducationFinanceController + FeeStructureController` | `finance.view+module_access:finance` | `workspaceAllowedFinance 327` | Reuse existing `finance.education` — alias `Training→Fees → finance.education.fee-collection 708` when `isProfessional && workspaceAllowedFinance` OPTIONAL |
| T16 | **Reports / Analytics** | `reports.hub 1484 hub.show + finance.reports/sales.reports/ purchase.reports` **generic** | `GET reports/hub` | `ReportsHubController` `AccountingReportController` | `tenant` | none | Generic — alias `Training→Reports → reports/hub` — academic `academic/analytics 159 domain:academic` must remain hidden |
| — | **Academic-only example NOT in training nav** | `settings.academic.placements.index?section=academic-years#academic-years 221 academicYears 1247` etc. | — | `StudentAcademicPlacementController` | `education.manage+domain:academic 1144` | `domain:academic` | **Correctly NOT mapped — 403 for MAWA** |

**Every `Training→*` above reuses an existing canonical route name (`route:list 1211`) — 0 duplicate routes.**

---

## 31. BUSINESS DECISIONS REQUIRED (do NOT implement in B14)

| # | Decision | Why Needed | Current State | Recommendation |
|---|----------|------------|---------------|----------------|
| D01 | **Does Training Institute need dedicated `Marks` top nav or keep via `Exams→Show→Marks`?** | Current training marks `exams.marks 181` is nested in `exams.show` — gap `Marks hidden as bucket` exists. Decide if `Training→Marks` should be explicit bucket `exams.index?tab=marks` or keep nested. | `F EXISTS BUT HIDDEN` | **Recommend SHOULD SHOW** alias `Training→Marks` — but needs product owner approval on label `Marks` vs `Exam Results` |
| D02 | **Does Training need `Results` top bucket vs `Exams tab results`?** | `Result` mix via `Result` model institute_id `ExamController` — currently via `Exams` only. | `F HIDDEN` | **Recommend SHOULD SHOW** alias `Training→Results` — approve |
| D03 | **Does Training need `Attendance` top bucket vs `batches.show attendance tab`?** | Batch attendance tab `batches.show` is correct but undiscoverable per gate matrix. | `F HIDDEN` | **Recommend SHOULD SHOW** alias — approve |
| D04 | **Does Training need `Enrollment` top bucket vs `batches.show + students.enroll`?** | `batches.show enrolled tab + students.enroll 144 + admissions.pipeline 1004` is canonical but `admissions.pending 141 isEducation gated` hides for MAWA. | `F HIDDEN` | **Recommend SHOULD SHOW** `Training→Enrollment → batches.show` — approve |
| D05 | **Should `Fees` be dedicated `Training→Fees 708` vs generic `Finance 328`?** | `finance.education.fee-collection 708` exists but only via generic `Finance` when `workspaceAllowedFinance`. No dedicated training fees bucket. | `C OPTIONAL` | **Recommend OPTIONAL** — keep generic unless finance module explicitly required for MAWA |
| D06 | **Should `Reports/Analytics` be `Training→Reports → reports/hub` vs academic analytics?** | `academic/analytics 1114 domain:academic` must remain hidden; `reports/hub 1484` generic exists. | `C OPTIONAL` | **Recommend OPTIONAL** — generic hub `reports.hub` suffices — do not expose academic analytics |
| D07 | **Does MAWA eventually need degree award similar to `Academic Final Result Transcript Promotion`?** | Training `Promotion/Completion 6 steps Course→Batch→Enrollment→Attendance→Assessment→Marks/Result→Promotion/Completion→Certificate` doc `MUST NOT` auto-expose `Academic Promotion 1217 Phases` `FinalResultRow locked/published + PromotionDecision + Transcript 1091`. | `11 ACADEMIC ONLY 403` | **Recommend BUSINESS DECISION REQUIRED — DO NOT EXPOSE` Academic Promotion/FinalResult/Transcript` — if training needs `Completion` concept, define new `Training Completion` using `Result`+`Certificate` not `AcademicFinalResult` — do not implement in B14 |

**All 7 are decisions, not audit failures.**

---

## 32. IMPLEMENTATION PLAN (audit-only proposal — DO NOT EXECUTE UNTIL APPROVED)

| Step | Scope | Action | Files to Edit (when approved) | Risk | Rollback |
|------|-------|--------|------------------------------|------|----------|
| I01 | **Enrollment alias** | `Training→Enrollment` `href batches.index` filtered or `admissions.pipeline 1004` reusing existing `students.enroll 144 + batches.transfer 56` — add inside `Training 285` collapsible after `Batches 295` — gate `isProfessional && workspaceAllowedEducation` same as `Batches` — no new route | `layouts/institute.blade.php 285-304` add `<a href="{{route('batches.index')}}#enrollment" data-enrollment-link>` alias `batches.index` | LOW | `git checkout layouts/institute.blade.php` |
| I02 | **Attendance alias** | `Training→Attendance` `href batches.index` `attendance tab` reuse `batches.show` — same gate | same file add `Attendance` alias | LOW | same |
| I03 | **Marks alias** | `Training→Marks` `href route('exams.index')?tab=marks or exams.show#marks` mapping `ExamController:saveMarks 181` `Result` mix — NOT `assessments.marks 1195 academic 403` | same `Exams 298` after | LOW | same |
| I04 | **Results alias** | `Training→Results` `href exams.index?tab=results` `Result::query` | same | LOW | same |
| I05 | **Fees optional alias** | `Training→Fees` `href finance.education.fee-collection 708` when `isProfessional && workspaceAllowedFinance 327` after `Certificates` | same `Finance` block conditionally | LOW | same |
| I06 | **Reports optional** | `Training→Reports` `href reports.hub 1484` | same | LOW | same |

**All steps reuse canonical names — 0 new routes/controllers/models/views — navigation `GET` only — `VIEW:81/94/113 WORKFLOW verified`. No migration.**

---

## 33. REGRESSION RISKS

| Risk | Likelihood If Implemented As Proposed | Mitigation |
|------|---------------------------------------|------------|
| `Q. Tenant leakage via new nav` — aliases are `GET` to already `tenant+BranchScoped` `where institute_id` routes (`batches, exams, Result where institute_id`) | None — aliases `GET` same `tenant` gated routes | Query not trusted, `InstituteDomain subjectTypeFor` clamp retained |
| `R. Academic exposure to professional` — `settings.academic.* 1144 domain:academic 403` remains; alias avoids it | None if alias maps to `batches/exams` not `settings.academic` | Verify `EnsureDomain` still 403 with `?tab=marks` query not bypass |
| `S. Professional Training UI competes with Academic` | None — `Academic 204 isEducation` `Training 285 isProfessional` mutually exclusive per `InstituteDomain` `isAcademic` vs `isProfessional` via `academicNavGroup vs training` | Keep `Academic→Professional→Other` switching `16/16 PASS` |
| `T. Adult training subjects leak academic` | None — `subjectTypeFor professional` fixed `62/79` | Keep `Rule::exists where institute_id & subject_type` |
| `U. Calculation/history regression` | None — `Assessment locking 1190 + Result finalization 1199 + optional bonus 2.00/5.00` untouched | Audit only — no `Academic*` logic touched |

---

## 34. ROLLBACK PLAN (if implementation after approval fails)

| Scenario | Action |
|----------|--------|
| Navigation alias introduces `500` due to typo `route('batches.index')` | `php artisan route:list 1211` re-check names; `git checkout HEAD -- layouts/institute.blade.php && php artisan view:clear` |
| Tenant test `BusinessProfile 16/16` fails `cross business profile blocked` | Revert aliases gateway `isProfessional && workspaceAllowedEducation` matches `Training 285`; ensure no `industry==='education'` hardcode added |
| Academic `domain:academic` 403 regression | Verify `EnsureDomain 11` not removed; aliases map to `batches/exams` not `settings.academic` |

---

## 35. FINAL VERDICT

| Dimension | Pass/Fail | Note |
|-----------|-----------|------|
| MAWA domain `training_center/training_institute → professional` | **PASS** — `InstituteDomain::PROFESSIONAL` `professional` — DO NOT CONVERT |
| Current navigation state audited | **PASS** — 6/6 Training visible (`Courses/Subjects/Curriculum/Batches/Exams/Certificates+Trainers 150`), 4 gaps `Enrollment/Attendance/Marks/Results` EXISTS+HIDDEN via `batches.show + exams.show/marks + Result` but no top Training bucket |
| Module inventory 35 inspected | **PASS — file:line current vs expected documented R01-R19** |
| Student/Trainee reusable | **PASS — single Student `139 TenantScoped` reused, label `Students` 136** |
| Teacher/Trainer reusable | **PASS — single Teacher `355/1076` label `Trainers 152`** |
| Course/Subject/Category/Curriculum/Modules/Lessons/Batch reusable | **PASS — all `TenantScoped+InstituteDomain derived professional` MAWA-only** |
| Enrollment / Attendance / Assessments / Marks / Results / Certificates reusable | **PASS — training canonical is `batches.* + students.enroll + exams.* + exams.marks + Result mix + certificates.index`** — academic `AcademicAssessment 1182 marks 1195 grading 1163` correctly **ACADEMIC ONLY 403** for MAWA |
| Academic-only protection | **PASS — 11 MUST REMAIN HIDDEN** `Academic Years, Classes, Groups, Placement, Assessment, Marks(academic), Aggregation, Grade Scales, Promotion, Final/Published Results, Transcript, Academic Attendance/Analytics` — no business decision to expose | 
| Route mapping `1211` | **PASS — every training item maps to existing canonical `route:list` name, 0 duplicate routes** |
| Controller/view/permission/domain/tenant/branch mapping | **PASS — TenantScoped/BranchScoped + workspaceAllowed* + permission students.view/batches.view/exams.view/certificates.view + domain:academic 403 intact** |
| Multi-business Academic→Professional→Other→Academic | **PASS — 16/16 BusinessProfile + 4/4 Tenant isolation** |
| Legacy/Duplicate | **PASS — courses/archive legacy not primary, single Teacher/Assessment split correctly by domain** |
| Navigation gaps | **4 GAPS EXISTS+HIDDEN (Enrollment, Attendance, Marks, Results)** + 2 OPTIONAL (Fees, Reports) — surface via alias reusing canonical, not new architecture |
| Business decisions required | **7 decisions D01-D07** — all should-have/optional, not implementation |
| Regression risks | **0 new — all alias GET to tenant-gated routes** |

```
PHASE: B14
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211)
ROUTES MODIFIED: NO
VIEWS MODIFIED: NO (AUDIT ONLY)
CONTROLLERS MODIFIED: NO
SERVICES MODIFIED: NO

CURRENT_MAWA_DOMAIN: training_center/training_institute → professional (InstituteDomain::PROFESSIONAL) — KEEP
CURRENT_NAV: 6 Training visible (Courses/Subjects/Curriculum/Batches/Exams/Certificates+Trainers) + 4 EXISTS+HIDDEN via batch/exam tabs (Enrollment/Attendance/Marks/Results) + 11 academic-only correctly 403 — gap is navigation not backend
REUSABILITY: Students/Trainers/Courses/Subjects/Curriculum/Batches/Enrollment/Attendance/Exam-Marks/Result/Certificate REUSABLE single systems — NO duplicate Teacher/Student/Course
ACADEMIC_ONLY: 11 MUST REMAIN HIDDEN (Academic Years/Class/Groups/Structure/Placement/Assessment/Marks/Grading/Promotion/Final/Transcript/Academic Attendance/Analytics)
GAPS: 4 EXISTS+HIDDEN → SHOULD SHOW (Enrollment/Attendance/Marks/Results) + 2 OPTIONAL (Fees/Reports)
ROUTE_MAP: every training item maps to existing canonical route:list 1211 — 0 duplicate routes
TENANT_ISOLATION: PASS — where institute_id + InstituteDomain subjectTypeFor professional + Rule exists where institute_id
DOMAIN_ISOLATION: PASS — domain:academic 403 for MAWA, query not bypass, hidden UI not sole defense
RBAC: PASS — students.view/batches.view/exams.view/certificates.view + education.manage+domain:academic stays academic only
IDOR: PASS — never trust institute_id, subject_type derived, cross-tenant 403 via TenantScoped
MULTI_BUSINESS: PASS — BusinessProfile 16/16 + Tenant isolation 4/4 — follows active_institution_id
LEGACY: courses/archive legacy hidden, Academic  single systems correctly split by domain
BUSINESS_DECISIONS: 7 (D01-D07) — Enrollment/Attendance/Marks/Results/Fees/Reports/Completion concept
REGRESSION_RISKS: 0 new when alias GET to tenant-gated routes
ROLLBACK: git checkout -- layouts/institute.blade.php && view:clear

DATA_MODIFIED: NO
DATA_DELETED: NO
MIGRATIONS: NO
NEW_DATA: NO
FINAL_VERDICT: GREEN — READY FOR GATE 2 APPROVAL BEFORE IMPLEMENTATION
```

**GREEN — MAWA Academy correctly remains `Training Center → Training Institute → professional`; substantial training backend (`Student/Teacher/Course/Subject/Curriculum/Batch/Enrollment/Attendance/Exam-Marks/Result/Certificate`) exists and is safe to surface via navigation alias reusing existing canonical routes (`route:list 1211`) with `TenantScoped/BranchScoped + InstituteDomain professional + EnsureDomain 403`; 11 academic-only concepts (`Academic Years/Class/Groups/Placement/Assessment/Marks/Grading/Promotion/Final/Transcript/Academic Attendance/Analytics`) must remain hidden; 4 operational gaps (`Enrollment/Attendance/Marks/Results` inside `batches.show/exams.show`) + 2 optional (`Fees 708/Reports`) are navigation-only aliases — 0 migrations, 0 duplicate systems, 0 tenant leaks — audit approved for implementation after product review.**

---

> STOP — B14 FORENSIC AUDIT COMPLETE. DO NOT START IMPLEMENTATION UNTIL AUDIT IS REVIEWED AND APPROVED. Next after approval: **Add 4 aliases inside `Training 285-304` reusing `batches.show enroll/attendance + exams.show saveMarks + Result mix` + optionally `fees 708 / reports hub` — `layouts/institute.blade.php` only — no new controllers/models/tables — `route:list 1211` unchanged.**

*Evidence: `config/industry_rules:52 training_center 5` + `InstituteDomain:31 PROFESSIONAL_TYPES` + `Institute:30 hasDomainData` + `CourseMaster:44 where institute_id` + `SubjectManagement:79 derived professional` + `Curriculum:397 availableCourses` + `Batch:TenantScoped+BranchScoped 60` + `Teacher:12 InstituteUser role teacher Trainers 152` + `ExamController:24 exams.* 175 saveMarks 181` + `Certificate:16 TenantScoped 190` + `Student:TenantScoped enroll 144` + `routes/web:165 batches + 139 students + 175 exams + 190 certificates` + `institute_modules 1144 academic domain:academic 403 + 928 courses.manage canonical + 952 subjects + 900 curricula + academic Years 1247 placements 1237 assessments 1182 grading 1163` + `layouts:284-304 Training 6 isProfessional 205 Academic isEducation hidden + _tabs isAcademic:8` + `Workspace 44 set/clear BranchContext + EnsureDomain 11 403` + `TenantContext SetTenantContext:26` + `php artisan route:list 1211 + view:clear INFO + BusinessProfile 16/16 PASS + Tenant 4/4 PASS` + `SubjectUnification none`.*
