@extends('layouts.institute')

@section('title', 'Module Management')

@push('styles')
<style>
  .topbar, .sidebar, .sidebar-backdrop { display: none !important; }
  .layout { display: block !important; }
  .content { margin-left: 0 !important; padding-top: 1.25rem !important; max-width: 100% !important; }
  .module-toggle-card .card { transition: border-color .2s, box-shadow .2s; }
  .module-toggle-card .card.border-primary { box-shadow: 0 0 0 .15rem rgba(13,110,253,.15); }
  .module-toggle-card.flash-success { animation: flash .8s ease; }
  @keyframes flash { 0%{background:rgba(25,135,84,.08)} 100%{background:transparent} }
</style>
@endpush

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0"><i class="bi bi-grid-3x3-gap me-2"></i>Module Management</h4>
            <p class="text-muted mb-0">Enable or disable modules for your institute. Changes apply immediately.</p>
        </div>
        <a href="{{ url('/settings') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back to Settings
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card mb-4">
        <div class="card-body py-2">
            <div class="d-flex gap-3 flex-wrap small">
                <span><span class="badge bg-success">ON</span> Active module</span>
                <span><span class="badge bg-secondary">OFF</span> Disabled module</span>
                <span><i class="bi bi-lock-fill text-muted"></i> No permission</span>
                <span><i class="bi bi-arrow-up-circle text-warning"></i> Upgrade required</span>
            </div>
        </div>
    </div>

    @if(isset($grouped['_root']))
        <div class="row g-3 mb-4">
            @foreach($grouped['_root'] as $module)
                @include('settings.partials.module-toggle-card', ['module' => $module])
            @endforeach
        </div>
    @endif

    @foreach($grouped as $parentKey => $children)
        @if($parentKey === '_root') @continue @endif

        @php
            $parent = $enriched->firstWhere('key', $parentKey);
        @endphp

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    @if($parent && $parent->icon)
                        <i class="bi {{ $parent->icon }} me-2"></i>
                    @else
                        <i class="bi bi-folder me-2"></i>
                    @endif
                    <strong>{{ $parent?->name ?? $parentKey }}</strong>
                    @if($parent && $parent->enabled)
                        <span class="badge bg-success ms-2">ON</span>
                    @else
                        <span class="badge bg-secondary ms-2">OFF</span>
                    @endif
                </div>
                <small class="text-muted">{{ count($children) }} sub-modules</small>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    @foreach($children as $child)
                        @include('settings.partials.module-toggle-card', ['module' => $child])
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.module-toggle').forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            var key = this.dataset.moduleKey;
            var enabled = this.checked ? 1 : 0;
            var row = this.closest('.module-toggle-card');
            var originalState = !this.checked;

            fetch('{{ route("settings.modules.toggle") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ module_key: key, enabled: enabled }),
            })
            .then(function(r) { return r.json().then(function(data) { return { ok: r.ok, status: r.status, data: data }; }); })
            .then(function(result) {
                if (result.ok) {
                    row.classList.add('flash-success');
                    setTimeout(function() { row.classList.remove('flash-success'); }, 1000);
                    if (key.indexOf('.') === -1 && !enabled) {
                        setTimeout(function() { window.location.reload(); }, 500);
                    }
                } else {
                    alert(result.data.error || 'Failed to toggle module.');
                    toggle.checked = originalState;
                }
            })
            .catch(function() {
                alert('Network error.');
                toggle.checked = originalState;
            });
        });
    });
});
</script>
@endpush
