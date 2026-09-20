@extends('layouts.institute')

@section('title', 'Share Capital')

@push('styles')
<style>
  /* Match settings index: hide navbar/sidebar on settings sub-pages */
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-bank me-2"></i>Share Capital</h4>
        <p class="page-header-desc mb-0">Authorized, issued and paid-up capital with issuance history.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.share-capital.settings') }}" class="btn btn-outline-secondary rounded-pill px-3">Settings</a>
        <a href="{{ route('settings.share-capital.certificates') }}" class="btn btn-outline-primary rounded-pill px-3">Certificates</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->has('error'))
    <div class="alert alert-danger">{{ $errors->first('error') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Authorized Capital</div><div class="h4 mb-0">{{ number_format($summary['authorized_capital'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Issued Capital</div><div class="h4 mb-0">{{ number_format($summary['issued_capital'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Paid-up Capital</div><div class="h4 mb-0">{{ number_format($summary['paid_up_capital'], 2) }}</div></div></div>
    <div class="col-md-3"><div class="admin-card p-3"><div class="text-muted small">Shares Outstanding</div><div class="h4 mb-0">{{ number_format($summary['shares_outstanding']) }}</div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="admin-card p-4">
            <h5>Issue New Shares</h5>
            <form method="POST" action="{{ route('settings.share-capital.issue') }}">
                @csrf
                <div class="mb-2">
                    <label class="form-label">Shareholder *</label>
                    <select name="shareholder_id" class="form-select" required>
                        @foreach(\App\Models\Shareholder::where('institute_id', tenant_id())->orderBy('name')->get() as $sh)
                            <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->shares }} shares)</option>
                        @endforeach
                    </select>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-2"><label class="form-label">Shares *</label><input type="number" name="shares" class="form-control" min="1" required></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Face Value *</label><input type="number" step="0.01" name="face_value" class="form-control" value="{{ $summary['share_face_value'] }}" required></div>
                    <div class="col-md-4 mb-2"><label class="form-label">Premium/Share</label><input type="number" step="0.01" name="premium_per_share" class="form-control" value="0"></div>
                </div>
                <button class="btn btn-primary mt-2">Issue Shares</button>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="admin-card p-4">
            <h5>Transfer Shares</h5>
            <form method="POST" action="{{ route('settings.share-capital.transfer') }}">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <label class="form-label">From *</label>
                        <select name="from_shareholder_id" class="form-select" required>
                            @foreach(\App\Models\Shareholder::where('institute_id', tenant_id())->orderBy('name')->get() as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }} ({{ $sh->shares }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-2">
                        <label class="form-label">To *</label>
                        <select name="to_shareholder_id" class="form-select" required>
                            @foreach(\App\Models\Shareholder::where('institute_id', tenant_id())->orderBy('name')->get() as $sh)
                                <option value="{{ $sh->id }}">{{ $sh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-2"><label class="form-label">Shares *</label><input type="number" name="shares" class="form-control" min="1" required></div>
                    <div class="col-md-6 mb-2"><label class="form-label">Face Value *</label><input type="number" step="0.01" name="face_value" class="form-control" value="{{ $summary['share_face_value'] }}" required></div>
                </div>
                <button class="btn btn-warning mt-2">Transfer Shares</button>
            </form>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Date</th><th>Type</th><th>From / To</th><th class="text-end">Shares</th><th class="text-end">Amount</th><th>Certificate</th></tr></thead>
            <tbody>
                @forelse($transactions as $tx)
                    <tr>
                        <td>{{ $tx->transaction_date?->format('d M Y') }}</td>
                        <td><span class="badge bg-info">{{ ucfirst($tx->type) }}</span></td>
                        <td>
                            @if($tx->type === 'transfer')
                                {{ $tx->fromShareholder?->name }} → {{ $tx->toShareholder?->name }}
                            @else
                                {{ $tx->shareholder?->name }}
                            @endif
                        </td>
                        <td class="text-end">{{ $tx->shares }}</td>
                        <td class="text-end">{{ number_format($tx->total_amount, 2) }}</td>
                        <td>{{ $tx->certificate_no }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-3 text-muted">No transactions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($transactions->hasPages())
        <div class="p-2 border-top">{{ $transactions->links() }}</div>
    @endif
</div>

@endsection
