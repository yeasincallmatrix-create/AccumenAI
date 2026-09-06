@extends('layouts.institute')

@section('title', 'Admission Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">
            Admission — {{ $admission->patient->full_name ?? 'N/A' }}
            <span class="badge bg-{{ $admission->status === 'active' ? 'danger' : 'success' }}">{{ ucfirst($admission->status) }}</span>
        </h4>
    </div>
    <div class="page-header-actions">
        @if($admission->status === 'active')
            <a class="btn btn-info" href="{{ route('medical.vitals.create', ['admission_id' => $admission->id]) }}">
                <i class="bi bi-heart-pulse me-1"></i>Record Vitals
            </a>
            <a class="btn btn-secondary" href="{{ route('medical.admissions.transfer.form', $admission) }}">
                <i class="bi bi-arrow-left-right me-1"></i>Transfer
            </a>
            <a class="btn btn-success" href="{{ route('medical.admissions.discharge.form', $admission) }}">
                <i class="bi bi-box-arrow-right me-1"></i>Discharge
            </a>
        @else
            <a class="btn btn-primary" href="{{ route('medical.admissions.discharge-summary', $admission) }}">
                <i class="bi bi-file-earmark-pdf me-1"></i>Discharge Summary (PDF)
            </a>
        @endif
        <a class="btn btn-warning" href="{{ route('medical.admissions.edit', $admission) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.admissions.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Admission Info</h6></div>
            <div class="card-body">
                <p><strong>Patient:</strong>
                    @if($admission->patient)
                        <a href="{{ route('medical.patients.show', $admission->patient) }}">{{ $admission->patient->full_name }}</a>
                        <span class="text-muted">({{ $admission->patient->mr_number }})</span>
                    @else
                        N/A
                    @endif
                </p>
                <p><strong>Bed:</strong>
                    @if($admission->bed)
                        {{ $admission->bed->bed_number }}
                        <span class="text-muted">({{ $admission->bed->ward->name ?? '' }})</span>
                    @else
                        <span class="text-muted">No bed assigned</span>
                    @endif
                </p>
                <p><strong>Admitted:</strong>
                    {{ $admission->admission_date?->format('d M Y') }}
                    {{ $admission->admission_time ? \Carbon\Carbon::parse($admission->admission_time)->format('h:i A') : '' }}
                </p>
                <p><strong>Admitting Doctor:</strong> {{ $admission->admittingDoctor->name ?? 'N/A' }}</p>
                <p class="mb-0"><strong>Length of Stay:</strong> {{ $admission->length_of_stay }} day(s)</p>
                @if($admission->status !== 'active')
                    <hr>
                    <p><strong>Discharged:</strong>
                        {{ $admission->discharge_date?->format('d M Y') }}
                        {{ $admission->discharge_time ? \Carbon\Carbon::parse($admission->discharge_time)->format('h:i A') : '' }}
                    </p>
                    <p class="mb-0"><strong>Discharged By:</strong> {{ $admission->dischargedBy->name ?? '—' }}</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Diagnosis</h6></div>
            <div class="card-body">
                <p><strong>Primary:</strong> {{ $admission->primary_diagnosis ?? '—' }}</p>
                <p class="mb-0"><strong>Secondary:</strong> {{ $admission->secondary_diagnosis ?? '—' }}</p>
                @if($admission->discharge_summary)
                    <hr>
                    <p><strong>Discharge Summary:</strong></p>
                    <p class="mb-0">{{ $admission->discharge_summary }}</p>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Latest Vitals</h6>
                @if($admission->status === 'active')
                    <a href="{{ route('medical.vitals.create', ['admission_id' => $admission->id]) }}" class="btn btn-sm btn-primary">+ Record</a>
                @endif
            </div>
            <div class="card-body">
                @if($vitals->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead><tr><th>Time</th><th>Temp</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr></thead>
                            <tbody>
                                @foreach($vitals as $vital)
                                <tr>
                                    <td>{{ $vital->recorded_at?->format('d M H:i') }}</td>
                                    <td>{{ $vital->temperature ?? '—' }}</td>
                                    <td>{{ $vital->blood_pressure ?? '—' }}</td>
                                    <td>{{ $vital->pulse ?? '—' }}</td>
                                    <td>{{ $vital->spo2 ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('medical.vitals.index', ['admission_id' => $admission->id]) }}" class="btn btn-sm btn-link">Full vitals history</a>
                @else
                    <p class="text-muted mb-0">No vitals recorded yet.</p>
                @endif
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Nursing Notes</h6></div>
            <div class="card-body">
                @if($notes->count() > 0)
                    @foreach($notes as $note)
                        <div class="border-bottom py-2">
                            <p class="mb-1">{{ $note->note }}</p>
                            <small class="text-muted">
                                {{ $note->recordedBy->name ?? 'Staff' }} · {{ $note->recorded_at?->format('d M Y h:i A') }}
                            </small>
                        </div>
                    @endforeach
                @else
                    <p class="text-muted">No nursing notes yet.</p>
                @endif

                @if($admission->status === 'active')
                    <form action="{{ route('medical.admissions.notes.store', $admission) }}" method="POST" class="mt-2">
                        @csrf
                        <div class="mb-2">
                            <textarea name="note" rows="2" class="form-control" required maxlength="5000"
                                      placeholder="Add a nursing note..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>Add Note
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
