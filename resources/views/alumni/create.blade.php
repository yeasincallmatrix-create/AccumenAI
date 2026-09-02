@extends('layouts.institute')

@section('title', mawa_e('alumni.add_alumni') . ' — AccumenAI')

@section('content')
<div class="page-header">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ mawa_e('alumni.add_alumni') }}</h4>
        <p class="page-header-desc">{{ mawa_e('alumni.create_desc') }}</p>
    </div>
    <div class="page-header-actions d-flex gap-2">
        <a class="btn btn-outline-primary" href="{{ route('alumni.directory') }}">
            <i class="bi bi-people-fill me-1"></i>{{ mawa_e('alumni.nav_directory') }}
        </a>
    </div>
</div>

<div class="admin-card">
    <div class="mb-3">
        <label class="form-label">{{ mawa_e('alumni.search_students') }}</label>
        <input type="text" id="alumni-student-search" class="form-control"
               placeholder="{{ mawa_e('alumni.search_placeholder') }}" autocomplete="off">
        <div class="form-text">{{ mawa_e('alumni.eligible_hint') }}</div>
    </div>

    <div id="alumni-search-results">
        @forelse ($recent as $row)
            @include('alumni.partials.student-row', ['row' => $row])
        @empty
            <p class="text-muted mb-0">{{ mawa_e('alumni.start_typing') }}</p>
        @endforelse
    </div>
</div>

<div class="admin-card" id="alumni-add-card" style="display:none">
    <h5 class="mb-3">{{ mawa_e('alumni.confirm_activation') }}</h5>
    <p class="text-muted">
        {{ mawa_e('alumni.activation_desc') }}
    </p>
    <form method="POST" action="{{ route('alumni.store') }}">
        @csrf
        <input type="hidden" name="student_id" id="alumni-student-id" value="">
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label">{{ mawa_e('alumni.reference_number') }} <span class="text-muted">{{ mawa_e('alumni.optional') }}</span></label>
                <input type="text" name="alumni_reference_number" class="form-control" maxlength="40"
                       value="{{ old('alumni_reference_number') }}">
                @error('alumni_reference_number')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label class="form-label">{{ mawa_e('alumni.graduation_date') }}</label>
                <input type="date" name="graduation_date" class="form-control" value="{{ old('graduation_date') }}">
                <div class="form-text">{{ mawa_e('alumni.graduation_date_hint') }}</div>
                @error('graduation_date')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>
        </div>
        @error('student_id')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror
        <button type="submit" class="btn btn-primary"><i class="bi bi-award-fill me-1"></i>{{ mawa_e('alumni.activate_profile') }}</button>
        <button type="button" class="btn btn-outline-secondary" id="alumni-add-cancel">{{ mawa_e('alumni.cancel') }}</button>
    </form>
</div>
@endsection

@php
    $jsTranslations = [
        'start_typing' => mawa_e('alumni.start_typing'),
        'no_students'  => mawa_e('alumni.no_students'),
        'eligible'     => mawa_e('alumni.badge_eligible'),
        'not_eligible' => mawa_e('alumni.badge_not_eligible'),
        'add'          => mawa_e('alumni.add_btn'),
    ];
@endphp

@push('scripts')
<script>
(function () {
    var T = @json($jsTranslations);
    var input = document.getElementById('alumni-student-search');
    var results = document.getElementById('alumni-search-results');
    var addCard = document.getElementById('alumni-add-card');
    var studentId = document.getElementById('alumni-student-id');
    var confirmName = document.getElementById('alumni-confirm-name');
    var timer = null;

    if (!input || !results) { return; }

    function showAdd(button) {
        studentId.value = button.getAttribute('data-student-id');
        confirmName.textContent = button.getAttribute('data-student-name');
        addCard.style.display = '';
        addCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    document.getElementById('alumni-add-cancel').addEventListener('click', function () {
        addCard.style.display = 'none';
        studentId.value = '';
    });

    results.addEventListener('click', function (event) {
        var button = event.target.closest('[data-add-alumni]');
        if (button) { showAdd(button); }
    });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q === '') {
            results.innerHTML = '<p class="text-muted mb-0">' + T.start_typing + '</p>';
            return;
        }
        timer = setTimeout(function () {
            fetch('{{ route('alumni.students.search') }}?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.results || !data.results.length) {
                    results.innerHTML = '<p class="text-muted mb-0">' + T.no_students + '</p>';
                    return;
                }
                var html = '';
                data.results.forEach(function (student) {
                    var name = student.name + (student.reg_no ? ' (' + student.reg_no + ')' : '');
                    var outcome = student.outcome ? 'Outcome: ' + student.outcome : '';
                    var badge = student.eligible
                        ? '<span class="badge text-bg-success">' + T.eligible + '</span>'
                        : '<span class="badge text-bg-secondary">' + T.not_eligible + '</span>';
                    var action = student.eligible
                        ? '<button type="button" class="btn btn-sm btn-outline-primary" data-add-alumni data-student-id="' + student.id + '" data-student-name="' + name.replace(/"/g, '&quot;') + '"><i class="bi bi-plus-lg me-1"></i>' + T.add + '</button>'
                        : '';
                    html += '<div class="d-flex align-items-center justify-content-between py-2 border-bottom">'
                        + '<div><span class="fw-semibold">' + name + '</span>'
                        + '<div class="text-muted small">' + outcome + '</div></div>'
                        + '<div class="d-flex gap-2 align-items-center">' + badge + action + '</div>'
                        + '</div>';
                });
                results.innerHTML = html;
            });
        }, 250);
    });
})();
</script>
@endpush
