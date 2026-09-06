<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lab Report — {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 11px; color: #555; }
        h2 { font-size: 14px; background: #f0f0f0; padding: 5px 8px; margin: 15px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #f5f5f5; }
        .abnormal { background: #fff3cd; }
        .critical { background: #f8d7da; }
        .meta td { border: none; padding: 3px 8px 3px 0; vertical-align: top; }
        .footer { margin-top: 30px; }
        .sign { float: right; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <p>{{ $hospital_address }}</p>
        <h2 style="background:none; border:none; text-align:center;">LABORATORY REPORT</h2>
    </div>

    <h2>Order Information</h2>
    <table class="meta">
        <tr>
            <td><strong>Order No:</strong> {{ $order->order_number }}</td>
            <td><strong>Order Date:</strong> {{ $order->order_date?->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><strong>Patient:</strong> {{ $patient->full_name ?? 'N/A' }} ({{ $patient->mr_number ?? '' }})</td>
            <td><strong>Age / Gender:</strong> {{ $patient->age ?? 'N/A' }} / {{ ucfirst($patient->gender ?? 'N/A') }}</td>
        </tr>
        <tr>
            <td><strong>Referred By:</strong> {{ $doctor->name ?? 'N/A' }}</td>
            <td><strong>Priority:</strong> {{ ucfirst($order->priority ?? 'routine') }}</td>
        </tr>
        <tr>
            <td><strong>Collected:</strong> {{ $order->collected_at?->format('d M Y h:i A') ?? '—' }}</td>
            <td><strong>Completed:</strong> {{ $order->completed_at?->format('d M Y h:i A') ?? '—' }}</td>
        </tr>
    </table>

    <h2>Results</h2>
    <table>
        <thead>
            <tr><th>Test</th><th>Result</th><th>Reference Range</th><th>Flag</th></tr>
        </thead>
        <tbody>
            @foreach($results as $result)
                <tr class="{{ $result->status === 'critical' ? 'critical' : ($result->status === 'abnormal' ? 'abnormal' : '') }}">
                    <td>{{ $result->labTest->name ?? 'N/A' }}{{ $result->labTest->unit ? ' ('.$result->labTest->unit.')' : '' }}</td>
                    <td>
                        <strong>{{ $result->result_value ?? '—' }}</strong>
                        @if($result->result_text)<br>{{ $result->result_text }}@endif
                        @if($result->comments)<br><em>{{ $result->comments }}</em>@endif
                    </td>
                    <td>{{ $result->normal_range ?? $result->labTest->normal_range ?? '—' }}</td>
                    <td>{{ ucfirst($result->status) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($order->result_notes)
        <h2>Notes</h2>
        <p>{{ $order->result_notes }}</p>
    @endif

    <div class="footer">
        <p>Generated on {{ $generated_at }}</p>
        <div class="sign">
            <p>______________________</p>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
