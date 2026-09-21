@extends('layouts.standalone')

@section('title', 'Recurring Templates — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Recurring Templates</h4>
    <p>Automate repetitive financial transactions with scheduled templates.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('finance.recurring-templates.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Template</a>
    </div>
</div>

<div class="admin-card mb-3">
    <form method="GET" class="row g-2 align-items-end">
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Search template # or name...">
        </div>
        <div class="col-md-3">
            <select class="form-select form-select-sm" name="type">
                <option value="">All types</option>
                @foreach (['journal_entry', 'invoice', 'vendor_bill', 'expense', 'payment'] as $t)
                    <option value="{{ $t }}" @selected(request('type') === $t)>{{ str_replace('_', ' ', ucfirst($t)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">All statuses</option>
                @foreach (['active', 'paused', 'completed', 'cancelled', 'failed'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
            @if (request('q') || request('type') || request('status'))
                <a href="{{ route('finance.recurring-templates.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </div>
    </form>
</div>

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Template #</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Frequency</th>
                    <th>Next Run</th>
                    <th>Occurrences</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $tpl)
                    <tr>
                        <td><a href="{{ route('finance.recurring-templates.show', $tpl) }}" class="text-decoration-none fw-semibold">{{ $tpl->template_number }}</a></td>
                        <td>{{ $tpl->name }}</td>
                        <td><span class="badge text-bg-light border">{{ str_replace('_', ' ', $tpl->transaction_type) }}</span></td>
                        <td>{{ ucfirst($tpl->frequency) }} @if($tpl->interval_count > 1)(every {{ $tpl->interval_count }})@endif</td>
                        <td>{{ $tpl->next_run_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td>{{ $tpl->occurrences_generated }}@if($tpl->max_occurrences)/{{ $tpl->max_occurrences }}@endif</td>
                        <td><span class="badge text-bg-{{ $tpl->statusColor() }}">{{ $tpl->status }}</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('finance.recurring-templates.show', $tpl) }}" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
                                @if($tpl->isActive() || $tpl->isPaused())
                                    <form method="POST" action="{{ route('finance.recurring-templates.generate-now', $tpl) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-outline-success" type="submit" title="Generate Now"><i class="bi bi-play"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No recurring templates found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">
        {{ $templates->links() }}
    </div>
</div>

@endsection
