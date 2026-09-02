@php
    $isProfessional = \App\Support\InstituteDomain::isProfessional($institute ?? null);
    $statusBadge = [
        'active'    => 'bg-success',
        'completed' => 'bg-primary',
        'dropped'   => 'bg-secondary',
        'suspended' => 'bg-danger',
    ];
    $statusNames = [
        'active' => mawa_lang('status.active'),
        'completed' => mawa_lang('status.completed'),
        'dropped' => mawa_lang('status.dropped'),
        'suspended' => mawa_lang('status.suspended'),
    ];
@endphp

<div class="admin-card" id="printArea">

    <div class="print-header d-none">
        <h4 class="mb-1">{{ $institute->name ?? '' }} — {{ $isProfessional ? mawa_e('sidebar.trainees') : mawa_e('sidebar.students') }}</h4>
        <p class="mb-0 text-muted">{{ $students->total() }} {{ $isProfessional ? strtolower(mawa_e('sidebar.trainees')) : 'students' }} · {{ now()->format('d M Y') }}</p>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3 align-items-end monetix-print-hidden">
        <div style="flex:1 1 280px;min-width:220px">
            <input type="text" class="form-control" wire:model.live.debounce.350ms="search"
                   placeholder="{{ $isProfessional ? 'Search name, trainee ID, registration no…' : mawa_e('students.search_placeholder') }}">
        </div>
        <select class="form-select form-select-sm" style="width:auto" wire:model.live="filters.status">
            <option value="">{{ mawa_e('students.all_status') ?? 'All Status' }}</option>
            @foreach ($statusNames as $slug => $label)
                <option value="{{ $slug }}">{{ $label }}</option>
            @endforeach
        </select>
        <select class="form-select form-select-sm" style="width:auto" wire:model.live="filters.gender">
            <option value="">{{ mawa_e('students.all_genders') ?? 'All Genders' }}</option>
            <option value="male">{{ mawa_e('options.gender_male') }}</option>
            <option value="female">{{ mawa_e('options.gender_female') }}</option>
            <option value="other">{{ mawa_e('options.gender_other') }}</option>
        </select>
        <select class="form-select form-select-sm" style="width:auto" wire:model.live="filters.religion">
            <option value="">{{ mawa_e('students.all_religions') ?? 'All Religions' }}</option>
            <option value="Islam">{{ mawa_e('options.religion_islam') }}</option>
            <option value="Hindu">{{ mawa_e('options.religion_hindu') }}</option>
            <option value="Buddhist">{{ mawa_e('options.religion_buddhist') }}</option>
            <option value="Christian">{{ mawa_e('options.religion_christian') }}</option>
            <option value="Other">{{ mawa_e('options.religion_other') }}</option>
        </select>
        @if ($branches->count())
            <select class="form-select form-select-sm" style="width:auto" wire:model.live="filters.branch_id">
                <option value="">{{ mawa_e('students.all_branches') ?? 'All Branches' }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        @endif
        <button type="button" class="btn btn-outline-secondary" wire:click="resetFilters" title="{{ mawa_e('actions.reset') }}">
            <i class="bi bi-arrow-counterclockwise"></i>
        </button>
        <div class="ms-auto d-flex gap-2 align-items-center">
            <div class="dropdown">
                <button type="button" class="candle-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-layout-three-columns me-1"></i>{{ mawa_e('actions.columns') }} <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">{{ mawa_e('actions.show_hide_columns') }}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ([
                        'serial'    => '#',
                        'no'        => mawa_e('students.table_no'),
                        'uid'       => 'Student UID',
                        'roll'      => mawa_e('students.roll_number'),
                        'name'      => mawa_e('students.table_name'),
                        'phone'     => mawa_e('students.table_phone'),
                        'email'     => mawa_e('students.table_email'),
                        'reg'       => mawa_e('students.table_reg_no'),
                        'gender'    => mawa_e('students.table_gender'),
                        'dob'       => mawa_e('students.table_dob'),
                        'age'       => mawa_e('students.table_age'),
                        'blood'     => mawa_e('students.table_blood'),
                        'religion'  => mawa_e('students.table_religion'),
                        'nationality' => mawa_e('students.table_nationality'),
                        'nid'       => mawa_e('students.table_nid'),
                        'passport'  => mawa_e('students.table_passport'),
                        'branch'    => mawa_e('students.table_branch'),
                        'guardian'  => mawa_e('students.table_guardian'),
                        'admission' => mawa_e('students.table_admission'),
                        'status'    => mawa_e('students.table_status'),
                        'action'    => mawa_e('actions.actions'),
                    ] as $col => $label)
                        <li>
                            <label class="dropdown-item" for="student-col-{{ $col }}">
                                <input type="checkbox" id="student-col-{{ $col }}" class="form-check-input me-2"
                                       wire:click="toggleColumn('{{ $col }}')"
                                       @checked(in_array($col, $visibleColumns, true))>
                                {{ $label }}
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="candle-btn-group">
                <button type="button" class="candle-btn" title="{{ mawa_e('actions.print') }}" onclick="window.print()">{{ mawa_e('actions.print') }}</button>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center monetix-print-hidden" style="width:32px">
                        <input type="checkbox" class="form-check-input" id="monetixSelectAll" aria-label="Select all">
                    </th>
                    @if (in_array('serial', $visibleColumns, true))<th class="text-muted">#</th>@endif
                    @if (in_array('no', $visibleColumns, true))<th>{{ mawa_e('students.table_no') }}</th>@endif
                    @if (in_array('uid', $visibleColumns, true))<th>Student UID</th>@endif
                    @if (in_array('roll', $visibleColumns, true))<th>{{ mawa_e('students.roll_number') }}</th>@endif
                    @if (in_array('name', $visibleColumns, true))<th>{{ mawa_e('students.table_name') }}</th>@endif
                    @if (in_array('phone', $visibleColumns, true))<th>{{ mawa_e('students.table_phone') }}</th>@endif
                    @if (in_array('email', $visibleColumns, true))<th>{{ mawa_e('students.table_email') }}</th>@endif
                    @if (in_array('reg', $visibleColumns, true))<th>{{ mawa_e('students.table_reg_no') }}</th>@endif
                    @if (in_array('gender', $visibleColumns, true))<th>{{ mawa_e('students.table_gender') }}</th>@endif
                    @if (in_array('dob', $visibleColumns, true))<th>{{ mawa_e('students.table_dob') }}</th>@endif
                    @if (in_array('age', $visibleColumns, true))<th>{{ mawa_e('students.table_age') }}</th>@endif
                    @if (in_array('blood', $visibleColumns, true))<th>{{ mawa_e('students.table_blood') }}</th>@endif
                    @if (in_array('religion', $visibleColumns, true))<th>{{ mawa_e('students.table_religion') }}</th>@endif
                    @if (in_array('nationality', $visibleColumns, true))<th>{{ mawa_e('students.table_nationality') }}</th>@endif
                    @if (in_array('nid', $visibleColumns, true))<th>{{ mawa_e('students.table_nid') }}</th>@endif
                    @if (in_array('passport', $visibleColumns, true))<th>{{ mawa_e('students.table_passport') }}</th>@endif
                    @if (in_array('branch', $visibleColumns, true))<th>{{ mawa_e('students.table_branch') }}</th>@endif
                    @if (in_array('guardian', $visibleColumns, true))<th>{{ mawa_e('students.table_guardian') }}</th>@endif
                    @if (in_array('admission', $visibleColumns, true))<th>{{ mawa_e('students.table_admission') }}</th>@endif
                    @if (in_array('status', $visibleColumns, true))<th>{{ mawa_e('students.table_status') }}</th>@endif
                    @if (in_array('action', $visibleColumns, true))<th class="text-end monetix-print-hidden">{{ mawa_e('actions.actions') }}</th>@endif
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    <tr>
                        <td class="text-center monetix-print-hidden">
                            <input type="checkbox" class="form-check-input monetix-check" value="{{ $student->id }}" aria-label="{{ $student->full_name }}">
                        </td>
                        @if (in_array('serial', $visibleColumns, true))<td class="text-muted">{{ $students->firstItem() + $loop->index }}</td>@endif
                        @if (in_array('no', $visibleColumns, true))<td class="fw-semibold">{{ $student->student_id }}</td>@endif
                        @if (in_array('uid', $visibleColumns, true))<td>@if(isset($student->uid) && $student->uid)<x-uid-with-copy :uid="$student->uid" />@else<span class="text-muted">—</span>@endif</td>@endif
                        @if (in_array('roll', $visibleColumns, true))<td>
                            @if ($student->roll_number || $student->reg_no)
                                <span class="fw-semibold text-primary">{{ $student->roll_number ?: $student->reg_no }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>@endif
                        @if (in_array('name', $visibleColumns, true))<td>
                            <a class="fw-semibold text-decoration-none" href="{{ route('students.show', $student) }}">{{ $student->full_name }}</a>
                        </td>@endif
                        @if (in_array('phone', $visibleColumns, true))<td>{{ $student->phone ?? '—' }}</td>@endif
                        @if (in_array('email', $visibleColumns, true))<td>{{ $student->email ?? '—' }}</td>@endif
                        @if (in_array('reg', $visibleColumns, true))<td>{{ $student->reg_no ?? '—' }}</td>@endif
                        @if (in_array('gender', $visibleColumns, true))<td>{{ $student->gender ?: '—' }}</td>@endif
                        @if (in_array('dob', $visibleColumns, true))<td>{{ $student->dob?->format('d M Y') ?? '—' }}</td>@endif
                        @if (in_array('age', $visibleColumns, true))<td>
                            @if ($student->dob && ! $student->dob->isFuture())
                                {{ $student->dob->diff(now())->format('%y') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>@endif
                        @if (in_array('blood', $visibleColumns, true))<td>{{ $student->blood_group ?? '—' }}</td>@endif
                        @if (in_array('religion', $visibleColumns, true))<td>{{ $student->religion ?? '—' }}</td>@endif
                        @if (in_array('nationality', $visibleColumns, true))<td>{{ $student->nationality ?? '—' }}</td>@endif
                        @if (in_array('nid', $visibleColumns, true))<td>{{ $student->nid_number ?? '—' }}</td>@endif
                        @if (in_array('passport', $visibleColumns, true))<td>{{ $student->passport_number ?? '—' }}</td>@endif
                        @if (in_array('branch', $visibleColumns, true))<td>{{ $student->branch->name ?? '—' }}</td>@endif
                        @if (in_array('guardian', $visibleColumns, true))<td>{{ $student->guardian_phone ?? '—' }}</td>@endif
                        @if (in_array('admission', $visibleColumns, true))<td>{{ $student->admission_date?->format('d M Y') }}</td>@endif
                        @if (in_array('status', $visibleColumns, true))<td>
                            <span class="badge {{ $statusBadge[$student->status] ?? 'bg-secondary' }}">{{ $statusNames[$student->status] ?? $student->status }}</span>
                        </td>@endif
                        @if (in_array('action', $visibleColumns, true))<td class="text-end monetix-print-hidden">
                            @if ($user->hasPermission('students.manage'))
                                <button type="button" class="btn btn-sm btn-outline-primary" data-edit-student="{{ $student->id }}" title="{{ mawa_e('actions.edit') }}" style="min-height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form class="d-inline" method="POST" action="{{ route('students.destroy', $student) }}"
                                      data-ajax-delete="1" data-confirm="{{ mawa_lang('students.confirm_delete_name', ['name' => $student->full_name]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" type="submit" title="{{ mawa_e('actions.delete') }}" style="min-height:32px;display:inline-flex;align-items:center;justify-content:center;padding:0 8px">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>@endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="99" class="text-center text-muted py-4">{{ $isProfessional ? 'No trainees found.' : mawa_e('students.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <nav class="mt-4 pt-2 d-flex flex-column align-items-center gap-2 monetix-print-hidden">
        {{ $students->links('pagination::bootstrap-5') }}
        @if ($students->total() > 0)
            <span class="text-muted small">
                {{ mawa_lang('students.showing_results', [
                    'from'  => $students->firstItem() ?? 0,
                    'to'    => $students->lastItem() ?? 0,
                    'total' => $students->total(),
                ]) }}
            </span>
        @endif
    </nav>

</div>

<style>
.candle-btn-group{ display:flex; width:fit-content; }
.candle-btn{
    padding:8px 14px;
    font-size:14px;
    font-weight:600;
    color:#198754;
    background-color:transparent;
    border:1px solid #198754;
    border-radius:6px;
    display:inline-flex;
    align-items:center;
    cursor:pointer;
    transition:background-color .15s ease-in-out,color .15s ease-in-out;
}
.candle-btn:hover{
    background-color:#198754;
    color:#fff;
}
.candle-btn-group .candle-btn + .candle-btn{ border-left:none; border-radius:0; }
.candle-btn-group .candle-btn:first-child{ border-top-left-radius:6px; border-bottom-left-radius:6px; }
.candle-btn-group .candle-btn:last-child{ border-top-right-radius:6px; border-bottom-right-radius:6px; }
@media print {
    .topbar, .sidebar, .page-header, .monetix-print-hidden { display: none !important; }
    .layout { display: block !important; min-height: 0 !important; }
    .content { width: 100% !important; margin: 0 !important; padding: 0 !important; }
    .admin-card { box-shadow: none !important; border: none !important; }
    #printArea, #printArea .table { background: #fff !important; color: #000 !important; }
    #printArea { padding: 0 !important; }
    .print-header { display: block !important; margin-bottom: 12px; }
    .table-responsive { overflow: visible !important; }
    .table { width: 100% !important; border-collapse: collapse; }
}
</style>

<script>
(function () {
    var all = document.getElementById('monetixSelectAll');
    var boxes = document.querySelectorAll('.monetix-check');
    if (!all || !boxes.length) { return; }
    all.addEventListener('change', function () {
        boxes.forEach(function (b) { b.checked = all.checked; });
    });
    boxes.forEach(function (b) {
        b.addEventListener('change', function () {
            var checked = Array.prototype.filter.call(boxes, function (x) { return x.checked; }).length;
            all.checked = checked === boxes.length;
        });
    });
})();
</script>
