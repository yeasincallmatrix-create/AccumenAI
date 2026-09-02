<?php

namespace App\Services\Purchase;

use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Models\Party;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseSupplierPayment;
use App\Services\Accounting\ReceivablesPayablesService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseReportService
{
    public function __construct(
        private readonly ReceivablesPayablesService $payables,
    ) {}

    private function branchScope(int $instituteId, ?int $actingBranchId, ?int $filterBranchId = null): ?int
    {
        // Never trust branch_id from input if actor is branch-restricted
        if ($actingBranchId !== null) {
            return $actingBranchId;
        }

        return $filterBranchId;
    }

    private function invoiceQuery(int $instituteId, ?int $actingBranchId, array $filters = [])
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = PurchaseInvoice::query()->where('institute_id', $instituteId);
        if ($branchId !== null) {
            $q->where(function ($qq) use ($branchId) {
                $qq->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        } elseif (isset($filters['branch_id']) && $filters['branch_id'] !== '') {
            $q->where('branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['product_id'])) {
            $q->whereHas('items', fn ($qq) => $qq->where('inventory_item_id', (int) $filters['product_id']));
        }
        if (! empty($filters['category_id'])) {
            $q->whereHas('items.inventoryItem', fn ($qq) => $qq->where('category_id', (int) $filters['category_id']));
        }
        if (! empty($filters['warehouse_id'])) {
            // invoices don't have warehouse directly, join via goods_receipt or PO
            $q->where(function ($qq) use ($filters) {
                $qq->whereHas('goodsReceipt', fn ($g) => $g->where('warehouse_id', (int) $filters['warehouse_id']))
                    ->orWhereHas('purchaseOrder', fn ($p) => $p->where('warehouse_id', (int) $filters['warehouse_id']));
            });
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        } else {
            // by default exclude cancelled/reversed for totals? caller can override
        }
        if (! empty($filters['payment_status'])) {
            $ps = $filters['payment_status'];
            if ($ps === 'paid') {
                $q->whereColumn('paid_amount', '>=', 'grand_total')->where('grand_total', '>', 0);
            } elseif ($ps === 'unpaid') {
                $q->where('paid_amount', 0);
            } elseif ($ps === 'partially_paid') {
                $q->where('paid_amount', '>', 0)->whereColumn('paid_amount', '<', 'grand_total');
            }
        }
        if (! empty($filters['from'])) {
            $q->whereDate('invoice_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('invoice_date', '<=', $filters['to']);
        }

        return $q;
    }

    private function returnQuery(int $instituteId, ?int $actingBranchId, array $filters = [])
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = PurchaseReturn::query()->where('institute_id', $instituteId);
        if ($branchId !== null) {
            $q->where(function ($qq) use ($branchId) {
                $qq->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }
        if (! empty($filters['warehouse_id'])) {
            $q->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['status'])) {
            $q->where('status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('return_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('return_date', '<=', $filters['to']);
        }

        return $q;
    }

    public function dashboardMetrics(int $instituteId, ?int $actingBranchId, array $filters = []): array
    {
        // Invoice aggregates — read-only, sql aggregation
        $postedQ = $this->invoiceQuery($instituteId, $actingBranchId, array_merge($filters, ['status' => 'posted']));
        $postedAgg = (clone $postedQ)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(discount_amount),0) as discounts, COALESCE(SUM(tax_amount),0) as tax, COALESCE(SUM(paid_amount),0) as paid, COALESCE(SUM(due_amount),0) as due')->first();

        $draftQ = $this->invoiceQuery($instituteId, $actingBranchId, array_merge($filters, ['status' => 'draft']));
        $draftAgg = (clone $draftQ)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')->first();

        $returnsQ = $this->returnQuery($instituteId, $actingBranchId, array_merge($filters, ['status' => 'posted']));
        $returnsAgg = (clone $returnsQ)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')->first();

        // total purchases = posted total (source of posting)
        $totalPurchases = round((float) ($postedAgg->total ?? 0), 4);
        $postedPurchases = $totalPurchases;
        $draftPurchases = round((float) ($draftAgg->total ?? 0), 4);
        $purchaseReturns = round((float) ($returnsAgg->total ?? 0), 4);
        $netPurchases = round($totalPurchases - $purchaseReturns, 4);
        $discounts = round((float) ($postedAgg->discounts ?? 0), 4);
        $tax = round((float) ($postedAgg->tax ?? 0), 4);
        $amountPaid = round((float) ($postedAgg->paid ?? 0), 4);
        $amountDue = round((float) ($postedAgg->due ?? 0), 4);

        // Finance source-of-truth — payable outstanding (derived AP)
        $branchForFinance = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $financeTotals = $this->payables->totals($instituteId, $branchForFinance);
        $outstandingPayable = round((float) ($financeTotals['payable'] ?? 0), 4);

        // Counts — sql count
        $poQuery = PurchaseOrder::query()->where('institute_id', $instituteId);
        if ($branchForFinance !== null) {
            $poQuery->where(fn ($q) => $q->where('branch_id', $branchForFinance)->orWhereNull('branch_id'));
        }
        if (! empty($filters['supplier_id'])) {
            $poQuery->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['branch_id']) && $actingBranchId === null) {
            $poQuery->where('branch_id', (int) $filters['branch_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $poQuery->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['from'])) {
            $poQuery->whereDate('order_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $poQuery->whereDate('order_date', '<=', $filters['to']);
        }
        $poCount = (int) $poQuery->count();

        $receiptQuery = GoodsReceipt::query()->where('institute_id', $instituteId);
        if ($branchForFinance !== null) {
            $receiptQuery->where(fn ($q) => $q->where('branch_id', $branchForFinance)->orWhereNull('branch_id'));
        }
        if (! empty($filters['warehouse_id'])) {
            $receiptQuery->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $receiptQuery->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['from'])) {
            $receiptQuery->whereDate('receipt_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $receiptQuery->whereDate('receipt_date', '<=', $filters['to']);
        }
        $receiptCount = (int) $receiptQuery->count();

        $invoiceCount = (int) ((clone $postedQ)->count() + (clone $draftQ)->count());
        // or total invoice count posted+draft
        $invoiceCnt = (int) PurchaseInvoice::query()->where('institute_id', $instituteId)
            ->when($branchForFinance !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchForFinance)->orWhereNull('branch_id')))
            ->whereNotIn('status', ['cancelled', 'reversed'])
            ->count();

        return [
            'total_purchases' => $totalPurchases,
            'posted_purchases' => $postedPurchases,
            'draft_purchases' => $draftPurchases,
            'draft_count' => (int) ($draftAgg->cnt ?? 0),
            'posted_count' => (int) ($postedAgg->cnt ?? 0),
            'purchase_returns' => $purchaseReturns,
            'returns_count' => (int) ($returnsAgg->cnt ?? 0),
            'net_purchases' => $netPurchases,
            'discounts' => $discounts,
            'tax' => $tax,
            'outstanding_payable' => $outstandingPayable,
            'amount_paid' => $amountPaid,
            'amount_due' => $amountDue,
            'purchase_orders_count' => $poCount,
            'receipts_count' => $receiptCount,
            'purchase_invoices_count' => $invoiceCnt,
        ];
    }

    public function timeSeries(int $instituteId, ?int $actingBranchId, array $filters, string $group): Collection
    {
        // group: daily|weekly|monthly|yearly
        $q = $this->invoiceQuery($instituteId, $actingBranchId, $filters)->where('status', 'posted');
        $select = match ($group) {
            'weekly' => 'YEARWEEK(invoice_date,1) as period, MIN(invoice_date) as period_date',
            'monthly' => "DATE_FORMAT(invoice_date,'%Y-%m') as period, MIN(invoice_date) as period_date",
            'yearly' => 'YEAR(invoice_date) as period, MIN(invoice_date) as period_date',
            default => 'DATE(invoice_date) as period, MIN(invoice_date) as period_date',
        };
        $groupBy = match ($group) {
            'weekly' => DB::raw('YEARWEEK(invoice_date,1)'),
            'monthly' => DB::raw("DATE_FORMAT(invoice_date,'%Y-%m')"),
            'yearly' => DB::raw('YEAR(invoice_date)'),
            default => DB::raw('DATE(invoice_date)'),
        };

        return $q->selectRaw($select)
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(discount_amount),0) as discounts, COALESCE(SUM(tax_amount),0) as tax')
            ->groupBy($groupBy)
            ->orderBy('period')
            ->get();
    }

    public function supplierWise(int $instituteId, ?int $actingBranchId, array $filters): Collection
    {
        $q = $this->invoiceQuery($instituteId, $actingBranchId, array_merge($filters, []))
            ->where('status', 'posted')
            ->select('supplier_id')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(discount_amount),0) as discounts, COALESCE(SUM(tax_amount),0) as tax, COALESCE(SUM(paid_amount),0) as paid, COALESCE(SUM(due_amount),0) as due')
            ->groupBy('supplier_id')
            ->orderByDesc('total');
        $rows = $q->get();
        $supplierIds = $rows->pluck('supplier_id')->filter()->values();
        $suppliers = Party::withoutGlobalScopes()->whereIn('id', $supplierIds)->get()->keyBy('id');

        return $rows->map(function ($r) use ($suppliers) {
            $s = $suppliers->get($r->supplier_id);
            $r->supplier_name = $s?->name ?? 'Unknown';
            $r->supplier_phone = $s?->phone ?? '';

            return $r;
        });
    }

    public function productWise(int $instituteId, ?int $actingBranchId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->where('pi.institute_id', $instituteId)
            ->where('pi.status', 'posted')
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($w) => $w->where('pi.branch_id', $branchId)->orWhereNull('pi.branch_id')))
            ->when(! empty($filters['supplier_id']), fn ($qq) => $qq->where('pi.supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['from']), fn ($qq) => $qq->whereDate('pi.invoice_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($qq) => $qq->whereDate('pi.invoice_date', '<=', $filters['to']))
            ->when(! empty($filters['product_id']), fn ($qq) => $qq->where('pii.inventory_item_id', (int) $filters['product_id']))
            ->when(! empty($filters['category_id']), function ($qq) use ($filters) {
                $qq->whereExists(function ($sq) use ($filters) {
                    $sq->select(DB::raw(1))->from('inventory_items as ii')->whereColumn('ii.id', 'pii.inventory_item_id')->where('ii.category_id', (int) $filters['category_id']);
                });
            });
        // Need to filter warehouse via pi goods_receipt? keep simple
        $paginator = $q->select('pii.inventory_item_id')
            ->selectRaw('COALESCE(SUM(pii.quantity),0) as qty, COALESCE(SUM(pii.line_total),0) as total, COALESCE(SUM(pii.discount_amount),0) as discounts, COALESCE(SUM(pii.tax_amount),0) as tax, COUNT(DISTINCT pi.id) as invoice_cnt')
            ->groupBy('pii.inventory_item_id')
            ->orderByDesc('total')
            ->paginate($perPage);

        $itemIds = collect($paginator->items())->pluck('inventory_item_id')->filter()->values();
        $items = InventoryItem::withoutGlobalScopes()->whereIn('id', $itemIds)->get()->keyBy('id');
        $paginator->getCollection()->transform(function ($r) use ($items) {
            $it = $items->get($r->inventory_item_id);
            $r->item_name = $it?->name ?? $r->inventory_item_id ? 'Item #'.$r->inventory_item_id : 'Manual';
            $r->sku = $it?->sku ?? '';
            $r->category_id = $it?->category_id ?? null;

            return $r;
        });

        return $paginator;
    }

    public function categoryWise(int $instituteId, ?int $actingBranchId, array $filters): Collection
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = DB::table('purchase_invoice_items as pii')
            ->join('purchase_invoices as pi', 'pi.id', '=', 'pii.purchase_invoice_id')
            ->leftJoin('inventory_items as ii', 'ii.id', '=', 'pii.inventory_item_id')
            ->where('pi.institute_id', $instituteId)
            ->where('pi.status', 'posted')
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($w) => $w->where('pi.branch_id', $branchId)->orWhereNull('pi.branch_id')))
            ->when(! empty($filters['supplier_id']), fn ($qq) => $qq->where('pi.supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['from']), fn ($qq) => $qq->whereDate('pi.invoice_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($qq) => $qq->whereDate('pi.invoice_date', '<=', $filters['to']))
            ->select('ii.category_id')
            ->selectRaw('COALESCE(SUM(pii.quantity),0) as qty, COALESCE(SUM(pii.line_total),0) as total')
            ->groupBy('ii.category_id')
            ->orderByDesc('total')
            ->get();
        $catIds = $q->pluck('category_id')->filter()->values();
        $cats = InventoryCategory::withoutGlobalScopes()->whereIn('id', $catIds)->get()->keyBy('id');

        return $q->map(function ($r) use ($cats) {
            $cat = $cats->get($r->category_id);
            $r->category_name = $cat?->name ?? ($r->category_id ? 'Category #'.$r->category_id : 'Uncategorized');

            return $r;
        });
    }

    public function branchWise(int $instituteId, ?int $actingBranchId, array $filters): Collection
    {
        $q = $this->invoiceQuery($instituteId, $actingBranchId, $filters)->where('status', 'posted')
            ->select('branch_id')
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(grand_total),0) as total')
            ->groupBy('branch_id')
            ->orderByDesc('total')
            ->get();
        $branchIds = $q->pluck('branch_id')->filter()->values();
        $branches = Branch::whereIn('id', $branchIds)->get()->keyBy('id');

        return $q->map(function ($r) use ($branches) {
            $b = $branches->get($r->branch_id);
            $r->branch_name = $b?->name ?? 'Institute-wide';

            return $r;
        });
    }

    public function warehouseReceiving(int $instituteId, ?int $actingBranchId, array $filters): Collection
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = GoodsReceipt::query()->where('institute_id', $instituteId)->where('status', 'confirmed');
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if (! empty($filters['warehouse_id'])) {
            $q->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('receipt_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('receipt_date', '<=', $filters['to']);
        }
        $rows = $q->select('warehouse_id')->selectRaw('COUNT(*) as cnt')->groupBy('warehouse_id')->orderByDesc('cnt')->get();
        $whIds = $rows->pluck('warehouse_id')->filter()->values();
        $whs = InventoryWarehouse::withoutGlobalScopes()->whereIn('id', $whIds)->get()->keyBy('id');
        // Also sum received qty per warehouse via items
        $itemAgg = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->where('gr.institute_id', $instituteId)->where('gr.status', 'confirmed')
            ->when($branchId !== null, fn ($qq) => $qq->where(fn ($w) => $w->where('gr.branch_id', $branchId)->orWhereNull('gr.branch_id')))
            ->when(! empty($filters['warehouse_id']), fn ($qq) => $qq->where('gr.warehouse_id', (int) $filters['warehouse_id']))
            ->when(! empty($filters['supplier_id']), fn ($qq) => $qq->where('gr.supplier_id', (int) $filters['supplier_id']))
            ->when(! empty($filters['from']), fn ($qq) => $qq->whereDate('gr.receipt_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($qq) => $qq->whereDate('gr.receipt_date', '<=', $filters['to']))
            ->select('gr.warehouse_id')
            ->selectRaw('COALESCE(SUM(gri.received_quantity - gri.rejected_quantity),0) as net_qty')
            ->groupBy('gr.warehouse_id')
            ->get()->keyBy('warehouse_id');

        return $rows->map(function ($r) use ($whs, $itemAgg) {
            $w = $whs->get($r->warehouse_id);
            $r->warehouse_name = $w?->name ?? 'Unknown';
            $r->net_qty = round((float) ($itemAgg->get($r->warehouse_id)?->net_qty ?? 0), 4);

            return $r;
        });
    }

    public function returnsReport(int $instituteId, ?int $actingBranchId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $q = $this->returnQuery($instituteId, $actingBranchId, $filters);
        // default only posted returns count as financial
        if (empty($filters['status'])) {
            $q->where('status', 'posted');
        }
        $q->with(['supplier', 'warehouse'])->orderByDesc('return_date');

        return $q->paginate($perPage);
    }

    public function paymentsReport(int $instituteId, ?int $actingBranchId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = PurchaseSupplierPayment::query()->where('institute_id', $instituteId)
            ->with(['supplier', 'purchaseInvoice']);
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('paid_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('paid_at', '<=', $filters['to']);
        }
        $q->orderByDesc('paid_at');

        return $q->paginate($perPage);
    }

    public function outstandingPayableReport(int $instituteId, ?int $actingBranchId, array $filters): Collection
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        // Reuse finance AP with aging — read-only delegated
        $balances = $this->payables->supplierBalancesWithAging($instituteId, $branchId);
        if (! empty($filters['supplier_id'])) {
            $balances = $balances->where('id', (int) $filters['supplier_id'])->values();
        }

        return $balances;
    }

    public function inventoryReconciliation(int $instituteId, ?int $actingBranchId, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $branchId = $this->branchScope($instituteId, $actingBranchId, isset($filters['branch_id']) ? (int) $filters['branch_id'] : null);
        $q = PurchaseOrder::query()->where('institute_id', $instituteId)
            ->with(['supplier', 'warehouse']);
        if ($branchId !== null) {
            $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if (! empty($filters['supplier_id'])) {
            $q->where('supplier_id', (int) $filters['supplier_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $q->where('warehouse_id', (int) $filters['warehouse_id']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('order_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('order_date', '<=', $filters['to']);
        }
        if (! empty($filters['product_id'])) {
            $q->whereHas('lines', fn ($qq) => $qq->where('inventory_item_id', (int) $filters['product_id']));
        }
        if (! empty($filters['category_id'])) {
            $q->whereHas('lines.inventoryItem', fn ($qq) => $qq->where('category_id', (int) $filters['category_id']));
        }
        $q->orderByDesc('order_date');
        $paginator = $q->paginate($perPage);
        // Avoid N+1: eager load lines, then compute aggregates in memory per page (20 rows)
        $paginator->load('lines.inventoryItem');
        // For each order, fetch receipt qty sums efficiently via one query per page
        $orderIds = $paginator->getCollection()->pluck('id');
        $receiptAgg = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->whereIn('gr.purchase_order_id', $orderIds)->where('gr.status', 'confirmed')
            ->select('gr.purchase_order_id')->selectRaw('COALESCE(SUM(gri.received_quantity - gri.rejected_quantity),0) as net_received')
            ->groupBy('gr.purchase_order_id')->get()->keyBy('purchase_order_id');
        $returnAgg = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->whereIn('pr.purchase_order_id', $orderIds)->where('pr.status', 'posted')
            ->select('pr.purchase_order_id')->selectRaw('COALESCE(SUM(pri.quantity),0) as returned_qty')
            ->groupBy('pr.purchase_order_id')->get()->keyBy('purchase_order_id');
        $paginator->getCollection()->each(function ($order) use ($receiptAgg, $returnAgg) {
            $ordered = (float) $order->lines->sum('quantity');
            $received = (float) ($receiptAgg->get($order->id)?->net_received ?? 0);
            $returned = (float) ($returnAgg->get($order->id)?->returned_qty ?? 0);
            $order->reconciliation = [
                'ordered_qty' => round($ordered, 4),
                'received_qty' => round($received, 4),
                'returned_qty' => round($returned, 4),
                'net_received_qty' => round($received - $returned, 4),
            ];
        });

        return $paginator;
    }

    public function supplierStatement(int $instituteId, ?int $actingBranchId, int $supplierId, array $filters = []): array
    {
        $supplier = Party::withoutGlobalScopes()->where('id', $supplierId)->where('institute_id', $instituteId)->firstOrFail();
        // Tenant already verified; branch check
        if ($actingBranchId !== null && $supplier->branch_id !== null && (int) $supplier->branch_id !== (int) $actingBranchId) {
            abort(404);
        }
        $branchId = $this->branchScope($instituteId, $actingBranchId, null);
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        // Opening balance as of day before from (finance derived)
        $openingAsOf = $from ? date('Y-m-d', strtotime($from.' -1 day')) : null;
        $openingBalance = 0.0;
        if ($openingAsOf) {
            $bal = $this->payables->partyBalance($supplier, $openingAsOf);
            $openingBalance = round((float) ($bal['payable'] ?? 0), 4);
        } else {
            // If no from, opening 0
            $openingBalance = 0.0;
        }

        // Fetch posted invoices in range
        $invoiceQ = PurchaseInvoice::query()->where('institute_id', $instituteId)->where('supplier_id', $supplierId)->where('status', 'posted');
        if ($branchId !== null) {
            $invoiceQ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if ($from) {
            $invoiceQ->whereDate('invoice_date', '>=', $from);
        }
        if ($to) {
            $invoiceQ->whereDate('invoice_date', '<=', $to);
        }
        $invoices = $invoiceQ->orderBy('invoice_date')->orderBy('id')->get(['id', 'invoice_number', 'invoice_date', 'grand_total', 'paid_amount', 'due_amount', 'status']);

        // Payments
        $paymentQ = PurchaseSupplierPayment::query()->where('institute_id', $instituteId)->where('supplier_id', $supplierId);
        if ($branchId !== null) {
            $paymentQ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if ($from) {
            $paymentQ->whereDate('paid_at', '>=', $from);
        }
        if ($to) {
            $paymentQ->whereDate('paid_at', '<=', $to);
        }
        $payments = $paymentQ->orderBy('paid_at')->orderBy('id')->get(['id', 'paid_at', 'amount', 'purchase_invoice_id']);

        // Returns (credit notes) — posted
        $returnQ = PurchaseReturn::query()->where('institute_id', $instituteId)->where('supplier_id', $supplierId)->where('status', 'posted');
        if ($branchId !== null) {
            $returnQ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
        }
        if ($from) {
            $returnQ->whereDate('return_date', '>=', $from);
        }
        if ($to) {
            $returnQ->whereDate('return_date', '<=', $to);
        }
        $returns = $returnQ->orderBy('return_date')->orderBy('id')->get(['id', 'return_number', 'return_date', 'grand_total']);

        // Refunds: purchase_returns refund is separate? Payment reverse or adjustment — treat as payment reverse? For now treat returns already credit, and refunds are same as payments with negative? Use purchase_returns refund via journal? For simplicity returns cover credit notes, refunds are captured via reverse payments (already excluded).
        // Build chronological ledger
        $entries = collect();
        foreach ($invoices as $inv) {
            $entries->push([
                'date' => $inv->invoice_date->toDateString(),
                'type' => 'invoice',
                'ref' => $inv->invoice_number,
                'debit' => 0.0,
                'credit' => round((float) $inv->grand_total, 4),
                'balance_change' => round((float) $inv->grand_total, 4), // increases payable
                'id' => $inv->id,
            ]);
        }
        foreach ($payments as $pay) {
            $entries->push([
                'date' => $pay->paid_at ? $pay->paid_at->toDateString() : '',
                'type' => 'payment',
                'ref' => 'Payment #'.$pay->id.($pay->purchase_invoice_id ? ' (Inv #'.$pay->purchase_invoice_id.')' : ''),
                'debit' => round((float) $pay->amount, 4),
                'credit' => 0.0,
                'balance_change' => -round((float) $pay->amount, 4),
                'id' => $pay->id,
            ]);
        }
        foreach ($returns as $ret) {
            $entries->push([
                'date' => $ret->return_date->toDateString(),
                'type' => 'credit_note',
                'ref' => $ret->return_number ?? 'Return #'.$ret->id,
                'debit' => round((float) $ret->grand_total, 4),
                'credit' => 0.0,
                'balance_change' => -round((float) $ret->grand_total, 4),
                'id' => $ret->id,
            ]);
        }
        // Adjustments via journal reversals? For now treat invoice reversals as credit notes already via returns.
        $sorted = $entries->sortBy('date')->values();
        $running = $openingBalance;
        $ledger = $sorted->map(function ($e) use (&$running) {
            $running = round($running + $e['balance_change'], 4);
            $e['running_balance'] = $running;

            return $e;
        });
        $closingBalance = round($running, 4);

        return [
            'supplier' => $supplier,
            'opening_balance' => round($openingBalance, 4),
            'entries' => $ledger,
            'closing_balance' => $closingBalance,
            'invoices_count' => $invoices->count(),
            'payments_count' => $payments->count(),
            'returns_count' => $returns->count(),
        ];
    }

    public function distinctSuppliers(int $instituteId, ?int $actingBranchId): Collection
    {
        return Party::withoutGlobalScopes()->where('institute_id', $instituteId)->whereIn('type', ['supplier', 'both'])->where('is_active', true)
            ->when($actingBranchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereNull('branch_id')->orWhere('branch_id', $actingBranchId)))
            ->orderBy('name')->get(['id', 'name']);
    }
}
