@extends('layouts.standalone')

@section('title', 'Dealership Dashboard — AccumenAI')
@section('page_title', 'Dashboard')

@section('content')
<div class="standalone-heading">
    <h4>ড্যাশবোর্ড (Dealership KPIs)</h4>
</div>

@include('dealership.reports._nav')

<div class="filter-card mb-3">
    <form method="GET" action="{{ route('dealership.dashboard.index') }}" class="row g-2">
        <div class="col-md-2"><label class="form-label">From</label><x-tdate-input class="form-control form-control-sm" name="from" :value="$filters['from'] ?? null" /></div>
        <div class="col-md-2"><label class="form-label">To</label><x-tdate-input class="form-control form-control-sm" name="to" :value="$filters['to'] ?? null" /></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search"></i> Filter</button></div>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Total Orders / মোট অর্ডার</small><h4 class="mb-0">{{ $kpis['total_orders'] }}</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Total Sales / মোট বিক্রয়</small><h4 class="mb-0">{{ number_format($kpis['total_sales'], 2) }}</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Collected / আদায়</small><h4 class="mb-0">{{ number_format($kpis['total_collected'], 2) }}</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Collection Rate</small><h4 class="mb-0">{{ $kpis['collection_rate'] }}%</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Pending Commission</small><h4 class="mb-0">{{ number_format($kpis['pending_commission'], 2) }}</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Target / Achieved</small><h4 class="mb-0">{{ number_format($kpis['target_total'], 2) }} / {{ number_format($kpis['achieved_total'], 2) }}</h4></div></div></div>
    <div class="col-md-3"><div class="admin-card card"><div class="card-body"><small class="text-muted">Present Today / আজ উপস্থিত</small><h4 class="mb-0">{{ $kpis['present_today'] }}</h4></div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-6"><div class="admin-card card"><div class="card-header">Sales Trend / বিক্রয় প্রবণতা</div><div class="card-body"><canvas id="salesTrendChart" height="120"></canvas></div></div></div>
    <div class="col-md-6"><div class="admin-card card"><div class="card-header">Target vs Achieved / টার্গেট বনাম অর্জন</div><div class="card-body"><canvas id="targetChart" height="120"></canvas></div></div></div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    var sales = {{ number_format($kpis['total_sales'], 2, '.', '') }};
    var collected = {{ number_format($kpis['total_collected'], 2, '.', '') }};
    var target = {{ number_format($kpis['target_total'], 2, '.', '') }};
    var achieved = {{ number_format($kpis['achieved_total'], 2, '.', '') }};

    var st = document.getElementById('salesTrendChart');
    if (st && window.Chart) {
        new Chart(st, { type: 'bar', data: { labels: ['Sales', 'Collected'], datasets: [{ data: [sales, collected] }] }, options: { plugins: { legend: { display: false } } } });
    }
    var tc = document.getElementById('targetChart');
    if (tc && window.Chart) {
        new Chart(tc, { type: 'bar', data: { labels: ['Target', 'Achieved'], datasets: [{ data: [target, achieved] }] }, options: { plugins: { legend: { display: false } } } });
    }
})();
</script>
@endpush
