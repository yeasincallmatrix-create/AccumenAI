<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class BackfillRegistrationNumbers extends Command
{
    protected $signature = 'backfill:registration-numbers';

    protected $description = 'Deprecated: registration_number removed, now using reg_no (10-digit) as primary identifier';

    public function handle(): int
    {
        $this->warn('This command is deprecated: registration_number column has been removed. System now uses reg_no (10-digit random) as primary registration identifier.');
        $this->info('No action required. All students already have reg_no via Student::creating booted fallback.');
        return self::SUCCESS;
    }
}
