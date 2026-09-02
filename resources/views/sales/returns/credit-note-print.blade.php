<!DOCTYPE html><html><head><meta charset="utf-8"><title>Credit Note {{ $ret->credit_note_number }}</title><style>body{font-family:sans-serif;margin:40px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:8px}th{background:#f8f9fa}</style></head><body onload="window.print()">
<h2>Credit Note: {{ $ret->credit_note_number }}</h2>
<p>Return: {{ $ret->return_number }} | Invoice: {{ $ret->invoice?->invoice_number }} | Customer: {{ $ret->customer?->name }} | Date: {{ $ret->return_date->format('Y-m-d') }}</p>
<table><thead><tr><th>Item</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead><tbody>@foreach($ret->items as $it)<tr><td>{{ $it->description }}</td><td>{{ $it->quantity }}</td><td>{{ number_format($it->unit_price,2) }}</td><td>{{ number_format($it->line_total,2) }}</td></tr>@endforeach</tbody><tfoot><tr><th colspan="3" style="text-align:right">Total</th><th>{{ number_format($ret->grand_total,2) }}</th></tr></tfoot></table>
<p>Status: {{ ucfirst($ret->status) }} | Refund: {{ ucfirst($ret->refund_status) }}</p>
</body></html>
