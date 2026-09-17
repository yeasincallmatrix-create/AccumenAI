<?php

namespace App\Console\Commands;

use App\Services\InstituteTaxonomyBackfill;
use Illuminate\Console\Command;

/**
 * Safe, idempotent backfill of institutes.industry_id / sub_industry_id
 * from the legacy string columns.
 *
 * Run after the taxonomy seeder (DatabaseSeeder calls
 * IndustryTaxonomySeeder): `php artisan taxonomy:backfill-institutes`.
 * Safe to run more than once; already-filled FKs are never overwritten and
 * unmatched legacy values are reported, never guessed.
 */
class BackfillInstituteTaxonomy extends Command
{
    protected $signature = 'taxonomy:backfill-institutes';

    protected $description = 'Backfill institutes.industry_id/sub_industry_id from legacy string columns (idempotent, reports unmatched values)';

    public function handle(): int
    {
        $report = InstituteTaxonomyBackfill::run();

        if ($report['skipped'] !== null) {
            $this->warn('Skipped: ' . $report['skipped'] . '.');

            return self::SUCCESS;
        }

        $this->info('Institute taxonomy backfill complete.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total institutes', $report['total']],
                ['industry_id filled', $report['industry_filled']],
                ['industry_id already set', $report['industry_already_set']],
                ['industry empty (no legacy value)', $report['industry_empty']],
                ['sub_industry_id filled', $report['sub_filled']],
                ['sub_industry_id already set', $report['sub_already_set']],
                ['sub_industry empty (none required)', $report['sub_empty']],
            ]
        );

        if ($report['unmatched_industries'] !== []) {
            $this->warn('Unmatched industry values (left NULL):');
            foreach ($report['unmatched_industries'] as $value) {
                $this->line('  - ' . $value);
            }
        }

        if ($report['unmatched_subs'] !== []) {
            $this->warn('Unmatched sub-industry values (left NULL):');
            foreach ($report['unmatched_subs'] as $value) {
                $this->line('  - ' . $value);
            }
        }

        if ($report['unmatched_industries'] === [] && $report['unmatched_subs'] === []) {
            $this->info('No unmatched values.');
        }

        return self::SUCCESS;
    }
}
