@extends('layouts.institute')
@section('title','Supplier Statement')
@section('content')
<h4>Supplier Statement</h4>
<div class="card mb-3"><div class="card-body">
<form method="GET" class="row g-2 align-items-end">
<div class="col-md-3">
<label class="form-label">Supplier *</label>
<select name="supplier_id" class="form-select form-select-sm" required>
@foreach($suppliers as $s)<option value="{{ $s->id }}" {{ isset($data['supplier']) && (string)$data['supplier']->id===(string)$s->id ? 'selected':'' }}>{{ $s->name }}</option>@endforeach
</select>
</div>
<div class="col-md-2"><label class="form-label">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm"></div>
<div class="col-md-2"><label class="form-label">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm"></div>
<div class="col-md-2"><button class="btn btn-sm btn-primary" type="submit">View</button> <a href="{{ route('purchase.reports.export',['type'=>'supplierStatement','supplier_id'=>request('supplier_id'),'from'=>request('from'),'to'=>request('to')]) }}" class="btn btn-sm btn-outline-success">CSV</a></div>
</form>
</div></div>
@if(isset($data))
<div class="card mb-3"><div class="card-body">
<div class="d-flex justify-content-between">
<div><strong>Supplier:</strong> {{ $data['supplier']->name }}<br><span class="text-muted small">{{ $data['supplier']->phone }}</span></div>
<div class="text-end"><strong>Opening:</strong> {{ number_format($data['opening_balance'],2) }}<br><strong>Closing:</strong> {{ number_format($data['closing_balance'],2) }}</div>
</div>
</div></div>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Date</th><th>Type</th><th>Ref</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr></thead><tbody>
<tr><td colspan="5" class="text-end fw-bold">Opening Balance</td><td class="text-end fw-bold">{{ number_format($data['opening_balance'],2) }}</td></tr>
@forelse($data['entries'] as $e)<tr><td>{{ $e['date'] }}</td><td>{{ $e['type'] }}</td><td>{{ $e['ref'] }}</td><td class="text-end">{{ number_format($e['debit'],2) }}</td><td class="text-end">{{ number_format($e['credit'],2) }}</td><td class="text-end">{{ number_format($e['running_balance'],2) }}</td></tr>
@empty<tr><td colspan="6" class="text-center text-muted">No transactions in range</td></tr>@endforelse
<tr><td colspan="5" class="text-end fw-bold">Closing Balance</td><td class="text-end fw-bold">{{ number_format($data['closing_balance'],2) }}</td></tr>
</tbody></table></div></div>
@endif
@endsection
