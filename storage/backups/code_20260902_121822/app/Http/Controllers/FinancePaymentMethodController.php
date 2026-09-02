<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\ChartOfAccount;
use App\Models\PaymentMethod;
use App\Services\Accounting\PaymentMethodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Payment methods (Step 9): browse, create, update, activate/deactivate and
 * delete payment methods. Names are unique per (institute, branch); system
 * methods can be deactivated but never deleted.
 */
class FinancePaymentMethodController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly PaymentMethodService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.payment-methods.index', [
            'institute' => $institute,
            'methods' => $this->service->list($institute->id, $this->actingBranchId($request), $request->query()),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.payment-methods.form', [
            'institute' => $institute,
            'method' => null,
            'accounts' => $this->accounts($institute->id),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $method = $this->service->create(
            $institute->id,
            $this->actingBranchId($request),
            $this->validated($request),
            $this->actorId($request),
        );

        return redirect()
            ->route('finance.payment-methods.index')
            ->with('status', 'Payment method "'.$method->name.'" created.');
    }

    public function edit(Request $request, PaymentMethod $method): View
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $method->institute_id !== (int) $institute->id, 404);

        return view('institute.finance.payment-methods.form', [
            'institute' => $institute,
            'method' => $method,
            'accounts' => $this->accounts($institute->id),
        ]);
    }

    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $method->institute_id !== (int) $institute->id, 404);

        $method = $this->service->update($method, $this->validated($request), $this->actorId($request));

        return redirect()
            ->route('finance.payment-methods.index')
            ->with('status', 'Payment method "'.$method->name.'" updated.');
    }

    public function toggle(Request $request, PaymentMethod $method): RedirectResponse
    {
        $this->requireInstitute($request);

        $method = $this->service->toggleActive($method, $this->actorId($request));

        return back()->with('status', 'Payment method "'.$method->name.'" '.($method->is_active ? 'activated' : 'deactivated').'.');
    }

    public function destroy(Request $request, PaymentMethod $method): RedirectResponse
    {
        $this->requireInstitute($request);

        $this->service->delete($method, $this->actorId($request));

        return redirect()
            ->route('finance.payment-methods.index')
            ->with('status', 'Payment method "'.$method->name.'" deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'coa_id' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function accounts(int $instituteId): Collection
    {
        return ChartOfAccount::query()
            ->where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'is_cash', 'is_bank']);
    }
}
