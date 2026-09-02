@extends('layouts.standalone')

@section('title', 'Journal '.$journal->journal_no.' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Journal {{ $journal->journal_no }}</h4>
    <p>{{ $journal->description ?? 'No description' }} · {{ $journal->journal_date }}</p>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        @if ($journal->status === 'draft')
            <form method="POST" action="{{ route('finance.journals.post', $journal) }}" class="d-inline" data-ajax-submit="1" data-confirm="Post this journal to the ledger?">
                @csrf
                <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Post journal</button>
            </form>
            <form method="POST" action="{{ route('finance.journals.void', $journal) }}" class="d-inline" data-ajax-submit="1" data-confirm="Void this draft journal?">
                @csrf
                <button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-x-lg me-1"></i>Void</button>
            </form>
        @endif
        @if ($journal->status === 'posted')
            <form method="POST" action="{{ route('finance.journals.reverse', $journal) }}" class="d-inline" data-ajax-submit="1" data-confirm="Reverse this journal? A reversing journal will be posted.">
                @csrf
                <input type="text" class="form-control form-control-sm d-inline-block me-1" style="width: 220px" name="reason" placeholder="Reason (optional)" maxlength="255">
                <button class="btn btn-outline-warning btn-sm" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Reverse</button>
            </form>
        @endif
        <span class="badge text-bg-{{ $journal->status === 'posted' ? 'success' : ($journal->status === 'draft' ? 'warning' : 'secondary') }}">{{ $journal->status }}</span>
        <span class="badge text-bg-light border">{{ ucfirst($journal->type) }}</span>
    </div>
</div>

@if ($journal->reversal_of)
    <div class="alert alert-warning">
        Reversal of <a href="{{ route('finance.journals.show', $journal->reversalOf) }}">{{ $journal->reversalOf->journal_no }}</a>.
    </div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Party</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                    <th>Memo</th>
                </tr>
            </thead>
            <tbody>
                @php $debit = 0; $credit = 0; @endphp
                @foreach ($journal->entries as $entry)
                    @php $debit += (float) $entry->debit; $credit += (float) $entry->credit; @endphp
                    <tr>
                        <td>{{ $entry->coa?->code }} — {{ $entry->coa?->name }}</td>
                        <td>{{ $entry->party?->name ?? '—' }}</td>
                        <td class="text-end">{{ $entry->debit > 0 ? number_format((float) $entry->debit, 2) : '—' }}</td>
                        <td class="text-end">{{ $entry->credit > 0 ? number_format((float) $entry->credit, 2) : '—' }}</td>
                        <td>{{ $entry->memo ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-semibold">
                    <td colspan="2">Totals</td>
                    <td class="text-end">{{ number_format($debit, 2) }}</td>
                    <td class="text-end">{{ number_format($credit, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="row g-3">
        <div class="col-md-3">
            <div class="small text-muted">Created by</div>
            <div>{{ $journal->creator?->name ?? '—' }} · {{ $journal->created_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Posted by</div>
            <div>{{ $journal->postedBy?->name ?? '—' }} · {{ $journal->posted_at?->format('Y-m-d H:i') }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Period</div>
            <div>{{ $journal->period?->name ?? '—' }}</div>
        </div>
        <div class="col-md-3">
            <div class="small text-muted">Branch</div>
            <div>{{ $journal->branch?->name ?? 'All branches' }}</div>
        </div>
        @if ($journal->reversed_at)
            <div class="col-md-3">
                <div class="small text-muted">Reversed by</div>
                <div>{{ $journal->reversedBy?->name ?? '—' }} · {{ $journal->reversed_at?->format('Y-m-d H:i') }}</div>
            </div>
        @endif
    </div>
</div>

@endsection