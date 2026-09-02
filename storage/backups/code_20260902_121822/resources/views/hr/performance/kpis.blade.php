@extends('layouts.institute')
@section('title','KPIs — HR')
@section('content')
<div class="standalone-heading"><h4>KPI Management</h4><p>Configurable KPI name, description, target, measurement, weight, score.</p></div>
<div class="admin-card p-3 mb-3">
    <h6>Create KPI</h6>
    <form method="POST" action="{{ route('hr.performance.kpis.store') }}" class="row g-2">
        @csrf
        <div class="col-md-2"><input type="text" name="name" class="form-control form-control-sm" placeholder="Name *" required></div>
        <div class="col-md-2"><input type="text" name="target" class="form-control form-control-sm" placeholder="Target"></div>
        <div class="col-md-2"><input type="text" name="measurement" class="form-control form-control-sm" placeholder="Measurement"></div>
        <div class="col-md-1"><input type="number" step="0.01" name="weight" class="form-control form-control-sm" placeholder="Weight" value="1"></div>
        <div class="col-md-2"><select name="branch_id" class="form-select form-select-sm"><option value="">All branches</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
        <div class="col-md-1"><button type="submit" class="btn btn-sm btn-primary">Create</button></div>
    </form>
</div>
<div class="admin-card">
    <div class="table-responsive">
        <table class="table small mb-0">
            <thead><tr><th>Name</th><th>Target</th><th>Measurement</th><th>Weight</th><th>Branch</th><th>Active</th></tr></thead>
            <tbody>
                @forelse($kpis as $k)
                    <tr><td>{{ $k->name }}<div class="text-muted small">{{ $k->description }}</div></td><td>{{ $k->target ?? '—' }}</td><td>{{ $k->measurement ?? '—' }}</td><td>{{ $k->weight }}</td><td>{{ $k->branch?->name ?? 'All' }}</td><td>{{ $k->is_active ? 'Yes' : 'No' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No KPIs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($kpis->hasPages())<div class="p-2 border-top">{{ $kpis->links() }}</div>@endif
</div>
@endsection
