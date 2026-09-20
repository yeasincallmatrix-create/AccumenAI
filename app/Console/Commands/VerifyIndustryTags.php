<?php

namespace App\Console\Commands;

use App\Models\ChartOfAccount;
use Database\Seeders\IndustryTagSeeder;
use Illuminate\Console\Command;

class VerifyIndustryTags extends Command
{
    protected $signature = 'coa:verify-industry-tags';

    protected $description = 'Verify industry tags are canonical; self-heal on mismatch';

    public function handle(): int
    {
        $canonical = [
            '4001' => ['education', 'training_center'],
            '4002' => ['education', 'training_center'],
            '4003' => null,
            '4010' => null,
            '5007' => null,
        ];

        $mismatches = 0;

        foreach ($canonical as $code => $expected) {
            $actual = ChartOfAccount::globalOnly()->where('code', $code)->value('industries');

            if ($actual !== $expected) {
                $mismatches++;
                $this->warn('Mismatch '.$code.': expected='.json_encode($expected).' got='.json_encode($actual));
            }
        }

        if ($mismatches > 0) {
            $this->info('Self-healing '.$mismatches.' accounts...');
            (new IndustryTagSeeder)->run();
            $this->info('Tags re-canonicalized');
        } else {
            $this->info('All tags canonical');
        }

        return self::SUCCESS;
    }
}
