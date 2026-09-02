<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off migration of student address fields from the legacy hard-coded
 * geo ids to the authoritative geodata.md ids (app/Support/BdGeo.php).
 *
 * Legacy values (old static form lists) -> new ids:
 *   divisions : Dhaka 1->6, Chattogram 2->1, Rajshahi 3->2, Khulna 4->3,
 *               Barishal 5->4, Sylhet 6->5 (Rangpur 7 / Mymensingh 8 unchanged)
 *   districts : Dhaka 1->47, Gazipur 2->41, Narayanganj 3->43, Tangail 4->44,
 *               Faridpur 5->52, Munshiganj 6->48
 *   upazilas  : Savar 1->365, Dhamrai 2->366, Keraniganj 3->367,
 *               Dohar 4->369, Nawabganj 5->368
 *
 * Safe to run repeatedly; updates only rows still carrying a legacy value.
 */
class RemapLegacyGeoIds extends Command
{
    protected $signature = 'geo:remap-legacy-ids
                            {--pretend : Print the planned updates without applying them.}';

    protected $description = 'Rewrite existing students legacy geo ids to the geodata.md dataset ids. Run once at deploy.';

    private const DIVISIONS = [1 => 6, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5];

    private const DISTRICTS = [1 => 47, 2 => 41, 3 => 43, 4 => 44, 5 => 52, 6 => 48];

    private const UPAZILAS = [1 => 365, 2 => 366, 3 => 367, 4 => 369, 5 => 368];

    public function handle(): int
    {
        $total = 0;

        foreach (['present_', 'permanent_'] as $prefix) {
            $total += $this->remap($prefix.'division_id', self::DIVISIONS);
            $total += $this->remap($prefix.'district_id', self::DISTRICTS);
            $total += $this->remap($prefix.'upazila_id', self::UPAZILAS);
        }

        $this->info("Remapped {$total} student address field(s).");

        return self::SUCCESS;
    }

    private function remap(string $column, array $map): int
    {
        $updated = 0;

        foreach ($map as $old => $new) {
            $count = DB::table('students')->where($column, $old)->count();
            if ($count === 0) {
                continue;
            }

            if ($this->option('pretend')) {
                $this->line("  [pretend] {$column}: {$old} -> {$new} ({$count} row(s))");
                $updated += $count;

                continue;
            }

            $updated += DB::table('students')->where($column, $old)->update([$column => $new]);
        }

        return $updated;
    }
}
