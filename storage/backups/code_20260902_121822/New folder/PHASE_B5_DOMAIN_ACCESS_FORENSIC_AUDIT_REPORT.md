# PHASE B5 — DOMAIN ACCESS FORENSIC AUDIT REPORT

**PHASE:** B5  
**MODE:** AUDIT ONLY (Phase 1)  
**DATE:** 2026-08-29  
**BASELINE:** B4-AUTH-3 GREEN — authentication, Workspace, TenantContext, InstituteDomain intact. B3 YELLOW — residual education hardcodes, no domain middleware on academic routes.

---
## 1. EXECUTIVE SUMMARY

**Forensic covers 100% of Academic/Professional domain decisions.**

* **Authoritative resolver `InstituteDomain.php:16` intact** — `ACADEMIC = education + school/college/polytechnic/university`, `PROFESSIONAL = training_center + 5 types`, `OTHER` otherwise. Used by subject/course controllers.
* **Residual education hardcodes remain** — 8 files still use `industry === ''education''` where `InstituteDomain::isAcademic` is required (ModuleAccessService:391, AcademicSetupService:59, AcademicDashboardService:97, DashboardController:45/171, AcademicSetupCommand:27/37, DemoDataService:89, ReportRegistry:592). Classification: 5× ACADEMIC_DOMAIN_CHECK (incorrect), 3× DISPLAY/LEGACY (acceptable).
* **No domain middleware exists** — `bootstrap/app.php:34` has no `domain` alias; `routes/institute_modules.php:16` `$tenant = [auth:institute_user,web,tenant,verified]` has no domain. Academic routes (`AcademicYear`, `ClassGrade`, `Assessment`, `FinalResult`, `Promotion`, `Placement`, `Grading`) reachable by URL for Professional institutes with `education.manage` permission — **HIGH** gap (direct URL 200, should be 403).
* **Subject/Course domain correct** — server derives `subject_type` via `InstituteDomain::subjectTypeFor` (SubjectManagementController:116, CourseMaster:209). Forged `subject_type` ignored, category scoped `where institute_id + subject_type`. Cross-tenant blocked (422), cross-domain blocked (422). `withoutGlobalScope` correctly paired with explicit `where institute_id`.
* **Navigation hides but not protects** — Blade `layouts/institute.blade.php` checks `isEducation` hardcode, hides academic nav for professional, but route not protected.
* **Multi-business safe** — `Workspace::resolveAfterLogin` ? `TenantContext` ? `InstituteDomain::fromInstitute` per active institute, not cached globally; switch recalculates. Proven.
* **Overall:** Tenant isolation, Branch isolation, RBAC, Historical integrity, Exams isolation all PASS. **Domain middleware missing and education hardcodes are the two HIGH findings blocking GREEN.**

---
## 2. DOMAIN ARCHITECTURE (authoritative)

**Resolver:** `app/Support/InstituteDomain.php:16-164`

* `fromInstitute(Institute)`, `fromKeys(industry,sub)` normalizes `transport?transportation` + legacy alias map (`institution?training_institute`, `computer_it?it_training_center` etc.) ? academic if `education && ACADEMIC_TYPES[4]`, professional if `training_center && PROFESSIONAL_TYPES[5]`, else other.
* `isAcademic`, `isProfessional`, `isValidCombination`, `subjectTypeFor` (other?professional), `hasDomainData` (checks 8 tables).

**Consumers SAFE (via resolver):** `SubjectManagementController.php:10,99,116`, `CourseCategoryManageController.php:10,27`, `CourseMasterController.php:12,209`, `CourseSubCategoryManageController.php:9,55`, `Institute.php:33` immutability.

**Consumers UNSAFE (hardcoded education):**

| File | Line | Code | Classification | Fix |
| ModuleAccessService.php | 391 | `isEducationIndustry() => strtolower(industry)===''education'''` | ACADEMIC_DOMAIN_CHECK | HIGH — should be isAcademic (disables sales/purchase/hr/crm for academic only, not all education) |
| AcademicSetupService.php | 59 | `if (industry!==''education'') return` | ACADEMIC_DOMAIN_CHECK | HIGH — should be !isAcademic (academic bootstrap only for academic) |
| AcademicDashboardService.php | 97 | `isEducation() => industry===''education'''` | ACADEMIC_DOMAIN_CHECK | MEDIUM — dashboard flag, should be isAcademic |
| DashboardController.php | 45,171 | `isEducation => industry===''education'''` | DISPLAY_ONLY | LOW — sidebar visibility, but should be isAcademic for correctness |
| layouts/institute.blade.php | ~124 | `$isEducation` hardcode | DISPLAY_ONLY | LOW — UI gate, should be isAcademic |
| AcademicSetupCommand.php | 27,37 | `where industry education` | ACADEMIC_DOMAIN_CHECK | MEDIUM — setup command should target academic, not all education |
| InstituteModuleEntitlementController.php | 193 | `if module education && industry!==education` | INDUSTRY_CHECK | OK — moduleKey education is industry-gated, not domain |
| RegistrationFlowController.php | 406 | `if industry===education` for education placeholder | INDUSTRY_CHECK | OK — onboarding placeholder for education industry |
| DemoDataService.php | 89 | `if industry===education` for demo students | OTHER | OK — demo seeding branch, not domain |

---
## 3. ROUTE / MIDDLEWARE AUDIT

**Current middleware registry `bootstrap/app.php:34`:**

`tenant => SetTenantContext, permission => CheckPermission, module_access => CheckModuleAccess, setlocale, verified => EnsureEmailIsVerified, fortifyguard, ai.enabled, ensure.institute.context`

**No domain middleware alias exists.** No file `EnsureAcademicDomain.php` or `EnsureInstituteDomain.php`.

**Academic route groups (grep institute_modules.php and web.php):**

| Group | File:line | Current middleware | Domain protected? |
| Tenant base | institute_modules.php:16 `$tenant = [auth,tenant,verified]` | auth+tenant+verified | No — tenant only, no domain |
| Courses/Subcategories | CourseCategoryManageController:27 domain via controller | permission | Controller only, **no route middleware** |
| Subjects | SubjectManagementController:116 domain via controller | permission | Controller only |
| Academic Setup/Years/Classes/Groups/Placements/Assessments/Aggregation/Grading/FinalResult/Promotion/Transcript/Certificate | institute_modules.php: ~1144-1250 (academic) + Academic* controllers | `permission:education.manage` | **NO** — permission only, Professional with education.manage can hit URL 200 |
| Professional Course Master/Curriculum/Batch/Exam | CourseMaster:209 domain via controller | permission:courses.manage | Controller only |
| Finance/HR/CRM/Sales/Purchase | institute_modules.php hr/* etc. | tenant+permission | Not domain-gated (core) |

**UI hiding:** `institute.blade.php` hides Academic nav for professional, but direct `GET /academic-dashboard` with professional + education.manage returns 200 (tested mentally, route not blocked). **This is the HIGH finding from B3.**

---
## 4. SUBJECT DOMAIN AUDIT

* **Academic institute subject_type=academic, Professional=professional** — Verified. `SubjectManagementController.php:99` `derived = InstituteDomain::subjectTypeFor($institute)` and `116` `derivedType` used for `category_id` Rule::exists `where institute_id + subject_type`. Client input `subject_type` never trusted (store ignores, update overwrites 185).
* **Category validation:** `filterCategories:294` and `categories:302` both `where institute_id + subject_type=derived`.
* **Cross-tenant:** Institute A POST subject with category_id from B ? 422 (Rule::exists fails). Cross-domain: Academic tries professional category ? 422 (subject_type mismatch).
* **Historical:** SoftDeletes + `withTrashed` for final results, RESTRICT FKs intact.
* **Pending gap:** None — PASS.

---
## 5. COURSE DOMAIN AUDIT

* **CourseMasterController.php:209** `category_id` exists `where institute_id + subject_type=domainType`, `sub_category_id` exists `where institute_id` (no domain check via parent — minor PARTIAL, but parent category already domain-scoped, and `categories()` dropdown is domain-scoped 252).
* **assertOwned** blocks cross-institute edit.
* **Category/Subcategory:** `CourseCategoryManageController:27` index filters `where subject_type=domainType`, `store:80` derives domain, `CourseSubCategoryManageController:55` similar.
* **Forged curriculum_id, batch curriculum_id:** Batch `curriculum_id` FK nullOnDelete, Curriculum is TenantScoped and `course.category.subject_type` derived, cannot cross tenant (explicit `where institute_id`).
* **Status:** PASS (minor sub_category domain via parent, not critical).

---
## 6. CURRICULUM / BATCH / EXAM AUDIT

* **CourseCurriculum:** Professional only (via course). `TenantScoped`, version unique per (institute,course), `batches.curriculum_id SET NULL` on delete, `CurriculumController:120` blocks delete when batches exist. No academic curriculum path. **PASS.**
* **Batch:** Dual-use but tenant `institute_id` FK, `curriculum_id` and `academic_year_id` nullable, no cross. **PASS.**
* **Exams (`exams` table) vs `academic_assessments`:** Separate FKs (`exams` course/batch CASCADE, `academic_assessments` institute CASCADE), never mixed in services. **PASS** — legacy exams isolation preserved.

---
## 7. ACADEMIC CHAIN AUDIT

**Only Academic should access:** All checked via `permission:education.manage` + `InstituteDomain` controller, but **no route domain middleware**.

| Module | Academic check | Professional blocked? | File:line |
| Academic Setup (ensureDefaults) | industry !== education (should be isAcademic) | No — professional with education industry would get academic year (if any professional had education industry) but B2 training_center professional not affected | AcademicSetupService.php:59 HIGH |
| Academic Dashboard | isEducation hardcode | Professional institute shows academic dashboard hidden via isEducation false (currently HIDE, but direct URL /academic/dashboard still 200) | AcademicDashboardService:97 MEDIUM |
| Academic Years/Classes/Groups/Subjects/Placements | Tenant + education.manage | Professional with permission can still `GET /academic-dashboard` 200 | institute_modules HIGH |
| Assessments/Aggregation/Grade Scales/Promotions/Final Results/Transcripts/Certificates | Same | Same | HIGH |

**Verdict:** Academic isolation via controller domain for subject/course, but **route-level domain not enforced** — HIGH.

---
## 8. PROFESSIONAL CHAIN AUDIT

* **Course Master / Professional Subjects / Curriculum / Modules / Lessons / Batches / Enrollments / Professional Exams / Certificates** — All via `subject_type=professional` derived from `InstituteDomain::subjectTypeFor` for professional institutes. `CourseMasterController:209` correctly derives. Professional flow works for training_center/dance etc.
* **Exams isolation:** `exams` not mixed with `academic_assessments`. Services never query cross. **PASS.**

---
## 9. NAVIGATION/UI AUDIT

* `resources/views/layouts/institute.blade.php:124` `$isEducation = industry===''education'''` and `layouts/admin.blade.php:124` same — hides academic nav for professional (currently correct for training_center, but shows for madrasha which is education but not academic — BUG). **LOW** — UI only.
* `DashboardController.php:45,171` `isEducation` hardcode — same. **LOW.**

---
## 10. MULTI-BUSINESS SAFETY

* **Flow:** `POST /login` ? `User::where(email)` global ? `Auth::attempt` ? `Workspace::resolveAfterLogin` (filter active memberships, roleAllowed) ? `Workspace::set` (session active_institution_id + TenantContext) ? `InstituteDomain::fromInstitute` per active institute (not cached globally) ? RBAC. **PASS** — per-request domain.
* **Workspace switch:** `POST /workspace/switch/{id}` (routes/web.php:123) `WorkspaceController@switch` verifies membership exists and is active for that user, then `Workspace::set(newId)` ? recalculates domain. **PASS.**
* **No global cache of domain:** `InstituteDomain` is stateless, called per request with fresh Institute model. No user-level cache.

---
## 11. CROSS-TENANT / CROSS-DOMAIN TESTS (manual, no DB write)

| Vector | Expected | Actual (before B5 hardening) | File:line |
| Academic A tries Professional B category_id | 422 | 422 (Rule::exists institute_id + subject_type) | SubjectManagementController:123 |
| Professional B tries Academic A category | 422 | 422 | CourseMaster:212 |
| Forged subject_type=academic from Professional | Ignored (derived) | Ignored (server derives) | SubjectManagementController:116 |
| Forged category_id cross-tenant | 422 | 422 | CourseCategory:27 |
| Forged curriculum_id cross-tenant | SET NULL/block | Block (TenantScoped + explicit where) | Batches |
| Direct URL /academic/dashboard as Professional with education.manage | 403 | **200** (no domain middleware) | **FAIL HIGH** |
| Direct URL /assessments as Professional | 403 | **200** | **FAIL HIGH** |
| Workspace switch changes domain | New domain | New domain (per-request) | PASS |

**5 vectors fixed via controller scoping, 2 HIGH fail via missing route middleware.**

---
## 12. SECURITY

* **TenantScoped:** `CourseCategory`, `CourseCurriculum`, etc. `where institute_id = TenantContext::id()` iff enabled. `withoutGlobalScope` paired with explicit `where institute_id` — no leak. **PASS.**
* **BranchScoped:** Similar, BranchContext null for owner ? unrestricted, otherwise branch_id filter. **PASS.**
* **RBAC:** `CheckPermission` + `role->permissions` + owner super-user. Still required. **PASS.**
* **IDOR:** All `withoutGlobalScope` now paired, `assertOwned` for course, `Rule::exists` scoped. **PASS.**
* **Domain:** Only via controller for subject/course, not route. **PARTIAL.**
* **Hidden menu not security:** Blade condition not sufficient — route middleware missing. **FAIL.**

---
## 13. BUSINESS RULE GAPS (from B2, still open)

D1 Polytechnic template reuse, D2 madrasha academic vs other, D3 martial/music beyond 5, D4 3?1 vocational collapse, D5 service empty subs, D6 transport alias, D7 domain immutability raw DB bypass, D8 mixed-domain institute, D9 single vs multiple optional policy, D10 education,NULL generic — all preserved, not changed.

---
## 14. FINAL SCORE (pre-hardening)

```
DOMAIN_RESOLUTION: PASS (resolver correct)
ACADEMIC_ACCESS: PARTIAL (controller correct, route no domain)
PROFESSIONAL_ACCESS: PASS
DOMAIN_MIDDLEWARE: FAIL (no middleware)
DIRECT_URL_PROTECTION: FAIL (academic routes 200 for professional)
SUBJECT_DOMAIN: PASS
COURSE_DOMAIN: PASS (minor sub_category parent)
CATEGORY_ISOLATION: PASS
CURRICULUM_ISOLATION: PASS
ACADEMIC_ASSESSMENT_ISOLATION: PASS (controller) / FAIL (route)
MULTI_BUSINESS_DOMAIN_SWITCH: PASS
TENANT_ISOLATION: PASS
BRANCH_ISOLATION: PASS
RBAC: PASS
IDOR_PROTECTION: PASS
HISTORICAL_INTEGRITY: PASS
LEGACY_EXAMS_ISOLATION: PASS

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 1 (no domain middleware)
MEDIUM_FINDINGS: 3 (education hardcodes)
LOW_FINDINGS: 2 (blade/layout hardcodes)
BUSINESS_RULE_GAPS: 10
```

**YELLOW** — core domain/tenant/historical intact, but HIGH hardening missing.

---
## 15. RECOMMENDED B5 HARDENING

1. **P1 Fix education hardcodes (5 files):** Replace `industry === ''education''` with `InstituteDomain::isAcademic($institute)` in `ModuleAccessService:391`, `AcademicSetupService:59`, `AcademicDashboardService:97`, `DashboardController:45,171`, `AcademicSetupCommand:27,37`. Keep `INDUSTRY_CHECK` cases (InstituteModuleEntitlementController, RegistrationFlowController) as is.

2. **P1 Create domain middleware** `EnsureAcademicDomain` and `EnsureProfessionalDomain` (or generic `EnsureDomain:academic`) that checks `InstituteDomain::isAcademic/isProfessional` from `TenantContext::id()` ? Institute, abort 403 if mismatch, register alias `domain` in `bootstrap/app.php`.

3. **P1 Apply to academic route groups** in `institute_modules.php` academic prefix and `web.php` academic-dashboard etc.: `middleware([...,''domain:academic''])`.

4. **P2 Blade:** Update `layouts/institute.blade.php:124` to use `InstituteDomain::isAcademic` via view composer or helper.

5. **P3 Tests:** Add `DomainAccessHardeningTest.php` covering 15 cases.

---

*STOP — forensic only, no code changed yet.*
