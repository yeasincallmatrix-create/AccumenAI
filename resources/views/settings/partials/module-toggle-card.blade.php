@php
    $isDisabled = !$module->can_toggle;
    $reasonLabel = match($module->reason_disabled) {
        'permission_denied' => 'No permission',
        'upgrade_required' => 'Upgrade required',
        'parent_disabled' => 'Parent module is off',
        default => null,
    };
@endphp

<div class="col-md-6 col-lg-4 module-toggle-card">
    <div class="card h-100 {{ $isDisabled ? 'bg-light' : '' }} {{ $module->coming_soon ? 'border-secondary' : '' }}">
        <div class="card-body d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2 flex-grow-1">
                @if($module->icon)
                    <i class="bi {{ $module->icon }} fs-4 {{ $isDisabled ? 'text-muted' : 'text-primary' }}"></i>
                @else
                    <i class="bi bi-box fs-4 {{ $isDisabled ? 'text-muted' : 'text-primary' }}"></i>
                @endif
                <div>
                    <div class="fw-semibold {{ $isDisabled ? 'text-muted' : '' }}">
                        {{ $module->name }}
                    </div>
                    @if($module->coming_soon)
                        <small class="badge bg-secondary">Coming Soon</small>
                    @elseif($reasonLabel)
                        <small class="text-muted d-block" style="font-size: 0.7rem;">
                            @if($module->reason_disabled === 'permission_denied')
                                <i class="bi bi-lock-fill me-1"></i>
                            @elseif($module->reason_disabled === 'upgrade_required')
                                <i class="bi bi-arrow-up-circle me-1"></i>
                            @elseif($module->reason_disabled === 'parent_disabled')
                                <i class="bi bi-arrow-down-circle me-1"></i>
                            @endif
                            {{ $reasonLabel }}
                        </small>
                    @endif
                </div>
            </div>

            <div class="form-check form-switch ms-3">
                <input
                    class="form-check-input module-toggle"
                    type="checkbox"
                    role="switch"
                    id="toggle_{{ str_replace('.', '_', $module->key) }}"
                    data-module-key="{{ $module->key }}"
                    {{ $module->enabled ? 'checked' : '' }}
                    {{ $isDisabled || $module->coming_soon ? 'disabled' : '' }}>
            </div>
        </div>
    </div>
</div>
