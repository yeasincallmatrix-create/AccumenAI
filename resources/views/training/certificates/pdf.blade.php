<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Certificate - {{ $certificate->certificate_number ?? '' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; text-align: center; padding: 40px; color: #222; }
        .border { border: 8px double #1a5fb4; padding: 30px; }
        h1 { color: #1a5fb4; margin-bottom: 5px; }
        .subtitle { font-size: 14px; color: #555; margin-bottom: 20px; }
        .name { font-size: 24px; font-weight: bold; margin: 15px 0; color: #000; }
        .details { font-size: 13px; margin: 10px 0; }
        .number { font-size: 11px; color: #666; margin-top: 20px; }
        .seal { margin-top: 25px; font-size: 10px; color: #888; }
    </style>
</head>
<body>
    <div class="border">
        <h1>Certificate of Completion</h1>
        <div class="subtitle">This is to certify that</div>
        <div class="name">{{ $certificate->student->full_name ?? $certificate->student->first_name ?? 'Trainee' }}</div>
        <div class="details">
            has successfully completed the course<br>
            <strong>{{ $certificate->course->name ?? '—' }}</strong><br>
            Batch: <strong>{{ $certificate->batch->name ?? '—' }} ({{ $certificate->batch->batch_code ?? '' }})</strong>
        </div>
        <div class="details">
            Issue Date: <strong>{{ $certificate->issue_date ? \Carbon\Carbon::parse($certificate->issue_date)->format('d M Y') : now()->format('d M Y') }}</strong>
        </div>
        <div class="number">
            Certificate No: <strong>{{ $certificate->certificate_number ?? '—' }}</strong><br>
            Verification: {{ $certificate->verification_url ?? url('verify/certificate/'.$certificate->certificate_number) }}
        </div>
        <div class="seal">
            {{ $certificate->batch->institute->name ?? 'Institute' }}<br>
            Digitally issued
        </div>
    </div>
</body>
</html>
