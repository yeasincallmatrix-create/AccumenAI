<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice — {{ clinical_no($invoice->invoice_number) }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 20px; }
        .header p { margin: 2px 0; font-size: 11px; color: #555; }
        h2 { font-size: 14px; background: #f0f0f0; padding: 5px 8px; margin: 15px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 5px 8px; text-align: left; font-size: 11px; }
        th { background: #f5f5f5; }
        td.num, th.num { text-align: right; }
        .meta td { border: none; padding: 3px 8px 3px 0; vertical-align: top; }
        .totals td { border: none; text-align: right; padding: 3px 8px; }
        .footer { margin-top: 30px; }
        .sign { float: right; text-align: center; margin-top: 40px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $hospital_name }}</h1>
        <p>{{ $hospital_address }}</p>
        <h2 style="background:none; border:none; text-align:center;">{{ strtoupper($invoice->type) }} INVOICE — {{ clinical_no($invoice->invoice_number) }}</h2>
    </div>

    <h2>Bill To</h2>
    <table class="meta">
        <tr>
            <td><strong>Patient:</strong> {{ $patient->full_name ?? 'N/A' }} ({{ clinical_no($patient->mr_number ?? '') }})</td>
            <td><strong>Invoice Date:</strong> <x-tdate :value="$invoice->invoice_date" fallback="d M Y" /></td>
        </tr>
        <tr>
            <td><strong>Phone:</strong> {{ $patient->phone ?? 'N/A' }}</td>
            <td><strong>Due Date:</strong> <x-tdate :value="$invoice->due_date" fallback="d M Y" /></td>
        </tr>
        @if($admission)
        <tr>
            <td><strong>Admission:</strong> <x-tdate :value="$admission->admission_date" fallback="d M Y" /></td>
            <td><strong>Status:</strong> {{ ucfirst($invoice->status) }}</td>
        </tr>
        @endif
    </table>

    <h2>Items</h2>
    <table>
        <thead>
            <tr><th>#</th><th>Description</th><th class="num">Amount</th><th class="num">Qty</th><th class="num">Discount</th><th class="num">Line Total</th></tr>
        </thead>
        <tbody>
            @foreach($items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item['description'] ?? '' }}</td>
                <td class="num">{{ number_format($item['amount'] ?? 0, 2) }}</td>
                <td class="num">{{ $item['quantity'] ?? 1 }}</td>
                <td class="num">{{ number_format($item['discount'] ?? 0, 2) }}</td>
                <td class="num">{{ number_format(($item['amount'] ?? 0) * ($item['quantity'] ?? 1), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td><strong>Subtotal:</strong> {{ number_format($invoice->subtotal, 2) }}</td></tr>
        <tr><td><strong>Tax (5%):</strong> {{ number_format($invoice->tax, 2) }}</td></tr>
        <tr><td><strong>Discount:</strong> {{ number_format($invoice->discount, 2) }}</td></tr>
        <tr><td><strong>Total:</strong> {{ number_format($invoice->total, 2) }}</td></tr>
        <tr><td><strong>Paid:</strong> {{ number_format($invoice->paid_amount, 2) }}</td></tr>
        <tr><td><strong>Due:</strong> {{ number_format($invoice->due_amount, 2) }}</td></tr>
    </table>

    <div class="footer">
        <div class="sign">
            <p>______________________</p>
            <p>Authorized Signature</p>
        </div>
    </div>
</body>
</html>
