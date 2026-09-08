<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Discharge Summary — {{ $patient->mr_number ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 11px; color: #555; }
        h2 { font-size: 14px; background: #f0f0f0; padding: 5px 8px; margin: 15px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #f5f5f5; }
        .meta td { border: none; padding: 3px 8px 3px 0; vertical-align: top; }
        .footer { margin-top: 30px; }
        .sign { float: right; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <p>{{ $hospital_address }}</p>
        <h2 style="background:none; border:none; text-align:center;">DISCHARGE SUMMARY</h2>
    </div>

    <h2>Patient Information</h2>
    <table class="meta">
        <tr>
            <td><strong>Name:</strong> {{ $patient->full_name ?? 'N/A' }}</td>
            <td><strong>MR Number:</strong> {{ $patient->mr_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Age / Gender:</strong> {{ $patient->age ?? 'N/A' }} / {{ ucfirst($patient->gender ?? 'N/A') }}</td>
            <td><strong>Phone:</strong> {{ $patient->phone ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td><strong>Admitted:</strong> <x-tdate :value="$admission->admission_date" fallback="d M Y" /></td>
            <td><strong>Discharged:</strong> <x-tdate :value="$admission->discharge_date" fallback="d M Y" /></td>
        </tr>
        <tr>
            <td><strong>Ward / Bed:</strong> {{ $admission->bed->ward->name ?? 'N/A' }} / {{ $admission->bed->bed_number ?? 'N/A' }}</td>
            <td><strong>Doctor:</strong> {{ $doctor->name ?? 'N/A' }}</td>
        </tr>
    </table>

    <h2>Diagnosis</h2>
    <p><strong>Primary:</strong> {{ $admission->primary_diagnosis ?? '—' }}</p>
    <p><strong>Secondary:</strong> {{ $admission->secondary_diagnosis ?? '—' }}</p>

    <h2>Discharge Summary</h2>
    <p>{{ $admission->discharge_summary ?? '—' }}</p>

    @if($vitals->count() > 0)
        <h2>Last Vitals</h2>
        <table>
            <thead>
                <tr><th>Date</th><th>Temp (°C)</th><th>BP</th><th>Pulse</th><th>SpO2</th></tr>
            </thead>
            <tbody>
                @foreach($vitals->take(5) as $vital)
                    <tr>
                        <td><x-tdate :value="$vital->recorded_at" fallback="d M Y H:i" :datetime="true" /></td>
                        <td>{{ $vital->temperature ?? '—' }}</td>
                        <td>{{ $vital->blood_pressure ?? '—' }}</td>
                        <td>{{ $vital->pulse ?? '—' }}</td>
                        <td>{{ $vital->spo2 ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($notes->count() > 0)
        <h2>Nursing Notes (latest)</h2>
        @foreach($notes->take(5) as $note)
            <p><strong><x-tdate :value="$note->recorded_at" fallback="d M Y H:i" :datetime="true" />:</strong> {{ $note->note }}</p>
        @endforeach
    @endif

    <div class="footer">
        <p>Generated on {{ $discharge_date }}</p>
        <div class="sign">
            <p>______________________</p>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
