<?php

namespace App\Listeners;

use App\Events\InvoicePaid;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Logs invoice payment events for observability.
 * Idempotent — pure logging, no side effects.
 */
class LogInvoicePaid implements ShouldQueue
{
    public function handle(InvoicePaid $event): void
    {
        Log::info('InvoicePaid event', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->invoice_number,
            'amount_paid' => $event->amountPaid,
            'institute_id' => $event->instituteId,
            'branch_id' => $event->branchId,
        ]);
    }
}
