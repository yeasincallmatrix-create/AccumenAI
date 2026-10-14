<?php

namespace App\Models\Medical;

use App\Models\Branch;
use App\Models\Concerns\TenantScoped;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One medical billing payment (medical_payments table).
 *
 * medical_invoices only carries the LAST payment_method/payment_reference as
 * overwritten columns, so a second payment wiped the first reference and left
 * no row to attach a receipt journal to. This model is that ledger:
 *
 *   - created by BillingService::processPayment() and TpaService::approve();
 *   - journal_id = the receipt journal posted for THIS payment only
 *     (null while posting was skipped fail-soft / missing CoA);
 *   - medical_invoices.journal_id stays reserved for the sale journal.
 *
 * SoftDeletes + TenantScoped per spec (LabTest/LabResult precedent in the
 * Medical namespace); institute_id is pinned to TenantContext on create.
 */
class Payment extends Model
{
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'medical_payments';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'invoice_id',
        'amount',
        'payment_method',
        'payment_reference',
        'paid_at',
        'journal_id',
        'received_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Reporting / period close: payments inside a [from, to] window on the
     * ledger's own clock (paid_at), independent of created_at.
     */
    public function scopeForDateRange($query, $from, $to)
    {
        return $query->whereBetween('paid_at', [$from, $to]);
    }
}
