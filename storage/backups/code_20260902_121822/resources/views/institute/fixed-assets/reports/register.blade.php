@extends('layouts.standalone')

@section('title', 'Asset Register — AccumenAI')
@section('page_title', 'Fixed Asset Reports')

@section('content')

<div class="standalone-heading">
    <h4>Asset Register</h4>
    <p>Complete register of fixed assets with cost, depreciation and net book value.</p>
    <div class="d-flex gap-2 flex-wrap">
        <form class="filter-layout d-flex align-items-end gap-2 flex-wrap" method="GET" action="{{ route('fixed_assets.reports.register') }}">
            <div>
                <label class="form-label mb-1">Status</label>
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\FixedAsset::STATUSES as $s)
                        <option value="{{ $s }}" @selected(($status ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btn btn-outline-primary btn-sm" type="submit"><i class="bi bi-funnel"></i> Apply</button>
            <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        </form>
    </div>
</div>

@if (isset($disposals))
    <div class="admin-card mb-3">
        <h6 class="card-title">Disposal History</h6>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th class="text-end">Sale Proceeds</th>
                        <th class="text-end">Gain/Loss</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($disposals as $disposal)
                        <tr>
                            <td>
                                @if ($disposal->asset)
                                    <a href="{{ route('fixed_assets.assets.show', $disposal->asset) }}" class="text-decoration-none">{{ $disposal->asset->name }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td><span class="badge text-bg-light border">{{ ucfirst($disposal->disposal_type) }}</span></td>
                            <td>{{ $disposal->disposal_date }}</td>
                            <td class="text-end">{{ number_format((float) $disposal->sale_proceeds, 2) }}</td>
                            <td class="text-end">
                                @php $gl = (float) $disposal->gain_loss; @endphp
                                <span class="{{ $gl >= 0 ? 'text-success' : 'text-danger' }}">{{ $gl >= 0 ? '+' : '' }}{{ number_format($gl, 2) }}</span>
                            </td>
                            <td>{{ $disposal->reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No disposals recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($disposals instanceof \Illuminate\Pagination\LengthAwarePaginator && $disposals->hasPages())
            <div class="p-2 border-top">{{ $disposals->links() }}</div>
        @endif
    </div>
@else
    @if ($byCategory)
        <div class="admin-card mb-3">
            <h6 class="card-title">Summary by Category</h6>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th class="text-end">Count</th>
                            <th class="text-end">Total Cost</th>
                            <th class="text-end">Accum. Depreciation</th>
                            <th class="text-end">Net Book Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($byCategory as $name => $row)
                            <tr>
                                <td>{{ $name }}</td>
                                <td class="text-end">{{ $row['count'] }}</td>
                                <td class="text-end">{{ number_format($row['cost'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['accumulated_depreciation'], 2) }}</td>
                                <td class="text-end">{{ number_format($row['nbv'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="admin-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th class="text-end">Cost</th>
                        <th class="text-end">Accum. Dep.</th>
                        <th class="text-end">NBV</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($register as $asset)
                        <tr>
                            <td class="text-muted">{{ $asset->asset_code }}</td>
                            <td>
                                <a href="{{ route('fixed_assets.assets.show', $asset) }}" class="text-decoration-none">{{ $asset->name }}</a>
                            </td>
                            <td>{{ $asset->category?->name ?? '—' }}</td>
                            <td>
                                <span class="badge text-bg-{{ in_array($asset->status, ['active']) ? 'success' : 'secondary' }}">{{ ucfirst(str_replace('_', ' ', $asset->status)) }}</span>
                            </td>
                            <td class="text-end">{{ number_format($asset->cost(), 2) }}</td>
                            <td class="text-end">{{ number_format((float) $asset->accumulated_depreciation, 2) }}</td>
                            <td class="text-end">{{ number_format($asset->netBookValue(), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No assets found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($register->hasPages())
            <div class="p-2 border-top">{{ $register->links() }}</div>
        @endif
    </div>
@endif

@endsection
