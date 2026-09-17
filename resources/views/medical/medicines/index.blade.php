@extends('layouts.institute')

@section('title', 'Medicines — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Medicine Catalog</h4>
    </div>
      <div class="page-header-actions">
          @dgdaEnabled
          <a class="btn btn-outline-info" href="{{ route('medical.pharmacy.medicines.migrate') }}">
               <i class="bi bi-arrow-left-right me-1"></i>Migrate to DGDA
          </a>
          @enddgdaEnabled
          <a class="btn btn-outline-primary" href="{{ route('medical.pharmacy.medicines.import.form') }}">
               <i class="bi bi-upload me-1"></i>Bulk Import
          </a>
          <a class="btn btn-primary" href="{{ route('medical.pharmacy.medicines.create') }}">
               <i class="bi bi-plus-lg me-1"></i>Add Medicine
          </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        @php
            $codeService = app(\App\Services\Medical\MedicineCodeService::class);
            $slabInfo = $codeService->slabInfo(\App\Support\MedicalScope::instituteId());
        @endphp

        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <strong class="text-muted"><i class="bi bi-upc me-1"></i>Code Capacity:</strong>
                    @foreach($slabInfo as $slab)
                        @php
                            $color = $slab['percent_used'] >= 90 ? 'danger'
                                   : ($slab['percent_used'] >= 70 ? 'warning' : 'success');
                        @endphp
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-{{ $color }}">{{ $slab['digits'] }}-digit</span>
                            <small class="text-muted">
                                {{ number_format($slab['used']) }}/{{ number_format($slab['capacity']) }}
                                ({{ $slab['percent_used'] }}%)
                            </small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <button type="button" class="btn btn-outline-primary" onclick="printSelectedBarcodes()">
                    <i class="bi bi-printer me-1"></i> Print Selected Barcodes
                </button>
                <span id="selected-count" class="text-muted ms-2"></span>
            </div>
        </div>

        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                               placeholder="{{ mawa_dgda_enabled() ? 'Search generic, brand, code or DGDA...' : 'Search generic, brand or code...' }}"
                               value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="dosage_form" class="form-select" onchange="this.form.submit()" title="Filter by dosage form">
                        <option value="">All Forms</option>
                        @foreach(config('medicine.dosage_forms') as $key => $label)
                            <option value="{{ $key }}" @selected(request('dosage_form') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if(mawa_dgda_enabled())
                <div class="col-md-2">
                    <select name="dgda" class="form-select" onchange="this.form.submit()" title="Filter by DGDA registry code">
                        <option value="">All DGDA</option>
                        <option value="coded" @selected(request('dgda') === 'coded')>With DGDA code</option>
                        <option value="pending" @selected(request('dgda') === 'pending')>DGDA sync pending</option>
                    </select>
                </div>
                @endif
                <div class="col-md-2 text-end">
                    <a href="{{ route('medical.pharmacy.medicines.index') }}" class="btn btn-secondary">Reset</a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 40px;">
                            <input type="checkbox" id="select-all-barcodes" onchange="toggleAllBarcodes(this)">
                        </th>
                        <th>Code</th>
                        <th>Medicine</th>
                        @if(mawa_dgda_enabled())<th>DGDA Code</th>@endif
                        <th>Form / Strength</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($medicines as $medicine)
                    <tr>
                        <td>
                            @if($medicine->code)
                                <input type="checkbox"
                                       class="barcode-checkbox"
                                       data-code="{{ $medicine->code }}"
                                       data-name="{{ $medicine->brand_name }}"
                                       data-strength="{{ $medicine->strength ?? '' }}"
                                       data-form="{{ $medicine->dosage_form ?? '' }}">
                            @endif
                        </td>
                        <td><strong>{{ $medicine->code }}</strong></td>
                        <td>
                            {{ $medicine->display_name }}
                            @if($medicine->is_controlled)
                                <span class="badge bg-danger ms-1">Controlled</span>
                            @endif
                            @if(!$medicine->requires_prescription)
                                <span class="badge bg-info text-dark ms-1">OTC</span>
                            @endif
                        </td>
                        @if(mawa_dgda_enabled())
                        <td>
                            @if($medicine->dgda_code)
                                <code>{{ $medicine->dgda_code }}</code>
                                @if($medicine->dgda_status && $medicine->dgda_status !== 'synced')
                                    <span class="badge bg-warning text-dark ms-1">{{ ucfirst($medicine->dgda_status) }}</span>
                                @endif
                            @else
                                <span class="badge bg-warning text-dark" title="No DGDA code — registry sync pending">DGDA sync pending</span>
                            @endif
                        </td>
                        @endif
                        <td>{{ $medicine->dosage_form }}{{ $medicine->strength ? ' '.$medicine->strength : '' }}</td>
                        <td>৳{{ number_format($medicine->selling_price, 2) }}</td>
                        <td>
                            @if($medicine->available_stock <= 0)
                                <span class="badge bg-danger">Out of stock</span>
                            @elseif($medicine->available_stock <= $medicine->reorder_level)
                                <span class="badge bg-warning text-dark">Low ({{ $medicine->available_stock }})</span>
                            @else
                                <span class="badge bg-success">{{ $medicine->available_stock }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('medical.pharmacy.medicines.show', $medicine) }}" class="btn btn-info" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="{{ route('medical.pharmacy.medicines.edit', $medicine) }}" class="btn btn-warning" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                @if(mawa_dgda_enabled())
                                <button type="button" class="btn btn-success" title="Validate against DGDA registry"
                                        onclick="document.getElementById('medicine-sync-{{ $medicine->id }}').submit();">
                                    <i class="bi bi-arrow-repeat"></i>
                                </button>
                                @endif
                                <button type="button" class="btn btn-danger" title="Delete"
                                        onclick="if(confirm('Delete this medicine? Medicines with stock cannot be deleted.')){document.getElementById('medicine-delete-{{ $medicine->id }}').submit();}">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            <form id="medicine-delete-{{ $medicine->id }}"
                                  action="{{ route('medical.pharmacy.medicines.destroy', $medicine) }}"
                                  method="POST" style="display:none;">
                                @csrf
                                @method('DELETE')
                            </form>
                            @if(mawa_dgda_enabled())
                            <form id="medicine-sync-{{ $medicine->id }}"
                                  action="{{ route('medical.pharmacy.medicines.sync-dgda', $medicine) }}"
                                  method="POST" style="display:none;">
                                @csrf
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ mawa_dgda_enabled() ? 8 : 7 }}" class="text-center text-muted py-4">
                            <i class="bi bi-capsule fs-2 d-block mb-2"></i>
                            No medicines found.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $medicines->links('pagination::bootstrap-5') }}
    </div>
</div>

@push('scripts')
<script>
function toggleAllBarcodes(source) {
    document.querySelectorAll('.barcode-checkbox').forEach(cb => cb.checked = source.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const count = document.querySelectorAll('.barcode-checkbox:checked').length;
    const el = document.getElementById('selected-count');
    if (el) el.textContent = count > 0 ? '(' + count + ' selected)' : '';
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.barcode-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
});

function printSelectedBarcodes() {
    const selected = [];
    document.querySelectorAll('.barcode-checkbox:checked').forEach(cb => {
        selected.push({
            code: cb.dataset.code,
            name: cb.dataset.name,
            strength: cb.dataset.strength,
            form: cb.dataset.form,
        });
    });

    if (selected.length === 0) {
        alert('Select at least one medicine');
        return;
    }

    if (!window.JsBarcode) {
        alert('Barcode library not loaded. Check your connection and retry.');
        return;
    }

    // Generate SVG for each, then open print window
    const tempDiv = document.createElement('div');
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px';
    document.body.appendChild(tempDiv);

    selected.forEach((item, idx) => {
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.id = 'temp-barcode-' + idx;
        tempDiv.appendChild(svg);
        try {
            JsBarcode('#temp-barcode-' + idx, item.code, {
                format: 'CODE128', width: 2, height: 70, fontSize: 14, margin: 8
            });
        } catch (e) { console.error(e); }
    });

    let html = '<html><head><title>Print Barcodes</title><style>';
    html += 'body { font-family: Arial, sans-serif; padding: 8px; margin: 0; }';
    html += '.label { display: inline-block; text-align: center; border: 1px dashed #ccc; padding: 8px; margin: 4px; width: 220px; vertical-align: top; page-break-inside: avoid; }';
    html += '.name { font-size: 12px; font-weight: bold; line-height: 1.2; }';
    html += '.sub { font-size: 10px; color: #666; margin-bottom: 4px; }';
    html += 'svg { max-width: 100%; height: auto; }';
    html += '@media print { .label { border: none; } }';
    html += '</style></head><body>';

    selected.forEach((item, idx) => {
        const svgEl = document.getElementById('temp-barcode-' + idx);
        html += '<div class="label">';
        html += '<div class="name">' + escapeHtml(item.name) + '</div>';
        html += '<div class="sub">' + escapeHtml(item.strength) + ' ' + escapeHtml(item.form) + '</div>';
        html += svgEl ? svgEl.outerHTML : '';
        html += '</div>';
    });

    html += '</body></html>';

    const w = window.open('', '_blank');
    w.document.write(html);
    w.document.close();

    setTimeout(() => {
        w.print();
        document.body.removeChild(tempDiv);
        setTimeout(() => w.close(), 500);
    }, 500);
}

function escapeHtml(s) {
    return String(s || '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}
</script>
@endpush
@endsection
