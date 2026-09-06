@extends('layouts.institute')

@section('title', 'Available Beds — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Available Beds</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.beds.index') }}">
            <i class="bi bi-arrow-left me-1"></i>All Beds
        </a>
    </div>
</div>

<div class="row mb-3">
    @forelse($summary as $row)
    <div class="col-md-4 mb-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title">{{ $row['name'] }} <span class="badge bg-secondary">{{ strtoupper($row['type']) }}</span></h6>
                <p class="mb-1"><strong>Available:</strong> {{ $row['available_beds'] }} / {{ $row['total_beds'] }}</p>
                <div class="progress" style="height: 16px;">
                    <div class="progress-bar {{ $row['occupancy_rate'] >= 90 ? 'bg-danger' : ($row['occupancy_rate'] >= 70 ? 'bg-warning' : 'bg-success') }}"
                         role="progressbar" style="width: {{ $row['occupancy_rate'] }}%">
                        {{ $row['occupancy_rate'] }}%
                    </div>
                </div>
            </div>
        </div>
    </div>
    @empty
    <div class="col-12"><p class="text-muted">No active wards.</p></div>
    @endforelse
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Available Bed List ({{ $availableBeds->count() }})</h6></div>
    <div class="card-body">
        @if($availableBeds->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Bed Number</th><th>Ward</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach($availableBeds as $bed)
                        <tr>
                            <td><strong>{{ $bed->bed_number }}</strong></td>
                            <td>{{ $bed->ward->name ?? 'N/A' }} ({{ strtoupper($bed->ward->type ?? '') }})</td>
                            <td class="text-end">
                                <a href="{{ route('medical.beds.show', $bed) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.admissions.create', ['bed_id' => $bed->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Admit Here
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No beds available right now.</p>
        @endif
    </div>
</div>
@endsection
