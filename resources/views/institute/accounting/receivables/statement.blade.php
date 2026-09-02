@extends('layouts.standalone')

@section('title', 'Customer Statement — AccumenAI')
@section('page_title', 'Receivables')

@push('styles')
<style>
    @media print {
        .topbar, .standalone-heading .btn, .filter-card { display: none !important; }
        .standalone-heading h4 { font-size: 1.4rem; }
        .admin-card { box-shadow: none !important; border: none !important; }
    }
</style>
@endpush

@section('content')

<div class="standalone-heading">
    <h4>Customer Statement — {{ $party->name }}</h4>
    <p>Account statement for <strong>{{ $party->name }}</strong> as of {{ now()->format('d M Y') }}.</p>
    <a href="{{ route('accounting.receivables.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Receivables</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Total Receivable</small>
            <h4 class="mb-0 mt-1">{{ number_format((float) $statement['balance'], 2) }}</h4>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card text-center">
            <small class="text-muted">Transactions</small>
            <h4 class="mb-0 mt-1">{{ $statement['transactions']->count() }}</h4>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <h6 class="card-title">Aging Summary</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-end">Current</th>
                    <th class="text-end">31–60</th>
                    <th class="text-end">61–90</th>
                    <th class="text-end">90+</th>
                    <th class="text-end fw-bold">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="text-end">{{ number_format((float) $statement['aging']['current'], 2) }}</td>
                    <td class="text-end">{{ number_format((float) $statement['aging']['31_60'], 2) }}</td>
                    <td class="text-end">{{ number_format((float) $statement['aging']['61_90'], 2) }}</td>
                    <td class="text-end">{{ number_format((float) $statement['aging']['91_plus'], 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format((float) $statement['balance'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <h6 class="card-title">Transactions</h6>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Journal #</th>
                    <th>Description</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end">Credit</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($statement['transactions'] as $txn)
                    <tr>
                        <td>{{ $txn->journal_date }}</td>
                        <td>{{ $txn->journal_no }}</td>
                        <td>{{ $txn->description }}</td>
                        <td class="text-end">{{ $txn->debit ? number_format((float) $txn->debit, 2) : '—' }}</td>
                        <td class="text-end">{{ $txn->credit ? number_format((float) $txn->credit, 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">No transactions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
