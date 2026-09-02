@extends('layouts.standalone')

@section('title', 'Sales Leads — AccumenAI')
@section('page_title', 'Sales Leads')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-funnel me-2"></i>Sales Leads</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('sales.leads.create') }}"><i class="bi bi-plus-lg me-1"></i>New Lead</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Name, email or phone">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s->id }}" {{ request('status_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('sales.leads.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Source</th>
                    <th class="text-end">Value</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($leads as $lead)
                    <tr>
                        <td class="fw-semibold">{{ $lead->displayName() }}</td>
                        <td>{{ $lead->email ?? '—' }}</td>
                        <td>{{ $lead->phone ?? '—' }}</td>
                        <td>
                            @if ($lead->status)
                                <span class="badge" style="background-color: {{ $lead->status->color ?? '#6c757d' }}">{{ $lead->status->name }}</span>
                            @else
                                <span class="badge bg-secondary">—</span>
                            @endif
                        </td>
                        <td>{{ $lead->source?->name ?? '—' }}</td>
                        <td class="text-end">{{ $lead->value_amount ? number_format($lead->value_amount, 2) : '—' }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('sales.leads.show', $lead) }}"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No leads found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($leads->hasPages())
        <div class="card-footer">{{ $leads->links() }}</div>
    @endif
</div>
@endsection
