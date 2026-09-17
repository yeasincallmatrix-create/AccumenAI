<?php

namespace App\Console\Commands;

use App\Models\Institute;
use App\Services\Medical\MedicineCodeService;
use Illuminate\Console\Command;

class CheckMedicineCodeCapacity extends Command
{
    protected $signature = 'medical:medicine-code-capacity {--warn=80}';
    protected $description = 'Check medicine code slab capacity per institute';

    public function handle(MedicineCodeService $service): int
    {
        $threshold = (int) $this->option('warn');

        foreach (Institute::orderBy('id')->get() as $institute) {
            $this->info("Institute #{$institute->id} — {$institute->name}");
            $slabs = $service->slabInfo($institute->id);

            foreach ($slabs as $slab) {
                $icon = $slab['percent_used'] >= 90 ? '🔴'
                      : ($slab['percent_used'] >= $threshold ? '🟡' : '🟢');
                $this->line(sprintf(
                    '  %s %d-digit: %s / %s (%.2f%%)',
                    $icon,
                    $slab['digits'],
                    number_format($slab['used']),
                    number_format($slab['capacity']),
                    $slab['percent_used']
                ));
            }
        }

        return 0;
    }
}
