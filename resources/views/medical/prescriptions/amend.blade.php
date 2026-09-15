@extends('layouts.institute')

@section('title', 'Amend Prescription — AccumenAI')

@section('content')
@push('styles')
<style>
#rx-items-body tr.rx-dragging { opacity: .5; }
#rx-items-body tr.rx-drop-before { box-shadow: inset 0 2px 0 0 var(--bs-primary); }
#rx-items-body tr.rx-drop-after { box-shadow: inset 0 -2px 0 0 var(--bs-primary); }
.rx-drag-handle { cursor: grab; display: inline-flex; align-items: stretch; border: 1px solid var(--bs-border-color); border-radius: .375rem; background: var(--bs-tertiary-bg, #f8f9fa); color: #6c757d; touch-action: none; user-select: none; overflow: hidden; height: 28px; }
.rx-drag-handle > i { display: inline-flex; align-items: center; padding: 0 .3rem; }
.rx-drag-handle .rx-order { display: inline-flex; align-items: center; justify-content: center; min-width: 1.9em; padding: 0 .45rem; border-left: 1px solid var(--bs-border-color); background: rgba(0, 0, 0, .04); font-size: .78em; font-weight: 600; }
.rx-drag-handle:hover { background: #e9ecef; color: #212529; }
.rx-drag-handle:active { cursor: grabbing; }
.rx-drag-cell { white-space: nowrap; }
.mb-6 { margin-bottom: 4.5rem !important; }
</style>
@endpush
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Amend Prescription — {{ clinical_no($prescription->prescription_number) }} (v{{ $prescription->version }})</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.show', $prescription) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="alert alert-warning">
    <strong>Amending Rx {{ clinical_no($prescription->prescription_number) }} (v{{ $prescription->version }})</strong>
    — created {{ $prescription->created_at->format('d M Y, H:i') }}<br>
    A new version (v{{ $prescription->version + 1 }}) will be created. The original will remain in the patient's history.
</div>

@include('medical.prescriptions._rx_form', ['mode' => 'amend'])
@endsection
