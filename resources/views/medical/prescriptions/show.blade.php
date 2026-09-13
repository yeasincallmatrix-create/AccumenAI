@extends('layouts.institute')

@section('title', 'Prescription — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            {{ clinical_no($prescription->prescription_number) }}
            @if($prescription->is_finalized)
                <span class="badge bg-success">Finalized</span>
            @else
                <span class="badge bg-warning text-dark">Draft</span>
            @endif
        </h4>
    </div>
    <div class="page-header-actions">
        @if(!$prescription->is_finalized)
            <a class="btn btn-warning" href="{{ route('medical.prescriptions.edit', $prescription) }}">
                <i class="bi bi-pencil me-1"></i>Edit
            </a>
            <form action="{{ route('medical.prescriptions.finalize', $prescription) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success"
                        onclick="return confirm('Finalize this prescription? It can no longer be edited.')">
                    <i class="bi bi-check-all me-1"></i>Finalize
                </button>
            </form>
        @else
            <a class="btn btn-primary" href="{{ route('medical.prescriptions.print', $prescription) }}">
                <i class="bi bi-printer me-1"></i>Print
            </a>
            <a class="btn btn-outline-primary" href="{{ route('medical.prescriptions.pdf', $prescription) }}">
                <i class="bi bi-file-earmark-pdf me-1"></i>PDF
            </a>
        @endif
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Prescription Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($prescription->patient)
                        <a href="{{ route('medical.patients.show', $prescription->patient) }}">{{ $prescription->patient->full_name }}</a>
                        <span class="text-muted">({{ clinical_no($prescription->patient->mr_number) }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Doctor:</strong> {{ $prescription->doctor->name ?? 'N/A' }}</p>
                <p><strong>Date:</strong> <x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></p>
                <p><strong>Diagnosis:</strong> {{ $prescription->diagnosis ?? '—' }}</p>
                <p class="mb-0"><strong>Follow-up:</strong> <x-tdate :value="$prescription->follow_up_date" fallback="d M Y" /></p>
                @if($prescription->is_finalized)
                    <hr>
                    <p class="mb-1"><strong>Signed:</strong> {{ $prescription->signed_at?->format('d M Y, h:i A') ?? '—' }}</p>
                    <p class="mb-1"><strong>Signature:</strong> <code>{{ substr((string) $prescription->signature_hash, 0, 16) }}…</code></p>
                    @if(!empty($qr))
                        <div class="d-flex align-items-center gap-3 mt-2">
                            <img src="{{ $qr }}" alt="Verification QR" width="110" height="110">
                            <small class="text-muted">Scan to verify<br><code>{{ $verifyCode ?? '' }}</code></small>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Clinical Notes</h6></div>
            <div class="card-body">
                <p><strong>Complaints:</strong> {{ $prescription->chief_complaints ?? '—' }}</p>
                <p><strong>Findings:</strong> {{ $prescription->examination_findings ?? '—' }}</p>
                <p><strong>Investigations:</strong> {{ $prescription->investigations ?? '—' }}</p>
                <p class="mb-0"><strong>Advice:</strong> {{ $prescription->advice ?? '—' }}</p>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Medicines ({{ $prescription->items->count() }})</h6></div>
    <div class="card-body">
        @if($prescription->items->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr><th>Medicine</th>@if(mawa_dgda_enabled())<th>DGDA Code</th>@endif<th>Dosage</th><th>Frequency</th><th>Days</th><th>Qty</th><th>Status</th><th></th></tr>
                    </thead>
                    <tbody>
                        @foreach($prescription->items as $item)
                        <tr>
                            <td>{{ $item->medicine_name }}</td>
                            @if(mawa_dgda_enabled())
                            <td>
                                @if($item->dgda_code)
                                    <span class="badge bg-success">DGDA: {{ $item->dgda_code }}</span>
                                @else
                                    <span class="badge bg-warning text-dark" title="No DGDA code — registry sync pending">DGDA sync pending</span>
                                @endif
                            </td>
                            @endif
                            <td>{{ $item->dosage }}</td>
                            <td>{{ $item->frequency }}</td>
                            <td>{{ $item->duration_days ?? '—' }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>
                                <span class="badge bg-{{ $item->status === 'dispensed' ? 'success' : ($item->status === 'cancelled' ? 'danger' : 'secondary') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if(!$prescription->is_finalized && $item->status === 'pending')
                                    <form action="{{ route('medical.prescriptions.items.destroy', [$prescription, $item]) }}"
                                          method="POST" class="d-inline"
                                          onsubmit="return confirm('Remove this medicine?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger" title="Remove">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No medicines on this prescription.</p>
        @endif

        @if(!$prescription->is_finalized)
            <hr>
            <h6>Add Medicine</h6>
            <form action="{{ route('medical.prescriptions.items.store', $prescription) }}" method="POST">
                @csrf
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="medicine_name" class="form-control form-control-sm" required
                               maxlength="200" placeholder="Medicine name">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="dosage" class="form-control form-control-sm" required
                               maxlength="50" placeholder="Dosage">
                    </div>
                    <div class="col-md-2">
                        <input type="text" name="frequency" class="form-control form-control-sm" required
                               maxlength="50" placeholder="Frequency">
                    </div>
                    <div class="col-md-2">
                        <input type="number" name="quantity" class="form-control form-control-sm" min="1" value="1" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="bi bi-plus-lg me-1"></i>Add
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>

@if($prescription->cdsFindings->isNotEmpty())
<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Clinical Safety Findings ({{ $prescription->cdsFindings->count() }})</h6></div>
    <div class="card-body">
        @foreach($prescription->cdsFindings as $finding)
            <div class="alert alert-{{ $finding->severity === 'CRITICAL' ? 'danger' : ($finding->severity === 'HIGH' ? 'warning' : 'info') }} mb-2">
                <strong>[{{ $finding->severity }}] {{ $finding->status }}</strong>
                {{ $finding->message }}
                @if(is_array($finding->explanation))
                    <br><small class="text-muted">
                        {{ $finding->explanation['why'] ?? '' }}
                        {{ $finding->explanation['evidence'] ?? '' }}
                        {{ $finding->explanation['action'] ?? '' }}
                    </small>
                @endif
                @if($finding->ruleVersion && $finding->ruleVersion->rule)
                    <br><small class="text-muted">Rule {{ $finding->ruleVersion->rule->rule_key }} v{{ $finding->ruleVersion->version }} · {{ $finding->evaluated_at?->format('d M Y, h:i A') }}</small>
                @endif
                @if(in_array($finding->status, ['open', 'acknowledged'], true))
                    <form action="{{ route('medical.prescriptions.findings.resolve', [$prescription, $finding]) }}" method="POST" class="row g-2 mt-1">
                        @csrf
                        <div class="col-md-3">
                            <select name="action" class="form-select form-select-sm" required>
                                <option value="acknowledge">Acknowledge</option>
                                <option value="override">Override (needs reason)</option>
                                <option value="resolve">Resolve</option>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <input type="text" name="reason" class="form-control form-control-sm" maxlength="2000" placeholder="Reason (required for override)">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-sm btn-secondary w-100">Apply</button>
                        </div>
                    </form>
                @elseif($finding->resolution_reason)
                    <br><small class="text-muted">Resolution: {{ $finding->resolution_reason }}</small>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
