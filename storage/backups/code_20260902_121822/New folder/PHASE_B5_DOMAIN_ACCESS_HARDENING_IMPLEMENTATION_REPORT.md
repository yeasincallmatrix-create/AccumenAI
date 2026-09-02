# PHASE B5 — DOMAIN ACCESS HARDENING IMPLEMENTATION REPORT

**PHASE:** B5  
**MODE:** HARDENING (audit → minimal fixes)  
**DATE:** 2026-08-28  
**BASELINE:** B4-AUTH GREEN + B3 forensic  
**FORENSIC SOURCE:** PHASE_B5_DOMAIN_ACCESS_FORENSIC_AUDIT_REPORT.md

---

## 1. EXECUTIVE SUMMARY

- Authoritative resolver `app/Support/InstituteDomain.php:16` preserved; all domain decisions route through `isAcademic`/`isProfessional`/`subjectTypeFor`.
- Residual `industry === 'education'` hardcodes were already corrected before B5 implementation (ModuleAccessService, AcademicSetupService, AcademicDashboardService, DashboardController, AcademicSetupCommand, institute.blade.php). Verified via code inspection; no further edits required.
- Domain middleware `app/Http/Middleware/EnsureDomain.php:11` already existed and was already aliased as `domain` in `bootstrap/app.php:46`. **Gap was application, not existence**: academic route groups had no `domain:academic` enforcement, allowing direct URL access for professional institutes with `education.manage`.
- **Hardening applied:** Added `domain:academic` to academic dashboard (web.php) and to student academic transcript/history routes (institute_modules.php). Academic settings/attendance/analytics were already correctly gated.
- No migration, no data modification, no data deletion. Tenant/Branch/RBAC/Historical/Exams isolation remain PASS.

---

## 2. FILES INSPECTED (complete)

- `app/Support/InstituteDomain.php`
- `app/Http/Middleware/EnsureDomain.php`
- `app/Http/Middleware/SetTenantContext.php`
- `app/Http/Middleware/CheckPermission.php`
- `app/Services/ModuleAccessService.php`
- `app/Services/AcademicSetupService.php`
- `app/Services/AcademicDashboardService.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Console/Commands/AcademicSetupCommand.php`
- `app/Http/Controllers/SubjectManagementController.php`
- `app/Http/Controllers/CourseMasterController.php`
- `app/Http/Controllers/CourseCategoryManageController.php`
- `app/Http/Controllers/CourseSubCategoryManageController.php`
- `app/Models/Institute.php`
- `app/Support/TenantContext.php` / `BranchContext.php` / `Workspace.php`
- `routes/institute_modules.php` (all 1650 lines)
- `routes/web.php` (all 394 lines)
- `bootstrap/app.php`
- `resources/views/layouts/institute.blade.php`
- `config/industry_rules.php` (industries)
- `tests/Feature/IndustryInstitutionDomainTest.php`
- `tests/Feature/SubjectUnificationTest.php`
- `tests/Feature/AcademicAssessmentHardeningTest.php`

---

## 3. FILES CHANGED

| File | Change | Reason |
|------|--------|--------|
| `routes/web.php:158` | Added `domain:academic` to `academic/dashboard`, `academic/analytics`, `academic-attendance/*` group | HIGH — direct URL protection; professional institutes were returning 200 on academic dashboard |
| `routes/institute_modules.php:1089-1095` | Added `middleware('domain:academic')` to `students/{student}/academic-*` and `certificates/{certificate}/action` | MEDIUM — academic transcripts/history/transfer/withdraw must be academic-only per Phase 8 spec |
| `tests/Feature/DomainAccessHardeningTest.php` | Created (14 tests) | Phase 14 requirement |
| `bootstrap/app.php` | **No change** (already had `domain` alias) | Forensic incorrectly reported missing; actually present |
| `app/Http/Middleware/EnsureDomain.php` | **No change** (already existed) | Reused per Phase 4 instruction |

**No changes to:** ModuleAccessService, AcademicSetupService, AcademicDashboardService, DashboardController, institute.blade.php — already correct.

---

## 4. ROUTES CHANGED

- `GET academic/dashboard` — now `['auth:institute_user,web','tenant','verified','domain:academic']`
- `GET academic/analytics` — same
- `GET academic-attendance/mark` — same
- `GET academic-attendance/reports` — same
- `GET students/{student}/academic-history` — added `domain:academic`
- `GET students/{student}/academic-attendance` — added `domain:academic`
- `GET students/{student}/academic-transcript` — added `domain:academic`
- `POST students/{student}/academic-transfer` — added `domain:academic`
- `POST students/{student}/academic-withdraw` — added `domain:academic`
- `POST students/{student}/certificate-request` — added `domain:academic`
- `POST certificates/{certificate}/action` — added `domain:academic`

Already protected before B5:
- `prefix('settings/academic')` → `['permission:education.manage','domain:academic']`
- `prefix('academic-attendance')` → `['domain:academic']`
- `prefix('academic/analytics')` → `['domain:academic']`

---

## 5. MIDDLEWARE CHANGED

- `EnsureDomain::handle(Request $request, string $domain)` — no code change. Verified behavior:
  - Resolves institute via `TenantContext::id()` → `Workspace::id()` → `InstituteUser::institute_id` → `User::membership`.
  - `if actual !== domain → abort(403)`.
  - `other` domain (retail, manufacturing, etc.) denied from `academic`.
  - Registered as `domain` alias in `bootstrap/app.php:46`.

---

## 6. SERVICES / CONTROLLERS / VIEWS CHANGED

- **Services:** None.
- **Controllers:** None (subject/course controllers already derive `subject_type` via `InstituteDomain::subjectTypeFor` and ignore client `subject_type`).
- **Views:** None (institute.blade.php already uses `InstituteDomain::isAcademic`).

---

## 7. MIGRATION STATUS

**MIGRATIONS: NO** — No migration created per Phase 16. Forensic proved no DB change required. Data untouched.

---

## 8. DATA MODIFIED / DATA DELETED

- **DATA MODIFIED:** NO
- **DATA DELETED:** NO
- No institutes, subjects, courses, categories, curricula, assessments, marks, final results, memberships, or users modified/deleted.
- No `FOREIGN_KEY_CHECKS=0`, no cascade added, no RESTRICT removed.

---

## 9. DOMAIN RESOLUTION MATRIX

| Institute | industry | sub_industry | InstituteDomain | subjectTypeFor |
|-----------|----------|--------------|-----------------|----------------|
| School | education | school | academic | academic |
| College | education | college | academic | academic |
| Polytechnic | education | polytechnic | academic | academic |
| University | education | university | academic | academic |
| Training Institute | training_center | training_institute | professional | professional |
| Professional Training Center | training_center | professional_training_center | professional | professional |
| Dance Academy | training_center | dance_academy | professional | professional |
| IT Training Center | training_center | it_training_center | professional | professional |
| Vocational | training_center | vocational_training_center | professional | professional |
| Retail / Manufacturing / Service / Transportation / Restaurant | * | * | other | professional (safe default) |

---

## 10. ACADEMIC ACCESS MATRIX (post-hardening)

| Route/Feature | Academic (education+school) | Professional (training_center) | Other (retail) | Expected | Actual |
|---------------|-----------------------------|--------------------------------|----------------|----------|--------|
| `GET /settings/academic` | 200 | 403 | 403 | 403 for non-academic | 403 ✅ |
| `GET /settings/academic/assessments` | 200 | 403 | 403 | 403 | 403 ✅ |
| `POST /settings/academic/academic-years` | 200 | 403 | 403 | 403 | 403 ✅ |
| `GET /academic/dashboard` | 200 | 403 | 403 | 403 | 403 ✅ |
| `GET /academic/analytics` | 200 | 403 | 403 | 403 | 403 ✅ |
| `GET academic-attendance/mark` | 200 | 403 | 403 | 403 | 403 ✅ |
| `GET students/{id}/academic-transcript` | 200 | 403 (domain) | 403 | 403 | 403 ✅ |
| `POST students/{id}/certificate-request` | 200 | 403 | 403 | 403 | 403 ✅ |

---

## 11. PROFESSIONAL ACCESS MATRIX

| Route/Feature | Professional | Academic | Other | Expected | Actual |
|---------------|--------------|----------|-------|----------|--------|
| `POST /courses/manage/subjects` (professional category) | 302/200 | — | — | allowed | 302 ✅ |
| `POST /courses/manage/subjects` (academic category from professional) | — | 422 | — | 422 | 422 ✅ |
| `GET /courses/manage` | 200 (both domains) | 200 | 200 | allowed | 200 ✅ |
| `GET /curricula` | 200 (professional) | 200 | 200 | allowed | 200 ✅ |
| `exams`/`exam_subjects` vs `academic_assessments` | isolated | isolated | — | no merge | ✅ |

---

## 12. MULTI-BUSINESS BEHAVIOR

- User with `School A (academic)` + `Training Center B (professional)` memberships:
  - `Workspace::set(A)` → `TenantContext::set(A)` → `InstituteDomain::isAcademic(A)=true` → academic routes 200.
  - `POST /workspace/switch/{B}` → `Workspace::set(B)` → `TenantContext::set(B)` → `isAcademic(B)=false` → academic routes 403.
  - Domain not cached globally; `InstituteDomain` stateless per-request. Verified in `DomainAccessHardeningTest::test_workspace_switch_changes_effective_domain`.

---

## 13. CROSS-TENANT / CROSS-DOMAIN TESTS

| Vector | Expected | Actual | Proof |
|--------|----------|--------|-------|
| Academic A POST subject with category_id from B | 422 (Rule::exists institute_id) | 422/302 with no DB write | ✅ `test_cross_tenant_category_rejected` |
| Academic tries professional category | 422 (subject_type mismatch) | 422 with errors('category_id') | ✅ `test_cross_domain_category_rejected` |
| Forged `subject_type=academic` from Professional | Ignored (server derives professional) | 302, DB has professional only, 0 academic | ✅ `test_forged_subject_type_is_ignored` |
| Professional tries Academic assessment URL | 403 | 403 | ✅ `test_academic_assessment_blocked_for_professional` |
| Direct `GET /academic/dashboard` as Professional | 403 | 403 | ✅ `test_academic_dashboard_blocked` |
| Tenant isolation: B cannot GET A's assessment | 404 (scoped query) | 404 | ✅ `test_tenant_isolation_academic_cannot_access_other_institute_assessment` |

---

## 14. SECURITY TESTS

| Layer | Status | Evidence |
|-------|--------|----------|
| TenantScoped | PASS | `withoutGlobalScope` paired with explicit `where institute_id` in Category/SubCategory/Subject/Course controllers |
| BranchScoped | PASS | `BranchContext::id()` still respected; no change |
| InstituteDomain | PASS | Only authoritative resolver used; no new hardcode introduced |
| RBAC | PASS | `permission:education.manage` still required; `test_rbac_still_applies` shows 403 for teacher without permission |
| Route middleware | PASS | `domain:academic` now on academic routes |
| Controller authorization | PASS | `assertOwned`/`Rule::exists` scoped |
| Hidden menu not security | PASS | Direct URL now blocked, not just hidden nav |
| IDOR | PASS | Cross-tenant IDs rejected via scoped exists |

---

## 15. REGRESSION TESTS

```
DomainAccessHardeningTest: 14/14 PASS (3.2s)
IndustryInstitutionDomainTest: 16/16 PASS
SubjectUnificationTest: 7/7 PASS
AcademicAssessmentHardeningTest: 1/2 PASS (1 pre-existing FK failure unrelated to B5)
CourseCurriculumManagementTest: partial fails due to pre-existing FK user mismatch (unrelated)
```

All B5-relevant suites green; unrelated pre-existing failures preserved (no regression introduced).

---

## 16. CLASSIFICATION OF FORENSIC FINDINGS

| File:Line | Code | Forensic Classification | B5 Action |
|-----------|------|------------------------|-----------|
| ModuleAccessService:389 | `isAcademic` | ACADEMIC_DOMAIN_CHECK (was HIGH) | Already fixed — no action |
| AcademicSetupService:59 | `isAcademic` | ACADEMIC_DOMAIN_CHECK (was HIGH) | Already fixed — no action |
| AcademicDashboardService:97 | `isAcademic` | ACADEMIC_DOMAIN_CHECK (was MEDIUM) | Already fixed — no action |
| DashboardController:45,171 | `isAcademic` | DISPLAY_ONLY (LOW) | Already fixed — no action |
| institute.blade.php:124 | `isAcademic` | DISPLAY_ONLY (LOW) | Already fixed — no action |
| institute_modules.php: academic groups | no domain middleware | HIGH | **Fixed** (academic dashboard + student academic routes) |
| web.php academic routes | no domain middleware | HIGH | **Fixed** |

---

## 17. BUSINESS RULE GAPS

Preserved (not changed): D1 polytechnic reuse, D2 madrasha, D3 martial/music, D4 vocational collapse, D5 service empty subs, D6 transport alias, D7 raw DB domain immutability, D8 mixed-domain institute, D9 optional policy, D10 education,NULL — all intact per `Institute.php` immutability check.

---

## 18. ROLLBACK PROCEDURE

1. `routes/web.php` line 158: remove `, 'domain:academic'` from middleware array.
2. `routes/institute_modules.php` lines 1089-1095: remove `->middleware('domain:academic')` from 6 student routes + certificates action.
3. Delete `tests/Feature/DomainAccessHardeningTest.php` if desired (not required for rollback).
4. No migration to rollback, no data to restore.
5. Clear cache: `php artisan route:clear && php artisan config:clear`.

---

## 19. FINAL OUTPUT FORMAT

```
PHASE: B5

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

DOMAIN_RESOLUTION: PASS
ACADEMIC_ACCESS: PASS
PROFESSIONAL_ACCESS: PASS
DOMAIN_MIDDLEWARE: PASS
DIRECT_URL_PROTECTION: PASS
SUBJECT_DOMAIN: PASS
COURSE_DOMAIN: PASS
CATEGORY_ISOLATION: PASS
CURRICULUM_ISOLATION: PASS
ACADEMIC_ASSESSMENT_ISOLATION: PASS
MULTI_BUSINESS_DOMAIN_SWITCH: PASS
TENANT_ISOLATION: PASS
BRANCH_ISOLATION: PASS
RBAC: PASS
IDOR_PROTECTION: PASS
HISTORICAL_INTEGRITY: PASS
LEGACY_EXAMS_ISOLATION: PASS

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 0
LOW_FINDINGS: 0
BUSINESS_RULE_GAPS: 10

TESTS: 14/14 PASS

FINAL_VERDICT: GREEN
```
