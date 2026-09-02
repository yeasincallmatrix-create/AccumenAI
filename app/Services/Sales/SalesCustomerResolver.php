<?php

namespace App\Services\Sales;

use App\Models\CrmContact;
use App\Models\CrmOrganization;
use App\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * S-2 Customer integration — reuses Party as financial source of truth,
 * enriches with CRM Contact/Organization when available.
 * No duplicate customer table.
 */
final class SalesCustomerResolver
{
    /**
     * Resolve a Party customer by ID with strict tenant/branch checks.
     * Throws 404 if not found or out of scope (prevents ID bypass).
     */
    public function resolve(int $instituteId, ?int $branchId, int $partyId): Party
    {
        $party = Party::withoutGlobalScopes()
            ->where('id', $partyId)
            ->where('institute_id', $instituteId)
            ->first();

        if (! $party) {
            abort(404, 'Customer not found.');
        }

        // Branch check: branch-restricted users can only select branch-visible parties
        if ($branchId !== null) {
            // Party is visible if branch_id is null (shared) or matches acting branch
            if ($party->branch_id !== null && (int) $party->branch_id !== (int) $branchId) {
                abort(404, 'Customer not found.');
            }
        }

        if (! $party->isCustomer()) {
            throw ValidationException::withMessages(['customer' => 'Selected party is not a customer.']);
        }

        if (! $party->is_active) {
            throw ValidationException::withMessages(['customer' => 'Selected customer is inactive.']);
        }

        return $party;
    }

    /**
     * Search customers (Party) — text search, code/sku search (for Party code/phone), pagination, tenant+branch scoped.
     */
    public function search(int $instituteId, ?int $branchId, ?string $search, int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $q = Party::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->whereIn('type', ['customer', 'both'])
            ->where('is_active', true);

        // Branch visibility: shared (null) or matching branch
        if ($branchId !== null) {
            $q->where(function (Builder $query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        if (filled($filters['customer_type'] ?? null)) {
            $q->where('type', $filters['customer_type']);
        }

        if (filled($search)) {
            $like = '%'.trim($search).'%';
            $q->where(function (Builder $query) use ($like) {
                $query->where('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('tin', 'like', $like);
            });
        }

        $q->orderBy('name');

        return $q->paginate($perPage);
    }

    /**
     * Enriched customer payload — Party + linked CRM Contact/Organization (matched by email/phone when explicit link not present).
     * Does not create duplicate records.
     */
    public function enriched(Party $party): array
    {
        $crmContact = null;
        $crmOrganization = null;

        // Try explicit link via party_meta if present (future-proof)
        $meta = $party->party_meta ?? [];
        if (! empty($meta['crm_contact_id'])) {
            $crmContact = CrmContact::withoutGlobalScopes()->where('institute_id', $party->institute_id)->where('id', $meta['crm_contact_id'])->first();
        }
        if (! empty($meta['crm_organization_id'])) {
            $crmOrganization = CrmOrganization::withoutGlobalScopes()->where('institute_id', $party->institute_id)->where('id', $meta['crm_organization_id'])->first();
        }

        // Fallback: match by email/phone within same institute (non-authoritative, for display only)
        if (! $crmContact && filled($party->email)) {
            $crmContact = CrmContact::withoutGlobalScopes()
                ->where('institute_id', $party->institute_id)
                ->where('email', $party->email)
                ->first();
        }
        if (! $crmContact && filled($party->phone)) {
            $crmContact = CrmContact::withoutGlobalScopes()
                ->where('institute_id', $party->institute_id)
                ->where('phone', $party->phone)
                ->first();
        }

        if ($crmContact && $crmContact->organization_id) {
            $crmOrganization = CrmOrganization::withoutGlobalScopes()->where('id', $crmContact->organization_id)->first();
        }

        return [
            'party' => $party,
            'crm_contact' => $crmContact,
            'crm_organization' => $crmOrganization,
            'billing' => [
                'name' => $party->name,
                'phone' => $party->phone,
                'email' => $party->email,
                'address' => $party->address,
                'tin' => $party->tin,
                'currency_id' => $party->billing_currency_id,
                'credit_limit' => $party->credit_limit,
            ],
            'contact' => $crmContact ? [
                'name' => $crmContact->displayName(),
                'email' => $crmContact->email,
                'phone' => $crmContact->phone,
                'whatsapp' => $crmContact->whatsapp,
                'address' => trim(implode(', ', array_filter([$crmContact->address_line1, $crmContact->city, $crmContact->state]))),
            ] : null,
        ];
    }

    /**
     * Search CRM contacts/organizations as alternative customer source (for selector that shows CRM-linked options).
     * Returns paginated CRM contacts/organizations that could be converted to Party.
     */
    public function searchCrm(int $instituteId, ?int $branchId, ?string $search, int $perPage = 15): LengthAwarePaginator
    {
        // Search CRM contacts that are customers
        $q = CrmContact::withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('is_customer', true);

        if ($branchId !== null) {
            $q->where(function (Builder $query) use ($branchId) {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        if (filled($search)) {
            $like = '%'.trim($search).'%';
            $q->where(function (Builder $query) use ($like) {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        $q->orderBy('first_name')->orderBy('last_name');

        return $q->paginate($perPage);
    }
}
