<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Services\Accounting\InventoryAccountingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * STEP 83 — Inventory Accounting Reports Controller.
 */
class InventoryAccountingReportController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly InventoryAccountingReportService $inventoryReport,
    ) {}

    public function stockValuation(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $report = $this->inventoryReport->stockValuation($institute->id, $branchId);

        return view('institute.accounting.reports.inventory-stock-valuation', array_merge($report, [
            'institute' => $institute,
        ]));
    }

    public function inventoryMovement(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->inventoryReport->inventoryMovement($institute->id, $branchId, [
            'from' => $from,
            'to' => $to,
        ]);

        return view('institute.accounting.reports.inventory-movements', array_merge($report, [
            'institute' => $institute,
            'from' => $from,
            'to' => $to,
        ]));
    }

    public function cogsReport(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $from = $request->query('from', now()->startOfMonth()->toDateString());
        $to = $request->query('to', now()->toDateString());

        $report = $this->inventoryReport->cogsReport($institute->id, $branchId, $from, $to);

        return view('institute.accounting.reports.inventory-cogs', array_merge($report, [
            'institute' => $institute,
            'from' => $from,
            'to' => $to,
        ]));
    }

    public function slowMovingInventory(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $report = $this->inventoryReport->slowMovingInventory($institute->id, $branchId);

        return view('institute.accounting.reports.inventory-slow-moving', array_merge($report, [
            'institute' => $institute,
        ]));
    }
}
