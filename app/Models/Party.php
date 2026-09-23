<?php

namespace App\Models;

use App\Models\Concerns\BranchScopedOrShared;
use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unified customer / supplier / both party used for AR and AP. Balances are
 * derived from journal lines (derive mode), so no balance columns exist here.
 */
class Party extends Model
{
    use BranchScopedOrShared;
    use HasFactory;
    use SoftDeletes;
    use TenantScoped;

    protected $table = 'parties';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'is_active' => 'boolean',
            'is_customer' => 'boolean',
            'is_vendor' => 'boolean',
            'party_meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Party $party): void {
            if ($party->isDirty('type') && !$party->isDirty('is_customer') && !$party->isDirty('is_vendor')) {
                $party->is_customer = in_array($party->type, ['customer', 'both'], true);
                $party->is_vendor = in_array($party->type, ['supplier', 'both'], true);
            }

            if ($party->is_customer && $party->is_vendor) {
                $party->party_type = 'both';
                $party->type = 'both';
            } elseif ($party->is_vendor) {
                $party->party_type = 'vendor';
                $party->type = 'supplier';
            } else {
                $party->party_type = 'customer';
                $party->type = 'customer';
            }
        });
    }

    public function scopeCustomers($query)
    {
        return $query->where('is_customer', true);
    }

    public function scopeSuppliers($query)
    {
        return $query->where('is_vendor', true);
    }

    public function scopeVendors($query)
    {
        return $this->scopeSuppliers($query);
    }

    public function scopeBoth($query)
    {
        return $query->where('is_customer', true)
            ->where('is_vendor', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDisplayTypeAttribute(): string
    {
        return match ($this->party_type) {
            'customer' => 'Customer',
            'vendor' => 'Vendor',
            'both' => 'Customer & Vendor',
            default => 'Contact',
        };
    }

    public function getBadgeColorAttribute(): string
    {
        return match ($this->party_type) {
            'customer' => 'primary',
            'vendor' => 'success',
            'both' => 'warning',
            default => 'secondary',
        };
    }

    public function canBeCustomer(): bool
    {
        return (bool) $this->is_customer;
    }

    public function canBeVendor(): bool
    {
        return (bool) $this->is_vendor;
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customerGroup(): BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function billingCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'billing_currency_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(InstituteUser::class, 'updated_by');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'party_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'party_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'party_id');
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(SalesQuotation::class, 'customer_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'customer_id');
    }

    public function isCustomer(): bool
    {
        return in_array($this->type, ['customer', 'both'], true);
    }

    public function isSupplier(): bool
    {
        return in_array($this->type, ['supplier', 'both'], true);
    }
}
