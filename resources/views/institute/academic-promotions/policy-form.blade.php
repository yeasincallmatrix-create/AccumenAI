@php
    $isAjax = request()->header('X-Requested-With') === 'XMLHttpRequest';
@endphp

@if(!$isAjax)
@extends('layouts.standalone')
@section('title', ($policy ? 'Edit' : 'New').' Promotion Policy — AccumenAI')
@section('page_title', $policy ? 'Edit Promotion Policy' : 'New Promotion Policy')
@endif

@php
    $classGroups = [];
    foreach ($classes as $entry) {
        $classGroups[(int) $entry['class_grade']->id] = $entry['class_grade']->groups()
            ->where('status', true)
            ->orderBy('display_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($group) => ['id' => (int) $group->id, 'name' => $group->name])
            ->all();
    }
@endphp

@section('content')

@if(!$isAjax)
<div class="standalone-heading">
    <h4>{{ $policy ? 'Edit Promotion Policy' : 'New Promotion Policy' }}</h4>
    <p>A policy declares the academic context (year + class + optional group) whose PUBLISHED final result will be evaluated. Rules are added after the policy exists.</p>
</div>
@endif

<form method="POST" action="{{ $policy ? route('settings.academic.promotions.policies.update', $policy) : route('settings.academic.promotions.policies.store') }}" data-promo-policy-form>
    @csrf
    @if ($policy)@method('PUT')@endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="pp_name">Policy Name *</label>
            <input type="text" id="pp_name" name="name" class="form-control" maxlength="120" required
                   value="{{ old('name', $policy?->name) }}" placeholder="e.g. Class 8 Promotion Rules">
            @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-2">
            <label class="form-label" for="pp_status">Status</label>
            <select id="pp_status" name="status" class="form-select">
                <option value="draft" @selected(old('status', $policy?->status ?? 'draft') === 'draft')>Draft</option>
                <option value="active" @selected(old('status', $policy?->status ?? 'draft') === 'active')>Active</option>
                <option value="archived" @selected(old('status', $policy?->status ?? 'draft') === 'archived')>Archived</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pp_year">Academic Year (source) *</label>
            <select id="pp_year" name="academic_year_id" class="form-select" required>
                <option value="">Select year</option>
                @foreach ($academicYears as $year)
                    <option value="{{ $year->id }}" @selected($year->id == ($policy?->academic_year_id ?? old('academic_year_id')))>
                        {{ $year->name }}@if ($year->is_current) (Current)@endif
                    </option>
                @endforeach
            </select>
            @error('academic_year_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pp_class">Class / Grade *</label>
            <select id="pp_class" name="class_grade_id" class="form-select" required>
                <option value="">Select class</option>
                @foreach ($classes as $entry)
                    <option value="{{ $entry['class_grade']->id }}" @selected($policy !== null && $policy->class_grade_id === $entry['class_grade']->id)>
                        {{ $entry['name'] }}@if ($entry['level_name']) — {{ $entry['level_name'] }}@endif
                    </option>
                @endforeach
            </select>
            @error('class_grade_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="pp_group">Group / Stream</label>
            <select id="pp_group" name="academic_group_id" class="form-select" data-selected="{{ $policy?->academic_group_id ?? '' }}">
                <option value="">Whole class</option>
                @if ($policy !== null && $policy->classGrade !== null)
                    @foreach ($policy->classGrade->groups()->where('status', true)->get() as $group)
                        <option value="{{ $group->id }}" @selected($policy->academic_group_id === $group->id)>{{ $group->name }}</option>
                    @endforeach
                @endif
            </select>
            @error('academic_group_id')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" type="submit">
            <i class="bi bi-check-lg me-1"></i>{{ $policy ? 'Save Policy' : 'Create Policy' }}
        </button>
        @if(!$isAjax)
            <a href="{{ route('settings.academic.promotions.index') }}" class="btn btn-outline-secondary">Cancel</a>
        @else
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        @endif
    </div>
</form>

@endsection

@if(!$isAjax)
@push('scripts')
<script>
(function () {
    var classSel = document.getElementById('pp_class');
    var groupSel = document.getElementById('pp_group');
    if (!classSel || !groupSel) { return; }
    var map = @json($classGroups);
    var selectedId = groupSel.getAttribute('data-selected') || '';

    function renderGroups() {
        var options = '<option value="">Whole class</option>';
        var groups = map[classSel.value] || [];
        groups.forEach(function (g) {
            options += '<option value="' + g.id + '"' + (String(selectedId) === String(g.id) ? ' selected' : '') + '>' + g.name + '</option>';
        });
        groupSel.innerHTML = options;
    }

    classSel.addEventListener('change', renderGroups);
})();
</script>
@endpush
@endif
