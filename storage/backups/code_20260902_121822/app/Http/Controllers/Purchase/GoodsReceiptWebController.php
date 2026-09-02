<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\InventoryWarehouse;
use App\Models\PurchaseOrder;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoodsReceiptWebController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly GoodsReceiptService $receipts) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = GoodsReceipt::with(['purchaseOrder','supplier','warehouse'])
            ->where('institute_id', $institute->id)
            ->when($branchId !== null, fn($q)=>$q->where(fn($qq)=>$qq->where('branch_id',$branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function($qq) use ($q){
                $qq->where('receipt_number','like',"%{$q}%")
                   ->orWhereHas('purchaseOrder', fn($c)=>$c->where('order_number','like',"%{$q}%"))
                   ->orWhereHas('supplier', fn($c)=>$c->where('name','like',"%{$q}%"));
            });
        }
        if ($request->filled('status')) $query->where('status',$request->input('status'));
        if ($request->filled('purchase_order_id')) $query->where('purchase_order_id',$request->input('purchase_order_id'));
        if ($request->filled('warehouse_id')) $query->where('warehouse_id',$request->input('warehouse_id'));

        $receipts = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('purchase.goods_receipts.index', [
            'institute'=>$institute,
            'receipts'=>$receipts,
            'statuses'=>GoodsReceipt::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $poId = $request->input('purchase_order_id');
        $purchaseOrder = null;
        $poLines = collect();
        if ($poId) {
            $purchaseOrder = PurchaseOrder::withoutGlobalScopes()->where('id',$poId)->where('institute_id',$institute->id)->first();
            if ($purchaseOrder) {
                if ($branchId !== null && $purchaseOrder->branch_id !== null && (int)$purchaseOrder->branch_id !== (int)$branchId) {
                    $purchaseOrder = null;
                } else {
                    $purchaseOrder->load('lines.inventoryItem','supplier','warehouse');
                    $poLines = $purchaseOrder->lines;
                }
            }
        }

        // Approved POs for dropdown
        $pos = PurchaseOrder::where('institute_id',$institute->id)
            ->whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
            ->when($branchId!==null, fn($q)=>$q->where(fn($qq)=>$qq->where('branch_id',$branchId)->orWhereNull('branch_id')))
            ->orderByDesc('id')->limit(100)->get(['id','order_number','supplier_id','warehouse_id']);

        $warehouses = InventoryWarehouse::where('institute_id',$institute->id)->where('is_active',true)
            ->when($branchId!==null, fn($q)=>$q->where(fn($qq)=>$qq->where('branch_id',$branchId)->orWhereNull('branch_id')))
            ->orderBy('name')->get();

        return view('purchase.goods_receipts.form', [
            'institute'=>$institute,
            'purchaseOrder'=>$purchaseOrder,
            'poLines'=>$poLines,
            'pos'=>$pos,
            'warehouses'=>$warehouses,
            'receipt'=>null,
            'isEdit'=>false,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'purchase_order_id'=>'required|integer|exists:purchase_orders,id',
            'warehouse_id'=>'required|integer|exists:inventory_warehouses,id',
            'receipt_date'=>'nullable|date',
            'notes'=>'nullable|string|max:2000',
            'lines'=>'required|array|min:1',
            'lines.*.purchase_order_line_id'=>'required|integer',
            'lines.*.received_quantity'=>'required|numeric|min:0.0001',
            'lines.*.rejected_quantity'=>'nullable|numeric|min:0',
            'lines.*.unit_cost'=>'nullable|numeric|min:0',
            'lines.*.batch_number'=>'nullable|string|max:80',
            'lines.*.lot_number'=>'nullable|string|max:80',
            'lines.*.expiry_date'=>'nullable|date|after:today',
            'lines.*.manufacture_date'=>'nullable|date|before_or_equal:today',
            'lines.*.serial_numbers'=>'nullable|string|max:1000',
            'lines.*.received_condition'=>'nullable|string|in:good,damaged,expired,quarantine',
            'lines.*.notes'=>'nullable|string|max:500',
        ]);

        // Parse serial_numbers comma-separated to array
        foreach ($data['lines'] as $idx=>$line) {
            if (!empty($line['serial_numbers']) && is_string($line['serial_numbers'])) {
                $data['lines'][$idx]['serial_numbers'] = array_filter(array_map('trim', explode(',', $line['serial_numbers'])));
            }
        }

        $po = PurchaseOrder::withoutGlobalScopes()->where('id',$data['purchase_order_id'])->where('institute_id',$institute->id)->first();
        abort_if(!$po,404);
        if ($branchId!==null && $po->branch_id!==null && (int)$po->branch_id!==(int)$branchId) abort(404);

        // Validate warehouse scope
        $wh = InventoryWarehouse::withoutGlobalScopes()->where('id',$data['warehouse_id'])->where('institute_id',$institute->id)->first();
        abort_if(!$wh,404);
        if ($branchId!==null && $wh->branch_id!==null && (int)$wh->branch_id!==(int)$branchId) abort(404);

        $receipt = $this->receipts->create([
            'purchase_order_id'=>$po->id,
            'supplier_id'=>$po->supplier_id,
            'warehouse_id'=>$wh->id,
            'receipt_date'=>$data['receipt_date'] ?? now()->toDateString(),
            'notes'=>$data['notes'] ?? null,
            'lines'=>$data['lines'],
        ], $institute->id, $branchId, $this->actorId($request));

        return redirect()->route('purchase.receipts.show', $receipt)->with('status', 'Goods Receipt '.$receipt->receipt_number.' created.');
    }

    public function show(Request $request, GoodsReceipt $receipt): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($receipt->institute_id !== $institute->id, 404);
        $branchId = $this->actingBranchId($request);
        if ($branchId!==null && $receipt->branch_id!==null && (int)$receipt->branch_id!==(int)$branchId) abort(404);

        $receipt->load(['purchaseOrder','supplier','warehouse','items.purchaseOrderLine','items.inventoryItem','branch','institute']);

        return view('purchase.goods_receipts.show', [
            'institute'=>$institute,
            'receipt'=>$receipt,
        ]);
    }

    public function confirm(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($receipt->institute_id !== $institute->id, 404);
        $branchId = $this->actingBranchId($request);
        if ($branchId!==null && $receipt->branch_id!==null && (int)$receipt->branch_id!==(int)$branchId) abort(404);

        $this->receipts->confirm($receipt, $this->actorId($request));

        return back()->with('status','Goods receipt confirmed. Stock updated.');
    }

    public function cancel(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($receipt->institute_id !== $institute->id, 404);
        $branchId = $this->actingBranchId($request);
        if ($branchId!==null && $receipt->branch_id!==null && (int)$receipt->branch_id!==(int)$branchId) abort(404);

        $reason = $request->input('cancellation_reason');
        $this->receipts->cancel($receipt, $this->actorId($request), $reason);

        return back()->with('status','Goods receipt cancelled.');
    }

    public function reverse(Request $request, GoodsReceipt $receipt): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($receipt->institute_id !== $institute->id, 404);
        $branchId = $this->actingBranchId($request);
        if ($branchId!==null && $receipt->branch_id!==null && (int)$receipt->branch_id!==(int)$branchId) abort(404);

        $reason = $request->input('reversal_reason','Reversal requested via web');
        $this->receipts->reverse($receipt, $this->actorId($request), $reason);

        return back()->with('status','Goods receipt reversed. Stock reversed.');
    }

    public function print(Request $request, GoodsReceipt $receipt): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($receipt->institute_id !== $institute->id, 404);
        $branchId = $this->actingBranchId($request);
        if ($branchId!==null && $receipt->branch_id!==null && (int)$receipt->branch_id!==(int)$branchId) abort(404);

        $receipt->load(['purchaseOrder','supplier','warehouse','items.purchaseOrderLine','items.inventoryItem','branch','institute']);

        return view('purchase.goods_receipts.print', [
            'institute'=>$institute,
            'receipt'=>$receipt,
        ]);
    }
}
