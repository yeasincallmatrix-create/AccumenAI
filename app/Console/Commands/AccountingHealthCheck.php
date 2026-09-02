<?php

namespace App\Console\Commands;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\JournalEntry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AccountingHealthCheck extends Command
{
    protected $signature = 'accounting:health-check {--institute= : Limit check to a specific institute ID}';

    protected $description = 'Read-only health check for the accounting engine. Verifies database integrity, balances, and invariants.';

    public function handle(): int
    {
        $instituteId = $this->option('institute') ? (int) $this->option('institute') : null;
        $failures = 0;
        $warnings = 0;

        $this->newLine();
        $this->info('=== ACCOUNTING HEALTH CHECK ===');
        $this->newLine();

        // 1. Database connectivity
        $this->line('1. Database connectivity...');
        try {
            DB::connection()->getPdo();
            $this->info('   OK');
        } catch (\Exception $e) {
            $this->error('   FAIL: ' . $e->getMessage());
            return 1;
        }

        // 2. Required tables
        $this->line('2. Required tables...');
        $requiredTables = [
            'journals', 'journal_entries', 'invoices', 'payments', 'chart_of_accounts',
            'account_groups', 'fiscal_years', 'accounting_periods', 'currencies',
            'parties', 'opening_balances', 'fx_revaluations', 'accounting_audit_trails',
            'accounting_settings',
        ];
        $existing = DB::select('SHOW TABLES');
        $existingNames = array_map(fn($r) => reset($r), $existing);
        foreach ($requiredTables as $table) {
            if (!in_array($table, $existingNames)) {
                $this->error("   FAIL: Missing table '$table'");
                $failures++;
            }
        }
        if ($failures === 0) {
            $this->info('   OK (' . count($requiredTables) . ' tables)');
        }

        // 3. Required CoA accounts
        $this->line('3. Chart of Accounts completeness...');
        $coaQuery = ChartOfAccount::query();
        if ($instituteId) {
            $coaQuery->where('institute_id', $instituteId);
        }
        $coaCount = $coaQuery->count();
        if ($coaCount === 0) {
            $this->warn('   WARN: No CoA accounts found');
            $warnings++;
        } else {
            $this->info("   OK ($coaCount accounts)");
        }

        // 4. Base currency
        $this->line('4. Base currency...');
        $baseCurrency = Currency::where('is_base', true)->first();
        if (!$baseCurrency) {
            $anyCurrency = Currency::where('is_active', true)->first();
            if (!$anyCurrency) {
                $this->error('   FAIL: No currencies configured');
                $failures++;
            } else {
                $this->warn('   WARN: No base currency flagged (have ' . Currency::where('is_active', true)->count() . ' active currencies, base is derived per institute via settings)');
                $warnings++;
            }
        } else {
            $this->info("   OK ({$baseCurrency->code})");
        }

        // 5. Fiscal year
        $this->line('5. Fiscal year...');
        $fyQuery = FiscalYear::query()->where('status', 'open');
        if ($instituteId) {
            $fyQuery->where('institute_id', $instituteId);
        }
        $openFY = $fyQuery->first();
        if (!$openFY) {
            $this->warn('   WARN: No open fiscal year found');
            $warnings++;
        } else {
            $this->info("   OK ({$fyQuery->count()} open)");
        }

        // 6. Open accounting period
        $this->line('6. Open accounting period...');
        $periodQuery = AccountingPeriod::query()->where('status', 'open');
        if ($instituteId) {
            $periodQuery->where('institute_id', $instituteId);
        }
        $openPeriods = $periodQuery->count();
        if ($openPeriods === 0) {
            $this->warn('   WARN: No open accounting period');
            $warnings++;
        } else {
            $this->info("   OK ($openPeriods open periods)");
        }

        // 7. Journal balance integrity
        $this->line('7. Journal balance integrity...');
        $journalQuery = DB::table('journals as j')
            ->join('journal_entries as je', 'je.journal_id', '=', 'j.id')
            ->where('j.status', 'posted')
            ->whereNull('j.deleted_at')
            ->whereNull('je.deleted_at');
        if ($instituteId) {
            $journalQuery->where('j.institute_id', $instituteId);
        }
        $unbalanced = $journalQuery
            ->select('j.id')
            ->selectRaw('ABS(SUM(je.debit) - SUM(je.credit)) as diff')
            ->groupBy('j.id')
            ->havingRaw('ABS(SUM(je.debit) - SUM(je.credit)) > 0.001')
            ->count();
        if ($unbalanced > 0) {
            $this->error("   FAIL: $unbalanced posted journals with unbalanced entries");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 8. Orphan journal entries (entries without a valid journal)
        $this->line('8. Orphan journal entries...');
        $orphaned = DB::table('journal_entries as je')
            ->leftJoin('journals as j', 'j.id', '=', 'je.journal_id')
            ->whereNull('j.id')
            ->count();
        if ($orphaned > 0) {
            $this->error("   FAIL: $orphaned orphan journal entries");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 9. Duplicate journal numbers
        $this->line('9. Duplicate journal numbers...');
        $dupQuery = DB::table('journals')->whereNull('deleted_at');
        if ($instituteId) {
            $dupQuery->where('institute_id', $instituteId);
        }
        $duplicates = $dupQuery
            ->select('journal_no')
            ->groupBy('journal_no')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicates > 0) {
            $this->error("   FAIL: $duplicates duplicate journal numbers");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 10. Tenant isolation check
        $this->line('10. Tenant isolation (orphaned references)...');
        $orphanInstitutes = DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->whereColumn('je.institute_id', '!=', 'j.institute_id')
            ->count();
        if ($orphanInstitutes > 0) {
            $this->error("   FAIL: $orphanInstitutes journal entries with mismatched institute_id");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 11. Negative amounts check
        $this->line('11. Negative amounts in journal entries...');
        $negatives = DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('debit', '<', 0)->orWhere('credit', '<', 0);
            })
            ->count();
        if ($negatives > 0) {
            $this->error("   FAIL: $negatives journal entries with negative amounts");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 12. Both debit and credit on same line
        $this->line('12. Lines with both debit and credit...');
        $bothSides = DB::table('journal_entries')
            ->whereNull('deleted_at')
            ->where('debit', '>', 0)
            ->where('credit', '>', 0)
            ->count();
        if ($bothSides > 0) {
            $this->error("   FAIL: $bothSides lines with both debit and credit");
            $failures++;
        } else {
            $this->info('   OK');
        }

        // 13. Reconciliation: trial balance
        $this->line('13. Trial balance reconciliation...');
        try {
            $reconciliation = app(\App\Services\Accounting\AccountingReconciliationService::class);
            if ($instituteId) {
                $checks = $reconciliation->all($instituteId);
                $failed = collect($checks)->filter(fn ($c) => $c['status'] === 'fail');
                if ($failed->isNotEmpty()) {
                    foreach ($failed as $key => $check) {
                        $this->error("   FAIL: $key - {$check['message']}");
                    }
                    $failures += $failed->count();
                } else {
                    $this->info('   OK (all ' . count($checks) . ' checks pass)');
                }
            } else {
                $this->info('   SKIP (specify --institute for detailed reconciliation)');
            }
        } catch (\Exception $e) {
            $this->warn('   WARN: Reconciliation check failed: ' . $e->getMessage());
            $warnings++;
        }

        // 14. Fixed asset tables
        $this->line('14. Fixed asset & tax tables...');
        $extraTables = ['fixed_assets', 'tax_rates', 'tax_jurisdictions', 'budgets', 'fx_revaluations'];
        $existingNames = array_map(fn($r) => reset($r), DB::select('SHOW TABLES'));
        $missingExtra = array_filter($extraTables, fn($t) => !in_array($t, $existingNames));
        if (!empty($missingExtra)) {
            $this->warn('   WARN: Missing tables: ' . implode(', ', $missingExtra));
            $warnings++;
        } else {
            $this->info('   OK');
        }

        // 15. Exchange rates coverage
        $this->line('15. Exchange rates & currencies...');
        $currencyCount = Currency::count();
        $activeCurrencyCount = Currency::where('is_active', true)->count();
        if ($currencyCount === 0) {
            $this->error('   FAIL: No currencies configured');
            $failures++;
        } else {
            $this->info("   OK ($activeCurrencyCount/$currencyCount active)");
        }

        // 16. Failed jobs
        $this->line('16. Failed jobs...');
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $this->warn("   WARN: $failedJobs failed jobs in queue");
                $warnings++;
            } else {
                $this->info('   OK');
            }
        } catch (\Exception $e) {
            $this->info('   SKIP (no failed_jobs table)');
        }

        // 17. Audit trail coverage
        $this->line('17. Audit trail...');
        $auditCount = DB::table('accounting_audit_trails')->count();
        $this->info("   OK ($auditCount audit records)");

        // Summary
        $this->newLine();
        $this->info('=== SUMMARY ===');
        if ($failures > 0) {
            $this->error("FAILURES: $failures | WARNINGS: $warnings");
            return 1;
        }
        if ($warnings > 0) {
            $this->warn("PASS (with $warnings warnings)");
        } else {
            $this->info('ALL CHECKS PASSED');
        }

        return 0;
    }
}
