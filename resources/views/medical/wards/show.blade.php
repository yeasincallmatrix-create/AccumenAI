@extends('layouts.institute')

@section('title', 'Ward Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $ward->name }} <span class="badge bg-secondary">{{ strtoupper($ward->type) }}</span></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-warning" href="{{ route('medical.wards.edit', $ward) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.wards.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row mb-3">
    <div class="col-md-3">
        <div class="card bg-primary text-white"><div class="card-body">
            <h6 class="card-title">Total Beds</h6><h2 class="card-text">{{ $ward->total_beds }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white"><div class="card-body">
            <h6 class="card-title">Available</h6><h2 class="card-text">{{ $ward->available_beds }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white"><div class="card-body">
            <h6 class="card-title">Occupied</h6><h2 class="card-text">{{ $ward->total_beds - $ward->available_beds }}</h2>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-dark"><div class="card-body">
            <h6 class="card-title">Daily Rate</h6><h2 class="card-text">৳{{ number_format($ward->daily_rate, 2) }}</h2>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Bed Map ({{ $ward->beds->count() }} beds)</h6>
        <a href="{{ route('medical.beds.create', ['ward_id' => $ward->id]) }}" class="btn btn-sm btn-primary">+ Add Bed</a>
    </div>
    <div class="card-body">
        @if($ward->beds->count() > 0)
            <div class="row g-2">
                @foreach($ward->beds as $bed)
                <div class="col-md-2 col-sm-3 col-4">
                    <a href="{{ route('medical.beds.show', $bed) }}" class="text-decoration-none">
                        <div class="card text-center border-{{ $bed->status === 'available' ? 'success' : ($bed->status === 'occupied' ? 'danger' : 'warning') }}">
                            <div class="card-body p-2">
                                <i class="bi bi-hospital fs-4 text-{{ $bed->status === 'available' ? 'success' : ($bed->status === 'occupied' ? 'danger' : 'warning') }}"></i>
                                <div class="fw-bold small">{{ $bed->bed_number }}</div>
                                <span class="badge bg-{{ $bed->status === 'available' ? 'success' : ($bed->status === 'occupied' ? 'danger' : ($bed->status === 'reserved' ? 'warning' : 'secondary')) }}">
                                    {{ ucfirst($bed->status) }}
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach
            </div>
        @else
            <p class="text-muted mb-0">No beds in this ward yet.</p>
        @endif
    </div>
</div>

@if($ward->notes)
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Notes</h6></div>
    <div class="card-body"><p class="mb-0">{{ $ward->notes }}</p></div>
</div>
@endif
@endsection
