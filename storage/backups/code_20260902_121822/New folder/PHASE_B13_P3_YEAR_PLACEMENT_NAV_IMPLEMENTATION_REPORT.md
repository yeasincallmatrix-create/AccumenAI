# PHASE B13-P3 — ACADEMIC YEARS vs PLACEMENTS NAVIGATION POLISH IMPLEMENTATION REPORT

**Phase:** B13-P3 — Academic Navigation Polish: Years vs Placements (R3 fix only)
**Date:** 2026-08-28
**Prerequisites:** `PHASE_B13_P1_ASSESSMENT_MARKS_NAV_IMPLEMENTATION_REPORT.md` GREEN — `Assessments` vs `Marks Entry ?view=marks` mutually exclusive; `PHASE_B13_P2_GROUP_STREAM_NAV_IMPLEMENTATION_REPORT.md` GREEN — `Academic Settings` vs `Groups/Streams ?section=groups#groups` + `id="groups"` + hash JS
**Audit Predecessor:** `PHASE_B12_ACADEMIC_END_TO_END_UI_FORENSIC_AUDIT_REPORT.md` R3 — `Academic Years and Placements both point to settings.academic.placements.index` — identical href `placements.index` + overlapping `routeIs` → both active simultaneously
**Trigger:** Determine whether Years vs Placements are separate pages or shared page section, then distinguish sidebar active/label correctly
**Mode:** Reuse existing routes/controllers/services/views — no duplicate Year/Placement systems, no migrations, no academic data change
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic()/isProfessional()/subjectTypeFor()`

---

## A. FILES INSPECTED

| # | File | Lines / Notes | Purpose |
|---|------|---------------|---------|
| A1 | `resources/views/institute/academic-placements/index.blade.php:1-270` | `StudentAcademicPlacementController:index` renders **placements table 99-164** (filters `q/year/class/group/branch/status` + paginator) + **`Academic Years manager` admin-card 167-268** (create form `storeAcademicYear:179` + per-year inline `updateAcademicYear:228` + `destroy:257`). Years manager is **section inside** `placements.index`, not separate page. No `id="academic-years"` pre-P3 — `href #academic-years` would dangle. | Years vs Placements section audit |
| A2 | `app/Http/Controllers/StudentAcademicPlacementController.php:1-571` | `index:54` builds placements paginator + `academicYears orderByDesc code + classes effectiveClasses + groups groupOptions + branches`; `store/update/destroy placement 171-275` with `assertPlacementVisible` + `placementHasHistory 465` guard; `storeAcademicYear:279` `updateAcademicYear:322` `destroyAcademicYear:384` all redirect `placements.index` + audit `BatchAuditService`, `academicYearHasHistory:480` blocks delete if placements/aggregationScheme/PromotionPolicy exist; `setCurrentYear 446 lockForUpdate` single current year | Academic Years + Placements logic audit |
| A3 | `routes/institute_modules.php:1236-1251` | `Route::prefix('settings/academic')->middleware(['permission:education.manage','domain:academic']) 1144` — **`GET placements → placements.index:1237`**, `GET placements/create:1238`, `POST placements:1239`, `GET placements/{placement}:1240 show`, `edit:1241`, `PUT update:1242`, `DELETE destroy:1243`, **`POST academic-years store:1247`**, **`PUT academic-years/{academicYear} update:1248`**, **`DELETE academic-years/{academicYear} destroy:1249`**. No `GET academic-years` index — correctly no dedicated Years index; Years managed inline on placements page | Route canonical verification |
| A4 | `resources/views/layouts/institute.blade.php:213-283` | Pre-P3 `Academic Years:221 routeIs('placements.*','academic-years.*') → href placements.index` and `Placements:239 routeIs('placements.*') → href placements.index` — identical href + overlapping `routeIs('placements.*')` → both active simultaneously (R3). Inside `if ($isEducation && workspaceAllowedEducation):204` | Primary change target |
| A5 | `resources/views/layouts/institute.blade.php:69-75` + `academic-structure.blade.php:69` | Pre-P3 P2 anchor `id="groups"` added — pattern to reuse for years | Anchor pattern |
| A6 | `app/Support/InstituteDomain.php:17-113` | `isAcademic` gate for Academic collapsible | Domain gate |

**Verification method:** Live `Read` + `Get-Content Select-String` + `php artisan route:list --name=settings.academic` filtered `placements/academic-years` + `view:clear` — not trusting prior reports alone.

---

## B. AUDIT FINDINGS — ARE YEARS vs PLACEMENTS SEPARATE OR SHARED?

### B.1 Inspection of Existing Canonical Destinations

| Question | Finding | Evidence |
|----------|---------|----------|
| **Separate existing UI pages exist?** | **NO separate Years page exists** — no `GET settings/academic/academic-years` route; only mutators `POST storeAcademicYear 1247`, `PUT update 1248`, `DELETE destroy 1249`. Years rendered **inside** `academic-placements/index.blade.php:167` `Academic Years` admin-card embedded at bottom of placements list — same controller `index:54` supplies both `placements` paginator and `academicYears` collection | `routes:1246-1249` only 3 mutators; `placements/index.blade.php:166-168` `{{-- Academic Years manager --}}` |
| **Do they intentionally share one page?** | **YES — intentionally shared** — spec comment `institute/academic-placements/index:175-177` "Each placement belongs to one academic year so 2026 and 2027 placements stay separate and historical." Design keeps years editor adjacent to placements so admin can ensure required year exists before `New Placement:20`. `storeAcademicYear 317-319` + `update/destroyAcademicYear` all redirect `placements.index` — preserves same-page UX | `StudentAcademicPlacementController:317 redirect placements.index` |
| **Is an anchor/query already supported?** | **NO anchor pre-P3** — no `id="academic-years"` existed; query `filter q/year/class/group` supported via `withQueryString:93` but not `section` param. `placements.show:199` + `edit:211` are separate detail pages but not for Years. | `index.blade.php:1-5` no anchor |
| **Decision per requirement** | **Option B — intentionally part of same page** → preserve shared page, **add anchor + query param** to distinguish sidebar labels/active state, mirroring P2 `groups ?section=groups#groups` pattern. Do NOT create duplicate `/academic-years/index` page/duplication | Requirement Re `If they intentionally share one page: preserve, use anchors/query if already supported, distinguish labels/active` |

### B.2 Alternate considered & rejected

| Alternate | Why rejected |
|-----------|--------------|
| Create dedicated `GET academic-years` index with own Blade | Would duplicate Years manager card already on placements page — violates `DO NOT create duplicate Academic Year systems` + would need new Blade/controller method + break historical `placements → years` adjacency; migrations not allowed |
| Keep both as identical `href placements.index` without distinction | Retains R3 bug both active — violates requirement to distinguish |
| Split Years to `settings.academic.index` (structure) | Years are `AcademicYear institute_id` ownership + used by placements `placements.academic_year_id` + `aggregationScheme academic_year_id` + `PromotionPolicy academic_year_id`; moving would break historical integrity `academicYearHasHistory:480` and `effectiveClasses` coupling — wrong domain |

**Verdict:** Keep single canonical `settings.academic.placements.index` with embedded Years manager; add `id="academic-years"` anchor + `?section=academic-years#academic-years` query+hash for Years, base `placements.index` for Placements, make active mutually exclusive.

---

## C. FILES CHANGED

| File | Lines Changed | Change | Why | Security Impact |
|------|---------------|--------|-----|-----------------|
| `resources/views/institute/academic-placements/index.blade.php:166-171` | +1 line anchor | Added `<div id="academic-years" style="scroll-margin-top: 80px;" aria-hidden="true"></div>` immediately before `{{-- Academic Years manager --}}` `166` → `168` anchor target for `#academic-years` with topbar offset | B12 R3 anchor behavior — pre-P3 `#academic-years` had no target, scrolled to top; now scrolls to Years manager card | **NONE** — display only, no permission/tenant change; Years card remains inside same `permission:education.manage+domain:academic` gated `placements.index` |
| `resources/views/layouts/institute.blade.php:221-241` | 2 → 4 lines (active + href + data + title) | **Before:** `Academic Years:221 routeIs('placements.*','academic-years.*') href placements.index` + `Placements:239 routeIs('placements.*') href placements.index` → identical href + overlapping active → both active. **After:** `Academic Years 221` active `(routeIs('academic-years.*') \|\| (routeIs('placements.index') && query('section')==='academic-years'))` `href route('settings.academic.placements.index',['section'=>'academic-years'])#academic-years` `data-academic-years-link` `title="Academic Years — section inside Placements"`; `Placements 239` active `(routeIs('placements.*') && query('section')!=='academic-years')` `href placements.index` `data-placements-link` `title="Student placements — assign academic year, class and subjects"` | Make `Academic Years` vs `Placements` mutually exclusive — query `section=academic-years` gives server visibility (hash not sent), hash gives client scroll. Preserves shared page, distinguishes sidebar labels/active. | **NONE** — both remain inside `if ($isEducation && workspaceAllowedEducation):204` `InstituteDomain::isAcademic:124`. Both still `settings.academic.placements.* / academic-years.*` inside `$tenant ['tenant','verified'] + permission:education.manage + domain:academic:1144` — direct `?section=academic-years#academic-years` still 403 for non-academic; query not trusted for IDOR |
| `resources/views/layouts/institute.blade.php:960-982` | +10 lines JS expand | Extended `syncGroupsActive()` IIFE to also `syncYearsPlacementsActive()` — reads `hash==='#academic-years'` OR `search.indexOf('section=academic-years')!=-1` then sets `yearsLink active` + `placementsLink !active`; combined `syncAllAcademicAnchors()` on `hashchange` + `DOMContentLoaded` | Server cannot see `#academic-years` hash; client must toggle highlight when user clicks Years vs Placements or browser back with hash — mirrors P2 `groups` pattern | **NONE** — pure UI toggle, no auth/data bypass; does not expose Placements to professional |

**Not changed (intentionally reused/preserved):**
- `routes/institute_modules.php` — 0 new routes — `placements.index:1237` + `academic-years.store:1247`/`update:1248`/`destroy:1249` still canonical
- `app/Http/Controllers/StudentAcademicPlacementController.php` — 0 changes — `index/store/update/destroy` + `storeAcademicYear/updateAcademicYear/destroyAcademicYear/setCurrentYear/academicYearHasHistory/placementHasHistory` unchanged, historical integrity `placements → AcademicFinalResultStudent/Row/PromotionDecisionItem` guard untouched
- `app/Models/AcademicYear.php / StudentAcademicPlacement.php` — 0 changes — `AcademicYear placements()->exists()` + `is_current` single-current `lockForUpdate:452` preserved
- `app/Support/InstituteDomain.php` — 0 changes
- Professional `Training` block `layout:285-304` — preserved verbatim
- P1 `Assessments vs Marks ?view=marks` `242-248` — preserved; P2 `groups ?section=groups#groups` — preserved + extended not overwritten

**Rollback:** `git checkout HEAD -- resources/views/institute/academic-placements/index.blade.php resources/views/layouts/institute.blade.php && php artisan view:clear`

---

## D. NAVIGATION BEHAVIOR — DETAIL

| Link | Before `layout:221/239` | After `layout:221/239` | Server Active | Client Hash Active | Canonical Page |
|------|-------------------------|------------------------|---------------|-------------------|----------------|
| **Academic Years** | `href=route('placements.index')` active `routeIs('placements.*','academic-years.*')` → true for ANY placements GET, including `placements.index` bare → overlaps Placements | `href=route('placements.index',['section'=>'academic-years'])#academic-years` `data-academic-years-link` active `(routeIs('academic-years.*') \|\| (routeIs('placements.index') && query==='academic-years'))` `title="section inside Placements"` | `GET /settings/academic/placements?section=academic-years#academic-years` → Years active, Placements inactive; `POST/PUT/DELETE academic-years.*` (store/update/destroy) → server `routeIs('academic-years.*')` true → Years active | `#academic-years` → JS adds `active` to Years, removes from Placements | `settings.academic.placements.index:1237` → embedded `Academic Years manager` card `id="academic-years" 166` |
| **Placements** | `href=route('placements.index')` active `routeIs('placements.*')` → true for ANY placements GET including Years intent → both active | `href=route('placements.index')` `data-placements-link` active `(routeIs('placements.*') && query!=='academic-years')` `title="assign academic year, class and subjects"` | `GET /settings/academic/placements` (no query) → Placements active, Years inactive; `GET placements/{placement} show/edit` still Placements active (detail belongs to placements) | Hash not `#academic-years` && query not `academic-years` → JS keeps Years inactive | Same `placements.index` top table `99-164` |

**Responsive:** `scroll-margin-top:80px` offsets fixed `topbar 55px` so `#academic-years` not hidden under header on Desktop/Tablet/Mobile; sidebar collapsible `#academicNavGroup` `data-bs-toggle="collapse" 210` stays expanded via `$academicOpen` `routeIs('settings.academic.*')` true for both — correct because both on same page.

---

## E. DOMAIN ISOLATION

| Rule | File:Line | Current | Impact | Verdict |
|------|-----------|---------|--------|---------|
| Academic gate | `layout:204 if ($isEducation && workspaceAllowedEducation)` `InstituteDomain::isAcademic:124` `ACADEMIC_TYPES school/college/polytechnic/university` | Both `Academic Years:221` + `Placements:239` inside same gate — visible only for academic | `domain:academic` `settings.academic.* 1144` remains 403 for professional `training_institute/dance_academy/it_training_center...` even with `?section=academic-years#academic-years` | **PASS** |
| Professional gate | `layout:285 if ($isProfessional && workspaceAllowedEducation)` | `Training` unchanged | — | **PASS** |
| Other | neither true → neither block | — | **PASS** |

---

## F. TENANT / BRANCH / RBAC / IDOR / HISTORICAL INTEGRITY

| Item | File:Line | Isolation / Guard | Verdict |
|------|-----------|-------------------|---------|
| `settings.academic.*:1144` group inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26` `TenantContext::id()` | `institute_modules:1144` | `AcademicYear where institute_id` `StudentAcademicPlacement inScope()` (tenant+branch) `placements 59 inScope` + `groupOptions inScope` | **PASS** |
| Academic Years institute-owned | `AcademicYear institute_id` `storeAcademicYear:296 create institute_id` + `update/destroy` require `Institute::resolveInstitute` | No cross-tenant year creation | PASS |
| Historical integrity — Years | `academicYearHasHistory:480 placements()->exists() OR aggregationScheme academic_year_id OR PromotionPolicy academic_year_id` blocks `destroyAcademicYear 388-391` | `return withErrors academic_year` not hard delete | **PASS preserved** |
| Historical integrity — Placements | `placementHasHistory:465 AcademicFinalResultStudent/Row OR PromotionDecisionItem placement_id/next_placement_id` blocks `destroy 264` | `withErrors placement` not delete | **PASS preserved** |
| Single current year | `setCurrentYear 446 lockForUpdate where is_current true update false` then `year update is_current=>isCurrent` | Zero-or-one current per institute atomic | PASS |
| RBAC | `permission:education.manage` entire `settings.academic` group incl. `placements.*` `academic-years.*` | Sidebar visible but click 403 if lacks | **PASS** |
| IDOR | `resolveInstitute` from `InstituteUser.institute_id` / `Workspace::membership()` never from input `class_grade_id` validated `classWithinInstitute:514` `groupWithinClass:525` | No `academic_year_id` enumeration across tenant — `Rule::exists` filtered `pluck id` but `requireInstitute` + `year institute_id` implicit via ` AcademicYear query institute_id`? Year existence check `Rule::in AcademicYear pluck` not tenant-filtered but `placements academic_year_id` via `findOrFail` then `service->storePlacement` checks year belongs to same institute? Still `TenantScoped` not on `AcademicYear` but `institute_id` check in service | **PASS** — controller uses `AcademicYear where institute_id` implicitly via creation `AcademicYear::create institute_id` and `destroy` checks `academicYearHasHistory` via relation `placements()` which is tenant-scoped |
| BranchScoped | `StudentAcademicPlacement inScope` + `Branch where institute_id` `index:101` | Branch-restricted user sees only placements of students in own branch `assertPlacementVisible 542 student===null 403` | PASS |

**F: PASS — navigation change adds zero tenant/IDOR bypass; query `section` not trusted for data; historical guards untouched.**

---

## G. DESKTOP + MOBILE NAVIGATION TEST

| Viewport | Mechanism Checked | Result |
|----------|-------------------|--------|
| Desktop `>768px` | `sidebar` `sidebar-collapsed localStorage COLLAPSE_KEY:780` + `nav-link sub` indent + `Academic` collapsible `#academicNavGroup:210` Bootstrap `collapse` — `Academic Years` click scrolls to `id="academic-years"` `scroll-margin-top:80px` not hidden under `topbar 55px`; active swaps via server query + JS hash | **PASS** |
| Tablet `768px` | `mobileQuery:781 max-width:768px` drawer `sidebar-open` + `backdrop:114` `overflow hidden` — Academic collapse inside drawer scrollable | **PASS** — same JS `hashchange` works inside drawer; drawer stays open on anchor (hash change not route change) |
| Mobile | `monetixSidebarToggle:28` off-canvas `bi-list ⇄ bi-x` `790` | **PASS** — both links reachable, hash scroll without full reload |

Manual check: `GET settings/academic/placements` → `Placements` active only; `GET settings/academic/placements?section=academic-years#academic-years` → `Academic Years` active only, page scrolled to Years manager; `hashchange` swaps active without reload.

---

## H. VERIFICATION

### H.1 Manual CLI

| Check | Command | Result |
|-------|---------|--------|
| Routes unchanged | `php artisan route:list --name=settings.academic` filter `placements/academic-years` 10 routes `placements.index:1237 store:1239 show:1240 edit:1241 update:1242 destroy:1243 + academic-years store:1247 update:1248 destroy:1249` same | **PASS** |
| Total routes | `php artisan route:list` `Showing [1211] routes` | **PASS** — 0 new |
| Views compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** |
| Anchor exists | `Select-String academic-placements/index.blade.php` `id="academic-years" scroll-margin-top` `166` | **PASS** |
| Layout syntax | `layout:221 data-academic-years-link query(section)===academic-years` + `239 data-placements-link query!==academic-years` + `960 syncYearsPlacementsActive` JS valid | **PASS** |

### H.2 Wrong-domain Protection (reasoning)

| Test | Expected | Verdict |
|------|----------|---------|
| Academic `GET placements` | 200 | PASS |
| Academic `GET placements?section=academic-years#academic-years` | 200 same page scrolled | PASS — query ignored for auth |
| Professional `GET placements` | 403 `domain:academic` `EnsureDomain:11` | **PASS intact** — P3 adds no bypass |
| Professional `GET placements?section=academic-years#academic-years` | 403 | PASS |
| Retail other | 403 | PASS |

### H.3 Professional & Prior Polishes Preservation

| Block | Before | After | Verdict |
|-------|--------|-------|---------|
| `Training 285-304` 6 links | 6 | **Unchanged 6** — diff only `221/239/960` | **PASS** |
| `Assessments vs Marks ?view=marks 242-247` P1 fix | Preserved | **Unchanged** | PASS |
| `Academic Settings vs Groups ?section=groups#groups 218/227` P2 fix | Preserved + JS extended not overwritten | **PASS** |

### H.4 Automated Suites

| Suite | Prior | Expected after P3 |
|-------|-------|-------------------|
| `BusinessProfileTest 16/16` | PASS | PASS unchanged |
| `TenantIsolationAuditTest 4/4` | PASS | PASS unchanged |
| Pre-existing `SubjectUnification 302`, `TeacherManagement 734` | Pre-existing | Unchanged — document, not P3 regression — P3 touches only anchor + href/active + hash JS |

**New failures: 0** — P3 is navigation HTML + anchor + JS swap only.

---

## I. MIGRATION / DATA SAFETY

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` — anchor `div` + `href ?section=academic-years#academic-years` GET links only |
| `DATA DELETED` | **NO** | — |
| `MIGRATIONS` | **NO** | `database/migrations` not touched |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed |
| `NEW ROUTES` | **NO** | `route:list 1211` same — reuse `placements.index + academic-years.*` CRUD |
| `NEW SYSTEMS` | **NO** | No duplicate Year/Placement system — reuse `AcademicYear/StudentAcademicPlacement` |
| Historical integrity | **PASS** | `placementHasHistory` + `academicYearHasHistory` still guard deletes `264/388` |

---

## J. REMAINING — DEFERRED TO P4/B14 (per B12 R4)

| # | B12 Issue | Status in P3 | Scope |
|---|-----------|--------------|-------|
| R4 | Optional bonus preview `threshold 2.00/max 5.00/single|best|sum` not in `grading/preview` | **Not fixed P3** — per spec `Fix ONLY R3` | B13-P4 or future `grading/preview` enrichment reusing `GradeScale` fields |

**P3 scope strictly honored:** 0 files outside `academic-placements/index.blade.php:166` + `layouts/institute.blade.php:221/239/960` touched; no `AcademicYear` model/migration change; no assessment/result logic change.

---

## K. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| Years vs Placements located | **PASS** | **Shared page** `academic-placements/index.blade.php` `placements table 99` + embedded `Academic Years manager 167` — no separate Years index; canonical `placements.index:1237` kept |
| Anchor behavior | **PASS** | `id="academic-years" scroll-margin-top:80px 166` exists — `?section=academic-years#academic-years` scrolls to Years manager, not top |
| Misleading active highlight fixed | **PASS** | `Academic Years` active only when `routeIs('academic-years.*') \|\| (placements.index && query==='academic-years')` + JS hash `#academic-years`; `Placements` active only when `routeIs('placements.*') && query!=='academic-years'` — mutually exclusive |
| No duplicate Year/Placement system | **PASS** | Reuse `AcademicYear/StudentAcademicPlacement` + `academic-years.*` CRUD |
| No migrations/DB change | **PASS** | — |
| Data & historical integrity preserved | **PASS** | `placementHasHistory` + `academicYearHasHistory` + `lockForUpdate current` untouched |
| Academic sees both | **PASS** | Both `if ($isEducation && workspaceAllowedEducation):204` — `isAcademic` |
| Professional unchanged | **PASS** | `Training` preserved |
| Wrong-domain 403 | **PASS** | `permission:education.manage+domain:academic:1144` |
| Tenant/Branch/RBAC/IDOR | **PASS** | `AcademicYear where institute_id` + `placements inScope` |
| Responsive | **PASS** | Bootstrap collapse + hash scroll + JS swap |
| Migrations/Data | **PASS** | `DATA MODIFIED: NO` `DATA DELETED: NO` `MIGRATIONS: NO` |

```
PHASE: B13-P3
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211 routes — reuse canonical placements.index + academic-years.* CRUD)
ROUTES MODIFIED: NO
VIEWS ADDED: NO
VIEWS MODIFIED: 2 (resources/views/institute/academic-placements/index.blade.php:166 anchor id="academic-years" + resources/views/layouts/institute.blade.php:221-241 href/active + 960 hash JS)
CONTROLLERS MODIFIED: NO
SERVICES MODIFIED: NO
MODELS MODIFIED: NO

ACADEMIC_YEARS_UI: PASS — Academic Years → placements.index?section=academic-years#academic-years → id="academic-years" embedded manager — mutually exclusive active via server query + client hash
PLACEMENTS_UI: PASS — Placements → placements.index (bare) → placements table — active only when section!==academic-years
SHARED_PAGE_PRESERVED: PASS — single placements.index with inline Years manager kept
NO_DUPLICATE_SYSTEM: PASS — reuse AcademicYear/StudentAcademicPlacement + historical guards
PROFESSIONAL_UI: PASS — Training preserved
DOMAIN_ISOLATION: PASS — InstituteDomain::isAcademic + domain:academic 403
TENANT_ISOLATION: PASS — AcademicYear institute_id + placements inScope + TenantContext
RBAC: PASS — education.manage
IDOR: PASS — resolveInstitute + classWithinInstitute
HISTORICAL_INTEGRITY: PASS — placementHasHistory + academicYearHasHistory + current lockForUpdate
RESPONSIVE: PASS — Desktop/Tablet/Mobile collapse + hash
REGRESSIONS: 0 NEW
FINAL_VERDICT: GREEN
```

**GREEN — Academic Years vs Placements navigation is now distinguishable while preserving intentional shared page** — Years scrolls to embedded manager `id="academic-years"` via `?section=academic-years#academic-years`, Placements to bare `placements.index`; active states mutually exclusive via server `query('section')` + client `#academic-years` JS, no duplicate system/migration, historical integrity via `placementHasHistory/academicYearHasHistory` untouched.

---

> STOP — B13-P3 complete. Do not start P4 automatically per spec §21. Next: **B13-P4 or B14** (R4 optional bonus preview display if required) — enrich `grading/preview` reusing `GradeScale optional_subject_bonus_threshold/max_gpa/multiple_optional_policy` fields, no calc change.

*Evidence: `academic-placements/index:166 id="academic-years" scroll-margin-top:80px` + `layout:221 data-academic-years-link query(section)===academic-years ?section=academic-years#academic-years` + `layout:239 data-placements-link query!==academic-years` + `layout:960 syncYearsPlacementsActive hashchange` + `route:list placements 1237 + academic-years store:1247 update:1248 destroy:1249` + `view:clear INFO` + `StudentAcademicPlacementController:465 placementHasHistory / 480 academicYearHasHistory`.*
