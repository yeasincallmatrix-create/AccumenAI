@extends('layouts.institute')

@section('title', 'Dividend ' . $dividend->reference_no)

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
        <h4 class="page-header-title">{{ $dividend->reference_no }} <span class="badge bg-{{ $dividend->status === 'paid' ? 'success' : ($dividend->status === 'draft' ? 'secondary' : 'warning') }}">{{ ucfirst($dividend->status) }}</span></h4>
        <p class="page-header-desc mb-0">FY {{ $dividend->financial_year }} • Declared {{ $dividend->declared_date?->format('d M Y') }} • {{ number_format($dividend->per_share_amount, 4) }}/share over {{ number_format($dividend->total_shares) }} shares</p>
    </div>
    <a href="{{ route('settings.dividend.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->has('error'))
    <div class="alert alert-danger">{{ $errors->first('error') }}</div>
@endif

<div class="row g-3 mb-3">
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Total Dividend</div><div class="h4 mb-0">{{ number_format($dividend->total_dividend, 2) }}</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Total Tax (10%)</div><div class="h4 mb-0">{{ number_format($dividend->total_tax, 2) }}</div></div></div>
    <div class="col-md-4"><div class="admin-card p-3"><div class="text-muted small">Total Net</div><div class="h4 mb-0">{{ number_format($dividend->total_net, 2) }}</div></div></div>
</div>

@if($dividend->isDraft())
    <div class="admin-card p-3 mb-3 d-flex gap-2">
        <form method="POST" action="{{ route('settings.dividend.mark-declared', $dividend) }}">
            @csrf
            <button class="btn btn-primary">Mark as Declared</button>
        </form>
        <form method="POST" action="{{ route('settings.dividend.cancel', $dividend) }}" onsubmit="return confirm('Cancel this dividend?')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger">Cancel Dividend</button>
        </form>
    </div>
@elseif($dividend->isDeclared())
    <div class="admin-card p-3 mb-3">
        <form method="POST" action="{{ route('settings.dividend.pay-all', $dividend) }}" class="d-flex gap-2 align-items-end flex-wrap">
            @csrf
            <div>
                <label class="form-label mb-1">Payment Method</label>
                <input type="text" name="payment_method" class="form-control form-control-sm" placeholder="bank">
            </div>
            <button class="btn btn-success">Pay All Pending</button>
        </form>
    </div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>Shareholder</th><th class="text-end">Shares</th><th class="text-end">Gross</th><th class="text-end">Tax %</th><th class="text-end">Tax</th><th class="text-end">Net</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
                @foreach($dividend->payouts as $p)
                    <tr>
                        <td>{{ $p->shareholder?->name }}</td>
                        <td class="text-end">{{ $p->shares }}</td>
                        <td class="text-end">{{ number_format($p->gross_amount, 2) }}</td>
                        <td class="text-end">{{ $p->tax_rate }}%</td>
                        <td class="text-end">{{ number_format($p->tax_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($p->net_amount, 2) }}</td>
                        <td><span class="badge bg-{{ $p->status === 'paid' ? 'success' : 'warning' }}">{{ ucfirst($p->status) }}</span></td>
                        <td class="text-end">
                            @if($p->status === 'pending')
                                <form method="POST" action="{{ route('settings.dividend.payouts.pay', [$dividend, $p]) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">Pay</button>
                                </form>
                            @else
                                <span class="text-muted small">{{ $p->paid_date?->format('d M Y') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection
