@php
    $categoryMeta = [
        'mandatory' => ['label' => 'Mandatory', 'class' => 'text-danger', 'hint' => 'Always on'],
        'default' => ['label' => 'Default', 'class' => 'text-success', 'hint' => 'On, tenant may switch off'],
        'optional' => ['label' => 'Optional', 'class' => 'text-secondary', 'hint' => 'Off, tenant may switch on'],
        'hidden' => ['label' => 'Hidden', 'class' => 'text-dark', 'hint' => 'Not listed to tenant'],
    ];
    $categories = array_keys($categoryMeta);
@endphp

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info">
            <i class="bi {{ $group['icon'] }}"></i> {{ $group['label'] }}
            <span class="text-muted ms-2">{{ $group['description'] }}</span>
        </div>
        <div class="toolbar-actions">
            <span class="badge bg-light text-dark border">{{ count($group['modules']) }} module(s)</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:30%">Module</th>
                    <th style="width:22%">Key</th>
                    <th>Category</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($group['modules'] as $module)
                    @php
                        $rowName = $groupKey . '_' . $loop->index;
                        $current = $matrix[$module['key']] ?? 'hidden';
                    @endphp
                    <tr>
                        <td class="fw-semibold">
                            <i class="bi {{ $module['icon'] ?: 'bi-puzzle' }} me-1 text-primary"></i>
                            {{ $module['name'] }}
                        </td>
                        <td>
                            <code>{{ $module['key'] }}</code>
                            @if (! empty($module['parent_key']))
                                <div class="small text-muted">child of {{ $module['parent_key'] }}</div>
                            @endif
                        </td>
                        <td>
                            <input type="hidden" name="modules[{{ $rowName }}][module_key]" value="{{ $module['key'] }}">
                            <div class="d-flex flex-wrap gap-3">
                                @foreach ($categories as $category)
                                    <div class="form-check" title="{{ $categoryMeta[$category]['hint'] }}">
                                        <input class="form-check-input" type="radio"
                                               name="modules[{{ $rowName }}][category]"
                                               id="mc_{{ $rowName }}_{{ $category }}"
                                               value="{{ $category }}"
                                               {{ $current === $category ? 'checked' : '' }}>
                                        <label class="form-check-label {{ $categoryMeta[$category]['class'] }}"
                                               for="mc_{{ $rowName }}_{{ $category }}">
                                            {{ $categoryMeta[$category]['label'] }}
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            <i class="bi bi-inbox"></i> No registered modules in this group yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
