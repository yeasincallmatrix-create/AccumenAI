@extends('layouts.institute')

@section('title', 'Budgets — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-wallet2 me-2"></i>Budgets</h4>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.reports') }}"><i class="bi bi-bar-chart me-1"></i>Reports</a>
        <a class="btn btn-outline-primary btn-sm rounded-pill" href="{{ route('finance.budgets.forecast') }}"><i class="bi bi-graph-up me-1"></i>Forecast</a>
        @if (auth()->user()->hasPermission('budget.create') || (auth()->user() instanceof \App\Models\User && \App\Support\Workspace::membership()?->hasPermission('budget.create')))
            <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('finance.budgets.create') }}"><i class="bi bi-plus-lg me-1"></i>New Budget</a>
        @endif
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Fiscal Year</label>
                <select name="fiscal_year_id" class="form-select form-select-sm">
                    <option value="">All Years</option>
                    @foreach ($fiscalYears as $fy)
                        <option value="{{ $fy->id }}" {{ request('fiscal_year_id') == $fy->id ? 'selected' : '' }}>{{ $fy->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select form-select-sm">
                    <option value="">All Types</option>
                    @foreach (['revenue', 'expense', 'cost', 'asset'] as $type)
                        <option value="{{ $type }}" {{ request('type') === $type ? 'selected' : '' }}>{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    @foreach (['draft', 'submitted', 'approved', 'rejected', 'locked'] as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
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
                    <th>Type</th>
                    <th>Fiscal Year</th>
                    <th>Currency</th>
                    <th class="text-end">Amount</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($budgets as $budget)
                    <tr>
                        <td class="fw-semibold">{{ $budget->name }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($budget->type) }}</span></td>
                        <td>{{ $budget->fiscalYear?->name ?? '—' }}</td>
                        <td>{{ $budget->currency?->code ?? '—' }}</td>
                        <td class="text-end">{{ number_format($budget->total_amount, 2) }}</td>
                        <td>v{{ $budget->version }}</td>
                        <td>
                            @php
                                $statusColors = ['draft' => 'secondary', 'submitted' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'locked' => 'primary'];
                            @endphp
                            <span class="badge bg-{{ $statusColors[$budget->status] ?? 'secondary' }}">{{ ucfirst($budget->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('finance.budgets.show', $budget->id) }}"><i class="bi bi-eye"></i></a>
                            @if ($budget->isEditable())
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('finance.budgets.edit', $budget->id) }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No budgets found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($budgets->hasPages())
        <div class="card-footer">
            {{ $budgets->links() }}
        </div>
    @endif
</div>
@endsection
