<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Sales\SalesCatalogService;
use App\Services\Sales\SalesCustomerResolver;
use Illuminate\Http\Request;

class SalesLookupController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly SalesCustomerResolver $customers,
        private readonly SalesCatalogService $catalog,
    ) {}

    /**
     * Customer search — Party customers with optional CRM enrichment.
     * Supports text search, pagination, tenant+branch scoped.
     */
    public function customers(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'customer_type' => ['nullable', 'in:customer,both'],
        ]);

        $paginator = $this->customers->search(
            $institute->id,
            $branchId,
            $request->query('q'),
            (int) ($request->query('per_page', 15)),
            $request->only(['customer_type'])
        );

        // Transform for selector — party + enriched CRM
        $data = $paginator->getCollection()->map(function ($party) {
            $enriched = $this->customers->enriched($party);
            return [
                'id' => $party->id,
                'name' => $party->name,
                'type' => $party->type,
                'phone' => $party->phone,
                'email' => $party->email,
                'tin' => $party->tin,
                'branch_id' => $party->branch_id,
                'is_active' => $party->is_active,
                'billing_currency_id' => $party->billing_currency_id,
                'credit_limit' => $party->credit_limit,
                'crm_contact' => $enriched['crm_contact'] ? [
                    'id' => $enriched['crm_contact']->id,
                    'name' => $enriched['crm_contact']->displayName(),
                ] : null,
                'crm_organization' => $enriched['crm_organization'] ? [
                    'id' => $enriched['crm_organization']->id,
                    'name' => $enriched['crm_organization']->name,
                ] : null,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Resolve single customer — strict tenant/branch check.
     */
    public function customerShow(Request $request, int $party)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $resolved = $this->customers->resolve($institute->id, $branchId, $party);
        $enriched = $this->customers->enriched($resolved);

        return response()->json(['data' => [
            'id' => $resolved->id,
            'name' => $resolved->name,
            'type' => $resolved->type,
            'phone' => $resolved->phone,
            'email' => $resolved->email,
            'address' => $resolved->address,
            'tin' => $resolved->tin,
            'branch_id' => $resolved->branch_id,
            'billing' => $enriched['billing'],
            'contact' => $enriched['contact'],
            'crm_contact' => $enriched['crm_contact'] ? ['id' => $enriched['crm_contact']->id, 'name' => $enriched['crm_contact']->displayName()] : null,
            'crm_organization' => $enriched['crm_organization'] ? ['id' => $enriched['crm_organization']->id, 'name' => $enriched['crm_organization']->name] : null,
        ]]);
    }

    /**
     * CRM-linked customer search — alternative source (CrmContact customers).
     */
    public function crmCustomers(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->customers->searchCrm(
            $institute->id,
            $branchId,
            $request->query('q'),
            (int) ($request->query('per_page', 15))
        );

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->displayName(),
                'email' => $c->email,
                'phone' => $c->phone,
                'branch_id' => $c->branch_id,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Item search — inventory products and non-stock services.
     */
    public function items(Request $request)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'item_type' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer'],
            'stockable' => ['nullable', 'boolean'],
        ]);

        $filters = $request->only(['item_type', 'category_id', 'stockable']);
        // Cast stockable boolean string to bool if present
        if ($request->has('stockable')) {
            $filters['stockable'] = $request->boolean('stockable');
        }

        $paginator = $this->catalog->search(
            $institute->id,
            $branchId,
            $request->query('q'),
            (int) ($request->query('per_page', 15)),
            $filters
        );

        return response()->json([
            'data' => $paginator->getCollection()->map(fn ($item) => [
                'id' => $item->id,
                'sku' => $item->sku,
                'barcode' => $item->barcode,
                'name' => $item->name,
                'item_type' => $item->item_type,
                'is_stockable' => in_array($item->item_type, \App\Services\Sales\SalesCatalogService::STOCKABLE_TYPES, true),
                'unit' => $item->unit,
                'selling_price' => $item->selling_price,
                'category_id' => $item->category_id,
                'tax_group_id' => $item->tax_group_id,
                'branch_id' => $item->branch_id,
                'is_active' => $item->is_active,
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * Item availability — reusable for Sales to determine price, tax, stock, branch.
     */
    public function itemAvailability(Request $request, int $item)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $request->validate([
            'qty' => ['nullable', 'numeric', 'min:0'],
        ]);

        $qty = (float) ($request->query('qty', 1));

        $availability = $this->catalog->availability($institute->id, $branchId, $item, $qty);

        return response()->json(['data' => [
            'identity' => $availability['identity'],
            'type' => $availability['type'],
            'is_stockable' => $availability['is_stockable'],
            'is_service' => $availability['is_service'],
            'selling_price' => $availability['selling_price'],
            'unit' => $availability['unit'],
            'category_id' => $availability['category_id'],
            'tax' => $availability['tax'],
            'discount_eligible' => $availability['discount_eligible'],
            'branch_available' => $availability['branch_available'],
            'stock' => $availability['stock'],
        ]]);
    }

    /**
     * Resolve single item — strict check.
     */
    public function itemShow(Request $request, int $item)
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $resolved = $this->catalog->resolve($institute->id, $branchId, $item);

        return response()->json(['data' => [
            'id' => $resolved->id,
            'sku' => $resolved->sku,
            'name' => $resolved->name,
            'item_type' => $resolved->item_type,
            'is_stockable' => in_array($resolved->item_type, \App\Services\Sales\SalesCatalogService::STOCKABLE_TYPES, true),
            'unit' => $resolved->unit,
            'selling_price' => $resolved->selling_price,
            'category_id' => $resolved->category_id,
            'tax_group_id' => $resolved->tax_group_id,
            'branch_id' => $resolved->branch_id,
        ]]);
    }
}
