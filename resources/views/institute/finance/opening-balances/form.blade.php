@extends('layouts.standalone')

@section('title', 'Opening Balances — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Opening Balances</h4>
    <p>Set the opening position per account at the start of a fiscal year. Debit and credit totals must balance; zero rows are ignored.</p>
</div>

@if ($fiscalYears->isEmpty())
    <div class="admin-card">
        <p class="mb-0 text-muted">No fiscal year is configured yet. <a href="{{ route('finance.periods.index') }}">Create a fiscal year</a> before entering opening balances.</p>
    </div>
@else
    <form method="POST" action="{{ route('finance.opening-balances.store') }}">
        @csrf

        <div class="filter-card mb-3">
            <div class="filter-layout">
                <div class="filter-search-row align-items-end flex-wrap">
                    <div class="filter-span">
                        <label class="form-label mb-1">Fiscal year</label>
                        <select class="form-select form-select-sm" name="fiscal_year_id" onchange="this.form.action='{{ route('finance.opening-balances.create') }}'; this.form.method='GET'; this.form.submit()">
                            @foreach ($fiscalYears as $fy)
                                <option value="{{ $fy->id }}" @selected((int) $selectedYear?->id === (int) $fy->id)>
                                    {{ $fy->name }} ({{ $fy->start_date }} — {{ $fy->end_date }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="filter-span">
                        <label class="form-label mb-1">Total debit</label>
                        <div class="fw-semibold" id="ob-total-debit">0.00</div>
                    </div>
                    <div class="filter-span">
                        <label class="form-label mb-1">Total credit</label>
                        <div class="fw-semibold" id="ob-total-credit">0.00</div>
                    </div>
                    <div class="filter-span d-flex align-items-end">
                        <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Save opening balances</button>
                        <a class="btn btn-outline-secondary btn-sm ms-1" href="{{ route('finance.reports.trial-balance') }}">View trial balance</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="admin-card mb-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Account</th>
                            <th>Type</th>
                            <th class="text-end" style="width:160px">Debit</th>
                            <th class="text-end" style="width:160px">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($accounts as $account)
                            <tr>
                                <td class="text-muted">{{ $account->code }}</td>
                                <td class="fw-semibold">{{ $account->name }}</td>
                                <td><span class="badge text-bg-light border">{{ ucfirst($account->type) }}</span></td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end ob-debit" name="entries[{{ $account->id }}][debit]" value="{{ old('entries.'.$account->id.'.debit', (float) $account->debit > 0 ? number_format((float) $account->debit, 2, '.', '') : '') }}">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm text-end ob-credit" name="entries[{{ $account->id }}][credit]" value="{{ old('entries.'.$account->id.'.credit', (float) $account->credit > 0 ? number_format((float) $account->credit, 2, '.', '') : '') }}">
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No active balance-sheet accounts for this fiscal year.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </form>
@endif

@endsection

@section('scripts')
<script>
(function () {
    var debits = document.querySelectorAll('.ob-debit');
    var credits = document.querySelectorAll('.ob-credit');
    var totalDebit = document.getElementById('ob-total-debit');
    var totalCredit = document.getElementById('ob-total-credit');

    function sum(nodes) {
        var total = 0;
        nodes.forEach(function (input) {
            var value = parseFloat(input.value);
            if (! isNaN(value) && value > 0) { total += value; }
        });
        return total;
    }

    function refresh() {
        if (totalDebit) { totalDebit.textContent = sum(debits).toFixed(2); }
        if (totalCredit) { totalCredit.textContent = sum(credits).toFixed(2); }
    }

    debits.forEach(function (input) { input.addEventListener('input', refresh); });
    credits.forEach(function (input) { input.addEventListener('input', refresh); });
    refresh();
})();
</script>
@endsection