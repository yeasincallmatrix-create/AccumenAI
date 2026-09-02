<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\PaymentMethod;
use App\Services\Accounting\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Invoices (Step 32): create AR invoices with items/installments, list, view
 * and cancel. Creation posts the SALE journal unless the institute keeps
 * invoice_auto_post=false.
 */
class FinanceInvoiceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly InvoiceService $service) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $query = Invoice::query()->with(['party', 'student', 'currency']);

        if (filled($q = $request->query('q'))) {
            $query->where('invoice_number', 'like', "%{$q}%");
        }

        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }

        if (filled($request->query('party_id'))) {
            $query->where('party_id', (int) $request->query('party_id'));
        }

        if (filled($request->query('from'))) {
            $query->whereDate('created_at', '>=', $request->query('from'));
        }

        if (filled($request->query('to'))) {
            $query->whereDate('created_at', '<=', $request->query('to'));
        }

        $invoices = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.finance.invoices.index', [
            'institute' => $institute,
            'invoices' => $invoices,
            'statuses' => ['unpaid', 'partial', 'paid', 'cancelled'],
            'customers' => Party::query()->whereIn('type', ['customer', 'both'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->query(),
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.invoices.form', [
            'institute' => $institute,
            'invoice' => null,
            'customers' => Party::query()->whereIn('type', ['customer', 'both'])->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'incomeAccounts' => ChartOfAccount::query()
                ->where('institute_id', $institute->id)
                ->where('type', 'income')
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'types' => ['admission', 'course_fee', 'exam_fee', 'certificate_fee', 'other'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $this->validated($request);
        $data['items'] = $this->normalizeItems($data['items'] ?? []);

        $invoice = $this->service->create(
            $institute->id,
            $this->actingBranchId($request),
            $data,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->invoice_number.' created.');
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $institute = $this->requireInstitute($request);

        $invoice->load(['party', 'student', 'items', 'installments', 'payments.journal', 'currency', 'journal']);

        return view('institute.finance.invoices.show', [
            'institute' => $institute,
            'invoice' => $invoice,
            'paymentMethods' => PaymentMethod::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $this->service->cancel($invoice, $institute->id, (int) $this->actorId($request));

        return back()->with('status', 'Invoice '.$invoice->invoice_number.' cancelled.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'party_id' => ['nullable', 'integer'],
            'student_id' => ['nullable', 'integer'],
            'enrollment_id' => ['nullable', 'integer'],
            'invoice_type' => ['required', Rule::in(['admission', 'course_fee', 'exam_fee', 'certificate_fee', 'other'])],
            'due_date' => ['nullable', 'date'],
            'currency_id' => ['nullable', 'integer'],
            'tax_group_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
            'items.*.coa_id' => ['nullable', 'integer'],
            'installments' => ['nullable', 'array', 'max:12'],
            'installments.*.amount' => ['required', 'numeric', 'gt:0'],
            'installments.*.due_date' => ['required', 'date'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items): array
    {
        return array_values(array_filter(
            array_map(function ($item) {
                $item['description'] = trim((string) ($item['description'] ?? ''));
                $item['coa_id'] = filled($item['coa_id'] ?? null) ? (int) $item['coa_id'] : null;

                return $item;
            }, $items),
            fn ($item) => $item['description'] !== '' && (float) ($item['amount'] ?? 0) > 0,
        ));
    }
}
