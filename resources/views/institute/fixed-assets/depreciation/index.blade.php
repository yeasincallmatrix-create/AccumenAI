@extends('layouts.standalone')

@section('title', 'Depreciation Runs — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Depreciation Runs</h4>
    <p>Batch depreciation posting for a period. One run per period prevents duplicate posting.</p>
    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#runForm"><i class="bi bi-plus-lg me-1"></i>Run Depreciation</button>
</div>

<div class="collapse" id="runForm">
    <div class="admin-card mb-3">
        <h6 class="card-title">Post Depreciation</h6>
        <form method="POST" action="{{ route('fixed_assets.depreciation.run') }}">
            @csrf
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Period Start <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="period_start" value="{{ old('period_start', now()->startOfMonth()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Period End <span class="text-danger">*</span></label>
                    <input type="date" class="form-control form-control-sm" name="period_end" value="{{ old('period_end', now()->endOfMonth()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-success btn-sm" type="submit" data-ajax-submit="1" data-confirm="Run depreciation for this period?"><i class="bi bi-calculator me-1"></i>Run Depreciation</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Journal</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="text-muted">{{ $runs->firstItem() + $loop->index }}</td>
                        <td>{{ $run->period_start->format('Y-m-d') }} to {{ $run->period_end->format('Y-m-d') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $run->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($run->status) }}</span>
                        </td>
                        <td>{{ $run->journal?->journal_no ?? '—' }}</td>
                        <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('fixed_assets.depreciation.show', $run) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No depreciation runs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($runs->hasPages())
        <div class="p-2 border-top">{{ $runs->links() }}</div>
    @endif
</div>

@endsection
