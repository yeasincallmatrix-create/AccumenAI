@extends('layouts.institute')

@section('title', 'TDS Certificates Received')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

@include('settings.tds._subnav')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-file-earmark-check me-2"></i>Certificates Received</h4>
        <p class="page-header-desc mb-0">TDS certificates received from customers/deductors.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('settings.tds-certificates-received.create') }}" class="btn btn-primary rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Record Certificate</a>
        <a href="{{ route('settings.tds-receivable.index') }}" class="btn btn-outline-secondary rounded-pill px-3">Back to Receivables</a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="admin-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Certificates (FY {{ $fy }})</h5>
        <form class="d-flex gap-2" method="GET">
            <select name="fy" class="form-select form-select-sm" onchange="this.form.submit()">
                @for($y = (int)date('Y'); $y >= (int)date('Y')-3; $y--)
                    <option value="{{ $y }}-{{ $y+1 }}" @selected($fy === $y.'-'.($y+1))>{{ $y }}-{{ $y+1 }}</option>
                @endfor
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Certificate No</th>
                    <th>Party</th>
                    <th>Date</th>
                    <th>Tax Period</th>
                    <th class="text-end">Base Amount</th>
                    <th class="text-end">TDS</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certs as $c)
                <tr>
                    <td><code>{{ $c->certificate_no }}</code></td>
                    <td>{{ $c->party?->name ?? '-' }}</td>
                    <td>{{ $c->certificate_date->format('d M Y') }}</td>
                    <td>{{ $c->tax_period }}</td>
                    <td class="text-end">{{ number_format($c->total_base, 2) }}</td>
                    <td class="text-end fw-bold">{{ number_format($c->total_tds, 2) }}</td>
                    <td>
                        @if($c->status === 'received')
                            <span class="badge bg-warning">Received</span>
                        @elseif($c->status === 'verified')
                            <span class="badge bg-success">Verified</span>
                        @else
                            <span class="badge bg-danger">Disputed</span>
                        @endif
                    </td>
                    <td>
                        @if($c->status === 'received')
                        <form method="POST" action="{{ route('settings.tds-certificates-received.verify', $c) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-success">Verify</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">No certificates received yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $certs->links() }}
</div>

@endsection
