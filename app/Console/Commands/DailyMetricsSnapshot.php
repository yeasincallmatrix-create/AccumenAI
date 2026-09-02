<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Accounting\ExecutiveDashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DailyMetricsSnapshot extends Command
{
    protected $signature = 'metrics:snapshot';

    protected $description = 'Capture daily KPIs for all active institutes and store for trend analysis';

    public function handle(): int
    {
        $service = app(ExecutiveDashboardService::class);

        $institutes = Institute::query()->where('status', 'active')->get();

        $this->info("Capturing daily metrics for {$institutes->count()} institutes...");

        $snapshots = [];

        foreach ($institutes as $institute) {
            try {
                $kpis = $service->kpiSummary((int) $institute->id, null);

                $snapshots[] = [
                    'institute_id' => $institute->id,
                    'date' => now()->toDateString(),
                    'total_revenue' => $kpis['total_revenue'],
                    'total_expenses' => $kpis['total_expenses'],
                    'net_income' => $kpis['net_income'],
                    'cash_balance' => $kpis['cash_balance'],
                    'accounts_receivable' => $kpis['accounts_receivable'],
                    'accounts_payable' => $kpis['accounts_payable'],
                    'active_customers' => $kpis['active_customers'],
                    'active_suppliers' => $kpis['active_suppliers'],
                    'created_at' => now(),
                ];
            } catch (\Exception $e) {
                $this->warn("   Skipped institute {$institute->id}: " . $e->getMessage());
            }
        }

        if (! empty($snapshots)) {
            $table = 'daily_metrics_snapshots';

            if (! DB::getSchemaBuilder()->hasTable($table)) {
                DB::getSchemaBuilder()->create($table, function ($blueprint) {
                    $blueprint->id();
                    $blueprint->foreignId('institute_id')->constrained()->cascadeOnDelete();
                    $blueprint->date('date');
                    $blueprint->decimal('total_revenue', 16, 4)->default(0);
                    $blueprint->decimal('total_expenses', 16, 4)->default(0);
                    $blueprint->decimal('net_income', 16, 4)->default(0);
                    $blueprint->decimal('cash_balance', 16, 4)->default(0);
                    $blueprint->decimal('accounts_receivable', 16, 4)->default(0);
                    $blueprint->decimal('accounts_payable', 16, 4)->default(0);
                    $blueprint->unsignedInteger('active_customers')->default(0);
                    $blueprint->unsignedInteger('active_suppliers')->default(0);
                    $blueprint->timestamps();
                    $blueprint->unique(['institute_id', 'date']);
                });
            }

            foreach ($snapshots as $snapshot) {
                DB::table($table)->updateOrInsert(
                    ['institute_id' => $snapshot['institute_id'], 'date' => $snapshot['date']],
                    $snapshot
                );
            }

            $this->info("Stored " . count($snapshots) . " snapshots.");
        } else {
            $this->warn('No snapshots captured.');
        }

        return 0;
    }
}
