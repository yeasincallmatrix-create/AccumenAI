@extends('layouts.institute')

@section('title', 'Purchase Quotations — AccumenAI')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Purchase Quotations</h4>
    <a class="btn btn-primary btn-sm rounded-pill" href="{{ route('purchase.quotations.create') }}"><i class="bi bi-plus-lg me-1"></i>New Quotation</a>
</div>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@include('purchase._tabs', ['activeTab' => 'quotations'])

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Number / Reference / Supplier">
            </div>
            <div class="col-md-2">
                <label class="form-label">Supplier</label>
                <select name="supplier_id" class="form-select form-select-sm">
                    <option value="">All Suppliers</option>
                    @php
                        $suppliers = \App\Models\Party::withoutGlobalScopes()->where('institute_id', $institute->id)->whereIn('type', ['supplier','both'])->where('is_active', true)->orderBy('name')->get();
                    @endphp
                    @foreach ($suppliers as $sup)
                        <option value="{{ $sup->id }}" {{ (string)request('supplier_id') === (string)$sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    @foreach ($statuses as $s)
                        <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Warehouse</label>
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    @php
                        $warehouses = \App\Models\InventoryWarehouse::withoutGlobalScopes()->where('institute_id', $institute->id)->orderBy('name')->get();
                    @endphp
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}" {{ (string)request('warehouse_id') === (string)$wh->id ? 'selected' : '' }}>{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            @php
                $actingUser = request()->user();
                $actingBranchId = null;
                if ($actingUser instanceof \App\Models\InstituteUser && $actingUser->branch_id !== null) {
                    $actingBranchId = (int) $actingUser->branch_id;
                } elseif ($actingUser instanceof \App\Models\User) {
                    $m = \App\Support\Workspace::membership();
                    if ($m !== null && $m->branch_id !== null) $actingBranchId = (int) $m->branch_id;
                }
                $branches = \App\Models\Branch::where('institute_id', $institute->id)->orderBy('name')->get();
            @endphp
            @if ($actingBranchId === null && $branches->count() > 1)
                <div class="col-md-2">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">All Branches</option>
                        @foreach ($branches as $br)
                            <option value="{{ $br->id }}" {{ (string)request('branch_id') === (string)$br->id ? 'selected' : '' }}>{{ $br->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-3">
                <button class="btn btn-sm btn-primary rounded-pill" type="submit"><i class="bi bi-search me-1"></i>Filter</button>
                <a href="{{ route('purchase.quotations.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Number</th>
                    <th>Supplier</th>
                    <th>Date</th>
                    <th>Valid Until</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($quotations as $q)
                    <tr>
                        <td class="fw-semibold">{{ $q->quotation_number }}</td>
                        <td>{{ $q->supplier?->name ?? '—' }}</td>
                        <td>{{ $q->quotation_date?->format('Y-m-d') }}</td>
                        <td>{{ $q->validity_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="text-end">{{ number_format($q->grand_total, 2) }} {{ $q->currency?->code }}</td>
                        <td>
                            @php $colors = ['draft'=>'secondary','sent'=>'info','accepted'=>'success','rejected'=>'danger','expired'=>'warning','cancelled'=>'dark']; @endphp
                            <span class="badge bg-{{ $colors[$q->status] ?? 'secondary' }}">{{ ucfirst($q->status) }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary rounded-pill" href="{{ route('purchase.quotations.show', $q) }}"><i class="bi bi-eye"></i></a>
                            @if ($q->isDraft())
                                <a class="btn btn-sm btn-outline-secondary rounded-pill" href="{{ route('purchase.quotations.edit', $q) }}"><i class="bi bi-pencil"></i></a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No quotations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($quotations->hasPages())
        <div class="card-footer">{{ $quotations->links() }}</div>
    @endif
</div>
@endsection
