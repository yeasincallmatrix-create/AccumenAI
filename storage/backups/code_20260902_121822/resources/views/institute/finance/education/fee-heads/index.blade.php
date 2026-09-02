@extends('layouts.standalone')

@section('title', 'Fee Heads — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>Fee Heads</h4>
    <p>Education-specific billable fees for {{ $institute->name }}. Each head maps to an income account so generated invoices credit the right revenue line.</p>
    <a href="{{ route('finance.education.dashboard') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Education Finance</a>
</div>

@if ($user->hasPermission('accounts.manage'))
    <div class="admin-card mb-3">
        <h6 class="card-title">Add fee head</h6>
        <form method="POST" action="{{ route('finance.education.fee-heads.store') }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-2">
                    <input type="text" class="form-control form-control-sm" name="name" placeholder="Name (e.g. Course Fee)" required>
                </div>
                <div class="col-md-1">
                    <input type="text" class="form-control form-control-sm" name="code" placeholder="Code">
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="type" required>
                        @foreach ($types as $type)
                            <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="default_amount" placeholder="Amount" value="0">
                </div>
                <div class="col-md-1">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_recurring" value="1" id="addRecurring">
                        <label class="form-check-label small" for="addRecurring">Recurring</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="billing_frequency">
                        <option value="one_time">One-time</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="annually">Annually</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <input type="number" class="form-control form-control-sm" name="sort_order" placeholder="Order" value="0">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary btn-sm w-100" type="submit"><i class="bi bi-plus-lg"></i></button>
                </div>
            </div>
            <div class="mt-2">
                <input type="text" class="form-control form-control-sm" name="description" placeholder="Description (optional)">
            </div>
        </form>
    </div>
@endif

<div class="admin-card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Order</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th class="text-end">Default amount</th>
                    <th>Recurring</th>
                    <th>Frequency</th>
                    <th>Income account</th>
                    <th>Status</th>
                    @if ($user->hasPermission('accounts.manage'))
                        <th class="text-end">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($feeHeads->sortBy('sort_order') as $feeHead)
                    <tr>
                        <td class="text-muted">{{ $feeHead->sort_order }}</td>
                        <td>{{ $feeHead->name }}@if ($feeHead->code) <span class="text-muted small">({{ $feeHead->code }})</span>@endif</td>
                        <td><span class="badge text-bg-light text-muted">{{ ucwords(str_replace('_', ' ', $feeHead->type)) }}</span></td>
                        <td class="text-end">{{ number_format($feeHead->default_amount, 2) }}</td>
                        <td>
                            @if ($feeHead->is_recurring)
                                <span class="badge text-bg-info">Recurring</span>
                            @else
                                <span class="badge text-bg-light text-muted">One-time</span>
                            @endif
                        </td>
                        <td>{{ $feeHead->billingFrequencyLabel() }}</td>
                        <td>{{ $feeHead->incomeAccount?->code }} {{ $feeHead->incomeAccount?->name ?? '—' }}</td>
                        <td>
                            @if ($user->hasPermission('accounts.manage'))
                                <form method="POST" action="{{ route('finance.education.fee-heads.toggle', $feeHead) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-{{ $feeHead->is_active ? 'success' : 'secondary' }}">{{ $feeHead->is_active ? 'Active' : 'Inactive' }}</button>
                                </form>
                            @else
                                <span class="badge text-bg-{{ $feeHead->is_active ? 'success' : 'secondary' }}">{{ $feeHead->is_active ? 'Active' : 'Inactive' }}</span>
                            @endif
                        </td>
                        @if ($user->hasPermission('accounts.manage'))
                            <td class="text-end">
                                <form method="POST" action="{{ route('finance.education.fee-heads.destroy', $feeHead) }}" class="d-inline" onsubmit="return confirm('Delete this fee head?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No fee heads yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
