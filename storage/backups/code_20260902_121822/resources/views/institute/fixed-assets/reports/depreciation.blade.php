@extends('layouts.standalone')

@section('title', 'Depreciation Schedule — AccumenAI')
@section('page_title', 'Fixed Asset Reports')

@section('content')

<div class="standalone-heading">
    <h4>Depreciation Schedule</h4>
    <p>Full depreciation schedule for each depreciable asset.</p>
    <button class="btn btn-outline-secondary btn-sm" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

@foreach ($schedules as $assetId => $data)
    @php $asset = $data['asset']; $schedule = $data['schedule']; @endphp
    <div class="admin-card mb-3">
        <h6 class="card-title">{{ $asset->asset_code }} — {{ $asset->name }}</h6>
        <p class="text-muted small mb-2">Method: {{ ucfirst(str_replace('_', ' ', $asset->depreciation_method)) }} · Life: {{ $asset->useful_life_months }} months · Cost: {{ number_format($asset->cost(), 2) }} · Residual: {{ number_format((float) $asset->residual_value, 2) }}</p>
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
                    @foreach ($schedule as $row)
                        <tr>
                            <td>{{ $row['period'] }}</td>
                            <td class="text-end">{{ number_format($row['opening_nbv'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['depreciation'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['accumulated_depreciation'], 2) }}</td>
                            <td class="text-end">{{ number_format($row['closing_nbv'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach

@if (empty($schedules))
    <div class="admin-card">
        <p class="text-center text-muted py-4 mb-0">No depreciable assets found.</p>
    </div>
@endif

@endsection
