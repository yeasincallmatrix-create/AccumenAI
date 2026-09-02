@extends('layouts.standalone')

@section('title', 'SaaS Dashboard — AccumenAI')
@section('page_title', 'SaaS Subscription Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="admin-card p-3 text-center">
            <div class="text-muted small">Total Institutes</div>
            <div class="fs-2 fw-bold">{{ $totalInstitutes }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-success">
            <div class="text-muted small">Active Subscriptions</div>
            <div class="fs-2 fw-bold text-success">{{ $activeCount }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-danger">
            <div class="text-muted small">Expired / No Subscription</div>
            <div class="fs-2 fw-bold text-danger">{{ $expiredCount }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="admin-card p-3 text-center border-primary">
            <div class="text-muted small">Total Revenue</div>
            <div class="fs-2 fw-bold text-primary">{{ number_format($totalRevenue, 2) }} BDT</div>
        </div>
    </div>
</div>

<div class="admin-card mb-4">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-clock-history"></i> Recent Subscriptions
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Institute</th>
                    <th>Package</th>
                    <th>Billing Cycle</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentSubscriptions as $sub)
                    <tr>
                        <td>{{ $sub->institute->name ?? '—' }}</td>
                        <td><span class="badge bg-info">{{ $sub->package->name ?? '—' }}</span></td>
                        <td>{{ ucfirst($sub->billing_cycle ?? '—') }}</td>
                        <td>{{ $sub->start_date ? \Carbon\Carbon::parse($sub->start_date)->format('d M Y') : '—' }}</td>
                        <td>{{ $sub->end_date ? \Carbon\Carbon::parse($sub->end_date)->format('d M Y') : '—' }}</td>
                        <td>
                            @if($sub->end_date && \Carbon\Carbon::parse($sub->end_date)->isFuture())
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-secondary">Expired</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No subscriptions found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="admin-card">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi bi-box-seam"></i> Available Packages
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Slug</th>
                    <th>Monthly Price</th>
                    <th>Yearly Price</th>
                    <th>Max Students</th>
                    <th>Max Teachers</th>
                    <th>Enabled Modules</th>
                </tr>
            </thead>
            <tbody>
                @foreach($packages as $pkg)
                    <tr>
                        <td>{{ $pkg->name }}</td>
                        <td><span class="badge bg-secondary">{{ $pkg->slug }}</span></td>
                        <td>{{ number_format($pkg->price_monthly, 2) }} BDT</td>
                        <td>{{ number_format($pkg->price_yearly, 2) }} BDT</td>
                        <td>{{ $pkg->max_students }}</td>
                        <td>{{ $pkg->max_teachers }}</td>
                        <td>{{ implode(', ', $pkg->enabledModuleKeys()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
