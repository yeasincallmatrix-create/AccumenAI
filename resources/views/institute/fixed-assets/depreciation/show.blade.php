@extends('layouts.standalone')

@section('title', 'Depreciation Run — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Depreciation Run</h4>
    <p>Period {{ $run->period_start->format('Y-m-d') }} to {{ $run->period_end->format('Y-m-d') }}</p>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <span class="badge text-bg-{{ $run->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($run->status) }}</span>
        @if ($run->journal)
            <a href="{{ route('finance.journals.show', $run->journal) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i>View Journal {{ $run->journal->journal_no }}</a>
        @endif
    </div>
</div>

<div class="admin-card mb-3">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="small text-muted">Period Start</div>
            <div>{{ $run->period_start->format('Y-m-d') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Period End</div>
            <div>{{ $run->period_end->format('Y-m-d') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Status</div>
            <div>{{ ucfirst($run->status) }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Created</div>
            <div>{{ $run->created_at?->format('Y-m-d H:i') }}</div>
        </div>
    </div>
</div>

@if ($run->journal)
    <div class="admin-card mb-3">
        <h6 class="card-title">Depreciation Journal ({{ $run->journal->journal_no }})</h6>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th>Memo</th>
                    </tr>
                </thead>
                <tbody>
                    @php $debit = 0; $credit = 0; @endphp
                    @foreach ($run->journal->entries ?? [] as $entry)
                        @php $debit += (float) $entry->debit; $credit += (float) $entry->credit; @endphp
                        <tr>
                            <td>{{ $entry->coa?->code }} — {{ $entry->coa?->name }}</td>
                            <td class="text-end">{{ $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '—' }}</td>
                            <td class="text-end">{{ $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '—' }}</td>
                            <td>{{ $entry->memo ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-semibold">
                        <td>Totals</td>
                        <td class="text-end">{{ number_format($debit, 2) }}</td>
                        <td class="text-end">{{ number_format($credit, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
@endif

@endsection
