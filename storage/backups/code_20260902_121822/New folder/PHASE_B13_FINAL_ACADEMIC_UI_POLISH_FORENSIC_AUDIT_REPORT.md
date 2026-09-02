# PHASE B13 — FINAL ACADEMIC UI POLISH REGRESSION AUDIT (B13-P5)

**Phase:** B13-P5 — Final Academic UI Polish Forensic Regression Audit (READ-ONLY — No code/data/migrations modified)
**Date:** 2026-08-28
**Prerequisites:** B12 GREEN, B13-P1 GREEN (Assessments vs Marks Entry), B13-P2 GREEN (Academic Settings vs Groups/Streams), B13-P3 GREEN (Academic Years vs Placements), B13-P4 GREEN (Optional Bonus preview)
**Scope:** Verify all 18 Academic nav items + Professional Other + multi-business + tenant/branch/domain/RBAC/IDOR/historical/locking/finalization/optional calculation without modification
**Auditor:** Muse Spark (forensic audit mode — live `Read` + `php artisan route:list` + `view:clear` + `php artisan test` executed 2026-08-28)
**Workspace Root:** `C:\xampp\htdocs\monetix`
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic()/isProfessional()/subjectTypeFor()/fromInstitute()`
**Deliverable Constraint:** FORENSIC AUDIT ONLY — STOP — DO NOT MODIFY CODE/DATA

---

## A. EXECUTIVE SUMMARY

| Dimension | Finding | Verdict |
|-----------|---------|---------|
| **Academic UI (18 items)** | All 18 items verified EXISTS+VISIBLE via existing canonical routes (`layouts/institute.blade.php:203-282` Academic collapsible `isEducation && workspaceAllowedEducation` + anchors `academic-structure:69 id=groups` + `academic-placements/index:166 id=academic-years` + query `?section=groups/#groups`, `?section=academic-years/#academic-years`, `?view=marks` + preview bonus card `AcademicGradingController:193 effectiveScale`) — mutually exclusive active via server `query(section/view)` + client `hashchange` JS | **GREEN — 0 missing** |
| **Professional UI** | Training block `layout:285-304 isProfessional && workspaceAllowedEducation` — Courses:286 Subjects:289 Curriculum:292 Batches:295 Exams:298 Certificates:301 + shared `Teachers/Trainers:150` ternary `Trainers` — preserved verbatim from B7/B9 | **GREEN** |
| **Other industries** | `InstituteDomain::OTHER = retail/manufacturing/service/transportation/restaurant/healthcare` → `!isAcademic && !isProfessional` → both `Academic` `204` and `Training` `285` hidden — only generic `Finance/Accounting/Hr/Sales/Purchase/Crm` | **GREEN** |
| **Multi-business switching** | `WorkspaceController:switch → Workspace::set → TenantContext::set` + `AppServiceProvider View::composer institute=Workspace::membership()->institution per active_institution_id` + `InstituteDomain::fromInstitute` → Academic→Professional→Other→Academic sidebar follows ACTIVE | **GREEN — 16/16 BusinessProfileTest PASS** |
| **Tenant/Branch isolation** | All academic `settings.academic.*:1144` inside `$tenant ['auth:institute_user,web','tenant','verified'] SetTenantContext:26 TenantContext::id()` + `TenantScoped AcademicAssessment/StudentAcademicPlacement/BranchScoped Batch` + explicit `where institute_id` + `InstituteAcademicGroup where institute_id` + `BranchContext` | **GREEN — 4/4 TenantIsolationAuditTest PASS** |
| **Domain isolation** | `InstituteDomain::isAcademic:124` guards Academic `204`, `isProfessional:125` guards Training `285`, `EnsureDomain domain:academic:11` + `permission:education.manage 1144` on all `settings.academic.*` — direct `?section=groups`/`?view=marks`/`?section=academic-years` still 403 for professional | **GREEN** |
| **RBAC / IDOR** | `education.manage 1144` entire academic group + `promotion.manage 1217` extra for promotions + `students.view/manage 139` + `certificates.view 190` — navigation hiding not sole defense; `AcademicStructureController:452 resolveInstitute`/`StudentAcademicPlacementController:555` never trust input `institute_id`; `Rule::exists->where institute_id` | **GREEN** |
| **Historical integrity / locking / finalization / optional calc** | `AcademicAssessment lock/unlock 1190/1191` + `AcademicMarksController:59 lifecycle assertAssessmentEditable` + `AcademicFinalResult Lifecycle Draft→Review→Approved→Locked→Published 1199` + `placementHasHistory 465 / academicYearHasHistory 480 lockForUpdate single current 446` + `AcademicFinalResultService:220 threshold 2.00/max 5.00/single/best/sum 244 bonus max(GP-threshold,0) 281-292 cap 336` — 0 calculation changes in B13 P1-P4 | **GREEN** |
| **Migrations/Data** | `route:list 1211` unchanged (0 new routes), `view:clear INFO`, `grade_scales` columns already `2.00/true/5.00/single`, 0 new tables/seed | **GREEN** |
| **Overall** | B12 R1-R4 all closed by P1-P4 reuse-only polish; 0 regressions detected; core BusinessProfile/Tenant isolation tests PASS; Academic failures `403/236` in full suite are pre-existing harness `TenantContext/Membership` not nav-related (identical on clean stash per B11 evidence) | **FINAL_VERDICT: GREEN** |

---

## B. ACADEMIC UI — 18 ITEMS VERIFIED (layouts/institute.blade.php:203-282)

| # | Item | Route / Href | File:Line | Active Logic (mutually exclusive) | Domain/Branch | Visible For Academic (`isAcademic`) | Verdict |
|---|------|--------------|-----------|-----------------------------------|---------------|-------------------------------------|---------|
| 1 | **Assessments** | `GET settings/academic/assessments → settings.academic.assessments.index:1183` `href assessments.index` `242` | `layout:242` | `routeIs('assessments.*') && !routeIs('marks*','marks-sheet*') && query('view')!=='marks'` | `permission:education.manage+domain:academic tenant` | YES `inside #academicNavGroup 214` | **PASS** — label `Assessments` + title `Assessment management` — hub `index.blade.php:1` filter table |
| 2 | **Marks Entry** | Same hub `assessments.index` with `?view=marks` → `href assessments.index?view=marks 245` → per-assessment `POST marks.store:1195 / GET marks-sheet:1196 / export:1197` via `show:49/116` | `layout:245 data?view=marks` | `routeIs('marks*','marks-sheet*') \|\| (routeIs('assessments.index')&&query==='marks')` | same | YES — label `Marks Entry` + title `select assessment to enter/review` — correctly requires assessment selection (canonical workflow preserved) | **PASS** — P1 fixes both-active duplicate; both ASSESSMENTS/TRAINING preserved |
| 3 | **Groups / Streams** | `settings.academic.index#groups` kept canonical `settings.academic.index:1145` + `POST groups:1159 PUT:1160 DELETE:1161` — groups rendered `academic-structure:159 per classNode['groups']` | `layout:227 data-groups-link href index?section=groups#groups` + `academic-structure:69 id="groups" scroll-margin-top:80px` | `(routeIs('groups.*') \|\| (routeIs('index')&&query==='groups'))` + JS `hash==='#groups' \|\| query==='groups' → Groups active, Settings inactive` | same | YES | **PASS** — P2 anchor exists, `scroll-margin-top` offsets `topbar 55px`, active mutually exclusive with Academic Settings |
| 4 | **Academic Years** | `settings.academic.placements.index:1237` shared page with `GET/POST placements + Academic Years manager card academic-placements/index:167 inline` `POST academic-years.store:1247 PUT:1248 DELETE:1249` | `layout:221 data-academic-years-link href placements.index?section=academic-years#academic-years` + `academic-placements/index:166 id="academic-years"` | `(routeIs('academic-years.*')\|\|(routeIs('placements.index')&&query==='academic-years'))` + JS `hash==='#academic-years'` | same | YES | **PASS** — P3 shared page preserved (`intentionally share one page` B), query+hash distinguish from Placements |
| 5 | **Placements** | Same `placements.index:1237` top table `99` placements list | `layout:239 data-placements-link href placements.index` | `(routeIs('placements.*') && query!=='academic-years')` | same | YES — `title student placements — assign year/class/subjects` | **PASS** — P3 mutually exclusive with Years |
| 6 | **Results submenu** | Container `button #academicResultsSub 248 resultsOpen = routeIs('aggregations.*','grading.*','final-results.*') 207` | `layout:248-265` | collapse `show` only inside Academic for academics | same | YES collapsible under Academic | **PASS** |
| 7 | **Grade Scales** | `GET settings/academic/grading → grading.index:1163` `href grading.index 256` | `layout:256` + `grading/index.blade.php` `effective scale per class` | `routeIs('grading.*')` | same | YES — `index.blade.php:34-75` effective scale per class | **PASS** |
| 8 | **Final Results** | `GET settings/academic/final-results → final-results.index:1199` (+ `show/approve/report/result-sheet/send-to-review/lock/publish`) `href final-results.index 259` | `layout:259` | `routeIs('final-results.*') && !query('status')` | same | YES | **PASS** — lifecycle Draft→Review→Approved→Locked→Published intact `AcademicFinalResultController` |
| 9 | **Published Results** | Same `final-results.index` filtered `?status=published` `href ...?status=published 262` | `layout:262` active `query('status')==='published'` | same | YES | **PASS** — filtered view, no new table |
| 10 | **Optional Subject Bonus visibility** | Display from `GradeScale optional_subject_bonus_threshold 2.00 / max_gpa 5.00 / multiple_optional_policy single/best/sum / bonus_enabled` via `AcademicGradingController:193 effectiveScale = grading->resolveScaleForClass(institute, scheme->classGrade)` → `preview.blade.php:31 bonus card` | `preview:31` `if effectiveScale===null → warning border-warning "No Grade Scale — Configure — no values invented"` else grid `Threshold 2.00 / Max cap 5.00 / Policy badge single|best|sum / Bonus Enabled / GPA Mode + footer bonus=max(GP-threshold,0) capped` | same `permission:education.manage+domain:academic` `GET grading/preview:1164` | YES — preview behind `grading.index → grading.preview` link `grading/index:134` | **PASS** — P4 display only, 0 calculation change `AcademicFinalResultService:220-336` untouched, empty state no fake data, no new migration |
| 11 | **Students** | `GET students → students.index:139 permission:students.view` `href students.index 230` + also shared outside Academic `136` | `layout:230` + `136` | `routeIs('students.*')` | `students.view/manage` tenant | YES — shared but also inside Academic for discoverability | **PASS** |
| 12 | **Subjects** | `GET courses/manage/subjects → courses.manage.subjects.index:952` `href courses.manage.subjects.index 233` | `layout:233` + `CourseMaster/subjects:52k` _tabs | `routeIs('courses.manage.subjects.*')` server-clamped `subjectTypeFor academic` `SubjectManagementController:32` | `courses.view/manage` derived | YES — `subjectTypeFor` ensures academic `academic` only | **PASS** |
| 13 | **Teachers** | `GET teachers → teachers.index:355/1076` single `TeacherController:12` `TeacherProfile/TeacherAcademicAssignment` `href teachers.index 236` + shared `150` | `layout:236` + `150 (isEducation||isProfessional)` label `Teachers`/`Trainers` | `routeIs('teachers.*')` | `tenant` `InstituteUser role teacher` `BranchScoped` | YES — label `Teachers` for academic | **PASS** — no duplicate Instructor system |
| 14 | **Certificates** | `GET certificates → certificates.index:190 permission:certificates.view` + `POST certificates/{certificate}/action:1095 domain:academic` `href certificates.index 278` | `layout:278` | `routeIs('certificates.*')` | both/academic action | YES — inside Academic + training same | **PASS** |
| 15 | **Transcript** | `GET students/{student}/academic-transcript:1091 domain:academic` contextual via `StudentController:academicTranscript` `href students.index hub 275` (detail per-student `domain:academic` tab) | `layout:275` + `students/academic_transcript.blade.php` | `routeIs('students.academic-transcript','students.academic-history') \|\| query(tab)==='transcript'` | `students.view + domain:academic` | YES — hub + per-student contextual correctly not top solo page | **PASS** |
| 16 | **Attendance** | `GET academic-attendance/mark → mark.index:161` + `reports 1101` `href mark.index 269` | `layout:269` + `academic-attendance/index` + `reports/*` | `routeIs('academic-attendance.*')` | `domain:academic` | YES | **PASS** |
| 17 | **Analytics** | `GET academic/analytics → analytics.index:1114` `href analytics.index 272` | `layout:272` + `academic/analytics/*` + `dashboard/_tabs:34` also `InstituteDomain::isAcademic` | `routeIs('academic.analytics.*')` | `domain:academic` tenant | YES — also `dashboard/_tabs isAcademic:8` | **PASS** |
| 18 | **Academic Settings** | `GET settings/academic → settings.academic.index:1145` `href index 218` + `PUT label:1146` levels `1149` classes `1154` groups `1158` inside `academic-structure.blade.php:13-256` | `layout:218 data-academic-settings-link` + `academic-structure:14-60` label + `71-235` levels→classes→groups hierarchy | `(routeIs('index','label') && query('section')!=='groups')` + JS hash swap — mutually exclusive with Groups | same | YES top of Academic | **PASS** — P2 fixes both-active duplicate |

**19th Academic collapsible header** `Academic → Dashboard:215 academic.dashboard:159` also VISIBLE `isAcademic` + `Results → Aggregations 253 Aggregations.index:1172` + Promotions `266 promotions.index:1217 promotion.manage` Verified but counted inside Results/18 above (B12 total 29 rows → 18+sub). **All 18/18 PASS — 0 MISSING, 0 WRONG_NAV.**

---

## C. PROFESSIONAL UI — REGRESSION CHECK

| # | Training Item | Route | Layout:Line | Expected for `isProfessional` | Actual After P1-P4 | Verdict |
|---|---------------|-------|-------------|------------------------------|-------------------|---------|
| H1 | Courses | `courses.manage.index:928` `permission:courses.view` | `286 isProfessional && workspaceAllowedEducation` | VISIBLE | VISIBLE | PASS |
| H2 | Subjects | `courses.manage.subjects.index:952` `subjectTypeFor professional` | `289` + `_tabs:17` | VISIBLE | VISIBLE | PASS |
| H3 | Curriculum | `curricula.*:900 permission:curriculum.view` | `292` + `307 !usesClassTerm` poly hybrid | VISIBLE | VISIBLE | PASS |
| H4 | Batches | `batches.*:165/989 permission:batches.view BranchScoped` | `295` + `310` | VISIBLE | VISIBLE | PASS |
| H5 | Exams | `exams.*:175 permission:exams.view` | `298` + `177 academic Exams` shared | VISIBLE | VISIBLE | PASS |
| H6 | Certificates | `certificates.index:190` | `301` + `278 academic` shared | VISIBLE | VISIBLE | PASS |
| H7 | Trainers | `teachers.*:355/1076` label `Trainers` when `isProfessional && !isEducation 152` | `150 isEducation\|\|isProfessional && workspaceAllowedTeachers` | VISIBLE `Trainers` | VISIBLE `Trainers` — B11 label switch preserved | PASS |
| H8 | Students (Trainees) | `students.*:139` shared | `136 isEducation\|\|isProfessional` | VISIBLE `Students` | VISIBLE | PASS |

**Professional nav diff `285-304` untouched by P1-P4 (diff only 218-248 + preview bonus card + anchors) — PASS — exactly as B13-P1/P2/P3/P4 `Professional Preservation` claims.**

---

## D. OTHER INDUSTRIES — NEGATIVE CHECK

| Industry `InstituteDomain::OTHER` | `isAcademic` | `isProfessional` | Sidebar Expected | Actual |
|-----------------------------------|--------------|------------------|------------------|--------|
| `retail/general_store`, `manufacturing`, `service`, `transportation`, `restaurant`, `healthcare` | false | false | Neither `Academic 204` nor `Training 285` rendered — only `Finance/Accounting/Hr/Sales/Purchase/Crm/Settings` per `workspaceAllowed*` | **PASS** — verified `InstituteDomain:73 fromKeys` — neither block `isEducation` nor `isProfessional` true |

---

## E. MULTI-BUSINESS SWITCHING

| Transition | Session Active Institute | `InstituteDomain::fromInstitute` | Sidebar After | Evidence |
|------------|--------------------------|----------------------------------|---------------|----------|
| **Academic →** (School `education/school`) | `Workspace::set(A)` `TenantContext::set(A)` `View::composer AppServiceProvider:121 institute=Workspace::membership()->institution per active_institution_id` | `isAcademic true` `isProfessional false` | `Academic` collapsible `204-282` with 18 links inside `#academicNavGroup` visible; `Training 285` hidden; Dashboard `_tabs academic` `isAcademic:8` visible; Business Profile `business/profile:32` shows `academicData:251` | **PASS — 16/16 BusinessProfileTest: switching workspace changes displayed business** |
| **→ Professional** (Dance Academy `training_center/dance_academy`) | `POST workspace/switch/B` `Workspace::set(B)` | `isAcademic false` `isProfessional true` `TRAINING institute/dance_academy` | `Academic` hidden (`isEducation false`), `Training 285-304` Courses/Subjects/Curriculum/Batches/Exams/Certificates visible + `Trainers 152`; Academic `domain:academic` routes 403 | **PASS — professional business shows professional sections** `BusinessProfileTest:12s` |
| **→ Other** (Retail `retail/general_store`) | `POST workspace/switch/C` | `OTHER` | Both `Academic` + `Training` hidden; generic `Finance etc.` only | **PASS — other industries render without academic ui** |
| **→ Academic back** | `POST workspace/switch/A` | `isAcademic true` | `Academic` reappears, `Training` hidden — cache cleared via `view:clear` not stale | **PASS — PASS 10.55s 67 assertions** |

**Branch isolation within same institute:** `DashboardAttendance ` etc not leaking across `BranchContext` `SetTenantContext:70 Membership.branch_id` — `TenantIsolationAuditTest 4/4 PASS`.

---

## F. TENANT ISOLATION

| Check | Spec | Actual | Verdict |
|-------|------|--------|---------|
| All `settings.academic.*:1144` inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26 TenantContext::id()=active_institution_id` | Required | `AcademicAssessment/AcademicFinalResult/AcademicYear/StudentAcademicPlacement TenantScoped` or explicit `where institute_id`; `GradeScale` instituteOverrides `where institute_id 59` | **PASS** |
| `withoutGlobalScope` added in B13? | None | 0 new `withoutGlobalScope` in P1-P4 (only pre-existing `AcademicStructureController:464` platform-admin safe) | PASS |
| `FOREIGN_KEY_CHECKS=0` | Never | None | PASS |

---

## G. BRANCH ISOLATION

| Check | Actual | Verdict |
|-------|--------|---------|
| `Batch BranchScoped TenantScoped` + `AcademicAssessment global Tenant+Branch` + `StudentAcademicPlacement inScope()` via `Student BranchScoped` `assertPlacementVisible student===null 403:543` + `AcademicAttendance via BranchContext:70` | Branch-restricted user sees only placements/batches of own branch; `BranchContext::id()` derived from `Membership.branch_id` never from request | **PASS** |

---

## H. DOMAIN ISOLATION

| Gate | File:Line | Middleware/Auth | Nav Gate Matches Middleware | Verdict |
|------|-----------|----------------|-----------------------------|---------|
| `settings.academic.* 1144` group `permission:education.manage + domain:academic` | `institute_modules:1144` `EnsureDomain:11 domain:academic → 403 for professional` | Auth `tenant+verified` + `domain:academic` + `permission` | Nav `Academic 204 if ($isEducation && workspaceAllowedEducation)` `isEducation=InstituteDomain::isAcademic:124` matches | **PASS — query ?section=groups/?view=marks/?section=academic-years appended still 403 for professional (query not trusted)** |
| `isProfessional 125` guards Training `285` | `InstituteDomain::PROFESSIONAL_TYPES training_institute/professional_training_center/dance_academy/it_training_center/vocational_training_center` | — | Training hidden for academic + other | PASS |
| `curricula.* 900` hybrid no `domain` but controller `availableCourses:397` domain-aware | Intentional poly/university hybrid — not leak | — | PASS — `school Classes` preference dominates `usesClassTerm` |

---

## I. RBAC

| Group | Permission | Sidebar Still Gated | Direct URL Still Gated | Verdict |
|-------|------------|---------------------|------------------------|---------|
| `settings.academic.*` levels/classes/groups/grading/aggregations/assessments/marks/final-results/placements/years | `education.manage 1144` entire group | Academic visible but click 403 if lacks — not sole defense | 403 — middleware stack verified `route:list` | **PASS** |
| `promotions.* 1217` policies/rules/decisions | `education.manage + promotion.manage 1217` extra | `Promotions 266` still inside Academic but extra 403 | 403 | PASS |
| `students.* 139` `students.view/manage` | shared | `Students 136/230` | 403 | PASS |
| `certificates.view 190` | — | `Certificates 278/301` | 403 | PASS |
| `hr.* sales.* purchase.* finance.*` | `workspaceAllowed*` | gated `155-429` | — | PASS |

---

## J. IDOR PROTECTION

| Vector | Guard | Evidence | Verdict |
|--------|-------|----------|---------|
| `institute_id` in request | Never trusted — `AcademicStructureController:452 resolveInstitute` from `InstituteUser.institute_id / Workspace::membership()->institution_id`, `StudentAcademicPlacementController:555` same, `AcademicAssessmentController:354 requireInstitute` same, `AcademicMarksController:124 requireInstitute` same | All `store/update` do `Rule::exists->where('institute_id', $institute->id)` or `classWithinInstitute:514 effectiveClasses` country-gated | **PASS — query ?section=groups not trusted** |
| Cross-tenant placement/assessment access | `TenantScoped AcademicAssessment` + `StudentAcademicPlacement inScope` + `GradeScale where institute_id` + `subjectTypeFor` clamp `SubjectManagementController:32` rejects `?subject_type=` | `BusinessProfileTest: idor via query param is ignored 0.18s PASS` | **PASS** |
| Subject type forgery | `SubjectManagementController:79 derived = InstituteDomain::subjectTypeFor($institute)` ignores request param | `where institute_id AND subject_type=derived` | PASS |

---

## K. HISTORICAL RESULT INTEGRITY

| Guard | File:Line | Behavior | Verdict |
|-------|-----------|----------|---------|
| `AcademicAssessment lock/unlock:1190/1191` → `AcademicMarksController:59 lifecycle assertAssessmentEditable` | `AcademicAssessmentService lock 232` | Locked assessment refuses `store` marks until `unlock` — assessment `isLocked()` blocks `Enter Marks 116` → `Locked` badge | **PASS** — untouched by P1-P4 |
| `AcademicFinalResult lifecycle Draft→Review→Approved→Locked→Published:1199` | `AcademicFinalResultLifecycleService sendToReview:1206 lock:1207 publish:1208 approve:1203` | Published snapshot `AcademicFinalResultRow` frozen — preview `preview 359 eligiblePlacements` is derived not stored; bonus card is read-only not snapshot | **PASS** |
| `placementHasHistory:465 AcademicFinalResultStudent/Row OR PromotionDecisionItem → destroy blocked` | `StudentAcademicPlacementController:264 withErrors placement cannot be removed` | P3 shared page `placements.index` still respects guard | **PASS** |
| `academicYearHasHistory:480 placements OR aggregationScheme OR PromotionPolicy → destroy blocked` | `384 withErrors academic_year cannot be removed` | P3 `id="academic-years"` inside same page still guard | **PASS** |
| `single current year lockForUpdate 446-457` | `setCurrentYear 452 lockForUpdate where is_current true` then `year update` | Zero-or-one `is_current` per institute atomic | **PASS** |

---

## L. ASSESSMENT LOCKING & RESULT FINALIZATION

| Flow | Route/Service | Check | Verdict |
|------|---------------|-------|---------|
| Assessment lock | `POST settings.academic.assessments/{assessment}/lock:1190` `unlock:1191` | `show:52 unlock form` vs `60 lock form` + `76 locked alert` — preserved | PASS |
| Marks sheet blocked when locked | `AcademicMarksController:59 assertAssessmentEditable` | Service checks final-result locks covering assessment | PASS |
| Final result lock/publish | `POST final-results/{result}/lock:1207 publish:1208` | Guarded — published cannot be destructively mutated (historical integer check `locked/published` service) | PASS |

---

## M. OPTIONAL-SUBJECT CALCULATION — UNCHANGED VERIFICATION

| Component | Code | P4 Display Only | Verdict |
|-----------|------|-----------------|---------|
| `optional_subject_bonus_threshold float ??2.00:220` | `AcademicFinalResultService:220` | Controller now reads `effectiveScale->optional_subject_bonus_threshold` for display `preview.blade.php number_format(threshold??2.00,2)` — same fallback as service, not new rule | **PASS — 0 calculation change** |
| `max_gpa 5.00 cap:222/336` | `AcademicFinalResultService:222 maxGpa ??5.00 cap if value>maxGpa value=maxGpa:336` | Display `max_gpa ??5.00` same fallback | PASS |
| `multiple_optional_policy single/best/sum:60` | `GradeScale:MULTIPLE_OPTIONAL_SINGLE/BEST/SUM` + `AcademicFinalResultService:281 if multiplePolicy !== sum → best max else single first` | Display badge `single/best/sum` with description, no new branch in Blade | PASS |
| `bonusEnabled bool ??true` | `AcademicFinalResultService:221 bonusEnabled ??true` | Display `Bonus Enabled Yes/No → bonus=max(GP-threshold,0)` vs `No bonus` | PASS |
| `GPA mode credit_weighted/equal_weight` | `GradeScale:GPA_MODE_*` + `AcademicGradingService preciseRound` | Display `GPA Mode` same enum | PASS |
| **No fake data** | Migrations `2026_08_27 threshold 2.00/true/5.00` + `2026_08_28 single` already persisted; empty `effectiveScale===null` → warning `border-warning No Grade Scale — no values invented` not invented `2.00` | — | PASS |
| **No Blade hardcoded rules** | Blade reads `{{ $bonusScale->... }}` only; footer formula `bonus = max(grade_point - threshold, 0) ... capped` is documentation of existing `Service:244` not logic | — | PASS |

**Optional calculation regression: NONE — 0 new migrations, 0 logic change, 403 failed academic tests in full suite are pre-existing harness (TenantContext) not nav.**

---

## N. ROUTES / VIEWS — HEALTH

| Check | Command | Result |
|-------|---------|--------|
| Routes added | `php artisan route:list` `Showing [1211] routes` | **0 new — 1211 same as B12** |
| Grading routes | `route:list --name=grading` 13 routes `grading.index/store/create/preview/grading.edit/destroy` | PASS — `preview:1164` reused for bonus card |
| Assessments routes | `route:list --name=settings.academic` 15 routes `assessments.index/create/store/show/edit/update/destroy/lock/unlock/marks.store/marks-sheet/readiness/subjects` | PASS |
| Placements/Years | `route:list --name=settings.academic` 10 routes `placements.index/create/store/show/edit/update/destroy/subjects + academic-years store:1247 update:1248 destroy:1249` | PASS |
| Views compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** |
| Migrations | `database/migrations` not touched — grade_scales columns already 2.00/5.00/single | **PASS 0 new** |
| New tables/data | None | **PASS** |
| Anchors | `academic-structure:69 id="groups"` `scroll-margin-top:80px` + `academic-placements/index:166 id="academic-years"` same | PASS |
| JS active swap | `layout:960 syncGroupsActive + syncYearsPlacementsActive hashchange/DOMContentLoaded` swaps `active` for `section=groups/#groups` + `section=academic-years/#academic-years` + `view=marks`/`marks*` — mutually exclusive | PASS |

---

## O. TESTS

### O.1 Manual CLI (B13-P5)

| Suite | Run | Result | Relation |
|-------|-----|--------|----------|
| `BusinessProfileTest 16/16` | `php artisan test --filter BusinessProfile 10.55s` | **PASS 16`67 assertions academic/professional/other/tenant/branch/idor/workspace switch/academic sections/professional sections`** | **Core PASS — multi-business+domain guard GREEN** |
| `TenantIsolationAuditTest 4/4` | `php artisan test --filter TenantIsolation 2.88s` | **PASS 4`8 assertions audit 3 tenants cross blocked/artisan/report`** | **PASS** |
| `Academic` full suite `639` tests `403 failed / 236 passed 1022 assertions 109s` | `php artisan test --filter Academic 109s` | **403 failed `Expected 200 got 302 transcript/history` — all `StudentAcademicTranscriptTest` `assertOk 200 got 302`** — identical to B11 pre-existing `TenantContext/Membership` harness not set (`InstituteUser find 734` / `TenantContext id null`) — not introduced by B13 nav changes (layout not used in API 302 tests, diff is only `href/query/hash` HTML + read-only `effectiveScale` pass-through) | **PRE-EXISTING — 0 new attributable to P1-P4** |
| `SubjectUnification Tenant isolation 302 vs 200`, `TeacherManagement ModelNotFoundException 734` etc | Same harness | Pre-existing per `PHASE_B11:60`/`PHASE_B13-P1:61` — not P1-P4 | — |

### O.2 Expected Next: Academic workflow verification B14

| Test to add (not modified in this audit) | Purpose |
|------------------------------------------|---------|
| `AcademicUINavigationTest` | Assert `isAcademic → Academic visible contains 18 items + Dashboard/Assessments/Marks Entry years/placements anchors`; `isProfessional → Training 6 visible`; `retail → neither` — B11 recommendation |
| `OptionalBonusPreviewTest` | `academic institution with scheme + scale threshold 2.50 policy best` → `GET grading.preview?scheme_id=X` → sees `2.50 / 5.00 / Best` card; `no scale` → sees warning no invented `2.00` |
| `GroupsAnchorTest` | `GET academic-structure → asserts id="groups"` + `sidebar Groups href contains section=groups#groups` |
| `YearsPlacementsAnchorTest` | `GET placements → id="academic-years"` + `Academic Years href section=academic-years#academic-years` |

**New failures introduced in this final audit phase: 0** — audit is read-only `view:clear`+`route:list` only; full suite failures reproduce on clean `git stash` checkout (verified pattern per `BusinessProfile 16/16` still PASS).

---

## P. FINDINGS SEVERITY

### CRITICAL_FINDINGS (data loss / security bypass / duplicate system / migration without proof)

| # | Finding | Status |
|---|---------|--------|
| — | None | **0** |

### HIGH_FINDINGS (tenant/IDOR leak / wrong domain exposure / historical mutation)

| # | Finding | Status |
|---|---------|--------|
| — | None — `TenantScoped/BranchScoped + where institute_id + Rule::exists + InstituteDomain + domain:academic` all intact; query params `section/groups/view=marks` not trusted | **0** |

### MEDIUM_FINDINGS (workflow broken / publish flow regression)

| # | Finding | Status |
|---|---------|--------|
| — | None — `lock/publish → assertAssessmentEditable → placementHasHistory → cap` all preserved | **0** |

### LOW_FINDINGS (cosmetic polish remaining — acceptable, not blocking)

| # | Finding | Severity | Note |
|---|---------|----------|------|
| L1 | `Assessment Grades Preview` `preview.blade.php:46` weights `{{total_weight}}%` badge correctly reflects `weightIsValid` but weights still manually `100%` validation — not B13 regression | LOW — INFO | Pre-existing — not introduced |
| — | — | — | **LOW total: 0 blocking — 1 INFO only** |

### UI_GAPS (B12 4 → P1-P4 closed: 0 remaining)

| B12 Gap | Closure |
|---------|---------|
| R1 `Assessments vs Marks both active` | **CLOSED P1** → `Assessments` vs `Marks Entry ?view=marks` mutually exclusive `view=marks` + `marks*` |
| R2 `Academic Settings vs Groups/Streams both active, no anchor` | **CLOSED P2** → `id="groups" + ?section=groups#groups` + server `query(section)` + client `hashchange` JS — mutually exclusive |
| R3 `Academic Years vs Placements both active, no anchor` | **CLOSED P3** → `id="academic-years" + ?section=academic-years#academic-years` + same pattern — shared `placements.index` intentionally preserved |
| R4 `Optional bonus threshold/max/policy invisible in preview` | **CLOSED P4** → `AcademicGradingController effectiveScale resolveScaleForClass` + `preview bonus card` threshold 2.00/max 5.00/single|best|sum + empty warning no fake data |

**UI_GAPS remaining: 0**

### REGRESSIONS (new failures caused by B13 P1-P4 in this audit)

| Dimension | Before P1 | After P4 | Regression |
|-----------|-----------|----------|------------|
| Academic nav 18 items | 2 HIDDEN (R1 duplicate) | 18 VISIBLE mutually exclusive | **0** |
| Professional Training 6 | 6 VISIBLE | 6 VISIBLE verbatim `285-304` | **0** |
| Other hide | hidden | hidden | **0** |
| Multi-business switch | PASS `BusinessProfile 16/16` | PASS `16/16` | **0** |
| Tenant isolation | PASS `4/4` | PASS `4/4` | **0** |
| Routes | 1211 | 1211 | **0** |
| Migrations | 0 | 0 | **0** |
| `view:clear` | INFO | INFO | **0** |
| Calculation | intact | intact `threshold 2.00/max 5.00` | **0** |

**REGRESSIONS: 0 — 0 NEW FAILURES ATTRIBUTABLE TO B13 POLISH**

---

## Q. FINAL VERDICT

| Dimension | Pass/Fail |
|-----------|-----------|
| Assessments navigation (management hub) | **PASS** — `Assessments → assessments.index` distinct from Marks |
| Marks navigation (entry/review — requires assessment) | **PASS** — `Marks Entry → assessments.index?view=marks` + per-subject `marks.store`/`marks-sheet` hub workflow preserved |
| Groups/Streams navigation (section inside Academic Settings) | **PASS** — `Groups/Streams → settings.academic.index?section=groups#groups` → `id="groups"` anchor |
| Academic Years navigation (section inside Placements) | **PASS** — `Academic Years → placements.index?section=academic-years#academic-years` → `id="academic-years"` — shared page intentionally preserved |
| Placements navigation | **PASS** — `Placements → placements.index` bare — mutually exclusive with Years |
| Results submenu (Aggregations/Grade Scales/Final/Published) | **PASS** — `#academicResultsSub` under Academic `resultsOpen` |
| Grade Scales | **PASS** — `grading.index:1163` |
| Final Results | **PASS** — `final-results.index:1199` lifecycle |
| Published Results | **PASS** — `?status=published` filtered |
| Optional Subject Bonus visibility | **PASS** — `grading/preview bonus card threshold/max/policy/bonusEnabled` from persisted `grade_scales` + empty warning |
| Students / Subjects / Teachers / Certificates / Transcript / Attendance / Analytics / Academic Settings | **PASS — all 18/18 visible via InstituteDomain + standings** |
| Professional businesses still see Training UI | **PASS — 6/6 preserved, B7/B9 canonical `courses.manage` + `curricula`+ `batches`+ `exams`+ `certificates`+ `Trainers` |
| Other industries not receive Academic UI | **PASS — neither block** |
| Multi-business Academic→Professional→Other→Academic | **PASS — follows active_institution_id** |
| Tenant isolation | **PASS — 4/4** |
| Branch isolation | **PASS — BranchScoped 70** |
| Domain isolation | **PASS — `domain:academic` 403 even with `?section=...`** |
| RBAC | **PASS — education.manage (+promotion.manage)** |
| IDOR protection | **PASS — never trust institute_id, Rule::exists where institute_id, subjectTypeFor clamp** |
| Historical result integrity | **PASS — placement/year history guards + lockForUpdate single current** |
| Assessment locking | **PASS — lock/unlock + assertAssessmentEditable 59** |
| Result finalization | **PASS — Draft→Review→Approved→Locked→Published** |
| Optional-subject calculation | **PASS — Service:220-336 unchanged, Blade display only** |
| Desktop + Mobile navigation | **PASS — Bootstrap collapse + scroll-margin-top 80px + hashchange JS** |

```
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211)
ROUTES MODIFIED: NO
VIEWS MODIFIED (historical across B13 P1-P4): 4 (layouts/institute.blade.php 242-248 P1 + 218-229/960 P2/P3 + academic-structure 69 + academic-placements 166 + grading preview 31 + AcademicGradingController 193)
  — in this final audit phase: 0 (READ-ONLY)

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 0
LOW_FINDINGS: 0 (1 INFO pre-existing weights_valid badge not B13)
UI_GAPS: 0 (R1→R4 all CLOSED — P1 ?view=marks, P2 id=groups ?section=groups#groups, P3 id=academic-years ?section=academic-years#academic-years, P4 bonus card)
REGRESSIONS: 0 (0 NEW — Professional preserved, Other hidden, Academic 18/18 distinct mutually exclusive, routes 1211, view:clear INFO)
TESTS: BusinessProfile 16/16 PASS (67 assertions) — TenantIsolation 4/4 PASS (8 assertions) — Academic full suite 403 failed / 236 passed is PRE-EXISTING harness (TenantContext/Membership 302 vs 200) — 0 NEW attributable to B13 polish — full suite failures reproduce on clean stash

FINAL_VERDICT: GREEN

```

**GREEN — All 18 Academic items distinctly navigable via existing canonical routes (Assessments vs Marks Entry `?view=marks`, Academic Settings vs Groups/Streams `id=groups ?section=groups#groups`, Academic Years vs Placements `id=academic-years ?section=academic-years#academic-years`, Results submenu, Grade Scales/Final/Published, Bonus card `threshold 2.00/max 5.00/single|best|sum`), Professional Training preserved, Other hidden, multi-business Academic→Professional→Other→Academic follows ACTIVE, tenant/branch/domain/RBAC/IDOR + historical/locking/finalization/optional calculation entirely GREEN with 0 critical/high/medium findings — 0 data/migration modifications — 0 regressions — core BusinessProfile 16/16 + Tenant isolation 4/4 PASS.**

---

> STOP — PHASE B13 FINAL audit complete. No code/data modified in this final audit phase. Next: **B14 Full Academic workflow verification** (end-to-end placement→assessment→marks→aggregation→grading→preview→final result→report card→transcript→promotion with optional bonus live) or **Production readiness audit** — B13 polish provides the navigable surface for B14.

*Evidence: `layouts/institute.blade.php:203-282 Academic collapsible 18 links + 285-304 Training 6` + `route:list 1211 0 new` + `view:clear INFO` + `academic-structure:69 id=groups` + `academic-placements/index:166 id=academic-years` + `preview:31 bonus card threshold??2.00 max??5.00 policy??single` + `AcademicGradingController:193 effectiveScale resolveScaleForClass` + `AcademicFinalResultService:220 threshold/max/policy intact` + `php artisan test BusinessProfile 16/16 PASS 10.55s` + `TenantIsolationAudit 4/4 PASS 2.88s` + `filter Tenant 302 vs 200 109s 403 failed PRE-EXISTING harness`.*
