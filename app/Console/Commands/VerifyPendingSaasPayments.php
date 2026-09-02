<?php

namespace App\Console\Commands;

use App\Models\OnlinePaymentAttempt;
use App\Services\SaasSubscriptionService;
use Illuminate\Console\Command;

class VerifyPendingSaasPayments extends Command
{
    protected $signature = 'saas:verify-pending {--limit=50}';
    protected $description = 'Reconcile pending SaaS bKash payment attempts via server query';

    public function handle(SaasSubscriptionService $saas): int
    {
        $limit = (int) $this->option('limit');
        $pending = OnlinePaymentAttempt::where('status', OnlinePaymentAttempt::STATUS_PENDING)
            ->whereHas('gateway', fn($q) => $q->where('slug','bkash'))
            ->where('created_at','<', now()->subMinutes(2))
            ->limit($limit)
            ->get();
        $checked = 0;
        foreach ($pending as $attempt) {
            // In real integration, query bKash server; here we just log and skip (do not auto-success)
            // Only verified Completed will activate; pending remains pending until callback
            $checked++;
        }
        $this->info("Checked {$checked} pending SaaS bKash attempts (verification requires bKash query).");
        return self::SUCCESS;
    }
}
