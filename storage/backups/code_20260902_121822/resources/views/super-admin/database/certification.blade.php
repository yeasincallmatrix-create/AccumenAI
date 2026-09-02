@extends('layouts.admin')
@section('title','Database Certification — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-patch-check me-2"></i>Database Certification</h1>
        <div class="small text-muted">Enterprise database certification scorecard</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@php
    $score = $cert['overall'] ?? 0;
    $status = $cert['status'] ?? 'NOT CERTIFIED';
    $checks = $cert['checks'] ?? [];
    $scores = $cert['scores'] ?? [];
@endphp

{{-- Overall Score --}}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <div class="small text-muted mb-2">Overall Score</div>
                <div class="display-1 fw-bold {{ $score>=90?'text-success':($score>=70?'text-warning':'text-danger') }}">{{ $score }}</div>
                <div class="h5 text-muted">/ 100</div>
                <div class="mt-3">
                    <span class="badge fs-6 px-4 py-2 bg-{{ $status==='CERTIFIED'?'success':($status==='CERTIFIED WITH WARNINGS'?'warning':'danger') }}">{{ $status }}</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-body">
                <h6>Category Scores</h6>
                <div class="row g-2">
                    @foreach($scores as $cat=>$s)
                    <div class="col-md-4 col-lg-3">
                        <div class="border rounded p-2 text-center">
                            <div class="small text-muted">{{ ucfirst(str_replace('_',' ',$cat)) }}</div>
                            <div class="fw-bold {{ ($s['score']??0)>=90?'text-success':(($s['score']??0)>=70?'text-warning':'text-danger') }}">{{ $s['score']??0 }}</div>
                            <span class="badge bg-{{ ($s['status']??'')==='PASS'?'success':(($s['status']??'')==='WARNING'?'warning':'danger') }}">{{ $s['status']??'N/A' }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Critical Issues --}}
@if($status === 'NOT CERTIFIED')
<h5 class="mb-3 text-danger"><i class="bi bi-exclamation-triangle"></i> Critical Issues</h5>
<div class="card mb-4 border-danger"><div class="card-body">
    @foreach($checks as $k=>$v)
        @if(is_array($v) && ($v['status']??'') === 'FAIL')
        <div class="small text-danger">• <strong>{{ ucfirst(str_replace('_',' ',$k)) }}</strong>: {{ $v['message'] ?? $v['detail'] ?? 'Failed' }}</div>
        @endif
    @endforeach
</div></div>
@endif

{{-- Warnings --}}
<h5 class="mb-3 text-warning"><i class="bi bi-exclamation-circle"></i> Warnings</h5>
<div class="card mb-4 border-warning"><div class="card-body">
    @php $hasWarnings = false; @endphp
    @foreach($checks as $k=>$v)
        @if(is_array($v) && ($v['status']??'') === 'WARNING')
            @php $hasWarnings = true; @endphp
            <div class="small text-warning">• <strong>{{ ucfirst(str_replace('_',' ',$k)) }}</strong>: {{ $v['message'] ?? $v['detail'] ?? 'Warning' }}</div>
        @endif
    @endforeach
    @if(!$hasWarnings)
        <div class="small text-muted">No warnings</div>
    @endif
</div></div>

{{-- Backup/Recovery Status --}}
<h5 class="mb-3"><i class="bi bi-cloud-upload"></i> Backup & Recovery</h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Off-site</div><span class="badge bg-{{ ($backup_storage['offsite']['status']??'NOT_CONFIGURED')==='PASS'?'success':'secondary' }}">{{ $backup_storage['offsite']['status']??'NOT_CONFIGURED' }}</span></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Encryption</div><span class="badge bg-{{ ($backup_storage['encryption']['status']??'NOT_CONFIGURED')==='ACTIVE'?'success':'secondary' }}">{{ $backup_storage['encryption']['status']??'NOT_CONFIGURED' }}</span></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">RPO</div><span class="badge bg-{{ ($backup_health['checks']['rpo']['status']??'FAIL')==='PASS'?'success':'danger' }}">{{ $backup_health['checks']['rpo']['status']??'FAIL' }}</span></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">RTO</div><span class="badge bg-{{ ($rto['rto_status']??'NOT_CONFIGURED')==='PASS'?'success':'secondary' }}">{{ $rto['rto_status']??'NOT_CONFIGURED' }}</span></div></div></div>
</div>

{{-- Passed Checks --}}
<h5 class="mb-3 text-success"><i class="bi bi-check-circle"></i> Passed Checks</h5>
<div class="card mb-4"><div class="card-body">
    @php $passedCount = 0; @endphp
    @foreach($checks as $k=>$v)
        @if(is_array($v) && ($v['status']??'') === 'PASS')
            @php $passedCount++; @endphp
            <span class="badge bg-success me-1 mb-1">{{ ucfirst(str_replace('_',' ',$k)) }}</span>
        @endif
    @endforeach
    <div class="small text-muted mt-2">{{ $passedCount }} checks passed</div>
</div></div>
@endsection
