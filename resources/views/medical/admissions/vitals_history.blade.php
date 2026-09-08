@extends('layouts.institute')

@section('title', 'Vitals History — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Vitals History — {{ $admission->patient->full_name ?? 'N/A' }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.vitals.create', ['admission_id' => $admission->id]) }}">
            <i class="bi bi-plus-lg me-1"></i>Record Vitals
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.admissions.show', $admission) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($vitals->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Recorded At</th>
                            <th>Temp (°C)</th>
                            <th>BP</th>
                            <th>Pulse</th>
                            <th>RR</th>
                            <th>SpO2</th>
                            <th>Sugar</th>
                            <th>BMI</th>
                            <th>By</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vitals as $vital)
                        <tr>
                            <td><x-tdate :value="$vital->recorded_at" fallback="d M Y h:i A" :datetime="true" /></td>
                            <td>{{ $vital->temperature ?? '—' }}</td>
                            <td>{{ $vital->blood_pressure ?? '—' }}</td>
                            <td>{{ $vital->pulse ?? '—' }}</td>
                            <td>{{ $vital->respiratory_rate ?? '—' }}</td>
                            <td>{{ $vital->spo2 ?? '—' }}</td>
                            <td>{{ $vital->blood_sugar ?? '—' }}</td>
                            <td>{{ $vital->bmi ?? '—' }}</td>
                            <td>{{ $vital->recordedBy->name ?? 'Staff' }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('medical.vitals.show', $vital) }}" class="btn btn-info" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-danger" title="Delete"
                                            onclick="if(confirm('Delete this vitals entry?')){document.getElementById('vital-delete-{{ $vital->id }}').submit();}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <form id="vital-delete-{{ $vital->id }}"
                                      action="{{ route('medical.vitals.destroy', $vital) }}"
                                      method="POST" style="display:none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $vitals->links('pagination::bootstrap-5') }}
        @else
            <p class="text-muted mb-0">No vitals recorded for this admission yet.</p>
        @endif
    </div>
</div>
@endsection
