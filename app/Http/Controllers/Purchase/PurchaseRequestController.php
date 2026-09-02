<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Services\Purchase\PurchaseNumberingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PurchaseRequestController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly PurchaseNumberingService $numberingService,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = PurchaseRequest::with(['requester', 'warehouse', 'currency'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('request_number', 'like', "%{$q}%")
                    ->orWhereHas('requester', fn ($u) => $u->where('first_name', 'like', "%{$q}%")->orWhere('last_name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('request_date', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('request_date', '<=', $request->input('to'));
        }

        $requests = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.requests.index', [
            'institute' => $institute,
            'requests' => $requests,
            'statuses' => PurchaseRequest::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('purchase.requests.create', [
            'institute' => $institute,
            'request' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'request_date' => ['required', 'date'],
            'required_by_date' => ['nullable', 'date', 'after_or_equal:request_date'],
            'warehouse_id' => ['nullable', 'integer', 'exists:inventory_warehouses,id'],
            'justification' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['nullable', 'integer', 'exists:inventory_items,id'],
            'lines.*.description' => ['required', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['nullable', 'string', 'max:30'],
            'lines.*.estimated_unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchaseRequest = DB::transaction(function () use ($institute, $branchId, $data, $request) {
            $requestNumber = $this->numberingService->nextNumber($institute->id, $branchId, 'order');

            $estimatedTotal = collect($data['lines'])->reduce(function ($carry, $line) {
                return $carry + ($line['quantity'] * ($line['estimated_unit_price'] ?? 0));
            }, 0);

            $pr = PurchaseRequest::create([
                'institute_id' => $institute->id,
                'branch_id' => $branchId,
                'request_number' => $requestNumber,
                'requester_id' => $this->actorId($request),
                'request_date' => $data['request_date'],
                'required_by_date' => $data['required_by_date'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'justification' => $data['justification'] ?? null,
                'notes' => $data['notes'] ?? null,
                'estimated_total' => $estimatedTotal,
                'status' => PurchaseRequest::STATUS_DRAFT,
                'created_by' => $this->actorId($request),
            ]);

            foreach ($data['lines'] as $index => $line) {
                $lineTotal = $line['quantity'] * ($line['estimated_unit_price'] ?? 0);
                $pr->lines()->create([
                    'institute_id' => $institute->id,
                    'inventory_item_id' => $line['inventory_item_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'] ?? null,
                    'estimated_unit_price' => $line['estimated_unit_price'] ?? 0,
                    'line_total' => $lineTotal,
                    'sort_order' => $index,
                ]);
            }

            return $pr;
        });

        $this->notifyAdmins($purchaseRequest, $request, 'created');

        return redirect()->route('purchase.requests.show', $purchaseRequest)->with('status', 'Purchase Request '.$purchaseRequest->request_number.' created.');
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($purchaseRequest->institute_id !== $institute->id, 404);

        $purchaseRequest->load(['requester', 'warehouse', 'currency', 'lines.inventoryItem', 'convertedOrder', 'branch']);

        return view('purchase.requests.show', [
            'institute' => $institute,
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($purchaseRequest->institute_id !== $institute->id, 404);

        abort_unless($purchaseRequest->canApprove(), 422, 'Only submitted requests can be approved.');

        $purchaseRequest->update([
            'status' => PurchaseRequest::STATUS_APPROVED,
            'approved_by' => $this->actorId($request),
            'approved_at' => now(),
            'updated_by' => $this->actorId($request),
        ]);

        $this->notifyAdmins($purchaseRequest, $request, 'approved');

        return back()->with('status', 'Purchase Request '.$purchaseRequest->request_number.' approved.');
    }

    public function convertToOrder(Request $request, PurchaseRequest $purchaseRequest): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($purchaseRequest->institute_id !== $institute->id, 404);

        abort_unless($purchaseRequest->canConvert(), 422, 'Only approved requests can be converted to a Purchase Order.');

        $orderService = app(\App\Services\Purchase\PurchaseOrderService::class);

        $order = DB::transaction(function () use ($purchaseRequest, $orderService, $institute, $request) {
            $lines = $purchaseRequest->lines->map(fn ($item) => [
                'inventory_item_id' => $item->inventory_item_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->estimated_unit_price,
            ])->toArray();

            $order = $orderService->create([
                'supplier_id' => null,
                'warehouse_id' => $purchaseRequest->warehouse_id,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => $purchaseRequest->required_by_date?->toDateString(),
                'currency_id' => $purchaseRequest->currency_id,
                'notes' => $purchaseRequest->notes,
                'lines' => $lines,
            ], $institute->id, $purchaseRequest->branch_id, $this->actorId($request));

            $purchaseRequest->update([
                'status' => PurchaseRequest::STATUS_CONVERTED,
                'converted_order_id' => $order->id,
                'converted_by' => $this->actorId($request),
                'converted_at' => now(),
                'updated_by' => $this->actorId($request),
            ]);

            return $order;
        });

        $this->notifyAdmins($purchaseRequest->refresh(), $request, 'converted');

        return redirect()->route('purchase.orders.show', $order)->with('status', 'Purchase Order '.$order->order_number.' created from request '.$purchaseRequest->request_number.'.');
    }

    private function notifyAdmins(PurchaseRequest $pr, Request $request, string $action): void
    {
        try {
            $titles = [
                'created' => 'Purchase request created',
                'approved' => 'Purchase request approved',
                'converted' => 'Purchase request converted',
            ];
            $messages = [
                'created' => "Purchase Request {$pr->request_number} was created.",
                'approved' => "Purchase Request {$pr->request_number} was approved.",
                'converted' => "Purchase Request {$pr->request_number} was converted to Purchase Order.",
            ];

            Notification::create([
                'scope' => 'institute',
                'institute_id' => $pr->institute_id,
                'category' => 'purchase_request',
                'title' => $titles[$action] ?? 'Purchase request updated',
                'message' => $messages[$action] ?? "Purchase Request {$pr->request_number} updated ({$action}).",
                'link_url' => route('purchase.requests.show', $pr),
                'created_by_type' => 'institute_user',
                'created_by_id' => $this->actorId($request),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('notification.purchase_request_admin_failed', ['error' => $e->getMessage(), 'action' => $action]);
        }
    }
}
