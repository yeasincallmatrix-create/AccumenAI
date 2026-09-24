@extends('layouts.institute')

@section('title', 'Batches — Training — AccumenAI')

@section('content')

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="#" class="text-decoration-none">Training</a></li>
        <li class="breadcrumb-item active">Batches</li>
    </ol>
</nav>

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Batches</h4>
        <p class="page-header-desc mb-0">Training batch management</p>
    </div>
    @if ($user->hasPermission('training_batches.manage'))
        <div class="page-header-actions">
            <a href="{{ route('training.batches.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Batch
            </a>
        </div>
    @endif
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Course</th>
                    <th>Code</th>
                    <th>Status</th>
                    <th>Seats</th>
                    <th>Start Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($batches as $batch)
                    <tr>
                        <td class="text-muted">{{ $batches->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">
                            <a href="{{ route('training.batches.show', $batch->id) }}" class="text-decoration-none">{{ $batch->name }}</a>
                        </td>
                        <td>{{ $batch->course?->name ?? '—' }}</td>
                        <td>{{ $batch->batch_code ?? '—' }}</td>
                        <td>
                            <span class="badge {{ in_array($batch->status ?? '', ['ongoing', 'running'], true) ? 'text-bg-success' : (($batch->status ?? '') === 'completed' ? 'text-bg-primary' : ((($batch->status ?? '') === 'cancelled' || ($batch->status ?? '') === 'archived') ? 'text-bg-danger' : 'text-bg-secondary')) }}">
                                {{ ucfirst($batch->status ?? '—') }}
                            </span>
                        </td>
                        <td>{{ $batch->seat_filled ?? 0 }} / {{ $batch->seat_capacity ?? '—' }}</td>
                        <td><x-tdate :value="$batch->start_date" fallback="d M Y" empty="—" /></td>
                        <td class="text-end">
                            <a href="{{ route('training.batches.show', $batch->id) }}" class="btn btn-sm btn-outline-primary">View</a>
                            @if ($user->hasPermission('training_batches.manage'))
                                <a href="{{ route('training.batches.edit', $batch->id) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No batches yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2" data-ajax-pagination>
        {{ $batches->links('pagination::bootstrap-5') }}
    </nav>
</div>

@endsection
