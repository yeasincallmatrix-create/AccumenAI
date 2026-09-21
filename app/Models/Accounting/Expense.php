<?php

namespace App\Models\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Concerns\TenantScoped;
use App\Models\Invoice;
use App\Models\Institute;
use App\Models\Party;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'expenses';

    protected $fillable = [
        'institute_id', 'branch_id', 'expense_number',
        'paid_by_user_id', 'payment_account_id',
        'expense_date', 'vendor_name', 'reference_number', 'expense_category',
        'description', 'amount', 'currency', 'exchange_rate', 'tax_amount', 'tax_group_id',
        'expense_account_id', 'journal_entry_id',
        'is_billable', 'customer_id', 'markup_percentage', 'billable_amount',
        'billing_status', 'billed_invoice_id', 'billed_at',
        'receipt_path', 'notes',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'tax_amount' => 'decimal:2',
        'is_billable' => 'boolean',
        'markup_percentage' => 'decimal:2',
        'billable_amount' => 'decimal:2',
        'billed_at' => 'datetime',
    ];

    public const CATEGORIES = [
        'travel' => 'Travel',
        'meals' => 'Meals & Entertainment',
        'supplies' => 'Office Supplies',
        'software' => 'Software & Subscriptions',
        'equipment' => 'Equipment',
        'communication' => 'Communication',
        'accommodation' => 'Accommodation',
        'transport' => 'Transport',
        'fuel' => 'Fuel',
        'courier' => 'Courier & Postage',
        'client_entertainment' => 'Client Entertainment',
        'professional_fees' => 'Professional Fees',
        'other' => 'Other',
    ];

    public const BILLING_STATUSES = ['unbillable', 'unbilled', 'billed', 'paid', 'cancelled'];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'expense_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'customer_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(\App\Models\JournalEntry::class);
    }

    public function billedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'billed_invoice_id');
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(\App\Models\TaxGroup::class);
    }

    public function scopeForInstitute($query, int $id)
    {
        return $query->where('institute_id', $id);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeUnbilled($query)
    {
        return $query->where('is_billable', true)->where('billing_status', 'unbilled');
    }

    public function scopeBilled($query)
    {
        return $query->where('billing_status', 'billed');
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('expense_category', $category);
    }

    public function isBillable(): bool
    {
        return $this->is_billable;
    }

    public function isUnbilled(): bool
    {
        return $this->billing_status === 'unbilled';
    }

    public function isBilled(): bool
    {
        return in_array($this->billing_status, ['billed', 'paid']);
    }

    public function canBeBilled(): bool
    {
        return $this->is_billable && $this->billing_status === 'unbilled';
    }

    public function computeBillableAmount(): float
    {
        $base = (float) $this->amount;
        $markup = (float) $this->markup_percentage;
        return round($base * (1 + $markup / 100), 2);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->expense_category] ?? ucfirst($this->expense_category ?? 'Other');
    }

    public function billingStatusColor(): string
    {
        return match ($this->billing_status) {
            'unbillable' => 'secondary',
            'unbilled' => 'warning',
            'billed' => 'primary',
            'paid' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}
