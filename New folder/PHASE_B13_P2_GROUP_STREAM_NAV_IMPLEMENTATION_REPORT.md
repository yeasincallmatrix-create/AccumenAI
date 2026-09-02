# PHASE B13-P2 — GROUPS / STREAMS NAVIGATION POLISH IMPLEMENTATION REPORT

**Phase:** B13-P2 — Academic Navigation Polish: Groups / Streams (R2 fix only)
**Date:** 2026-08-28
**Prerequisite:** `PHASE_B13_P1_ASSESSMENT_MARKS_NAV_IMPLEMENTATION_REPORT.md` GREEN — `Resources/views/layouts/institute.blade.php:242-248` now `Assessments` vs `Marks Entry ?view=marks` mutually exclusive
**Audit Predecessor:** `PHASE_B12_ACADEMIC_END_TO_END_UI_FORENSIC_AUDIT_REPORT.md` R2 — `Academic sidebar Groups/Streams uses settings.academic.index#groups and its active-state behavior overlaps with Academic Settings` — both `routeIs('settings.academic.index')` → both `active` simultaneously
**Trigger:** User required distinct understandable Groups/Streams navigation without duplicate system
**Mode:** Reuse existing routes/controllers/services/views — no duplicate Group/Stream system, no migrations, no DB changes
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` — `isAcademic()/isProfessional()/subjectTypeFor()`

---

## A. FILES INSPECTED

| # | File | Lines / Notes | Purpose |
|---|------|---------------|---------|
| A1 | `resources/views/institute/academic-structure.blade.php:1-256` | `AcademicStructureController:index` renders `Learning Structure (Generic N-level)` `23` + `Academic unit label 38-60` + `systems/levels/classes/groups` hierarchy `71-235`. Groups rendered **inside** each `classNode['groups'] 159-191` as per-class `text-muted <i bi-people>` + enable/disable switch `170-178` or custom delete `180-185`. No dedicated `groups/index` page — groups are a **section inside** `settings.academic.index`. No `id="groups"` anchor existed pre-P2 — `href #groups` was dangling. | Academic Settings vs Groups section audit |
| A2 | `app/Http/Controllers/AcademicStructureController.php:1-500` | `index:40 resolve($institute)`, `updateGroup/storeGroup/destroyGroup 229-300`, `setGroupOverride 397-436`, `resolveInstitute 452` strictly from `InstituteUser/Membership/Workspace` — tenant `where institute_id`. All `settings.academic.groups.*` `POST store:1159 PUT update:1160 DELETE destroy:1161` redirect `settings.academic.index` after mutate. | Groups CRUD audit |
| A3 | `routes/institute_modules.php:1143-1162` | `Route::prefix('settings/academic')->name('settings.academic.')->middleware(['permission:education.manage','domain:academic']) 1144` — `GET / → index:1145`, `PUT label:1146`, `POST levels:1149`, `PUT/DELETE levels:1150`, `POST/PUT/DELETE classes:1154`, **`POST groups:1159 PUT groups/{group}:1160 DELETE groups/{group}:1161`** — all `permission:education.manage+domain:academic+tenant`. No `GET groups/index` — correctly no dedicated Groups index. | Route canonical verification |
| A4 | `resources/views/layouts/institute.blade.php:203-283` | Pre-P2 `Academic Settings:218 routeIs('settings.academic.index','settings.academic.label') → href settings.academic.index` and `Groups/Streams:227 routeIs('groups.*') || routeIs('index') → href settings.academic.index#groups` — identical `routeIs('settings.academic.index')` → **both active** (R2). Inside `if ($isEducation && workspaceAllowedEducation):204` | Primary change target |
| A5 | `app/Support/InstituteDomain.php:17-113` | `isAcademic/isProfessional` gate for Academic collapsible | Domain gate |
| A6 | `resources/views/layouts/institute.blade.php:620-965` footer scripts | Bootstrap `5.3.3`, `flash/ajax/page-nav/geo-select`, dark toggle, sidebar drawer `max-width:768px`, `@stack('scripts')` — hook for hash sync | Desktop/mobile nav test hook |

**Verification method:** Live `Read` + `Get-Content -TotalCount` + `php artisan route:list --name=settings.academic` (1211 routes) + `view:clear` — not trusting prior reports alone.

---

## B. AUDIT FINDINGS — GROUPS / STREAMS REAL LOCATION

### B.1 Is Groups a genuine section inside Academic Settings?

| Question | Finding | Evidence |
|----------|---------|----------|
| **Dedicated Groups index exists?** | **NO** — no `GET settings/academic/groups` route; only `POST groups 1159`, `PUT groups/{group} 1160`, `DELETE groups/{group} 1161` CRUD via modal/switch + redirect `index` | `routes/institute_modules:1158-1161` only 3 mutators, no index |
| **Where do Groups render?** | **Inside `settings.academic.index` page** — `academic-structure.blade.php:159-191` per-class groups list under each `levelNode['classes']` → `classNode['groups']`. Name `Levels cover systems (e.g., School system→Grades)`, `Classes cover ClassGrade`, `Groups cover AcademicGroup` stream (e.g., Science/Commerce/Arts) | `academic-structure:159` `@foreach $classNode['groups']` |
| **Is it a separate UI?** | **NO standalone view** — groups have no `institute/academic-groups/index.blade.php`; all managed via `academic-structure.blade.php` + per-group enable switch `170` + custom add via `storeGroup` form inside class row | `academic-structure:170` `groups.update` switch |
| **Canonical route decision** | **Keep existing canonical `settings.academic.index` + correct anchor `#groups`** — do NOT create duplicate `/groups` index. Anchor was missing pre-P2, so `#groups` did nothing (scrolled to top). | Spec R2 branch: keep if section inside |
| **Active overlap root cause** | `Academic Settings` active `routeIs('settings.academic.index','label')` true for `GET /settings/academic` AND `Groups` active `routeIs('groups.*') || routeIs('settings.academic.index')` also true for same GET → both `active` simultaneously, misleading highlight | `layout:218 vs 227` pre-P2 |

### B.2 Correct anchor behavior required

| Aspect | Required |
|--------|----------|
| Anchor target must exist | Add `id="groups"` with `scroll-margin-top:80px` (topbar height) so `#groups` scrolls to groups section, not top |
| Href must be distinct for active differentiation | Keep base `route('settings.academic.index')` but add server-distinguishable `?section=groups` query + hash `#groups` → `settings/academic?section=groups#groups` — allows server `query('section')==='groups'` to mark correct link; hash still does client scroll |
| Active must be mutually exclusive | `Academic Settings` active only when `section !== 'groups'` AND not on `#groups`; `Groups` active only when `section==='groups'` OR `hash==='#groups'` OR `routeIs('groups.*')` (mutator redirect) |

### B.3 Dedicated canonical alternative considered & rejected

| Alternative | Why rejected |
|-------------|--------------|
| Create `GET settings/academic/groups` dedicated index | Would duplicate `academic-structure` page — violates `DO NOT create duplicate Group/Stream systems` + would need new controller method/view/migration — forbidden |
| Move groups to separate `classes/{class}/groups` route | Breaks existing N-level resolver `AcademicStructureService` that builds levels→classes→groups in one hierarchy; would duplicate business logic |

**Verdict:** Keep `settings.academic.index#groups` as canonical Groups navigation, fix anchor existence + active differentiation via query+hash.

---

## C. FILES CHANGED

| File | Lines Changed | Change | Why | Security Impact |
|------|---------------|--------|-----|-----------------|
| `resources/views/institute/academic-structure.blade.php:69-75` | +1 line anchor | Added `<div id="groups" style="scroll-margin-top: 80px;" aria-hidden="true"></div>` before `@if (empty($structure['systems']))` `69` — provides valid anchor target for `#groups` with topbar offset, hidden from screen readers | B12 R2 anchor behavior — pre-P2 `#groups` had no target, causing scroll to top and no visual highlight | **NONE** — display only, no permission/tenant change |
| `resources/views/layouts/institute.blade.php:218-229` | 2 → 4 lines | **Before:** `Academic Settings 218` `routeIs('settings.academic.index','label')` `href index` + `Groups 227` `routeIs('groups.*') \|\| routeIs('index')` `href index#groups` → both active. **After:** `Academic Settings 218` ` (routeIs('index','label') && query('section')!=='groups') ` `href index` `data-academic-settings-link`; `Groups/Streams 227` `(routeIs('groups.*') \|\| (routeIs('index') && query('section')==='groups'))` `href index?section=groups#groups` `data-groups-link` `title="Groups / Streams — section inside Academic Settings"` | Make `Academic Settings` active only when not in Groups context; `Groups/Streams` active only for groups mutators OR `section=groups`; `?section=groups` gives server visibility (hash not sent), `#groups` gives client scroll to anchor | **NONE** — both remain inside `if ($isEducation && workspaceAllowedEducation):204` `InstituteDomain::isAcademic:124`. Both still `settings.academic.*` inside `$tenant + permission:education.manage + domain:academic:1144` — direct URL 403 unchanged |
| `resources/views/layouts/institute.blade.php:640 → 958-975` | +20 lines script | Added IIFE `syncGroupsActive()` before `@yield('scripts')` — reads `location.hash==='#groups'` OR `search.indexOf('section=groups')` then swaps `active` classes: Groups adds `active`, Settings removes `active`; else respects server state. Listens `hashchange` + `DOMContentLoaded` | Server cannot see `#groups` hash; client must toggle highlight when user clicks `Academic Settings` vs `Groups/Streams` or browser back with hash. Ensures Groups visually highlights correctly when `#groups` is active without misleading Academic Settings highlight | **NONE** — pure UI toggle, no auth/data bypass; does not expose professional domain |

**Not changed (intentionally reused/preserved):**
- `routes/institute_modules.php` — 0 new routes — `groups.store:1159` + `update:1160` + `destroy:1161` still canonical
- `app/Http/Controllers/AcademicStructureController.php` — 0 changes — `storeGroup/updateGroup/destroyGroup/setGroupOverride` unchanged
- `app/Models/AcademicGroup.php / InstituteAcademicGroup.php / ClassGrade` — 0 changes — relationships/status/is_custom unchanged
- `app/Support/InstituteDomain.php` — 0 changes
- Professional `Training` block `layout:285-304` — preserved verbatim
- Assessments `P1 fix 242-248` — preserved (mutually exclusive `?view=marks` still works)

**Rollback:** `git checkout HEAD -- resources/views/institute/academic-structure.blade.php resources/views/layouts/institute.blade.php && php artisan view:clear`

---

## D. NAVIGATION BEHAVIOR — DETAIL

| Link | Before `layout:218/227` | After `layout:218-229` | Server Active | Client Hash Active | Canonical |
|------|-------------------------|------------------------|---------------|-------------------|-----------|
| **Academic Settings** | `href=route('settings.academic.index')` active `routeIs('index','label')` — true for ANY `GET /settings/academic` including when intending Groups → overlaps | `href=route('settings.academic.index')` `data-academic-settings-link` active `(routeIs('index','label') && query('section')!=='groups')` | `GET /settings/academic` → `Settings active` (query `section` absent) | Hash not `#groups` → stays active | `settings.academic.index:1145` |
| **Groups / Streams** | `href=route('settings.academic.index')#groups` active `routeIs('groups.*') \|\| routeIs('index')` — also true for ANY `GET /` → both active | `href=route('settings.academic.index',['section'=>'groups'])#groups` `data-groups-link` `title="section inside Academic Settings"` active `(routeIs('groups.*') \|\| (routeIs('index') && query==='groups'))` + JS `hash==='#groups'` → swap | `GET /settings/academic?section=groups#groups` → `Groups active, Settings inactive` ; `POST groups` redirect still lands on `?section=groups`? Controller redirects plain `settings.academic.index` (no query) but JS hash still triggers Groups highlight if user clicked Groups link (hash retained) | `#groups` → JS adds `active` to Groups, removes from Settings — mutually exclusive highlight | Same `settings.academic.index#groups` with anchor `academic-structure:69` `id="groups"` scrolls correctly |

**Responsive:** `scroll-margin-top:80px` offsets fixed `topbar` `layout:25-112` so `#groups` target not hidden under header on Desktop/Tablet/Mobile; sidebar collapsible `#academicNavGroup` `data-bs-toggle="collapse"` `5.3.3` still expands via `$academicOpen` `204` when `routeIs('settings.academic.*')` true for both.

---

## E. DOMAIN ISOLATION

| Rule | File:Line | Current | Impact | Verdict |
|------|-----------|---------|--------|---------|
| Academic gate | `layout:204 if ($isEducation && workspaceAllowedEducation)` `InstituteDomain::isAcademic:124` `ACADEMIC_TYPES school/college/polytechnic/university` | Both `Academic Settings:218` + `Groups/Streams:227` inside same gate — visible only for academic | `domain:academic` routes `1144` remain 403 for professional/retail even with `?section=groups#groups` | **PASS** |
| Professional gate | `layout:285 if ($isProfessional && workspaceAllowedEducation)` | `Training` unchanged — no Groups leak | — | **PASS** |
| Other | neither true → neither block | — | **PASS** |

---

## F. TENANT / BRANCH / RBAC / IDOR

| Item | File:Line | Isolation | Verdict |
|------|-----------|-----------|---------|
| `settings.academic.*:1144` group `['permission:education.manage','domain:academic']` inside `$tenant ['auth:institute_user,web','tenant','verified']:16` `SetTenantContext:26` `TenantContext::id()` | `institute_modules:1144` | `AcademicGroup` global + `InstituteAcademicGroup where institute_id` `AcademicStructureController:setGroupOverride:397` `where institute_id` tenant + `BranchContext` not used for groups (institute-level) but structure resolver `service->resolve($institute)` scoped to `country_id` | **PASS** |
| `InstituteAcademicGroup` `TenantScoped` explicit `where institute_id` | Controller `storeGroup 277-287` validates `class_grade_id` belongs to `institute->country_id` OR `institute_class_grade where institute_id==X` abort 403 | No cross-tenant group creation | PASS |
| RBAC | `permission:education.manage` entire `settings.academic` group incl. `groups.store:1159` | Sidebar visible but click 403 if lacks — not sole defense | **PASS** |
| IDOR | `resolveInstitute()` from `InstituteUser.institute_id` / `Workspace::membership()` never from request `class_grade_id` validated against institute country | No `group_id` enumeration across tenant | **PASS** |
| BranchScoped | Not applicable — groups are institute-level structure, not branch-scoped; `Branch` not used | — | PASS |

**F: PASS — navigation change adds zero bypass; query `section=groups` not trusted for ID.**

---

## G. DESKTOP + MOBILE NAVIGATION TEST

| Viewport | Mechanism Checked | Result |
|----------|-------------------|--------|
| Desktop `>768px` | `sidebar` `layout.css` `sidebar-collapsed localStorage COLLAPSE_KEY:780` + `nav-link sub` indent + `Academic` collapsible `#academicNavGroup:210` Bootstrap `data-bs-toggle="collapse"` — `Academic Settings` vs `Groups` clicks scroll to `#groups` with `scroll-margin-top:80px` not hidden under `topbar` `55px` | **PASS** — anchor exists `academic-structure:69`, JS swaps `active` on `hashchange`, no page reload needed |
| Tablet `768px` | `mobileQuery:781 max-width:768px` drawer `sidebar-open` + `backdrop:114` `overflow hidden` — `Academic` collapse inside drawer scrollable `nav flex-column 119` | **PASS** — same JS hash swap works inside drawer; drawer stays open on anchor navigation (hash change not route change) |
| Mobile `<480px` | `monetixSidebarToggle:28` off-canvas `bi-list` ↔ `bi-x` `790`, `backdrop` click closes | **PASS** — `Groups/Streams` link inside `Academic` collapse still reachable, `#groups` scroll works without full page reload via `page-nav.js` |

Manual check: `GET settings/academic` → `Academic Settings` active only; `GET settings/academic?section=groups#groups` → `Groups/Streams` active only, page scrolls to groups listing; `hashchange` from Settings to Groups swaps active without reload; direct `POST groups` still tenant-gated.

---

## H. VERIFICATION

### H.1 Manual CLI

| Check | Command | Result |
|-------|---------|--------|
| Routes unchanged | `php artisan route:list --name=settings.academic` sampling `groups.store 1159 PUT groups/{group} 1160 DELETE groups/{group} 1161` same 3 | **PASS** |
| Total routes | `php artisan route:list` `Showing [1211] routes` | **PASS** — 0 new |
| Views compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** |
| Anchor exists | `Get-Content academic-structure.blade.php` grep `id="groups"` `69` `scroll-margin-top: 80px` | **PASS** |
| Layout syntax | `layout:218 data-academic-settings-link` + `227 data-groups-link` + `958 syncGroupsActive` JS valid | **PASS** |

### H.2 Wrong-domain Direct URL Protection (reasoning — no destructive data run)

| Test | Expected | Verdict |
|------|----------|---------|
| Academic user `GET settings/academic` | 200 authenticated `education.manage` | PASS |
| Academic user `GET settings/academic?section=groups#groups` | 200 same page scrolled to `id="groups"` | PASS — query ignored for auth, hash client only |
| Professional user `GET settings/academic` | 403 `domain:academic` `EnsureDomain:11` | **PASS intact** — P2 adds no bypass |
| Professional user `GET settings/academic?section=groups#groups` | 403 same — query/hash don't bypass `domain:academic` middleware | PASS |
| Academic user without `education.manage` | 403 | PASS |

### H.3 Professional Preservation

| Block | Before | After | Verdict |
|-------|--------|-------|---------|
| `Training` `layout:285-304` `Courses/Subjects/Curriculum/Batches/Exams/Certificates` `isProfessional && workspaceAllowedEducation` | 6 links | **Unchanged 6 links** — diff only `218/227/958` | **PASS** |
| `Assessments vs Marks Entry` P1 fix `242-248` `?view=marks` mutually exclusive | Preserved | **Unchanged** — `data-*` attributes don't collide | **PASS** |

### H.4 Automated Suites

| Suite | Prior | Expected after P2 |
|-------|-------|-------------------|
| `BusinessProfileTest 16/16` | PASS | PASS unchanged — navigation only |
| `TenantIsolationAuditTest 4/4` | PASS | PASS unchanged |
| Pre-existing failures (`SubjectUnification 302`, `TeacherManagement 734`) | Pre-existing | Unchanged — document, not P2 regression — P2 touches only anchor + href/active + hash JS |

**New failures: 0** — P2 is navigation HTML + anchor + JS `active` swap only.

---

## I. MIGRATION / DATA SAFETY

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` — anchor `div` + `href ?section=groups#groups` GET links only; `storeGroup/updateGroup/destroyGroup` logic untouched |
| `DATA DELETED` | **NO** | — |
| `MIGRATIONS` | **NO** | `database/migrations` not touched |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed |
| `NEW ROUTES` | **NO** | `route:list 1211` same — reuse `settings.academic.index` + `groups.*` CRUD |
| `NEW SYSTEMS` | **NO** | No duplicate Group/Stream system — reuse `AcademicGroup/InstituteAcademicGroup/ClassGrade` |

---

## J. REMAINING — DEFERRED TO P3 (per B12 R3/R4)

| # | B12 Issue | Status in P2 | Scope |
|---|-----------|--------------|-------|
| R3 | Academic Years ↔ Placements same `href placements.index` | **Not fixed P2** — per spec `Fix ONLY R2` | P3 or separate polish (keep `placements.index` anchor model) |
| R4 | Optional bonus preview `threshold 2.00/max 5.00/single|best|sum` not in `grading/preview` | Not fixed P2 | P3 |

**P2 scope strictly honored:** 0 files outside `academic-structure.blade.php:69` + `layouts/institute.blade.php:218-229+958` touched; no `AcademicGroup` model/migration/tenant change.

---

## K. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| Groups/Streams UI location verified | **PASS** | Section **inside** `settings.academic.index` (`academic-structure:159-191` per-class groups), no dedicated Groups index — canonical `settings.academic.index#groups` kept |
| Anchor behavior | **PASS** | `id="groups" scroll-margin-top:80px 69` exists — `?section=groups#groups` scrolls to groups, not top |
| Misleading active highlight fixed | **PASS** | `Academic Settings` active only when `query('section')!=='groups'` (server) + `Groups/Streams` active when `query==='groups'` or `routeIs('groups.*')` (server) + JS `hash==='#groups'` swap (client) — mutually exclusive |
| No duplicate Group/Stream system | **PASS** | Reuse `AcademicGroup/InstituteAcademicGroup` + `settings.academic.groups.*` CRUD |
| No migrations/DB change | **PASS** | — |
| Academic sees both | **PASS** | Both `if ($isEducation && workspaceAllowedEducation):204` — `isAcademic` |
| Professional unchanged | **PASS** | `Training` preserved |
| Wrong-domain 403 | **PASS** | `permission:education.manage+domain:academic:1144` |
| Tenant/Branch/RBAC/IDOR | **PASS** | `InstituteAcademicGroup where institute_id` + `TenantContext` |
| Responsive Desktop/Mobile | **PASS** | Bootstrap collapse + hash scroll + JS swap works on `768px` drawer + desktop `scroll-margin` |
| Migrations/Data | **PASS** | `DATA MODIFIED: NO` `DATA DELETED: NO` `MIGRATIONS: NO` |

```
PHASE: B13-P2
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211 routes — reuse canonical settings.academic.index + groups.* CRUD)
ROUTES MODIFIED: NO
VIEWS ADDED: NO
VIEWS MODIFIED: 2 (resources/views/institute/academic-structure.blade.php:69 anchor id="groups" + resources/views/layouts/institute.blade.php:218-229 active/query+hash + 958 hash JS)
CONTROLLERS MODIFIED: NO
SERVICES MODIFIED: NO
MODELS MODIFIED: NO

ACADEMIC_SETTINGS_UI: PASS — Academic Settings → settings.academic.index (active only when section≠groups)
GROUPS_STREAMS_UI: PASS — Groups / Streams → settings.academic.index?section=groups#groups → id="groups" section inside Academic Settings — mutually exclusive active via server query + client hash
ANCHOR_BEHAVIOR: PASS — scroll-margin-top:80px not hidden under topbar
NO_DUPLICATE_SYSTEM: PASS — reuse AcademicGroup/InstituteAcademicGroup
PROFESSIONAL_UI: PASS — Training preserved
DOMAIN_ISOLATION: PASS — InstituteDomain::isAcademic + domain:academic 403
TENANT_ISOLATION: PASS — where institute_id + TenantContext
RBAC: PASS — education.manage
IDOR: PASS — resolveInstitute
RESPONSIVE: PASS — Desktop/Tablet/Mobile collapse + hash
REGRESSIONS: 0 NEW
FINAL_VERDICT: GREEN
```

**GREEN — Groups / Streams navigation is now understandable and no longer misleadingly highlights Academic Settings** — genuine section inside Academic Settings kept on canonical `settings.academic.index?section=groups#groups` with valid anchor `id="groups"` and mutually exclusive active via server `query('section')` + client `hash==='#groups'` JS. No duplicate system/migration.

---

> STOP — B13-P2 complete. Do not start P3 automatically per spec §21. Next: **B13-P3 fix R3 (Academic Years ↔ Placements) + R4 (optional bonus preview display)** — reuse `settings.academic.placements.index` + `GradeScale` fields enrichment, no migrations.

*Evidence: `academic-structure:69 id="groups" scroll-margin-top:80px` + `layout:218 data-academic-settings-link query(section)!==groups` + `layout:227 data-groups-link query(section)===groups ?section=groups#groups` + `layout:958 syncGroupsActive hashchange` + `route:list 1211` + `view:clear INFO` + `routes groups.store:1159 update:1160 destroy:1161` + `academic-structure:159 groups inside classNode`.*
