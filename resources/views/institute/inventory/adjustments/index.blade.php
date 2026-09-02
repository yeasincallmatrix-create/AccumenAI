@extends('layouts.standalone')
@section('title', 'Stock Adjustments — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Stock Adjustments</h4>
    <p>Controlled stock adjustments (surplus / deficit / wastage). Posting records system vs counted quantities and creates inventory journals.</p>
    <a href="{{ route('inventory.adjustments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Adjustment</a>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.adjustments.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Adjustment # or reason">
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Type</label>
                <select class="form-select form-select-sm" name="adjustment_type" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <option value="adjustment" @selected(request('adjustment_type')==='adjustment')>Adjustment</option>
                    <option value="wastage" @selected(request('adjustment_type')==='wastage')>Wastage</option>
                </select>
            </div>
            <div class="filter-span">
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <option value="posted" @selected(request('status')==='posted')>Posted</option>
                </select>
            </div>
            <div class="filter-span">
                <button class="btn btn-outline-primary btn-sm mt-1" type="submit"><i class="bi bi-search"></i> Filter</button>
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.adjustments.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

@if(($createMode ?? false))
<div class="card mb-3">
    <div class="card-body">
        <h6>New Stock Adjustment</h6>
        <form method="POST" action="{{ route('inventory.adjustments.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                    <select class="form-select" name="warehouse_id" required>
                        <option value="">Select warehouse...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="adjustment_type" required>
                        <option value="adjustment">Adjustment</option>
                        <option value="wastage">Wastage</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Reason <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="reason" value="{{ old('reason') }}" required>
                </div>
            </div>
            <h6>Adjustment Lines</h6>
            <div id="adj-lines">
                <div class="row g-2 mb-2 adj-line">
                    <div class="col-md-4">
                        <select class="form-select form-select-sm" name="lines[0][item_id]" required>
                            <option value="">Select item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.0001" class="form-control form-control-sm" name="lines[0][system_qty]" placeholder="System Qty" required>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.0001" class="form-control form-control-sm" name="lines[0][counted_qty]" placeholder="Counted Qty" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-adj-line"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addAdjLine()"><i class="bi bi-plus"></i> Add Line</button>
            <div><button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check-circle me-1"></i>Post Adjustment</button></div>
        </form>
    </div>
</div>
@endif

<div class="admin-card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-muted">#</th>
                    <th>Adjustment #</th>
                    <th>Warehouse</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($adjustments as $adj)
                    <tr>
                        <td class="text-muted">{{ $adjustments->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('inventory.adjustments.show', $adj) }}" class="fw-semibold">{{ $adj->adjustment_no }}</a></td>
                        <td>{{ $adj->warehouse?->name ?? '—' }}</td>
                        <td><span class="badge text-bg-light border">{{ ucfirst($adj->adjustment_type) }}</span></td>
                        <td>{{ Str::limit($adj->reason, 40) }}</td>
                        <td>{{ $adj->created_at?->format('Y-m-d H:i') }}</td>
                        <td><span class="badge text-bg-{{ $adj->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($adj->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('inventory.adjustments.show', $adj) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No adjustments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($adjustments->hasPages())
        <div class="p-2 border-top">{{ $adjustments->links() }}</div>
    @endif
</div>

@if($createMode ?? false)
<script>
let adjIndex = 1;
function addAdjLine() {
    const container = document.getElementById('adj-lines');
    const firstLine = container.querySelector('.adj-line');
    const clone = firstLine.cloneNode(true);
    clone.querySelectorAll('select, input').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, '[' + adjIndex + ']');
        el.value = '';
    });
    container.appendChild(clone);
    adjIndex++;
}
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-adj-line')) {
        e.target.closest('.adj-line').remove();
    }
});
</script>
@endif
@endsection
