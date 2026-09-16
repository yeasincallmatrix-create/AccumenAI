@extends('layouts.institute')

@section('page-title', 'Module Settings')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-hospital me-2"></i>Medical Sub-Modules</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Enable or disable medical sub-modules for your institute. Changes take effect immediately.</p>

                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.modules.update') }}">
                        @csrf

                        @foreach($subModules as $sub)
                            @php
                                $isEnabled = in_array($sub->key, $enabledKeys, true);
                            @endphp
                            <div class="form-check form-switch mb-3 p-3 border rounded {{ $sub->coming_soon ? 'bg-light' : '' }}">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="modules[]"
                                       value="{{ $sub->key }}"
                                       id="mod_{{ $sub->key }}"
                                       {{ $isEnabled ? 'checked' : '' }}
                                       {{ $sub->coming_soon ? 'disabled' : '' }}>
                                <label class="form-check-label d-flex align-items-center" for="mod_{{ $sub->key }}">
                                    <i class="bi {{ $sub->icon }} me-2 fs-5"></i>
                                    <span>
                                        <strong>{{ $sub->name }}</strong>
                                        @if($sub->coming_soon)
                                            <span class="badge bg-secondary ms-2" style="font-size:10px;">Coming Soon</span>
                                        @endif
                                    </span>
                                </label>
                            </div>
                        @endforeach

                        <button type="submit" class="btn btn-primary mt-3">
                            <i class="bi bi-save me-1"></i>Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
