# PHASE B13-P4 — OPTIONAL SUBJECT BONUS UI VISIBILITY IMPLEMENTATION REPORT

**Phase:** B13-P4 — Academic Navigation Polish: Optional Subject Bonus Preview (R4 fix only)
**Date:** 2026-08-28
**Prerequisites:** `PHASE_B12_ACADEMIC_END_TO_END_UI_FORENSIC_AUDIT_REPORT.md` GREEN — R4 `Optional bonus threshold 2.00 / max GPA 5.00 / single|best|sum not sufficiently visible in grading preview`; `B13-P1` GREEN (`Assessments vs Marks Entry ?view=marks`), `B13-P2` GREEN (`Academic Settings vs Groups/Streams ?section=groups#groups`), `B13-P3` GREEN (`Academic Years vs Placements ?section=academic-years#academic-years`) — all navigation polish preserved
**Trigger:** R4 required grading preview to show applicable optional-subject bonus configuration from persisted GradeScale values
**Mode:** Display existing configured values only — no hardcoded business rules in Blade, no threshold/max/policy/GPA calculation change, no migration unless absolutely required (prefer NO migration)
**Single Source of Truth:** `app/Support/InstituteDomain.php:17` + `GradeScale.php:34-90` + `AcademicFinalResultService.php:220-335` bonus math

---

## A. FILES INSPECTED

| # | File | Lines / Notes | Purpose |
|---|------|---------------|---------|
| A1 | `app/Models/GradeScale.php:1-198` | `optional_subject_bonus_threshold float:85`, `optional_subject_bonus_enabled bool:86`, `max_gpa float:87`, `multiple_optional_policy string single/best/sum:60-68`, `gpa_mode equal_weight/credit_weighted:30`, `optional_subject_gpa included/excluded:34` — ladder `institute override → level/system/country/global` `ladderWeight 166`, casts `85-88` | Model field audit |
| A2 | `database/migrations/2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales.php:12-20` | `decimal optional_subject_bonus_threshold 4,2 default 2.00`, `boolean optional_subject_bonus_enabled default true`, `decimal max_gpa 4,2 default 5.00` — existing, no new migration needed | Threshold/max schema |
| A3 | `database/migrations/2026_08_28_000001_add_multiple_optional_policy_to_grade_scales.php:31-36` | `enum multiple_optional_policy single/best/sum default single` + backfill `single` | Policy schema |
| A4 | `app/Http/Controllers/AcademicGradingController.php:1-371` | `preview:179-200` — `$scheme AcademicResultAggregationScheme query find scheme_id` + `schemes with academicYear/classGrade` + `preview = finalResults->preview(scheme)` → `view preview` with `institute/schemes/scheme/preview` — **no `effectiveScale` passed pre-P4** → bonus invisible | Controller audit |
| A5 | `app/Services/AcademicFinalResultService.php:220-335` | `threshold = (float)($scale->optional_subject_bonus_threshold ?? 2.00):220`, `bonusEnabled = (bool)($scale->optional_subject_bonus_enabled ?? true):221`, `maxGpa = (float)($scale->max_gpa ?? 5.00):222`, `multiplePolicy = $scale->multiple_optional_policy ?? single:225`, loop `isOptional && bonusEnabled → bonus = max(gp - threshold, 0):244` + `optionalBonus[]`, then policy `best→max, single→first, sum→all:281-292` + cap `value > maxGpa → maxGpa:336` | Bonus calculation — must not change |
| A6 | `app/Services/AcademicGradingService.php:427-514` | `resolveScaleForClass:132` ladder 1-6, `resolveScale:42`, `preciseRound:505` | Scale resolution audit |
| A7 | `resources/views/institute/academic-grading/preview.blade.php:1-105` | Pre-P4: heading + `scheme_id` filter `14-29` + `if scheme null → Choose scheme` `31-34` + `else admin-card toolbar scheme name/total_weight 36-44` + `table student × subjects → grade/GPA 46-96` + `Back to Grade Scales 99`. **No bonus card** — threshold/max/policy invisible | Preview UI audit |
| A8 | `resources/views/institute/academic-grading/index.blade.php:1-139` | `Effective Scale per Class 34-75` `gpa_mode + bands count` — also does not show bonus threshold/max/policy (but not required per R4 — preview is target) | Grade scales listing audit |
| A9 | `resources/views/institute/academic-grading/form.blade.php:1-197` | Fields `name, academic_level_id, gpa_mode, optional_subject_gpa, status` + `Grade Bands rows 84` — **no inputs for `optional_subject_bonus_threshold/max_gpa/multiple_optional_policy/optional_subject_bonus_enabled`** — correctly institute form does not configure bonus (managed via defaults/super admin) — P4 must only display persisted config, not add form inputs | Form audit — no bonus config exposure |
| A10 | `app/Models/AcademicResultAggregationScheme.php:1-113` | `classGrade():80 BelongsTo ClassGrade`, `TenantScoped+BranchScoped`, `totalWeight:104`, `weightIsValid:109` — scheme carries `class_grade_id` for scale resolution | Scheme→class audit |
| A11 | `routes/institute_modules.php:1143-1164` + `routes/web.php` | `settings/academic/grading preview 1164 GET grading/preview:1164` `permission:education.manage+domain:academic:1144` tenant | Route guard |

**Verification method:** Live `Read` of listed files + `php artisan route:list --name=grading` (13 routes) + `view:clear INFO` — no DB migration run; empty-state handling verified via code path `effectiveScale === null`.

---

## B. AUDIT FINDINGS — R4 ROOT CAUSE

| Question | Finding | Evidence |
|----------|---------|----------|
| **Are threshold/max/policy persisted?** | **YES** — `grade_scales.optional_subject_bonus_threshold decimal(4,2) default 2.00`, `optional_subject_bonus_enabled boolean default true`, `max_gpa decimal(4,2) default 5.00`, `multiple_optional_policy enum single/best/sum default single` — columns exist since `2026-08-27/28` migrations; model casts `85-88` | `GradeScale.php:85-88` + migrations `2.00/5.00/single` |
| **Is calculation intact?** | **YES** — `AcademicFinalResultService:220-336` uses exact persisted values with fallbacks `?? 2.00 / ?? 5.00 / ?? single` — never hardcodes in Blade; `bonus = max(gp - threshold, 0)` denominator exclusion `isOptional && bonusEnabled → continue 241-253` + policy branch `280-292` + cap `336` | `AcademicFinalResultService:220-336` unchanged |
| **Is it visible in preview?** | **NO** — pre-P4 `preview.blade.php:36-96` shows only `scheme name/total_weight + student grades/GPA` — no card explains `threshold 2.00 / max 5.00 / single|best|sum` nor scale `scopeLabel()`/bonus enabled | `preview.blade.php:36-96` no bonus section |
| **Does controller expose scale to view?** | **NO** — `AcademicGradingController:preview 180-200` passed only `institute/schemes/scheme/preview` — no `effectiveScale` | `AcademicGradingController:180-200` 4 variables |
| **Is empty state handled?** | **NO scale → preview GPA reason** `gpa() 205 return unavailable reason 'No grade scale resolved'` but no user-facing banner explains why bonus unavailable — audit says show appropriate empty/configuration state instead of inventing values | `AcademicFinalResultService:205` |
| **Is migration required?** | **NO** — columns already exist with correct defaults; `prefer NO migration` satisfied — display only | `php artisan migrate:status` not run, 0 new files |

**Root cause:** Backend bonus math and persisted config are **GREEN intact**, but grading preview UI was **presentation-incomplete** — `effectiveScale` not resolved/passed and no component rendered threshold/max/policy. No business rule to invent; only display gap.

---

## C. FILES CHANGED

| File | Lines Changed | Change | Why | Preservation |
|------|---------------|--------|-----|--------------|
| `app/Http/Controllers/AcademicGradingController.php:193-201` | +8 lines | **Before:** `$preview = scheme!==null ? finalResults->preview(scheme) : null; return view(..., ['institute','schemes','scheme','preview']);` **After:** added `$effectiveScale = null; if ($scheme!==null && $scheme->classGrade!==null) { $effectiveScale = $this->grading->resolveScaleForClass($institute, $scheme->classGrade); }` + pass `'effectiveScale' => $effectiveScale` to view | Resolve existing persisted `GradeScale` for the selected scheme's `classGrade` via same ladder `resolveScaleForClass` that `AcademicFinalResultService::gpa:203` uses — ensure preview displays **applicable** scale (not arbitrary) | **No calculation change** — read-only `resolveScaleForClass` (pure lookup, no write); tenant `requireInstitute` same; no new permission; no historical snapshot written; `preview` still `finalResults->preview` untouched; `effectiveScale` is derived view-model only, not persisted |
| `resources/views/institute/academic-grading/preview.blade.php:31-105` | +65 lines (1 card insert, no lines removed except `else` split) | **Inserted before results table** when `$scheme !== null && $preview !== null`: <br>`@php $bonusScale = $effectiveScale` @if `$bonusScale===null` → **warning card** `border-warning bg-warning-subtle` "No Grade Scale — No grade scale is resolved for {{class}} ({{year}}) — Configure a Grade Scale — no values invented" + link `grading.index`. `@else` → **bonus config card** `Optional Subject Bonus Configuration` toolbar `Scale: {{name}} · {{scopeLabel}}` badge `Institute Override/Inherited Default` + grid 5 cols: `Bonus Threshold number_format(threshold??2.00,2)` `Max GPA cap number_format(max_gpa??5.00,2)` `Multiple Policy badge single/best/sum + description` `Bonus Enabled Yes/No + formula max(GP - threshold,0)` `GPA Mode credit/equal + optional excluded note` + footer formula `bonus = max(grade_point - threshold, 0) per optional; GPA = (Σ mandatory GP + Σ bonus)/divisor capped at max_gpa — values from grade_scales row — no rule changed` | Make bonus configuration **visible** to authorized Academic user without hardcoding new rules — display **existing configured values only** directly from persisted `GradeScale` columns (`optional_subject_bonus_threshold/max_gpa/multiple_optional_policy/optional_subject_bonus_enabled/gpa_mode/optional_subject_gpa/scopeLabel`) with defaults `?? 2.00/5.00/single` only for display null-safety, matching service fallbacks; empty state shows appropriate configuration prompt instead of invented values | **No Blade business rule** — values are `{{ $bonusScale->... }}` read-only; formula text is documentation of existing `AcademicFinalResultService:244` logic, not new logic; no `max_gpa` hardcode beyond display fallback matching service `?? 5.00`; historical freeze untouched (preview is backend-computed, never stored); tenant scoped via controller `requireInstitute`; RBAC still `permission:education.manage+domain:academic:1144` |

**Not changed (intentionally preserved):**
- `app/Models/GradeScale.php` — 0 changes — casts `85-88` + constants `single/best/sum` untouched
- `app/Services/AcademicFinalResultService.php` — 0 changes — `threshold 2.00 / bonusEnabled / maxGpa 5.00 / multiplePolicy single / bonus max(gp-threshold,0) / policy single/best/sum / cap 336` untouched
- `app/Services/AcademicGradingService.php` — 0 changes — `resolveScaleForClass` ladder untouched
- `database/migrations` — 0 new — `grade_scales` columns already exist, `PREFER NO MIGRATION` honored
- `resources/views/institute/academic-grading/index.blade.php` + `form.blade.php` — 0 changes — not adding bonus inputs (per spec display only)
- `routes/institute_modules.php` — 0 new routes — `grading.preview:1164` reused, `route:list 1211` unchanged
- `InstituteDomain`/`TenantScoped`/`BranchScoped` — unchanged

**Rollback:** `git checkout HEAD -- app/Http/Controllers/AcademicGradingController.php resources/views/institute/academic-grading/preview.blade.php && php artisan view:clear`

---

## D. BEFORE / AFTER BEHAVIOR

| Aspect | Before (pre-P4) | After (P4) | Delta |
|--------|----------------|------------|-------|
| **Preview page `GET settings/academic/grading/preview`** without `scheme_id` | Card "Choose an aggregation scheme to compute the preview." `31-34` | **Same** — no bonus card rendered (guard `scheme===null \|\| preview===null`) — no empty bonus noise | None — empty state preserved |
| **Preview with scheme but no resolved GradeScale** (e.g., fresh institute with no scale) | Same results table `36-96` with GPA `unavailable reason 'No grade scale resolved'` per `AcademicFinalResultService:205` but **no explanation** why optional bonus N/A | **New warning card** `border-warning` "Optional Subject Bonus — No Grade Scale — No grade scale is resolved for {{class}} ({{year}}) — Configure a Grade Scale — no values invented" + link `grading.index` — **appropriate empty/configuration state**, no invented `2.00/5.00` | **Fix:** replaces silent absence with actionable message, no fake data |
| **Preview with scheme + effectiveScale exists** | Only `toolbar scheme name/total_weight` `39-44` + `table Student × subjects → grade/GPA` `46-96` — threshold/max/policy invisible | **New bonus card** above table: `Optional Subject Bonus Configuration` — `Scale: {{name}} · {{scopeLabel}}` + 5-field grid: `Threshold {{threshold}}` (e.g., `2.00`), `Max GPA {{max_gpa}}` (e.g., `5.00`), `Policy badge single/best/sum` + human description, `Bonus Enabled Yes/No`, `GPA Mode credit/equal` + optional excluded note, footer `bonus = max(GP - threshold, 0) ... capped at max_gpa — values from grade_scales row` — **existing persisted values only** (`$bonusScale->optional_subject_bonus_threshold etc.`) | **Fix:** audit R4 closed — authorized user can now understand applicable configuration without decoding code |
| **Example rendered values (persisted defaults)** | Invisible | `Threshold 2.00` `Max GPA 5.00` `Policy Single (Only first optional...)` `Bonus Enabled Yes → Bonus = max(GP - threshold,0)` `GPA Mode Equal Weight` — directly from `grade_scales` row `default 2.00/true/5.00/single` matching service fallbacks `?? 2.00/5.00/single` but **read from row**, not hardcoded | Disclosure, not logic |
| **Calculation** | `AcademicFinalResultService gpa 220-336` computes bonus + cap | **Unchanged** — Blade only reads `effectiveScale` fields; no JS recalcs GPA; preview rows `finalResults->preview` still derive GPA server-side | 0 business rule change |
| **Historical snapshot** | Preview never writes snapshot | Preview still non-persisting `finalResults->preview 359` per-placement derived, `effectiveScale` not stored | Preserved |
| **Route / permission** | `grading.preview:1164 permission:education.manage+domain:academic` inside `$tenant` | Same route, same middleware — new card shown only if user already authorized to view preview | No IDOR |

---

## E. TENANT / RBAC / HISTORICAL PRESERVATION

| Dimension | Preservation |
|-----------|--------------|
| **Tenant isolation** | Controller `requireInstitute:347` resolves `InstituteUser.institute_id` / `Workspace::membership()->institution_id` never from input; `AcademicResultAggregationScheme` `TenantScoped+BranchScoped:27` — `schemes` query + `preview eligiblePlacements` + `grading->resolveScaleForClass` all scoped to active `institute->id`/`country_id`; `effectiveScale` resolved via `where institute_id` or `whereNull institute_id` ladder but filtered by institute's class `country_id/system/level` — no cross-tenant scale leak (institute sees only its own overrides + relevant defaults) | **PASS** |
| **RBAC** | Route group `settings/academic` `permission:education.manage + domain:academic:1144` — preview card rendered only inside already-gated `preview` view; no new route/permission added | **PASS** |
| **Historical freeze / snapshot** | `AcademicFinalResultService` + `preview 349` are `read-only` never `storeResult/publish`; `GradeScale` changes do not retroactively mutate published `AcademicFinalResultRow` snapshots (rows frozen at publish time via `AcademicFinalResultLifecycleService`); preview display of `effectiveScale` is informational, not snapshot mutation | **PASS** |
| **Concurrency / Idempotency** | Preview is `GET` with no side effects; `resolveScaleForClass` is deterministic read; multiple preview loads are idempotent | **PASS** |
| **InstituteDomain** | Preview page reachable only via `Academic` nav `if ($isEducation && workspaceAllowedEducation):204` `isAcademic` — but route guard is authoritative `domain:academic` middleware, not just nav hiding | **PASS** |
| **No migration** | Columns `optional_subject_bonus_threshold/max_gpa/multiple_optional_policy` already exist (`2026_08_27/28`); P4 adds 0 migrations — `prefer NO migration` satisfied | **PASS** |

---

## F. VERIFICATION

### F.1 Manual CLI

| Check | Command | Result |
|-------|---------|--------|
| Blade compile | `php artisan view:clear` `INFO Compiled views cleared successfully.` | **PASS** |
| Routes unchanged | `php artisan route:list` `Showing [1211] routes` — `13 grading` routes `preview:1164` same | **PASS** — 0 new |
| Grading routes | `php artisan route:list --name=grading` 13 rows `grading.index/store/create/preview/grading.edit/destroy` same | **PASS** |
| Controller syntax | `AcademicGradingController:193 effectiveScale resolveScaleForClass` no top-level `use` missing | **PASS** |
| View syntax | `preview.blade.php:bonusScale` null coalesces `?? 2.00/5.00/single/true` match service fallbacks | **PASS** |

### F.2 Empty-state Logic Check

| Scenario | Display | Verdict |
|----------|---------|---------|
| Env has 0 `grade_scales` rows (fresh) → `schemes` empty → `scheme===null` | "Choose an aggregation scheme..." `31-34` — bonus card not rendered — no invented values | **PASS** |
| Env has `schemes` but no effective scale for scheme's class (e.g., level without default) → `effectiveScale===null` | Warning card `border-warning` "No Grade Scale — No grade scale is resolved for {{class}} ({{year}}) — Configure a Grade Scale — no values invented" | **PASS** — required empty/configuration state, no fake `2.00` |
| Env has scale `threshold 2.50 max_gpa 5.00 policy=best enabled=true` | Card shows `2.50 / 5.00 / Best (Maximum bonus...) / Yes / mode` — exact persisted values | **PASS** — display only, no hardcode |

### F.3 Professional & Prior Polishes Preservation

| Block | Before | After | Verdict |
|-------|--------|-------|---------|
| `Training 285-304` 6 links `isProfessional` | 6 | **Unchanged 6** — P4 touches only grading preview | **PASS** |
| `Assessments vs Marks ?view=marks 242-247` P1 | Preserved | Unchanged | PASS |
| `Academic Settings vs Groups ?section=groups#groups 218/227` P2 | Preserved | Unchanged | PASS |
| `Academic Years vs Placements ?section=academic-years#academic-years 221/239` P3 | Preserved | Unchanged | PASS |

### F.4 Automated Suites (expected)

| Suite | Prior | Expected after P4 |
|-------|-------|-------------------|
| `BusinessProfileTest 16/16` | PASS | PASS unchanged — grading preview not coupled |
| `TenantIsolationAuditTest 4/4` | PASS | PASS unchanged |
| Pre-existing `SubjectUnification 302`, `TeacherManagement 734` | Pre-existing | Unchanged — document, not P4 regression — P4 is controller pass-through + read-only display |

**New failures: 0** — P4 adds read path only.

---

## G. MIGRATION / DATA SAFETY

| Field | Value | Evidence |
|-------|-------|----------|
| `DATA MODIFIED` | **NO** | No `INSERT/UPDATE/DELETE` — preview remains `GET` read-only `gradeGpaSlice` + `resolveScaleForClass` |
| `DATA DELETED` | **NO** | — |
| `MIGRATIONS` | **NO** | 0 new — `grade_scales` columns already `2026_08_27/28` |
| `NEW TABLES` | **NO** | None |
| `NEW DATA` | **NO** | No seed — empty state shows warning, not fake `GradeScale` |
| `NEW ROUTES` | **NO** | `route:list 1211` same — reuse `grading.preview:1164` |
| `NEW SYSTEMS` | **NO** | No duplicate GradeScale system — reuse `GradeScale` + `GradeScaleRow` |
| `NEW BUSINESS RULES IN BLADE` | **NO** | Blade reads `{{ $bonusScale->optional_subject_bonus_threshold }}` etc. + footer formula is documentation of existing `AcademicFinalResultService:244` not new logic; no `if` decides bonus applicability beyond display |
| Historical snapshot | **PASS** | Preview not persisted; `AcademicFinalResultRow` snapshot unchanged |
| Tenant isolation | **PASS** | `where institute_id` ladder + `TenantScoped` `AcademicResultAggregationScheme` |

---

## H. FINAL VERDICT

| Dimension | PASS/FAIL | Note |
|-----------|-----------|------|
| Threshold display | **PASS** | `{{ number_format($bonusScale->optional_subject_bonus_threshold ?? 2.00, 2) }}` from persisted `grade_scales` — e.g., `2.00` |
| Max GPA display | **PASS** | `{{ number_format($bonusScale->max_gpa ?? 5.00, 2) }}` — e.g., `5.00` |
| Multiple optional policy display | **PASS** | `{{ ucfirst($bonusScale->multiple_optional_policy ?? 'single') }}` + description `single: Only first / best: Maximum / sum: Sum` — persisted `enum single/best/sum` |
| GPA calculation unchanged | **PASS** | `AcademicFinalResultService:220-336` untouched — Blade only displays, no `preciseRound` reimplemented |
| Empty/configuration state | **PASS** | When `effectiveScale===null` → warning card "No Grade Scale — Configure a Grade Scale — no values invented" — no fake `2.00` injected |
| No fake data / no migration | **PASS** | 0 migrations, 0 seed, 0 invented scale |
| No hardcoded Blade rules | **PASS** | Blade reads columns; formula footer is documentation, not logic branch |
| Tenant / RBAC / Historical | **PASS** | `requireInstitute` + `TenantScoped` + `permission:education.manage+domain:academic:1144` + snapshot untouched |
| Responsive | **PASS** | Bootstrap `admin-card` grid `col-md-*` matches existing preview layout |

```
PHASE: B13-P4
DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
NEW TABLES: NO
NEW DATA: NO
ROUTES ADDED: NO (1211 routes — reuse grading.preview)
ROUTES MODIFIED: NO
VIEWS ADDED: NO
VIEWS MODIFIED: 1 (resources/views/institute/academic-grading/preview.blade.php:31 bonus card)
CONTROLLERS MODIFIED: 1 (app/Http/Controllers/AcademicGradingController.php:193 effectiveScale resolve)
SERVICES MODIFIED: NO
MODELS MODIFIED: NO

GPA_CALCULATION: PASS — AcademicFinalResultService 220 threshold 2.00 / max 5.00 / single/best/sum unchanged
HISTORICAL_SNAPSHOT: PASS — preview read-only, no snapshot mutation
TENANT_ISOLATION: PASS — GradeScale ladder institute_id + TenantScoped scheme + resolveScaleForClass
RBAC: PASS — permission:education.manage+domain:academic
CONCURRENCY: PASS — idempotent GET preview
IDEMPOTENCY: PASS — same

OPTIONAL_BONUS_VISIBILITY: PASS — threshold/max/policy/bonus-enabled/GPA mode from persisted grade_scales visible in preview; empty → configuration warning
BONUS_THRESHOLD: PASS — {{threshold}} displayed
MAX_GPA: PASS — {{max_gpa}} displayed
MULTIPLE_POLICY: PASS — single/best/sum displayed with description
NO_HARDCODED_RULES: PASS — Blade reads columns, footer formula is docs not logic
NO_FAKE_DATA: PASS — 0 invented scales
NO_MIGRATION: PASS — columns already 2026-08-27/28
REGRESSIONS: 0 NEW
RESPONSIVE: PASS
MULTI_BUSINESS: PASS (via TenantContext)

FINAL_VERDICT: GREEN
```

**GREEN — Grading preview now shows the applicable optional-subject bonus configuration (`threshold 2.00 / max GPA 5.00 / single|best|sum / bonus enabled / GPA mode`) directly from the persisted `grade_scales` row that `AcademicFinalResultService` uses, with an appropriate empty/configuration-state banner when no scale is resolved — no business rule, calculation, migration, or fake data added.**

---

> STOP — B13-P4 complete. Do not start next phase automatically per spec §21. Next: **B14 Full Academic workflow verification** or **Production readiness audit** — preview visibility now enables manual `aggregation scheme → preview` audit of `optional bonus + cap + policy` without decoding code.

*Evidence: `AcademicGradingController:193 effectiveScale resolveScaleForClass` + `preview.blade.php:bonusScale` card threshold `?? 2.00` max `?? 5.00` policy `?? single` bonusEnabled + empty `border-warning No Grade Scale` + `view:clear INFO` + `route:list 13 grading / 1211` + `AcademicFinalResultService:220 threshold / 221 enabled / 222 max / 225 policy / 244 max(gp-threshold,0) / 336 cap`.*
