@php
    $selected = array_flip(array_map('intval', $selectedSubjectIds ?? []));
    $mandatoryCount = count($payload['mandatory'] ?? []);
    $totalAvailable = $mandatoryCount
        + count($payload['ungrouped'] ?? [])
        + collect($payload['groups'] ?? [])->sum(fn ($g) => count($g['members'] ?? []));
@endphp

@if (! empty($payload['config_errors'] ?? []))
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>Some selection groups have configuration problems.
        <ul class="mb-0 mt-1">
            @foreach ($payload['config_errors'] as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($totalAvailable === 0)
    <div class="admin-card">
        <p class="text-muted mb-0 py-3 text-center">
            <i class="bi bi-info-circle me-1"></i>{{ $payload['class_id'] ?? false ? 'No subjects are configured for this class / group yet.' : 'Select a class to load its subjects.' }}
        </p>
    </div>
@else

    {{-- Mandatory (always auto-included, never a checkbox) --}}
    <div class="admin-card mb-3">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-star-fill"></i>
                <span class="fw-semibold">Mandatory Subjects</span>
                <span class="badge text-bg-success badge-soft ms-2">{{ $mandatoryCount }}</span>
            </div>
        </div>
        <div class="p-3 d-flex flex-wrap gap-2">
            @forelse ($payload['mandatory'] as $node)
                <span class="badge text-bg-light border px-3 py-2">
                    <i class="bi bi-check2-circle text-success me-1"></i>{{ $node['name'] }}
                </span>
            @empty
                <span class="text-muted small">No mandatory subjects.</span>
            @endforelse
        </div>
    </div>

    {{-- Optional / elective groups --}}
    @foreach ($payload['groups'] as $entry)
        @php
            $group = $entry['group'];
            $rules = $entry['rules'];
            $picked = 0;
            foreach ($entry['members'] as $node) {
                if (isset($selected[$node['id']])) {
                    $picked++;
                }
            }
        @endphp
        <div class="admin-card mb-3" data-selection-group data-group-id="{{ $group->id }}" data-min="{{ $rules['minimum'] }}" data-max="{{ $rules['maximum'] }}">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-collection"></i>
                    <span class="fw-semibold">{{ $group->name }}</span>
                    <span class="badge text-bg-light border ms-2">{{ $rules['selection_type'] }}</span>
                    <span class="badge text-bg-secondary badge-soft ms-2" data-group-count>Selected: {{ $picked }} / {{ $rules['maximum'] }}</span>
                    <span class="text-muted small ms-2">Choose {{ $rules['minimum'] }} – {{ $rules['maximum'] }} subject(s)</span>
                </div>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    @foreach ($entry['members'] as $node)
                        <div class="col-md-4">
                            <label class="subject-pick d-flex align-items-center gap-2 p-2 border rounded">
                                <input type="checkbox" name="subject_ids[]" value="{{ $node['id'] }}" class="form-check-input mt-0"
                                       data-subject-pick @checked(isset($selected[$node['id']]))>
                                <span>{{ $node['name'] }}</span>
                                @if ($node['short_name'])
                                    <small class="text-muted">{{ $node['short_name'] }}</small>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endforeach

    {{-- Free optional / elective (no group) --}}
    @if (count($payload['ungrouped'] ?? []) > 0)
        <div class="admin-card mb-3">
            <div class="table-toolbar">
                <div class="toolbar-info">
                    <i class="bi bi-collection"></i>
                    <span class="fw-semibold">Optional / Elective Subjects</span>
                </div>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    @foreach ($payload['ungrouped'] as $node)
                        <div class="col-md-4">
                            <label class="subject-pick d-flex align-items-center gap-2 p-2 border rounded">
                                <input type="checkbox" name="subject_ids[]" value="{{ $node['id'] }}" class="form-check-input mt-0"
                                       data-subject-pick @checked(isset($selected[$node['id']]))>
                                <span>{{ $node['name'] }}</span>
                                @if ($node['short_name'])
                                    <small class="text-muted">{{ $node['short_name'] }}</small>
                                @endif
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

@endif