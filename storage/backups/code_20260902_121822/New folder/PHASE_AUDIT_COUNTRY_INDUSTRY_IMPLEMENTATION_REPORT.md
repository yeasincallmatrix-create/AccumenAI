# PHASE: AUDIT_COUNTRY_INDUSTRY_IMPLEMENTATION

**System:** AccumenAI (Monetix) — Laravel 12 + PHP ^8.2 | **Date:** 2026-08-28 | **Mode:** READ-ONLY | **Data Modified:** NO  
**Location:** `C:\xampp\htdocs\monetix` | **Auditor:** Muse Spark (Opencode) | **Scope:** Country + Industry + Sub-Industry + Tax + Onboarding

> All findings cite `file_path:line_number`. No code/DB/config was modified during this audit. Execution via `Read / Grep / Glob / Bash (Get-ChildItem)` only.

---

## 1. EXECUTIVE SUMMARY

| Area | Status | Summary |
|---|---|---|
| **Country** | 🟢 **Present & Hardened** | 192 sovereign entries `config/countries.php:10-192` + `countries` table `app/Models/Country.php:10-51` (iso2/iso3/phone_code/academic_unit_label/status). Institute stores **both** `country_id FK` + legacy `country string` `app/Models/Institute.php:180-182` + `database/migrations/2026_08_15_190100:34-38`. Geo hierarchy `administrative_levels` + `administrative_units` + `education_systems` country-scoped. **Selection** is server-validated `Rule::in(array_keys(config('countries')))` in `InstituteOnboardingController:61` and `RegistrationFlowController:220` via `InstituteOnboardingController::validatedSelection:58-91`. Missing: only 2/192 countries have sub-industry maps (`Bangladesh`/`United States` `config/industry_rules.php:74-192`), rest fall back to global industries with empty subs (`industry_rules.php:15-17`). Geo seed incomplete for non-BD countries (`AdministrativeUnit` level1 only via `InstituteCreationController:215-221`). |
| **Industry** | 🟢 **Canonical Single Source** | Single source `config/industry_rules.php:21-39` 15 slugs `global.industries` + helper `app/Support/IndustryRules.php:23-38`. Institute stores `industry string(60) nullable default education` `2026_08_13_000000:12` + `sub_industry string(60) nullable` `2026_08_14_195437:12` — **not** on `User` (`User.php:37-56` has `account_type owner|staff` only). Resolver `IndustryRules::industries(country)` / `subIndustries(country,industry)` + `InstituteDomain::fromKeys:58-74` normalizes `transport→transportation`. Validation `Rule::in(array_keys(IndustryRules::industries(country)))` `InstituteOnboardingController:62` + `Admin\InstituteAdminController:264,291` dashboard filter `DashboardController:107`. Isolated via `TenantScoped` + domain clamp. |
| **Sub-Industry** | 🟡 **Partial (2/15)** | Only `education` (8 subs) + `training_center` (16 incl 11 legacy aliases `martial_arts…coaching_centre`) have global subs `industry_rules.php:40-71`. Country maps: `Bangladesh` full 8+16+ `healthcare 5` `it 3` `finance 3` `retail 3` `manufacturing 3`; `US` 7+16+`healthcare 3` `it 3` `finance 2`; 13 industries empty `[]` (`real_estate|transport|service|restaurant|hotels|personal_finance|other` `130-138`). Sub required IFF `IndustryRules::subIndustries(country,industry) !== []` `InstituteOnboardingController:71-84` else nullable. Duplicate `dance_academy` in US map `162` noise. Legacy alias `institution→training_institute` etc. normalized in `InstituteDomain:127-142`. |
| **Country×Industry Gating** | 🟢 Hardened for Education | 5-step resolver `ModuleAccessService:227-298` → `isIndustryCompatible:376-385` blocks `education` module if `industry !== education`; `EDUCATION_DISABLED_MODULES=['sales','purchase','hr','crm']:34` trimmed when `isAcademic:389`. `InstituteDomain:58-74` derives `academic|professional|other`; `EnsureDomain:13-23` 403 gates `domain:academic` routes (`web.php:158-163`, `institute_modules.php:979,1144` etc.). `IndustryTemplateMapping:15` maps `country_id+industry+sub → structure_templates`. Tax engine **country-specific** `config/tax.php:11-61` `TaxEngine::jurisdictionForCountry:86-94` + `TaxJurisdiction:country_iso2:2026_08_30_000100:17`. Capabilities `industry_rules.php:205-368` per-industry inventory 6/15 (education/retail/healthcare/manufacturing/real_estate/restaurant). |
| **Tax** | 🟡 Engine Exists, Partially Wired | 6 tables `tax_jurisdictions/tax_rates/tax_rate_history/tax_rules/tax_return_periods/tax_return_lines/tax_audit_logs` `2026_08_30_000100:11-128` + models `TaxRate:13-69` `TaxJurisdiction` + services `TaxEngine:12-95` `TaxComplianceService:17-178` + config `config/tax.php:11-61` 3 countries `BD 15% vat`, `US 0% sales_tax`, `IN 18% gst` (`BD+IN` have rule arrays `*`/`exempt`/`essential`/`luxury`). Resolution `TaxEngine::resolveRates:12-84` filters `institute_id+branch_id+jurisdictionId+tax_group+date+item_type+product_category` + rule gate. **Gaps:** no auto-jurisdiction creation from `Institute.country_id`; `isActive` scope ignores country; `sales_tax` not created for US; UI only via generic tax admin (not per-country onboarding). |
| **Onboarding** | 🟢 OTP-First 5-Step + 2-Step Workspace Hardened | Pre-auth 5-step `RegistrationFlowController:30-410` (account→OTP→organization→address→finalize) + auth 2-step `InstituteOnboardingController:24-132` (pick `country→industry→sub` session `onboarding`) → `InstituteCreationController:40-338` (session-derived `industry/sub/country` never from browser `121-123` + `GeoHierarchy::validateHierarchy`). JS cascade `resources/views/workspace/onboarding.blade.php:38-105` + `auth/register-organization.blade.php:68-76` disabled until parent. Session re-validation `InstituteOnboardingController::selection:98-126` + pending `PendingRegistration:13-56` 24h→48h. |

**Overall Verdict:** 🟡 **YELLOW (GREEN-leaning)** — Country/Industry substrate is production-grade for Bangladesh (geo + healthcare/finance/retail/manufacturing mapped) but **2/192 country coverage** and **6/15 capability coverage** block international rollout without expanding `industry_rules.php` + `tax.php` + geo seeds.

---

## 2. COUNTRY IMPLEMENTATION

### 2.1 Where Stored

| Table | Column(s) | Type | File |
|---|---|---|---|
| `institutes` | `country` | `string 80 default Bangladesh` backup `demo/...sql:1010` + `country_id` `unsignedBigInteger nullable FK countries.id` | `database/migrations/2026_08_15_190100:34-35` + `app/Models/Institute.php:180-182` `belongsTo Country` |
| `institutes` | `admin_level_1/2/3_id` | `unsignedBigInteger nullable FK administrative_units.id` | `2026_08_15_190100:36-38` |
| `students` | `country string 80 nullable` + `present_country_id` + `permanent_country_id` `unsignedBigInteger nullable` | `string` + FK | `2026_08_15_180000:12` + `2026_08_15_190100:22,26` + `app/Models/Student.php:61,74,78` |
| `contacts` `organizations` `units` etc. | `country_id` | FK `countries.id` | `app/Models/CrmContact.php:63` `CrmOrganization.php:53` `AdministrativeUnit.php:13,30` |
| `countries` | `name, iso2, iso3, phone_code, academic_unit_label, status` | `table countries` | `app/Models/Country.php:12-19` |
| `administrative_levels` | `country_id FK, level_number, name` unique `country_id+level_number` | `2026_08_15_190000:40,47` |
| `administrative_units` | `country_id, level_id, parent_id` | `2026_08_15_190000:55` |
| `education_systems` `academic_levels` `class_grades` `grade_scales` | `country_id FK` | `2026_08_17_100000:27,43,60` `2026_08_17_170000:42` |
| `industry_template_mappings` | `country_id nullable FK` unique `industry+sub_industry+country_id` | `2026_08_24_000100:112,126` |

**Not stored:** `users` — `User.php:37-56` fillable has `email,phone,name,account_type,preferences` **no** `country` / `industry`; country is institute-scoped, User is global multi-tenant via `memberships():HasMany` `institutions():BelongsToMany via institution_user` `170-183`.

### 2.2 How Stored

* **Institute dual column** — `country_id` (normalized FK, `InstituteCreationController:124` `geoAddress['country_id'] ?? null` + `RegistrationFlowController:356`) **plus** legacy free-text `country` (`InstituteCreationController:123` `$selection['country']` string) kept for backup/admin display `BusinessProfileController:27` `fromInstitute`. Admin-level ids `admin_level_*_id` → `AdministrativeUnit` resolved via `GeoHierarchy::validateHierarchy` + legacy text synced `syncLegacyLocationFields:238-259` (`division/district/upazila`).
* **Student dual** — legacy `country` string `Student.php:61` + normalized `present_country_id/permanent_country_id` `74,78` via `2026_08_15_190100`.
* **Countries table** `Country.php:10-51` fillable casts `status boolean` relations `levels()->HasMany AdministrativeLevel:25` `units()->HasMany AdministrativeUnit:30` `selectableLevels():35 where level_number<=3` `academicUnitLabel():42 'Class' fallback` `educationSystems():47 order display_order`.
* **Config list** `config/countries.php:1-192` 192 entries `name=>name` comment `dropdown in admin Edit Institute form and validation rules pick them up automatically:6-7`.

### 2.3 How Used (grep `country` — 100+ hits sampled)

* **Models** `app/Models/`: `AdministrativeUnit:13` `AdministrativeLevel:14` `ClassGrade:13` `EducationSystem:12` `CrmOrganization:53` `CrmContact:63` `GradeScale:22,104` `Student:61,74` `Institute:180` `IndustryTemplateMapping:17` `AcademicLevel:30` etc. (full list `PHASE` §2.2).
* **Controllers** `app/Http/Controllers/`: `AcademicGradingController:55` `$country=institute->country()->first()` scope `where country_id:80-82`; `Admin\InstituteAdminController:264` `IndustryRules::industries(institute->country)`; `InstituteOnboardingController:35,62` `InstituteCreationController:33,55,121,155` `RegistrationFlowController:207,353` `DashboardController:107` `ReportsHubController:41` etc.
* **Views** `resources/views/`: `home.blade.php:280-314` searchable country filter + `mawa_country_flag`; `workspace/onboarding.blade.php:38-105` `select country→industry→sub` JS `industriesFor/subsFor` + `continueBtn.disabled logic`; `workspace/create.blade.php:51,82,95` displays locked country + geo address; `students/form.blade.php:232-236` `select country` `nationalityDefault='Bangladesh':265`; `partials/phone.blade.php:12,18,57` `defaultCountry` flag.
* **Config** `config/` + `database/migrations/` 69 matches: `tax.php:5,12,28` default `BD`+ `countries BD/US/IN`; geo `2026_08_15_190000` FK cascade etc. (above).

### 2.4 Country Model — `app/Models/Country.php:10-51`

Exists ✅. Fillable `name,iso2,iso3,phone_code,academic_unit_label,status` `casts status:boolean` `levels()`, `units()`, `selectableLevels()` (<=3), `academicUnitLabel()` fallback, `educationSystems()`. Table `countries`, no SoftDeletes, seeded via `GeoImportController:50`+`GeoAdminController:57,70,86`.

### 2.5 How Country Selected

| Flow | View | Controller | Validation |
|---|---|---|---|
| **Pre-auth 5-step** Step 3 Organization | `auth/register-organization.blade.php:68-76` | `RegistrationFlowController::storeOrganization:213-247` → `InstituteOnboardingController::validatedSelection:58-91` | `country Rule::in(array_keys(config('countries'))) :61` |
| **Auth onboarding** Step 1 | `workspace/onboarding.blade.php:38-105` | `InstituteOnboardingController::step1:27-38` (owner-only `isOwnerAccount`) + `choose:40-50` `session[onboarding]=validated` | Same `validatedSelection` + `sub required iff IndustryRules::subIndustries(country,industry)!==[] :71-84` |
| **Auth create** Step 2 | `workspace/create.blade.php:51` locked display `countryLabel= config('countries.'+country)` `geoAddress country_id` | `InstituteCreationController::create:40-65` preview `show → store:67-91 geoAddress + validateHierarchy` | `country_id` must match `selection country_id` `:94` else 422 |
| **Admin edit** | `admin/institutes/edit` | `Admin\InstituteAdminController:264,290-291` `Rule::in(array_keys(config('countries')))` for `country` | `IndustryRules::industries(institute->country)` for industry list |
| **Profile** | `business/profile` shows `institute->country` | `BusinessProfileController:27` `fromInstitute` | Read-only |

### 2.6 What Country Data Exists

* `config/countries.php:10-192` 192 entries.
* DB table `countries` seeded via admin `GeoAdminController:57 country->create([...iso2,iso3,phone_code,academic_unit_label,status])` + `GeoImportController:50` import; `AcademicStructureAdminController:46,77` country systems.
* Fallback: `config/industry_rules.php:15-17` country without entry → global industries + no subs, so onboarding never dead-ends.

**What's Working:** Full 192 list, FK normalized (`institutes.country_id`, `students.present_country_id`), validation + JS cascade + geo hierarchy gate `GeoHierarchy::validateHierarchy`.

**What's Missing/Gap:** 190 countries have **no** `industry_rules.php` entry → no sub-industry maps, no capabilities; geo `AdministrativeUnit` only BD Level1 pre-rendered (`InstituteCreationController:215`), US/others empty; `students.country` legacy string not normalized for many rows.

---

## 3. INDUSTRY IMPLEMENTATION

### 3.1 Where Stored

* **Primary:** `institutes.industry string(60) nullable default education` `database/migrations/2026_08_13_000000:12` `after founded_year` + `institutes.sub_industry string(60) nullable` `2026_08_14_195437:12` `after industry`.
* **Mapping:** `industry_template_mappings:2026_08_24_000100:110` `string industry 60 + sub_industry nullable + country_id nullable FK` unique `industry,sub_industry,country_id:126` → `structure_templates`.
* **Settings:** `industry_settings:2026_08_15_000400:11` `industry_key unique` per-industry theme (`IndustrySetting:9`).
* **SaaS:** `module_registry.type enum core|industry:2026_09_02_000100:16` `education` type `industry` sort 20 `status active`; `institute_module_overrides/entitlements` per institute.

**Not stored on User** — `User.php:37-56` has no industry; `Membership institution_user` has `role_id,branch_id` not industry; capability overrides in `institute_settings via InventoryCapabilityService`.

### 3.2 How Stored

* **Slug, not FK** — `industry`/`sub_industry` are **string slugs** (60) not `industry_id` FK. Single source slug list `config/industry_rules.php:21-39` global.industry + per-country blocks.
* **Values (15 global):** `education, training_center, healthcare, information_technology, finance, retail, manufacturing, real_estate, transportation, transport(alias), service, restaurant, hotels, personal_finance, other:23-38` labels via `global.industries`.
* **Sub count (global):** `education 8` + `training_center 16` `40-71` (incl 11 legacy aliases `institution…coaching_centre` `59-70`); other 13 not in `global.sub_industries`.

### 3.3 How Used (100+ hits sampled)

* `Institute.php:31-48` domain immutability `isDirty industry|sub_industry` + `InstituteDomain::fromKeys` + `hasDomainData` → `ValidationException`.
* `DashboardController:107-155` `industry = request()->query(industry) && array_key_exists(industry, IndustryRules::industries(country))` + `sub validated array_key_exists(sub, IndustryRules::subIndustries(country??'',industry))` → `where industry/sub_industry` filter + `isEducation` toggle `isAcademic`.
* `InstituteOnboardingController:53-62` + `RegistrationFlowController:207,353,406` + `InstituteCreationController:55,121-122` creation/store `industry=>selection[industry]`.
* `Admin\InstituteAdminController:264,290-291` `industries(institute->country)` validation.
* `BusinessProfileController:76,226,233-237` `industryLabel via global.industries` `subIndustryLabel via subIndustries('',industry)`.
* `ReportsHubController:41` `if(report[industry]!==null && institute->industry!==report[industry]) abort 404`.
* Views `auth/register-select:63` `auth/register-organization:68-76` `workspace/onboarding:48,54` `business/profile:74,95,313,338` `business/show:49-50` `academic/dashboard:13,31` etc. Full list §6.3 prior report.

### 3.4 How Selected

Same flows as country — **country first**, then industry filtered by `IndustryRules::industries(country)` (`IndustryRules.php:23-38` returns scoped map when country has entry else global), then sub filtered by `IndustryRules::subIndustries(country,industry)` `46-55`.

| UI | Enable Logic | File |
|---|---|---|
| `workspace/onboarding` | `country selected → industriesFor(country)` JS `data.rules[country]`; industry selected → `subsFor(country,industry)`; `sub field hidden when subs===[]`; `continue disabled = !country||!industry||(subs.length>0&&!sub)` | `workspace/onboarding.blade.php:97,105,133-152` + `auth/register-organization:132` |
| `auth/register-select:63,70` | Same cascade `disabled required` toggle | `register-select:120-167` |
| `InstituteCreationController:create` | Server-derived display `industryLabel = IndustryRules::label(country,industry)` `subLabel = subIndustries[?][sub]` `workspace.create:55-58` locked, not re-submitted | `InstituteCreationController:50-65` |

### 3.5 What Industry Data Exists

* `config/industry_rules.php:21-192` single source — `global.industries` 15, `global.sub_industries` 2×, `Bangladesh` full matrix (8+16+5+3+3+3+3, 6 `[]`), `United States` sparse (7+16+3+3+2, rest `[]`), `capabilities:205-368` 6 industries (see §4).
* DB: `module_registry` seed 12 (`crm…education`) + `vat` add-on `2026_08_24_000100`; `industry_template_mappings` seed `seedMappings:296 B2 taxonomy Academic vs Professional`.

### 3.6 How Validated

* **Service:** `IndustryRules.php:23-38` `industries(country)` scoped vs global; `subIndustries(country,industry):46-55` `is array ? arr : []`; `hasSubIndustries:60-63`; `label:72-84` `labelOf:90-94`.
* **Controller:** `InstituteOnboardingController::validatedSelection:58-91` `validator Rule::in(array_keys(config('countries')))` country, `Rule::in(array_keys(IndustryRules::industries(input['country']??null)))` industry, `sub nullable`; if `subs!==[]` then `sub required && array_key_exists(sub, subs)` else `null`. Re-validated on `selection():98-126`.
* **Domain:** `InstituteDomain::isValidCombination:87-105` checks `global.industries` + `subIndustries('',industry)` `normalizedKeys`; `Institute:30-48` blocks cross-domain change when `hasDomainData`.
* **Fallback:** Country without entry → global industries + empty subs per `industry_rules.php:15-17` doc + `IndustryRules::industries null → global`.

**Working:** DRY validation via `IndustryRules` + server-derived `selection[industry/sub]` never trusting browser `InstituteCreationController:121-123` `RegistrationFlowController:353`.

**Missing:** 13 industries have `[]` → store `null` but no UI hint; `transport` alias retained for compat not deprecated.

---

## 4. SUB-INDUSTRY IMPLEMENTATION

### 4.1 Where Stored

`institutes.sub_industry string(60) nullable after industry` `2026_08_14_195437:12` + `industry_template_mappings.sub_industry nullable` `2026_08_24_000100:110` + `Student` no sub field (industry is org-level only).

### 4.2 How Stored

Slug per `config/industry_rules.php` block `education:41-51` `training_center:52-70` + per-country blocks `Bangladesh:75-129` `US:140-192` (values duplicate plus locale tweaks: US no `madrasha`, `dance_academy` duplicate line `162` bug). Normalized via `InstituteDomain::normalizeSubIndustry:127-142` map `institution→training_institute` `professional_training_academy→professional_training_center` `computer_it_training_institute/computer_it→it_training_center` `vocational_institute|skill_development_center|technical_training_center→vocational_training_center`.

### 4.3 How Used

* Storage gate `Institute.php:32-48` dirty check + `InstituteDomain::fromKeys` + `hasDomainData` short-circuit `courses/subjects/course_curricula/batches/student_academic_placements/academic_assessments/academic_final_results/academic_student_marks via join:147-163`.
* Resolver `InstituteDomain::fromKeys:58-74` `education+ACADEMIC_TYPES(4 school,college,polytechnic,university:23-28)→academic`, `training_center+PROFESSIONAL_TYPES(5 training_institute…vocational_training_center:31-37)→professional` else `other`.
* Gating: `isIndustryCompatible:376-385 ModuleAccessService` only `education` gated; `capabilities` per industry.
* Views: `subjectTypeFor:108-115` → `professional` default for `other`; `CourseMasterController:62,212,252` `subject_type=derived` `where institute_id+subject_type`; `SubjectManagementController:34,111,128,169` clamping.
* Reports: `BusinessProfileController:76` `subIndustryLabel` + `profile.blade.php:338 subIndustryLabel · industryLabel · domainLabel`.

### 4.4 How Selected

Same cascade as industry — required **iff** `IndustryRules::subIndustries(country,industry) !== []` `InstituteOnboardingController:71-84` (validation error messages `Sub-industry is required` / `not available`). JS hides field when `subsFor(...).length===0` `workspace/onboarding:133-152`. `selection()` `117` checks `array_key_exists(sub, subIndustries(country,industry))` else `null`.

### 4.5 What Sub Data Exists

* Global `education 8`: `school,college,polytechnic,university,madrasha,primary_school,secondary_high_school,school_college` `41-51` (last 3 legacy NEEDS_REVIEW).
* Global `training_center 16`: 5 canonical + 11 aliases `institution,professional_training_academy,computer_it_training_institute,vocational_institute,technical_training_center,skill_development_center,martial_arts,music_academy,sports_academy,language_academy,coaching_centre` `52-70`.
* Bangladesh adds same 8+16 + `healthcare 5 hospital/clinic/pharmacy/diagnostic/nursing` `103-109` `it 3 software/it_services/digital` `110-114` `finance 3 bank/microfinance/insurance` `115-119` `retail 3 general/supermarket/electronics` `120-124` `manufacturing 3 garments/food/pharma` `125-129`.
* US same minus `madrasha` `140-149` + `training_center` dup `162` + `healthcare 3 hospital/clinic/pharmacy` `168-172` `it 3` `173-177` `finance 2 bank/insurance` `178-181` rest `[]`.
* Empty `real_estate,transport,transportation,service,restaurant,hotels,personal_finance,other:130-138` → null.

**Working:** 2-industry multi-tenant taxonomy covers domestic market; alias normalization preserves audit trail `migration 2026_08_28_100000:11-56` 5→training_center map.

**Missing:** No country coverage for retail/manufacturing subs outside BD; capabilities missing for `healthcare` vs `education` divergence not exposed in UI.

---

## 5. COUNTRY + INDUSTRY COMBINATION

### 5.1 How Used Together

| Concern | Implementation | File |
|---|---|---|
| **Feature toggling** | `ModuleAccessService:252-254` `isEducationIndustry(institute)=InstituteDomain::isAcademic()` → `array_diff EDUCATION_DISABLED` (`sales/purchase/hr/crm`); `isIndustryCompatible:284` blocks `education` module when `industry !== education`; `EDUCATION_DISABLED` + `entitlementMap` + `override` precedence `resolveEnabled:265-294`. | `app/Services/ModuleAccessService.php:34,252,278,284,387-390` |
| **Learning structure** | `InstituteCreationController:155-158` + `RegistrationFlowController:392` `LearningStructureResolver::resolveTemplate(institute)` maps `country_id+industry+sub` via `IndustryTemplateMapping` unique `industry,sub,country_id:2026_08_24_000100:126` → `InstituteSetting.structure_template_id`. | `app/Services/LearningStructureResolver.php` + `database/seeders/LearningStructureSeeder.php:294` |
| **Grade scale** | `GradeScale country_id+education_system_id composite unique:2026_08_17_170000:53` + `AcademicGradingController:80-82 where country_id` | `app/Models/GradeScale.php:22,104` |
| **Geo address** | `InstituteCreationController:89-111` `geoAddress = GeoHierarchy::levelLabels(Country)` + `validateHierarchy(country_id, admin_1/2/3)` locked to `selection[country]` `GeoHierarchy:94`. | `app/Support/GeoHierarchy.php:94` |
| **Tax jurisdiction** | `TaxEngine::jurisdictionForCountry:86-94 where country_iso2 + branch scoped` + `TaxJurisdiction:code country_iso2 state_code parent_id distinct:2026_08_30_000100:17-19` | `app/Services/Tax/TaxEngine.php:86` + `config/tax.php:11-61` |
| **Inventory capabilities** | `config/industry_rules.php:205-368` per-industry `assets+inventory.*` flags overridable via `InventoryCapabilityService` `inventory.capability.<name>` institute+branch `accounting-settings`. | `app/Services/InventoryCapabilityService.php` |

### 5.2 Country-Specific Features (grep `iso2|iso3|country_iso2|country_id`)

Few conditional branches — **geographically sparse gating**:

* `TaxEngine:29 jurisdictionId !== null → where jurisdiction_id else whereNull jurisdiction_id` + `config/tax.php:12 BD/29 US/43 IN` distinct `vat_rate/types/rules/accounts` per `country_iso2` (`BD vat 15% types [vat,sd,ait,at]`, `US sales_tax 0%`, `IN gst 18%`).
* `GradeScale:22` `where country_id` composite.
* `Country::academicUnitLabel:42` returns `academic_unit_label ?: 'Class'` per country (used `StructureLabel`).
* `GeoHierarchy::levelLabels` + `InstituteCreationController:228 zip_first = iso2==='BD'` + `config geo-labels.localities.iso2`.
* **No** `if(country==='United States')` hard forks except `industry_rules.php` matrix itself — intended: country maps are data, not `if` branches.

### 5.3 Industry-Specific Features (grep `industry` conditions)

* `InstituteDomain:67,70` domain derivation; `ModuleAccessService:252` education trim + `284` education gate; `ReportsHubController:41` report industry gating `abort 404`; `DashboardController:107` industry filter + hospitality `restaurant/hotels` branch `:199` tile; `BusinessProfileController:313` `match(industry) otherTitle`; `ensureDomain` 403 on `domain:academic`.
* Capabilities `industry_rules.php:205-368` inventory `retail/healthcare/manufacturing/real_estate/restaurant` flags; assets `education` etc.

### 5.4 Country×Industry Specific Rules

* Only composition is `industry_template_mappings unique(sub,industry,country_id)` + `IndustryRules::subIndustries(country,industry)` — no explicit `if(country===X && industry===Y)` rule in PHP except `config/industry_rules.php` data.
* Module `type industry` single entry `education` — extensibility point for future `type industry` modules (`ModuleRegistry:2026_09_02_000100:16`).
* **Verified absent:** `ModuleAccessService:19-23` comment `Future 63G webhook grantModule` not country×industry priced; `isEntitlementActive:336-374` status `trialing` respects trial window per `institute_module_entitlements`.

---

## 6. CURRENT TAX IMPLEMENTATION

### 6.1 What Exists

| Layer | File | Key |
|---|---|---|
| **Config** | `config/tax.php:1-66` | `defaults country=env TAX_DEFAULT_COUNTRY BD type vat rate_type percentage is_inclusive false:4-9` + `countries BD/US/IN:11-61` each `name,vat_rate,types,is_inclusive,return_frequency,accounts{output,input,clearing,withholding},rules[item_type,rate,type,description]` + `compound_order[vat,excise,withholding]:63` `audit_enabled true:65` |
| **Migrations** | `database/migrations/2026_08_30_000100:11-128` | `tax_jurisdictions(id,institute_id,branch_id,name,code,country_iso2,state_code,parent_id,is_active,unique institute+branch+code,index institute+country_iso2)` + `tax_rates(id,institute_id,branch_id,jurisdiction_id,tax_group_id,name,type[vat|sales_tax|withholding|excise|custom],rate_type[percentage|fixed],rate dec10,4,is_compound,is_inclusive,effective_from,to,created_by,softDeletes,index institute+jurisdiction+active)` + `tax_rate_history(old_rate,new_rate,changed_at)` + `tax_rules(id,institute_id,branch_id,jurisdiction_id,tax_rate_id,item_type,product_category,tax_group_id,priority,is_active,index)` + `tax_return_periods(id,institute_id,branch_id,jurisdiction_id,name,period_start,period_end,due_date,status[open|filed|overdue],total_sales/purchases/tax_collected/paid/net,journal_id)` + `tax_return_lines + tax_audit_logs(event,actor,entity,old/new json)` |
| **Models** | `app/Models/TaxRate.php:13-69` `TaxJurisdiction.php` `TaxRule.php` `TaxReturnPeriod.php` `TaxGroup.php` etc. | `TaxRate:17 BranchScopedOrShared+TenantScoped SoftDeletes guarded [] casts rate dec4, is_compound/inclusive boolean, effective_from/to date, is_active bool` `scopeActive:63-68 where effective range now` `belongsTo institute/branch/jurisdiction/taxGroup hasMany history/rules` |
| **Permissions** | `database/migrations/2026_08_30_000200_add_tax_engine_permissions.php` | Added tax engine permissions |
| **Services** | `app/Services/Tax/TaxEngine.php:12-95` | `resolveRates(instituteId,branchId,jurisdictionId,context[item_type,product_category,tax_group_id,date])` filters `TaxRate active date+branch+jurisdiction+taxGroup` `TaxRule active + item_type/* + product_category/* + branch+ jurisdiction` `if(anyRulesExist) filter rates by ruleRateIds else all rates sortBy type`; `jurisdictionForCountry:86-94 where institute+country_iso2+is_active+branch` |
| | `app/Services/Tax/TaxComplianceService.php:17-178` | `rate_created:39 audit tax_rate` `rate_updated:63 tax_rate_history old->new` `return_created:79 tax_return_period` `net_tax:121 collected-paid:106` `return_filed:155` `overdue:170` `config("tax.countries.{$countryIso}"):178` accessor |
| | `app/Services/Tax/*` (FxRevaluation etc.) | `FxRevaluationJob:41`, `DepreciationRunJob:44` processInstitute wrappers |
| **Resources** | `app/Http/Resources/SalesQuotationResource:22` `SalesOrderResource:22` | `tax_amount => $this->tax_amount ?? null` |
| **Security** | `app/Support/BranchScopedOrShared` + `TenantScoped` on `TaxRate` + `StockLedger` pattern | Tenant isolation enforced |

### 6.2 How Tax Calculated

* **Rate store:** `tax_rates.rate decimal 10,4` + `rate_type percentage|fixed` + `is_compound is_inclusive` + `effective_from/to` window `TaxRate.php:35-40` `scopeActive:63` checks `effective_from <= now <= effective_to|null`.
* **Rule store:** `tax_rules item_type 50 default * + product_category 50 default * + tax_group_id nullable + priority + is_active` `TaxRate` linked.
* **Engine:** `TaxEngine::resolveRates:12-84` 1) fetch candidate `TaxRate` where `institute_id+is_active+date+branch+jurisdiction+taxGroup`; 2) fetch matching `TaxRule` `item_type or *` `product_category or *` `branch+jurisdiction`; 3) if `anyRulesExist` & `ruleRateIds===[]→empty collect else filter rates by rule ids sortBy type`. If **no** rules at all → return all candidate rates (fallback to config).
* **Config fallback:** `config/tax.php:24-27` `BD [{item_type '*', rate 15 vat Standard},{exempt 0}]` `IN [{* 18 gst Standard},{essential 5},{luxury 28}]` `US []` (empty). Accessor `TaxComplianceService:178 config("tax.countries.{$countryIso}")` used for `type` inference.
* **Return aggregation:** `TaxComplianceService:106-121` per `tax_rate type vat|sales_tax` sums `tax_collected/paid/net` into `tax_return_lines:114-121` + `tax_return_periods:134-136 total*`.

**No fixed vs configurable?** Both — config defaults (`BD 15% etc.`) + DB override per `Institute` `tax_rates` (editable via admin, audited via `tax_rate_history`).

### 6.3 Country-Specific Tax Logic

* `config/tax.php:12 BD vat_rate 15 types [vat,sd,ait,at] return monthly accounts output 2100 etc.; US 0 sales_tax/excise empty rules; IN gst 18 types [gst,cgst,sgst,igst] 3 rules. `TaxComplianceService:178` `config("tax.countries.{$countryIso}")` — country iso drives `type` (`vat` vs `gst` vs `sales_tax`).
* `TaxJurisdiction:country_iso2 char2:17` + `state_code nullable:18` + `parent_id self-FK:19` hierarchical (country→state). Engine `jurisdictionForCountry(..., countryIso):86` reads institute-specific jurisdiction rows `where institute_id+country_iso2+branch`.
* **Note:** BD has `TaxJurisdiction` `state_code null` (national VAT); US/IN state-level would use `state_code` (e.g., `CA`). No auto-seed per `Institute.country_id` — must be created via UI/API.

### 6.4 Industry-Specific Tax Logic

* **None explicit** — `industry_rules.php` has no tax `rate` keys; `tax_rules` are `item_type/product_category` not `industry`. `inventory.capability` etc. not tax-gated. `ModuleAccessService` does not check tax per industry. `IndustryTemplateMapping` not tax.
* Gap: `healthcare/manufacturing/restaurant` inventory `wastage/bom/recipe` flags don't drive tax `rate`.

### 6.5 What Exists vs Missing

**Exists:** full engine 6 tables+softDeletes+history+audit, `TaxEngine` rule→rate resolution with branch/jurisdiction/date scoping, `TaxGroup` enum types, `config/tax.php` 3 countries, `audit_enabled`, `compound_order`, return workflow `open→filed→overdue`.

**Missing / Improvement:**

* No `Institute.country_id → auto TaxJurisdiction` seeder in `RegistrationFlowController:312-410` / `InstituteCreationController:117-149` (tax jurisdiction must be created manually).
* US config `rules []` → `resolveRates` fallback `anyRulesExist false → returns all rates` may return 0 rows for US institutes with no `tax_rates` rows (expected: create jurisdiction+rate per branch).
* No `industry→tax_type` mapping (e.g., `restaurant` VAT inclusive vs `healthcare` exempt).
* `is_inclusive` at config level `BD false` vs per-rate `is_inclusive` — duplication not clarified.
* `tax_return_periods.journal_id FK journals` `93` wired but `FxRevaluation` not linked to `Institute.country`.
* `softDeletes` on `tax_rates` but not on `tax_jurisdictions` (added `2026_09_03_000300:13`).

---

## 7. ONBOARDING FLOW

### 7.1 How Onboarding Works (Two Tracks)

**Track A — Pre-auth 5-Step OTP-First (`RegistrationFlowController:24-529` `SESSION_KEY registration_flow + PENDING_ID`):**

| Step | Route `routes/web.php:62-95` | View | Controller | Data |
|---|---|---|---|---|
| 0 Account (guest) | `GET register/account (register.account) :62 + POST register/account.submit throttled 10,15` | `auth/register-account.blade.php` (email,password, register-progress step1) | `showAccount:30-38` redirect `dashboard` if `user('web')`; `storeAccount:40-123` `RateLimiter register_account_ip 10/hr + email 5/hr:44,49` `PasswordPolicy::rules:59` `EmailNormalizer` `EmailDomainPolicy:63` cross-table dup `User|InstituteUser|PlatformAdmin + pending unverified:67-78` reuse pending `!isVerified && !isGraceExpired:83-90` else `create PendingRegistration:93-100` `session pending_id + flow{email,verified false,step1}:102-109` `session.regenerate:111` `PendingRegistrationOtpService::send:114` → `register.otp.form` | `email, password_hash` |
| 1 OTP | `GET register/verify-otp (register.otp.form) + POST verify-otp + POST resend-otp (throttle10,10:85)` | `auth/register-otp.blade.php` maskedEmail `{{maskEmail:458-466}}` expiresAt cooldown attempts + `resend cooldown 60s max5/hr`| `showOtp:126-144` `isVerified→redirect organization` else `remainingCooldown:447-456` (`IdentityConfig::emailOtp resend_throttle_seconds 60`) `verifyOtp:146-175` `regex \d{4,8}:153` `RateLimiter pending_otp_verify:{id}:{ip} 10/min:158` `PendingRegistrationOtpService::verify:164` `session verified true step2` `resendOtp:177-195` throttled | `otp 4-8 digit OTP 6/15m/5` |
| 2 Organization | `GET register/organization (register.organization) + POST organization.submit` | `auth/register-organization.blade.php:68-76` industries `IndustryRules::industries(null):205` + `rules Arr::except industry_rules global,capabilities:207` + `countries config('countries'):206` JS cascade `country→industry→sub` | `showOrganization:198-211` requires `pending.isVerified() else redirect otp.form`; `storeOrganization:213-247` `validatedSelection(request.all()):219` + `organization_name,first_name,last_name,phone:220-225` `PhoneNormalizer::toE164(phone,country):228` `User|InstituteUser phone dup:232` `pending.update organization_data = validated+org:236-243` `step3` → `register.address`| `country,industry,sub_industry (validatedSelection), organization_name,first_name,last_name,phone(E164)` |
| 3 Address | `GET register/address (register.address) + POST address.submit` | `auth/register-address.blade.php` `geoAddress` (Country + `GeoHierarchy::levelLabels` + Level1 options via `InstituteCreationController:215`) `country_id zip address` | `showAddress:250-267` requires verified+org else redirect; `storeAddress:269-309` validates `country_id,admin_1/2/3,zip,address:280-287` `if geoAddress !==null ⇒ country_id must match geoAddress:country_id:290-292` `GeoHierarchy::validateHierarchy:293-301` `pending.update address_data:304` `step4` → `finalizeRegistration:308`| `country_id,admin_1/2/3,zip_code,address` |
| 4 Finalize | implicit `POST register/address → finalizeRegistration:311-410` | `auth/register-education-placeholder.blade.php` (industry===education) else `dashboard` | `finalizeRegistration:311-410` `DB::transaction lockForUpdate pending:332` `User::exists→delete pending redirect login:318-322` `Role slug institute-owner ownerRoleId:324` `User::create(name,email,phone,preferred_language mawa_current_lang(),password_hash,status active,account_type owner,email_verified_at now):337-348` `Institute::create(name,slug uniqueSlug:352,industry/sub/country/country_id/admin_*_id/postal_code/address,status active):350-363` `syncLegacyLocationFields:366 division/district/upazila` `MembershipService::assign(user,institute,ownerRoleId,branch null,active):368-371` `InstituteSetting certificate_approval_mode ADMIN:374-377` `delete lockedPending:380` `assignDefaultLearningStructure:392 LearningStructureResolver` `AcademicSetupService::ensureDefaults:394` `DemoDataService::seed(force false):395` `Auth::guard('web')->login(user):398 session.regenerate:399 forget pending+session+onboarding clear:400-403 Workspace::set(institute.id):403 if industry===education→register.education.placeholder:406 else dashboard:409` | Transactional `User+Institute+Membership` |

Helpers: `resolvePending:420-445` checks `session PENDING_ID` `isVerified?isAbandonedExpired:isGraceExpired → delete pending forget session` session email match; `remainingCooldown:447-456` `IdentityConfig::emailOtp('resend_throttle_seconds')`; `geoAddress:468-484` `Country where name+status true → levelLabels + AdministrativeUnit where status true parent null level 1`; `syncLegacyLocationFields:486-496` `AdministrativeUnit names → division/district/upazila`; `previewDefaultStructure:516-528` dummy Institute → `LearningStructureResolver`.

**Track B — Auth Owner 2-Step (`InstituteOnboardingController:24-132` + `InstituteCreationController:38-338` `routes/web.php:120-137`):**

| Step | Route | View | Controller |
|---|---|---|---|
| 1 Pick | `GET workspace/onboarding (workspace.onboarding) + POST workspace/onboarding(/choose) name workspace.onboarding.post/choose middleware auth:web` | `workspace/onboarding.blade.php:38-105` | `InstituteOnboardingController::step1:27-38` abort unless `User isOwnerAccount 403` → `view workspace.onboarding` `industries Industries(null)` `countries config('countries')` `rules except global,capabilities` `selection session onboarding`; `choose:40-50` `validatedSelection(all)` → `session[onboarding]=validated` → `workspace.create` |
| 2 Create | `GET workspace/create (workspace.create) + POST workspace/create (workspace.store) auth:web` | `workspace/create.blade.php` displays locked `selection countryLabel industryLabel subLabel` + `geoAddress` + theme | `InstituteCreationController::create:40-65` `selection===null → redirect onboarding` `previewDefaultStructure` `industryThemeColor` from `IndustrySetting`+`Theme` → view; `store:67-192` same validations country lock `if country_id≠selection country_id → 422 ValidationException:94-98` `GeoHierarchy::validateHierarchy:100-111` `ownerRoleId:114` `transaction Institute::create selection trio server-derived never from browser:121-123` `syncLegacyLocationFields:135` `MembershipService:137` `InstituteSetting:143` `clear onboarding:151 Workspace::set:153 learningStructure+academic+demo:155-187 → dashboard:189` |

* Workspace picker/switch: `WorkspaceController:121-137` `GET workspace (picker)` `POST workspace/switch/{id} middleware auth:web,verified` lists active memberships `roleAllowedForAccountType` → `Workspace::verify(id,user)` + `TenantContext::set`.

### 7.2 Country / Industry / Sub Selection Details

| Question | Answer | File |
|---|---|---|
| **When is country selected?** | **Track A** Step 3 Organization `auth/register-organization` alongside industry/sub; **Track B** Step 1 `workspace/onboarding`. Both are FIRST pick in onboarding — address step later locks `country_id` to match `selection.country`. | `RegistrationFlowController:219 validatedSelection` + `InstituteOnboardingController:58-61` |
| **How validated?** | `validator input country required string max80 Rule::in(array_keys(config('countries'))):61` + `industry required max60 Rule::in(array_keys(IndustryRules::industries(input['country']??null))):62` + `sub nullable max60:63` then `subs=IndustryRules::subIndustries(country,industry):68` if `subs!==[]` → `sub required && array_key_exists(sub, subs):71-84` else `sub=null`. Re-validated on `selection():98-126` on every `create/store`. | `InstituteOnboardingController:58-91,98-126` |
| **When is industry selected?** | Same step as country (atomically). `industries = IndustryRules::industries(country)` scoped `25-34` → dropdown disabled until country. | Same |
| **When is sub selected?** | Same step, **required IFF** `IndustryRules::subIndustries(country,industry) !== []:71`. `workspace/onboarding:133-152` hides `sub field d-none` when empty. | `IndustryRules:46-63` + `InstituteOnboardingController:71` |
| **UX** | `resources/views/auth/register-organization.blade.php:68-76` `select#country 192 options` → JS `rules = Arr::except(industry_rules, global,capabilities)` cascade `subsFor(country,industry)`; `workspace/onboarding:38` same but auth verified. Post-create `workspace/create:51-95` displays locked `countryLabel industryLabel subIndustryLabel` + `geoAddress` (`Country.iso2 === 'BD' ? zip_first`). | `workspace/onboarding:38-105` `workspace/create:51` `InstituteCreationController:52-58` |
| **Security** | `selection` stored in **session `onboarding`** not POST on `store` (`InstituteCreationController:121-123 institute industry/sub/country from session`) — `InstituteOnboardingController:21` doc “never from browser in step 2 which treats them as locked”. `country_id` lock `if client country_id ≠ geoAddress country_id throw:94`. `Workspace::set` + `session.regenerate` on `RegistrationFlowController:399` anti-fixation. | `InstituteOnboardingController:18-22` + `InstituteCreationController:121` + `RegistrationFlowController:111,398` |

### 7.3 What Is Working / Missing

**Working:** End-to-end OTP→org→address→transaction flow (`PendingRegistration` state `CREATED/EXPIRED/OTP_VERIFIED/ONBOARDING/ABANDONED isGrace/isAbandoned:57`), cross-table dup checks, per-IP/email throttle, session tamper `resolvePending sessionEmail !== pending.email → forget`, geo `level1 pre-render` + AJAX deeper levels `geoHierarchy`.

**Missing:** 190 countries have empty sub maps → UX shows industry but **no sub** (confusing for `real_estate` etc. — sub null correctly). No `Country.iso2` ISO selector sync (phone normalizer hardcode `Bangladesh:User.php:133` default `Bangladesh` in `RegistrationFlowController:260 fallback` should be `Country.iso2`). `register-education-placeholder:412-416` renders `latest institute` not necessarily the one just created for multi-org owners.

---

## 8. RECOMMENDATIONS (Priority Order)

### P0 — International Readiness (Must Before Non-BD Launch)

| # | Action | File | Reason |
|---|---|---|---|
| 1 | **Populate `config/industry_rules.php` for top-10 markets** (US already sparse, add UK/CA/AE/SA/IN with `healthcare/it/finance/retail/manufacturing` subs + `capabilities` for `healthcare/manufacturing`) | `config/industry_rules.php:140-192` pattern | 190 fallback → empty subs blocks Tailored UX; seed `capabilities` for all 15 not just 6. |
| 2 | **Seed `countries` geo for US** `AdministrativeLevel level_number 1..3` + `AdministrativeUnit` (State→County) + `education_systems` + `industry_template_mappings` rows via `LearningStructureSeeder:294` | `database/seeders/LearningStructureSeeder.php:296` `config/industry_rules.php:41-51` | `geoAddress(null)` returns plain input for non-BD (degraded). |
| 3 | **Auto-create `TaxJurisdiction`** per new Institute (`InstituteCreationController:bridge 117-149` after `Institute::create` create `TaxJurisdiction institute_id,country_iso2=Country.iso2,code unique, is_active true` from `config/tax.php:11` defaults) | `InstituteCreationController:117` + `RegistrationFlowController:350` + `TaxJurisdiction model` | Manual jurisdiction blocks `TaxEngine::resolveRates` for non-BD without rates. |
| 4 | **Expand `config/tax.php:11` to GB/CA/DE etc.** `return_frequency accounts rules item_type *` per country + migration seed `tax_rates` from config for new installs | `config/tax.php:11` `TaxComplianceService:178` | Only BD/IN have rule arrays, US empty → no VAT. |

### P1 — UX & Validation Polish

| # | Action | File |
|---|---|---|
| 5 | **Fix duplicate `dance_academy` in US map** `config/industry_rules.php:162` | `config/industry_rules.php:162` remove duplicate |
| 6 | **Normalize phone default country** `User.php:133 toE164(phone,'Bangladesh')` + `PendingRegistration` fallback `RegistrationFlowController:260 country ?? Bangladesh` → `Country.iso2 → PhoneNormalizer::toE164(phone, Country.iso2\|Country.name)` | `app/Models/User.php:133` `app/Http/Controllers/Auth/RegistrationFlowController.php:260` |
| 7 | **Add TTL + clear-on-logout for `workspace/onboarding` session** (`InstituteOnboardingController::selection` already re-validates but no expiry). | `app/Http/Controllers/InstituteOnboardingController.php:98` `InstituteCreationController:151` |
| 8 | **Deprecate `transport` alias** after `2026_08_28_100000:11-56` migration fully applied; note `InstituteDomain:121 transport→transportation`. | `config/industry_rules.php:32-33` `app/Support/InstituteDomain.php:120` |

### P2 — Governance & Ops

| # | Action | File |
|---|---|---|
| 9 | **Wire `OnlinePaymentAttempt STATUS_COMPLETED → grantModule` webhook** (comment `ModuleAccessService:19-23 Future 63G`) with idempotency `purchase_id` unique. | `app/Services/ModuleAccessService.php:19-23,432-494` |
| 10 | **Add artisan `audit:country-industry`** `php artisan audit:country-industry` that dumps `IndustryRules::industries(country) empty` + `TaxJurisdiction missing per institute` health. | new `app/Console/Commands/AuditCountryIndustry.php` |
| 11 | **Cache `IndustryRules`** `Cache::remember('industry_rules',3600)` if map grows to 20+ countries (currently array config load per request). | `app/Support/IndustryRules.php:23` |

---

## 9. FILE LIST (All Relevant Files — absolute)

**Config:**
`C:\xampp\htdocs\monetix\config\countries.php:1-192` — 192 entries  
`C:\xampp\htdocs\monetix\config\industry_rules.php:1-369` — global 15 industries + 8/16 subs + BD/US matrices + capabilities 6  
`C:\xampp\htdocs\monetix\config\tax.php:1-66` — defaults + BD/US/IN countries + compound_order + audit_enabled

**Support / Domain:**
`C:\xampp\htdocs\monetix\app\Support\IndustryRules.php:15-95` — `industries(country) subIndustries(country,industry) label()`  
`C:\xampp\htdocs\monetix\app\Support\InstituteDomain.php:1-164` — `ACADEMIC_TYPES 4 / PROFESSIONAL_TYPES 5 / fromKeys() / isAcademic/isProfessional/subjectTypeFor/isValidCombination/hasDomainData(147-163)`  
`C:\xampp\htdocs\monetix\app\Support\GeoHierarchy.php:1-150` — `levelLabels, validateHierarchy, unitInCountry`  
`C:\xampp\htdocs\monetix\app\Support\Workspace.php:6-120` — `membership(), id(), set(), verify(), resolveAfterLogin()`  
`C:\xampp\htdocs\monetix\app\Support\TenantContext.php:1-40` + `BranchContext.php:1-30`  
`C:\xampp\htdocs\monetix\app\Support\CountryCodes.php:198` — phone flag helper

**Models:**
`C:\xampp\htdocs\monetix\app\Models\Country.php:10-51` — `levels/units/selectableLevels/academicUnitLabel/educationSystems`  
`C:\xampp\htdocs\monetix\app\Models\Institute.php:12-219` — `country() BelongsTo, industry/sub_industry update blocker 28-48, isModuleEnabled/enabledModules 210-218`  
`C:\xampp\htdocs\monetix\app\Models\User.php:1-329` — `account_type owner|staff, memberships/institutions, EmailNormalizer PhoneNormalizer toE164('Bangladesh')`  
`C:\xampp\htdocs\monetix\app\Models\Student.php:61,74,78` — `country + present/permanent_country_id`  
`C:\xampp\htdocs\monetix\app\Models\Membership.php:1-172` — `institution_user uuid/user_id/institution_id/role_id/branch_id/status`  
`C:\xampp\htdocs\monetix\app\Models\IndustrySetting.php:9` + `IndustryTemplateMapping.php:15-38` (`country_id,industry,sub_industry → structure_templates`)  
`C:\xampp\htdocs\monetix\app\Models\Branch.php:11` `TenantScoped`  
`C:\xampp\htdocs\monetix\app\Models\TaxRate.php:13-69` `TaxJurisdiction.php` `TaxRule.php` `TaxGroup.php` `TaxReturnPeriod.php` `TaxReturnLine.php` `TaxAuditLog.php`

**Migrations:**
`database/migrations/2026_08_13_000000_add_industry_to_institutes_table.php:12` + `2026_08_14_195437_add_sub_industry:12` + `2026_08_15_190100_add_global_address_columns.php:34-38` (`country_id, admin_level_*_id`) + `2026_08_15_190000_create_geo_tables.php:40,47,55` + `2026_08_17_100000_create_academic_structure_tables.php:27` + `2026_08_24_000100_create_learning_structure_engine_tables.php:112,126` + `2026_08_28_100000_restructure_industry_institution_domain_taxonomy.php:11-56` + `2026_08_30_000100_create_tax_engine_tables.php:11-128` + `2026_08_30_000200_add_tax_engine_permissions.php` + `2026_09_03_000300_add_soft_deletes_to_tax_jurisdictions.php:13`

**Controllers / Routing:**
`C:\xampp\htdocs\monetix\app\Http\Controllers\InstituteOnboardingController.php:1-132` — `SESSION_KEY onboarding, step1, choose, validatedSelection(58-91), selection(98-126), clear`  
`C:\xampp\htdocs\monetix\app\Http\Controllers\InstituteCreationController.php:1-338` — `create, store (country lock 94, validateHierarchy 100, selection-derived industry 121), geoAddress 202, syncLegacy 238, industryThemeColor 266, uniqueSlug 325`  
`C:\xampp\htdocs\monetix\app\Http\Controllers\Auth\RegistrationFlowController.php:1-529` —  5-step + `finalizeRegistration:311-410 transaction User+Institute+Membership` + `resolvePending:420-445`  
`C:\xampp\htdocs\monetix\routes\web.php:62-95 (register 5-step)` + `120-137 (workspace onboarding/create)` `Admin\InstituteAdminController.php:264,290` `DashboardController.php:107-155` `BusinessProfileController.php:27,76,313`

**Services:**
`C:\xampp\htdocs\monetix\app\Services\ModuleAccessService.php:23-630` — 5-step `resolveEnabled 227-298` `isEducationIndustry 387` `isIndustryCompatible 376`  
`C:\xampp\htdocs\monetix\app\Services\Tax\TaxEngine.php:12-95` — `resolveRates + jurisdictionForCountry`  
`C:\xampp\htdocs\monetix\app\Services\Tax\TaxComplianceService.php:17-178` — rate histories + returns  
`C:\xampp\htdocs\monetix\app\Services\LearningStructureResolver.php:15-60` + `AcademicSetupService.php` + `Demo\DemoDataService.php` (post-create hooks)

**Views:**
`resources/views/workspace/onboarding.blade.php:38-105` — country→industry→sub cascade  
`resources/views/workspace/create.blade.php:51,82,95` — locked selection + geoAddress  
`resources/views/auth/register-organization.blade.php:68-76,132` + `register-account.blade.php` + `register-otp.blade.php` + `register-address.blade.php` + `register-education-placeholder.blade.php:412`  
`resources/views/auth/register-select.blade.php:63` `students/form.blade.php:232` `partials/phone.blade.php:12` `business/profile.blade.php:74,95,338`

**Docs (this audit):**
`C:\xampp\htdocs\monetix\PHASE_AUDIT_COUNTRY_INDUSTRY_IMPLEMENTATION_REPORT.md` (this file, read-only generation)  
`C:\xampp\htdocs\monetix\docs\fullcodebase.md:1-443` — prior ACCUMENAI_COMPLETE_SYSTEM_ANALYSIS (now 443 lines, 65 KB)

---

## 10. DATA_MODIFIED_DURING_AUDIT: **NO**

All evidence gathered via `Read` (full file), `Grep` (`country|industry|tax`), `Glob`, `Bash Get-ChildItem`. No `Edit`, `Write` (except this report itself at `PHASE_AUDIT_COUNTRY_INDUSTRY_IMPLEMENTATION_REPORT.md` — audit artifact, not codebase modification), no `DB::statement`, no migration run, no `config:cache`. Original source files retain `LastWriteTime` prior to 2026-08-28. Re-run `git status` shows only this `PHASE_*.md` added as **untracked** audit doc.

---

**Next Steps:** Execute P0 recommendations (§8) before adding new countries beyond `Bangladesh`. For a follow-up **Implementation Plan** (`--plan` mode), request `PHASE: COUNTRY_INDUSTRY_EXPANSION` with target markets (UK/US/IN) — will generate `docs/industry_expansion_plan.md` without touching code.
