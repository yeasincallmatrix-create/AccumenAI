@extends('layouts.institute')

@section('title', 'Blood Unit ' . $unit->unit_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-droplet"></i> {{ $unit->unit_number }}
            <span class="badge bg-{{ $unit->statusColor() }} ms-1">{{ $unit->statusLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($user && $user->hasPermission('medical_bloodbank.edit'))
                <a href="{{ route('medical.blood-bank.units.edit', $unit) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                @if($unit->status === 'available')
                    <form method="POST" action="{{ route('medical.blood-bank.units.discard', $unit) }}" class="d-inline" onsubmit="var r = prompt('Discard reason (required):'); if (r === null || r.trim() === '') { return false; } this.querySelector('input[name=reason]').value = r; return confirm('Discard this blood unit?');">
                        @csrf
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i> Discard</button>
                    </form>
                @endif
            @endif
            <a href="{{ route('medical.blood-bank.units.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-droplet"></i> Unit Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="160">Unit #</th><td>{{ $unit->unit_number }}</td></tr>
                        <tr><th>Blood Group</th><td><span class="badge bg-light text-dark">{{ $unit->blood_group }}</span></td></tr>
                        <tr><th>Component</th><td>{{ $unit->componentLabel() }}</td></tr>
                        <tr><th>Volume</th><td>{{ $unit->volume_ml }} ml</td></tr>
                        <tr><th>Donor</th>
                            <td>
                                @if($unit->donor)
                                    <a href="{{ route('medical.blood-bank.donors.show', $unit->donor) }}">{{ $unit->donor->fullName() }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr><th>Collection Date</th><td><x-tdate :value="$unit->collection_date" /></td></tr>
                        <tr><th>Expiry Date</th>
                            <td>
                                <x-tdate :value="$unit->expiry_date" />
                                @if($unit->daysUntilExpiry() !== null && $unit->status === 'available')
                                    @if($unit->daysUntilExpiry() <= 7)
                                        <small class="text-danger ms-1">({{ $unit->daysUntilExpiry() }} day(s) left)</small>
                                    @else
                                        <small class="text-muted ms-1">({{ $unit->daysUntilExpiry() }} day(s) left)</small>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        <tr><th>Crossmatch Required</th><td>{{ $unit->crossmatch_required ? 'Yes' : 'No' }}</td></tr>
                        <tr><th>Status</th><td><span class="badge bg-{{ $unit->statusColor() }}">{{ $unit->statusLabel() }}</span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-shield-check"></i> Screening Results</div>
                <div class="card-body">
                    @php
                        $screeningTests = [
                            'HIV' => $unit->screening_hiv,
                            'HBsAg' => $unit->screening_hbsag,
                            'HCV' => $unit->screening_hcv,
                            'Syphilis' => $unit->screening_syphilis,
                            'Malaria' => $unit->screening_malaria,
                        ];
                        $allScreened = collect($screeningTests)->every(fn($v) => $v !== null && $v !== 'pending');
                    @endphp

                    <table class="table table-sm mb-3">
                        <thead>
                            <tr><th>Test</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                            @foreach($screeningTests as $testName => $testStatus)
                                <tr>
                                    <td>{{ $testName }}</td>
                                    <td>
                                        @if($testStatus === 'pass')
                                            <span class="badge bg-success">Pass</span>
                                        @elseif($testStatus === 'fail')
                                            <span class="badge bg-danger">Fail</span>
                                        @else
                                            <span class="badge bg-secondary">Pending</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($user && $user->hasPermission('medical_bloodbank.edit') && $unit->status === 'available')
                        <form method="POST" action="{{ route('medical.blood-bank.units.screen', $unit) }}">
                            @csrf
                            <div class="row g-2">
                                @foreach(['screening_hiv' => 'HIV', 'screening_hbsag' => 'HBsAg', 'screening_hcv' => 'HCV', 'screening_syphilis' => 'Syphilis', 'screening_malaria' => 'Malaria'] as $field => $label)
                                    <div class="col-md-4">
                                        <label class="form-label">{{ $label }}</label>
                                        <select name="{{ $field }}" class="form-select form-select-sm" required>
                                            <option value="pending" @selected(($unit->$field ?? 'pending') === 'pending')>Pending</option>
                                            <option value="pass" @selected($unit->$field === 'pass')>Pass</option>
                                            <option value="fail" @selected($unit->$field === 'fail')>Fail</option>
                                        </select>
                                    </div>
                                @endforeach
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle"></i> Update Screening</button>
                                </div>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-arrow-up"></i> Issue History ({{ $unit->issueItems->count() }})</div>
                <div class="card-body">
                    @if($unit->issueItems->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Request #</th>
                                        <th>Patient</th>
                                        <th>Issued By</th>
                                        <th>Issued At</th>
                                        <th>Returned At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($unit->issueItems as $item)
                                        <tr>
                                            <td>
                                                <a href="{{ route('medical.blood-bank.requests.show', $item->request) }}">
                                                    <strong>{{ $item->request?->request_number ?? '-' }}</strong>
                                                </a>
                                            </td>
                                            <td>{{ $item->request?->patient?->full_name ?? '-' }}</td>
                                            <td>{{ $item->issuedBy?->name ?? '-' }}</td>
                                            <td><x-tdate :value="$item->issued_at" /></td>
                                            <td>
                                                @if($item->returned_at)
                                                    <x-tdate :value="$item->returned_at" />
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge bg-{{ $item->statusColor() }}">{{ $item->statusLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No issue history for this unit.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
