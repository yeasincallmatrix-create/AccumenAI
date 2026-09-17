@extends('layouts.institute')

@section('title', '{{ $vaccine->name }} — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0"><i class="bi bi-shield-check"></i> {{ $vaccine->name }}</h4>
        <div class="d-flex gap-2">
            @if($user && $user->hasPermission('medical.vaccination.manage'))
                <a href="{{ route('medical.vaccination.vaccine-masters.edit', $vaccine) }}" class="btn btn-outline-warning btn-sm"><i class="bi bi-pencil"></i> Edit</a>
            @endif
            <a href="{{ route('medical.vaccination.vaccine-masters.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4"><strong>Code:</strong> {{ $vaccine->code ?? '—' }}</div>
                        <div class="col-md-4"><strong>Category:</strong> <span class="badge bg-info">{{ $vaccine->categoryLabel() }}</span></div>
                        <div class="col-md-4"><strong>Status:</strong> <span class="badge bg-{{ $vaccine->is_active ? 'success' : 'secondary' }}">{{ $vaccine->is_active ? 'Active' : 'Inactive' }}</span></div>
                        <div class="col-md-4"><strong>Doses in Series:</strong> {{ $vaccine->doses_in_series }}</div>
                        <div class="col-md-4"><strong>Route:</strong> {{ $vaccine->routeLabel() ?? '—' }}</div>
                        <div class="col-md-4"><strong>Site:</strong> {{ $vaccine->siteLabel() ?? '—' }}</div>
                        <div class="col-md-4"><strong>Dose Volume:</strong> {{ $vaccine->dose_volume ?? '—' }}</div>
                        <div class="col-md-4"><strong>Default Fee:</strong> {{ number_format($vaccine->default_fee, 2) }}</div>
                        <div class="col-12"><strong>Protects Against:</strong> {{ $vaccine->protects_against ?? '—' }}</div>
                        <div class="col-12"><strong>Description:</strong> {{ $vaccine->description ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Recent Vaccination Records ({{ $vaccine->records->count() }})</h6></div>
                <div class="card-body">
                    @forelse($vaccine->records->take(10) as $record)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>{{ $record->record_number }}</strong> — {{ $record->patient->full_name ?? 'N/A' }}
                                <br><small class="text-muted">Dose {{ $record->dose_number }} | {{ $record->administered_date->format('d M Y') }}</small>
                            </div>
                            <a href="{{ route('medical.vaccination.records.show', $record) }}" class="btn btn-outline-primary btn-sm">View</a>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No vaccination records.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0">Stock Summary</h6></div>
                <div class="card-body">
                    @forelse($vaccine->stocks as $stock)
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <strong>Batch: {{ $stock->batch_number }}</strong>
                                <br><small class="text-muted">Available: {{ $stock->quantity_available }} | Exp: {{ $stock->expiry_date->format('d M Y') }}</small>
                            </div>
                            <span class="badge bg-{{ $stock->statusColor() }}">{{ $stock->statusLabel() }}</span>
                        </div>
                    @empty
                        <p class="text-muted text-center mb-0">No stock records.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
