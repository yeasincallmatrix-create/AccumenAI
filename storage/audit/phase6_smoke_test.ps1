# ============================================================================
# Phase 6 smoke test — Industry Matrix final wiring (7 categories)
#
#   1. DB schema (both DBs)        5. Hard boundaries (Layer 7 / Layer 8)
#   2. Seed counts (both DBs)      6. HTTP route smoke (5 URLs)
#   3. Service probes              7. Audit + risk_level write probe
#   4. Layer 6.5 override lifecycle
#
# Read-only except short-lived probe rows that are always deleted (try/finally).
# Raw output is printed for every check. Exit code = number of failures.
# Run from anywhere; all artisan calls run with DB_DATABASE=monetix_test.
# ============================================================================

$ErrorActionPreference = 'Continue'
$root = 'C:\xampp\htdocs\AccumenAI'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$tmp = 'C:\Users\Yasin\AppData\Local\Temp\opencode'
$baseUrl = 'http://localhost/AccumenAI/public'
$env:DB_DATABASE = 'monetix_test'

$script:pass = 0
$script:fail = 0

Push-Location $root
try {
    function Verdict {
        param([bool]$Ok, [string]$Name)
        if ($Ok) { $script:pass++; Write-Host "  [PASS] $Name" -ForegroundColor Green }
        else     { $script:fail++; Write-Host "  [FAIL] $Name" -ForegroundColor Red }
    }

    function Invoke-Sql {
        param([string]$Db, [string]$Sql)
        $f = Join-Path $tmp ("smoke_" + [guid]::NewGuid().ToString('N') + '.sql')
        Set-Content -Path $f -Value $Sql -Encoding ASCII
        try {
            $out = Get-Content $f | & $mysql -u root $Db 2>&1
            ($out | Out-String -Width 300).Trim()
        } finally {
            Remove-Item $f -ErrorAction SilentlyContinue
        }
    }

    function Invoke-Tinker {
        param([string]$Php)
        $f = Join-Path $tmp ("smoke_" + [guid]::NewGuid().ToString('N') + '.php')
        # PHP files need the opening tag or PHP echoes the source as text.
        Set-Content -Path $f -Value ("<?php`n" + $Php) -Encoding UTF8
        try {
            $unix = $f -replace '\\', '/'
            $out = & php artisan tinker --execute "require '$unix';" 2>&1
            ($out | Out-String -Width 300).Trim()
        } finally {
            Remove-Item $f -ErrorAction SilentlyContinue
        }
    }

    function Get-HttpStatus {
        param([string]$Url)
        # PS 5.1 throws InvalidOperationException (no Response object) for
        # -MaximumRedirection 0 on a 302 — curl reports the code cleanly.
        $code = curl.exe -s -o NUL -w '%{http_code}' --max-redirs 0 $Url 2>$null
        if ($code -match '^\d+$') { return [int]$code }
        return 0
    }

    # ── 1. Schema (both DBs) ────────────────────────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 1: DB SCHEMA (both databases) ===' -ForegroundColor Cyan

    foreach ($db in @('monetix_test', 'accumen_ai')) {
        $out = Invoke-Sql $db "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='module_access_logs' AND COLUMN_NAME='risk_level';"
        Write-Host "[$db] risk_level column: $out"
        Verdict ($out -match 'enum') "$db module_access_logs.risk_level is enum"

        $out = Invoke-Sql $db "SELECT COUNT(*) AS tables_ok FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME IN ('industry_subcategories','subcategory_default_modules','module_terminology','module_rules','country_tax_modules','super_admin_overrides');"
        Write-Host "[$db] phase tables present: $out"
        Verdict ($out -match 'tables_ok\s+6') "$db all 6 phase tables exist"
    }

    # ── 2. Seed counts (both DBs) ───────────────────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 2: SEED COUNTS (both databases) ===' -ForegroundColor Cyan

    $seedSql = @'
SELECT 'industry_subcategories' AS tbl, COUNT(*) AS n FROM industry_subcategories
UNION ALL SELECT 'subcategory_default_modules', COUNT(*) FROM subcategory_default_modules
UNION ALL SELECT 'module_terminology', COUNT(*) FROM module_terminology
UNION ALL SELECT 'module_rules', COUNT(*) FROM module_rules
UNION ALL SELECT 'country_tax_modules', COUNT(*) FROM country_tax_modules
UNION ALL SELECT 'module_registry', COUNT(*) FROM module_registry;
'@
    foreach ($db in @('monetix_test', 'accumen_ai')) {
        $out = Invoke-Sql $db $seedSql
        Write-Host "[$db]"
        Write-Host $out
        Verdict ($out -match 'industry_subcategories\s+23')   "$db industry_subcategories = 23"
        Verdict ($out -match 'subcategory_default_modules\s+118') "$db subcategory_default_modules = 118"
        Verdict ($out -match 'module_terminology\s+16')       "$db module_terminology = 16"
        Verdict ($out -match 'module_rules\s+5')              "$db module_rules = 5"
        Verdict ($out -match 'country_tax_modules\s+[2-9]')   "$db country_tax_modules >= 2"
        Verdict ($out -match 'module_registry\s+[1-9]\d*')    "$db module_registry > 0"
    }

    # ── 3. Service probes (subcategory / terminology / rule) ────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 3: SERVICE PROBES (monetix_test) ===' -ForegroundColor Cyan

    $out = Invoke-Tinker @'
$svc = app(\App\Services\IndustrySubcategoryService::class);
$m = $svc->getModules('healthcare', 'pharmacy');
echo 'pharmacy mandatory: ' . implode(',', $m['mandatory']) . PHP_EOL;
echo 'pharmacy default:   ' . implode(',', $m['default']) . PHP_EOL;
echo 'pharmacy optional:  ' . implode(',', $m['optional']) . PHP_EOL;
echo 'find healthcare/pharmacy: ' . ($svc->findByKey('healthcare', 'pharmacy') ? 'FOUND' : 'NULL') . PHP_EOL;
$term = app(\App\Services\TerminologyService::class);
$bd = new \App\Models\Institute(); $bd->country_code = 'BD';
$us = new \App\Models\Institute(); $us->country_code = 'US';
$bdVal = $term->get('medical.opd', $bd, 'X');
$usVal = $term->get('medical.opd', $us, 'X');
echo 'BD medical.opd term: ' . $bdVal . PHP_EOL;
echo 'US medical.opd term: ' . $usVal . PHP_EOL;
echo 'BD vs US term differs: ' . var_export($bdVal !== $usVal, true) . PHP_EOL;
$rule = app(\App\Services\RuleEngineService::class);
echo 'BD tax.vat.rate rule: ' . json_encode($rule->get('tax.vat', 'rate', $bd, null)) . PHP_EOL;
echo 'BD missing rule default: ' . json_encode($rule->get('nope.key', 'x', $bd, 'fb')) . PHP_EOL;
'@
    Write-Host $out
    Verdict ($out -match 'pharmacy mandatory:.*medical\.pharmacy') 'subcategory: pharmacy mandatory contains medical.pharmacy'
    Verdict ($out -match 'find healthcare/pharmacy: FOUND')        'subcategory: findByKey hit'
    Verdict ($out -match 'BD medical\.opd term: .+')               'terminology: BD country term returned'
    Verdict ($out -match 'BD vs US term differs: true')            'terminology: BD country term beats global for US tenant'
    Verdict ($out -match 'BD tax\.vat\.rate rule: .*15')           'rule: BD tax.vat rate = 15'
    Verdict ($out -match '"fb"')                                   'rule: default fallback works'

    # ── 4. Layer 6.5 override lifecycle ─────────────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 4: SUPER ADMIN OVERRIDE LIFECYCLE (monetix_test) ===' -ForegroundColor Cyan

    $out = Invoke-Tinker @'
$svc = app(\App\Services\ModuleAccessService::class);
$pkgId = \Illuminate\Support\Facades\DB::table('subscription_packages')->where('slug', 'basic')->value('id');
$mk = function () use ($pkgId) {
    $inst = \App\Models\Institute::create([
        'name' => 'Phase6 Smoke ' . substr(uniqid(), -6),
        'slug' => 'phase6-smoke-' . substr(uniqid(), -6),
        'status' => 'active',
        'country' => 'Bangladesh',
        'industry' => 'education',
        'package_id' => $pkgId,
    ]);
    $inst->subcategory_key = 'school';
    $inst->country_code = 'BD';
    $inst->save();
    return $inst;
};
$inst = null; $ovId = null;
try {
    $inst = \App\Models\Institute::withoutEvents($mk);
    \Illuminate\Support\Facades\DB::table('institute_subscriptions')->insert([
        'institute_id' => $inst->id, 'package_id' => $pkgId,
        'billing_cycle' => 'yearly', 'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(), 'status' => 'active',
        'created_at' => now(),
    ]);
    $keys = function () use ($svc, $inst) {
        return array_keys(array_filter($svc->resolveEnabled($inst)));
    };
    echo 'tenant #' . $inst->id . ' medical.pharmacy BEFORE override: ' . var_export(in_array('medical.pharmacy', $keys()), true) . PHP_EOL;

    $ovId = \Illuminate\Support\Facades\DB::table('super_admin_overrides')->insertGetId([
        'institute_id' => $inst->id, 'module_key' => 'medical.pharmacy',
        'override_layer' => 'industry', 'reason' => 'Phase 6 smoke test lifecycle probe row.',
        'two_factor_verified' => true, 'started_at' => now(),
        'expires_at' => now()->addDays(7),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $svc->flushCache($inst->id);
    echo 'medical.pharmacy WITH active override: ' . var_export(in_array('medical.pharmacy', $keys()), true) . PHP_EOL;
    echo 'parent medical WITHOUT override (per-module bypass): ' . var_export(in_array('medical', $keys()), true) . PHP_EOL;

    \Illuminate\Support\Facades\DB::table('super_admin_overrides')->where('id', $ovId)->update(['expires_at' => now()->subMinute()]);
    $svc->flushCache($inst->id);
    echo 'medical.pharmacy AFTER expiry: ' . var_export(in_array('medical.pharmacy', $keys()), true) . PHP_EOL;
} finally {
    if ($ovId) \Illuminate\Support\Facades\DB::table('super_admin_overrides')->where('id', $ovId)->delete();
    if ($inst) {
        \Illuminate\Support\Facades\DB::table('institute_subscriptions')->where('institute_id', $inst->id)->delete();
        \Illuminate\Support\Facades\DB::table('institute_module_overrides')->where('institute_id', $inst->id)->delete();
        \Illuminate\Support\Facades\DB::table('module_access_logs')->where('institute_id', $inst->id)->delete();
        $inst->delete();
    }
    $left = \Illuminate\Support\Facades\DB::table('super_admin_overrides')->count();
    echo 'cleanup done; super_admin_overrides rows left: ' . $left . PHP_EOL;
}
'@
    Write-Host $out
    Verdict ($out -match 'BEFORE override: false')            'override: medical.pharmacy blocked before override'
    Verdict ($out -match 'WITH active override: true')        'override: active override applies (Layer 6.5)'
    Verdict ($out -match 'AFTER expiry: false')               'override: expired override stops applying'
    Verdict ($out -match 'parent medical.*: false')           'override: parent module stays off (per-module bypass)'
    Verdict ($out -match 'rows left: 0')                      'override: probe rows cleaned up'

    # ── 5. Hard boundaries (Layer 7 / Layer 8) ──────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 5: HARD BOUNDARIES (Layer 7 industry / Layer 8 country) ===' -ForegroundColor Cyan

    $out = Invoke-Tinker @'
$svc = app(\App\Services\ModuleAccessService::class);
$mk = function ($ind, $sub, $cc) {
    $i = new \App\Models\Institute();
    $i->industry = $ind; $i->subcategory_key = $sub;
    $i->country_code = $cc; $i->status = 'active';
    return $i;
};
$edu = $mk('education', 'school', 'BD');
$bd  = $mk('retail', 'grocery', 'BD');
$us  = $mk('retail', 'grocery', 'US');
$hc  = $mk('healthcare', 'pharmacy', 'BD');
echo 'L7 education + medical.pharmacy compatible: ' . var_export($svc->isIndustryCompatible($edu, 'medical.pharmacy'), true) . PHP_EOL;
echo 'L7 healthcare + medical.pharmacy compatible: ' . var_export($svc->isIndustryCompatible($hc, 'medical.pharmacy'), true) . PHP_EOL;
echo 'L7 education + crm compatible: ' . var_export($svc->isIndustryCompatible($edu, 'crm'), true) . PHP_EOL;
echo 'L8 BD + vat allowed: ' . var_export($svc->isCountryTaxAllowed('vat', $bd), true) . PHP_EOL;
echo 'L8 BD + gst allowed: ' . var_export($svc->isCountryTaxAllowed('gst', $bd), true) . PHP_EOL;
echo 'L8 US + vat allowed: ' . var_export($svc->isCountryTaxAllowed('vat', $us), true) . PHP_EOL;
echo 'resolver education medical.* count: ' . count(array_values(array_filter(array_keys(array_filter($svc->resolveEnabled($edu))), fn ($m) => str_starts_with($m, 'medical')))) . PHP_EOL;
echo 'resolver BD gst enabled: ' . var_export(in_array('gst', array_keys(array_filter($svc->resolveEnabled($bd)))), true) . PHP_EOL;
'@
    Write-Host $out
    Verdict ($out -match 'L7 education \+ medical\.pharmacy compatible: false') 'Layer 7 blocks medical for education'
    Verdict ($out -match 'L7 healthcare \+ medical\.pharmacy compatible: true')  'Layer 7 allows medical for healthcare'
    Verdict ($out -match 'L7 education \+ crm compatible: true')                 'Layer 7 passes industry-neutral modules'
    Verdict ($out -match 'L8 BD \+ vat allowed: true')                           'Layer 8 allows vat for BD'
    Verdict ($out -match 'L8 BD \+ gst allowed: false')                          'Layer 8 blocks gst for BD'
    Verdict ($out -match 'L8 US \+ vat allowed: false')                          'Layer 8 blocks vat for US'
    Verdict ($out -match 'resolver education medical\.\* count: 0')              'resolver: no medical modules for education'
    Verdict ($out -match 'resolver BD gst enabled: false')                       'resolver: gst off for BD tenant'

    # ── 6. HTTP route smoke ─────────────────────────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 6: HTTP ROUTE SMOKE (expect 200/302, never 404/500) ===' -ForegroundColor Cyan

    $urls = @(
        '/admin/industry-subcategories',
        '/admin/institutes/modules-overview',
        '/admin/institutes/1/modules',
        '/settings/terminology',
        '/super-admin/institutes/1/emergency-override'
    )
    foreach ($u in $urls) {
        $code = Get-HttpStatus ($baseUrl + $u)
        Write-Host "  $code  $u"
        Verdict (($code -eq 200) -or ($code -eq 302)) "HTTP $u -> $code"
    }

    # ── 7. Audit + risk_level write probe ───────────────────────────────────
    Write-Host ''
    Write-Host '=== CATEGORY 7: AUDIT + risk_level WRITE PROBE ===' -ForegroundColor Cyan

    $out = Invoke-Tinker @'
echo 'risk_level enum: ' . \Illuminate\Support\Facades\DB::select("SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='module_access_logs' AND COLUMN_NAME='risk_level'")[0]->t . PHP_EOL;
$svc = app(\App\Services\ModuleAccessService::class);
$pkgId = \Illuminate\Support\Facades\DB::table('subscription_packages')->where('slug', 'basic')->value('id');
$mk = function () use ($pkgId) {
    $inst = \App\Models\Institute::create([
        'name' => 'Phase6 Audit ' . substr(uniqid(), -6),
        'slug' => 'phase6-audit-' . substr(uniqid(), -6),
        'status' => 'active', 'country' => 'Bangladesh',
        'industry' => 'retail', 'package_id' => $pkgId,
    ]);
    $inst->country_code = 'BD'; $inst->save();
    return $inst;
};
$inst = null;
try {
    $inst = \App\Models\Institute::withoutEvents($mk);
    $svc->logAccess($inst->id, 'vat', 'emergency_override', null, null, 'enabled', $pkgId, 'phase6 smoke risk_level probe', actorType: 'system', riskLevel: 'critical');
    $row = \Illuminate\Support\Facades\DB::table('module_access_logs')->where('institute_id', $inst->id)->where('action', 'emergency_override')->first();
    echo 'written risk_level: ' . ($row ? $row->risk_level : 'ROW MISSING') . PHP_EOL;
    $svc->logAccess($inst->id, 'crm', 'enable', null, 'disabled', 'enabled', $pkgId, 'phase6 smoke default probe');
    $row2 = \Illuminate\Support\Facades\DB::table('module_access_logs')->where('institute_id', $inst->id)->where('action', 'enable')->first();
    echo 'default risk_level: ' . ($row2 ? $row2->risk_level : 'ROW MISSING') . PHP_EOL;
} finally {
    if ($inst) {
        \Illuminate\Support\Facades\DB::table('module_access_logs')->where('institute_id', $inst->id)->delete();
        \Illuminate\Support\Facades\DB::table('institute_subscriptions')->where('institute_id', $inst->id)->delete();
        $inst->delete();
    }
    echo 'cleanup rows left: ' . \Illuminate\Support\Facades\DB::table('module_access_logs')->where('notes', 'like', 'phase6 smoke%')->count() . PHP_EOL;
}
'@
    Write-Host $out
    Verdict ($out -match 'enum\(.*critical')           'audit: risk_level enum contains critical'
    Verdict ($out -match 'written risk_level: critical') 'audit: logAccess writes explicit risk_level'
    Verdict ($out -match 'default risk_level: low')      'audit: default risk_level = low'
    Verdict ($out -match 'cleanup rows left: 0')         'audit: probe log rows cleaned up'

    # ── Summary ─────────────────────────────────────────────────────────────
    Write-Host ''
    Write-Host '=== PHASE 6 SMOKE SUMMARY ===' -ForegroundColor Cyan
    Write-Host "PASS: $script:pass   FAIL: $script:fail"
    exit $script:fail
} finally {
    Pop-Location
}
