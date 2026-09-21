@extends('layouts.institute')

@section('title', tenant_tds_label() . ' Certificates')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
</style>
@endpush

@section('content')

@include('settings.tds._subnav')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
    <div class="page-header-text">
        <h4 class="page-header-title"><i class="bi bi-file-earmark-check me-2"></i>{{ tenant_tds_label() }} Certificates</h4>
        <p class="page-header-desc mb-0">Certificates issued for {{ tenant_tds_label() }} deductions deposited.</p>
    </div>
    <a href="{{ route('settings.tds.index') }}" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-arrow-left me-1"></i>Back to {{ tenant_tds_label() }}</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="admin-card p-4">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Certificate No</th>
                    <th>Financial Year</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($certificates as $cert)
                <tr>
                    <td><code>{{ $cert->certificate_no }}</code></td>
                    <td>{{ $cert->financial_year }}</td>
                    <td>{{ $cert->issue_date->format('d M Y') }}</td>
                    <td>
                        @if($cert->status === 'issued')
                            <span class="badge bg-success">Issued</span>
                        @elseif($cert->status === 'draft')
                            <span class="badge bg-secondary">Draft</span>
                        @else
                            <span class="badge bg-danger">Cancelled</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">No certificates generated yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $certificates->links() }}
</div>

@endsection
