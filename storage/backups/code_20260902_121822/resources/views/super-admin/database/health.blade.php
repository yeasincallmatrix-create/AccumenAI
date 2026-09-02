@extends('layouts.admin')
@section('title','Database Health — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-heart-pulse me-2"></i>Database Health</h1>
        <div class="small text-muted">Migrations, tables, seeds, foreign keys, duplicates, tenant isolation</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

@php
    $migrations = $health['checks']['migrations'] ?? [];
    $orphanData = $health['checks']['orphans'] ?? [];
@endphp

{{-- Health Score --}}
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Health Score</div><div class="display-6 fw-bold {{ ($health['score']??0)>=90?'text-success':(($health['score']??0)>=70?'text-warning':'text-danger') }}">{{ $health['score']??0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Status</div><div class="h4 {{ ($health['status']??'')==='HEALTHY'?'text-success':'text-warning' }}">{{ $health['status']??'N/A' }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Missing Tables</div><div class="h4 {{ empty($health['missing_tables']??[])?'text-success':'text-danger' }}">{{ count($health['missing_tables']??[]) }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Orphan Records</div><div class="h4 {{ empty($orphanData['orphans']??[])?'text-success':'text-warning' }}">{{ count($orphanData['orphans']??[]) }}</div></div></div></div>
</div>

{{-- Migrations --}}
<h5 class="mb-3"><i class="bi bi-code-square"></i> Migrations</h5>
<div class="card mb-4"><div class="card-body row g-3">
    <div class="col-md-3"><div class="small text-muted">Applied</div><div class="fw-bold">{{ $migrations['ran'] ?? 0 }}</div></div>
    <div class="col-md-3"><div class="small text-muted">Pending</div><div class="fw-bold {{ count($migrations['pending']??[])>0?'text-danger':'text-success' }}">{{ count($migrations['pending']??[]) }}</div></div>
    <div class="col-md-3"><div class="small text-muted">Status</div><span class="badge bg-{{ ($migrations['healthy']??false)?'success':'danger' }}">{{ ($migrations['healthy']??false)?'PASS':'FAIL' }}</span></div>
    <div class="col-md-3"><div class="small text-muted">Schema</div><div class="small">{{ $health['checks']['schema']['version'] ?? '—' }}</div></div>
    @if(!empty($migrations['pending']))
    <div class="col-12"><div class="small text-muted">Pending migrations:</div>
        @foreach($migrations['pending'] as $p)<div class="small text-danger">• {{ $p }}</div>@endforeach
    </div>
    @endif
</div></div>

{{-- Tables --}}
<h5 class="mb-3"><i class="bi bi-table"></i> Tables</h5>
<div class="card mb-4"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-4"><div class="small text-muted">Expected</div><div class="fw-bold">{{ count($health['expected_tables']??[]) }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Existing</div><div class="fw-bold text-success">{{ count($health['existing_tables']??[]) }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Missing</div><div class="fw-bold {{ count($health['missing_tables']??[])>0?'text-danger':'text-success' }}">{{ count($health['missing_tables']??[]) }}</div></div>
    </div>
    @if(!empty($health['missing_tables']))
    <div class="mt-2">@foreach($health['missing_tables'] as $t)<span class="badge bg-danger me-1">{{ $t }}</span>@endforeach</div>
    @endif
</div></div>

{{-- Seeds --}}
<h5 class="mb-3"><i class="bi bi种子-fill"></i> Seed Integrity</h5>
<div class="card mb-4"><div class="card-body">
    <span class="badge bg-{{ ($seeds['healthy']??false)?'success':'warning' }} mb-2">{{ ($seeds['healthy']??false)?'ALL PRESENT':'ISSUES' }}</span>
    @if(!empty($seeds['missing']))
        @foreach($seeds['missing'] as $m)<div class="small text-warning">Missing: {{ $m }}</div>@endforeach
    @endif
    @if(!empty($seeds['results']))
    <div class="row g-2 mt-1">
        @foreach($seeds['results'] as $name=>$result)
        <div class="col-md-3"><span class="badge bg-{{ ($result['present']??false)?'success':'danger' }}">{{ $name }}</span></div>
        @endforeach
    </div>
    @endif
</div></div>

{{-- Foreign Keys --}}
<h5 class="mb-3"><i class="bi bi-link-45deg"></i> Foreign Keys</h5>
<div class="card mb-4"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-4"><div class="small text-muted">Missing FK</div><div class="fw-bold {{ count($fk['missing']??[])>0?'text-warning':'text-success' }}">{{ count($fk['missing']??[]) }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Incorrect</div><div class="fw-bold {{ count($fk['incorrect']??[])>0?'text-danger':'text-success' }}">{{ count($fk['incorrect']??[]) }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Unsafe</div><div class="fw-bold {{ count($fk['unsafe']??[])>0?'text-danger':'text-success' }}">{{ count($fk['unsafe']??[]) }}</div></div>
    </div>
    @if(!empty($fk['missing']))
    <div class="mt-2 small text-muted">Missing (non-blocking):</div>
    @foreach(array_slice($fk['missing'],0,10) as $f)<div class="small">• {{ $f }}</div>@endforeach
    @if(count($fk['missing'])>10)<div class="small text-muted">... and {{ count($fk['missing'])-10 }} more</div>@endif
    @endif
</div></div>

{{-- Duplicates --}}
<h5 class="mb-3"><i class="bi bi-copy"></i> Duplicates</h5>
<div class="card mb-4"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-4"><div class="small text-muted">Critical</div><div class="fw-bold {{ ($dup['critical']??0)>0?'text-danger':'text-success' }}">{{ $dup['critical']??0 }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Warnings</div><div class="fw-bold {{ ($dup['warnings']??0)>0?'text-warning':'text-success' }}">{{ $dup['warnings']??0 }}</div></div>
        <div class="col-md-4"><div class="small text-muted">Safe</div><div class="fw-bold text-success">{{ ($dup['safe']??true)?'Yes':'No' }}</div></div>
    </div>
</div></div>

{{-- Tenant Isolation --}}
<h5 class="mb-3"><i class="bi bi-person-lock"></i> Tenant Isolation</h5>
<div class="card mb-4"><div class="card-body">
    <div class="row g-3">
        <div class="col-md-3"><div class="small text-muted">Status</div><span class="badge bg-{{ ($tenant['status']??'')==='SECURE'?'success':'danger' }}">{{ $tenant['status']??'N/A' }}</span></div>
        <div class="col-md-3"><div class="small text-muted">Leakage</div><div class="fw-bold {{ ($tenant['leakage']??0)>0?'text-danger':'text-success' }}">{{ $tenant['leakage']??0 }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Cross Queries</div><div class="fw-bold {{ ($tenant['cross_queries']??0)>0?'text-danger':'text-success' }}">{{ $tenant['cross_queries']??0 }}</div></div>
        <div class="col-md-3"><div class="small text-muted">Unauthorized</div><div class="fw-bold {{ ($tenant['unauthorized']??0)>0?'text-danger':'text-success' }}">{{ $tenant['unauthorized']??0 }}</div></div>
    </div>
</div></div>

{{-- Schema Comparison --}}
<h5 class="mb-3"><i class="bi bi bi-arrow-left-right"></i> Schema Comparison</h5>
<div class="card mb-4"><div class="card-body">
    <span class="badge bg-{{ ($schema_compare['mismatch']??false)?'warning':'success' }}">{{ ($schema_compare['mismatch']??false)?'MISMATCH':'COMPATIBLE' }}</span>
    @if(!empty($schema_compare['differences']))
        @foreach($schema_compare['differences'] as $diff)<div class="small text-warning">• {{ $diff }}</div>@endforeach
    @endif
</div></div>
@endsection
