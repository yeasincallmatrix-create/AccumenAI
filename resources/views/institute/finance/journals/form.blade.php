@extends('layouts.standalone')

@section('title', $journal ? 'Edit Journal — AccumenAI' : 'New Journal — AccumenAI')
@section('page_title', $journal ? 'Edit Journal' : 'New Journal')

@section('content')

<div class="standalone-heading">
    <h4>{{ $journal ? 'Edit Journal' : 'New Journal' }}</h4>
    <p>Journal entries must balance (sum of debits equals sum of credits) before they can be posted. A line cannot carry both a debit and a credit.</p>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('finance.journals.store') }}">
        @csrf

        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Journal date <span class="text-danger">*</span></label>
                <input type="date" class="form-control form-control-sm" name="journal_date" value="{{ old('journal_date', now()->toDateString()) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm" name="type" required>
                    @foreach ($types as $type)
                        <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Currency</label>
                <select class="form-select form-select-sm" name="currency_id">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((string) old('currency_id') === (string) $currency->id)>{{ $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Description</label>
                <input type="text" class="form-control form-control-sm" name="description" value="{{ old('description') }}" maxlength="500">
            </div>

            <div class="col-12">
                <label class="form-label">Entries <span class="text-danger">*</span></label>
                <div id="entry-rows">
                    @php $oldEntries = old('entries', [['coa_id' => '', 'debit' => '', 'credit' => '', 'memo' => ''], ['coa_id' => '', 'debit' => '', 'credit' => '', 'memo' => '']]); @endphp
                    @foreach ($oldEntries as $index => $entry)
                        <div class="row g-2 mb-2 align-items-center entry-row">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm" name="entries[{{ $index }}][coa_id]" required>
                                    <option value="">— Account —</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((string) ($entry['coa_id'] ?? '') === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select form-select-sm" name="entries[{{ $index }}][party_id]">
                                    <option value="">— Party —</option>
                                    @foreach ($parties as $party)
                                        <option value="{{ $party->id }}" @selected((string) ($entry['party_id'] ?? '') === (string) $party->id)>{{ $party->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-1">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="entries[{{ $index }}][debit]" value="{{ $entry['debit'] ?? '' }}" placeholder="Debit">
                            </div>
                            <div class="col-md-1">
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="entries[{{ $index }}][credit]" value="{{ $entry['credit'] ?? '' }}" placeholder="Credit">
                            </div>
                            <div class="col-md-3">
                                <input type="text" class="form-control form-control-sm" name="entries[{{ $index }}][memo]" value="{{ $entry['memo'] ?? '' }}" placeholder="Memo" maxlength="255">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger remove-entry"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary mt-1" id="add-entry"><i class="bi bi-plus-lg"></i> Add line</button>
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Create journal</button>
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('finance.journals.index') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

@endsection

@section('scripts')
<script>
(function () {
    const rows = document.getElementById('entry-rows');
    const add = document.getElementById('add-entry');
    let index = {{ count(old('entries', [['x'], ['x']])) }};

    function rowHtml(i) {
        return `
            <div class="row g-2 mb-2 align-items-center entry-row">
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="entries[${i}][coa_id]" required>
                        <option value="">— Account —</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="entries[${i}][party_id]">
                        <option value="">— Party —</option>
                        @foreach ($parties as $party)
                            <option value="{{ $party->id }}">{{ $party->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="entries[${i}][debit]" placeholder="Debit"></div>
                <div class="col-md-1"><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="entries[${i}][credit]" placeholder="Credit"></div>
                <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="entries[${i}][memo]" placeholder="Memo" maxlength="255"></div>
                <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-entry"><i class="bi bi-x-lg"></i></button></div>
            </div>`;
    }

    add.addEventListener('click', function () {
        rows.insertAdjacentHTML('beforeend', rowHtml(index++));
    });

    rows.addEventListener('click', function (event) {
        if (event.target.closest('.remove-entry')) {
            const row = event.target.closest('.entry-row');
            if (rows.querySelectorAll('.entry-row').length > 1) {
                row.remove();
            }
        }
    });
})();
</script>
@endsection