# Country Selection, Terminology & Package Infrastructure Audit (Q1–Q15)

**Date:** 2026-09-24  
**Mode:** READ-ONLY (no file modifications, no migrations, no commits)  
**Evidence dir:** `storage/audit/country_module/`  
**DB:** `accumen_ai` via `C:\xampp\mysql\bin\mysql.exe -u root` + `php artisan tinker`

---

=== Q1: Onboarding Country Selection Flow ===

Command:
```
# Controllers located
Get-ChildItem app\Http\Controllers -Filter *Institute*.php | Select Name
# Country hits in InstituteOnboardingController (tinker script q1_q2_q14_q15_evidence.php)
php artisan tinker storage/audit/country_module/scripts/q1_q2_q14_q15_evidence.php
```

Output (excerpt, full: `q1_q2_q14_q15_raw.txt`):
```
q1_controllers:
  InstituteCreationController: EXISTS
  InstituteOnboardingController: EXISTS

q1_onboarding_country_hits:
  16:  * Step-1 owner onboarding: pick country -> industry -> sub-industry.
  18:  * The choices are scoped by country (config/industry_rules.php) so the rest of
  53:      * Validate a country -> industry -> sub-industry selection against the
  61:            'country' => ['required', 'string', 'max:80', Rule::in(array_keys(config('countries', [])))],
  62:            'industry' => ['required', 'string', 'max:60', Rule::in(array_keys(IndustryRules::industries($input['country'] ?? null)))],
  68:         $subs = IndustryRules::subIndustries($country, $industry);
  87:             'country' => $country,
  105:         $country = $selection['country'] ?? null;
  109:         if (! is_string($country) || ! array_key_exists($country, config('countries', []))) {
  122:             'country' => $country,
```

Finding: Onboarding **does** collect country first. `InstituteOnboardingController` validates `country` against `config('countries')`, then scopes `industry` / `sub_industry` via `IndustryRules` from `config/industry_rules.php`. Flow is hard-wired: **country → industry → sub-industry**. Same pattern in registration views (Q2).

---

=== Q2: Views That Display Country ===

Command:
```
Get-ChildItem resources\views\auth -Filter *.php | Select-String "country|Country"
Get-ChildItem resources\views\workspace -Filter onboarding* | Select-String "country|industry|sub_industry"
```

Output (excerpt):
```
resources\views\auth\register-select.blade.php
  53: <label ... for="country">{{ mawa_lang('workspace.country') }} ...
  54: <select id="country" name="country" class="form-select" required>
  112/113: industriesFor(country) { var scoped = data.rules[country]; ... }
  120/121: subsFor(country, industry) { var scoped = data.rules[country]; ... }

resources\views\auth\register-organization.blade.php
  58-62: country select (mawa_lang('workspace.country'))
  96-134: JS cascade country → industry → sub (data.rules[country])

resources\views\auth\register-address.blade.php
  23: {{ $selection['country'] ?? '' }} - {{ $selection['industry'] ?? '' }}
  40: :country-id="old('country_id', $geoAddress['country_id'])"

resources\views\auth\register-owner.blade.php
  56: workspace.country: {{ $countryLabel }}
  93: phone partial with 'country' => $selection['country']

resources\views\workspace\onboarding.blade.php
  38-42: country select (mawa_lang)
  48-57: industry + sub_industry selects
  86-163: full JS cascade on data.rules[country]
```

Finding: Country is a **required** step-1 field on every onboarding/register surface (`register-select`, `register-organization`, `register-address`, `register-owner`, `workspace/onboarding`). Labels go through `mawa_lang()`; the country **list** comes from `config('countries')` (legacy display list — see locale.php B107 note: DB `countries` is source of truth).

---

=== Q3: `institutes` Table Country / Terminology Columns ===

Command:
```
mysql -u root accumen_ai -e "DESCRIBE institutes;"
# saved: storage/audit/country_module/q3_describe_institutes.txt
# filter for term|label|override|locale → no matches (q7_describe_match.txt empty)
```

Output (country-related + absence of terminology cols):
```
Field            Type              Null  Key  Default
country          varchar(80)       NO         Bangladesh
country_id       bigint unsigned   YES   MUL  NULL
package_id       bigint unsigned   YES   MUL  NULL
industry         varchar(...)
industry_id      bigint unsigned   YES        NULL
sub_industry_id  bigint unsigned   YES        NULL
--- no columns matching term|label|override|locale ---
--- ERROR 1054 on country_code (see Q4) ---
```

Finding: `institutes` has **`country` (varchar display, default 'Bangladesh')** and **`country_id` (FK)** but **NO `country_code`** and **NO terminology/label/locale-override columns**. There is no per-tenant term override storage on the institute row.

---

=== Q4: Tenant Country Data + country_code Probe ===

Command:
```
mysql -u root accumen_ai -e "SELECT id, name, country, country_id FROM institutes;"
mysql -u root accumen_ai -e "SELECT country_code FROM institutes LIMIT 1;"   # expected ERROR 1054
```

Output (`q4_tenants_country.txt`):
```
id   name                          country     country_id
4    Professional Training Center  Bangladesh  NULL
5    Mawa Academy                  Bangladesh  21
189  Central Hospital              Bangladesh  21
191  CENTRAL DIAGNOSTIC CENTER     Bangladesh  21
192  Mawa Supershop                Bangladesh  21

# SELECT country_code FROM institutes:
ERROR 1054 (42S22): Unknown column 'country_code' in 'field list'
```

Finding: All **5 live tenants are Bangladesh**. `country_id=21` for 4/5; tenant 4 has **NULL country_id** (gap). **`country_code` column does not exist** on `institutes` (raw ERROR 1054 reported as requested).

---

=== Q5: config/terminology.php (or equivalent) ===

Command:
```
Test-Path config\terminology.php
Get-ChildItem config -Filter *terminolog*; Get-ChildItem config -Filter *translat*; Get-ChildItem config -Filter *term*
Get-ChildItem config -Filter *.php | Select-String "terminolog|translat|term_"
```

Output:
```
Test-Path config\terminology.php  →  False
# filename globs: no matches
# content grep hits (only generic Laravel comment):
  config\app.php:76 | by Laravel's translation / localization methods. ...
  config\app.php:77 | set to any locale for which you plan to have translation strings.
```

Finding: **No `config/terminology.php`.** No terminology/translatable/term_ config. Closest is `config/locale.php` (BD-first phone/currency/date/country fallbacks) and `config/geo-labels.php` (geo admin level labels only).

---

=== Q6: Language Directory Structure ===

Command:
```
Get-ChildItem lang -Recurse -File
# resources/lang: empty / no files (glob resources/lang/**/* → No files found)
```

Output:
```
lang\bn\auth.php
lang\bn\pagination.php
lang\bn\passwords.php
lang\bn\validation.php
lang\en\auth.php
lang\en\pagination.php
lang\en\passwords.php
lang\en\validation.php
lang\mawa\bn.php
lang\mawa\en.php
```

Finding: **10 lang files** only. Locales: `bn`, `en` + `mawa` brand strings (`lang/mawa/{bn,en}.php`). `resources/lang` is empty. UI language toggled by `SetLocale` middleware (`?lang=en|bn`) via `mawa_lang()` / `mawa_e()` helpers (`app/helpers.php:33,139,178`). **en/bn only — no other locales.**

---

=== Q7: Terminology Tables / Columns ===

Command:
```
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%terminolog%';"
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%translat%';"
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%term%';"
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%label%';"
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%dict%';"
# institutes DESCRIBE filter term|label|override|locale → empty
```

Output:
```
%terminolog%  → (empty)
%translat%    → (empty)
%term%        → (empty)
%label%       → structure_label_dictionary
%dict%        → structure_label_dictionary
              → tax_jurisdictions

structure_label_dictionary:
  id, name, code, category, status, metadata, created_at, updated_at
  COUNT(*) = 0   (empty)
  Only consumer: app\Models\StructureLabel.php ($table = 'structure_label_dictionary')
  No migration matched *structure_label* under database/migrations (glob empty)

Medical domain (separate, offline-curated):
  medicine_concepts COUNT = 48
  artisan: medical:backfill-medicine-terminology
  app\Services\Medical\MedicineTerminologyService.php
  app\Console\Commands\BackfillMedicineTerminology.php
  tests assert no terminology management routes (Phase 10)
```

Finding: **No general `terminology*` / `translat*` tables.** `structure_label_dictionary` exists but is **empty (0 rows)** and has no seed/migration in-tree. Institute has no term columns. The only real terminology machinery is **medical-domain only** (`medicine_concepts` = 48 rows, curated offline via artisan, no admin UI). **No country-driven terminology override layer exists.**

---

=== Q8: Hardcoded Domain Terms in Views ===

Command:
```
Get-ChildItem resources\views -Recurse -Filter *.php | Select-String "OPD|IPD"   # count
Get-ChildItem resources\views\medical -Recurse -Filter *.php | Select-String "OPD|IPD" | ? { Line -notmatch "__\(|mawa_lang|mawa_e" }
```

Output (excerpt, full: `q8_hardcoded_terms.txt`):
```
resources\views\medical\dashboard.blade.php:16  Today's OPD
resources\views\medical\dashboard.blade.php:24  Active IPD
resources\views\medical\admissions\current.blade.php:3  Current IPD - AccumenAI
resources\views\medical\admissions\index.blade.php:8    Admissions (IPD)
resources\views\medical\appointments\index.blade.php:8  Appointments (OPD)
resources\views\medical\billing\invoices\create.blade.php:41  ['opd'=>'OPD','ipd'=>'IPD',...]

Total OPD/IPD matches under resources\views: 546
Under resources\views\medical outside translation helpers: 42
```

Finding: Domain terms (**OPD, IPD**, Admissions, Pharmacy labels, etc.) are **hardcoded English** in medical Blade views. 42 medical-view hits are outside `__()`/`mawa_lang()`/`mawa_e()`. No country- or locale-driven term substitution for these strings.

---

=== Q9: config/country_modules.php Content ===

Command:
```
Get-Content config\country_modules.php
```

Output (full file, 35 lines):
```php
return [
    'defaults' => ['education', 'crm', 'accounting'],
    'BD' => ['education', 'crm', 'accounting', 'hr'],
    'US' => ['education', 'crm', 'accounting', 'sales'],
    'GB' => ['education', 'crm', 'accounting', 'sales'],
    'IN' => ['education', 'crm', 'accounting', 'hr'],
    'CA' => ['education', 'crm', 'accounting', 'sales'],
    'AU' => ['education', 'crm', 'accounting', 'hr', 'sales'],
    'PK' => ['education', 'crm', 'accounting', 'hr'],
    'NP' => ['education', 'crm', 'accounting'],
    'LK' => ['education', 'crm', 'accounting'],
    'MY' => ['education', 'crm', 'accounting', 'inventory', 'sales'],
    'AE' => ['education', 'crm', 'accounting', 'sales', 'hr'],
    'SA' => ['education', 'crm', 'accounting', 'hr'],
];
```

Finding: File exists with **`defaults` + 12 ISO-2 country entries** (BD, US, GB, IN, CA, AU, PK, NP, LK, MY, AE, SA). Header states it maps ISO2 → default `module_registry.key` list for admin batch `assign_default_modules`. Unknown keys silently skipped by `CountryBatchService`.

---

=== Q10: country_modules / Country Module Enablement Usage ===

Command:
```
Get-ChildItem app, config, database -Recurse -Filter *.php | Select-String "country_modules"
Get-ChildItem app -Recurse -Filter *.php | Select-String "resolveEnabled|isModuleEnabled|country_code"
# tinker: country_tax_configs schema + count
Get-ChildItem app -Recurse -Filter *CountryTax* | Select FullName
Get-Content config\tax.php | Select -First 50
```

Output (excerpt):
```
country_modules consumers:
  app\Services\CountryBatchService.php:182
    $config = config('country_modules', []);
    $defaults = $config['defaults'] ?? ['education', 'crm', 'accounting'];
  # ONLY consumer in app/config/database

CountryTaxConfig files:
  app\Models\CountryTaxConfig.php
  app\Services\Accounting\CountryTaxConfigService.php

country_tax_configs:
  country_code char(2) UNI, tds_label, module_label, tax_authority, ...
  COUNT(*) = 0   (CountryTaxConfigSeeder not run on this DB)

config/tax.php presets:
  defaults.country = env('TAX_DEFAULT_COUNTRY', 'BD')
  countries: BD (vat 15%, types vat/sd/ait/at), US (sales_tax), IN (gst/cgst/sgst/igst), GB, ...

ModuleAccessService.resolveEnabled():
  - industry from institute, NOT country
  - country_id used ONLY in PackageScope fallback chain:
    [$countryId, $industryId, $subIndustryId] → ... → [null,null,null]
  - country does NOT flip modules on/off directly; it picks a package scope row

resolveEnabled / isModuleEnabled / country_code hit files (first 20):
  BackfillIndustryModules, LocalPackageProvider, InstituteModuleEntitlementController,
  ModuleAdminController, CurrencySettingRequest, ModuleToggle, Institute, CountryTaxConfig,
  TaxDeductionRule, Tds*, CountryCurrencyMap, ... (mostly country_code on tax models)
```

Finding: `config/country_modules.php` is **only consumed by `CountryBatchService::assignDefaultModules()`** (admin batch → writes institute-module entitlements / audit). It is **NOT** used at runtime module gate. Runtime `resolveEnabled()` uses **industry + package scope**; country enters only as **package_scope.country_id dimension**. Tax-by-country lives in `config/tax.php` + `country_tax_configs` (table empty — seeder not run) + `CountryTaxConfigService`.

---

=== Q11: Package / Subscription Tables ===

Command:
```
mysql -u root accumen_ai -e "SHOW TABLES LIKE 'package%';"
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%subscription%';"
mysql -u root accumen_ai -e "DESCRIBE subscription_packages; SELECT * FROM subscription_packages;"
mysql -u root accumen_ai -e "SHOW COLUMNS FROM institutes LIKE '%package%';"
# tinker q11_q13_queries.php → q11_q13_raw.txt
```

Output (`q11_q13_raw.txt` + `q11_subscription_packages.txt`):
```
package tables:
  package_features
  package_modules
  package_scoped_features
  package_scoped_modules
  package_scopes

institutes package cols:
  package_id bigint unsigned YES MUL NULL

subscription_packages (4 rows):
  id name       slug      price_m  price_y  is_default status
  1  FREE       free      0.00     0.00     1          active
  2  BASIC      basic     1500.00  15000.00 0          active
  3  ADVANCED   advanced  4000.00  40000.00 0          active
  4  PREMIUM    premium   9000.00  90000.00 0          active
  # no `tier` column (ERROR 1054 on SELECT tier)

package_scopes columns:
  id, package_id, country_id, industry_id, sub_industry_id,
  inherit_from_parent, price_monthly, price_yearly, currency,
  status, scope_hash (UNI), created_at, updated_at

package_features by package: pkg2=7, pkg3=36, pkg4=42  (FREE/package1 has 0 rows)
```

Finding: Full package stack present: **tiers** = subscription_packages FREE/BASIC/ADVANCED/PREMIUM (4); **legacy** package_features/package_modules; **scoped** package_scopes (+ package_scoped_features/modules) with **country_id + industry_id + sub_industry_id** dimensions and scope_hash. Institute links via `package_id`. FREE has **0** package_features rows (gap vs BASIC/ADVANCED/PREMIUM).

---

=== Q12: Sub-Category / Sub-Industry Tables ===

Command:
```
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%subcat%';"     # empty
mysql -u root accumen_ai -e "SHOW TABLES LIKE '%industry%';"
# tinker: DESCRIBE sub_industries; SELECT COUNT(*) FROM sub_industries
Get-Content config\industry_rules.php | Select-String "sub|category"
```

Output (`q11_q13_raw.txt`):
```
%subcat%  → (empty)
%industry% → industry_settings, industry_template_mappings
              (+ industries, sub_industries via exact/other queries)

industries:        id, name, slug, code, description, status, sort_order, timestamps
sub_industries:    id, industry_id, country_id (nullable MUL), name, slug, code,
                   description, status, sort_order, scope_hash (UNI, STORED GENERATED),
                   timestamps
sub_industries COUNT = 65

industry_rules.php (SSOT for onboarding):
  * Single source of truth for industries and sub-industries.
  * default sub-industries (industry => slug => label) used when no country
  * each value is that industry's sub-industries in the country (slug => label)
  * 'sub_industries' => [ ... ]
  * // US does not include 'mrasha' as a Bangladesh-specific sub-industry.
```

Finding: **No `%subcat%` tables.** Sub-industry model = **`sub_industries` table (65 rows, optional `country_id`)** + **`config/industry_rules.php`** (country-scoped SSOT used by onboarding). Sub-industry is **country-aware** (e.g. madrasha BD-only). This is the only country-scoped “sub-category” layer; there is no separate package sub-category table.

---

=== Q13: module_registry + tax/vat/gst module keys ===

Command:
```
# tinker q11_q13_queries.php:
SELECT COUNT(*) FROM module_registry;
SELECT `key`, parent_key, type, is_core, status FROM module_registry
  WHERE `key` LIKE 'tax%' OR `key` LIKE '%vat%' OR `key` LIKE '%gst%'
     OR `key` LIKE '%tds%' OR `key` LIKE '%invoice%' OR `key` LIKE '%billing%';
SELECT `key`, type, is_core, status FROM module_registry WHERE type='core' ORDER BY `key`;
```

Output (`q11_q13_raw.txt`):
```
module_registry COUNT(*) = 58

tax/vat/gst/invoice/billing match:
  key                 parent_key  type      is_core  status
  vat                 (null)      core      1        active
  tds                 (null)      core      0        active
  medical.billing     medical     industry  0        active
  purchase.invoices   purchase    core      1        active

core modules (type=core): accounting, ai, crm, finance, hr, inventory,
  notifications, purchase, purchase.*, reports, sales, sales.*, tds, vat
  # NO key matching 'gst%' anywhere
```

Finding: **`vat`** (core, is_core=1) and **`tds`** (core, is_core=0) are the tax modules. **No `gst` / `tax` module key.** GST appears only as a **country type** inside `config/tax.php` (IN types: gst/cgst/sgst/igst), not as a registry module. **module_registry = 58 rows** (prior note said 59 — raw count is **58**).

---

=== Q14: Middleware for Country / Locale / Geo ===

Command:
```
Get-ChildItem app\Http\Middleware -Filter *.php | Select Name
Get-ChildItem app\Http\Middleware -Filter *.php | Select-String "country|locale|geo"
# tinker q1_q2_q14_q15_evidence.php → q1_middleware_country_geo (empty array)
Get-Content bootstrap\app.php   # middleware aliases + web/api append
```

Output (excerpt):
```
Middleware files (24):
  AssignRequestId, AuditActivityLog, AuthenticateLabDevice, BlockPlatformAdminEscalation,
  CheckFeatureAccess, CheckModuleAccess, CheckPermission, DenyTeacherFromFinance,
  EnsureAiEnabled, EnsureDomain, EnsureInstituteContext, FinanceWriteGate,
  ForceJsonResponse, MedicalDomain, MedicalModuleAccess, NormalizePersonNames,
  PlatformMaintenance, RequireAdvancedAccounting, SchemaVersionCheck, SecurityHeaders,
  SetFortifyGuard, SetLocale, SetTenantContext

country|geo|locale hits in Middleware:
  (tinker structured scan: EMPTY for country|geo)
  SetLocale.php:20 class SetLocale; :38/:46 app()->setLocale($current)
  # SetLocale handles ?lang=en|bn only — NOT country, NOT geo

bootstrap/app.php:
  aliases: tenant, setlocale, module_access, feature, medical, ...
  web append: AssignRequestId, NormalizePersonNames, SetLocale, SecurityHeaders, PlatformMaintenance
  api append: NormalizePersonNames, ForceJsonResponse, SetLocale
  # NO country middleware alias / registration
```

Finding: **No country middleware. No geo middleware.** Language is handled by **`SetLocale`** (registered web+api) which only resolves **en|bn** (query → session → user preferred_language). Country context is **not** a request middleware; it lives on the institute row / session selection during onboarding.

---

=== Q15: Routes Referencing Country / Geo ===

Command:
```
Get-ChildItem routes -Filter *.php | Select-String "country|geo"
# tinker q1_q2_q14_q15_evidence.php → q15_routes_country_geo
```

Output (`q1_q2_q14_q15_raw.txt`):
```
routes\institute_modules.php:
  1648  GET  academic/country/{country}          → adminAcadStruct@country     (admin.academic.country)
  1649  PUT  academic/country/{country}          → updateCountry
  1651  POST academic/country/{country}/systems  → storeSystem
  1798  // GEO (public) outside tenant/auth so register/address works as guest
  1801  GET  geo/levels/{country}                → GeoController@levels  (geo.levels)
  1802  GET  geo/units                           → GeoController@units   (geo.units)
  1805+ // ADMIN GEO (platform_admin outside tenant)
  1808  GET    admin/geo                         → GeoAdminController@index
  1809  GET    admin/geo/create                  → createCountry
  1810  POST   admin/geo/countries               → storeCountry
  1811  GET    admin/geo/{country}/edit          → edit
  1812  PUT    admin/geo/{country}               → update
  1813  POST   admin/geo/{country}/toggle        → toggleStatus
  1814-1829  geo/imports*, geo/clear*, geo/duplicates*  (GeoImportController, GeoDuplicatesController)

routes\web.php:
  133-135  visitor country home: session('visitor_country') > config('app.country')
           HomePage::resolveForCountry($countryIso2)
  159      // Workspace onboarding — owner-only pick of country/industry/sub_industry
  586-601  Admin: Country Batch Actions → CountryBatchController@__invoke  (admin/countries/batch)
```

Finding: Country/geo surface area = **(a)** public geo API for registration address (`geo/levels/{country}`, `geo/units`), **(b)** platform_admin GEO CRUD/import/duplicates, **(c)** academic country settings (tenant admin), **(d)** visitor-country homepage resolution, **(e)** onboarding route, **(f)** country batch action. **~10+ geo routes + academic/country + batch.** These are data/admin routes — **not** a per-request country context middleware.

---

=== SUMMARY MATRIX ===

| Q | Topic | Exists? | Evidence / Location | Runtime effect |
|---|--------|---------|---------------------|----------------|
| 1 | Onboarding collects country | **YES** | `InstituteOnboardingController` country→industry→sub; validates vs `config('countries')` + `IndustryRules` | Required step-1; scopes rest of form |
| 2 | Views show country | **YES** | register-select/organization/address/owner + workspace/onboarding | Required select; JS cascade `data.rules[country]` |
| 3 | institutes country cols | **YES** (`country`, `country_id`) / **NO** `country_code`, **NO** term cols | `q3_describe_institutes.txt` | Display string + FK; no term overrides |
| 4 | Tenant country data | **YES** — 5/5 BD; `country_id` NULL on id=4; `country_code` **ERROR 1054** | `q4_tenants_country.txt` | All tenants BD; 1 missing FK |
| 5 | config/terminology.php | **NO** | Test-Path False; no *terminolog*/*translat*/*term* file | N/A |
| 6 | Language dirs | **YES** — bn/en + mawa/{bn,en}; 10 files; resources/lang empty | `lang/**` glob | en/bn only via SetLocale + mawa_lang |
| 7 | Terminology tables | **NO** general; `structure_label_dictionary` empty (0); medical `medicine_concepts`=48 offline | SHOW TABLES LIKE; tinker counts | No country term override; medical-only offline curation |
| 8 | Hardcoded domain terms | **YES** — 546 OPD/IPD view hits; 42 medical outside i18n | `q8_hardcoded_terms.txt` | English hardcoded; no locale/country swap |
| 9 | config/country_modules.php | **YES** — defaults + 12 ISO2 | full file read | Defaults for batch assign only |
| 10 | country_modules / module enable | **Partial** — only `CountryBatchService:182`; runtime uses industry + package_scope.country_id; `country_tax_configs`=0 (seeder not run); `config/tax.php` BD/US/IN/GB presets | grep + tinker | Batch-only for country_modules; runtime country→package scope only |
| 11 | Package tables | **YES** — 5 package_* tables + subscription_packages (FREE/BASIC/ADVANCED/PREMIUM); institutes.package_id | `q11_q13_raw.txt`, `q11_subscription_packages.txt` | Scoped packages w/ country+industry+sub dims |
| 12 | Sub-category tables | **NO** `%subcat%`; **YES** `sub_industries` (65, country_id) + `config/industry_rules.php` | tinker + config grep | Country-scoped sub-industry SSOT for onboarding |
| 13 | module_registry tax keys | **YES** — 58 rows; **vat** (core=1), **tds** (core=0); **no gst/tax key** | tinker tax_modules + core_modules | Only vat/tds as modules; GST is tax.php country type |
| 14 | Country/locale/geo middleware | **NO** country/geo MW; **YES** `SetLocale` (en\|bn only), web+api | Middleware scan (empty country/geo); bootstrap/app.php | Language yes; country context **not** middleware |
| 15 | Country/geo routes | **YES** — geo public (2), admin geo (~15), academic/country (3), visitor home, onboarding, countries/batch | `q15_routes_country_geo` | Data/admin APIs; not request country resolver |

---

=== GAPS IDENTIFIED ===

1. **No `config/terminology.php`** and no general terminology/translat* tables — no infrastructure for country-driven term labels (Q5, Q7).
2. **`structure_label_dictionary` is empty (0 rows)** with no in-tree migration/seed consumer beyond `app\Models\StructureLabel` — dead/unpopulated surface (Q7).
3. **`institutes.country_code` column missing** (ERROR 1054) — ISO2 not denormalized on tenant; code lookups must join `country_id`→`countries` or use display `country` varchar (Q3, Q4).
4. **Tenant id=4 has `country_id = NULL`** while country='Bangladesh' — inconsistent FK; breaks any country_id-scoped package_scope / tax resolution for that tenant (Q4).
5. **Hardcoded English domain terms** (OPD/IPD etc.): 546 view matches; **42 medical-view hits outside translation helpers** — not locale- or country-aware (Q8).
6. **`config/country_modules.php` not used at runtime** — only `CountryBatchService::assignDefaultModules()` (admin batch). Runtime module gate = industry + package_scope, not country defaults (Q9, Q10).
7. **`country_tax_configs` table empty (0 rows)** — `CountryTaxConfigSeeder` not run; CountryTaxConfigService has no live country tax rows (Q10).
8. **No GST / tax module key** in module_registry — GST only as `config/tax.php` country `types` for IN; cannot enable/disable GST as a module (Q13).
9. **module_registry count is 58**, not 59 as previously noted — correct raw count from tinker (Q13).
10. **No country middleware** — country is chosen at onboarding and stored on institute; no per-request country context resolver/middleware (geo routes are data APIs only) (Q14, Q15).
11. **Language coverage is en/bn only** (10 lang files) — no multi-locale pack beyond mawa brand strings; SetLocale hardcodes `['en','bn']` (Q6, Q14).
12. **FREE package (package_id=1) has 0 rows in package_features** while BASIC/ADVANCED/PREMIUM have 7/36/42 — potential FREE entitlement gap (Q11).
13. **Terminology (medical) is offline-only by design** (Phase 10 test: no terminology management routes) — country cannot influence medical terms even if needed (Q7, Q8).

---

**HALT.** Report complete — Q1–Q15 answered with raw evidence paths under `storage/audit/country_module/`. No files outside the audit evidence directory were modified; no migrations or commits performed.
