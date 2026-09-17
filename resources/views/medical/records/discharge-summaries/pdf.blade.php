<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Discharge Summary {{ $summary->summary_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; margin: 40px; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header h2 { margin: 0; }
        .meta { width: 100%; margin-bottom: 12px; }
        .meta td { padding: 3px 6px; vertical-align: top; }
        h4 { background: #f0f0f0; padding: 5px 8px; margin: 12px 0 6px; }
        p { margin: 4px 0 8px; white-space: pre-line; }
        .sign { margin-top: 40px; display: flex; justify-content: space-between; }
        .sign div { width: 40%; border-top: 1px solid #333; padding-top: 5px; text-align: center; }
        @media print { body { margin: 10px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 10px;">
        <button onclick="window.print()">Print / Save as PDF</button>
    </div>
    <div class="header">
        <h2>DISCHARGE SUMMARY</h2>
        <div>{{ $summary->summary_number }}</div>
    </div>
    <table class="meta" border="0">
        <tr>
            <td><strong>Patient:</strong> {{ $summary->patient->full_name ?? 'N/A' }}</td>
            <td><strong>MRN:</strong> {{ $summary->patient->mr_number ?? $summary->patient_id }}</td>
        </tr>
        <tr>
            <td><strong>Admission Date:</strong> {{ $summary->admission_date->format('d M Y') }}</td>
            <td><strong>Discharge Date:</strong> {{ $summary->discharge_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><strong>Length of Stay:</strong> {{ $summary->length_of_stay_days }} days</td>
            <td><strong>Condition on Discharge:</strong> {{ ucfirst(str_replace('_', ' ', $summary->condition_on_discharge)) }}</td>
        </tr>
    </table>

    <h4>Admission Diagnosis</h4><p>{{ $summary->admission_diagnosis }}</p>
    @if($summary->final_diagnosis)<h4>Final Diagnosis</h4><p>{{ $summary->final_diagnosis }}</p>@endif
    <h4>Hospital Course</h4><p>{{ $summary->hospital_course }}</p>
    @if($summary->procedures_done)<h4>Procedures Done</h4><p>{{ $summary->procedures_done }}</p>@endif
    @if($summary->investigations_summary)<h4>Investigations</h4><p>{{ $summary->investigations_summary }}</p>@endif
    @if($summary->treatment_given)<h4>Treatment Given</h4><p>{{ $summary->treatment_given }}</p>@endif
    <h4>Discharge Medications</h4><p>{{ $summary->discharge_medications }}</p>
    <h4>Discharge Instructions</h4><p>{{ $summary->discharge_instructions }}</p>
    @if($summary->diet_instructions)<h4>Diet Instructions</h4><p>{{ $summary->diet_instructions }}</p>@endif
    @if($summary->activity_restrictions)<h4>Activity Restrictions</h4><p>{{ $summary->activity_restrictions }}</p>@endif
    @if($summary->follow_up_date)
        <h4>Follow-up</h4>
        <p>{{ $summary->follow_up_date->format('d M Y') }}@if($summary->follow_up_department) — {{ $summary->follow_up_department }}@endif
        {{ $summary->follow_up_instructions ?? '' }}</p>
    @endif

    <div class="sign">
        <div>Prepared by: {{ $summary->preparedBy->name ?? '' }}<br>Date: {{ $summary->updated_at->format('d M Y') }}</div>
        <div>Consultant Signature &amp; Seal</div>
    </div>
</body>
</html>
