@extends('layouts.institute')

@section('title', 'Medicine Details — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $medicine->display_name }}</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-success" href="{{ route('medical.pharmacy.stock.create', ['medicine_id' => $medicine->id]) }}">
            <i class="bi bi-plus-lg me-1"></i>Add Stock
        </a>
        <a class="btn btn-warning" href="{{ route('medical.pharmacy.medicines.edit', $medicine) }}">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.medicines.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Catalog Info</h6></div>
            <div class="card-body">
                <p><strong>Code:</strong> {{ $medicine->code }}</p>
                <p><strong>Generic:</strong> {{ $medicine->generic_name }}</p>
                <p><strong>Brand:</strong> {{ $medicine->brand_name ?? '—' }}</p>
                <p><strong>Category:</strong> {{ $medicine->category ?? '—' }}</p>
                <p><strong>Form / Strength:</strong> {{ $medicine->dosage_form }}{{ $medicine->strength ? ' '.$medicine->strength : '' }}</p>
                <p><strong>Unit / Pack:</strong> {{ $medicine->unit }} × {{ $medicine->pack_size }}</p>
                <p><strong>Buy / Sell:</strong> ৳{{ number_format($medicine->purchase_price, 2) }} / ৳{{ number_format($medicine->selling_price, 2) }}</p>
                <p class="mb-0"><strong>Reorder Level / Qty:</strong> {{ $medicine->reorder_level }} / {{ $medicine->reorder_quantity }}</p>
                @if(mawa_dgda_enabled())
                <hr>
                <p class="mb-1"><strong>DGDA Code:</strong>
                    @if($medicine->dgda_code)
                        <span class="badge bg-success">DGDA: {{ $medicine->dgda_code }}</span>
                    @else
                        <span class="badge bg-warning text-dark" title="No DGDA code — registry sync pending">DGDA sync pending</span>
                    @endif
                </p>
                @if($medicine->dgda_dar_number)
                    <p class="mb-1"><strong>DAR No:</strong> <code>{{ $medicine->dgda_dar_number }}</code></p>
                @endif
                @if($medicine->dgda_concept_id)
                    <p class="mb-1"><strong>Registry Concept:</strong> <code>{{ $medicine->dgda_concept_id }}</code></p>
                @endif
                @if($medicine->dgda_status)
                    <p class="mb-1"><strong>Sync Status:</strong>
                        <span class="badge bg-{{ $medicine->dgda_status === 'synced' ? 'success' : ($medicine->dgda_status === 'failed' ? 'danger' : 'secondary') }}">{{ ucfirst($medicine->dgda_status) }}</span>
                        @if($medicine->dgda_synced_at)
                            <small class="text-muted">· {{ $medicine->dgda_synced_at->format('d M Y, h:i A') }}</small>
                        @endif
                    </p>
                @endif
                @endif
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Stock Summary</h6></div>
            <div class="card-body text-center">
                <h2>{{ $medicine->available_stock }} <small class="text-muted fs-6">available</small></h2>
                <p class="text-muted">Total across batches: {{ $medicine->total_stock }}</p>
                @if($medicine->available_stock <= $medicine->reorder_level)
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        At or below reorder level ({{ $medicine->reorder_level }}). Suggested order: {{ $medicine->reorder_quantity }} units.
                    </div>
                @endif
            </div>
        </div>

        @if($medicine->side_effects || $medicine->contraindications || $medicine->storage_conditions)
        <div class="card mt-3">
            <div class="card-header"><h6 class="mb-0">Safety Info</h6></div>
            <div class="card-body">
                @if($medicine->side_effects)<p><strong>Side Effects:</strong> {{ $medicine->side_effects }}</p>@endif
                @if($medicine->contraindications)<p><strong>Contraindications:</strong> {{ $medicine->contraindications }}</p>@endif
                @if($medicine->storage_conditions)<p class="mb-0"><strong>Storage:</strong> {{ $medicine->storage_conditions }}</p>@endif
            </div>
        </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="bi bi-clock-history me-2"></i>Change History
        </h6>
    </div>
    <div class="card-body">
        @php
            $auditLogs = \App\Models\Medical\ClinicalAuditLog::where('auditable_type', \App\Models\Medical\Medicine::class)
                ->where('auditable_id', $medicine->id)
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();
        @endphp

        @if($auditLogs->isEmpty())
            <p class="text-muted mb-0">No changes recorded yet.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Who</th>
                            <th>Action</th>
                            <th>Changes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($auditLogs as $log)
                            <tr>
                                <td class="text-nowrap">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                                <td>
                                    {{ $log->actor_name ?? 'System' }}<br>
                                    <small class="text-muted">{{ $log->user_type }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-{{
                                        match($log->action) {
                                            'created' => 'success',
                                            'updated' => 'info',
                                            'archived' => 'warning',
                                            'restored' => 'primary',
                                            default => 'secondary',
                                        }
                                    }}">{{ ucfirst($log->action) }}</span>
                                </td>
                                <td>
                                    @if($log->action === 'updated' && $log->new_values)
                                        @php $changes = is_array($log->new_values) ? $log->new_values : json_decode($log->new_values, true); @endphp
                                        <ul class="list-unstyled mb-0 small">
                                            @foreach($changes as $field => $value)
                                                @continue(in_array($field, ['updated_at', 'created_at', 'deleted_at', 'normalized_name']))
                                                <li>
                                                    <strong>{{ $field }}:</strong>
                                                    {{ is_scalar($value) ? $value : json_encode($value) }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    @elseif($log->action === 'created')
                                        <em class="text-muted small">Medicine created</em>
                                    @else
                                        <em class="text-muted small">{{ $log->reason ?? '—' }}</em>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-upc-scan me-2"></i>Barcode</h5>
    </div>
    <div class="card-body text-center">
        @if($medicine->code)
            <svg id="barcode-svg" class="mb-3"></svg>
            <div class="d-flex justify-content-center gap-2 flex-wrap">
                <button type="button" class="btn btn-sm btn-primary"
                        onclick="printSingleBarcode()">
                    <i class="bi bi-printer me-1"></i> Print Barcode
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="downloadBarcode('barcode-svg', '{{ $medicine->code }}')">
                    <i class="bi bi-download me-1"></i> Download PNG
                </button>
            </div>
        @else
            <p class="text-muted mb-0">No code assigned yet.</p>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($medicine->code)
        generateBarcode('barcode-svg', '{{ $medicine->code }}');
    @endif
});

function generateBarcode(elementId, code) {
    if (!window.JsBarcode || !code) return;
    try {
        JsBarcode('#' + elementId, code, {
            format: 'CODE128',
            width: 2.5,
            height: 90,
            displayValue: true,
            fontSize: 16,
            font: 'monospace',
            margin: 12,
        });
    } catch (e) {
        console.error('Barcode error:', e);
    }
}

function printSingleBarcode() {
    const svg = document.getElementById('barcode-svg');
    if (!svg) return;

    const name = {!! json_encode($medicine->brand_name) !!};
    const strength = {!! json_encode($medicine->strength ?? '') !!};
    const form = {!! json_encode($medicine->dosage_form ?? '') !!};

    const w = window.open('', '_blank', 'width=420,height=320');
    w.document.write(`
        <html><head><title>Barcode — ${name}</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 20px; margin: 0; }
            .label { border: 1px dashed #ccc; padding: 15px; max-width: 320px; margin: 0 auto; }
            .name { font-size: 15px; font-weight: bold; margin-bottom: 4px; }
            .sub { font-size: 12px; color: #666; margin-bottom: 8px; }
            svg { max-width: 100%; height: auto; }
            @media print { .label { border: none; } }
        </style></head><body>
        <div class="label">
            <div class="name">${name}</div>
            <div class="sub">${strength} ${form}</div>
            ${svg.outerHTML}
        </div>
        <script>window.onload = function() { window.print(); setTimeout(() => window.close(), 500); };<\/script>
        </body></html>
    `);
    w.document.close();
}

function downloadBarcode(svgId, code) {
    const svg = document.getElementById(svgId);
    if (!svg) return;

    const svgData = new XMLSerializer().serializeToString(svg);
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();

    const svgBlob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' });
    const url = URL.createObjectURL(svgBlob);

    img.onload = function() {
        canvas.width = img.width * 2;
        canvas.height = img.height * 2;
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);

        const a = document.createElement('a');
        a.download = 'barcode-' + code + '.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
    };
    img.src = url;
}
</script>
@endpush

<div class="card mt-3">
    <div class="card-header"><h6 class="mb-0">Batches ({{ $medicine->stocks->count() }})</h6></div>
    <div class="card-body">
        @if($medicine->stocks->count() > 0)
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead><tr><th>Batch</th><th>Expiry</th><th>Qty</th><th>Sell Price</th><th></th></tr></thead>
                    <tbody>
                        @foreach($medicine->stocks as $batch)
                        <tr class="{{ $batch->is_expired ? 'table-danger' : '' }}">
                            <td>{{ $batch->batch_number }}</td>
                            <td>
                                <x-tdate :value="$batch->expiry_date" fallback="d M Y" />
                                @if($batch->is_expired)
                                    <span class="badge bg-danger">Expired</span>
                                @elseif($batch->days_to_expiry <= 30)
                                    <span class="badge bg-warning text-dark">{{ $batch->days_to_expiry }}d left</span>
                                @endif
                            </td>
                            <td>{{ $batch->current_quantity }}</td>
                            <td>৳{{ number_format($batch->selling_price, 2) }}</td>
                            <td class="text-end">
                                <a href="{{ route('medical.pharmacy.stock.show', $batch) }}" class="btn btn-sm btn-info">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-muted mb-0">No batches recorded.</p>
        @endif
    </div>
</div>
@endsection
