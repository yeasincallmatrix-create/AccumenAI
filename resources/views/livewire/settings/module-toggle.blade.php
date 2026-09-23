<div>
    @if (session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Default Modules --}}
    <div class="mb-4">
        <h6>Included Modules</h6>
        @foreach($defaultModules as $module)
            <div class="form-check">
                <input type="checkbox" checked disabled class="form-check-input">
                <label class="form-check-label">
                    {{ $module }}
                    <span class="badge bg-success">Included</span>
                </label>
            </div>
        @endforeach
    </div>

    {{-- Optional Modules --}}
    @if(!empty($optionalModules))
    <div class="mb-4">
        <h6>Optional Modules</h6>
        @foreach($optionalModules as $module)
            <div class="form-check">
                <input type="checkbox"
                       wire:click="toggleModule('{{ $module }}')"
                       {{ in_array($module, $enabledModules) ? 'checked' : '' }}
                       class="form-check-input">
                <label class="form-check-label">
                    {{ $module }}
                    <span class="badge bg-warning">Optional</span>
                </label>
            </div>
        @endforeach
    </div>
    @endif

    {{-- Disabled Modules --}}
    @if(!empty($disabledModules))
    <div class="mb-4">
        <h6>Not Available</h6>
        @foreach($disabledModules as $module)
            <div class="form-check">
                <input type="checkbox" disabled class="form-check-input">
                <label class="form-check-label text-muted">
                    {{ $module }}
                    <span class="badge bg-secondary">Not Available</span>
                </label>
            </div>
        @endforeach
    </div>
    @endif
</div>
