@extends('layouts.admin')

@section('title', 'Grade Scales — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">Grade Scales</h4>
        <p class="page-header-desc">Global grading defaults resolved via the ladder GLOBAL → COUNTRY → SYSTEM → LEVEL. Institute overrides are managed by each institute and never appear here.</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('admin.academic.grading.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Grade Scale
        </a>
    </div>
</div>

<div class="admin-card mt-3">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Scope</th>
                    <th>GPA Mode</th>
                    <th>Optional Subjects</th>
                    <th>Bands</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($scales as $scale)
                    <tr>
                        <td class="fw-semibold">{{ $scale->name }}</td>
                        <td>
                            <span class="badge text-bg-light border">{{ $scale->scopeLabel() }}</span>
                        </td>
                        <td>{{ $scale->gpa_mode === 'credit_weighted' ? 'Credit Weighted' : 'Equal Weight' }}</td>
                        <td>{{ ucfirst($scale->optional_subject_gpa) }}</td>
                        <td>
                            <span class="badge text-bg-primary">{{ $scale->rows_count }}</span>
                        </td>
                        <td>
                            @if ($scale->status)
                                <span class="badge text-bg-success">Active</span>
                            @else
                                <span class="badge text-bg-secondary">Inactive</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('admin.academic.grading.edit', $scale) }}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.academic.grading.destroy', $scale) }}" class="d-inline" data-ajax-delete="1" data-confirm="Remove this grade scale?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No grade scales configured yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
