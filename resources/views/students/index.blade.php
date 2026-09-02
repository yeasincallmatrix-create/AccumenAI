@extends('layouts.institute')

@php $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
@section('title', ($isProfessional ? mawa_lang('sidebar.trainees') : mawa_lang('sidebar.students')) . ' — AccumenAI')

@section('content')

<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div class="page-header-text">
        <h4 class="page-header-title">{{ $isProfessional ? mawa_e('sidebar.trainees') : mawa_e('sidebar.students') }}</h4>
    </div>
    <div class="page-header-actions">
        @if ($user->hasPermission('students.manage'))
            <a class="btn btn-primary" href="{{ route('students.create') }}">
                <i class="bi bi-plus-lg me-1"></i>{{ mawa_e('students.add_new') }}
            </a>
        @endif
    </div>
</div>

@include('students._tabs', ['activeTab' => 'students'])

@livewire('student-list')

@if ($user->hasPermission('students.manage'))
    @php
        $blankStudent = new \App\Models\Student(['status' => 'active', 'admission_date' => now()]);
    @endphp
    @include('students._edit_modal', ['student' => $blankStudent])
@endif
@endsection

@push('scripts')
<script>
(function () {
    var EDIT_DATA = @json($editData);
    var EDIT_URL_BASE = @json(url('students') . '/');
    var DEFAULT_COUNTRY_ID = @json($defaultCountryId ?? null);
    var modalEl = document.getElementById('editStudentModal');
    if (!modalEl) { return; }

    function setVal(id, val) {
        var el = document.getElementById(id);
        if (el && val !== undefined && val !== null) { el.value = val; }
    }

    function setPhone(input, val) {
        if (!input) { return; }
        var group = input.closest('.phone-country-group');
        if (!group) { input.value = val || ''; return; }
        var sel = group.querySelector('.phone-country-select');
        var number = String(val || '');
        if (number.charAt(0) === '+') {
            var digits = number.slice(1);
            var matched = null;
            for (var i = 0; i < sel.options.length; i++) {
                var code = String(sel.options[i].value).replace(/\D/g, '');
                if (digits.indexOf(code) === 0 && (matched === null || code.length > matched.length)) { matched = code; }
            }
            if (matched !== null) {
                sel.value = matched.replace(/\D/g, '');
                if (window.monetixPhoneSync) { window.monetixPhoneSync(sel); }
                input.value = digits.slice(matched.length);
                return;
            }
        }
        input.value = number.replace(/^\+/, '');
    }

    window.monetixFillEdit = function (id) {
        var d = EDIT_DATA[id] || {};
        var title = modalEl.querySelector('.modal-title');
        if (title) { title.textContent = 'Edit — ' + ((d.first_name || '') + ' ' + (d.last_name || '')).trim(); }
        var form = modalEl.querySelector('form');
        if (form) { form.action = EDIT_URL_BASE + (d.id || id); }

        setVal('e_student_id', id);
        setVal('e_return_to_list', '1');

        setVal('e_first_name', d.first_name);
        setVal('e_last_name', d.last_name);
        setVal('e_roll_number', d.roll_number);
        setVal('e_reg_no', d.reg_no);
        setVal('e_gender', d.gender);
        setVal('e_dob', d.dob);
        setVal('e_admission_date', d.admission_date);
        setPhone(document.getElementById('e_phone'), d.phone);
        setVal('e_email', d.email);
        setVal('e_religion', d.religion);
        setVal('e_status', d.status);
        setVal('e_father_name', d.father_name);
        setVal('e_mother_name', d.mother_name);
        setPhone(document.getElementById('e_guardian_phone'), d.guardian_phone);
        setVal('e_nationality', d.nationality);
        setVal('e_nid_number', d.nid_number);
        setVal('e_birth_cert_number', d.birth_cert_number);
        setVal('e_passport', d.passport_number);
        setVal('e_blood_group', d.blood_group);
        setVal('present_zip_code', d.present_zip_code);
        setVal('present_address', d.present_address);
        setVal('permanent_zip_code', d.permanent_zip_code);
        setVal('permanent_address', d.permanent_address);
        setVal('e_emergency_contact_name', d.emergency_contact_name);
        setPhone(document.getElementById('e_emergency_contact_phone'), d.emergency_contact_phone);

        setGeoValues('present_', {
            country_id: d.present_country_id,
            1: d.present_admin_1_id,
            2: d.present_admin_2_id,
            3: d.present_admin_3_id,
            zip_code: d.present_zip_code,
        });
        setGeoValues('permanent_', {
            country_id: d.permanent_country_id,
            1: d.permanent_admin_1_id,
            2: d.permanent_admin_2_id,
            3: d.permanent_admin_3_id,
            zip_code: d.permanent_zip_code,
        });
    };

    function setGeoValues(prefix, vals) {
        var root = document.querySelector('[data-address-component][data-prefix="' + prefix + '"]');
        if (!root || !root.setGeoValues) { return; }
        var countryId = vals.country_id;
        if (!countryId && DEFAULT_COUNTRY_ID) { countryId = DEFAULT_COUNTRY_ID; }
        var fill = {};
        for (var k in vals) { fill[k] = vals[k]; }
        fill.country_id = countryId;
        root.setGeoValues(countryId, fill);
    }

    if (window.Monetix && Monetix.delegate) {
        Monetix.delegate('click', '[data-edit-student]', function (e, btn) {
            monetixFillEdit(btn.getAttribute('data-edit-student'));
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }, 'mtx-student-edit');
    }

    @if ($editingStudent)
    (function () {
        var title = document.querySelector('#editStudentModal .modal-title');
        if (title) { title.textContent = 'Edit — {{ $editingStudent->full_name }}'; }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    })();
    @endif
})();
</script>
@endpush