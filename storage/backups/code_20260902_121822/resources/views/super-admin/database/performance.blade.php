@extends('layouts.admin')
@section('title','Performance — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-graph-up me-2"></i>Database Performance</h1>
        <div class="small text-muted">Query performance, index analysis, N+1 detection</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

{{-- Query Performance --}}
<h5 class="mb-3">Query Performance (24h)</h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Total Queries</div><div class="h4 mb-0">{{ number_format($perf['total_queries']??0) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Slow Queries</div><div class="h4 mb-0 text-{{ ($perf['slow_query_count']??0)>0?'warning':'success' }}">{{ $perf['slow_query_count']??0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Failed Queries</div><div class="h4 mb-0 text-{{ ($perf['failed_query_count']??0)>0?'danger':'success' }}">{{ $perf['failed_query_count']??0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Avg Execution</div><div class="h4 mb-0">{{ number_format($perf['average_execution_time']??0,1) }}ms</div></div></div></div>
</div>

{{-- Slow Queries --}}
@if(!empty($slow_queries))
<h5 class="mb-3">Recent Slow Queries</h5>
<div class="card mb-4"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead><tr><th>Time</th><th>Query</th><th>Duration</th><th>Status</th></tr></thead>
            <tbody>
            @foreach($slow_queries as $q)
            <tr>
                <td class="small">{{ $q->created_at ?? '—' }}</td>
                <td class="small" style="max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ substr($q->query ?? '',0,100) }}</td>
                <td class="small fw-bold text-warning">{{ $q->execution_time ?? '—' }}ms</td>
                <td><span class="badge bg-{{ ($q->status??'')==='success'?'success':'warning' }}">{{ $q->status??'—' }}</span></td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div></div>
@endif

{{-- Index Analysis --}}
<h5 class="mb-3"><i class="bi bi-list-columns-reverse"></i> Index Recommendations <span class="badge bg-warning text-dark">RECOMMENDATION ONLY</span></h5>
<div class="card mb-4"><div class="card-body">
    @if(!empty($indexRecs))
        @foreach($indexRecs as $r)
        <div class="border rounded p-2 mb-2">
            <div class="fw-semibold">{{ $r['table'] }} ({{ $r['columns'] }})</div>
            <div class="small text-muted">Current: {{ implode(', ', $r['current_indexes']) ?: 'none' }} | Benefit: {{ $r['query_benefit'] }} | Impact: {{ $r['estimated_impact'] }} | Risk: {{ $r['duplicate_risk'] }}</div>
            <span class="badge bg-{{ str_contains($r['recommendation'],'CREATE')?'success':(str_contains($r['recommendation'],'DEFER')?'secondary':'warning') }}">{{ $r['recommendation'] }}</span>
            <span class="small text-muted"> — Do NOT create automatically</span>
        </div>
        @endforeach
    @else
        <div class="text-success small">No missing critical indexes</div>
    @endif
</div></div>

{{-- Duplicate Indexes --}}
@if(!empty($dupIndex))
<h5 class="mb-3">Duplicate Index Evidence</h5>
<div class="card mb-4"><div class="card-body">
    @foreach($dupIndex as $d)
    <div class="small">• {{ is_array($d) ? json_encode($d) : $d }}</div>
    @endforeach
    <div class="small text-muted mt-1">Do NOT auto-drop. Review manually.</div>
</div></div>
@endif

{{-- N+1 Detection --}}
<h5 class="mb-3">N+1 Detection</h5>
<div class="card mb-4"><div class="card-body">
    @php $n1Summary = $n1['summary'] ?? []; @endphp
    <div class="row g-3 mb-2">
        <div class="col-md-3"><div class="small text-muted">Confirmed</div><div class="fw-bold {{ ($n1Summary['confirmed']??0)>0?'text-danger':'text-success' }}">{{ $n1Summary['confirmed']??0 }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Suspected</div><div class="fw-bold text-warning">{{ $n1Summary['suspected']??0 }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Review</div><div class="fw-bold text-secondary">{{ $n1Summary['review']??0 }}</div></div>
    </div>
    @if(!empty($n1['findings']))
    <div class="table-responsive mt-2">
        <table class="table table-sm mb-0">
            <thead><tr><th>Model</th><th>Relation</th><th>Classification</th><th>Recommendation</th></tr></thead>
            <tbody>
            @foreach(array_slice($n1['findings'],0,15) as $f)
            <tr>
                <td class="small">{{ $f['model']??'—' }}</td>
                <td class="small">{{ $f['relation']??'—' }}</td>
                <td><span class="badge bg-{{ ($f['classification']??'')==='CONFIRMED'?'danger':(($f['classification']??'')==='SUSPECTED'?'warning':'secondary') }}">{{ $f['classification']??'—' }}</span></td>
                <td class="small">{{ $f['recommendation']??'—' }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div></div>

{{-- Baseline --}}
@if(!empty($baseline))
<h5 class="mb-3">Performance Baseline</h5>
<div class="card mb-4"><div class="card-body row g-3">
    @foreach($baseline as $k=>$v)
    <div class="col-md-3"><div class="small text-muted">{{ ucfirst(str_replace('_',' ',$k)) }}</div><div class="fw-bold">{{ is_array($v)?json_encode($v):$v }}</div></div>
    @endforeach
</div></div>
@endif
@endsection
