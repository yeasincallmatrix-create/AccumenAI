@extends('layouts.institute')

@section('page-title', 'Feature Access')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-grid-3x3-gap-fill me-2"></i>Feature Access</h5>
                    <span class="badge bg-primary">{{ $features->count() }} features</span>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Features available to <strong>{{ $institute->name }}</strong>. Your access is determined by your subscription package. Platform administrators may grant or deny specific features via override.</p>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    @php
                        $grouped = $features->groupBy('module_key');
                    @endphp

                    @forelse($grouped as $moduleKey => $moduleFeatures)
                        <div class="mb-4">
                            <h6 class="text-uppercase text-muted fw-bold mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                                <i class="bi bi-puzzle me-1"></i>{{ str_replace('_', ' ', ucfirst($moduleKey)) }}
                            </h6>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th style="min-width:200px">Feature</th>
                                            <th class="text-center" style="width:120px">Status</th>
                                            <th class="text-center" style="width:160px">Source</th>
                                            <th style="min-width:150px">Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($moduleFeatures as $feature)
                                            @php
                                                $state = $states[$feature->feature_key] ?? ['enabled' => false, 'source' => 'package', 'granted_by' => null, 'reason' => null];
                                            @endphp
                                            <tr>
                                                <td>
                                                    <strong>{{ $feature->name }}</strong>
                                                    <br><small class="text-muted"><code>{{ $feature->feature_key }}</code></small>
                                                </td>
                                                <td class="text-center">
                                                    @if($state['enabled'])
                                                        <span class="badge text-bg-success"><i class="bi bi-check-circle me-1"></i>Enabled</span>
                                                    @else
                                                        <span class="badge text-bg-secondary"><i class="bi bi-x-circle me-1"></i>Disabled</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    @if($state['source'] === 'override')
                                                        @if($state['enabled'])
                                                            <span class="badge text-bg-primary"><i class="bi bi-shield-check me-1"></i>Override granted</span>
                                                        @else
                                                            <span class="badge text-bg-danger"><i class="bi bi-shield-x me-1"></i>Override denied</span>
                                                        @endif
                                                    @else
                                                        <span class="badge text-bg-light text-dark"><i class="bi bi-box me-1"></i>Package default</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($state['reason'])
                                                        <small class="text-muted">{{ $state['reason'] }}</small>
                                                    @elseif($state['source'] === 'override' && $state['granted_by'])
                                                        <small class="text-muted">By {{ $state['granted_by'] }}</small>
                                                    @else
                                                        <small class="text-muted">—</small>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-grid-3x3-gap fs-1 d-block mb-3"></i>
                            <p>No features registered in the system.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
