<?php

namespace App\Services\Purchase;

use App\Models\Party;
use App\Support\TenantContext;
use App\Support\BranchContext;
use App\Services\HrAuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Supplier (Vendor) management service.
 *
 * Wraps the existing Party model (type = 'supplier') to provide CRUD,
 * search/filter/pagination, tenant/branch isolation and audit logging.
 * No duplicate accounting identity — reuses Party with type='supplier'.
 */
class SupplierService
{
    protected HrAuditService $audit;

    public function __construct(HrAuditService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Default config for purchase settings.
     */
    protected function defaults(): array
    {
        return [
            'enabled' => true,
        ];
    }

    /** ---------- READ ---------- */

    public function get(int $partyId, ?int $actorId = null): ?array
    {
        $instituteId = TenantContext::id() ?? 0;
        $party = Party::where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->first();

        if (! $party) {
            return null;
        }

        $actorId = $actorId ?? auth()->id() ?? null;
        if ($actorId !== null) {
            $this->audit->record(
                $instituteId,
                $actorId,
                'supplier_viewed',
                $party->id,
                null,
                ['id' => $party->id, 'name' => $party->name, 'type' => $party->type]
            );
        }

        return $this->format($party);
    }

    public function index(): array
    {
        $instituteId = TenantContext::id() ?? 0;

        $query = Party::where('institute_id', $instituteId)
            ->where('type', 'supplier');

        // Branch isolation is handled by Party::BranchScopedOrShared global scope.
        // Do not add manual branch filter here; rely on global scope for null-or-branch logic.
        // Optional explicit branch filter via query param for admin/owner manual filtering:
        $filterBranch = Request::get('branch_id');
        if ($filterBranch !== null && $filterBranch !== '') {
            $query->where('branch_id', (int) $filterBranch);
        }

        // Filters
        $search = Request::get('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('tin', 'like', "%{$search}%");
            });
        }

        $status = Request::get('status');
        if ($status !== null) {
            $query->where('is_active', $status === 'active');
        }

        // Pagination
        $perPage = (int) Request::get('per_page', 15);
        $perPage = max(1, min(100, $perPage));
        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $items = $query->forPage($page, $perPage)->get();
        $total = $query->count();

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );

        return [
            'suppliers' => $items->map(fn ($p) => $this->format($p)),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function show(int $partyId, ?int $actorId = null): ?array
    {
        return $this->get($partyId, $actorId);
    }

    /** alias for test expectation that service exposes store() */
    public function store(array $data, ?int $actorId = null): int
    {
        return $this->create($data, $actorId);
    }

    /** ---------- CREATE ---------- */

    public function create(array $data, ?int $actorId = null): int
    {
        $instituteId = TenantContext::id() ?? 0;

        // Build party data - institute_id set from TenantContext (session), NOT from request input.
        // branch_id is derived from BranchContext when enabled to enforce branch isolation.
        $partyData = [
            'institute_id' => $instituteId,
            'type' => 'supplier',
            'is_active' => true,
            'name' => $data['name'] ?? '',
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'address' => $data['address'] ?? '',
            'tin' => $data['tin'] ?? '',
            'branch_id' => BranchContext::enabled() ? BranchContext::id() : ($data['branch_id'] ?? null),
        ];

        // Prevent spoofing: institute_id always from context, branch_id already resolved above
        unset($data['institute_id'], $data['branch_id']);
        $partyData['institute_id'] = $instituteId;

        $party = Party::create($partyData);

        $this->audit->record(
            $instituteId,
            $actorId,
            'supplier_created',
            $party->id,
            null,
            $this->format($party)
        );

        return $party->id;
    }

    /** ---------- UPDATE ---------- */

    public function update(int $partyId, array $data, ?int $actorId = null): bool
    {
        $instituteId = TenantContext::id() ?? 0;

        $party = Party::where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->first();

        if (! $party) {
            return false;
        }

        // Remove institute_id/branch_id from input to prevent spoofing
        unset($data['institute_id'], $data['branch_id']);

        $old = $this->format($party);

        $party->fill($data)->save();

        $this->audit->record(
            $instituteId,
            $actorId,
            'supplier_updated',
            $party->id,
            $old,
            $this->format($party)
        );

        return true;
    }

    /** ---------- SOFT DELETE ---------- */

    public function softDelete(int $partyId, ?int $actorId = null): bool
    {
        $instituteId = TenantContext::id() ?? 0;

        $party = Party::where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->first();

        if (! $party) {
            return false;
        }

        $old = $this->format($party);

        $party->delete();  // soft delete via SoftDeletes trait

        $this->audit->record(
            $instituteId,
            $actorId,
            'supplier_deleted',
            $party->id,
            $old,
            null
        );

        return true;
    }

    /** ---------- RESTORE ---------- */

    public function restore(int $partyId, ?int $actorId = null): bool
    {
        $instituteId = TenantContext::id() ?? 0;

        $party = Party::withTrashed()
            ->where('institute_id', $instituteId)
            ->where('id', $partyId)
            ->first();

        if (! $party) {
            return false;
        }

        $party->restore();

        $this->audit->record(
            $instituteId,
            $actorId,
            'supplier_restored',
            $party->id,
            null,
            $this->format($party)
        );

        return true;
    }

    /** ---------- FORMAT ---------- */

    protected function format(Party $party): array
    {
        return [
            'id' => $party->id,
            'name' => $party->name,
            'type' => $party->type,
            'is_active' => $party->is_active,
            'phone' => $party->phone,
            'email' => $party->email,
            'address' => $party->address,
            'tin' => $party->tin,
            'credit_limit' => $party->credit_limit,
            'party_meta' => $party->party_meta,
            'branch_id' => $party->branch_id,
            'created_at' => $party->created_at,
            'updated_at' => $party->updated_at,
        ];
    }
}