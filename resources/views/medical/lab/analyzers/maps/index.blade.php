@extends('layouts.institute')

@section('title', 'Parameter Maps — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Parameter Maps <small class="text-muted">{{ $analyzer->code }}</small></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.show', $analyzer) }}">Back</a>
        @if($analyzer->adapter_key === 'sysmex_xn')
            <form method="POST" action="{{ route('medical.laboratory.analyzers.maps.seed-sysmex', $analyzer) }}" class="d-inline" onsubmit="return confirm('Seed 36 Sysmex XN-550 maps? Existing codes are kept.');">
                @csrf
                <button type="submit" class="btn btn-info">Seed Sysmex XN-550 Maps</button>
            </form>
        @endif
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Vendor Code</th><th>Universal</th><th>Key</th><th>Lab Test</th><th>Unit From → To</th><th>Factor</th><th>Ref Range</th><th>Active</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($maps as $map)
                    <tr>
                        <form method="POST" action="{{ route('medical.laboratory.analyzers.maps.update', [$analyzer, $map]) }}">
                            @csrf
                            @method('PUT')
                            <td><strong>{{ $map->vendor_code }}</strong></td>
                            <td><input type="text" name="universal_code" value="{{ $map->universal_code }}" class="form-control form-control-sm" required></td>
                            <td><input type="text" name="parameter_key" value="{{ $map->parameter_key }}" class="form-control form-control-sm"></td>
                            <td>
                                <select name="lab_test_id" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach($labTests as $test)
                                        <option value="{{ $test->id }}" @selected((int) $map->lab_test_id === (int) $test->id)>{{ $test->code }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="text-nowrap">
                                <input type="text" name="unit_from" value="{{ $map->unit_from }}" class="form-control form-control-sm d-inline-block" style="width:80px">
                                →
                                <input type="text" name="unit_to" value="{{ $map->unit_to }}" class="form-control form-control-sm d-inline-block" style="width:80px">
                            </td>
                            <td><input type="number" step="any" name="conversion_factor" value="{{ $map->conversion_factor }}" class="form-control form-control-sm" style="width:80px"></td>
                            <td class="text-nowrap">
                                <input type="text" name="ref_range_text" value="{{ $map->ref_range_text }}" class="form-control form-control-sm d-inline-block" style="width:90px">
                            </td>
                            <td class="text-center"><input type="checkbox" name="is_active" value="1" @checked($map->is_active) class="form-check-input"></td>
                            <td class="text-end">
                                <input type="hidden" name="vendor_code" value="{{ $map->vendor_code }}">
                                <div class="btn-group btn-group-sm">
                                    <button type="submit" class="btn btn-primary" title="Save"><i class="bi bi-check-lg"></i></button>
                        </form>
                                <form method="POST" action="{{ route('medical.laboratory.analyzers.maps.destroy', [$analyzer, $map]) }}" onsubmit="return confirm('Delete map {{ $map->vendor_code }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                                </div>
                            </td>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No parameter maps yet. Add one below or seed Sysmex defaults.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $maps->links('pagination::bootstrap-5') }}
    </div>
</div>

<div class="card">
    <div class="card-header"><h6 class="mb-0">Add Map</h6></div>
    <div class="card-body">
        <form method="POST" action="{{ route('medical.laboratory.analyzers.maps.store', $analyzer) }}">
            @csrf
            <div class="row g-2">
                <div class="col-md-2"><input type="text" name="vendor_code" class="form-control" placeholder="Vendor code *" required></div>
                <div class="col-md-2"><input type="text" name="universal_code" class="form-control" placeholder="Universal *" required></div>
                <div class="col-md-2"><input type="text" name="unit_from" class="form-control" placeholder="Unit from"></div>
                <div class="col-md-2"><input type="text" name="unit_to" class="form-control" placeholder="Unit to"></div>
                <div class="col-md-2"><input type="text" name="ref_range_text" class="form-control" placeholder="Ref range"></div>
                <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Add</button></div>
            </div>
        </form>
    </div>
</div>
@endsection
