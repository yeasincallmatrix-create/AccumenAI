@extends('layouts.institute')

@section('title', 'Migrate Medicines to DGDA — AccumenAI')

@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">Migrate Medicines to DGDA</h4>
    </div>
    <div class="page-header-actions">
        <a class="btn btn-secondary" href="{{ route('medical.pharmacy.medicines.index') }}">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary">{{ $analysis['total'] }}</h3>
                <p class="mb-0">Custom Medicines</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success">{{ count($analysis['auto_matched']) }}</h3>
                <p class="mb-0">Auto-Matched</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-warning">{{ count($analysis['need_review']) }}</h3>
                <p class="mb-0">Need Review</p>
            </div>
        </div>
    </div>
</div>

@if(count($analysis['auto_matched']) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Auto-Matched ({{ count($analysis['auto_matched']) }})</h5>
        <form action="{{ route('medical.pharmacy.medicines.migrate.apply-auto') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-success btn-sm" onclick="return confirm('Link all auto-matched medicines to DGDA?')">
                <i class="bi bi-check-all"></i> Link All
            </button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Medicine</th>
                    <th>DGDA Match</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($analysis['auto_matched'] as $item)
                <tr>
                    <td>{{ $item['medicine']->brand_name }} {{ $item['medicine']->strength }}</td>
                    <td>
                        <strong>{{ $item['match']->brand_name }}</strong>
                        <small class="text-muted d-block">{{ $item['match']->dar_number }} · {{ $item['match']->strength_raw }}</small>
                    </td>
                    <td class="text-end">
                        <form action="{{ route('medical.pharmacy.medicines.migrate.apply-manual') }}" method="POST" class="d-inline">
                            @csrf
                            <input type="hidden" name="medicine_id" value="{{ $item['medicine']->id }}">
                            <input type="hidden" name="dgda_registration_id" value="{{ $item['match']->id }}">
                            <button type="submit" class="btn btn-sm btn-success">Link</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(count($analysis['need_review']) > 0)
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Need Review ({{ count($analysis['need_review']) }})</h5>
    </div>
    <div class="card-body">
        @foreach($analysis['need_review'] as $item)
        <div class="border rounded p-3 mb-3">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <strong>{{ $item['medicine']->brand_name }} {{ $item['medicine']->strength }}</strong>
                    <small class="text-muted d-block">Generic: {{ $item['medicine']->generic_name ?? 'N/A' }}</small>
                </div>
            </div>
            <div class="mt-2">
                <small class="text-muted">Possible DGDA matches:</small>
                @foreach($item['candidates'] as $candidate)
                <div class="d-flex justify-content-between align-items-center border-top mt-2 pt-2">
                    <div>
                        {{ $candidate->brand_name }} — {{ $candidate->strength_raw }}
                        <small class="text-muted d-block">{{ $candidate->dar_number }}</small>
                    </div>
                    <form action="{{ route('medical.pharmacy.medicines.migrate.apply-manual') }}" method="POST" class="d-inline">
                        @csrf
                        <input type="hidden" name="medicine_id" value="{{ $item['medicine']->id }}">
                        <input type="hidden" name="dgda_registration_id" value="{{ $candidate->id }}">
                        <button type="submit" class="btn btn-sm btn-outline-primary">Link</button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

@if(count($analysis['no_match']) > 0)
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">No Match ({{ count($analysis['no_match']) }})</h5>
    </div>
    <div class="card-body">
        <p class="text-muted">These medicines have no DGDA registry match. They will remain as custom entries.</p>
        <ul class="list-unstyled">
            @foreach($analysis['no_match'] as $item)
            <li class="mb-1">
                <i class="bi bi-x-circle text-muted me-1"></i>
                {{ $item['medicine']->brand_name }} {{ $item['medicine']->strength }}
                <small class="text-muted">({{ $item['medicine']->code }})</small>
            </li>
            @endforeach
        </ul>
    </div>
</div>
@endif

@if($analysis['total'] === 0)
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-check-circle text-success fs-1 d-block mb-2"></i>
        <h5>All medicines are already linked to DGDA</h5>
        <p class="text-muted mb-0">No custom medicines found without DGDA codes.</p>
    </div>
</div>
@endif
@endsection
