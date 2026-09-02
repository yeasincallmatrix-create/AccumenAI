@extends('layouts.institute')
@section('title', 'Certificates — AccumenAI')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Certificates</li>
    </ol>
</nav>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ ($isProfessional ?? false) ? 'Training Certificates' : 'Certificates' }}</h4>
        <p class="text-muted small mb-0">{{ ($isProfessional ?? false) ? 'Generate certificates for trainees who meet attendance & exam criteria.' : 'Issued certificates and requests.' }}</p>
    </div>
    @if(($isProfessional ?? false) && ($trainingBatches ?? collect())->isNotEmpty())
    <form method="GET" action="{{ route('certificates.index') }}" class="d-flex gap-2">
        <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($trainingBatches as $b)
                <option value="{{ $b->id }}" @selected($selectedCertBatchId==$b->id)>{{ $b->name }} ({{ $b->batch_code }})</option>
            @endforeach
        </select>
    </form>
    @endif
</div>

@if(request()->query('popup') && ($isProfessional ?? false) && isset($certTrainees) && $certTrainees->isNotEmpty())
<div class="admin-card mb-4">
    <h6 class="mb-3"><i class="bi bi-patch-check me-1"></i> Generate Certificates — Batch: {{ $trainingBatches->firstWhere('id',$selectedCertBatchId)?->name ?? '' }}</h6>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th><input type="checkbox" id="certCheckAll"></th><th>Trainee</th><th>Attendance</th><th>Exam</th><th>Eligible</th></tr></thead>
            <tbody>
            @foreach($certTrainees as $ct)
                <tr class="{{ $ct->eligible ? '' : 'opacity-50' }}">
                    <td><input type="checkbox" class="form-check-input cert-check" value="{{ $ct->trainee_id }}" {{ $ct->eligible ? 'checked' : '' }}></td>
                    <td class="fw-semibold">{{ trim(($ct->trainee->first_name ?? '').' '.($ct->trainee->last_name ?? '')) ?: 'Trainee #'.$ct->trainee_id }} <div class="small text-muted">{{ $ct->trainee->email ?? '' }}</div></td>
                    <td><span class="badge {{ $ct->attendance >=80 ? 'text-bg-success' : 'text-bg-warning' }}">{{ $ct->attendance }}%</span> {{ $ct->attendance >=80 ? '✓' : '✗ < 80%' }}</td>
                    <td><span class="badge {{ $ct->exam_status=='pass' ? 'text-bg-success' : 'text-bg-danger' }}">{{ ucfirst($ct->exam_status) }}</span></td>
                    <td>{!! $ct->eligible ? '<span class="badge text-bg-primary">Eligible</span>' : '<span class="badge text-bg-secondary">Not eligible</span>' !!}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3 d-flex gap-2 justify-content-end">
        <button class="btn btn-outline-primary btn-sm" onclick="alert('Generating '+document.querySelectorAll('.cert-check:checked').length+' certificates…')">Generate Selected</button>
        <button class="btn btn-primary btn-sm" onclick="document.querySelectorAll('.cert-check').forEach(c=>c.checked=true); alert('Generating ALL eligible certificates…')">Generate All</button>
    </div>
</div>
@elseif(request()->query('popup') && ($isProfessional ?? false))
<div class="admin-card mb-4 text-center text-muted py-3 small">No trainees in selected batch or no completed batches.</div>
@endif
<script>
document.getElementById('certCheckAll')?.addEventListener('change', function(){ document.querySelectorAll('.cert-check').forEach(c=>c.checked=this.checked); });
</script>

@livewire('certificate-list')

@endsection