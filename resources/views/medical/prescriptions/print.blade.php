@extends('layouts.institute')

@section('title', 'Print Prescription — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 no-print">
    <div class="page-header-text">
        <h4 class="page-header-title">Print Preview — {{ $prescription->prescription_number }}</h4>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print
        </button>
        <a class="btn btn-outline-primary" href="{{ route('medical.prescriptions.pdf', $prescription) }}">
            <i class="bi bi-file-earmark-pdf me-1"></i>PDF
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.prescriptions.show', $prescription) }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="text-center border-bottom pb-3 mb-3">
            <h4 class="mb-1">℞ Prescription</h4>
            <p class="text-muted mb-0">{{ $prescription->prescription_number }} · <x-tdate :value="$prescription->prescription_date" fallback="d M Y" /></p>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <p class="mb-1"><strong>Patient:</strong> {{ $patient->full_name ?? 'N/A' }}</p>
                <p class="mb-1"><strong>Age / Gender:</strong> {{ $patient->age ?? 'N/A' }} / {{ ucfirst($patient->gender ?? 'N/A') }}</p>
                <p class="mb-0"><strong>MR:</strong> {{ $patient->mr_number ?? 'N/A' }}</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>Doctor:</strong> {{ $doctor->name ?? 'N/A' }}</p>
                <p class="mb-1"><strong>Diagnosis:</strong> {{ $prescription->diagnosis ?? '—' }}</p>
                <p class="mb-0"><strong>Follow-up:</strong> <x-tdate :value="$prescription->follow_up_date" fallback="d M Y" /></p>
            </div>
        </div>

        @if($prescription->chief_complaints)
            <p><strong>Complaints:</strong> {{ $prescription->chief_complaints }}</p>
        @endif
        @if($prescription->examination_findings)
            <p><strong>Findings:</strong> {{ $prescription->examination_findings }}</p>
        @endif

        <table class="table table-bordered align-middle">
            <thead>
                <tr><th>#</th><th>Medicine</th>@if(mawa_dgda_enabled())<th>DGDA Code</th>@endif<th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th></tr>
            </thead>
            <tbody>
                @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->medicine_name }}</td>
                    @if(mawa_dgda_enabled())<td><code>{{ $item->dgda_code ?? '—' }}</code></td>@endif
                    <td>{{ $item->dosage }}</td>
                    <td>{{ $item->frequency }}</td>
                    <td>{{ $item->duration_days ? $item->duration_days.' days' : '—' }}</td>
                    <td>{{ $item->quantity }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($prescription->advice)
            <p><strong>Advice:</strong> {{ $prescription->advice }}</p>
        @endif

        <div class="text-end mt-5">
            <p class="mb-0">______________________</p>
            <p class="text-muted">Doctor's Signature</p>
            @if($prescription->signed_at)
                <p class="text-muted small mb-0">Signed {{ $prescription->signed_at->format('d M Y, h:i A') }} · <code>{{ substr((string) $prescription->signature_hash, 0, 16) }}…</code></p>
            @endif
        </div>

        @if(!empty($qr))
            <div class="mt-4 d-flex align-items-center gap-3">
                <img src="{{ $qr }}" alt="Verification QR" width="100" height="100">
                <small class="text-muted">Scan to verify this prescription<br><code>{{ $verifyCode ?? '' }}</code></small>
            </div>
        @endif
    </div>
</div>

<style>
@media print {
    .no-print, .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
    .layout, main.content { display: block !important; margin: 0 !important; padding: 0 !important; }
}
</style>
@endsection
