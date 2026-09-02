<?php

namespace App\Services\Sales;

use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Party;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    // ---- helpers ----
    private function branchScope(Builder $q, ?int $branchId, string $col = 'branch_id'): Builder
    {
        if ($branchId !== null) {
            $q->where(function (Builder $qq) use ($branchId, $col) {
                $qq->where($col, $branchId)->orWhereNull($col);
            });
        }
        return $q;
    }

    private function dateScope(Builder $q, ?string $from, ?string $to, string $col = 'order_date'): Builder
    {
        if ($from) $q->whereDate($col, '>=', $from);
        if ($to) $q->whereDate($col, '<=', $to);
        return $q;
    }

    private function safeDate(?string $d): ?string
    {
        if (!$d) return null;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : null;
    }

    // ---- dashboard ----
    public function dashboard(int $instituteId, ?int $branchId, ?string $from = null, ?string $to = null): array
    {
        $from = $this->safeDate($from);
        $to = $this->safeDate($to);

        $base = SalesOrder::query()->where('institute_id', $instituteId);
        $this->branchScope($base, $branchId, 'branch_id');
        $this->dateScope($base, $from, $to, 'order_date');
        $baseClone = fn() => (clone $base);

        $counts = [
            'total_orders' => $baseClone()->count(),
            'draft' => $baseClone()->where('status','draft')->count(),
            'pending' => $baseClone()->where('status','pending_approval')->count(),
            'posted' => $baseClone()->whereIn('status',['approved','processing','ready_for_delivery','completed'])->count(),
            'cancelled' => $baseClone()->where('status','cancelled')->count(),
            'rejected' => $baseClone()->where('status','rejected')->count(),
        ];

        $sums = $baseClone()->whereNotIn('status',['cancelled','rejected'])->selectRaw('COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount_amount),0) as discount, COALESCE(SUM(tax_amount),0) as tax, COALESCE(SUM(grand_total),0) as grand')->first();
        // posted sums
        $postedSums = $baseClone()->whereIn('status',['approved','processing','ready_for_delivery','completed'])->selectRaw('COALESCE(SUM(grand_total),0) as posted_grand, COALESCE(SUM(discount_amount),0) as posted_discount, COALESCE(SUM(tax_amount),0) as posted_tax')->first();

        // Returns (posted only) - scope via sales_orders branch? returns have no direct order branch? they have branch_id themselves.
        $returnsQ = SalesReturn::query()->where('institute_id',$instituteId)->where('status','posted');
        $this->branchScope($returnsQ,$branchId,'branch_id');
        $this->dateScope($returnsQ,$from,$to,'return_date');
        $returnsAgg = $returnsQ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')->first();

        $totalSales = (float)($sums->grand ?? 0);
        $returnsTotal = (float)($returnsAgg->total ?? 0);
        $netSales = $totalSales - $returnsTotal;
        $discounts = (float)($sums->discount ?? 0);
        $tax = (float)($sums->tax ?? 0);

        // Receivables via invoices linked to sales orders (posted source of truth)
        // Invoices: sales_order_id not null, status != cancelled
        $invBase = Invoice::query()->where('institute_id',$instituteId)->whereNotNull('sales_order_id')->whereNotIn('status',['cancelled'])
            ->whereHas('salesOrder', fn($q)=> $this->branchScope($q,$branchId,'branch_id'))
            ->when($from, fn($q)=> $q->whereDate('created_at','>=',$from))
            ->when($to, fn($q)=> $q->whereDate('created_at','<=',$to));

        $receivables = (float)(clone $invBase)->sum('payable_amount');
        $collection = (float)(clone $invBase)->sum('paid_amount');
        $outstanding = (float)(clone $invBase)->sum('due_amount');

        return [
            'counts' => $counts,
            'totals' => [
                'total_sales' => round($totalSales,2),
                'posted_sales' => round((float)($postedSums->posted_grand ?? 0),2),
                'pending_sales' => round((float)($baseClone()->whereIn('status',['draft','pending_approval'])->sum('grand_total')),2),
                'cancelled_sales' => round((float)($baseClone()->where('status','cancelled')->sum('grand_total')),2),
                'returns_count' => (int)($returnsAgg->cnt ?? 0),
                'returns_total' => round($returnsTotal,2),
                'net_sales' => round($netSales,2),
                'discounts' => round($discounts,2),
                'tax' => round($tax,2),
                'receivables' => round($receivables,2),
                'collection' => round($collection,2),
                'outstanding' => round($outstanding,2),
            ],
            'from' => $from, 'to' => $to,
        ];
    }

    // ---- period reports ----
    public function salesByPeriod(int $instituteId, ?int $branchId, string $group, ?string $from, ?string $to, array $filters = []): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrder::query()->where('institute_id',$instituteId)->whereNotIn('status',['cancelled','rejected']);
        $this->branchScope($q,$branchId,'branch_id');
        $this->dateScope($q,$from,$to,'order_date');
        if (!empty($filters['customer_id'])) $q->where('customer_id',$filters['customer_id']);
        if (!empty($filters['status'])) $q->where('status',$filters['status']);
        if (!empty($filters['branch_id']) && $branchId===null) $q->where('branch_id',$filters['branch_id']);
        if (!empty($filters['salesperson_id'])) $q->where('created_by',$filters['salesperson_id']);

        // product/category filters require join to lines
        if (!empty($filters['product_id']) || !empty($filters['category_id'])) {
            $q->whereHas('lines', function(Builder $qq) use ($filters){
                if (!empty($filters['product_id'])) $qq->where('inventory_item_id',$filters['product_id']);
                if (!empty($filters['category_id'])) {
                    $qq->whereHas('inventoryItem', fn($iii)=> $iii->where('category_id',$filters['category_id']));
                }
            });
        }

        $select = match($group){
            'daily' => "DATE(order_date) as period",
            'weekly' => "YEARWEEK(order_date,1) as period",
            'monthly' => "DATE_FORMAT(order_date,'%Y-%m') as period",
            'yearly' => "YEAR(order_date) as period",
            default => "DATE(order_date) as period",
        };
        $groupBy = $group === 'weekly' ? 'YEARWEEK(order_date,1)' : ($group==='monthly'?"DATE_FORMAT(order_date,'%Y-%m')":($group==='yearly'?"YEAR(order_date)":"DATE(order_date)"));

        return $q->selectRaw("$select, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(discount_amount),0) as discount, COALESCE(SUM(tax_amount),0) as tax")
            ->groupByRaw($groupBy)->orderBy('period')->get();
    }

    public function productWise(int $instituteId, ?int $branchId, ?string $from, ?string $to, array $filters=[]): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrderLine::query()->where('sales_order_lines.institute_id',$instituteId)
            ->join('sales_orders as so','so.id','=','sales_order_lines.order_id')
            ->leftJoin('inventory_items as ii','ii.id','=','sales_order_lines.inventory_item_id')
            ->whereNotIn('so.status',['cancelled','rejected'])
            ->when($branchId!==null, fn($qq)=> $qq->where(function($s) use ($branchId){ $s->where('so.branch_id',$branchId)->orWhereNull('so.branch_id'); }))
            ->when($from, fn($qq)=> $qq->whereDate('so.order_date','>=',$from))
            ->when($to, fn($qq)=> $qq->whereDate('so.order_date','<=',$to))
            ->when(!empty($filters['customer_id']), fn($qq)=> $qq->where('so.customer_id',$filters['customer_id']))
            ->when(!empty($filters['product_id']), fn($qq)=> $qq->where('sales_order_lines.inventory_item_id',$filters['product_id']))
            ->when(!empty($filters['category_id']), fn($qq)=> $qq->where('ii.category_id',$filters['category_id']))
            ->when(!empty($filters['salesperson_id']), fn($qq)=> $qq->where('so.created_by',$filters['salesperson_id']));

        return $q->selectRaw('COALESCE(sales_order_lines.inventory_item_id,0) as product_id, COALESCE(ii.name, sales_order_lines.description) as product_name, COALESCE(ii.sku,"") as sku, SUM(sales_order_lines.quantity) as qty, COALESCE(SUM(sales_order_lines.line_total),0) as total, COUNT(DISTINCT so.id) as orders')
            ->groupBy('sales_order_lines.inventory_item_id','ii.name','ii.sku','sales_order_lines.description')
            ->orderByDesc('total')->get();
    }

    public function categoryWise(int $instituteId, ?int $branchId, ?string $from, ?string $to, array $filters=[]): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrderLine::query()->where('sales_order_lines.institute_id',$instituteId)
            ->join('sales_orders as so','so.id','=','sales_order_lines.order_id')
            ->leftJoin('inventory_items as ii','ii.id','=','sales_order_lines.inventory_item_id')
            ->leftJoin('inventory_categories as ic','ic.id','=','ii.category_id')
            ->whereNotIn('so.status',['cancelled','rejected'])
            ->when($branchId!==null, fn($qq)=> $qq->where(function($s) use ($branchId){ $s->where('so.branch_id',$branchId)->orWhereNull('so.branch_id'); }))
            ->when($from, fn($qq)=> $qq->whereDate('so.order_date','>=',$from))
            ->when($to, fn($qq)=> $qq->whereDate('so.order_date','<=',$to))
            ->when(!empty($filters['customer_id']), fn($qq)=> $qq->where('so.customer_id',$filters['customer_id']));

        return $q->selectRaw('COALESCE(ic.id,0) as category_id, COALESCE(ic.name,"Uncategorized") as category_name, SUM(sales_order_lines.quantity) as qty, COALESCE(SUM(sales_order_lines.line_total),0) as total, COUNT(DISTINCT so.id) as orders')
            ->groupBy('ic.id','ic.name')->orderByDesc('total')->get();
    }

    public function customerWise(int $instituteId, ?int $branchId, ?string $from, ?string $to, array $filters=[]): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrder::query()->where('institute_id',$instituteId)->whereNotIn('status',['cancelled','rejected'])
            ->when($branchId!==null, fn($qq)=>$qq->where(function($s) use ($branchId){ $s->where('branch_id',$branchId)->orWhereNull('branch_id'); }))
            ->when($from, fn($qq)=>$qq->whereDate('order_date','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('order_date','<=',$to))
            ->when(!empty($filters['product_id']), fn($qq)=> $qq->whereHas('lines', fn($l)=> $l->where('inventory_item_id',$filters['product_id'])))
            ->when(!empty($filters['salesperson_id']), fn($qq)=>$qq->where('created_by',$filters['salesperson_id']));

        return $q->selectRaw('customer_id, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(discount_amount),0) as discount, COALESCE(SUM(tax_amount),0) as tax')
            ->groupBy('customer_id')->with('customer')->orderByDesc('total')->get()->map(function($row){
                $row->customer_name = $row->customer?->name ?? 'Unknown';
                return $row;
            });
    }

    public function salespersonWise(int $instituteId, ?int $branchId, ?string $from, ?string $to): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrder::query()->where('institute_id',$instituteId)->whereNotIn('status',['cancelled','rejected'])
            ->when($branchId!==null, fn($qq)=>$qq->where(function($s) use ($branchId){ $s->where('branch_id',$branchId)->orWhereNull('branch_id'); }))
            ->when($from, fn($qq)=>$qq->whereDate('order_date','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('order_date','<=',$to));
        return $q->selectRaw('created_by as salesperson_id, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as total')->groupBy('created_by')->orderByDesc('total')->get();
    }

    public function branchWise(int $instituteId, ?int $branchId, ?string $from, ?string $to): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesOrder::query()->where('institute_id',$instituteId)->whereNotIn('status',['cancelled','rejected']);
        $this->branchScope($q,$branchId,'branch_id');
        $this->dateScope($q,$from,$to,'order_date');
        return $q->selectRaw('branch_id, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as total')->groupBy('branch_id')->with('branch')->orderByDesc('total')->get();
    }

    public function warehouseWise(int $instituteId, ?int $branchId, ?string $from, ?string $to): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesDelivery::query()->where('sales_deliveries.institute_id',$instituteId)->where('sales_deliveries.status','confirmed')
            ->when($branchId!==null, fn($qq)=>$qq->where(function($s) use ($branchId){ $s->where('sales_deliveries.branch_id',$branchId)->orWhereNull('sales_deliveries.branch_id'); }))
            ->when($from, fn($qq)=>$qq->whereDate('delivery_date','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('delivery_date','<=',$to))
            ->join('sales_delivery_lines as sdl','sdl.delivery_id','=','sales_deliveries.id')
            ->join('sales_order_lines as sol','sol.id','=','sdl.order_line_id');
        return $q->selectRaw('sales_deliveries.warehouse_id, COALESCE(SUM(sdl.delivery_quantity),0) as qty, COALESCE(SUM(sol.line_total * sdl.delivery_quantity / NULLIF(sol.quantity,0)),0) as total, COUNT(DISTINCT sales_deliveries.id) as deliveries')
            ->groupBy('sales_deliveries.warehouse_id')->with('warehouse')->orderByDesc('total')->get();
    }

    public function returnsReport(int $instituteId, ?int $branchId, ?string $from, ?string $to, array $filters=[]): Collection
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        $q = SalesReturn::query()->where('institute_id',$instituteId)->where('status','posted');
        $this->branchScope($q,$branchId,'branch_id');
        $this->dateScope($q,$from,$to,'return_date');
        if (!empty($filters['customer_id'])) $q->where('customer_id',$filters['customer_id']);
        return $q->selectRaw('DATE(return_date) as period, COUNT(*) as count, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(refunded_amount),0) as refunded')->groupByRaw('DATE(return_date)')->orderBy('period')->get();
    }

    public function returnsDetail(int $instituteId, ?int $branchId, array $filters=[], int $perPage=20): LengthAwarePaginator
    {
        $q = SalesReturn::query()->where('institute_id',$instituteId)->where('status','posted')
            ->with(['customer','invoice','items']);
        $this->branchScope($q,$branchId,'branch_id');
        if (!empty($filters['from'])) $q->whereDate('return_date','>=',$filters['from']);
        if (!empty($filters['to'])) $q->whereDate('return_date','<=',$filters['to']);
        if (!empty($filters['customer_id'])) $q->where('customer_id',$filters['customer_id']);
        return $q->orderByDesc('return_date')->paginate($perPage);
    }

    // ---- customer statement ----
    public function customerStatement(int $instituteId, ?int $branchId, int $customerId, ?string $from=null, ?string $to=null): array
    {
        $from=$this->safeDate($from); $to=$this->safeDate($to);
        // Opening balance = sum payable - paid before from (receivable derived from invoices) - sales returns credit
        // Note: invoices.sales_order_id branch scoping skipped for statement — statement is per customer across branch visibility already filtered by party branch scope externally
        $invBefore = Invoice::query()->where('institute_id',$instituteId)->where('party_id',$customerId)->whereNotIn('status',['cancelled'])->whereDate('created_at','<',$from ?? '9999-12-31')
            ->when($from, fn($qq)=>$qq->whereDate('created_at','<',$from));
        // If no from, opening 0
        $opening = 0;
        if ($from) {
            $payableBefore = (float)(clone $invBefore)->sum('payable_amount');
            $paidBefore = (float)(clone $invBefore)->sum('paid_amount');
            $opening = round($payableBefore - $paidBefore,2);
            // subtract returns before from (credit)
            $returnsBefore = SalesReturn::query()->where('institute_id',$instituteId)->where('customer_id',$customerId)->where('status','posted')->whereDate('return_date','<',$from);
            $this->branchScope($returnsBefore,$branchId,'branch_id');
            $opening -= round((float)$returnsBefore->sum('grand_total'),2);
        }

        // Invoices in range
        $invQ = Invoice::query()->where('institute_id',$instituteId)->where('party_id',$customerId)->whereNotIn('status',['cancelled'])
            ->when($from, fn($qq)=>$qq->whereDate('created_at','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('created_at','<=',$to));
        $invoices = $invQ->orderBy('created_at')->get(['id','invoice_number','created_at','payable_amount','paid_amount','due_amount','status','sales_order_id']);

        // Payments in range via invoices of customer
        $payQ = \App\Models\Payment::query()->where('institute_id',$instituteId)->where('party_id',$customerId)
            ->whereHas('invoice', fn($qq)=> $qq->where('party_id',$customerId))
            ->when($from, fn($qq)=>$qq->whereDate('paid_at','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('paid_at','<=',$to));
        $payments = $payQ->orderBy('paid_at')->get();

        // Returns in range
        $retQ = SalesReturn::query()->where('institute_id',$instituteId)->where('customer_id',$customerId)->where('status','posted')
            ->when($from, fn($qq)=>$qq->whereDate('return_date','>=',$from))
            ->when($to, fn($qq)=>$qq->whereDate('return_date','<=',$to));
        $this->branchScope($retQ,$branchId,'branch_id');
        $returns = $retQ->orderBy('return_date')->get();

        // Merge timeline for running balance
        $entries = collect();
        foreach ($invoices as $inv) {
            $entries->push(['date'=>$inv->created_at,'type'=>'invoice','ref'=>$inv->invoice_number,'debit'=> (float)$inv->payable_amount,'credit'=>0,'balance'=>0,'model'=>$inv]);
        }
        foreach ($payments as $pay) {
            $entries->push(['date'=>$pay->paid_at,'type'=>'payment','ref'=>'Payment #'.$pay->id,'debit'=>0,'credit'=> (float)$pay->amount,'balance'=>0,'model'=>$pay]);
        }
        foreach ($returns as $ret) {
            $entries->push(['date'=>$ret->return_date,'type'=>'credit_note','ref'=>$ret->credit_note_number ?? $ret->return_number,'debit'=>0,'credit'=> (float)$ret->grand_total,'balance'=>0,'model'=>$ret]);
            if ((float)$ret->refunded_amount > 0) {
                $entries->push(['date'=>$ret->return_date,'type'=>'refund','ref'=>'Refund '.$ret->return_number,'debit'=>0,'credit'=> (float)$ret->refunded_amount,'balance'=>0,'model'=>$ret]);
            }
        }
        $entries = $entries->sortBy('date')->values();
        $running = $opening;
        $entries = $entries->map(function($e) use (&$running) {
            $running = round($running + $e['debit'] - $e['credit'],2);
            $e['balance'] = $running;
            return $e;
        })->values();
        $closing = round($running,2);

        return [
            'opening' => round($opening,2),
            'closing' => $closing,
            'entries' => $entries,
            'invoices' => $invoices,
            'payments' => $payments,
            'returns' => $returns,
        ];
    }

    // ---- detail paginated sales list for exports ----
    public function salesList(int $instituteId, ?int $branchId, array $filters=[], int $perPage=20): LengthAwarePaginator
    {
        $q = SalesOrder::query()->where('institute_id',$instituteId)->whereNotIn('status',['cancelled','rejected'])
            ->with(['customer','currency','branch']);
        $this->branchScope($q,$branchId,'branch_id');
        if (!empty($filters['from'])) $q->whereDate('order_date','>=',$filters['from']);
        if (!empty($filters['to'])) $q->whereDate('order_date','<=',$filters['to']);
        if (!empty($filters['customer_id'])) $q->where('customer_id',$filters['customer_id']);
        if (!empty($filters['status'])) $q->where('status',$filters['status']);
        if (!empty($filters['product_id'])) $q->whereHas('lines', fn($qq)=> $qq->where('inventory_item_id',$filters['product_id']));
        if (!empty($filters['category_id'])) $q->whereHas('lines.inventoryItem', fn($qq)=> $qq->where('category_id',$filters['category_id']));
        if (!empty($filters['salesperson_id'])) $q->where('created_by',$filters['salesperson_id']);
        if (!empty($filters['branch_id']) && $branchId===null) $q->where('branch_id',$filters['branch_id']);
        if (!empty($filters['payment_status'])) {
            // payment_status via invoices: unpaid/partial/paid derived from invoices due
            $q->whereHas('invoices', fn($qq)=> $qq->where('status',$filters['payment_status']));
        }
        return $q->orderByDesc('order_date')->paginate($perPage);
    }
}
