@php
    $categoryMeta = [
        'mandatory' => ['label' => 'Mandatory', 'class' => 'text-danger', 'hint' => 'Always on'],
        'default' => ['label' => 'Default', 'class' => 'text-success', 'hint' => 'On, tenant may switch off'],
        'optional' => ['label' => 'Optional', 'class' => 'text-secondary', 'hint' => 'Off, tenant may switch on'],
        'hidden' => ['label' => 'Hidden', 'class' => 'text-dark', 'hint' => 'Not listed to tenant'],
    ];
    $categories = array_keys($categoryMeta);
    $columnCount = 1 + count($categories);

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
                    @foreach ($categories as $category)
                        <th class="text-center {{ $categoryMeta[$category]['class'] }}" style="width:15%">
                            {{ $categoryMeta[$category]['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($group['modules'] as $module)
                    @php
                        $moduleIndex = $loop->index;
                        $rowName = $groupKey . '_' . $moduleIndex;
                        $current = $matrix[$module['key']] ?? 'hidden';
                    @endphp
                    <tr class="{{ $module['has_children'] ? 'table-light fw-semibold parent-row' : '' }}"
                        @if ($module['has_children']) data-parent-key="{{ $module['key'] }}" @endif>
                        <td>
                            <input type="hidden" name="modules[{{ $rowName }}][module_key]" value="{{ $module['key'] }}">
                            <div class="d-flex align-items-center gap-1 flex-wrap">
                                @if ($module['has_children'])
                                    <a href="#"
                                       class="module-toggle text-body text-decoration-none d-inline-flex align-items-center gap-1"
                                       data-module-toggle="{{ $module['key'] }}"
                                       aria-expanded="true">
                                        <i class="bi bi-caret-down-fill" data-caret></i>
                                        <i class="bi {{ $module['icon'] ?: 'bi-puzzle' }} text-primary"></i>
                                        <span>{{ $module['name'] }}</span>
                                    </a>
                                    <span class="badge text-bg-secondary">{{ count($module['children']) }} sub</span>
                                @else
                                    <i class="bi {{ $module['icon'] ?: 'bi-puzzle' }} text-primary me-1"></i>
                                    <span>{{ $module['name'] }}</span>
                                @endif
                                <code>{{ $module['key'] }}</code>
                                @if (! empty($module['parent_key']))
                                    <span class="small text-muted">child of {{ $module['parent_key'] }}</span>
                                @endif
                            </div>
                        </td>
                        @foreach ($categories as $category)
                            <td class="text-center">
                                <label class="d-block m-0 py-1" for="mc_{{ $rowName }}_{{ $category }}"
                                       title="{{ $categoryMeta[$category]['hint'] }}">
                                    <input type="radio"
                                           name="modules[{{ $rowName }}][category]"
                                           id="mc_{{ $rowName }}_{{ $category }}"
                                           value="{{ $category }}"
                                           @if ($module['has_children']) class="parent-radio" data-parent-key="{{ $module['key'] }}" @endif
                                           {{ $current === $category ? 'checked' : '' }}>
                                    <span class="visually-hidden">{{ $categoryMeta[$category]['label'] }} — {{ $module['name'] }}</span>
                                </label>
                            </td>
                        @endforeach
                    </tr>

                    @if ($module['has_children'])
                        @php
                            $parentSaved = $matrix[$module['key']] ?? 'hidden';
                        @endphp
                        @foreach ($module['children'] as $child)
                            @php
                                $childRowName = $groupKey . '_' . $moduleIndex . '_' . $loop->index;
                                $childCurrent = $matrix[$child['key']] ?? 'hidden';
                                $childOverride = $childCurrent !== $parentSaved;
                            @endphp
                            <tr class="module-child-row child-row{{ $childOverride ? ' child-override' : '' }}"
                                data-child-of="{{ $module['key'] }}"
                                data-child-key="{{ $child['key'] }}">
                                <td>
                                    <input type="hidden" name="modules[{{ $childRowName }}][module_key]" value="{{ $child['key'] }}">
                                    <div class="ps-4 d-flex align-items-center gap-1 flex-wrap">
                                        <span class="text-muted">└</span>
                                        <i class="bi {{ $child['icon'] ?: 'bi-puzzle' }} text-primary small"></i>
                                        <span>{{ $child['name'] }}</span>
                                        <code>{{ $child['key'] }}</code>
                                        @if ($childOverride)
                                            <span class="small text-primary child-override-label">override</span>
                                        @endif
                                    </div>
                                </td>
                                @foreach ($categories as $category)
                                    <td class="text-center">
                                        <label class="d-block m-0 py-1" for="mc_{{ $childRowName }}_{{ $category }}"
                                               title="{{ $categoryMeta[$category]['hint'] }}">
                                            <input type="radio"
                                                   name="modules[{{ $childRowName }}][category]"
                                                   id="mc_{{ $childRowName }}_{{ $category }}"
                                                   value="{{ $category }}"
                                                   class="child-radio"
                                                   data-child-key="{{ $child['key'] }}"
                                                   data-parent-key="{{ $module['key'] }}"
                                                   {{ $childCurrent === $category ? 'checked' : '' }}>
                                            <span class="visually-hidden">{{ $categoryMeta[$category]['label'] }} — {{ $child['name'] }}</span>
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr>
                        <td colspan="{{ $columnCount }}" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No registered modules in this group yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
