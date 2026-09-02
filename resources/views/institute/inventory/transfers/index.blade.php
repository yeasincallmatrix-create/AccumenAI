@extends('layouts.standalone')
@section('title', 'Stock Transfers — AccumenAI')
@section('page_title', 'Inventory')
@section('content')
<div class="standalone-heading">
    <h4>Stock Transfers</h4>
    <p>Transfer stock between warehouses of the same tenant. Transfers are non-financial (no journal entry).</p>
    <a href="{{ route('inventory.transfers.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Transfer</a>
</div>

@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="filter-card mb-3">
    <form class="filter-layout" method="GET" action="{{ route('inventory.transfers.index') }}">
        <div class="filter-search-row align-items-end flex-wrap">
            <div class="filter-span">
                <label class="form-label mb-1">Search</label>
                <input type="search" class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Transfer # or warehouse name">
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
                <a class="btn btn-outline-secondary btn-sm mt-1" href="{{ route('inventory.transfers.index') }}">Reset</a>
            </div>
        </div>
    </form>
</div>

@if(($createMode ?? false))
<div class="card mb-3">
    <div class="card-body">
        <h6>New Stock Transfer</h6>
        <form method="POST" action="{{ route('inventory.transfers.store') }}">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-md-5">
                    <label class="form-label">Source Warehouse <span class="text-danger">*</span></label>
                    <select class="form-select" name="source_warehouse_id" required>
                        <option value="">Select source...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Destination Warehouse <span class="text-danger">*</span></label>
                    <select class="form-select" name="destination_warehouse_id" required>
                        <option value="">Select destination...</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Notes</label>
                    <input type="text" class="form-control" name="notes" value="{{ old('notes') }}">
                </div>
            </div>
            <h6>Transfer Lines</h6>
            <div id="transfer-lines">
                <div class="row g-2 mb-2 transfer-line">
                    <div class="col-md-6">
                        <select class="form-select form-select-sm" name="lines[0][item_id]" required>
                            <option value="">Select item...</option>
                            @foreach($items as $item)
                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku ?? 'N/A' }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="number" step="0.0001" class="form-control form-control-sm" name="lines[0][quantity]" placeholder="Quantity" required>
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-line"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" onclick="addLine()"><i class="bi bi-plus"></i> Add Line</button>
            <div><button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check-circle me-1"></i>Post Transfer</button></div>
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
                    <th>Transfer #</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $t)
                    <tr>
                        <td class="text-muted">{{ $transfers->firstItem() + $loop->index }}</td>
                        <td><a href="{{ route('inventory.transfers.show', $t) }}" class="fw-semibold">{{ $t->transfer_no }}</a></td>
                        <td>{{ $t->sourceWarehouse?->name ?? '—' }}</td>
                        <td>{{ $t->destinationWarehouse?->name ?? '—' }}</td>
                        <td>{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                        <td><span class="badge text-bg-{{ $t->status === 'posted' ? 'success' : 'secondary' }}">{{ ucfirst($t->status) }}</span></td>
                        <td class="text-end">
                            <a href="{{ route('inventory.transfers.show', $t) }}" class="btn btn-sm btn-outline-primary rounded-pill">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No transfers found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($transfers->hasPages())
        <div class="p-2 border-top">{{ $transfers->links() }}</div>
    @endif
</div>

@if($createMode ?? false)
<script>
let lineIndex = 1;
function addLine() {
    const container = document.getElementById('transfer-lines');
    const firstLine = container.querySelector('.transfer-line');
    const clone = firstLine.cloneNode(true);
    clone.querySelectorAll('select, input').forEach(el => {
        el.name = el.name.replace(/\[\d+\]/, '[' + lineIndex + ']');
        el.value = '';
    });
    container.appendChild(clone);
    lineIndex++;
}
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-line')) {
        e.target.closest('.transfer-line').remove();
    }
});
</script>
@endif
@endsection
