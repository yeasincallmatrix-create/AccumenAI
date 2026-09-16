@extends('layouts.institute')
@section('page-title', 'Import Medicines')

@section('content')
<div class="container">
    <div class="admin-card">
        <h3>Bulk Import Medicines</h3>

        <div class="alert alert-info">
            <strong>Instructions:</strong>
            <ol>
                <li>Download the CSV template</li>
                <li>Fill in your medicines (one per row)</li>
                <li>Upload the CSV file (max 5 MB)</li>
                <li>Duplicate rows (same brand_name + strength) will be skipped</li>
            </ol>
            <a href="{{ route('medical.pharmacy.medicines.import.template') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i> Download Template
            </a>
        </div>

        @if(session('import_summary'))
            @php $s = session('import_summary'); @endphp
            <div class="alert alert-{{ $s['imported'] > 0 ? 'success' : 'warning' }}">
                <h5>Import Summary</h5>
                <p><strong>Imported:</strong> {{ $s['imported'] }}</p>
                <p><strong>Skipped:</strong> {{ $s['skipped'] }}</p>

                @if(!empty($s['errors']))
                    <details>
                        <summary>View {{ $s['total_errors'] }} error(s)</summary>
                        <ul class="mb-0 mt-2">
                            @foreach($s['errors'] as $err)
                                <li><small>{{ $err }}</small></li>
                            @endforeach
                            @if($s['total_errors'] > count($s['errors']))
                                <li><em>... and {{ $s['total_errors'] - count($s['errors']) }} more</em></li>
                            @endif
                        </ul>
                    </details>
                @endif
            </div>
        @endif

        <form action="{{ route('medical.pharmacy.medicines.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label for="csv_file" class="form-label">CSV File</label>
                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv,.txt" required>
                @error('csv_file')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-upload"></i> Import
            </button>
            <a href="{{ route('medical.pharmacy.medicines.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection
