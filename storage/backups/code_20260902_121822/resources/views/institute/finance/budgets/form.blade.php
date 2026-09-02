@extends('layouts.institute')

@section('title', ($budget ? 'Edit Budget' : 'Create Budget') . ' — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>{{ $budget ? 'Edit Budget' : 'Create Budget' }}</h4>
    <a class="btn btn-outline-secondary btn-sm rounded-pill" href="{{ route('finance.budgets.index') }}"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<form method="POST" action="{{ $budget ? route('finance.budgets.update', $budget->id) : route('finance.budgets.store') }}">
    @csrf
    @if ($budget) @method('PUT') @endif

    <div class="card mb-4">
        <div class="card-header fw-semibold">Budget Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $budget->name ?? '') }}" required maxlength="100">
                    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select name="type" class="form-select" {{ $budget ? 'disabled' : '' }} required>
                        @foreach (['revenue' => 'Revenue', 'expense' => 'Expense', 'cost' => 'Cost', 'asset' => 'Asset Planning'] as $val => $lbl)
                            <option value="{{ $val }}" {{ old('type', $budget->type ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    @if ($budget) <input type="hidden" name="type" value="{{ $budget->type }}"> @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fiscal Year <span class="text-danger">*</span></label>
                    <select name="fiscal_year_id" class="form-select" {{ $budget ? 'disabled' : '' }} required>
                        @foreach ($fiscalYears as $fy)
                            <option value="{{ $fy->id }}" {{ old('fiscal_year_id', $budget->fiscal_year_id ?? '') == $fy->id ? 'selected' : '' }}>{{ $fy->name }}</option>
                        @endforeach
                    </select>
                    @if ($budget) <input type="hidden" name="fiscal_year_id" value="{{ $budget->fiscal_year_id }}"> @endif
                </div>
                <div class="col-md-4">
                    <label class="form-label">Currency</label>
                    <input type="text" class="form-control" value="{{ $currency->code ?? '—' }}" disabled>
                    <input type="hidden" name="currency_id" value="{{ $budget->currency_id ?? $currency?->id ?? '' }}">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $budget->notes ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-check me-1"></i>Budget Lines</span>
            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" id="addLine"><i class="bi bi-plus-lg me-1"></i>Add Line</button>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0" id="linesTable">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Month</th>
                        <th>Amount</th>
                        <th>Notes</th>
                        <th class="text-end">Remove</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $existingLines = [];
                        if ($budget && $budget->versions->count()) {
                            $activeVersion = $budget->versions->where('version', $budget->version)->first();
                            $existingLines = $activeVersion?->lines ?? collect();
                        }
                    @endphp
                    @forelse ($existingLines as $line)
                        <tr class="budget-line-row">
                            <td>
                                <select name="lines[{{ $loop->index }}][coa_id]" class="form-select form-select-sm" required>
                                    <option value="">Select account</option>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}" {{ $line->coa_id == $acc->id ? 'selected' : '' }}>{{ $acc->code }} — {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $loop->index }}][month]" class="form-select form-select-sm">
                                    <option value="0" {{ $line->month == 0 ? 'selected' : '' }}>Annual Total</option>
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" {{ $line->month == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                                    @endfor
                                </select>
                            </td>
                            <td><input type="number" name="lines[{{ $loop->index }}][amount]" class="form-control form-control-sm" value="{{ $line->amount }}" step="0.01" min="0" required></td>
                            <td><input type="text" name="lines[{{ $loop->index }}][notes]" class="form-control form-control-sm" value="{{ $line->notes ?? '' }}"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @empty
                        <tr class="budget-line-row">
                            <td>
                                <select name="lines[0][coa_id]" class="form-select form-select-sm" required>
                                    <option value="">Select account</option>
                                    @foreach ($accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->code }} — {{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[0][month]" class="form-select form-select-sm">
                                    <option value="0">Annual Total</option>
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->format('F') }}</option>
                                    @endfor
                                </select>
                            </td>
                            <td><input type="number" name="lines[0][amount]" class="form-control form-control-sm" step="0.01" min="0" required></td>
                            <td><input type="text" name="lines[0][notes]" class="form-control form-control-sm"></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary rounded-pill" href="{{ route('finance.budgets.index') }}">Cancel</a>
        <button class="btn btn-primary rounded-pill px-4" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $budget ? 'Update' : 'Create' }} Budget</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('addLine')?.addEventListener('click', function () {
    var tbody = document.querySelector('#linesTable tbody');
    var idx = tbody.querySelectorAll('.budget-line-row').length;
    var tr = document.createElement('tr');
    tr.className = 'budget-line-row';
    var accountOptions = '<option value="">Select account</option>';
    @foreach ($accounts as $acc)
        accountOptions += '<option value="{{ $acc->id }}">{{ $acc->code }} — {{ addslashes($acc->name) }}</option>';
    @endforeach
    var monthOptions = '<option value="0">Annual Total</option>';
    @for ($m = 1; $m <= 12; $m++)
        monthOptions += '<option value="{{ $m }}">{{ \Carbon\Carbon::create()->month($m)->format("F") }}</option>';
    @endfor
    tr.innerHTML =
        '<td><select name="lines[' + idx + '][coa_id]" class="form-select form-select-sm" required>' + accountOptions + '</select></td>' +
        '<td><select name="lines[' + idx + '][month]" class="form-select form-select-sm">' + monthOptions + '</select></td>' +
        '<td><input type="number" name="lines[' + idx + '][amount]" class="form-control form-control-sm" step="0.01" min="0" required></td>' +
        '<td><input type="text" name="lines[' + idx + '][notes]" class="form-control form-control-sm"></td>' +
        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button></td>';
    tbody.appendChild(tr);
});

document.addEventListener('click', function (e) {
    if (e.target.closest('.remove-line')) {
        var row = e.target.closest('.budget-line-row');
        if (document.querySelectorAll('.budget-line-row').length > 1) {
            row.remove();
        }
    }
});
</script>
@endpush
