@extends('layouts.institute')

@section('title', 'Blood Request ' . $bloodRequest->request_number . ' — AccumenAI')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <h4 class="mb-0">
            <i class="bi bi-clipboard2-pulse"></i> {{ $bloodRequest->request_number }}
            <span class="badge bg-{{ $bloodRequest->statusColor() }} ms-1">{{ $bloodRequest->statusLabel() }}</span>
            <span class="badge bg-{{ $bloodRequest->urgencyColor() }} ms-1">{{ $bloodRequest->urgencyLabel() }}</span>
        </h4>
        <div class="d-flex gap-2 flex-wrap">
            @if($user && $user->hasPermission('medical_bloodbank.edit') && $bloodRequest->isPending())
                <a href="{{ route('medical.blood-bank.requests.edit', $bloodRequest) }}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil"></i> Edit
                </a>
            @endif
            <a href="{{ route('medical.blood-bank.requests.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-clipboard2-pulse"></i> Request Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="160">Request #</th><td>{{ $bloodRequest->request_number }}</td></tr>
                        <tr><th>Patient</th>
                            <td>
                                @if($bloodRequest->patient)
                                    <a href="{{ route('medical.patients.show', $bloodRequest->patient) }}">{{ $bloodRequest->patient->full_name }}</a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        <tr><th>Requested By</th><td>{{ $bloodRequest->requestedBy?->name ?? '-' }}</td></tr>
                        <tr><th>Doctor</th><td>{{ $bloodRequest->doctor?->name ?? '-' }}</td></tr>
                        <tr><th>Blood Group</th><td><span class="badge bg-light text-dark">{{ $bloodRequest->blood_group }}</span></td></tr>
                        <tr><th>Component</th><td>{{ $bloodRequest->component ?? '-' }}</td></tr>
                        <tr><th>Units Requested</th><td>{{ $bloodRequest->units_requested }}</td></tr>
                        <tr><th>Units Issued</th><td>{{ $bloodRequest->units_issued }}</td></tr>
                        <tr><th>Urgency</th><td><span class="badge bg-{{ $bloodRequest->urgencyColor() }}">{{ $bloodRequest->urgencyLabel() }}</span></td></tr>
                        <tr>
                            <th>Status</th>
                            <td><span class="badge bg-{{ $bloodRequest->statusColor() }}">{{ $bloodRequest->statusLabel() }}</span></td>
                        </tr>
                        @if($bloodRequest->approved_at)
                            <tr><th>Approved By</th><td>{{ $bloodRequest->approvedBy?->name ?? '-' }}</td></tr>
                            <tr><th>Approved At</th><td><x-tdate :value="$bloodRequest->approved_at" /></td></tr>
                        @endif
                        @if($bloodRequest->fulfilled_at)
                            <tr><th>Fulfilled At</th><td><x-tdate :value="$bloodRequest->fulfilled_at" /></td></tr>
                        @endif
                        @if($bloodRequest->cancelled_at)
                            <tr><th>Cancelled At</th><td><x-tdate :value="$bloodRequest->cancelled_at" /></td></tr>
                            <tr><th>Cancel Reason</th><td>{{ $bloodRequest->cancel_reason }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-text"></i> Clinical Information</div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th width="160">Clinical Indication</th><td>{{ $bloodRequest->clinical_indication ?? '-' }}</td></tr>
                        <tr><th>Diagnosis</th><td>{{ $bloodRequest->diagnosis ?? '-' }}</td></tr>
                        <tr><th>Notes</th><td>{{ $bloodRequest->notes ?? '-' }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-journal-arrow-up"></i> Issue History ({{ $bloodRequest->issueItems->count() }})</div>
                <div class="card-body">
                    @if($bloodRequest->issueItems->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Unit #</th>
                                        <th>Blood Group</th>
                                        <th>Component</th>
                                        <th>Issued By</th>
                                        <th>Issued At</th>
                                        <th>Returned At</th>
                                        <th>Return Reason</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bloodRequest->issueItems as $item)
                                        <tr>
                                            <td>
                                                <a href="{{ route('medical.blood-bank.units.show', $item->unit) }}">
                                                    <strong>{{ $item->unit?->unit_number ?? '-' }}</strong>
                                                </a>
                                            </td>
                                            <td><span class="badge bg-light text-dark">{{ $item->unit?->blood_group ?? '-' }}</span></td>
                                            <td>{{ $item->unit?->component ?? '-' }}</td>
                                            <td>{{ $item->issuedBy?->name ?? '-' }}</td>
                                            <td><x-tdate :value="$item->issued_at" /></td>
                                            <td>
                                                @if($item->returned_at)
                                                    <x-tdate :value="$item->returned_at" />
                                                @else
                                                    <span class="text-muted">-</span>
                                                @endif
                                            </td>
                                            <td>{{ $item->return_reason ?? '-' }}</td>
                                            <td>
                                                <span class="badge bg-{{ $item->statusColor() }}">{{ $item->statusLabel() }}</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No units issued yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-gear"></i> Actions</div>
                <div class="card-body">
                    <div class="row g-3">
                        @if($user && $user->hasPermission('medical_bloodbank.issue'))
                            @if($bloodRequest->isPending())
                                <div class="col-md-4">
                                    <div class="card border-success">
                                        <div class="card-body">
                                            <h6 class="card-title text-success"><i class="bi bi-check-circle"></i> Approve Request</h6>
                                            <p class="card-text small text-muted">Approve this blood request to allow unit issuance.</p>
                                            <form method="POST" action="{{ route('medical.blood-bank.requests.approve', $bloodRequest) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Approve this request?')">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($bloodRequest->isApproved() || $bloodRequest->status === 'partially_fulfilled')
                                @php
                                    $compatibleUnits = \App\Models\Medical\BloodUnit::where('status', 'available')
                                        ->where('expiry_date', '>', now())
                                        ->where('blood_group', $bloodRequest->blood_group)
                                        ->get();
                                    if ($bloodRequest->component) {
                                        $compatibleUnits = $compatibleUnits->where('component', $bloodRequest->component);
                                    }
                                @endphp
                                <div class="col-md-4">
                                    <div class="card border-info">
                                        <div class="card-body">
                                            <h6 class="card-title text-info"><i class="bi bi-droplet"></i> Issue Unit</h6>
                                            <form method="POST" action="{{ route('medical.blood-bank.requests.issue', $bloodRequest) }}">
                                                @csrf
                                                <div class="mb-2">
                                                    <select name="blood_unit_id" class="form-select form-select-sm" required>
                                                        <option value="">Select Unit</option>
                                                        @foreach($compatibleUnits as $u)
                                                            <option value="{{ $u->id }}">{{ $u->unit_number }} — {{ $u->blood_group }} {{ $u->component }} (exp: {{ $u->expiry_date?->format('d M Y') }})</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <button type="submit" class="btn btn-info btn-sm" onclick="return confirm('Issue this unit?')">
                                                    <i class="bi bi-droplet"></i> Issue
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($bloodRequest->issueItems->where('status', 'issued')->count() > 0)
                                <div class="col-md-4">
                                    <div class="card border-warning">
                                        <div class="card-body">
                                            <h6 class="card-title text-warning"><i class="bi bi-arrow-return-left"></i> Return Unit</h6>
                                            <form method="POST" action="{{ route('medical.blood-bank.requests.return', $bloodRequest) }}">
                                                @csrf
                                                <div class="mb-2">
                                                    <select name="issue_item_id" class="form-select form-select-sm" required>
                                                        <option value="">Select Issued Unit</option>
                                                        @foreach($bloodRequest->issueItems->where('status', 'issued') as $item)
                                                            <option value="{{ $item->id }}">{{ $item->unit?->unit_number }} — {{ $item->unit?->blood_group }} {{ $item->unit?->component }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="mb-2">
                                                    <input type="text" name="return_reason" class="form-control form-control-sm" placeholder="Return reason (required)" required>
                                                </div>
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Return this unit?')">
                                                    <i class="bi bi-arrow-return-left"></i> Return
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(!$bloodRequest->isFulfilled() && !$bloodRequest->isCancelled())
                                <div class="col-md-4">
                                    <div class="card border-danger">
                                        <div class="card-body">
                                            <h6 class="card-title text-danger"><i class="bi bi-x-circle"></i> Cancel Request</h6>
                                            <form method="POST" action="{{ route('medical.blood-bank.requests.cancel', $bloodRequest) }}" onsubmit="var r = prompt('Cancellation reason (required):'); if (r === null || r.trim() === '') { return false; } this.querySelector('input[name=cancel_reason]').value = r; return confirm('Cancel this request?');">
                                                @csrf
                                                <input type="hidden" name="cancel_reason" value="">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-x-circle"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
