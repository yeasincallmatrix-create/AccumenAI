<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Accounting\RecurringTemplate;
use App\Services\Accounting\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringTemplateController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        protected RecurringTransactionService $service,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = RecurringTemplate::query()
            ->where('institute_id', $institute->id);

        if (filled($q = $request->query('q'))) {
            $query->where(fn ($qq) => $qq
                ->where('name', 'like', "%{$q}%")
                ->orWhere('template_number', 'like', "%{$q}%"));
        }

        if (filled($request->query('type'))) {
            $query->where('transaction_type', $request->query('type'));
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        $templates = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.recurring-templates.index', [
            'institute' => $institute,
            'templates' => $templates,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.recurring-templates.create', [
            'institute' => $institute,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'transaction_type' => 'required|in:' . implode(',', RecurringTemplate::TRANSACTION_TYPES),
            'frequency' => 'required|in:' . implode(',', RecurringTemplate::FREQUENCIES),
            'interval_count' => 'required|integer|min:1|max:365',
            'custom_cron' => 'nullable|string|max:100|required_if:frequency,custom',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_occurrences' => 'nullable|integer|min:1',
            'auto_post' => 'nullable|boolean',
            'template_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $validated['institute_id'] = $institute->id;
        $validated['branch_id'] = $this->actingBranchId($request);
        $validated['template_number'] = $this->generateTemplateNumber($institute->id);
        $validated['next_run_at'] = Carbon::parse($validated['start_date'])->startOfDay();
        $validated['status'] = 'active';
        $validated['created_by'] = $this->actorId($request);
        $validated['auto_post'] = (bool) ($validated['auto_post'] ?? false);

        $template = RecurringTemplate::create($validated);

        $this->auditLog($institute->id, 'create', $template->id, [
            'template_number' => $template->template_number,
            'transaction_type' => $template->transaction_type,
            'frequency' => $template->frequency,
        ]);

        return redirect()
            ->route('finance.recurring-templates.show', $template)
            ->with('success', "Recurring template {$template->template_number} created.");
    }

    public function show(Request $request, RecurringTemplate $recurring_template): View
    {
        $this->authorizeAccess($request, $recurring_template);
        $recurring_template->load(['generations' => fn ($q) => $q->orderByDesc('id')->limit(20)]);
        $preview = $recurring_template->previewOccurrences(5);

        return view('institute.finance.recurring-templates.show', [
            'template' => $recurring_template,
            'preview' => $preview,
        ]);
    }

    public function edit(Request $request, RecurringTemplate $recurring_template): View
    {
        $this->authorizeAccess($request, $recurring_template);

        return view('institute.finance.recurring-templates.edit', [
            'template' => $recurring_template,
        ]);
    }

    public function update(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);

        if (!$recurring_template->isActive() && !$recurring_template->isPaused()) {
            return back()->with('error', 'Only active or paused templates can be edited.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:200',
            'frequency' => 'required|in:' . implode(',', RecurringTemplate::FREQUENCIES),
            'interval_count' => 'required|integer|min:1|max:365',
            'custom_cron' => 'nullable|string|max:100|required_if:frequency,custom',
            'end_date' => 'nullable|date',
            'max_occurrences' => 'nullable|integer|min:1',
            'auto_post' => 'nullable|boolean',
            'template_data' => 'required|array',
            'notes' => 'nullable|string',
        ]);
        $validated['auto_post'] = (bool) ($validated['auto_post'] ?? false);

        $recurring_template->update($validated);

        return back()->with('success', 'Template updated.');
    }

    public function destroy(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);
        $recurring_template->delete();

        return redirect()
            ->route('finance.recurring-templates.index')
            ->with('success', 'Template deleted.');
    }

    public function pause(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);
        $this->service->pause($recurring_template);

        return back()->with('success', 'Template paused.');
    }

    public function resume(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);
        $this->service->resume($recurring_template);

        return back()->with('success', 'Template resumed.');
    }

    public function cancel(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);
        $this->service->cancel($recurring_template);

        return back()->with('success', 'Template cancelled.');
    }

    public function generateNow(Request $request, RecurringTemplate $recurring_template): RedirectResponse
    {
        $this->authorizeAccess($request, $recurring_template);

        try {
            $this->service->generateNow($recurring_template);

            return back()->with('success', 'Generation triggered.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Generation failed: ' . $e->getMessage());
        }
    }

    private function authorizeAccess(Request $request, RecurringTemplate $template): void
    {
        $institute = $this->requireInstitute($request);
        abort_unless($template->institute_id === $institute->id, 403, 'Unauthorized.');
    }

    private function generateTemplateNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'RT-' . $year . '-';

        $last = RecurringTemplate::where('institute_id', $instituteId)
            ->where('template_number', 'like', $prefix . '%')
            ->orderByDesc('template_number')
            ->value('template_number');

        if ($last) {
            $seq = (int) substr($last, -5) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    private function auditLog(int $instituteId, string $action, int $entityId, array $payload): void
    {
        app(AccountingAuditService::class)->log($instituteId, [
            'actor_type' => 'user',
            'actor_id' => auth()->id() ?? null,
            'action' => $action,
            'entity_type' => 'recurring_template',
            'entity_id' => $entityId,
            'after_payload' => $payload,
        ]);
    }
}
