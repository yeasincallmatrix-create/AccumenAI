@extends('layouts.institute')

@section('title', $donor->fullName() . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-person-heart"></i> {{ $donor->fullName() }}
            <span class="badge bg-light text-dark ms-2">{{ $donor->donor_number }}</span>
            <span class="badge bg-{{ $donor->statusColor() }} ms-1">{{ $donor->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($user && $user->hasPermission('medical_bloodbank.edit'))
                <a href="{{ route('medical.blood-bank.donors.edit', $donor) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            <a href="{{ route('medical.blood-bank.donors.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-person"></i> Donor Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="160">Donor #</th><td>{{ $donor->donor_number }}</td></tr>
                        <tr><th>Name</th><td>{{ $donor->fullName() }}</td></tr>
                        <tr><th>Blood Group</th><td><span class="badge bg-light text-dark">{{ $donor->bloodGroupLabel() }}</span></td></tr>
                        <tr><th>Gender</th><td>{{ $donor->genderLabel() }}</td></tr>
                        <tr><th>Phone</th><td>{{ $donor->phone ?? '-' }}</td></tr>
                        <tr><th>Email</th><td>{{ $donor->email ?? '-' }}</td></tr>
                        <tr><th>Date of Birth</th><td>{{ $donor->date_of_birth ? $donor->date_of_birth->format('d M Y') : '-' }}</td></tr>
                        <tr><th>Weight</th><td>{{ $donor->weight_kg ? $donor->weight_kg . ' kg' : '-' }}</td></tr>
                        <tr><th>Hemoglobin</th><td>{{ $donor->hemoglobin ? $donor->hemoglobin . ' g/dL' : '-' }}</td></tr>
                        <tr><th>Last Donation</th><td>{{ $donor->last_donation_date ? $donor->last_donation_date->format('d M Y') : 'Never' }}</td></tr>
                        <tr>
                            <th>Eligible</th>
                            <td>
                                @if($donor->is_eligible)
                                    <span class="badge bg-success">Eligible</span>
                                @else
                                    <span class="badge bg-danger">Not Eligible</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Status</th><td><span class="badge bg-{{ $donor->statusColor() }}">{{ $donor->statusLabel() }}</span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-droplet"></i> Donation History ({{ $donor->bloodUnits->count() }})</div>
                <div class="card-body">
                    @if($donor->bloodUnits->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Unit #</th>
                                        <th>Blood Group</th>
                                        <th>Component</th>
                                        <th>Collection Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($donor->bloodUnits as $unit)
                                        <tr>
                                            <td>
                                                <a href="{{ route('medical.blood-bank.units.show', $unit) }}">
                                                    <strong>{{ $unit->unit_number }}</strong>
                                                </a>
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $unit->blood_group }}</span></td>
                                            <td>{{ $unit->component }}</td>
                                            <td><x-tdate :value="$unit->collection_date" /></td>
                                            <td>
                                                <span class="badge bg-{{ $unit->statusColor() }}">{{ $unit->statusLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No donation history yet.</p>
                    @endif
                </div>
            </div>

            @if($donor->medical_history)
                <div class="card shadow-sm mt-3">
                    <div class="card-header"><i class="bi bi-journal-medical"></i> Medical History</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $donor->medical_history }}</p>
                    </div>
                </div>
            @endif

            @if($donor->notes)
                <div class="card shadow-sm mt-3">
                    <div class="card-header"><i class="bi bi-sticky"></i> Notes</div>
                    <div class="card-body">
                        <p class="mb-0">{{ $donor->notes }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
