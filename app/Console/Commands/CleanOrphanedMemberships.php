<?php

namespace App\Console\Commands;

use App\Services\MembershipService;
use Illuminate\Console\Command;

class CleanOrphanedMemberships extends Command
{
    protected $signature = 'memberships:clean-orphaned';

    protected $description = 'Remove memberships whose user or institute no longer exists';

    public function handle(): int
    {
        $deleted = app(MembershipService::class)->cleanOrphaned();

        $this->info("Deleted {$deleted} orphaned membership(s).");

        return self::SUCCESS;
    }
}
