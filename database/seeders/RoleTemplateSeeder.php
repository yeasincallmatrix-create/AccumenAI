<?php

namespace Database\Seeders;

use App\Models\Institute;
use App\Services\RoleTemplateService;
use Illuminate\Database\Seeder;

/**
 * Seed industry-appropriate staff roles for every institute.
 *
 * Delegates to RoleTemplateService (the same entry point the
 * institute-creation flow should call for new institutes). Idempotent —
 * safe to re-run; existing roles and manual permission grants are kept.
 *
 * Run explicitly:
 *
 *   php artisan db:seed --class=RoleTemplateSeeder
 */
class RoleTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(RoleTemplateService::class);

        $totals = ['institutes' => 0, 'roles_created' => 0, 'roles_existing' => 0, 'permissions_attached' => 0];

        foreach (Institute::orderBy('id')->get() as $institute) {
            $summary = $service->seedForInstitute($institute);
            $totals['institutes']++;
            $totals['roles_created'] += $summary['roles_created'];
            $totals['roles_existing'] += $summary['roles_existing'];
            $totals['permissions_attached'] += $summary['permissions_attached'];

            $this->command->line(
                "Institute {$institute->id} ({$institute->industry}): "
                . "{$summary['roles_created']} created, {$summary['roles_existing']} existing, "
                . "{$summary['permissions_attached']} permissions attached."
            );

            if (! empty($summary['skipped_slugs'])) {
                $this->command->warn('  Skipped unknown slugs: ' . implode(', ', $summary['skipped_slugs']));
            }
        }

        $this->command->info(
            "Role templates seeded for {$totals['institutes']} institute(s): "
            . "{$totals['roles_created']} roles created, {$totals['permissions_attached']} permissions attached."
        );
    }
}
