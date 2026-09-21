@extends('layouts.standalone')

@section('title', 'Statement ' . $statement->id . ' — AccumenAI')
@section('page_title', 'Accounting')

@section('content')

<div class="standalone-heading">
    <h4>Statement #{{ $statement->id }}</h4>
    <p>
        {{ $statement->bankAccount?->name ?? 'Unknown' }} · {{ $statement->statement_date?->format('d M Y') }}
        · <span class="badge text-bg-{{ $statement->status === 'imported' ? 'success' : 'primary' }}">{{ $statement->status }}</span>
        @if($statement->original_filename)
            · <span class="badge text-bg-light border">{{ strtoupper($statement->import_source ?? 'csv') }}</span>
        @endif
    </p>
    <div class="d-flex gap-2 flex-wrap">
        @if($summary['unmatched'] > 0)
            <form method="POST" action="{{ route('accounting.bank-feed.auto-match', $statement) }}" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-lightning me-1"></i>Auto-Match ({{ $summary['unmatched'] }})</button>
            </form>
            <form method="POST" action="{{ route('accounting.bank-feed.apply-rules', $statement) }}" class="d-inline">
                @csrf
                <button class="btn btn-info btn-sm" type="submit"><i class="bi bi-gear me-1"></i>Apply Rules</button>
            </form>
        @endif
        <a href="{{ route('accounting.bank-feed.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Total</small><h6 class="mb-0 mt-1">{{ $summary['total'] }}</h6></div></div>
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Unmatched</small><h6 class="mb-0 mt-1 text-warning">{{ $summary['unmatched'] }}</h6></div></div>
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Auto-Matched</small><h6 class="mb-0 mt-1 text-success">{{ $summary['auto_matched'] }}</h6></div></div>
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Rule-Matched</small><h6 class="mb-0 mt-1 text-info">{{ $summary['rule_matched'] }}</h6></div></div>
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Manual</small><h6 class="mb-0 mt-1 text-primary">{{ $summary['manual_matched'] }}</h6></div></div>
    <div class="col-md-2"><div class="admin-card text-center"><small class="text-muted text-uppercase">Ignored</small><h6 class="mb-0 mt-1 text-secondary">{{ $summary['ignored'] }}</h6></div></div>
</div>

@if($summary['unmatched'] > 0)
<div class="admin-card mb-3">
    <form method="POST" action="{{ route('accounting.bank-feed.bulk-categorize', $statement) }}" id="bulkForm">
        @csrf
        <div class="d-flex gap-2 align-items-center mb-2 flex-wrap">
            <strong>Bulk Actions:</strong>
            <select class="form-select form-select-sm" style="width:300px" name="account_id" id="bulkAccount">
                <option value="">— Select GL Account —</option>
                @foreach ($accounts as $acct)
                    <option value="{{ $acct->id }}">{{ $acct->code }} — {{ $acct->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Categorize selected lines?')"><i class="bi bi-check-lg me-1"></i>Categorize Selected</button>
        </div>
        <input type="hidden" name="line_ids[]" id="bulkLineIds" value="">
    </form>
    <form method="POST" action="{{ route('accounting.bank-feed.bulk-ignore', $statement) }}" id="bulkIgnoreForm">
        @csrf
        <input type="hidden" name="line_ids[]" id="bulkIgnoreLineIds" value="">
        <button type="submit" class="btn btn-outline-secondary btn-sm" onclick="return confirm('Ignore selected lines?')"><i class="bi bi-slash-circle me-1"></i>Ignore Selected</button>
    </form>
</div>
@endif

<div class="admin-card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:30px"><input type="checkbox" id="selectAll"></th>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Reference</th>
                    <th>Counterparty</th>
                    <th class="text-end">Amount</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Confidence</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statement->lines as $line)
                    <tr class="{{ $line->category_status === 'unmatched' ? 'table-warning' : '' }}">
                        <td>
                            @if($line->category_status === 'unmatched')
                                <input type="checkbox" class="line-check" value="{{ $line->id }}">
                            @endif
                        </td>
                        <td>{{ $line->transaction_date?->format('d M Y') }}</td>
                        <td>{{ Str::limit($line->description, 50) }}</td>
                        <td>{{ $line->reference ?? '—' }}</td>
                        <td>{{ $line->counterparty ?? '—' }}</td>
                        <td class="text-end fw-semibold {{ $line->type === 'deposit' ? 'text-success' : 'text-danger' }}">
                            {{ $line->type === 'deposit' ? '+' : '-' }}{{ number_format($line->amount, 2) }}
                        </td>
                        <td><span class="badge text-bg-{{ $line->type === 'deposit' ? 'success' : 'danger' }}">{{ $line->type }}</span></td>
                        <td>
                            @php
                                $statusColors = [
                                    'unmatched' => 'warning',
                                    'auto_matched' => 'success',
                                    'rule_matched' => 'info',
                                    'manual_matched' => 'primary',
                                    'ignored' => 'secondary',
                                ];
                            @endphp
                            <span class="badge text-bg-{{ $statusColors[$line->category_status] ?? 'secondary' }}">{{ str_replace('_', ' ', $line->category_status) }}</span>
                        </td>
                        <td>{{ $line->match_confidence ? $line->match_confidence . '%' : '—' }}</td>
                        <td class="text-end">
                            @if($line->category_status === 'unmatched')
                                <button class="btn btn-outline-primary btn-sm categorize-btn"
                                    data-line-id="{{ $line->id }}"
                                    data-description="{{ e($line->description) }}"
                                    data-bs-toggle="modal" data-bs-target="#categorizeModal"
                                    title="Categorize"><i class="bi bi-tag"></i></button>
                            @endif
                            @if($line->matched_je_id)
                                <span class="small text-muted">JE #{{ $line->matched_je_id }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No statement lines.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Categorize Modal -->
<div class="modal fade" id="categorizeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="categorizeForm">
                @csrf
                <div class="modal-header">
                    <h6 class="modal-title">Categorize Transaction</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" id="categorizeDesc"></p>
                    <div class="mb-3">
                        <label class="form-label">GL Account <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="account_id" required>
                            <option value="">Select account...</option>
                            @foreach ($accounts as $acct)
                                <option value="{{ $acct->id }}">{{ $acct->code }} — {{ $acct->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Narration</label>
                        <input type="text" class="form-control form-control-sm" name="narration" maxlength="500">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="create_rule" value="1" id="createRule">
                        <label class="form-check-label" for="createRule">Create rule for future transactions like this</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Categorize</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
(function() {
    const selectAll = document.getElementById('selectAll');
    const checks = document.querySelectorAll('.line-check');
    const bulkIds = document.getElementById('bulkLineIds');
    const ignoreIds = document.getElementById('bulkIgnoreLineIds');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checks.forEach(c => c.checked = this.checked);
            updateBulkIds();
        });
    }
    checks.forEach(c => c.addEventListener('change', updateBulkIds));

    function updateBulkIds() {
        const selected = [...document.querySelectorAll('.line-check:checked')].map(c => c.value);
        if (bulkIds) bulkIds.value = selected.join(',');
        if (ignoreIds) ignoreIds.value = selected.join(',');
    }

    document.querySelectorAll('.categorize-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const lineId = this.dataset.lineId;
            const desc = this.dataset.description;
            document.getElementById('categorizeForm').action = '{{ url("accounting/bank-feed/line") }}/' + lineId + '/categorize';
            document.getElementById('categorizeDesc').textContent = desc;
        });
    });
})();
</script>
@endsection
