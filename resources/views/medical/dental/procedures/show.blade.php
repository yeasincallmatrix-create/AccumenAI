@extends('layouts.institute')

@section('title', 'Procedure ' . $procedure->procedure_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-tools"></i> {{ $procedure->procedure_number }}</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical.dental.procedure.edit'))
                <a href="{{ route('medical.dental.procedures.edit', $procedure) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil"></i> Edit</a>
                @if($procedure->status !== 'followed_up')
                    <form method="POST" action="{{ route('medical.dental.procedures.follow-up', $procedure) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Mark Followed Up</button>
                    </form>
                @endif
            @endif
            <a href="{{ route('medical.dental.procedures.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Number:</strong> {{ $procedure->procedure_number }}</div>
                        <div class="col-md-6"><strong>Patient:</strong> {{ $procedure->patient->full_name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Dentist:</strong> {{ $procedure->dentist->name ?? 'N/A' }}</div>
                        <div class="col-md-6"><strong>Status:</strong> <span class="badge bg-{{ $procedure->statusColor() }}">{{ $procedure->statusLabel() }}</span></div>
                        <div class="col-md-6"><strong>Category:</strong> {{ $procedure->categoryLabel() ?? '—' }}</div>
                        <div class="col-md-6"><strong>Procedure:</strong> {{ $procedure->procedure_name }}</div>
                        <div class="col-md-6"><strong>Code:</strong> {{ $procedure->procedure_code ?? '—' }}</div>
                        <div class="col-md-6"><strong>Tooth:</strong> {{ $procedure->fullToothName() ?? '—' }}</div>
                        <div class="col-md-6"><strong>Surface:</strong> {{ $procedure->tooth_surface ?? '—' }}</div>
                        <div class="col-md-12"><strong>Diagnosis:</strong> {{ $procedure->diagnosis ?? '—' }}</div>
                        <div class="col-md-12"><strong>Notes:</strong> {{ $procedure->procedure_notes ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Anesthesia</h6></div>
                <div class="card-body">
                    <p><strong>Type:</strong> {{ $procedure->anesthesia_type ?? 'None' }}</p>
                    <p><strong>Agent:</strong> {{ $procedure->anesthesia_agent ?? '—' }}</p>
                    <p><strong>Volume:</strong> {{ $procedure->anesthesia_volume_ml ? $procedure->anesthesia_volume_ml . ' ml' : '—' }}</p>
                </div>
            </div>
            <div class="card shadow-sm mb-3">
                <div class="card-header bg-white"><h6 class="mb-0">Timing & Billing</h6></div>
                <div class="card-body">
                    <p><strong>Performed:</strong> {{ $procedure->performed_at->format('d M Y H:i') }}</p>
                    <p><strong>Duration:</strong> {{ $procedure->duration_minutes ? $procedure->duration_minutes . ' min' : '—' }}</p>
                    <p><strong>Fee:</strong> {{ number_format($procedure->fee, 2) }}</p>
                    <p><strong>Payment:</strong> {{ ucfirst($procedure->payment_status) }}</p>
                    @if($procedure->follow_up_date)
                        <p><strong>Follow-up:</strong> {{ $procedure->follow_up_date->format('d M Y') }}</p>
                    @endif
                </div>
            </div>
            @if($procedure->medications_prescribed)
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><h6 class="mb-0">Medications</h6></div>
                    <div class="card-body"><p>{{ $procedure->medications_prescribed }}</p></div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
