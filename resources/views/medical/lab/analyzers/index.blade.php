@extends('layouts.institute')

@section('title', 'Lab Analyzers — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Lab Analyzers</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.dashboard') }}">
            <i class="bi bi-speedometer2 me-1"></i>Dashboard
        </a>
        <a class="btn btn-primary" href="{{ route('medical.laboratory.analyzers.create') }}">
            <i class="bi bi-plus-lg me-1"></i>Add Analyzer
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search name, code, manufacturer, model...">
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::INSTRUMENT_TYPES as $type)
                            <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        @foreach(\App\Models\LabIntegration\LabAnalyzer::STATUSES as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="{{ route('medical.laboratory.analyzers.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Code</th><th>Name</th><th>Manufacturer / Model</th><th>Type</th><th>Protocol</th><th>Adapter</th><th>Status</th><th>Last Seen</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($analyzers as $analyzer)
                    <tr>
                        <td><strong>{{ $analyzer->code }}</strong></td>
                        <td>{{ $analyzer->name }}</td>
                        <td>{{ $analyzer->manufacturer ?? '—' }} / {{ $analyzer->model ?? '—' }}</td>
                        <td>{{ ucfirst($analyzer->instrument_type) }}</td>
                        <td><span class="badge bg-secondary">{{ strtoupper($analyzer->protocol) }}</span></td>
                        <td><code>{{ $analyzer->adapter_key }}:{{ $analyzer->adapter_version }}</code></td>
                        <td>@include('medical.lab.analyzers._status_badge', ['status' => $analyzer->status])</td>
                        <td>{{ $analyzer->last_seen_at ? $analyzer->last_seen_at->diffForHumans() : 'Never' }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.laboratory.analyzers.show', $analyzer) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.laboratory.analyzers.edit', $analyzer) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            <i class="bi bi-robot fs-2 d-block mb-2"></i>
                            No analyzers registered.
                            <a href="{{ route('medical.laboratory.analyzers.create') }}">Add your first analyzer</a>.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $analyzers->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
