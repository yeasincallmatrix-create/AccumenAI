@extends('layouts.admin')
@section('title','Integrity & Security — Super Admin')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h4 mb-1"><i class="bi bi-shield-check me-2"></i>Integrity & Security</h1>
        <div class="small text-muted">Tenant isolation, referential integrity, data safety</div>
    </div>
    <a href="{{ route('super-admin.database.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div class="alert alert-info small"><i class="bi bi-eye"></i> All sections are READ ONLY. No automatic cleanup or modification.</div>

{{-- Tenant Isolation --}}
<h5 class="mb-3"><i class="bi bi-person-lock"></i> Tenant Isolation</h5>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Status</div><span class="badge bg-{{ ($tenant['status']??'')==='SECURE'?'success':'danger' }} fs-6">{{ $tenant['status']??'N/A' }}</span></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Leakage</div><div class="h3 mb-0 {{ ($tenant['leakage']??0)>0?'text-danger':'text-success' }}">{{ $tenant['leakage']??0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Cross Queries</div><div class="h3 mb-0 {{ ($tenant['cross_queries']??0)>0?'text-danger':'text-success' }}">{{ $tenant['cross_queries']??0 }}</div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Unauthorized</div><div class="h3 mb-0 {{ ($tenant['unauthorized']??0)>0?'text-danger':'text-success' }}">{{ $tenant['unauthorized']??0 }}</div></div></div></div>
</div>

{{-- Referential Integrity --}}
<h5 class="mb-3"><i class="bi bi-link-45deg"></i> Referential Integrity</h5>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Missing FK</div><div class="h3 mb-0 {{ count($fk['missing']??[])>0?'text-warning':'text-success' }}">{{ count($fk['missing']??[]) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Incorrect</div><div class="h3 mb-0 {{ count($fk['incorrect']??[])>0?'text-danger':'text-success' }}">{{ count($fk['incorrect']??[]) }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Unsafe</div><div class="h3 mb-0 {{ count($fk['unsafe']??[])>0?'text-danger':'text-success' }}">{{ count($fk['unsafe']??[]) }}</div></div></div></div>
</div>
@if(!empty($fk['missing']))
<div class="card mb-4"><div class="card-body">
    <div class="small fw-bold text-muted mb-1">Missing Foreign Keys (non-blocking):</div>
    @foreach(array_slice($fk['missing'],0,15) as $f)<div class="small">• {{ $f }}</div>@endforeach
    @if(count($fk['missing'])>15)<div class="small text-muted">... and {{ count($fk['missing'])-15 }} more</div>@endif
</div></div>
@endif

{{-- Duplicates --}}
<h5 class="mb-3"><i class="bi bi-copy"></i> Duplicate Data</h5>
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Critical</div><div class="h3 mb-0 {{ ($dup['critical']??0)>0?'text-danger':'text-success' }}">{{ $dup['critical']??0 }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Warnings</div><div class="h3 mb-0 text-{{ ($dup['warnings']??0)>0?'warning':'success' }}">{{ $dup['warnings']??0 }}</div></div></div></div>
    <div class="col-md-4"><div class="card border-0 shadow-sm"><div class="card-body text-center"><div class="small text-muted">Safe</div><div class="h3 mb-0 text-success">{{ ($dup['safe']??true)?'Yes':'No' }}</div></div></div></div>
</div>

{{-- Soft Delete --}}
<h5 class="mb-3"><i class="bi bi-trash"></i> Soft Delete Integrity</h5>
<div class="card mb-4"><div class="card-body">
    @php $softDelete = $consistency['soft_delete'] ?? []; @endphp
    <span class="badge bg-{{ empty($softDelete['issues']??[])?'success':'warning' }}">{{ empty($softDelete['issues']??[])?'CLEAN':'ISSUES' }}</span>
    @if(!empty($softDelete['issues']))
        @foreach($softDelete['issues'] as $i)<div class="small text-warning">• {{ is_array($i)?json_encode($i):$i }}</div>@endforeach
    @endif
</div></div>

{{-- Consistency --}}
<h5 class="mb-3"><i class="bi bi-check-circle"></i> Data Consistency</h5>
<div class="card mb-4"><div class="card-body">
    <span class="badge bg-{{ ($consistency['overall']??'')==='CLEAN'?'success':'warning' }}">{{ $consistency['overall']??'N/A' }}</span>
    @if(!empty($consistency['issues']))
        @foreach($consistency['issues'] as $i)<div class="small text-warning">• {{ is_array($i)?json_encode($i):$i }}</div>@endforeach
    @endif
</div></div>

{{-- Accounting --}}
<h5 class="mb-3"><i class="bi bi-calculator"></i> Accounting Integrity</h5>
<div class="card mb-4"><div class="card-body">
    <span class="badge bg-{{ ($accounting['healthy']??false)?'success':'danger' }}">{{ ($accounting['healthy']??false)?'HEALTHY':'ISSUES' }}</span>
    @if(!empty($accounting['issues']))
        @foreach($accounting['issues'] as $i)<div class="small text-danger">• {{ is_array($i)?json_encode($i):$i }}</div>@endforeach
    @endif
</div></div>

{{-- Inventory --}}
<h5 class="mb-3"><i class="bi bi-box"></i> Inventory Integrity</h5>
<div class="card mb-4"><div class="card-body">
    <span class="badge bg-{{ ($inventory['healthy']??false)?'success':'danger' }}">{{ ($inventory['healthy']??false)?'HEALTHY':'ISSUES' }}</span>
    @if(!empty($inventory['issues']))
        @foreach($inventory['issues'] as $i)<div class="small text-danger">• {{ is_array($i)?json_encode($i):$i }}</div>@endforeach
    @endif
</div></div>
@endsection
