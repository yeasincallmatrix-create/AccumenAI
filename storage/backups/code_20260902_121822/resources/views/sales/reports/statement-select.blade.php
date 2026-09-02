@extends('layouts.institute')
@section('title','Customer Statement — Select — AccumenAI')
@section('content')
<h4 class="mb-3">Customer Statement — Select Customer</h4>
<div class="card"><div class="card-body">
<form method="GET" action="{{ route('sales.reports.statement') }}" class="d-flex gap-2">
<select name="customer_id" class="form-select" required>
<option value="">— Choose customer —</option>
@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} — {{ $c->phone }} (#{{ $c->id }})</option>@endforeach
</select>
<button class="btn btn-primary rounded-pill">View Statement</button>
</form>
</div></div>
@endsection
