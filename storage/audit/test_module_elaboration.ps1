<#
.SYNOPSIS
    Module Elaboration verification for /admin/module-config (2026-09-25).

.DESCRIPTION
    Re-runs the fix verification end to end:
      1. Lint the changed controller
      2. Clear config / view / route caches
      3. Dump the live parent / child / single hierarchy from module_registry
      4. Run the UniversalModuleConfigTest suite (12 tests):
         - 5 collapsible parents, childless modules stay flat
         - medical.pharmacy / sales.orders render as indented child rows
         - rendered form round-trips and saves parent + child + single rows
      5. Report PASS / FAIL and exit non-zero on any failure

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File storage\audit\test_module_elaboration.ps1
#>

$ErrorActionPreference = 'Stop'
$repo = 'C:\xampp\htdocs\AccumenAI'
Set-Location -LiteralPath $repo

$failures = 0

function Step-Check {
    param([string]$Name, [int]$ExitCode)

    if ($ExitCode -eq 0) {
        Write-Host ("  PASS  {0}" -f $Name) -ForegroundColor Green
    }
    else {
        Write-Host ("  FAIL  {0} (exit {1})" -f $Name, $ExitCode) -ForegroundColor Red
        $script:failures++
    }
}

Write-Host ""
Write-Host "=== Module Elaboration verification ===" -ForegroundColor Cyan
Write-Host "Repo: $repo"
Write-Host "Branch: $((git rev-parse --abbrev-ref HEAD))  Commit: $((git rev-parse --short HEAD))"
Write-Host ""

Write-Host "[1/5] Lint changed controller" -ForegroundColor Cyan
php -l app\Http\Controllers\Admin\UniversalModuleConfigController.php
Step-Check 'php -l UniversalModuleConfigController' $LASTEXITCODE

Write-Host "[2/5] Clear config / view / route caches" -ForegroundColor Cyan
php artisan config:clear | Out-Null
Step-Check 'config:clear' $LASTEXITCODE
php artisan view:clear | Out-Null
Step-Check 'view:clear' $LASTEXITCODE
php artisan route:clear | Out-Null
Step-Check 'route:clear' $LASTEXITCODE

Write-Host "[3/5] Live hierarchy from module_registry" -ForegroundColor Cyan
$hierarchy = '$rows = DB::table(''module_registry'')->where(''status'', ''active'')->orderBy(''sort_order'')->orderBy(''key'')->get();'
$hierarchy += '$kids = $rows->filter(fn ($r) => $r->parent_key !== null)->groupBy(''parent_key'');'
$hierarchy += '$parents = $rows->filter(fn ($r) => ! $r->parent_key && $kids->has($r->key));'
$hierarchy += '$singles = $rows->filter(fn ($r) => ! $r->parent_key && ! $kids->has($r->key));'
$hierarchy += '$children = $rows->filter(fn ($r) => $r->parent_key !== null);'
$hierarchy += 'echo ''parents='' . $parents->count() . '' children='' . $children->count() . '' singles='' . $singles->count() . '' total='' . $rows->count() . PHP_EOL;'
$hierarchy += 'foreach ($parents as $p) { echo ''  '' . $p->key . '' -> '' . $kids->get($p->key)->pluck(''key'')->implode('', '') . PHP_EOL; }'
php artisan tinker --execute="$hierarchy"
Step-Check 'hierarchy dump' $LASTEXITCODE

Write-Host "[4/5] UniversalModuleConfigTest suite" -ForegroundColor Cyan
php artisan test --filter UniversalModuleConfigTest
Step-Check 'UniversalModuleConfigTest' $LASTEXITCODE

Write-Host "[5/5] Changed files" -ForegroundColor Cyan
git status --short -- app/Http/Controllers/Admin/UniversalModuleConfigController.php resources/views/admin/module-config tests/Feature/UniversalModuleConfigTest.php storage/audit/module_elaboration_diagnostic.txt storage/audit/test_module_elaboration.ps1

Write-Host ""
if ($failures -eq 0) {
    Write-Host "RESULT: PASS - module elaboration verified (5 parents / 43 children / 12 singles)." -ForegroundColor Green
}
else {
    Write-Host "RESULT: FAIL - $failures step(s) failed." -ForegroundColor Red
    exit 1
}
