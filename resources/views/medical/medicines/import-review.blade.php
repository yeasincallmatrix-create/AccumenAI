@extends('layouts.institute')
@section('page-title', 'Review Import')

@section('content')
<div class="container">
    <h3><i class="bi bi-clipboard-check me-2"></i>Review Import: {{ $batch->original_filename }}</h3>

    @php
        $analysis = $batch->parsed_data;
        $clean = $analysis['clean'] ?? [];
        $conflicts = $analysis['conflicts'] ?? [];
        $rowErrors = $analysis['errors'] ?? [];
    @endphp

    {{-- Summary Cards --}}
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ count($clean) }}</h3>
                    <small>Clean rows</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h3 class="mb-0">{{ count($conflicts) }}</h3>
                    <small>Conflicts (need resolution)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ count($rowErrors) }}</h3>
                    <small>Errors (will be skipped)</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-secondary text-white">
                <div class="card-body">
                    <h3 class="mb-0">{{ $batch->total_rows }}</h3>
                    <small>Total rows</small>
                </div>
            </div>
        </div>
    </div>

    {{-- Errors (read-only) --}}
    @if(count($rowErrors) > 0)
        <div class="card mb-4 border-danger">
            <div class="card-header bg-danger-subtle">
                <h5 class="mb-0 text-danger">
                    <i class="bi bi-exclamation-octagon me-2"></i>
                    Errors ({{ count($rowErrors) }} rows will be skipped)
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Row</th><th>Brand Name</th><th>Reason</th></tr>
                    </thead>
                    <tbody>
                        @foreach($rowErrors as $rowError)
                            <tr>
                                <td>#{{ $rowError['row'] }}</td>
                                <td>{{ $rowError['data']['brand_name'] ?? '—' }}</td>
                                <td class="text-danger">
                                    @if(isset($rowError['issues']))
                                        {{ collect($rowError['issues'])->pluck('message')->implode(', ') }}
                                    @else
                                        {{ $rowError['reason'] ?? 'Unknown error' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Conflicts (needs user decision) --}}
    @if(count($conflicts) > 0)
        <div class="card mb-4 border-warning">
            <div class="card-header bg-warning-subtle">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Conflicts ({{ count($conflicts) }} rows need your decision)
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('medical.pharmacy.medicines.import.confirm', $batch->batch_id) }}" method="POST" id="import-form">
                    @csrf

                    <div class="alert alert-info small">
                        <strong>How to resolve:</strong>
                        <ul class="mb-0">
                            <li><strong>Skip</strong> — ignore this row</li>
                            <li><strong>Update Existing</strong> — overwrite the matching medicine</li>
                            <li><strong>Create New</strong> — create as new (code will be auto-generated if conflict)</li>
                        </ul>
                    </div>

                    @foreach($conflicts as $conflict)
                        <div class="card mb-3 border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <strong>Row #{{ $conflict['row'] }}:</strong>
                                        {{ $conflict['data']['brand_name'] }}
                                        {{ $conflict['data']['strength'] }}
                                        ({{ $conflict['data']['dosage_form'] }})
                                    </div>
                                    <span class="badge bg-warning text-dark">Conflict</span>
                                </div>

                                <ul class="small text-muted mb-3">
                                    @foreach($conflict['issues'] as $issue)
                                        <li>
                                            <span class="badge bg-{{ $issue['severity'] === 'error' ? 'danger' : 'warning text-dark' }} me-1">
                                                {{ $issue['severity'] }}
                                            </span>
                                            {{ $issue['message'] }}
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="btn-group" role="group">
                                    <input type="radio"
                                           name="resolutions[{{ $conflict['row'] }}]"
                                           id="skip_{{ $conflict['row'] }}"
                                           value="skip"
                                           class="btn-check"
                                           checked>
                                    <label class="btn btn-sm btn-outline-secondary" for="skip_{{ $conflict['row'] }}">
                                        <i class="bi bi-x-circle"></i> Skip
                                    </label>

                                    @php
                                        $hasExisting = collect($conflict['issues'])->contains(fn($i) => isset($i['existing_id']));
                                        // Create-new is only safe for pure code conflicts: an
                                        // identical name would violate the per-institute
                                        // normalized-name unique index on save.
                                        $hasNameConflict = collect($conflict['issues'])->contains(fn($i) => in_array($i['type'] ?? '', ['medicine_exists', 'duplicate_in_batch'], true));
                                    @endphp

                                    @if($hasExisting)
                                        <input type="radio"
                                               name="resolutions[{{ $conflict['row'] }}]"
                                               id="update_{{ $conflict['row'] }}"
                                               value="update"
                                               class="btn-check">
                                        <label class="btn btn-sm btn-outline-primary" for="update_{{ $conflict['row'] }}">
                                            <i class="bi bi-pencil-square"></i> Update Existing
                                        </label>
                                    @endif

                                    @if(! $hasNameConflict)
                                        <input type="radio"
                                               name="resolutions[{{ $conflict['row'] }}]"
                                               id="create_{{ $conflict['row'] }}"
                                               value="create_new"
                                               class="btn-check">
                                        <label class="btn btn-sm btn-outline-success" for="create_{{ $conflict['row'] }}">
                                            <i class="bi bi-plus-circle"></i> Create New
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i>
                            Apply Import ({{ count($clean) }} clean + selected conflicts)
                        </button>
                        <a href="{{ route('medical.pharmacy.medicines.import.form') }}" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    @else
        {{-- No conflicts — direct import --}}
        <div class="card mb-4 border-success">
            <div class="card-header bg-success-subtle">
                <h5 class="mb-0 text-success">
                    <i class="bi bi-check-circle me-2"></i>No conflicts detected
                </h5>
            </div>
            <div class="card-body">
                <p>{{ count($clean) }} rows are ready to import.</p>
                <form action="{{ route('medical.pharmacy.medicines.import.confirm', $batch->batch_id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-upload me-1"></i> Import {{ count($clean) }} Medicines
                    </button>
                    <a href="{{ route('medical.pharmacy.medicines.import.form') }}" class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    @endif

    {{-- Clean preview --}}
    @if(count($clean) > 0)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-check-circle text-success me-2"></i>
                    Clean Rows ({{ count($clean) }} will be imported)
                </h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <thead>
                        <tr><th>Row</th><th>Brand</th><th>Strength</th><th>Form</th><th>Code</th></tr>
                    </thead>
                    <tbody>
                        @foreach(array_slice($clean, 0, 20) as $row)
                            <tr>
                                <td>#{{ $row['_row_num'] }}</td>
                                <td>{{ $row['brand_name'] }}</td>
                                <td>{{ $row['strength'] }}</td>
                                <td>{{ $row['dosage_form'] }}</td>
                                <td>
                                    @if(!empty($row['code']))
                                        <code>{{ $row['code'] }}</code>
                                    @else
                                        <span class="text-muted">auto</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if(count($clean) > 20)
                    <small class="text-muted">... and {{ count($clean) - 20 }} more rows</small>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
