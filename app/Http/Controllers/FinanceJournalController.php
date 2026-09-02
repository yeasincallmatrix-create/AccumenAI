<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Journal;
use App\Models\Party;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Journals (Step 32): browse, create, view, post drafts, reverse and void
 * double-entry documents. Drafts never affect the ledger; posted journals can
 * only be reversed (never hard-deleted).
 */
class FinanceJournalController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly JournalPostingService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Journal::query()->with(['creator', 'period', 'branch'])
            ->withSum('entries as total_debit', 'debit')
            ->withSum('entries as total_credit', 'credit');

        if (filled($q = $request->query('q'))) {
            $query->where('journal_no', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%");
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        if (filled($request->query('branch_id'))) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }

        if (filled($request->query('from'))) {
            $query->whereDate('journal_date', '>=', $request->query('from'));
        }

        if (filled($request->query('to'))) {
            $query->whereDate('journal_date', '<=', $request->query('to'));
        }

        $journals = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.journals.index', [
            'institute' => $institute,
            'journals' => $journals,
            'types' => ['sale', 'purchase', 'receipt', 'payment', 'journal', 'contra', 'opening', 'adjustment'],
            'statuses' => ['draft', 'posted', 'reversed', 'void'],
            'branches' => Branch::query()
                ->where('institute_id', $institute->id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.journals.form', [
            'institute' => $institute,
            'journal' => null,
            'accounts' => $this->accounts($institute->id),
            'parties' => Party::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'types' => ['sale', 'purchase', 'receipt', 'payment', 'journal', 'contra', 'opening', 'adjustment'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request);
        $data['institute_id'] = $institute->id;
        $data['branch_id'] = $this->actingBranchId($request);
        $data['currency_id'] = $data['currency_id'] ?? $this->defaultCurrencyId();

        $journal = $this->service->create($data, (int) $this->actorId($request));

        return redirect()
            ->route('finance.journals.show', $journal)
            ->with('status', 'Journal '.$journal->journal_no.' '.($journal->status === 'posted' ? 'posted.' : 'saved as draft.'));
    }

    public function show(Request $request, Journal $journal): View
    {
        $this->requireInstitute($request);

        $journal->load(['entries.coa', 'entries.party', 'creator', 'postedBy', 'reversedBy', 'reversalOf', 'period']);

        return view('institute.finance.journals.show', [
            'institute' => $this->requireInstitute($request),
            'journal' => $journal,
        ]);
    }

    public function post(Request $request, Journal $journal): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->post($journal, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Journal '.$journal->journal_no.' posted.');
    }

    public function reverse(Request $request, Journal $journal): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $reason = $request->input('reason');
        $this->service->reverse($journal, $institute->id, (int) $this->actorId($request), $reason);

        return back()->with('status', 'Journal '.$journal->journal_no.' reversed.');
    }

    public function void(Request $request, Journal $journal): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->void($journal, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Draft journal '.$journal->journal_no.' voided.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $entries = $request->validate([
            'journal_date' => ['required', 'date'],
            'type' => ['required', 'in:sale,purchase,receipt,payment,journal,contra,opening,adjustment'],
            'currency_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:500'],
            'entries' => ['required', 'array', 'min:2'],
            'entries.*.coa_id' => ['required', 'integer'],
            'entries.*.party_id' => ['nullable', 'integer'],
            'entries.*.debit' => ['required', 'numeric', 'min:0'],
            'entries.*.credit' => ['required', 'numeric', 'min:0'],
            'entries.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $entries['entries'] = array_values(array_filter(
            array_map(fn ($line) => array_filter($line, fn ($value) => $value !== '' && $value !== null), $entries['entries']),
            fn ($line) => ! empty($line),
        ));

        return $entries;
    }

    private function defaultCurrencyId(): int
    {
        $currency = Currency::query()->orderBy('code')->value('id');

        return (int) $currency;
    }

    private function accounts(int $instituteId): Collection
    {
        return ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type']);
    }
}
