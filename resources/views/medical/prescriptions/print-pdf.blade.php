<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Prescription {{ clinical_no($prescription->prescription_number) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { text-align: center; border-bottom: 2px solid #0d6efd; padding-bottom: 10px; margin-bottom: 14px; }
        .header h2 { margin: 0 0 4px 0; }
        .muted { color: #666; }
        table.info { width: 100%; margin-bottom: 12px; }
        table.info td { padding: 2px 6px 2px 0; vertical-align: top; }
        table.meds { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table.meds th, table.meds td { border: 1px solid #999; padding: 5px 7px; }
        table.meds th { background: #eee; }
        .sign { margin-top: 40px; text-align: right; }
        .verify { margin-top: 18px; font-size: 11px; color: #444; }
        .verify code { font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>℞ Prescription</h2>
        <div class="muted">{{ clinical_no($prescription->prescription_number) }} · {{ $prescription->prescription_date?->format('d M Y') }}</div>
    </div>

    <table class="info">
        <tr>
            <td><strong>Patient:</strong> {{ $patient->full_name ?? 'N/A' }} ({{ clinical_no($patient->mr_number ?? 'N/A') }})</td>
            <td><strong>Doctor:</strong> {{ $doctor->name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Age / Gender:</strong> {{ $patient->age ?? 'N/A' }} / {{ ucfirst($patient->gender ?? 'N/A') }}</td>
            <td><strong>Diagnosis:</strong> {{ $prescription->diagnosis ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Follow-up:</strong> {{ $prescription->follow_up_date?->format('d M Y') ?? '—' }}</td>
            <td><strong>Signed:</strong> {{ $prescription->signed_at?->format('d M Y, h:i A') ?? '—' }}</td>
        </tr>
    </table>

    @if($prescription->chief_complaints)
        <p><strong>Complaints:</strong> {{ $prescription->chief_complaints }}</p>
    @endif
    @if($prescription->examination_findings)
        <p><strong>Findings:</strong> {{ $prescription->examination_findings }}</p>
    @endif

    <table class="meds">
        <thead>
            <tr><th>#</th><th>Medicine</th>@if(mawa_dgda_enabled())<th>DGDA Code</th>@endif<th>Dosage</th><th>Frequency</th><th>Duration</th><th>Qty</th></tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $item->medicine_name }}</td>
                    @if(mawa_dgda_enabled())<td>{{ $item->dgda_code ?? '—' }}</td>@endif
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

    <div class="sign">
        <p style="margin:0">______________________</p>
        <p class="muted" style="margin:2px 0">Doctor's Signature</p>
    </div>

    <div class="verify">
        Verification: <code>{{ $verifyCode ?? '' }}</code><br>
        Signature: <code>{{ $prescription->signature_hash }}</code>
    </div>
</body>
</html>
