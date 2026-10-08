@php
    $coreLookup = array_flip(config('industry-modules.core', []));
    $disabledLookup = array_flip(config("industry-modules.{$industry}.disabled", []));
    // A disabled root disables its whole subtree (real_estate ⇒ real_estate.*).
    $isDisabled = fn ($key) => isset($disabledLookup[$key]) || isset($disabledLookup[explode('.', $key, 2)[0]]);
    $parentCount = count($group['modules']);
    $childCount = $group['child_count'] ?? 0;
@endphp

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi {{ $group['icon'] }}"></i> {{ $group['label'] }}
            <span class="text-muted ms-2">{{ $group['description'] }}</span>
        </div>
        <div class="toolbar-actions">
            <span class="badge bg-light text-dark border">{{ $parentCount }} module(s)</span>
            @if ($childCount > 0)
                <span class="badge bg-light text-dark border">{{ $childCount }} sub-module(s)</span>
            @endif
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:40%">Module</th>
                    @foreach ($tiers as $tier)
                        <th class="text-center" style="width:{{ 60 / count($tiers) }}%">
                            <div class="fw-bold">{{ $tier['name'] }}</div>
                            <div class="d-flex align-items-center justify-content-center gap-1 mt-1">
                                <span class="badge text-bg-success">{{ $planCounts[$tier['id']] ?? 0 }} on</span>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-plan-tier-select="{{ $tier['id'] }}" title="Select all in {{ $tier['name'] }}">All</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1" data-plan-tier-clear="{{ $tier['id'] }}" title="Select none in {{ $tier['name'] }}">None</button>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($group['modules'] as $module)
                    @php
                        $isCore = isset($coreLookup[$module['key']]);
                    @endphp
                    <tr class="{{ $module['has_children'] ? 'table-light fw-semibold parent-row' : '' }}">
                        <td>
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                @if ($module['has_children'])
                                    <i class="bi {{ $module['icon'] ?: 'bi-puzzle' }} text-primary"></i>
                                    <span>{{ $module['name'] }}</span>
                                    <span class="badge text-bg-secondary">{{ count($module['children']) }} sub</span>
                                @else
                                    <i class="bi {{ $module['icon'] ?: 'bi-puzzle' }} text-primary me-1"></i>
                                    <span>{{ $module['name'] }}</span>
                                @endif
                                <code>{{ $module['key'] }}</code>
                                @if ($isCore)
                                    <span class="badge text-bg-info">core</span>
                                @endif
                            </div>
                        </td>
                        @foreach ($tiers as $tier)
                            @php
                                $blocked = $isDisabled($module['key']);
                                $checked = $blocked ? false : (! empty($planMatrix[$tier['id']][$module['key']]) || $isCore);
                            @endphp
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block">
                                    <input class="form-check-input plan-check" type="checkbox"
                                           name="plans[{{ $tier['id'] }}][]"
                                           value="{{ $module['key'] }}"
                                           data-key="{{ $module['key'] }}"
                                           data-rank="{{ $tier['rank'] }}"
                                           {{ $checked ? 'checked' : '' }}
                                           {{ ($isCore || $blocked) ? 'disabled' : '' }}>
                                </div>
                                @if ($blocked)
                                    <div><span class="badge text-bg-danger" style="font-size:.65rem">not for industry</span></div>
                                @endif
                            </td>
                        @endforeach
                    </tr>

                    @if ($module['has_children'])
                        @foreach ($module['children'] as $child)
                            @php
                                $childCore = isset($coreLookup[$child['key']]);
                            @endphp
                            <tr class="module-child-row">
                                <td>
                                    <div class="ps-4 d-flex align-items-center gap-1 flex-wrap">
                                        <span class="text-muted">└</span>
                                        <i class="bi {{ $child['icon'] ?: 'bi-puzzle' }} text-primary small"></i>
                                        <span>{{ $child['name'] }}</span>
                                        <code>{{ $child['key'] }}</code>
                                        @if ($childCore)
                                            <span class="badge text-bg-info">core</span>
                                        @endif
                                    </div>
                                </td>
                                @foreach ($tiers as $tier)
                                    @php
                                        $childBlocked = $isDisabled($child['key']);
                                        $childChecked = $childBlocked ? false : (! empty($planMatrix[$tier['id']][$child['key']]) || $childCore);
                                    @endphp
                                    <td class="text-center">
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input plan-check" type="checkbox"
                                                   name="plans[{{ $tier['id'] }}][]"
                                                   value="{{ $child['key'] }}"
                                                   data-key="{{ $child['key'] }}"
                                                   data-rank="{{ $tier['rank'] }}"
                                                   {{ $childChecked ? 'checked' : '' }}
                                                   {{ ($childCore || $childBlocked) ? 'disabled' : '' }}>
                                        </div>
                                        @if ($childBlocked)
                                            <div><span class="badge text-bg-danger" style="font-size:.65rem">not for industry</span></div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ 1 + count($tiers) }}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No registered modules in this group yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
