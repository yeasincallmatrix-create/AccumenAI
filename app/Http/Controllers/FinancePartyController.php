<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\Currency;
use App\Models\Party;
use App\Services\Accounting\PartyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Parties (Step 32): unified customers / suppliers / both used for AR and AP.
 * Duplicate phone numbers within (institute, branch, type) are rejected.
 */
class FinancePartyController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PartyService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Party::query()->with(['branch', 'customerGroup']);

        if (filled($q = $request->query('q'))) {
            $query->where(function ($builder) use ($q) {
                $builder->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if (filled($request->query('type'))) {
            $query->where('type', $request->query('type'));
        }

        if (filled($request->query('status'))) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        $parties = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.parties.index', [
            'institute' => $institute,
            'parties' => $parties,
            'types' => ['customer', 'supplier', 'both'],
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.parties.form', [
            'institute' => $institute,
            'party' => null,
            'types' => ['customer', 'supplier', 'both'],
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $party = $this->service->create(
            $institute->id,
            $this->actingBranchId($request),
            $this->validated($request),
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.parties.index')
            ->with('status', 'Party "'.$party->name.'" created.');
    }

    public function edit(Request $request, Party $party): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.parties.form', [
            'institute' => $institute,
            'party' => $party,
            'types' => ['customer', 'supplier', 'both'],
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Party $party): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $party = $this->service->update(
            $party,
            $this->validated($request),
            $institute->id,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.parties.index')
            ->with('status', 'Party "'.$party->name.'" updated.');
    }

    public function destroy(Request $request, Party $party): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->delete($party, $institute->id, (int) $this->actorId($request));

        return redirect()
            ->route('finance.parties.index')
            ->with('status', 'Party "'.$party->name.'" moved to trash.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'customer_group_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:2000'],
            'tin' => ['nullable', 'string', 'max:50'],
            'billing_currency_id' => ['nullable', 'integer'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
