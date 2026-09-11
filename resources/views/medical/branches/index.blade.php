@extends('layouts.institute')

@section('title', 'Branches — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Branches</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-primary" href="{{ route('medical.branches.create') }}">
            <i class="bi bi-plus-lg me-1"></i>New Branch
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @if($branches->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Code</th><th>Name</th><th>Status</th><th>Principal</th><th></th></tr></thead>
                    <tbody>
                        @foreach($branches as $branch)
                            <tr>
                                <td><code>{{ $branch->code }}</code></td>
                                <td>{{ $branch->name }}</td>
                                <td>
                                    <span class="badge bg-{{ $branch->status === 'active' ? 'success' : 'secondary' }}">
                                        {{ ucfirst($branch->status) }}
                                    </span>
                                </td>
                                <td>{{ $branch->is_principal ? 'Yes' : '—' }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-secondary" href="{{ route('medical.branches.show', $branch) }}">Manage</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $branches->links() }}</div>
        @else
            <p class="text-muted mb-0">No branches yet.</p>
        @endif
    </div>
</div>
@endsection
