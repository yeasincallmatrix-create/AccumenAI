@extends('layouts.institute')
@section('title','New Review — HR')
@section('content')
<div class="standalone-heading"><h4>New Performance Review</h4><p>Link employee, period, reviewer, KPIs.</p></div>
<div class="admin-card p-3">
    <form method="POST" action="{{ route('hr.performance.reviews.store') }}">
        @csrf
        <div class="row g-2">
            <div class="col-md-4"><label class="form-label small">Employee *</label><select name="employee_id" class="form-select form-select-sm" required><option value="">— Select —</option>@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->display_name }} ({{ $e->employee_code }})</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label small">Period *</label><select name="period_id" class="form-select form-select-sm" required><option value="">— Select —</option>@foreach($periods as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->start_date->format('Y-m-d') }}→{{ $p->end_date->format('Y-m-d') }})</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label small">Review Date *</label><input type="date" name="review_date" value="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
            <div class="col-md-12"><label class="form-label small">Comments</label><textarea name="comments" class="form-control form-control-sm" rows="2"></textarea></div>
            <div class="col-12"><h6>KPIs (optional)</h6><div id="kpi-rows">
                @foreach($kpis as $kpi)
                    <div class="row g-1 mb-1 align-items-end">
                        <div class="col-md-3"><input type="hidden" name="kpis[{{ $loop->index }}][kpi_id]" value="{{ $kpi->id }}"><input type="text" name="kpis[{{ $loop->index }}][name]" value="{{ $kpi->name }}" class="form-control form-control-sm"></div>
                        <div class="col-md-2"><input type="text" name="kpis[{{ $loop->index }}][target]" value="{{ $kpi->target }}" class="form-control form-control-sm" placeholder="Target"></div>
                        <div class="col-md-1"><input type="number" step="0.5" name="kpis[{{ $loop->index }}][weight]" value="{{ $kpi->weight }}" class="form-control form-control-sm"></div>
                        <div class="col-md-1"><input type="number" step="0.5" name="kpis[{{ $loop->index }}][score]" class="form-control form-control-sm" placeholder="Score"></div>
                    </div>
                @endforeach
            </div></div>
            <div class="col-12"><button type="submit" class="btn btn-primary btn-sm">Create Review</button> <a href="{{ route('hr.performance.reviews') }}" class="btn btn-outline-secondary btn-sm">Cancel</a></div>
        </div>
    </form>
</div>
@endsection
