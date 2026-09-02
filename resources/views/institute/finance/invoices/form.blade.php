@extends('layouts.standalone')

@section('title', 'New Invoice — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>New Invoice</h4>
    <p>Create an invoice for a customer. A sale journal (debit Accounts Receivable / credit income) is posted automatically.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.invoices.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Customer <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="party_id" required>
                    <option value="">— Select customer —</option>
                    @foreach ($customers as $customer)
                        <option value="{{ $customer->id }}" @selected((string) old('party_id') === (string) $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Invoice type</label>
                <select class="form-select form-select-sm" name="invoice_type">
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('invoice_type', 'other') === $type)>{{ str_replace('_', ' ', ucfirst($type)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Due date</label>
                <input type="date" class="form-control form-control-sm" name="due_date" value="{{ old('due_date') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Currency</label>
                <select class="form-select form-select-sm" name="currency_id">
                    <option value="">— None —</option>
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((string) old('currency_id') === (string) $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label">Discount</label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="discount" value="{{ old('discount', 0) }}">
            </div>

            <div class="col-12">
                <label class="form-label">Items <span class="text-danger">*</span></label>
                <div id="item-rows">
                    @php $oldItems = old('items', [['description' => '', 'amount' => '', 'coa_id' => '']]); @endphp
                    @foreach ($oldItems as $index => $item)
                        <div class="row g-2 mb-2 align-items-center item-row">
                            <div class="col-md-5">
                                <input type="text" class="form-control form-control-sm" name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" placeholder="Description" maxlength="200" required>
                            </div>
                            <div class="col-md-2">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[{{ $index }}][amount]" value="{{ $item['amount'] ?? '' }}" placeholder="Amount" required>
                            </div>
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" name="items[{{ $index }}][coa_id]">
                                    <option value="">— Income account —</option>
                                    @foreach ($incomeAccounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) ($item['coa_id'] ?? '') === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="add-item"><i class="bi bi-plus-lg"></i> Add item</button>
            </div>

            <div class="col-12">
                <label class="form-label">Note</label>
                <textarea class="form-control form-control-sm" name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Create invoice</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.invoices.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const rows = document.getElementById('item-rows');
    const add = document.getElementById('add-item');
    let index = {{ count(old('items', [['x']])) }};

    function rowHtml(i) {
        return `
            <div class="row g-2 mb-2 align-items-center item-row">
                <div class="col-md-5"><input type="text" class="form-control form-control-sm" name="items[${i}][description]" placeholder="Description" maxlength="200" required></div>
                <div class="col-md-2"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="items[${i}][amount]" placeholder="Amount" required></div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="items[${i}][coa_id]">
                        <option value="">— Income account —</option>
                        @foreach ($incomeAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-item"><i class="bi bi-x-lg"></i></button></div>
            </div>`;
    }

    add.addEventListener('click', function () {
        rows.insertAdjacentHTML('beforeend', rowHtml(index++));
    });

    rows.addEventListener('click', function (event) {
        if (event.target.closest('.remove-item')) {
            const row = event.target.closest('.item-row');
            if (rows.querySelectorAll('.item-row').length > 1) {
                row.remove();
            }
        }
    });
})();
</script>
@endsection