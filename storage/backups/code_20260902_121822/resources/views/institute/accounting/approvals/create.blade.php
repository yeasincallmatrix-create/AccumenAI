@extends('layouts.standalone')

@section('title', 'Create Approval Workflow — AccumenAI')
@section('page_title', 'Approvals')

@section('content')

<div class="standalone-heading">
    <h4>Create Approval Workflow</h4>
    <p>Define a new multi-step approval chain.</p>
    <a href="{{ route('accounting.approvals.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
</div>

<div class="admin-card">
    <form method="POST" action="{{ route('accounting.approvals.store') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">Workflow Name</label>
            <input type="text" name="name" class="form-control" required placeholder="e.g. Journal Approval — High Value">
        </div>

        <div class="mb-3">
            <label class="form-label">Module</label>
            <select name="module" class="form-select" required>
                <option value="">— Select Module —</option>
                @foreach ($modules as $mod)
                    <option value="{{ $mod }}">{{ ucfirst($mod) }}</option>
                @endforeach
            </select>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">Amount From</label>
                <input type="number" step="0.01" name="amount_from" class="form-control" required value="0">
            </div>
            <div class="col-md-6">
                <label class="form-label">Amount To</label>
                <input type="number" step="0.01" name="amount_to" class="form-control" required value="999999999">
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label">Approver Roles (in order)</label>
            <select name="approver_role_ids[]" class="form-select" multiple required size="5">
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                @endforeach
            </select>
            <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple. Order determines approval chain.</small>
        </div>

        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg"></i> Create Workflow</button>
    </form>
</div>

@endsection
