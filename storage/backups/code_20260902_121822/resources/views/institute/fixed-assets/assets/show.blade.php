@extends('layouts.standalone')

@section('title', $asset->name.' — AccumenAI')
@section('page_title', 'Finance')

@section('content')

<div class="standalone-heading">
    <h4>{{ $asset->name }}</h4>
    <p>{{ $asset->asset_code }} · {{ ucfirst(str_replace('_', ' ', $asset->status)) }}</p>
    <div class="d-flex gap-2 flex-wrap align-items-center">
        @if (in_array($asset->status, ['draft', 'acquired']))
            <form method="POST" action="{{ route('fixed_assets.assets.capitalize', $asset) }}" class="d-inline" data-ajax-submit="1" data-confirm="Capitalize this asset? This will post the capitalization journal and set the status to active.">
                @csrf
                <button class="btn btn-success btn-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Capitalize</button>
            </form>
        @endif
        <span class="badge text-bg-{{ in_array($asset->status, ['active']) ? 'success' : (in_array($asset->status, ['draft', 'acquired']) ? 'warning' : 'secondary') }}">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="admin-card mb-3">
            <h6 class="card-title">Asset Details</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="small text-muted">Name</div>
                    <div>{{ $asset->name }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Asset Code</div>
                    <div>{{ $asset->asset_code }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Serial Number</div>
                    <div>{{ $asset->serial_number ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Manufacturer</div>
                    <div>{{ $asset->manufacturer ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Model</div>
                    <div>{{ $asset->model ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Category</div>
                    <div>{{ $asset->category?->name ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Location</div>
                    <div>{{ $asset->location?->name ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Department</div>
                    <div>{{ $asset->department ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Responsible Person</div>
                    <div>{{ $asset->responsible_person ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="small text-muted">Description</div>
                    <div>{{ $asset->description ?? '—' }}</div>
                </div>
            </div>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Financial Summary</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="small text-muted">Acquisition Cost</div>
                    <div class="fw-semibold">{{ number_format((float) $asset->acquisition_cost, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Additional Cost</div>
                    <div class="fw-semibold">{{ number_format((float) $asset->additional_capitalized_cost, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Total Cost</div>
                    <div class="fw-semibold">{{ number_format($asset->cost(), 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Residual Value</div>
                    <div class="fw-semibold">{{ number_format((float) $asset->residual_value, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Accumulated Depreciation</div>
                    <div class="fw-semibold">{{ number_format((float) $asset->accumulated_depreciation, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Impairment</div>
                    <div class="fw-semibold">{{ number_format((float) $asset->impairment_amount, 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Net Book Value</div>
                    <div class="fw-semibold">{{ number_format($asset->netBookValue(), 2) }}</div>
                </div>
                <div class="col-md-3">
                    <div class="small text-muted">Depreciable Base</div>
                    <div class="fw-semibold">{{ number_format($asset->depreciableBase(), 2) }}</div>
                </div>
            </div>
        </div>

        @if ($asset->depreciationEntries->count())
            <div class="admin-card mb-3">
                <h6 class="card-title">Depreciation History</h6>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th class="text-end">Opening NBV</th>
                                <th class="text-end">Depreciation</th>
                                <th class="text-end">Accumulated</th>
                                <th class="text-end">Closing NBV</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($asset->depreciationEntries->sortByDesc('period_start') as $entry)
                                <tr>
                                    <td>{{ $entry->period_start }} to {{ $entry->period_end }}</td>
                                    <td class="text-end">{{ number_format((float) $entry->opening_nbv, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $entry->depreciation_amount, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $entry->accumulated_depreciation, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $entry->closing_nbv, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="admin-card mb-3">
            <h6 class="card-title">Depreciation Settings</h6>
            <div class="mb-2">
                <div class="small text-muted">Method</div>
                <div>{{ $asset->depreciation_method ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Useful Life (months)</div>
                <div>{{ $asset->useful_life_months ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Rate</div>
                <div>{{ $asset->depreciation_rate ? number_format((float) $asset->depreciation_rate, 4).'%' : '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Frequency</div>
                <div>{{ ucfirst($asset->depreciation_frequency ?? 'monthly') }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Depreciation Start</div>
                <div>{{ $asset->depreciation_start_date?->format('Y-m-d') ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Capitalization Date</div>
                <div>{{ $asset->capitalization_date?->format('Y-m-d') ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Purchase Date</div>
                <div>{{ $asset->purchase_date?->format('Y-m-d') ?? '—' }}</div>
            </div>
        </div>

        <div class="admin-card mb-3">
            <h6 class="card-title">Warranty</h6>
            <div class="mb-2">
                <div class="small text-muted">Provider</div>
                <div>{{ $asset->warranty_provider ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Period</div>
                <div>{{ $asset->warranty_start?->format('Y-m-d') ?? '—' }} to {{ $asset->warranty_end?->format('Y-m-d') ?? '—' }}</div>
            </div>
            <div class="mb-2">
                <div class="small text-muted">Reference</div>
                <div>{{ $asset->warranty_reference ?? '—' }}</div>
            </div>
        </div>

        @if ($asset->notes)
            <div class="admin-card mb-3">
                <h6 class="card-title">Notes</h6>
                <div>{{ $asset->notes }}</div>
            </div>
        @endif

        <a href="{{ route('fixed_assets.assets.edit', $asset) }}" class="btn btn-outline-primary btn-sm w-100"><i class="bi bi-pencil-square me-1"></i>Edit Asset</a>
    </div>
</div>

@endsection
