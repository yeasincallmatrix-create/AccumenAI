@extends('layouts.institute')

@section('title', 'Dashboard — AccumenAI')

@section('content')

@include('dashboard._tabs', ['activeTab' => 'dashboard'])

@if (($isHospitality ?? false))
    {{-- Clean Hospitality Dashboard — restaurant / hotels (no student) --}}
    <div class="page-header">
        <div class="page-header-text">
            <h4 class="page-header-title">{{ mawa_lang('inst_dashboard.welcome', ['name' => $user->name ?? 'Admin']) }}</h4>
            <p class="page-header-desc">{{ $institute->name ?? 'Your restaurant' }} — Welcome</p>
        </div>
    </div>
    <div class="dash-card text-center py-5">
        <div class="mb-3" style="font-size:2.5rem; color:var(--primary);"><i class="bi bi-shop"></i></div>
        <h5 class="mb-2">Welcome to {{ $institute->name ?? 'your restaurant' }}</h5>
        <p class="text-muted mb-0">Your dashboard is ready. Manage orders, menu and inventory from the sidebar.</p>
    </div>

@elseif (($isCleanStudent ?? false))
    {{-- Clean Student Dashboard — non-education industries --}}
    <div class="page-header">
        <div class="page-header-text">
            <h4 class="page-header-title">{{ mawa_lang('inst_dashboard.welcome', ['name' => $user->name ?? 'Admin']) }}</h4>
            <p class="page-header-desc">{{ $institute->name ?? 'Your institute' }} — Student Overview</p>
        </div>
        <a href="{{ route('students.index') }}" class="btn btn-primary btn-sm rounded-pill"><i class="bi bi-people me-1"></i> All Students</a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(13,110,253,.1); color:var(--primary);"><i class="bi bi-people-fill"></i></div>
                <div class="num">{{ $stats['total'] }}</div>
                <div class="label">Total Students</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;"><i class="bi bi-person-check-fill"></i></div>
                <div class="num">{{ $stats['active'] }}</div>
                <div class="label">Active</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;"><i class="bi bi-person-plus-fill"></i></div>
                <div class="num">{{ $stats['newThisMonth'] }}</div>
                <div class="label">New This Month</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(13,202,240,.12); color:#0aa2c0;"><i class="bi bi-mortarboard-fill"></i></div>
                <div class="num">{{ $stats['completed'] }}</div>
                <div class="label">Completed</div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="dash-card h-100">
                <h6 class="mb-3"><i class="bi bi-pie-chart me-2"></i>By Status</h6>
                @forelse ($byStatus as $status => $total)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-capitalize">{{ $status }}</span>
                        <span class="badge text-bg-light border">{{ $total }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No data</p>
                @endforelse
            </div>
        </div>
        <div class="col-md-8">
            <div class="dash-card h-100">
                <h6 class="mb-3"><i class="bi bi-diagram-3 me-2"></i>By Branch</h6>
                @forelse ($byBranch as $row)
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span>{{ $row->branch?->name ?? 'No branch' }}</span>
                        <span class="badge text-bg-primary">{{ $row->total }}</span>
                    </div>
                @empty
                    <p class="text-muted small mb-0">No branch data</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-person-vcard me-2"></i> Recent Students</h6>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('students.index') }}">{{ mawa_e('inst_dashboard.view_all') }}</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>ID</th>
                        <th>Phone</th>
                        <th>Branch</th>
                        <th>Admission</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentStudents as $student)
                        <tr>
                            <td><img src="{{ $student->photo ? $student->photo_url : 'https://ui-avatars.com/api/?name='.urlencode($student->full_name).'&background=0d6efd&color=fff&size=32' }}" class="rounded-circle" width="32" height="32" alt=""></td>
                            <td class="fw-semibold">{{ $student->full_name }}</td>
                            <td class="text-muted">{{ $student->student_id }}</td>
                            <td>{{ $student->phone ?? '—' }}</td>
                            <td>{{ $student->branch?->name ?? '—' }}</td>
                            <td>{{ $student->admission_date?->format('d M Y') ?? '—' }}</td>
                            <td><span class="badge {{ ['active'=>'text-bg-success','completed'=>'text-bg-primary','dropped'=>'text-bg-secondary','suspended'=>'text-bg-danger'][$student->status] ?? 'text-bg-secondary' }}">{{ ucfirst($student->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">{{ mawa_e('inst_dashboard.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@elseif (auth('institute_user')->check() || auth('web')->check())
    <div class="page-header">
        <div class="page-header-text">
            <h4 class="page-header-title">{{ mawa_lang('inst_dashboard.welcome', ['name' => $user->name ?? 'Admin']) }}</h4>
            <p class="page-header-desc">{{ $institute->name ?? 'Your academy' }} — {{ mawa_e('inst_dashboard.subtitle') }}</p>
            @if(isset($institute) && $institute?->uid)<div class="mt-1"><x-uid-with-copy :uid="$institute->uid" label="Institute UID" /></div>@endif
            @if(isset($user) && $user?->uid)<div class="mt-1"><x-uid-with-copy :uid="$user->uid" label="Your UID" /></div>@endif
        </div>
    </div>

    @php $isProfessionalHome = \App\Support\InstituteDomain::isProfessional($institute ?? null); @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(13,110,253,.1); color:var(--primary);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="num">{{ $stats['students'] }}</div>
                <div class="label">{{ $isProfessionalHome ? mawa_e('sidebar.trainees') : mawa_e('inst_dashboard.stat_students') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;">
                    <i class="bi bi-play-circle-fill"></i>
                </div>
                <div class="num">{{ $stats['runningBatches'] }}</div>
                <div class="label">{{ mawa_e('inst_dashboard.stat_running') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="icon" style="background:rgba(255,193,7,.15); color:#b8860b;">
                    <i class="bi bi-collection-fill"></i>
                </div>
                <div class="num">{{ $stats['batches'] }}</div>
                <div class="label">{{ mawa_e('inst_dashboard.stat_batches') }}</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <a href="{{ $usesClassTerm ? route('classes.index') : route('courses.manage.index') }}" class="text-decoration-none">
                <div class="stat-card">
                    <div class="icon" style="background:rgba(220,53,69,.1); color:#dc3545;">
                        <i class="bi bi-journal-bookmark-fill"></i>
                    </div>
                    <div class="num">{{ $stats['assignedCourses'] }}</div>
                    <div class="label">{{ $usesClassTerm ? mawa_e('inst_dashboard.stat_classes') : mawa_e('inst_dashboard.stat_courses') }}</div>
                </div>
            </a>
        </div>
    </div>

    @if (! empty($crmSummary) || ! empty($financeSummary))
        <div class="row g-3 mb-4">
            @if (! empty($crmSummary))
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(13,110,253,.1); color:var(--primary);">
                            <i class="bi bi-person-lines-fill"></i>
                        </div>
                        <div class="num">{{ $crmSummary['contacts'] }}</div>
                        <div class="label">{{ mawa_e('inst_dashboard.stat_crm_contacts') }}</div>
                        <div class="stat-sub">{{ $crmSummary['open_leads'] }} {{ mawa_e('inst_dashboard.stat_crm_open_leads') }}</div>
                    </div>
                </div>
            @endif
            @if (! empty($financeSummary))
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="icon" style="background:rgba(25,135,84,.1); color:#198754;">
                            <i class="bi bi-cash-coin"></i>
                        </div>
                        <div class="num">{{ number_format((float) $financeSummary['receivable'], 2) }}</div>
                        <div class="label">{{ mawa_e('inst_dashboard.stat_finance_receivable') }}</div>
                        <div class="stat-sub">{{ number_format((float) $financeSummary['net_income'], 2) }} {{ mawa_e('inst_dashboard.stat_finance_net_income') }}</div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="dash-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0"><i class="bi bi-person-vcard me-2"></i> {{ mawa_e('inst_dashboard.recent_admissions') }}</h6>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('students.index') }}">{{ mawa_e('inst_dashboard.view_all') }}</a>
        </div>

        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ mawa_e('inst_dashboard.table_name') }}</th>
                        <th>{{ mawa_e('inst_dashboard.table_student_id') }}</th>
                        <th>{{ mawa_e('inst_dashboard.table_admission') }}</th>
                        <th>{{ mawa_e('inst_dashboard.table_status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentAdmissions as $student)
                        <tr>
                            <td class="fw-semibold">{{ $student->full_name }}</td>
                            <td class="text-muted">{{ $student->student_id }}</td>
                            <td>{{ $student->admission_date->format('d M Y') }}</td>
                            <td>
                                @php
                                    $badge = [
                                        'active' => 'text-bg-success',
                                        'completed' => 'text-bg-primary',
                                        'dropped' => 'text-bg-secondary',
                                        'suspended' => 'text-bg-danger',
                                    ];
                                @endphp
                                <span class="badge {{ $badge[$student->status] ?? 'text-bg-secondary' }}">{{ mawa_e('status.' . $student->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">{{ mawa_e('inst_dashboard.empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

@auth('platform_admin')
    @php
        $statMeta = [
            'institutes'     => ['bi-building-fill',        'rgba(13,110,253,.12)',  '#0d6efd'],
            'students'       => ['bi-people-fill',          'rgba(25,135,84,.12)',   '#198754'],
            'courses'        => ['bi-journal-bookmark-fill','rgba(255,193,7,.15)',   '#d97706'],
            'batches'        => ['bi-collection-fill',      'rgba(111,66,193,.12)',  '#6f42c1'],
            'instituteUsers' => ['bi-person-badge-fill',    'rgba(13,110,253,.10)',  '#0d6efd'],
            'exams'          => ['bi-clipboard-check-fill', 'rgba(13,202,240,.15)',  '#0aa2c0'],
            'results'        => ['bi-award-fill',           'rgba(255,193,7,.15)',   '#b8860b'],
            'certificates'   => ['bi-patch-check-fill',     'rgba(25,135,84,.12)',   '#198754'],
        ];
        $statRoutes = [
            'institutes'     => route('admin.institutes.index'),
            'students'       => route('admin.students.index'),
            'courses'        => route('admin.courses.index'),
            'instituteUsers' => route('admin.settings.staff'),
            'certificates'   => route('admin.certificates.index'),
        ];
    @endphp

    <div class="page-header">
        <div class="page-header-text">
            <h4 class="page-header-title">{{ mawa_e('sidebar.dashboard') }}</h4>
            <p class="page-header-desc">{{ mawa_lang('dashboard.admin_welcome', ['name' => $roleLabel]) }}</p>
        </div>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="dropdown country-filter">
                <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by country">
                    <i class="bi bi-globe2"></i>
                    <span class="country-filter-label">
                        @if (isset($country) && $country)
                            <img src="{{ mawa_country_flag($country) }}" class="country-flag me-1" alt="" width="18" height="13">
                            {{ config('countries')[$country] ?? $country }}
                        @else
                            {{ mawa_e('dashboard.all_countries') }}
                        @endif
                    </span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
            <ul class="dropdown-menu dropdown-menu-end country-dropdown">
                <li>
                    <a class="dropdown-item {{ ($country ?? null) === null ? 'active' : '' }}"
                       href="{{ route('dashboard', array_filter(['industry' => $industry ?? null, 'sub_industry' => $subIndustry ?? null, 'country' => null], fn ($v) => $v !== null)) }}">
                        <i class="bi bi-globe me-2"></i>{{ mawa_e('dashboard.all_countries') }}
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <div class="dropdown-item-country-search">
                        <i class="bi bi-search"></i>
                        <input type="text" class="country-search-input" placeholder="Search country..." autocomplete="off">
                    </div>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <ul class="country-dropdown-list">
                    @foreach (config('countries') as $value => $label)
                        <li>
                            <a class="dropdown-item country-item {{ ($country ?? null) === $value ? 'active' : '' }}"
                               href="{{ route('dashboard', array_filter(['industry' => $industry ?? null, 'sub_industry' => $subIndustry ?? null, 'country' => $value], fn ($v) => $v !== null)) }}">
                                <img src="{{ mawa_country_flag($value) }}" class="country-flag" alt="" width="18" height="13">
                                <span class="ms-2">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                    </ul>
                </li>
            </ul>
            </div>
            <div class="dropdown industry-filter">
                <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ mawa_e('dashboard.filter_industry') }}">
                    <i class="bi bi-funnel-fill"></i>
                    <span class="industry-filter-label">
                        {{ (isset($industry) && $industry ? ($industries[$industry] ?? $industry) : mawa_e('dashboard.all_industries')) }}
                    </span>
                    <i class="bi bi-chevron-down small"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item {{ ($industry ?? null) === null ? 'active' : '' }}"
                           href="{{ route('dashboard', array_filter(['country' => $country ?? null, 'sub_industry' => $subIndustry ?? null, 'industry' => null], fn ($v) => $v !== null)) }}">
                            <i class="bi bi-collection me-2"></i>{{ mawa_e('dashboard.all_industries') }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    @foreach ($industries as $value => $label)
                        <li>
                            <a class="dropdown-item {{ ($industry ?? null) === $value ? 'active' : '' }}"
                               href="{{ route('dashboard', array_filter(['country' => $country ?? null, 'sub_industry' => $subIndustry ?? null, 'industry' => $value], fn ($v) => $v !== null)) }}">
                                <i class="bi bi-building me-2"></i>{{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            @if (isset($industry) && $industry && count($subIndustries) > 0)
                <div class="dropdown sub-industry-filter">
                    <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Filter by sub industry">
                        <i class="bi bi-diagram-2-fill"></i>
                        <span class="sub-industry-filter-label">
                            {{ ($subIndustry ?? null) ? ($subIndustries[$subIndustry] ?? $subIndustry) : mawa_e('dashboard.all_sub_industries') }}
                        </span>
                        <i class="bi bi-chevron-down small"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ ($subIndustry ?? null) === null ? 'active' : '' }}"
                               href="{{ route('dashboard', array_filter(['country' => $country ?? null, 'industry' => $industry, 'sub_industry' => null], fn ($v) => $v !== null)) }}">
                                <i class="bi bi-collection me-2"></i>{{ mawa_e('dashboard.all_sub_industries') }}
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @foreach ($subIndustries as $subValue => $subLabel)
                            <li>
                                <a class="dropdown-item {{ ($subIndustry ?? null) === $subValue ? 'active' : '' }}"
                                   href="{{ route('dashboard', array_filter(['country' => $country ?? null, 'industry' => $industry, 'sub_industry' => $subValue], fn ($v) => $v !== null)) }}">
                                    <i class="bi bi-diagram-2 me-2"></i>{{ $subLabel }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        @foreach ($stats as $label => $count)
            @php
                $meta = $statMeta[$label] ?? ['bi-circle-fill', 'rgba(13,110,253,.10)', '#0d6efd'];
                $title = mawa_lang('dashboard.stat_' . $label);
            @endphp
            <div class="col-6 col-md-3">
                @php $statRoute = $statRoutes[$label] ?? null; @endphp
                @if ($statRoute)
                    <a href="{{ $statRoute }}" class="text-decoration-none">
                @endif
                <div class="stat-card {{ $statRoute ? 'stat-clickable' : '' }}">
                    <div class="icon" style="background:{{ $meta[1] }}; color:{{ $meta[2] }};">
                        <i class="bi {{ $meta[0] }}"></i>
                    </div>
                    <div class="num">{{ $count }}</div>
                    <div class="label">{{ $title }}</div>
                    @if ($statRoute)
                        <div class="go"><i class="bi bi-arrow-right"></i> {{ mawa_e('dashboard.view_all') }}</div>
                    @endif
                </div>
                @if ($statRoute)
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info">
                <i class="bi bi-inbox"></i> {{ mawa_e('dashboard.course_requests') }}
                @if ($pendingCourseRequests->count() > 0)
                    <span class="badge text-bg-warning ms-2">{{ $pendingCourseRequests->count() }} pending</span>
                @endif
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.courses.requests') }}">{{ mawa_e('dashboard.view_all') }} <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ mawa_e('dashboard.table_institute') }}</th>
                        <th>{{ mawa_e('dashboard.table_course') }}</th>
                        <th>Requested by</th>
                        <th>{{ mawa_e('dashboard.table_admission') }}</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingCourseRequests as $courseRequest)
                        <tr>
                            <td class="fw-semibold">{{ $courseRequest->institute->name ?? '—' }}</td>
                            <td>{{ $courseRequest->course->name ?? '—' }}</td>
                            <td class="text-muted">{{ $courseRequest->requestedBy->name ?? '—' }}</td>
                            <td class="text-muted">{{ $courseRequest->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.courses.requests', ['status' => 'pending']) }}">{{ mawa_e('dashboard.view_all') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">{{ mawa_e('dashboard.no_course_requests') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-person-badge"></i> {{ mawa_e('dashboard.latest_students') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ mawa_e('dashboard.table_student') }}</th>
                        <th>{{ mawa_e('dashboard.table_institute') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($latestStudents as $student)
                        <tr>
                            <td class="fw-semibold">{{ $student->full_name }}</td>
                            <td class="text-muted">{{ $student->institute->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center text-muted py-4">{{ mawa_e('dashboard.no_students') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="admin-card">
        <div class="table-toolbar">
            <div class="toolbar-info"><i class="bi bi-building"></i> {{ mawa_e('dashboard.institutes_by_students') }}</div>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ mawa_e('dashboard.table_institute') }}</th>
                        <th class="text-end">{{ mawa_e('dashboard.table_students') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($institutes as $inst)
                        <tr>
                            <td class="fw-semibold">{{ $inst->name }}</td>
                            <td class="text-end"><span class="badge text-bg-primary">{{ $inst->students_count }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endauth

@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.country-dropdown').forEach(function (menu) {
        var input = menu.querySelector('.country-search-input');
        if (!input) { return; }
        var items = menu.querySelectorAll('.country-dropdown-list .country-item');
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            items.forEach(function (item) {
                item.style.display = item.textContent.trim().toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
        input.addEventListener('click', function (e) { e.stopPropagation(); });
    });
})();
</script>
@endpush