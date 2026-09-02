<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after an invoice is marked as paid or partially paid.
 *
 * Listeners must be idempotent and must not throw exceptions.
 */
class InvoicePaid
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $instituteId,
        public readonly ?int $branchId,
        public readonly float $amountPaid,
    ) {}
}
