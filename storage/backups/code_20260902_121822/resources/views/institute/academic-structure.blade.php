@extends('layouts.standalone')

@section('title', 'Academic Structure — AccumenAI')
@section('page_title', 'Academic Structure')

@section('content')

@php
    $label = $structure['academic_unit_label'] ?? 'Class';
    $country = $structure['country'] ?? null;
@endphp

@include('institute.academic._step-nav', ['currentStep'=>1,'currentLabel'=>'Structure','prevRoute'=>null,'prevLabel'=>null,'nextRoute'=>'settings.academic.academic-years.index','nextLabel'=>'2 · Academic Years'])

<div class="standalone-heading">
    <h4>1 · Structure — Academic Structure</h4>
    <p>Step 1 of 7 — Customize the education hierarchy for this institute — enable or disable inherited levels / classes / groups, add your own, and name the top-level unit. Changes apply immediately. Complete this before Placements.</p>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-diagram-3"></i> Learning Structure (Generic — N-level)</div>
        <span class="badge text-bg-light border small">Phase 3</span>
    </div>
    <div class="p-3">
        <p class="small text-muted mb-2">Generic resolver — template and levels come from <code>LearningStructureResolver</code>. This selector works for School (Class→Section), University (Faculty→Department→Program→Semester), Training Institute (Course→Batch), etc. without hardcoding.</p>
        <div data-learning-component
             data-options-endpoint="{{ route('academic.structure.options') }}"
             data-nodes-endpoint="{{ route('academic.structure.nodes') }}"
             data-branch-id="{{ request()->query('branch_id') ?? '' }}">
            <div class="text-muted small">Loading structure…</div>
        </div>
    </div>
</div>

<div class="admin-card mb-3">
    <div class="table-toolbar">
        <div class="toolbar-info"><i class="bi bi-tags"></i> Academic unit label</div>
    </div>
    <form method="POST" action="{{ route('settings.academic.label') }}" class="row g-2 align-items-start">
        @csrf
        @method('PUT')
        <div class="col-md-4 d-flex flex-column">
            <label class="form-label small mb-1 fw-medium" for="academic_unit_label">Unit label</label>
            <input type="text" id="academic_unit_label" name="academic_unit_label" class="form-control form-control-sm"
                   value="{{ old('academic_unit_label', $institute->settings?->academic_unit_label) }}"
                   maxlength="40" placeholder="{{ $label }}">
            <div class="form-text small" style="min-height:18px;">Blank = use country default ("{{ $label }}").</div>
            @error('academic_unit_label')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4 d-flex flex-column">
            <label class="form-label small mb-1 fw-medium">Country</label>
            <input type="text" class="form-control form-control-sm bg-light" value="{{ $country?->name ?? '—' }}" disabled style="height:31px;">
            <div class="form-text small invisible" style="min-height:18px;">.</div>
        </div>
        <div class="col-md-4 d-flex flex-column justify-content-start">
            <label class="form-label small mb-1 d-none d-md-block invisible">action</label>
            <button class="btn btn-primary btn-sm w-100" type="submit" style="height:31px;"><i class="bi bi-check-lg me-1"></i>Save</button>
            <div class="form-text small invisible" style="min-height:18px;">.</div>
        </div>
    </form>
</div>

<div id="groups" style="scroll-margin-top: 80px;" aria-hidden="true"></div>

@if (empty($structure['systems']))
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle"></i>
            No education systems have been configured for {{ $country?->name ?? 'your country' }} yet. Please contact your platform administrator.
        </p>
    </div>
@endif

@foreach ($structure['systems'] as $systemData)
    @php $system = $systemData['education_system']; @endphp
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-diagram-3"></i> {{ $system->name }}</div>
        </div>

        @forelse ($systemData['levels'] as $levelNode)
            @php
                $level = $levelNode['level'];          // AcademicLevel|null
                $lvlOv = $levelNode['override'];       // InstituteAcademicLevel|null
                $lvlBadge = $levelNode['source'] === 'custom' ? 'text-bg-info' : ($levelNode['source'] === 'customized' ? 'text-bg-warning' : 'text-bg-light border');
                $lvlBadgeText = $levelNode['source'] === 'custom' ? 'Custom' : ($levelNode['source'] === 'customized' ? 'Customized' : 'Inherited');
            @endphp

            <div class="academic-block">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <h6 class="mb-0 fw-semibold">
                        <i class="bi bi-layers"></i> {{ $levelNode['name'] }}
                        <span class="badge {{ $lvlBadge }} ms-1">{{ $lvlBadgeText }}</span>
                    </h6>
                    <div class="d-flex gap-2 align-items-center">
                        @if ($level !== null)
                            <form method="POST" action="{{ route('settings.academic.levels.update', $level) }}" data-academic-form class="d-inline">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="enabled" value="{{ $levelNode['enabled'] ? 0 : 1 }}">
                                <div class="form-check form-switch form-switch-sm mb-0">
                                    <input class="form-check-input" type="checkbox" data-enable-switch
                                           @checked($levelNode['enabled'])>
                                    <label class="form-check-label small text-muted">{{ $levelNode['enabled'] ? 'Enabled' : 'Disabled' }}</label>
                                </div>
                            </form>
                        @elseif ($lvlOv)
                            <form method="POST" action="{{ route('settings.academic.levels.destroy', $lvlOv) }}"
                                  data-ajax-action="1" data-confirm="Remove this custom level (including its classes and groups)?" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Remove</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if ($level !== null && ! $levelNode['enabled'])
                    <div class="alert alert-light border py-2 small text-muted mb-2">
                        <i class="bi bi-info-circle"></i> This level is disabled. Its classes will not appear.
                    </div>
                @endif

                <div class="ps-3 border-start border-2 ms-1 mb-3">
                    @forelse ($levelNode['classes'] as $classNode)
                        @php
                            $classGrade = $classNode['class_grade'];
                            $clsOv = $classNode['override'];
                            $clsBadge = $classNode['source'] === 'custom' ? 'text-bg-info' : ($classNode['source'] === 'customized' ? 'text-bg-warning' : 'text-bg-light border');
                            $clsBadgeText = $classNode['source'] === 'custom' ? 'Custom' : ($classNode['source'] === 'customized' ? 'Customized' : 'Inherited');
                        @endphp
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-1">
                            <span class="fw-semibold small">
                                <i class="bi bi-book"></i> {{ $classNode['name'] }}
                                <span class="badge {{ $clsBadge }} ms-1">{{ $clsBadgeText }}</span>
                                @if ($classNode['groups'])
                                    <span class="text-muted fw-normal">{{ count($classNode['groups']) }} group(s)</span>
                                @endif
                            </span>
                            <div class="d-flex gap-2 align-items-center">
                            @if ($classGrade !== null)
                                <form method="POST" action="{{ route('settings.academic.classes.update', $classGrade) }}" data-academic-form class="d-inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="enabled" value="{{ $classNode['enabled'] ? 0 : 1 }}">
                                    <div class="form-check form-switch form-switch-sm mb-0">
                                        <input class="form-check-input" type="checkbox" data-enable-switch @checked($classNode['enabled'])>
                                        <label class="form-check-label small text-muted">{{ $classNode['enabled'] ? 'Enabled' : 'Disabled' }}</label>
                                    </div>
                                </form>
                            @elseif ($clsOv)
                                <form method="POST" action="{{ route('settings.academic.classes.destroy', $clsOv) }}"
                                      data-ajax-action="1" data-confirm="Remove this custom class and its groups?" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                            </div>
                        </div>

                        @if ($classNode['groups'])
                            <div class="ps-3 small">
                                @foreach ($classNode['groups'] as $groupNode)
                                    @php
                                        $group = $groupNode['academic_group'];
                                        $grpOv = $groupNode['override'];
                                    @endphp
                                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 py-1">
                                        <span class="text-muted"><i class="bi bi-people"></i> {{ $groupNode['name'] }}</span>
                                        <div class="d-flex gap-2 align-items-center">
                                        @if ($group !== null)
                                            <form method="POST" action="{{ route('settings.academic.groups.update', $group) }}" data-academic-form class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="enabled" value="{{ $groupNode['enabled'] ? 0 : 1 }}">
                                                <div class="form-check form-switch form-switch-sm mb-0">
                                                    <input class="form-check-input" type="checkbox" data-enable-switch @checked($groupNode['enabled'])>
                                                    <label class="form-check-label small text-muted">{{ $groupNode['enabled'] ? 'Enabled' : 'Disabled' }}</label>
                                                </div>
                                            </form>
                                        @elseif ($grpOv)
                                            <form method="POST" action="{{ route('settings.academic.groups.destroy', $grpOv) }}"
                                                  data-ajax-action="1" data-confirm="Remove this custom group?" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @empty
                        <p class="text-muted small mb-0 py-1">No classes under this level.</p>
                    @endforelse

                    {{-- Add class under this level (global level, or custom level override row). --}}
                    <form method="POST" action="{{ route('settings.academic.classes.store') }}" class="row g-2 mt-2 align-items-center">
                        @csrf
                        <input type="hidden" name="{{ $level !== null ? 'academic_level_id' : 'institute_academic_level_id' }}"
                               value="{{ $level !== null ? $level->id : $lvlOv->id }}">
                        <div class="col-auto flex-grow-1">
                            <input type="text" name="name" class="form-control form-control-sm" placeholder="Add custom class under this level (e.g. Grade 7)" maxlength="120" required>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> Add class</button>
                        </div>
                        @error('name')<div class="text-danger small col-12">{{ $message }}</div>@enderror
                    </form>
                </div>
            </div>
        @empty
            <p class="text-muted small mb-3">No levels configured for this system.</p>
        @endforelse

        <div class="table-toolbar border-top pt-2">
            <div class="toolbar-info">
                <i class="bi bi-plus-circle"></i> Add custom level to {{ $system->name }}
            </div>
        </div>
        <form method="POST" action="{{ route('settings.academic.levels.store') }}" class="row g-2 px-3 pb-3">
            @csrf
            <input type="hidden" name="education_system_id" value="{{ $system->id }}">
            <div class="col-md-6">
                <input type="text" name="name" class="form-control form-control-sm" placeholder="e.g. Upper Secondary" maxlength="120" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="display_order" class="form-control form-control-sm" placeholder="Order" min="0" value="0">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-lg"></i> Add level</button>
            </div>
            @error('education_system_id')<div class="text-danger small col-12">{{ $message }}</div>@enderror
            @error('name')<div class="text-danger small col-12">{{ $message }}</div>@enderror
        </form>
    </div>
@endforeach

@endsection

@push('scripts')
<script src="{{ asset('js/learning-select.js') }}"></script>
<script>
(function () {
    document.querySelectorAll('input[data-enable-switch]').forEach(function (box) {
        box.addEventListener('change', function () {
            var form = box.closest('form');
            var hidden = form.querySelector('input[name=enabled]');
            if (hidden) {
                hidden.value = box.checked ? '1' : '0';
            }
            form.submit();
        });
    });
})();
</script>
@endpush