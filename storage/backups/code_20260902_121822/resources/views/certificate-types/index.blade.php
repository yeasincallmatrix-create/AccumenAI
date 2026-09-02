@extends('layouts.institute')

@section('title', 'Certificate Types — AccumenAI')

@php
    $statusBadge = ['1' => 'text-bg-success', '0' => 'text-bg-secondary'];
    $statusNames = ['1' => 'Active', '0' => 'Inactive'];
@endphp

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Certificate Types</h4>
        <p class="page-header-desc mb-0">Configure certificate categories for your institute (e.g. Course Completion, Graduation, Training).</p>
    </div>
    <div class="page-header-actions">
        <a href="{{ route('certificates.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Certificates
        </a>
        <a href="{{ route('certificate-types.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Add Type
        </a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:50px">#</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th style="width:80px">Order</th>
                    <th class="text-end" style="width:140px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($types as $type)
                    <tr>
                        <td class="text-muted">{{ $types->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $type->name }}</td>
                        <td>{{ $type->description ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $statusBadge[$type->is_active ? '1' : '0'] ?? 'text-bg-secondary' }}">
                                {{ $type->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $type->display_order }}</td>
                        <td class="text-end">
                            <a href="{{ route('certificate-types.edit', $type) }}" class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form action="{{ route('certificate-types.destroy', $type) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this certificate type?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No certificate types configured yet. Click "Add Type" to create one.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($types->hasPages())
        <div class="card-footer bg-transparent">{{ $types->links() }}</div>
    @endif
</div>
@endsection
