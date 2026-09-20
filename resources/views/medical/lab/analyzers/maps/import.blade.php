@extends('layouts.institute')

@section('title', 'Import Parameter Maps — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Import Maps <small class="text-muted">{{ $analyzer->code }}</small></h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.laboratory.analyzers.maps.index', $analyzer) }}">Back</a>
        <a class="btn btn-info" href="{{ route('medical.laboratory.analyzers.maps.import.template', $analyzer) }}">
            <i class="bi bi-download me-1"></i>CSV Template
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted">Upload a CSV with header <code>vendor_code,vendor_name,universal_code,parameter_key,unit_from,unit_to,conversion_factor,ref_low,ref_high,ref_range_text</code>. Required columns: <code>vendor_code</code>, <code>universal_code</code>. Existing vendor codes are updated, not duplicated. Max 2 MB.</p>

        <form method="POST" action="{{ route('medical.laboratory.analyzers.maps.import', $analyzer) }}" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="csv_file">CSV File *</label>
                <input type="file" id="csv_file" name="csv_file" accept=".csv,.txt" class="form-control @error('csv_file') is-invalid @enderror" required>
                @error('csv_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <button type="submit" class="btn btn-primary">Import</button>
        </form>
    </div>
</div>
@endsection
