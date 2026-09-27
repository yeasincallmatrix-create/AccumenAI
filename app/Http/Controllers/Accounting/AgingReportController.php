<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Services\AgingCalculatorService;
use App\Services\Accounting\ReportExportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Phase B — Aging report screens (AR / AP / Invoice / Summary / Config).
 *
 * Thin controller over AgingCalculatorService. Institute identity comes
 * only from the authenticated workspace (ResolvesInstitute), never from
 * request input. Each route carries its own `permission:<slug>` middleware
 * (this codebase's CheckPermission alias) — platform admins bypass, owners
 * are auto-granted through Membership::hasPermission().
 */
class AgingReportController extends Controller
{
    use ResolvesInstitute;

    private const EXPORT_TYPES = [
        'ar_aging' => 'ar_aging.view',
        'ap_aging' => 'ap_aging.view',
    ];

    public function __construct(
        private readonly AgingCalculatorService $service,
        private readonly ReportExportService $exporter,
    ) {}

    public function arAging(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $this->asOf($request);

        $report = $this->service->arAging($institute->id, $asOf);

        return view('accounting.aging.ar', [
            'institute' => $institute,
            'report' => $report,
            'asOf' => $asOf,
        ]);
    }

    public function apAging(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $this->asOf($request);

        $report = $this->service->apAging(null, $asOf, $institute->id);

        return view('accounting.aging.ap', [
            'institute' => $institute,
            'report' => $report,
            'asOf' => $asOf,
        ]);
    }

    public function invoiceAging(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $this->asOf($request);

        $ar = $this->service->arAging($institute->id, $asOf);
        $ap = $this->service->apAging(null, $asOf, $institute->id);

        return view('accounting.aging.invoice', [
            'institute' => $institute,
            'ar' => $ar,
            'ap' => $ap,
            'asOf' => $asOf,
        ]);
    }

    public function summary(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $asOf = $this->asOf($request);

        $ar = $this->service->arAging($institute->id, $asOf);
        $ap = $this->service->apAging(null, $asOf, $institute->id);

        return view('accounting.aging.summary', [
            'institute' => $institute,
            'ar' => $ar,
            'ap' => $ap,
            'asOf' => $asOf,
        ]);
    }

    public function config(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('accounting.aging.config', [
            'institute' => $institute,
            'buckets' => $this->service->buckets(),
            'sources' => config('accounting.aging.source', []),
        ]);
    }

    /**
     * CSV export for ar_aging / ap_aging (type is validated against an
     * allow-list; unknown types 404).
     */
    public function export(Request $request, string $type): StreamedResponse
    {
        abort_unless(isset(self::EXPORT_TYPES[$type]), 404);
        $this->guardPermission($request, self::EXPORT_TYPES[$type]);

        $institute = $this->requireInstitute($request);
        $asOf = $this->asOf($request);

        $report = match ($type) {
            'ar_aging' => $this->service->arAging($institute->id, $asOf),
            'ap_aging' => $this->service->apAging(null, $asOf, $institute->id),
        };

        $rows = collect();
        foreach ($report['buckets'] as $key => $bucket) {
            foreach ($bucket['rows'] as $row) {
                $rows->push([
                    'Invoice' => $row['invoice_number'],
                    'Due Date' => $row['due_date'],
                    'Days Overdue' => $row['days_overdue'],
                    'Bucket' => $bucket['label'],
                    'Outstanding' => $row['outstanding'],
                ]);
            }
        }

        $filename = "aging-{$type}-{$report['as_of']}.csv";

        return $this->exporter->csv($rows, $filename);
    }

    private function asOf(Request $request): Carbon
    {
        if (!$request->filled('as_of')) {
            return now();
        }

        try {
            return Carbon::parse($request->query('as_of'));
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Dynamic per-type permission gate for /export/{type} (route middleware
     * cannot vary on the {type} segment). Mirrors CheckPermission: platform
     * admins bypass, everyone else needs the exact slug.
     */
    private function guardPermission(Request $request, string $permission): void
    {
        $user = $request->user();

        if ($user instanceof PlatformAdmin) {
            return;
        }

        if ($user !== null && method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return;
        }

        abort(403, 'You are not authorized to perform this action.');
    }
}
